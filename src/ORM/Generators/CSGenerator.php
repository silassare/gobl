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

use Gobl\DBAL\Column;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\Utils\ORMClassKind;
use PHPUtils\Str;

/**
 * Class CSGenerator.
 */
abstract class CSGenerator
{
	protected RDBMSInterface $db;

	protected bool $ignore_private_table;

	protected bool $ignore_private_column;

	/**
	 * CSGenerator constructor.
	 *
	 * @param RDBMSInterface $db
	 * @param bool           $ignore_private_table
	 * @param bool           $ignore_private_column
	 */
	public function __construct(
		RDBMSInterface $db,
		bool $ignore_private_table = true,
		bool $ignore_private_column = true
	) {
		$this->db                    = $db;
		$this->ignore_private_table  = $ignore_private_table;
		$this->ignore_private_column = $ignore_private_column;
	}

	/**
	 * Returns array that can be used to generate file.
	 *
	 * @param \Gobl\DBAL\Table $table
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
	 * @param \Gobl\DBAL\Table $table the table object
	 *
	 * @return array
	 */
	public function getTableInject(Table $table): array
	{
		$ns         = $table->getNamespace();
		$pk_columns = [];

		if ($table->hasPrimaryKeyConstraint()) {
			/** @var \Gobl\DBAL\Constraints\PrimaryKey $pk */
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
				'entity_vr'  => ORMClassKind::ENTITY_VR->getClassName($table),
				'results'    => ORMClassKind::RESULTS->getClassName($table),
				'query'      => ORMClassKind::QUERY->getClassName($table),
				'controller' => ORMClassKind::CONTROLLER->getClassName($table),
			],
			'table'      => [
				'name'     => $table->getName(),
				'singular' => $table->getSingularName(),
			],
		];
	}

	/**
	 * Gets column data to be used in template file.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return array
	 */
	public function describeColumn(Column $column): array
	{
		$column_name = $column->getName();
		$type        = $column->getType();

		$filtersRules = [];

		foreach ($type->getAllowedFilterOperators() as $operator) {
			$method = Str::toMethodName('where_' . $operator->getFilterSuffix($column));

			$rule = [
				'name'                 => $operator->value,
				'method'               => $method,
				'rightOperandTypeHint' => $this->toTypeHintString(
					ORMTypeHint::getOperatorRightOperandTypesHint($type, $operator)
				),
				'noArg'                => 1 === $operator->getOperandsCount(),
			];

			$filtersRules[] = $rule;
		}

		return [
			'private'           => $column->isPrivate(),
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
			'writeTypeHint'     => $this->getColumnWriteTypeHintString($column),
			'readTypeHint'      => $this->getColumnReadTypeHintString($column),
			'readTypeHintSaved' => $this->getColumnReadTypeHintString($column, true),
		];
	}

	/**
	 * Map type to custom type string.
	 *
	 * @param \Gobl\ORM\ORMTypeHint $type_hint
	 *
	 * @return string
	 */
	abstract public function toTypeHintString(ORMTypeHint $type_hint): string;

	/**
	 * Returns column name constants name.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 */
	public static function toColumnNameConst(Column $column): string
	{
		return 'COL_' . \strtoupper($column->getName());
	}

	/**
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 */
	public function getColumnWriteTypeHintString(Column $column): string
	{
		$type      = $column->getType();
		$type_hint = $type->getWriteTypeHint();

		if ($type->isAutoIncremented() || $type->isNullable()) {
			$type_hint->nullable();
		}

		return $this->toTypeHintString($type_hint);
	}

	/**
	 * @param \Gobl\DBAL\Column $column
	 * @param bool              $saved
	 *
	 * @return string
	 */
	public function getColumnReadTypeHintString(Column $column, bool $saved = false): string
	{
		$type        = $column->getType();
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
	 * @param \Gobl\DBAL\Table $table the table object
	 *
	 * @return array
	 */
	public function describeTableColumns(Table $table): array
	{
		$columns = $table->getColumns();
		$list    = [];

		foreach ($columns as $column) {
			if (!($this->ignore_private_column && $column->isPrivate())) {
				$list[$column->getFullName()] = $this->describeColumn($column);
			}
		}

		return $list;
	}

	/**
	 * Gets relations data to be used in template file for a given table.
	 *
	 * @param \Gobl\DBAL\Table $table the table object
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
