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

namespace Gobl\DBAL\Diff;

use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\Constraint;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\UniqueKey;
use Gobl\DBAL\Diff\Actions\ColumnAdded;
use Gobl\DBAL\Diff\Actions\ColumnDeleted;
use Gobl\DBAL\Diff\Actions\ColumnRenamed;
use Gobl\DBAL\Diff\Actions\ColumnTypeChanged;
use Gobl\DBAL\Diff\Actions\DBCharsetChanged;
use Gobl\DBAL\Diff\Actions\DBCollateChanged;
use Gobl\DBAL\Diff\Actions\ForeignKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\ForeignKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\TableAdded;
use Gobl\DBAL\Diff\Actions\TableDeleted;
use Gobl\DBAL\Diff\Actions\TableRenamed;
use Gobl\DBAL\Diff\Actions\UniqueKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\UniqueKeyConstraintDeleted;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use OLIUP\CG\PHPClass;
use OLIUP\CG\PHPFile;

/**
 * Class Diff.
 */
class Diff
{
	/**
	 * Diff constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db_from
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db_to
	 */
	public function __construct(protected RDBMSInterface $db_from, protected RDBMSInterface $db_to)
	{
	}

	/**
	 * Diff destructor.
	 */
	public function __destruct()
	{
		unset($this->db_from, $this->db_to);
	}

	/**
	 * Create diff file using two db.
	 *
	 * @return \OLIUP\CG\PHPFile
	 */
	public function generateMigrationFile(): PHPFile
	{
		$up       = $this->getDiff();
		$down     = (new self($this->db_to, $this->db_from))->getDiff();
		$gen_from = $this->db_from->getGenerator();
		$gen_to   = $this->db_to->getGenerator();

		$file  = new PHPFile();
		$class = new PHPClass();

		$class->implements(MigrationInterface::class);

		$up_sql   = \implode(\PHP_EOL, \array_map(static fn (DiffAction $item) => $gen_to->buildDiffActionQuery($item), $up));
		$down_sql = \implode(\PHP_EOL, \array_map(static fn (DiffAction $item) => $gen_from->buildDiffActionQuery($item), $down));

		$m_get_version = $class->newMethod('getVersion')
			->public()
			->setReturnType('int');
		$m_get_label   = $class->newMethod('getLabel')
			->public()
			->setReturnType('string');

		$m_get_timestamp = $class->newMethod('getTimestamp')
			->public()
			->setReturnType('int');

		$time = $version = \time();

		$m_get_label->addChild('return \'Auto generated.\';')
			->comment('@inheritDoc');

		$file->comment('Generated on: ' . \date('jS F Y, g:i a', $time));

		$m_get_timestamp->addChild('return ' . $time . ';')
			->comment('@inheritDoc');

		$m_get_version->addChild('return ' . $version . ';')
			->comment('@inheritDoc');

		$m_up = $class->newMethod('up')
			->public()
			->setReturnType('string');

		$m_up->addChild(\sprintf('return <<<DIFF_SQL
%s
DIFF_SQL;
', $up_sql))
			->comment('@inheritDoc');

		$m_down = $class->newMethod('down')
			->public()
			->setReturnType('string');

		$m_down->addChild(\sprintf('
return <<<DIFF_SQL
%s
DIFF_SQL;
', $down_sql))
			->comment('@inheritDoc');

		$tables = [];

		foreach ($this->db_to->getTables() as $table) {
			$tables[$table->getName()] = $table->toArray();
		}

		$m_get_configs = $class->newMethod('getConfigs')
			->public()
			->setReturnType('array');

		$m_get_configs->addChild(\sprintf('return %s;', \var_export($this->db_to->getConfig()
			->toSafeArray(), true)));

		$m_get_tables = $class->newMethod('getTables')
			->public()
			->setReturnType('array');

		$m_get_tables->addChild(\sprintf('return %s;', \var_export($tables, true)))
			->comment('@inheritDoc');

		return $file->addChild('return ' . $class . ';');
	}

	/**
	 * Check if db has changes.
	 *
	 * @return bool
	 */
	public function hasChanges(): bool
	{
		return \count($this->getDiff()) > 0;
	}

	/**
	 * Gets diff.
	 *
	 * @return \Gobl\DBAL\Diff\DiffAction[]
	 */
	public function getDiff(): array
	{
		$diff = [];
		$c_f  = $this->db_from->getConfig();
		$c_t  = $this->db_to->getConfig();

		if ($c_f->getDbCollate() !== $c_t->getDbCollate()) {
			$diff[] = new DBCollateChanged($c_t->getDbCollate(), $this->db_from);
		}

		if ($c_f->getDbCharset() !== $c_t->getDbCharset()) {
			$diff[] = new DBCharsetChanged($c_t->getDbCharset(), $this->db_from);
		}

		$from = $this->getTableKeysMap($this->db_from);
		$to   = $this->getTableKeysMap($this->db_to);

		foreach ($from as $key => $from_table) {
			$to_table = $to[$key] ?? $this->db_to->getTable($from_table->getName());

			if (!$to_table) {
				$diff[] = new TableDeleted($from_table);
				$reason = \sprintf('table "%s" was deleted.', $from_table->getFullName());

				foreach ($from_table->getForeignKeyConstraints() as $fk) {
					$diff[] = $this->getConstraintDeletedClassInstance($fk, $reason);
				}

				foreach ($from_table->getUniqueKeyConstraints() as $uq) {
					$diff[] = $this->getConstraintDeletedClassInstance($uq, $reason);
				}
			} else {
				$this->diffTable($from_table, $to_table, $diff);
			}
		}

		foreach ($to as $key => $to_table) {
			$from_table = $from[$key] ?? $this->db_from->getTable($to_table->getName());

			if (!$from_table) {
				$diff[] = new TableAdded($to_table);
				$reason = \sprintf('table "%s" was added.', $to_table->getFullName());

				foreach ($to_table->getForeignKeyConstraints() as $fk) {
					$diff[] = $this->getConstraintAddedClassInstance($fk, $reason);
				}

				foreach ($to_table->getUniqueKeyConstraints() as $uq) {
					$diff[] = $this->getConstraintAddedClassInstance($uq, $reason);
				}
			}
		}

		if (!empty($diff)) {
			\usort($diff, static function (DiffAction $a, DiffAction $b) {
				return $a->getType()
					->getPriority() - $b->getType()
					->getPriority();
			});
		}

		return $diff;
	}

	protected function getConstraintDeletedClassInstance(Constraint $constraint, string $reason = 'constraint deleted.'): PrimaryKeyConstraintDeleted|ForeignKeyConstraintDeleted|UniqueKeyConstraintDeleted
	{
		if ($constraint instanceof PrimaryKey) {
			return new PrimaryKeyConstraintDeleted($constraint, $reason);
		}

		if ($constraint instanceof UniqueKey) {
			return new UniqueKeyConstraintDeleted($constraint, $reason);
		}

		/** @var \Gobl\DBAL\Constraints\ForeignKey $constraint */
		return new ForeignKeyConstraintDeleted($constraint, $reason);
	}

	protected function diffTable(Table $from_table, Table $to_table, array &$diff): void
	{
		if ($from_table->getFullName() !== $to_table->getFullName()) {
			$reason = $from_table->getPrefix() !== $to_table->getPrefix() ? \sprintf(
				'table prefix changed from "%s" to "%s"',
				$from_table->getPrefix(),
				$to_table->getPrefix()
			) : \sprintf(
				'table name changed from "%s" to "%s"',
				$from_table->getName(),
				$to_table->getName()
			);

			$diff[] = new TableRenamed($from_table, $to_table, $reason);
		}

		$this->diffTableColumns($from_table, $to_table, $diff);
		$this->diffTablePKConstraint($from_table, $to_table, $diff);
		$this->diffTableFKConstraints($from_table, $to_table, $diff);
		$this->diffTableUQConstraints($from_table, $to_table, $diff);
	}

	/**
	 * Diff tables columns.
	 *
	 * @param \Gobl\DBAL\Table $from_table
	 * @param \Gobl\DBAL\Table $to_table
	 * @param array            $diff
	 */
	protected function diffTableColumns(Table $from_table, Table $to_table, array &$diff): void
	{
		$from         = $this->getColumnsKeysMap($from_table);
		$to           = $this->getColumnsKeysMap($to_table);
		$change_table = $to_table;

		foreach ($from as $key => $from_column) {
			$to_column = $to[$key] ?? $to_table->getColumn($from_column->getName());

			if (!$to_column) {
				$diff[] = new ColumnDeleted($change_table, $from_column);
			} else {
				$this->diffColumn($change_table, $from_column, $to_column, $diff);
			}
		}

		foreach ($to as $key => $to_column) {
			$from_column = $from[$key] ?? $from_table->getColumn($to_column->getName());
			if (!$from_column) {
				$diff[] = new ColumnAdded($change_table, $to_column);
			}
		}
	}

	protected function diffColumn(Table $table, Column $from_column, Column $to_column, array &$diff): void
	{
		if ($from_column->getFullName() !== $to_column->getFullName()) {
			$reason = $from_column->getPrefix() !== $to_column->getPrefix() ? \sprintf(
				'column prefix changed from "%s" to "%s"',
				$from_column->getPrefix(),
				$to_column->getPrefix()
			) : \sprintf(
				'column name changed from "%s" to "%s"',
				$from_column->getName(),
				$to_column->getName()
			);

			$diff[] = new ColumnRenamed($table, $from_column, $to_column, $reason);
		}

		if (!$this->db_from->getGenerator()
			->hasSameColumnTypeDefinition($from_column, $to_column)) {
			$diff[] = new ColumnTypeChanged($table, $from_column, $to_column);
		}
	}

	protected function diffTablePKConstraint(Table $from_table, Table $to_table, array &$diff): void
	{
		$a_pk = $from_table->getPrimaryKeyConstraint();
		$b_pk = $to_table->getPrimaryKeyConstraint();
		if ($a_pk) {
			if ($b_pk) {
				if (!empty(\array_diff($a_pk->getColumns(), $b_pk->getColumns()))) {
					// there is a change in the constraint columns types
					$diff[] = $this->getConstraintDeletedClassInstance($a_pk);
					$diff[] = $this->getConstraintAddedClassInstance($b_pk);
				}
			} else {
				$diff[] = $this->getConstraintDeletedClassInstance($a_pk);
			}
		} elseif ($b_pk) {
			$diff[] = $this->getConstraintAddedClassInstance($b_pk);
		}
	}

	protected function getConstraintAddedClassInstance(Constraint $constraint, string $reason = 'constraint deleted.'): ForeignKeyConstraintAdded|PrimaryKeyConstraintAdded|UniqueKeyConstraintAdded
	{
		if ($constraint instanceof PrimaryKey) {
			return new PrimaryKeyConstraintAdded($constraint, $reason);
		}

		if ($constraint instanceof UniqueKey) {
			return new UniqueKeyConstraintAdded($constraint, $reason);
		}

		/** @var \Gobl\DBAL\Constraints\ForeignKey $constraint */
		return new ForeignKeyConstraintAdded($constraint, $reason);
	}

	protected function diffTableFKConstraints(Table $from_table, Table $to_table, array &$diff): void
	{
		$from = $from_table->getForeignKeyConstraints();
		$to   = $to_table->getForeignKeyConstraints();

		foreach ($from as $key => $from_constraint) {
			$to_constraint = $to[$key] ?? null;
			if (!$to_constraint) {
				$diff[] = $this->getConstraintDeletedClassInstance($from_constraint);
			} else {
				$touched = '';
				if (($a = $from_constraint->getHostTable()
					->getFullName()) !== ($b = $to_constraint->getHostTable()
					->getFullName())) {
					$touched = \sprintf('constraint host table changed from "%s" to "%s".', $a, $b);
				} elseif (($a = $from_constraint->getReferenceTable()
					->getFullName()) !== ($b = $to_constraint->getReferenceTable()
					->getFullName())
				) {
					$touched = \sprintf('constraint reference table changed from "%s" to "%s".', $a, $b);
				} elseif ($from_constraint->getColumnsMapping() !== $to_constraint->getColumnsMapping()) {
					$touched = 'constraints column mapping changed.';
				} else {
					$gen = $this->db_from->getGenerator();
					foreach ($to_constraint->getColumnsMapping() as $host_column => $reference_column) {
						if (!$gen->hasSameColumnTypeDefinition(
							$from_constraint->getHostTable()
								->getColumnOrFail($host_column),
							$to_constraint->getHostTable()
								->getColumnOrFail($host_column)
						)) {
							$touched = \sprintf(
								'constraints column "%s" type changed in host table "%s".',
								$host_column,
								$from_constraint->getHostTable()
									->getFullName()
							);

							break;
						}
						if (!$gen->hasSameColumnTypeDefinition(
							$from_constraint->getReferenceTable()
								->getColumnOrFail($reference_column),
							$to_constraint->getReferenceTable()
								->getColumnOrFail($reference_column)
						)) {
							$touched = \sprintf(
								'constraints column "%s" type changed in reference table "%s".',
								$reference_column,
								$from_constraint->getReferenceTable()
									->getFullName()
							);

							break;
						}
					}
				}

				if ($touched) {
					$diff[] = $this->getConstraintDeletedClassInstance($from_constraint, $touched);
					$diff[] = $this->getConstraintAddedClassInstance($to_constraint, $touched);
				}
			}
		}

		foreach ($to as $key => $to_constraint) {
			if (!isset($from[$key])) {
				$diff[] = $this->getConstraintAddedClassInstance($to_constraint);
			}
		}
	}

	protected function diffTableUQConstraints(Table $from_table, Table $to_table, array &$diff): void
	{
		$from = $from_table->getUniqueKeyConstraints();
		$to   = $to_table->getUniqueKeyConstraints();

		foreach ($from as $key => $from_constraint) {
			$to_constraint = $to[$key] ?? null;
			if (!$to_constraint) {
				$diff[] = $this->getConstraintDeletedClassInstance($from_constraint);
			} else {
				$a_columns = $from_constraint->getColumns();
				$b_columns = $to_constraint->getColumns();
				$c_columns = \array_merge(\array_diff($a_columns, $b_columns), \array_diff($b_columns, $a_columns));
				if (!empty($c_columns)) {
					// there is a change in the constraint columns
					$reason = \sprintf('constraint columns (%s) has changed: rename, addition or deletion.', \implode(' , ', $c_columns));
					$diff[] = $this->getConstraintDeletedClassInstance($from_constraint, $reason);
					$diff[] = $this->getConstraintAddedClassInstance($to_constraint, $reason);
				}
			}
		}

		foreach ($to_table->getUniqueKeyConstraints() as $key => $to_constraint) {
			if (!isset($from[$key])) {
				$diff[] = $this->getConstraintAddedClassInstance($to_constraint);
			}
		}
	}

	/**
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 *
	 * @return array<string, \Gobl\DBAL\Table>
	 */
	private function getTableKeysMap(RDBMSInterface $db): array
	{
		$list = [];

		foreach ($db->getTables() as $table) {
			$list[$table->getDiffKey()] = $table;
		}

		return $list;
	}

	/**
	 * @return array<string, \Gobl\DBAL\Column>
	 */
	private function getColumnsKeysMap(Table $table): array
	{
		$list = [];

		foreach ($table->getColumns() as $column) {
			$list[$column->getDiffKey()] = $column;
		}

		return $list;
	}
}
