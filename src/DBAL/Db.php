<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL;

use Gobl\DBAL\Builders\DbBuilder;
use Gobl\DBAL\Constraints\Constraint;
use Gobl\DBAL\Constraints\ForeignKeyAction;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\SQLLite\SQLLite;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
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
use Gobl\DBAL\Types\Utils\TypeUtils;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Class Db.
 */
abstract class Db implements RDBMSInterface
{
	public const REG_COLUMN_REF = '~^(ref|cp):(\w+)\.(\w+)$~';

	/**
	 * Gobl rdbms class setting shortcuts map.
	 *
	 * @var array
	 */
	private static array $rdbms_map = [
		MySQL::NAME   => MySQL::class,
		SQLLite::NAME => SQLLite::class,
	];

	/**
	 * Database tables.
	 *
	 * @var \Gobl\DBAL\Table[]
	 */
	private array $tables = [];

	/**
	 * @var array
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
	private function __clone()
	{
	}

	/**
	 * Creates instance of RDBMS with the given name.
	 *
	 * @param string   $rdbms_name
	 * @param DbConfig $config
	 *
	 * @return RDBMSInterface
	 */
	public static function createInstanceWithName(string $rdbms_name, DbConfig $config): RDBMSInterface
	{
		if (!isset(self::$rdbms_map[$rdbms_name])) {
			throw new InvalidArgumentException(\sprintf('Undefined rdbms: %s.', $rdbms_name));
		}

		/** @var RDBMSInterface $rdbms_class */
		$rdbms_class = self::$rdbms_map[$rdbms_name];

		return $rdbms_class::createInstance($config);
	}

	/**
	 * {@inheritDoc}
	 */
	public function lock(): self
	{
		foreach ($this->tables as $table) {
			$table->lock();
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function addTable(Table $table): self
	{
		$name = $table->getName();

		try {
			$table->assertNotLocked();
			$table->assertIsValid();
		} catch (Throwable $t) {
			throw new DBALException(\sprintf(
				'Table "%s" could not be added.',
				$name,
			), null, $t);
		}

		if (empty($table->getPrefix()) && !empty($prefix = $this->getConfig()
			->getDbTablePrefix())) {
			$table->setPrefix($prefix);
		}

		$full_name = $table->getFullname();
		// prevents table "name" conflict with another table "name" or "full name"
		if ($this->hasTable($name)) {
			throw new DBALException(\sprintf('The table name conflict with an existing table name or full name: "%s".', $name));
		}

		// prevents table "full name" conflict with another table "name" or "full name"
		if ($this->hasTable($full_name)) {
			throw new DBALException(\sprintf('The table full name conflict with an existing table name or full name: "%s".', $full_name));
		}

		$this->tbl_full_name_map[$full_name] = $name;
		$this->tables[$name]                 = $table;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function addTablesToNamespace(string $namespace, array $tables): self
	{
		$tables_prefix           = $this->getConfig()
			->getDbTablePrefix();
		$tables_with_constraints = [];
		$tables_with_relations   = [];
		// we add tables and columns first
		foreach ($tables as $table_name => $table_options) {
			if ($table_options instanceof Table) {
				$tbl = $table_options;
			} elseif (\is_array($table_options)) {
				if (empty($table_options['columns']) || !\is_array($table_options['columns'])) {
					throw new DBALException(\sprintf('You should define columns for table "%s".', $table_name));
				}

				if (isset($table_options['constraints'])) {
					if (!\is_array($table_options['constraints'])) {
						throw new DBALException(\sprintf(
							'Property "constraints" defined in table "%s" should be an array.',
							$table_name
						));
					}

					if (!empty($table_options['constraints'])) {
						$tables_with_constraints[] = $table_name;
					}
				}

				if (isset($table_options['relations'])) {
					if (!\is_array($table_options['relations'])) {
						throw new DBALException(\sprintf(
							'Property "relations" defined in table "%s" should be an array.',
							$table_name
						));
					}

					if (!empty($table_options['relations'])) {
						$tables_with_relations[] = $table_name;
					}
				}

				$columns          = $table_options['columns'];
				$table_col_prefix = null;
				$tbl              = new Table($table_name, $table_options['prefix'] ?? $tables_prefix);

				$tbl->setNamespace($namespace);

				if (isset($table_options['diff_key'])) {
					$tbl->setDiffKey($table_options['diff_key']);
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

				foreach ($columns as $column_name => $column_opt) {
					if ($column_opt instanceof Column) {
						$col = $column_opt;

						if ($column_name !== $col->getName()) {
							throw new DBALException(\sprintf(
								'Column "%s" in table "%s" has an instance of "%s" with a different name "%s".',
								$column_name,
								$table_name,
								Column::class,
								$col->getName()
							));
						}
					} elseif ($column_opt instanceof TypeInterface) {
						$col = new Column($column_name, $table_col_prefix);
						$col->setType($column_opt);
					} else {
						if (\is_string($column_opt)) {
							$col_options = ['type' => $column_opt];
						} elseif (\is_array($column_opt)) {
							$col_options = $column_opt;
						} else {
							throw new DBALException(\sprintf(
								'Invalid column "%s" options in table "%s".',
								$column_name,
								$table_name
							));
						}

						if (!isset($col_options['type'])) {
							throw new DBALException(\sprintf(
								'Missing required property "type" for column "%s" in table "%s".',
								$column_name,
								$table_name
							));
						}

						$type = $col_options['type'];

						if (\is_string($type)) {
							if (static::isColumnReference($type)) {
								$col_reference            = $type;
								$ref_options              = $this->resolveColumnReference($col_reference, $table_name, $tables);
								$col_options              = TypeUtils::mergeOptions($ref_options, $col_options);
								$col_options['type']      = $ref_options['type'];
								$col_options['reference'] = $col_reference;
							}
						} elseif ($type instanceof TypeInterface) {
							$col_options         = TypeUtils::mergeOptions($type->toArray(), $col_options);
							$col_options['type'] = $type->getName();
						} else {
							throw new DBALException(\sprintf(
								'Invalid "type" property for column "%s" in table "%s".',
								$column_name,
								$table_name
							));
						}

						try {
							$col = new Column($column_name, $table_col_prefix);

							if (isset($col_options['private'])) {
								$col->setPrivate((bool) $col_options['private']);
							}

							if (isset($col_options['prefix']) && $col_options['prefix'] !== $table_col_prefix) {
								$col->setPrefix((string) $col_options['prefix']);
							}

							if (isset($col_options['reference'])) {
								$col->setReference($col_options['reference']);
							}

							$col->setTypeFromOptions($col_options);
						} catch (Throwable $t) {
							throw new DBALException(\sprintf(
								'Unable to initialize column "%s" in table "%s".',
								$column_name,
								$table_name
							), $col_options, $t);
						}
					}

					$tbl->addColumn($col);
				}
			} else {
				throw new DBALException(\sprintf(
					'Invalid table "%s" definition. You should provide an array of options or an instance of "%s".',
					$table_name,
					Table::class
				));
			}

			$this->addTable($tbl);
		}

		// we add constraints after
		foreach ($tables_with_constraints as $table_name) {
			$tbl           = $this->tables[$table_name];
			$table_options = $tables[$table_name];
			$constraints   = $table_options['constraints'];

			foreach ($constraints as $constraint) {
				if ($constraint instanceof Constraint) {
					$constraint = $constraint->toArray();
				}

				$type = $constraint['type'] ?? null;

				if (empty($type)) {
					throw new DBALException(\sprintf(
						'You should define constraint "type" in table "%s".',
						$table_name
					), $constraint);
				}

				$columns = $constraint['columns'] ?? null;

				if (!\is_array($columns) || empty($columns)) {
					throw new DBALException(\sprintf(
						'Required constraint "columns" is not defined or is empty in table "%s".',
						$table_name
					), $constraint);
				}

				try {
					switch ($type) {
						case 'unique_key':
						case 'unique':// old to be removed
							$tbl->addUniqueKeyConstraint($columns);

							break;

						case 'primary_key':
							$tbl->addPrimaryKeyConstraint($columns);

							break;

						case 'foreign_key':
							if (!isset($constraint['reference'])) {
								throw new DBALException(\sprintf(
									'You should declare foreign key "reference" table in table "%s".',
									$table_name
								));
							}

							$reference = $constraint['reference'];

							if (!isset($this->tables[$reference])) {
								throw new DBALException(\sprintf(
									'Reference table "%s" for foreign key in table "%s" is not defined.',
									$reference,
									$table_name
								));
							}

							$reference_table = $this->tables[$reference];
							$update_action   = null;
							$delete_action   = null;
							$name            = $constraint['name'] ?? null;

							if (isset($constraint['update'])) {
								$update_action = ForeignKeyAction::tryFrom($constraint['update']);
								if (!$update_action) {
									throw new DBALException(\sprintf(
										'Invalid update action "%s" for foreign key constraint.',
										$constraint['update']
									));
								}
							}

							if (isset($constraint['delete'])) {
								$delete_action = ForeignKeyAction::tryFrom($constraint['delete']);
								if (!$delete_action) {
									throw new DBALException(\sprintf(
										'Invalid delete action "%s" for foreign key constraint.',
										$constraint['delete']
									));
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
							throw new DBALException(\sprintf(
								'Unknown constraint type "%s" defined in table "%s".',
								$type,
								$table_name
							));
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

		// we could now add relations
		foreach ($tables_with_relations as $table_name) {
			$tbl           = $this->tables[$table_name];
			$table_options = $tables[$table_name];
			$relations     = $table_options['relations'];

			foreach ($relations as $relation_name => $rel_options) {
				try {
					$r = null;

					if ($rel_options instanceof Relation) {
						$r = $rel_options;
					} elseif (\is_array($rel_options) && isset($rel_options['type'], $rel_options['target'])) {
						$type   = RelationType::tryFrom($rel_options['type']);
						$target = $rel_options['target'];

						if (\is_string($target)) {
							$target = $this->getTableOrFail($target);
						} elseif (!$target instanceof Table) {
							throw new DBALException(\sprintf(
								'property "target" defined for relation "%s" in table "%s" should be of string|%s type not "%s".',
								$relation_name,
								$table_name,
								Table::class,
								\get_debug_type($target)
							));
						}

						$filters = $rel_options['filters'] ?? null;

						if ($filters && !\is_array($filters)) {
							throw new DBALException(\sprintf(
								'property "filters" defined for relation "%s" in table "%s" should be of array type not "%s".',
								$relation_name,
								$table_name,
								\get_debug_type($filters)
							));
						}

						$link_options = $rel_options['link'] ?? null;

						if ($link_options) {
							if ($link_options instanceof LinkInterface) {
								$link = $link_options;
							} elseif (\is_array($link_options)) {
								$link = Relation::createLink($this, $tbl, $target, $link_options);
							} else {
								throw new DBALException(\sprintf(
									'property "link" defined for relation "%s" in table "%s" should be of array|%s type not "%s".',
									$relation_name,
									$table_name,
									LinkInterface::class,
									\get_debug_type($link_options)
								));
							}
						} else {
							// old way to define relations
							$columns = $rel_options['columns'] ?? null;

							if ($columns) {
								if (!\is_array($columns)) {
									throw new DBALException(\sprintf(
										'property "columns" defined for relation "%s" in table "%s" should be of array type not "%s".',
										$relation_name,
										$table_name,
										\get_debug_type($columns)
									));
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
								throw new DBALException(\sprintf(
									'Invalid "link" type for relation "%s" in table "%s". Many to many relation should use a link through.',
									$relation_name,
									$table_name
								));
							}

							$r = new ManyToMany($relation_name, $link);
						}

						$r && $filters && $r->setTargetCustomFilters($filters);
					}

					if (null === $r) {
						throw new DBALException(\sprintf(
							'Invalid or incomplete option provided for relation "%s" in table "%s".',
							$relation_name,
							$table_name
						));
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

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function scope(string $namespace): DbBuilder
	{
		return new DbBuilder($this, $namespace);
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasTable(string $name): bool
	{
		return isset($this->tables[$name]) || isset($this->tbl_full_name_map[$name]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function assertHasTable(string $name): void
	{
		if (!$this->hasTable($name)) {
			throw new DBALRuntimeException(\sprintf('The table "%s" is not defined.', $name));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTable(string $name): ?Table
	{
		if ($this->hasTable($name)) {
			if (isset($this->tbl_full_name_map[$name])) {
				$name = $this->tbl_full_name_map[$name];
			}

			return $this->tables[$name];
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTableOrFail(string $name): Table
	{
		$this->assertHasTable($name);

		if (isset($this->tbl_full_name_map[$name])) {
			$name = $this->tbl_full_name_map[$name];
		}

		return $this->tables[$name];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTables(?string $namespace = null): array
	{
		if (null !== $namespace) {
			$results = [];

			foreach ($this->tables as $name => $table) {
				if ($namespace !== $table->getNamespace()) {
					continue;
				}

				$results[$name] = $table;
			}

			return $results;
		}

		return $this->tables;
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
	 * @return null|array
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
	 * Clean column type options.
	 *
	 * @param array $options The column type options
	 * @param bool  $clone   Whether the column is cloned or not
	 *
	 * @return array
	 */
	public static function cleanColumnTypeOptionsForReference(array $options, bool $clone): array
	{
		if (!$clone) {
			unset($options['auto_increment']);
		}

		unset($options['diff_key']);

		return $options;
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
	 * You don't need to define param circle
	 * it is for internal use only
	 * to prevent cyclic search that may cause infinite loop
	 *
	 * @param string $reference          The reference column path
	 * @param string $current_table_name The table in which the reference is
	 * @param array  $tables             Tables config array
	 * @param array  $circle             Contains all references, to prevent infinite loop (Internal use only)
	 *
	 * @return array
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function resolveColumnReference(
		string $reference,
		string $current_table_name,
		array $tables = [],
		array &$circle = []
	): array {
		if (isset($this->resolved_column_ref[$reference])) {
			return $this->resolved_column_ref[$reference];
		}

		if (\in_array($reference, $circle, true)) {
			$circle[] = $reference;

			throw new DBALException(\sprintf(
				'Possible cyclic reference path "%s" found while resolving column reference "%s" in table "%s".',
				\implode(' > ', $circle),
				$circle[0],
				$current_table_name
			));
		}

		$circle[] = $reference;
		$info     = static::parseColumnReference($reference);

		if ($info) {
			$_col_opt  = null;
			$clone     = $info['clone'];
			$ref_table = $info['table'];
			$ref_col   = $info['column'];

			if (isset($this->tables[$ref_table])) {
				$tbl = $this->tables[$ref_table];

				if ($col = $tbl->getColumn($ref_col)) {
					$_col_opt = $col->getType()
						->toArray();
				}
			} elseif (isset($tables[$ref_table])) {
				if ($tables[$ref_table] instanceof Table) {
					$tbl = $tables[$ref_table];

					if ($col = $tbl->getColumn($ref_col)) {
						$_col_opt = $col->getType()
							->toArray();
					}
				} elseif (\is_array($tables[$ref_table]) && isset($tables[$ref_table]['columns'][$ref_col])) {
					$ref_col_opt          = $tables[$ref_table]['columns'][$ref_col];
					$ref_type_to_override = null;

					if (\is_string($ref_col_opt)) {
						$type = $ref_col_opt;

						if (static::isColumnReference($ref_col_opt)) {
							$ref_type_to_override = $this->resolveColumnReference($type, $ref_table, $tables, $circle);
						}
					} elseif (\is_array($ref_col_opt)) {
						if (isset($ref_col_opt['type'])) {
							$type     = $ref_col_opt['type'];
							$_col_opt = $ref_col_opt;

							if (\is_string($type) && static::isColumnReference($type)) {
								$ref_type_to_override = $this->resolveColumnReference(
									$type,
									$ref_table,
									$tables,
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

		throw new DBALException(\sprintf(
			'Unable to resolve column type reference "%s" found in table "%s".',
			$reference,
			$current_table_name
		));
	}
}
