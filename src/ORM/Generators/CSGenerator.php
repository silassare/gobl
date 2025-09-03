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

namespace Gobl\ORM\Generators;

use BackedEnum;
use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\Exceptions\GoblRuntimeException;
use Gobl\Gobl;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\Utils\ORMClassKind;
use OLIUP\CG\PHPEnum;
use PHPUtils\Str;

/**
 * Class CSGenerator.
 */
abstract class CSGenerator
{
	protected RDBMSInterface $db;

	protected bool $ignore_private_tables    = true;
	protected bool $ignore_private_columns   = true;
	protected bool $ignore_sensitive_columns = false;

	/**
	 * @var array<string, string>
	 */
	protected array $enums_checked = [];

	/**
	 * @var array<string, array<string,int|string>>
	 */
	protected array $enums_infos = [];

	/**
	 * CSGenerator constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(
		RDBMSInterface $db,
	) {
		$this->db = $db;
	}

	/**
	 * Ignores private table.
	 *
	 * @param bool $ignore
	 *
	 * @return static
	 */
	public function ignorePrivateTables(bool $ignore = true): static
	{
		$this->ignore_private_tables = $ignore;

		return $this;
	}

	/**
	 * Ignores sensitive column.
	 *
	 * @param bool $ignore
	 *
	 * @return static
	 */
	public function ignoreSensitiveColumns(bool $ignore = true): static
	{
		$this->ignore_sensitive_columns = $ignore;

		return $this;
	}

	/**
	 * Ignores private column.
	 *
	 * @param bool $ignore
	 *
	 * @return static
	 */
	public function ignorePrivateColumns(bool $ignore = true): static
	{
		$this->ignore_private_columns = $ignore;

		return $this;
	}

	/**
	 * Returns array that can be used to generate file.
	 *
	 * @param Table $table
	 *
	 * @return array
	 */
	public function describeTable(Table $table): array
	{
		$inject              = $this->getTableInject($table);
		$inject['columns']   = $this->describeTableColumns($table);
		$inject['relations'] = $this->describeTableRelations($table);

		return $inject;
	}

	/**
	 * Gets data to be used in template file for a given table.
	 *
	 * @param Table $table the table object
	 *
	 * @return array
	 */
	public function getTableInject(Table $table): array
	{
		$ns         = $table->getNamespace();
		$pk_columns = [];

		if ($table->hasPrimaryKeyConstraint()) {
			/** @var PrimaryKey $pk */
			$pk = $table->getPrimaryKeyConstraint();

			foreach ($pk->getColumns() as $column_name) {
				$pk_columns[] = $this->describeColumn($table->getColumnOrFail($column_name));
			}
		}

		return [
			'namespace'  => $ns,
			'pk_columns' => $pk_columns,
			'private'    => $table->isPrivate(),
			'class'      => [
				'crud'       => ORMClassKind::CRUD->getClassName($table),
				'entity'     => ORMClassKind::ENTITY->getClassName($table),
				'results'    => ORMClassKind::RESULTS->getClassName($table),
				'query'      => ORMClassKind::QUERY->getClassName($table),
				'controller' => ORMClassKind::CONTROLLER->getClassName($table),
			],
			'table' => [
				'name'     => $table->getName(),
				'singular' => $table->getSingularName(),
			],
		];
	}

	/**
	 * Gets column data to be used in template file.
	 *
	 * @param Column $column
	 *
	 * @return array
	 */
	public function describeColumn(Column $column): array
	{
		$column_name = $column->getName();
		$type        = $column->getType();

		$filtersRules = [];

		if ($type instanceof TypeEnum) {
			try {
				$enum_class = $type->getEnumClass();
				$this->declareEnum($enum_class, $column->getTable()->getName());
			} catch (TypesException $t) {
				throw new GoblRuntimeException('Enum class not found for column "' . $column->getFullName() . '".', null, $t);
			}
		}

		foreach ($type->getAllowedFilterOperators() as $operator) {
			$method = Str::toMethodName('where_' . $operator->getFilterSuffix($column));

			$rule = [
				'name'                 => $operator->value,
				'method'               => $method,
				'rightOperandTypeHint' => $this->toTypeHintString(
					ORMTypeHint::getOperatorRightOperandTypesHint($type, $operator)
				),
				'noArg' => 1 === $operator->getOperandsCount(),
			];

			$filtersRules[] = $rule;
		}

		return [
			'private'           => $column->isPrivate(),
			'sensitive'         => $column->isSensitive(),
			'name'              => $column_name,
			'fullName'          => $column->getFullName(),
			'prefix'            => $column->getPrefix(),
			'isAutoIncremented' => $type->isAutoIncremented(),
			'isNullable'        => $type->isNullable(),
			'hasDefault'        => $type->hasDefault(),
			'filtersRules'      => $filtersRules,
			'methodSuffix'      => Str::toClassName($column_name),
			'const'             => self::toColumnNameConst($column),
			'argName'           => $column_name,
			'writeTypeHint'     => $this->getWriteTypeHintString($type),
			'readTypeHint'      => $this->getReadTypeHintString($type),
			'readTypeHintSaved' => $this->getReadTypeHintString($type, true),
		];
	}

	/**
	 * Map type to custom type string.
	 *
	 * @param ORMTypeHint $type_hint
	 *
	 * @return string
	 */
	abstract public function toTypeHintString(ORMTypeHint $type_hint): string;

	/**
	 * Returns column name constants name.
	 *
	 * @param Column $column
	 *
	 * @return string
	 */
	public static function toColumnNameConst(Column $column): string
	{
		return 'COL_' . \strtoupper($column->getName());
	}

	/**
	 * @param TypeInterface $type
	 *
	 * @return string
	 */
	public function getWriteTypeHintString(TypeInterface $type): string
	{
		$type_hint = $type->getWriteTypeHint();

		if ($type->isAutoIncremented() || $type->isNullable()) {
			$type_hint->nullable();
		}

		return $this->toTypeHintString($type_hint);
	}

	/**
	 * @param TypeInterface $type
	 * @param bool          $saved
	 *
	 * @return string
	 */
	public function getReadTypeHintString(TypeInterface $type, bool $saved = false): string
	{
		$type_hint   = $type->getReadTypeHint();
		$is_nullable = $type->isNullable();

		if ($type->isAutoIncremented() && !$saved) {
			$is_nullable = true;
		}

		if ($is_nullable) {
			$type_hint->nullable();
		}

		return $this->toTypeHintString($type_hint);
	}

	/**
	 * Gets columns data to be used in template file for a given table.
	 *
	 * @param Table $table the table object
	 *
	 * @return array
	 */
	public function describeTableColumns(Table $table): array
	{
		$columns = $table->getColumns();
		$list    = [];

		foreach ($columns as $column) {
			if ($this->ignore_private_columns && $column->isPrivate()) {
				continue;
			}
			if ($this->ignore_sensitive_columns && $column->isSensitive()) {
				continue;
			}

			$list[$column->getFullName()] = $this->describeColumn($column);
		}

		return $list;
	}

	/**
	 * Gets relations data to be used in template file for a given table.
	 *
	 * @param Table $table the table object
	 *
	 * @return array
	 */
	public function describeTableRelations(Table $table): array
	{
		$use       = [];
		$relations = $table->getRelations();
		$list      = [];

		foreach ($relations as /* $relation_name => */ $relation) {
			$type         = $relation->getType();
			$host_table   = $relation->getHostTable();
			$target_table = $relation->getTargetTable();

			$use[] = ORMClassKind::CONTROLLER->getClassFQN(
				$target_table,
				true
			) . ' as ' . ORMClassKind::CONTROLLER->getClassName($target_table) . 'RealR';
			$use[] = ORMClassKind::ENTITY->getClassFQN(
				$target_table,
				true
			) . ' as ' . ORMClassKind::ENTITY->getClassName($target_table) . 'RealR';

			$r_name = $relation->getName();
			$list[] = [
				'name'         => $r_name,
				'type'         => $type->value,
				'methodSuffix' => Str::toClassName($r_name),
				'host'         => $this->getTableInject($host_table),
				'target'       => $this->getTableInject($target_table),
			];
		}

		return [
			'use'  => \array_unique($use),
			'list' => $list,
		];
	}

	/**
	 * Generate classes for tables in the database.
	 *
	 * @param Table[] $tables the tables list
	 * @param string  $path   the destination folder path
	 * @param string  $header the source header to use
	 *
	 * @return $this
	 */
	abstract public function generate(array $tables, string $path, string $header = ''): static;

	/**
	 * @param class-string<BackedEnum> $enum_class
	 * @param string                   $table_name
	 */
	protected function declareEnum(string $enum_class, string $table_name): void
	{
		if (!isset($this->enums_checked[$enum_class])) {
			$name = (new PHPEnum($enum_class))->getName();

			if (isset($this->enums_infos[$name])) {
				$name = Str::toClassName($table_name . '_' . $name);
			}

			$cases = [];

			foreach ($enum_class::cases() as $case) {
				$v                  = $case->value;
				$cases[$case->name] = \is_int($v) ? $v : '\'' . $v . '\'';
			}

			$this->enums_infos[$name]         = $cases;
			$this->enums_checked[$enum_class] = $name;
		}
	}

	/**
	 * Returns file header doc.
	 *
	 * @return string
	 */
	protected static function getHeaderDoc(): string
	{
		$comment = <<<'COMMENT'
/**
 * Auto generated file,
 *
 * INFO: you are free to edit it,
 * but make sure to know what you are doing.
 *
 * Proudly With: {gobl_version}
 * Time: {gobl_time}
 */
COMMENT;

		return Str::interpolate($comment, [
			'gobl_version' => GOBL_VERSION,
			'gobl_time'    => Gobl::getGeneratedAtDate(),
		]);
	}

	/**
	 * Write contents to file.
	 *
	 * @param string $path      the file path
	 * @param string $content   the file content
	 * @param bool   $overwrite overwrite file if exists, default is true
	 */
	protected function writeFile(string $path, string $content, bool $overwrite = true): void
	{
		if (!$overwrite && \file_exists($path)) {
			return;
		}

		\file_put_contents($path, $content);
	}
}
