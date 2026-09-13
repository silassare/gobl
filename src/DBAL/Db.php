<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL;

use Closure;
use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Constraints\Constraint;
use Gobl\DBAL\Constraints\ForeignKeyAction;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Indexes\Index;
use Gobl\DBAL\Indexes\IndexType;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Relations\LinkThrough;
use Gobl\DBAL\Relations\LinkType;
use Gobl\DBAL\Relations\ManyToMany;
use Gobl\DBAL\Relations\ManyToOne;
use Gobl\DBAL\Relations\OneToMany;
use Gobl\DBAL\Relations\OneToOne;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\RelationType;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\Gobl;
use InvalidArgumentException;
use Override;
use PDO;
use PHPUtils\Lock\Traits\PermanentlyLockableTrait;
use PHPUtils\Store\Map;
use Throwable;

/**
 * Class Db.
 */
abstract class Db implements RDBMSInterface
{
	use PermanentlyLockableTrait {
		// use `protected` instead of `private` as Db is abstract and the concrete RDBMS class may want this
		lock as protected traitLock;
	}

	public const REG_COLUMN_REF = '~^(ref|cp):(\w+)\.(\w+)$~';

	/**
	 * The stages a table of a lazy schema is built in, in order: its columns, then its constraints
	 * and indexes, then its relations -- the passes {@see loadSchema()} runs over a whole schema.
	 */
	private const LAZY_COLUMNS     = 1;
	private const LAZY_CONSTRAINTS = 2;
	private const LAZY_RELATIONS   = 3;

	/**
	 * The stage of a table of a lazy schema whose build failed: using it again throws.
	 */
	private const LAZY_FAILED = -1;

	/**
	 * Gobl rdbms class setting shortcuts map.
	 *
	 * @var array
	 */
	private static array $rdbms_map = [
		MySQL::NAME      => MySQL::class,
		PostgreSQL::NAME => PostgreSQL::class,
		SQLite::NAME     => SQLite::class,
	];

	/**
	 * Database tables.
	 *
	 * A table declared by a lazy schema holds its place here as `null` until it is built, so
	 * {@see getTables()} returns the tables in the order they were declared.
	 *
	 * @var array<string, null|Table>
	 */
	private array $tables = [];

	/**
	 * Map table morph type to table name.
	 *
	 * @var array<string,string>
	 */
	private array $morph_types = [];

	/**
	 * Database namespaces.
	 *
	 * @var NamespaceBuilder[]
	 */
	private array $namespaces = [];

	/**
	 * Map table full name to table name.
	 *
	 * @var array<string, string>
	 */
	private array $tbl_full_name_map = [];

	/**
	 * PDO database connection instance.
	 *
	 * @var null|PDO
	 */
	private ?PDO $db_connection = null;

	private array $resolved_column_ref = [];

	/**
	 * Whether {@see loadSchema()} declares the tables it is given as arrays rather than build them.
	 */
	private bool $lazy_schema = false;

	/**
	 * Tables of a lazy schema not built yet.
	 *
	 * @var array<string, array{options: array, namespace: null|string, schema: array, morph_type: string}>
	 */
	private array $lazy_tables = [];

	/**
	 * Tables of a lazy schema built in part: name -> [definition, last stage started].
	 *
	 * @var array<string, array{0: array{options: array, namespace: null|string, schema: array, morph_type: string}, 1: int}>
	 */
	private array $lazy_partial = [];

	/**
	 * Db destructor.
	 */
	public function __destruct()
	{
		unset($this->db_connection);
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return ['instance_of' => static::class];
	}

	/**
	 * Disable clone.
	 */
	private function __clone() {}

	/**
	 * Creates a new database instance of the given rdbms name.
	 *
	 * @param string   $rdbms_type
	 * @param DbConfig $config
	 *
	 * @return RDBMSInterface
	 */
	public static function newInstanceOf(string $rdbms_type, DbConfig $config): RDBMSInterface
	{
		if (!isset(self::$rdbms_map[$rdbms_type])) {
			throw new InvalidArgumentException(\sprintf('Undefined rdbms: %s.', $rdbms_type));
		}

		/** @var RDBMSInterface $rdbms_class */
		$rdbms_class = self::$rdbms_map[$rdbms_type];

		return $rdbms_class::new($config);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function getConnection(): PDO
	{
		if (null === $this->db_connection) {
			$this->lock();

			$this->db_connection = $this->connect();
		}

		return $this->db_connection;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Locking the database performs the following in order:
	 *  1. Calls `pack()` on every registered namespace (builds deferred constraints and relations).
	 *  2. Calls `lock()` on every table (cascades to all columns, constraints, and indexes).
	 *  3. Builds the `morph_types` lookup map (table name -> morph type).
	 *  4. Validates morph-type uniqueness: throws `DBALException` if two tables share the
	 *     same morph type, or if a morph type collides with another table's name or full name.
	 *
	 * A table of a lazy schema is locked once it is complete, and its morph type is registered here
	 * all the same: read from its definition when it is not built yet.
	 *
	 * Once locked, no further tables or columns may be added.
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function lock(): static
	{
		if (!$this->isLocked()) {
			$this->traitLock();

			foreach ($this->namespaces as $namespace) {
				$namespace->pack();
			}

			foreach ($this->tables as $name => $table) {
				if (null === $table) {
					$morph_type = $this->lazy_tables[$name]['morph_type'];
				} else {
					if (!isset($this->lazy_partial[$name])) {
						$table->lock();
					}

					$morph_type = $table->getMorphType();
				}

				$this->registerMorphType($name, $morph_type);
			}
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The tables of a lazy schema are all built first.
	 */
	#[Override]
	public function getTables(?string $namespace = null): array
	{
		$this->buildLazyTables();

		/** @var array<string, Table> $tables */
		$tables = $this->tables;

		if (null !== $namespace) {
			$results = [];

			foreach ($tables as $name => $table) {
				if ($namespace !== $table->getNamespace()) {
					continue;
				}

				$results[$name] = $table;
			}

			return $results;
		}

		return $tables;
	}

	/**
	 * Exports the registered tables as a plain array compatible with {@see loadSchema()}.
	 *
	 * The returned array is keyed by table name. Use {@see toSchemaJson()} to produce
	 * a JSON string that includes the `$schema` URL for IDE validation.
	 *
	 * @param null|string $namespace when provided, only exports tables in that namespace
	 *
	 * @return array<string, array>
	 */
	#[Override]
	public function toSchemaArray(?string $namespace = null): array
	{
		$result = [];

		foreach ($this->getTables($namespace) as $name => $table) {
			$result[$name] = $table->toArray();
		}

		return $result;
	}

	/**
	 * Exports the registered tables as a formatted JSON string.
	 *
	 * When a default schema URL has been configured via {@see Gobl::setDefaultSchemaUrl()},
	 * a `$schema` key is prepended so JSON editors can validate and auto-complete the file:
	 *
	 * ```php
	 * Gobl::setDefaultSchemaUrl('https://raw.githubusercontent.com/silassare/gobl/main/docs/public/schema.json');
	 *
	 * // export
	 * file_put_contents('schema.json', $db->toSchemaJson('App\Db'));
	 *
	 * // import
	 * $db->ns('App\Db')->schemaFile('schema.json');
	 * ```
	 *
	 * @param null|string $namespace when provided, only exports tables in that namespace
	 * @param int         $flags     flags forwarded to {@see json_encode()} (default: JSON_PRETTY_PRINT)
	 *
	 * @return string
	 */
	#[Override]
	public function toSchemaJson(?string $namespace = null, int $flags = \JSON_PRETTY_PRINT): string
	{
		$data = $this->toSchemaArray($namespace);
		$url  = Gobl::getDefaultSchemaUrl();

		if (null !== $url) {
			$data = ['$schema' => $url] + $data;
		}

		return \json_encode($data, $flags) ?: '{}';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setLazySchema(bool $lazy = true): static
	{
		$this->lazy_schema = $lazy;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isLazySchema(): bool
	{
		return $this->lazy_schema;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Processes the schema in three passes:
	 *  1. **Register tables & columns** - each entry is either a `Table` instance or an array;
	 *     column definitions may reference other columns via `ref:table.col` / `cp:table.col`.
	 *  2. **Apply constraints/indexes** - FK, PK, UK, and index declarations are processed once
	 *     all tables are registered so cross-table references can be resolved.
	 *  3. **Apply relations** - relation definitions are processed last.
	 *
	 * With a lazy schema ({@see setLazySchema()}), a table defined as an array is only declared
	 * here -- its name, full name and morph type are registered -- and goes through the same passes
	 * when it is first used.
	 *
	 * When `$desired_namespace` is provided it overrides the namespace declared inside each table.
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function loadSchema(array $schema, ?string $desired_namespace = null): static
	{
		$built = [];

		// we add tables and columns first
		foreach ($schema as $table_name => $table_options) {
			if ($this->lazy_schema && \is_array($table_options)) {
				$this->declareTable($table_name, $table_options, $desired_namespace, $schema);

				continue;
			}

			$this->addTable($this->buildTable($table_name, $table_options, $desired_namespace, $schema));

			if (\is_array($table_options)) {
				$built[$table_name] = $table_options;
			}
		}

		// we add constraints after
		foreach ($built as $table_name => $table_options) {
			$this->applyConstraints($this->tables[$table_name], $table_name, $table_options['constraints'] ?? []);
		}

		// we add indexes after constraints
		foreach ($built as $table_name => $table_options) {
			$this->applyIndexes($this->tables[$table_name], $table_name, $table_options['indexes'] ?? []);
		}

		// we could now add relations
		foreach ($built as $table_name => $table_options) {
			$this->applyRelations($this->tables[$table_name], $table_name, $table_options['relations'] ?? []);
		}

		return $this;
	}

	/**
	 * Checks if a given string is a column reference.
	 *
	 * @param string $str
	 *
	 * @return bool
	 */
	public static function isColumnReference(string $str): bool
	{
		return null !== static::parseColumnReference($str);
	}

	/**
	 * Parse a column reference.
	 *
	 * @param string $reference The column reference
	 *
	 * @return null|array{clone: bool, table: string, column: string}
	 */
	public static function parseColumnReference(string $reference): ?array
	{
		if (\preg_match(self::REG_COLUMN_REF, $reference, $parts)) {
			$head  = $parts[1];
			$clone = 'cp' === $head;

			return [
				'clone'  => $clone,
				'table'  => $parts[2],
				'column' => $parts[3],
			];
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * A table of a lazy schema is built first.
	 */
	#[Override]
	public function getTable(string $name): ?Table
	{
		$name = $this->tableNameOf($name);

		if (null === $name) {
			return null;
		}

		if (null === $this->tables[$name] || isset($this->lazy_partial[$name])) {
			return $this->lazyStage($name, self::LAZY_RELATIONS);
		}

		return $this->tables[$name];
	}

	#[Override]
	public function getTableByMorphType(string $morph_type): ?Table
	{
		if (isset($this->morph_types[$morph_type])) {
			return $this->getTable($this->morph_types[$morph_type]);
		}

		return null;
	}

	#[Override]
	public function hasTable(string $name): bool
	{
		return null !== $this->tableNameOf($name);
	}

	/**
	 * Strips type options that should not be inherited when a column is used as a reference.
	 *
	 * - `auto_increment` and `meta` are removed unless `$clone` is `true` (a copy column may preserve it,
	 *   but a reference column must never auto-increment independently).
	 * - `diff_key`, `old_name`, and `old_prefix` are always removed because they are specific to the original column definition
	 *   and have no meaning on a derived/reference column.
	 *
	 * @param array $options The raw column type options array to clean
	 * @param bool  $clone   `true` when the column is a copy (`cp:`) rather than a reference (`ref:`)
	 *
	 * @return array cleaned options
	 */
	public static function cleanColumnTypeOptionsForReference(array $options, bool $clone): array
	{
		if (!$clone) {
			unset($options['auto_increment'], $options['meta']);
		}

		unset($options['diff_key'], $options['old_name'], $options['old_prefix']);

		return $options;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function addTable(Table $table): static
	{
		$this->assertNotLocked();

		$name = $table->getName();

		try {
			$table->assertNotLocked();
		} catch (Throwable $t) {
			throw new DBALException(
				\sprintf(
					'Table "%s" could not be added.',
					$name,
				),
				null,
				$t
			);
		}

		if (empty($table->getPrefix()) && !empty($prefix = $this->getConfig()
			->getDbTablePrefix())) {
			$table->setPrefix($prefix);
		}

		$full_name = $table->getFullname();

		$this->assertNamesAvailable($name, $full_name);

		$this->tbl_full_name_map[$full_name] = $name;
		$this->tables[$name]                 = $table->lockName();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * A table of a lazy schema is built first.
	 */
	#[Override]
	public function getTableOrFail(string $name): Table
	{
		$table = $this->getTable($name);

		if (null === $table) {
			throw new DBALRuntimeException(\sprintf('The table "%s" is not defined.', $name));
		}

		return $table;
	}

	/**
	 * {@inheritDoc}
	 *
	 * A name and a full name are taken together when a table is declared: a table of a lazy schema
	 * knows its full name before it is built.
	 */
	#[Override]
	public function getTableFullName(string $name): ?string
	{
		$name = $this->tableNameOf($name);

		if (null === $name) {
			return null;
		}

		if (null !== $this->tables[$name]) {
			return $this->tables[$name]->getFullName();
		}

		$full_name = \array_search($name, $this->tbl_full_name_map, true);

		return false === $full_name ? null : (string) $full_name;
	}

	#[Override]
	public function assertHasTable(string $name): void
	{
		if (!$this->hasTable($name)) {
			throw new DBALRuntimeException(\sprintf('The table "%s" is not defined.', $name));
		}
	}

	#[Override]
	public function ns(string $namespace): NamespaceBuilder
	{
		return $this->namespace($namespace);
	}

	#[Override]
	public function namespace(string $namespace): NamespaceBuilder
	{
		if (!isset($this->namespaces[$namespace])) {
			$this->namespaces[$namespace] = new NamespaceBuilder($this, $namespace);
		}

		return $this->namespaces[$namespace];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function resolveColumn(string $reference, string $used_in_table_name): array
	{
		return $this->resolveColumnInternal($reference, $used_in_table_name);
	}

	/**
	 * Connect to the relational database management system.
	 *
	 * @return PDO
	 */
	abstract protected function connect(): PDO;

	/**
	 * Resolve reference column.
	 *
	 * Recursively resolves a `ref:table.column` or `cp:table.column` reference to the
	 * underlying column's type-option array.
	 *
	 * Resolution results are cached in `$this->resolved_column_ref` keyed by the reference
	 * string; subsequent calls for the same reference return the cached result immediately.
	 *
	 * A table of a lazy schema is not built for this: its definition is read instead.
	 *
	 * @param string $reference          The reference column path (e.g. `ref:users.user_id`)
	 * @param string $used_in_table_name The table in which the reference is being used
	 * @param array  $schema             Schema definition (may be partially built during `loadSchema`)
	 * @param array  $circle             Accumulator passed by reference to detect reference cycles
	 *                                   and prevent infinite recursion; throws on cycle detection
	 *
	 * @return array resolved column type options
	 *
	 * @throws DBALException
	 *
	 * @internal
	 */
	protected function resolveColumnInternal(
		string $reference,
		string $used_in_table_name,
		array $schema = [],
		array &$circle = []
	): array {
		if (isset($this->resolved_column_ref[$reference])) {
			return $this->resolved_column_ref[$reference];
		}

		if (\in_array($reference, $circle, true)) {
			$circle[] = $reference;

			throw new DBALException(
				\sprintf(
					'Possible cyclic reference path "%s" found while resolving column reference "%s" in table "%s".',
					\implode(' > ', $circle),
					$circle[0],
					$used_in_table_name
				)
			);
		}

		$circle[] = $reference;
		$info     = static::parseColumnReference($reference);

		if ($info) {
			$_col_opt  = null;
			$clone     = $info['clone'];
			$ref_table = $info['table'];
			$ref_col   = $info['column'];
			$ref_name  = $this->tableNameOf($ref_table);
			$tbl       = null === $ref_name ? null : $this->tables[$ref_name];
			$ref_def   = $schema[$ref_table] ?? (null === $ref_name ? null : ($this->lazy_tables[$ref_name]['options'] ?? null));

			if ($tbl) {
				$_col_opt = $tbl->getColumn($ref_col)?->getType()
					->toArray();
			} elseif (null !== $ref_def) {
				if ($ref_def instanceof Table) {
					$_col_opt = $ref_def->getColumn($ref_col)?->getType()
						->toArray();
				} elseif (\is_array($ref_def) && isset($ref_def['columns'][$ref_col])) {
					$ref_col_opt          = $ref_def['columns'][$ref_col];
					$ref_type_to_override = null;

					if (\is_string($ref_col_opt)) {
						$type = $ref_col_opt;

						if (static::isColumnReference($ref_col_opt)) {
							$ref_type_to_override = $this->resolveColumnInternal($type, $ref_table, $schema, $circle);
						}
					} elseif (\is_array($ref_col_opt)) {
						if (isset($ref_col_opt['type'])) {
							$type     = $ref_col_opt['type'];
							$_col_opt = $ref_col_opt;

							if (\is_string($type) && static::isColumnReference($type)) {
								$ref_type_to_override = $this->resolveColumnInternal(
									$type,
									$ref_table,
									$schema,
									$circle
								);
							} elseif ($type instanceof TypeInterface) {
								$ref_type_to_override = $type->toArray();
							}
						}
					} elseif ($ref_col_opt instanceof Column) {
						$_col_opt = $ref_col_opt->toArray();
					} elseif ($ref_col_opt instanceof TypeInterface) {
						$_col_opt = $ref_col_opt->toArray();
					}

					if (\is_array($ref_type_to_override)) {
						if (empty($_col_opt)) {
							$_col_opt = $ref_type_to_override;
						} else {
							$_col_opt         = TypeUtils::mergeOptions($ref_type_to_override, $_col_opt);
							$_col_opt['type'] = $ref_type_to_override['type'];
						}
					}
				}
			}

			if (\is_array($_col_opt)) {
				$_col_opt = static::cleanColumnTypeOptionsForReference($_col_opt, $clone);

				$this->resolved_column_ref[$reference] = $_col_opt;

				return $_col_opt;
			}
		}

		throw new DBALException(
			\sprintf(
				'Unable to resolve column type reference "%s" found in table "%s".',
				$reference,
				$used_in_table_name
			)
		);
	}

	/**
	 * The name of a table given its name or full name, whether it is built or only declared.
	 */
	private function tableNameOf(string $name): ?string
	{
		if (\array_key_exists($name, $this->tables)) {
			return $name;
		}

		return $this->tbl_full_name_map[$name] ?? null;
	}

	/**
	 * Refuses a table name or full name that is the name or full name of another table.
	 *
	 * @throws DBALException
	 */
	private function assertNamesAvailable(string $name, string $full_name): void
	{
		// prevents table "name" conflict with another table "name" or "full name"
		if ($this->hasTable($name)) {
			throw new DBALException(
				\sprintf('The table name conflict with an existing table name or full name: "%s".', $name)
			);
		}

		// prevents table "full name" conflict with another table "name" or "full name"
		if ($this->hasTable($full_name)) {
			throw new DBALException(
				\sprintf('The table full name conflict with an existing table name or full name: "%s".', $full_name)
			);
		}
	}

	/**
	 * Registers a table's morph type, refusing one another table uses already, or that is the name or
	 * full name of another table.
	 *
	 * @throws DBALException
	 */
	private function registerMorphType(string $name, string $morph_type): void
	{
		// prevents morph type conflict
		if (isset($this->morph_types[$morph_type])) {
			throw new DBALException(
				\sprintf('The morph type "%s" is already used by another table.', $morph_type)
			);
		}

		$other = $this->tableNameOf($morph_type);
		// prevents morph type conflict with another table "name" or "full name"
		if (null !== $other && $other !== $name) {
			throw new DBALException(
				\sprintf(
					'The morph type "%s" of table "%s" conflict with the existing table "%s" name or full name.',
					$morph_type,
					$name,
					$other
				)
			);
		}

		$this->morph_types[$morph_type] = $name;
	}

	/**
	 * Declares a table of a lazy schema: its names are taken, and it is built when first used.
	 *
	 * @param string      $table_name        the table name
	 * @param array       $table_options     the table definition
	 * @param null|string $desired_namespace overrides the namespace the definition declares
	 * @param array       $schema            the schema the table is defined in, to resolve column references
	 *
	 * @throws DBALException
	 */
	private function declareTable(
		string $table_name,
		array $table_options,
		?string $desired_namespace,
		array $schema
	): void {
		$this->assertNotLocked();

		if (empty($table_options['columns']) || !\is_array($table_options['columns'])) {
			throw new DBALException(\sprintf('You should define columns for table "%s".', $table_name));
		}

		// the prefix addTable() would give the table
		$prefix = $table_options['prefix'] ?? null;

		if (empty($prefix)) {
			$prefix = $this->getConfig()
				->getDbTablePrefix();
		}

		$full_name = empty($prefix) ? $table_name : $prefix . '_' . $table_name;

		$this->assertNamesAvailable($table_name, $full_name);

		$this->tbl_full_name_map[$full_name] = $table_name;
		$this->tables[$table_name]           = null;
		$this->lazy_tables[$table_name]      = [
			'options'    => $table_options,
			'namespace'  => $desired_namespace,
			'schema'     => $schema,
			'morph_type' => (string) ($table_options['morph_type'] ?? $table_name),
		];
	}

	/**
	 * Completes every table of a lazy schema.
	 *
	 * @throws DBALException
	 */
	private function buildLazyTables(): void
	{
		if (empty($this->lazy_tables) && empty($this->lazy_partial)) {
			return;
		}

		foreach (\array_keys($this->tables) as $name) {
			if (null === $this->tables[$name] || isset($this->lazy_partial[$name])) {
				$this->lazyStage($name, self::LAZY_RELATIONS);
			}
		}
	}

	/**
	 * Builds a table of a lazy schema up to a stage.
	 *
	 * A stage is marked started before it runs, so a table reached again through a cycle of foreign
	 * keys or relations is handed out as it is -- as the eager passes do, which add a foreign key to
	 * a table whose relations are not there yet. While a stage runs, the table's lazy builder is
	 * detached, so what the stage adds to the table does not come back here; once it is attached
	 * again, the table's accessors finish building it.
	 *
	 * @throws DBALException
	 */
	private function lazyStage(string $name, int $stage): Table
	{
		if (self::LAZY_FAILED === ($this->lazy_partial[$name][1] ?? null)) {
			throw new DBALException(\sprintf('The table "%s" could not be built.', $name));
		}

		if (null === $this->tables[$name]) {
			$definition = $this->lazy_tables[$name];

			try {
				$table = $this->buildTable(
					$name,
					$definition['options'],
					$definition['namespace'],
					$definition['schema']
				);
			} catch (Throwable $t) {
				$this->lazy_partial[$name] = [$definition, self::LAZY_FAILED];

				throw $t;
			}

			if (empty($table->getPrefix()) && !empty($prefix = $this->getConfig()
				->getDbTablePrefix())) {
				$table->setPrefix($prefix);
			}

			unset($this->lazy_tables[$name]);

			// its name and full name were taken when it was declared
			$this->tables[$name]       = $table->lockName();
			$this->lazy_partial[$name] = [$definition, self::LAZY_COLUMNS];

			$table->setLazyBuilder($this->lazyBuilder($name));
		}

		$table = $this->tables[$name];
		$state = $this->lazy_partial[$name] ?? null;

		if (null === $state || $state[1] >= $stage) {
			/** @var Table $table */
			return $table;
		}

		[$definition, $started] = $state;
		$options                = $definition['options'];

		$table->setLazyBuilder(null);

		try {
			if ($started < self::LAZY_CONSTRAINTS) {
				$this->lazy_partial[$name][1] = self::LAZY_CONSTRAINTS;

				$this->applyConstraints($table, $name, $options['constraints'] ?? []);
				$this->applyIndexes($table, $name, $options['indexes'] ?? []);
			}

			if (self::LAZY_RELATIONS === $stage) {
				$this->lazy_partial[$name][1] = self::LAZY_RELATIONS;

				$this->applyRelations($table, $name, $options['relations'] ?? []);
			}
		} catch (Throwable $t) {
			$this->lazy_partial[$name][1] = self::LAZY_FAILED;

			throw $t;
		}

		if (self::LAZY_RELATIONS === $stage) {
			unset($this->lazy_partial[$name]);

			if ($this->isLocked()) {
				// A lazy schema is one taken as valid: its column defaults are not validated again
				// every time a process builds the table.
				Type::trustingDefaults(static fn () => $table->lock());
			}
		} else {
			$table->setLazyBuilder($this->lazyBuilder($name));
		}

		return $table;
	}

	/**
	 * What a table of a lazy schema calls to finish building itself, from its accessors.
	 *
	 * @return Closure(bool):void called with true when the relations are needed too
	 */
	private function lazyBuilder(string $name): Closure
	{
		return function (bool $with_relations) use ($name): void {
			$this->lazyStage($name, $with_relations ? self::LAZY_RELATIONS : self::LAZY_CONSTRAINTS);
		};
	}

	/**
	 * A table other tables point to, by name or full name: a table of a lazy schema is built as
	 * far as its columns, which is what a foreign key or a relation takes; its accessors build the
	 * rest when something reads it.
	 */
	private function referencedTable(string $name): ?Table
	{
		$name = $this->tableNameOf($name);

		if (null === $name) {
			return null;
		}

		return $this->tables[$name] ?? $this->lazyStage($name, self::LAZY_COLUMNS);
	}

	/**
	 * Builds a table with its columns: the first pass of {@see loadSchema()}.
	 *
	 * @param string      $table_name        the table name
	 * @param mixed       $table_options     the table definition: an array of options or a `Table` instance
	 * @param null|string $desired_namespace overrides the namespace the definition declares
	 * @param array       $schema            the schema the table is defined in, to resolve column references
	 *
	 * @return Table
	 *
	 * @throws DBALException
	 */
	private function buildTable(
		string $table_name,
		mixed $table_options,
		?string $desired_namespace,
		array $schema
	): Table {
		$table_namespace_option = null;

		if ($table_options instanceof Table) {
			$tbl = $table_options;
		} elseif (\is_array($table_options)) {
			if (empty($table_options['columns']) || !\is_array($table_options['columns'])) {
				throw new DBALException(\sprintf('You should define columns for table "%s".', $table_name));
			}

			if (isset($table_options['constraints']) && !\is_array($table_options['constraints'])) {
				throw new DBALException(
					\sprintf(
						'Property "constraints" defined in table "%s" should be an array.',
						$table_name
					)
				);
			}

			if (isset($table_options['relations']) && !\is_array($table_options['relations'])) {
				throw new DBALException(
					\sprintf(
						'Property "relations" defined in table "%s" should be an array.',
						$table_name
					)
				);
			}

			if (isset($table_options['indexes']) && !\is_array($table_options['indexes'])) {
				throw new DBALException(
					\sprintf(
						'Property "indexes" defined in table "%s" should be an array.',
						$table_name
					)
				);
			}

			if (!empty($table_options['namespace']) && \is_string($table_options['namespace'])) {
				$table_namespace_option = $table_options['namespace'];
			}

			$columns          = $table_options['columns'];
			$table_col_prefix = null;
			$tbl              = new Table($table_name, $table_options['prefix'] ?? $this->getConfig()
				->getDbTablePrefix());

			if (isset($table_options['diff_key'])) {
				$tbl->setDiffKey($table_options['diff_key']);
			}

			if (isset($table_options['old_name'])) {
				$tbl->oldName((string) $table_options['old_name']);
			}

			if (isset($table_options['morph_type'])) {
				$tbl->setMorphType($table_options['morph_type']);
			}

			if (isset($table_options['charset'])) {
				$tbl->setCharset($table_options['charset']);
			}

			if (isset($table_options['collate'])) {
				$tbl->setCollate($table_options['collate']);
			}

			if (isset($table_options['column_prefix'])) {
				$table_col_prefix = (string) $table_options['column_prefix'];
				$tbl->setColumnPrefix($table_col_prefix);
			}

			if (isset($table_options['singular_name'])) {
				$tbl->setSingularName((string) $table_options['singular_name']);
			}

			if (isset($table_options['plural_name'])) {
				$tbl->setPluralName((string) $table_options['plural_name']);
			}

			if (isset($table_options['private'])) {
				$tbl->setPrivate((bool) $table_options['private']);
			}

			if (isset($table_options['meta']) && (\is_array($table_options['meta']) || $table_options['meta'] instanceof Map)) {
				$tbl->mergeMeta($table_options['meta']);
			}

			foreach ($columns as $column_name => $column_opt) {
				if ($column_opt instanceof Column) {
					$col = $column_opt;

					if ($column_name !== $col->getName()) {
						throw new DBALException(
							\sprintf(
								'Column "%s" in table "%s" has an instance of "%s" with a different name "%s".',
								$column_name,
								$table_name,
								Column::class,
								$col->getName()
							)
						);
					}
				} elseif ($column_opt instanceof TypeInterface) {
					$col = new Column($column_name, $table_col_prefix, $column_opt);
				} else {
					if (\is_string($column_opt)) {
						$col_options = ['type' => $column_opt];
					} elseif (\is_array($column_opt)) {
						$col_options = $column_opt;
					} else {
						throw new DBALException(
							\sprintf(
								'Invalid column "%s" option in table "%s".',
								$column_name,
								$table_name
							)
						);
					}

					if (!isset($col_options['type'])) {
						throw new DBALException(
							\sprintf(
								'Missing required property "type" for column "%s" in table "%s".',
								$column_name,
								$table_name
							)
						);
					}

					$type = $col_options['type'];

					if (\is_string($type)) {
						if (static::isColumnReference($type)) {
							$col_reference = $type;
							$ref_options   = $this->resolveColumnInternal(
								$col_reference,
								$table_name,
								$schema
							);
							$col_options                      = TypeUtils::mergeOptions($ref_options, $col_options);
							$col_options['type']              = $ref_options['type'];
							$col_options['_column_reference'] = $col_reference;
						}
					} elseif ($type instanceof TypeInterface) {
						$col_options         = TypeUtils::mergeOptions($type->toArray(), $col_options);
						$col_options['type'] = $type->getName();
					} else {
						throw new DBALException(
							\sprintf(
								'Invalid "type" property for column "%s" in table "%s".',
								$column_name,
								$table_name
							)
						);
					}

					try {
						$col = new Column($column_name, $table_col_prefix, $col_options);

						if (isset($col_options['diff_key'])) {
							$col->setDiffKey($col_options['diff_key']);
						}

						if (isset($col_options['old_name'])) {
							$col->oldName((string) $col_options['old_name']);
						}

						if (isset($col_options['old_prefix'])) {
							$col->oldPrefix((string) $col_options['old_prefix']);
						}

						if (isset($col_options['private'])) {
							$col->setPrivate((bool) $col_options['private']);
						}

						if (isset($col_options['sensitive'])) {
							$col->setSensitive((bool) $col_options['sensitive'], $col_options['sensitive_redacted_value'] ?? null);
						}

						if (isset($col_options['prefix']) && $col_options['prefix'] !== $table_col_prefix) {
							$col->setPrefix((string) $col_options['prefix']);
						}

						if (isset($col_options['meta']) && (\is_array($col_options['meta']) || $col_options['meta'] instanceof Map)) {
							$col->mergeMeta($col_options['meta']);
						}

						if (isset($col_options['_column_reference'])) {
							$col->setReference($col_options['_column_reference']);
						}
					} catch (Throwable $t) {
						throw new DBALException(
							\sprintf(
								'Unable to initialize column "%s" in table "%s".',
								$column_name,
								$table_name
							),
							$col_options,
							$t
						);
					}
				}

				$tbl->addColumn($col);
			}
		} else {
			throw new DBALException(
				\sprintf(
					'Invalid table "%s" definition. You should provide an array of options or an instance of "%s".',
					$table_name,
					Table::class
				)
			);
		}

		if ($desired_namespace) {
			$tbl->setNamespace($desired_namespace);
		} elseif ($table_namespace_option) {
			$tbl->setNamespace($table_namespace_option);
		}

		return $tbl;
	}

	/**
	 * Adds a table's constraints: part of the second pass of {@see loadSchema()}.
	 *
	 * @param Table  $tbl         the table
	 * @param string $table_name  the table name
	 * @param array  $constraints the constraints the table definition declares
	 *
	 * @throws DBALException
	 */
	private function applyConstraints(Table $tbl, string $table_name, array $constraints): void
	{
		foreach ($constraints as $constraint) {
			if ($constraint instanceof Constraint) {
				$constraint = $constraint->toArray();
			}

			$type = $constraint['type'] ?? null;

			if (empty($type)) {
				throw new DBALException(
					\sprintf(
						'You should define constraint "type" in table "%s".',
						$table_name
					),
					$constraint
				);
			}

			$columns = $constraint['columns'] ?? null;

			if (!\is_array($columns) || empty($columns)) {
				throw new DBALException(
					\sprintf(
						'Required constraint "columns" is not defined or is empty in table "%s".',
						$table_name
					),
					$constraint
				);
			}

			try {
				switch ($type) {
					case 'unique_key':
					case 'unique': // old to be removed
						$tbl->addUniqueKeyConstraint($columns);

						break;

					case 'primary_key':
						$tbl->addPrimaryKeyConstraint($columns);

						break;

					case 'foreign_key':
						if (!isset($constraint['reference'])) {
							throw new DBALException(
								\sprintf(
									'You should declare foreign key "reference" table in table "%s".',
									$table_name
								)
							);
						}

						$reference = $constraint['reference'];

						// by name only, as it always was: not by full name
						$reference_table = \array_key_exists($reference, $this->tables)
							? $this->referencedTable($reference)
							: null;

						if (null === $reference_table) {
							throw new DBALException(
								\sprintf(
									'Reference table "%s" for foreign key in table "%s" is not defined.',
									$reference,
									$table_name
								)
							);
						}

						$update_action = null;
						$delete_action = null;
						$name          = $constraint['name'] ?? null;

						if (isset($constraint['update'])) {
							$update_action = ForeignKeyAction::tryFrom($constraint['update']);
							if (!$update_action) {
								throw new DBALException(
									\sprintf(
										'Invalid update action "%s" for foreign key constraint.',
										$constraint['update']
									)
								);
							}
						}

						if (isset($constraint['delete'])) {
							$delete_action = ForeignKeyAction::tryFrom($constraint['delete']);
							if (!$delete_action) {
								throw new DBALException(
									\sprintf(
										'Invalid delete action "%s" for foreign key constraint.',
										$constraint['delete']
									)
								);
							}
						}

						$tbl->addForeignKeyConstraint(
							$name,
							$reference_table,
							$constraint['columns'],
							$update_action,
							$delete_action
						);

						break;

					default:
						throw new DBALException(
							\sprintf(
								'Unknown constraint type "%s" defined in table "%s".',
								$type,
								$table_name
							)
						);
				}
			} catch (Throwable $t) {
				throw new DBALException(
					\sprintf('Unable to add constraint to table "%s".', $table_name),
					$constraint,
					$t
				);
			}
		}
	}

	/**
	 * Adds a table's indexes: part of the second pass of {@see loadSchema()}.
	 *
	 * @param Table  $tbl        the table
	 * @param string $table_name the table name
	 * @param array  $indexes    the indexes the table definition declares
	 *
	 * @throws DBALException
	 */
	private function applyIndexes(Table $tbl, string $table_name, array $indexes): void
	{
		foreach ($indexes as $index_def) {
			if ($index_def instanceof Index) {
				$index_def = $index_def->toArray();
			}

			$columns = $index_def['columns'] ?? null;

			if (!\is_array($columns) || empty($columns)) {
				throw new DBALException(
					\sprintf(
						'Required "columns" is not defined or is empty for an index in table "%s".',
						$table_name
					),
					$index_def
				);
			}

			try {
				$index_type = null;

				if (isset($index_def['type'])) {
					$index_type = IndexType::tryFrom($index_def['type']);

					if (!$index_type) {
						throw new DBALException(
							\sprintf(
								'Invalid index type "%s" in table "%s".',
								$index_def['type'],
								$table_name
							)
						);
					}
				}

				$tbl->addIndex($columns, $index_type);
			} catch (Throwable $t) {
				throw new DBALException(
					\sprintf('Unable to add index to table "%s".', $table_name),
					$index_def,
					$t
				);
			}
		}
	}

	/**
	 * Adds a table's relations: the third pass of {@see loadSchema()}.
	 *
	 * @param Table  $tbl        the table
	 * @param string $table_name the table name
	 * @param array  $relations  the relations the table definition declares
	 *
	 * @throws DBALException
	 */
	private function applyRelations(Table $tbl, string $table_name, array $relations): void
	{
		foreach ($relations as $relation_name => $rel_options) {
			try {
				$r = null;

				if ($rel_options instanceof Relation) {
					$r = $rel_options;
				} elseif (\is_array($rel_options) && isset($rel_options['type'], $rel_options['target'])) {
					$type   = RelationType::tryFrom($rel_options['type']);
					$target = $rel_options['target'];

					if (\is_string($target)) {
						$target = $this->referencedTable($target)
							?? throw new DBALRuntimeException(\sprintf('The table "%s" is not defined.', $target));
					} elseif (!$target instanceof Table) {
						throw new DBALException(
							\sprintf(
								'property "target" defined for relation "%s" in table "%s" should be of string|%s type not "%s".',
								$relation_name,
								$table_name,
								Table::class,
								\get_debug_type($target)
							)
						);
					}

					$link_options = $rel_options['link'] ?? null;

					if ($link_options) {
						if ($link_options instanceof LinkInterface) {
							$link = $link_options;
						} elseif (\is_array($link_options)) {
							$link = Relation::createLink($this, $tbl, $target, $link_options);
						} else {
							throw new DBALException(
								\sprintf(
									'property "link" defined for relation "%s" in table "%s" should be of array|%s type not "%s".',
									$relation_name,
									$table_name,
									LinkInterface::class,
									\get_debug_type($link_options)
								)
							);
						}
					} else {
						// old way to define relations
						$columns = $rel_options['columns'] ?? null;

						if ($columns) {
							if (!\is_array($columns)) {
								throw new DBALException(
									\sprintf(
										'Property "columns" defined for relation "%s" in table "%s" should be of array type not "%s".',
										$relation_name,
										$table_name,
										\get_debug_type($columns)
									)
								);
							}

							$link_options = [
								'type'    => LinkType::COLUMNS->value,
								'columns' => $columns,
							];
						} else {
							// there is no columns defined so we will suppose it's of type columns
							$link_options = [
								'type' => LinkType::COLUMNS->value,
							];
						}

						$link = Relation::createLink($this, $tbl, $target, $link_options);
					}

					if (RelationType::ONE_TO_ONE === $type) {
						$r = new OneToOne($relation_name, $link);
					} elseif (RelationType::ONE_TO_MANY === $type) {
						$r = new OneToMany($relation_name, $link);
					} elseif (RelationType::MANY_TO_ONE === $type) {
						$r = new ManyToOne($relation_name, $link);
					} elseif (RelationType::MANY_TO_MANY === $type) {
						if (!$link instanceof LinkThrough) {
							throw new DBALException(
								\sprintf(
									'Invalid "link" type for relation "%s" in table "%s". Many to many relation should use a link through.',
									$relation_name,
									$table_name
								)
							);
						}

						$r = new ManyToMany($relation_name, $link);
					}
				}

				if (null === $r) {
					throw new DBALException(
						\sprintf(
							'Invalid or incomplete option provided for relation "%s" in table "%s".',
							$relation_name,
							$table_name
						)
					);
				}

				// Apply optional per-relation column projection (Feature 5).
				if (!empty($rel_options['select']) && \is_array($rel_options['select'])) {
					$r->setSelect($rel_options['select']);
				}

				$tbl->addRelation($r);
			} catch (Throwable $t) {
				throw new DBALException(
					\sprintf('Unable to add relation "%s" defined in table "%s".', $relation_name, $table_name),
					$rel_options,
					$t
				);
			}
		}
	}
}
