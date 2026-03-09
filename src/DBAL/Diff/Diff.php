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

namespace Gobl\DBAL\Diff;

use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\Constraint;
use Gobl\DBAL\Constraints\ForeignKey;
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
use Gobl\DBAL\Diff\Actions\IndexAdded;
use Gobl\DBAL\Diff\Actions\IndexDeleted;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\TableAdded;
use Gobl\DBAL\Diff\Actions\TableDeleted;
use Gobl\DBAL\Diff\Actions\TableRenamed;
use Gobl\DBAL\Diff\Actions\UniqueKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\UniqueKeyConstraintDeleted;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\MigrationMode;
use Gobl\DBAL\Table;
use OLIUP\CG\PHPClass;
use OLIUP\CG\PHPComment;
use OLIUP\CG\PHPFile;
use OLIUP\CG\PHPType;

/**
 * Class Diff.
 */
class Diff
{
	/**
	 * Diff constructor.
	 *
	 * @param RDBMSInterface $db_from
	 * @param RDBMSInterface $db_to
	 */
	public function __construct(protected RDBMSInterface $db_from, protected RDBMSInterface $db_to) {}

	/**
	 * Diff destructor.
	 */
	public function __destruct()
	{
		unset($this->db_from, $this->db_to);
	}

	/**
	 * Get destination db.
	 */
	public function getDbTo(): RDBMSInterface
	{
		return $this->db_to;
	}

	/**
	 * Get source db.
	 */
	public function getDbFrom(): RDBMSInterface
	{
		return $this->db_from;
	}

	/**
	 * Create diff file using two db.
	 *
	 * @param int         $version the migration version
	 * @param null|string $label   the migration label
	 *
	 * @return PHPFile
	 */
	public function generateMigrationFile(int $version, ?string $label = 'Auto generated.'): PHPFile
	{
		$up       = $this->getDiff();
		$down     = (new self($this->db_to, $this->db_from))->getDiff();
		$up_sql   = $this->diffSql($this->db_to, $up);
		$down_sql = $this->diffSql($this->db_from, $down);

		$file  = new PHPFile();
		$class = new PHPClass();

		$class->implements(MigrationInterface::class);

		$m_get_version = $class->newMethod('getVersion')
			->public()
			->setReturnType('int');
		$m_get_label = $class->newMethod('getLabel')
			->public()
			->setReturnType('string');

		$m_get_timestamp = $class->newMethod('getTimestamp')
			->public()
			->setReturnType('int');

		$m_before_run = $class->newMethod('beforeRun')
			->public()
			->setReturnType(new PHPType('bool', 'string'))
			->addChild(
				PHPComment::inline('TODO: implement your custom logic here')
			)
			->addChild(\PHP_EOL . 'return true;');

		$m_after_run = $class->newMethod('afterRun')
			->public()
			->setReturnType('void')
			->addChild(
				PHPComment::inline('TODO: implement your custom logic here')
			);

		$m_before_run->comment('@inheritDoc');
		$m_before_run->newArgument('mode')->setType('\\' . MigrationMode::class);
		$m_before_run->newArgument('query')->setType('string');

		$m_after_run->comment('@inheritDoc');
		$m_after_run->newArgument('mode')->setType('\\' . MigrationMode::class);

		$time = \time();

		$m_get_label->addChild(\sprintf('return <<<DIFF_LABEL
%s
DIFF_LABEL;
', $label))
			->comment('@inheritDoc');

		$file->comment('Generated on: ' . \date('jS F Y, g:i:s a', $time));

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

		$m_get_schema = $class->newMethod('getSchema')
			->public()
			->setReturnType('array');

		$m_get_schema->addChild(\sprintf('return %s;', \var_export($tables, true)))
			->comment('@inheritDoc');

		return $file->addChild('return ' . $class . ';');
	}

	/**
	 * Make an in-memory {@see MigrationInterface} instance from the current diff.
	 *
	 * Unlike {@see generateMigrationFile()}, this method does not produce source code;
	 * it returns a live PHP object that can be directly passed to a {@see MigrationRunner}.
	 *
	 * @param int         $version the migration version
	 * @param null|string $label   the migration label
	 *
	 * @return MigrationInterface
	 */
	public function makeMigrationInstance(int $version, ?string $label = 'Auto generated.'): MigrationInterface
	{
		$up       = $this->getDiff();
		$down     = (new self($this->db_to, $this->db_from))->getDiff();
		$up_sql   = $this->diffSql($this->db_to, $up);
		$down_sql = $this->diffSql($this->db_from, $down);

		$time    = \time();
		$configs = $this->db_to->getConfig()->toSafeArray();
		$tables  = [];

		foreach ($this->db_to->getTables() as $table) {
			$tables[$table->getName()] = $table->toArray();
		}

		return new class($version, $label ?? '', $time, $up_sql, $down_sql, $configs, $tables) implements MigrationInterface {
			/**
			 * Migration constructor.
			 *
			 * @param int    $version   the migration version number
			 * @param string $label     a human-readable migration label
			 * @param int    $timestamp the UNIX timestamp when the migration was generated
			 * @param string $up_sql    the SQL to run when migrating up
			 * @param string $down_sql  the SQL to run when rolling back
			 * @param array  $configs   the database configuration at migration time
			 * @param array  $schema    the full table schema at migration time
			 */
			public function __construct(
				private readonly int $version,
				private readonly string $label,
				private readonly int $timestamp,
				private readonly string $up_sql,
				private readonly string $down_sql,
				private readonly array $configs,
				private readonly array $schema,
			) {}

			public function getVersion(): int
			{
				return $this->version;
			}

			public function getLabel(): string
			{
				return $this->label;
			}

			public function getTimestamp(): int
			{
				return $this->timestamp;
			}

			public function getSchema(): array
			{
				return $this->schema;
			}

			public function getConfigs(): array
			{
				return $this->configs;
			}

			public function up(): string
			{
				return $this->up_sql;
			}

			public function down(): string
			{
				return $this->down_sql;
			}

			public function beforeRun(MigrationMode $mode, string $query): bool|string
			{
				return true;
			}

			public function afterRun(MigrationMode $mode): void {}
		};
	}

	/**
	 * Computes the ordered list of diff actions needed to migrate `db_from` to `db_to`.
	 *
	 * Two-pass algorithm:
	 * 1. **Change pass** (iterate `db_from` tables): for each existing table, either emit a
	 *    `TableDeleted` cascade (+ its FK/UK/index deletions) when the table is gone in `db_to`,
	 *    or delegate column/constraint diffing to `diffTable()`.
	 * 2. **Addition pass** (iterate `db_to` tables): for each table absent in `db_from`, emit a
	 *    `TableAdded` cascade (+ its FK/UK/index additions).
	 *
	 * The resulting `DiffAction[]` list is sorted by `DiffActionType::getPriority()` so that
	 * destructive actions (e.g. FK deletions) precede constructive ones (e.g. table creation),
	 * producing a migration that can run without constraint violations.
	 *
	 * @return DiffAction[]
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

				foreach ($from_table->getIndexes() as $idx) {
					$diff[] = new IndexDeleted($idx, $reason);
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

				foreach ($to_table->getIndexes() as $idx) {
					$diff[] = new IndexAdded($idx, $reason);
				}
			}
		}

		if (!empty($diff)) {
			\usort($diff, static fn (DiffAction $a, DiffAction $b) => $a->getType()->getPriority() - $b->getType()->getPriority());
		}

		return $diff;
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
	 * Creates the appropriate deleted-constraint diff action instance for the given constraint.
	 *
	 * @param Constraint $constraint the constraint that was deleted
	 * @param string     $reason     optional human-readable reason for the deletion
	 *
	 * @return ForeignKeyConstraintDeleted|PrimaryKeyConstraintDeleted|UniqueKeyConstraintDeleted
	 */
	protected function getConstraintDeletedClassInstance(Constraint $constraint, string $reason = ''): ForeignKeyConstraintDeleted|PrimaryKeyConstraintDeleted|UniqueKeyConstraintDeleted
	{
		if ($constraint instanceof PrimaryKey) {
			$c = new PrimaryKeyConstraintDeleted($constraint);
		} elseif ($constraint instanceof UniqueKey) {
			$c = new UniqueKeyConstraintDeleted($constraint);
		} else {
			/** @var ForeignKey $constraint */
			$c = new ForeignKeyConstraintDeleted($constraint);
		}

		!empty($reason) && $c->setReason($reason);

		return $c;
	}

	/**
	 * Computes the diff between two table versions and appends any detected change actions to $diff.
	 *
	 * Checks renames, columns, primary/foreign/unique-key constraints, and indexes.
	 *
	 * @param Table $from_table the original table state
	 * @param Table $to_table   the target table state
	 * @param array $diff       the accumulator array of {@see DiffAction} objects
	 */
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
		$this->diffTableIndexes($from_table, $to_table, $diff);
	}

	/**
	 * Diff tables columns.
	 *
	 * @param Table $from_table
	 * @param Table $to_table
	 * @param array $diff
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

	/**
	 * Computes the diff between two column versions and appends rename/type-change actions to $diff.
	 *
	 * @param Table  $table       the table that owns the columns
	 * @param Column $from_column the original column state
	 * @param Column $to_column   the target column state
	 * @param array  $diff        the accumulator array of {@see DiffAction} objects
	 */
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

	/**
	 * Computes the diff of primary-key constraints between two table versions and appends actions to $diff.
	 *
	 * @param Table $from_table the original table state
	 * @param Table $to_table   the target table state
	 * @param array $diff       the accumulator array of {@see DiffAction} objects
	 */
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

	/**
	 * Creates the appropriate added-constraint diff action instance for the given constraint.
	 *
	 * @param Constraint $constraint the constraint that was added
	 * @param string     $reason     optional human-readable reason for the addition
	 *
	 * @return ForeignKeyConstraintAdded|PrimaryKeyConstraintAdded|UniqueKeyConstraintAdded
	 */
	protected function getConstraintAddedClassInstance(Constraint $constraint, string $reason = ''): ForeignKeyConstraintAdded|PrimaryKeyConstraintAdded|UniqueKeyConstraintAdded
	{
		if ($constraint instanceof PrimaryKey) {
			$c = new PrimaryKeyConstraintAdded($constraint, $reason);
		} elseif ($constraint instanceof UniqueKey) {
			$c = new UniqueKeyConstraintAdded($constraint, $reason);
		} else {
			/** @var ForeignKey $constraint */
			$c = new ForeignKeyConstraintAdded($constraint, $reason);
		}

		!empty($reason) && $c->setReason($reason);

		return $c;
	}

	/**
	 * Computes the diff of foreign-key constraints between two table versions and appends actions to $diff.
	 *
	 * Detects added, removed, or structurally changed foreign-key constraints.
	 *
	 * @param Table $from_table the original table state
	 * @param Table $to_table   the target table state
	 * @param array $diff       the accumulator array of {@see DiffAction} objects
	 */
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
			if (isset($from[$key])) {
				continue;
			}

			$diff[] = $this->getConstraintAddedClassInstance($to_constraint);
		}
	}

	/**
	 * Computes the diff of unique-key constraints between two table versions and appends actions to $diff.
	 *
	 * Detects added, removed, or column-changed unique-key constraints.
	 *
	 * @param Table $from_table the original table state
	 * @param Table $to_table   the target table state
	 * @param array $diff       the accumulator array of {@see DiffAction} objects
	 */
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
					$reason = \sprintf('constraint columns (%s) has changed: rename, addition or deletion.', \implode(', ', $c_columns));
					$diff[] = $this->getConstraintDeletedClassInstance($from_constraint, $reason);
					$diff[] = $this->getConstraintAddedClassInstance($to_constraint, $reason);
				}
			}
		}

		foreach ($to_table->getUniqueKeyConstraints() as $key => $to_constraint) {
			if (isset($from[$key])) {
				continue;
			}

			$diff[] = $this->getConstraintAddedClassInstance($to_constraint);
		}
	}

	/**
	 * Computes the diff of indexes between two table versions and appends actions to $diff.
	 *
	 * Detects added, removed, or type/column-changed indexes.
	 *
	 * @param Table $from_table the original table state
	 * @param Table $to_table   the target table state
	 * @param array $diff       the accumulator array of {@see DiffAction} objects
	 */
	protected function diffTableIndexes(Table $from_table, Table $to_table, array &$diff): void
	{
		$from = $from_table->getIndexes();
		$to   = $to_table->getIndexes();

		foreach ($from as $key => $from_index) {
			$to_index = $to[$key] ?? null;
			if (!$to_index) {
				$diff[] = new IndexDeleted($from_index);
			} else {
				$a_columns    = $from_index->getColumns();
				$b_columns    = $to_index->getColumns();
				$c_columns    = \array_merge(\array_diff($a_columns, $b_columns), \array_diff($b_columns, $a_columns));
				$type_changed = $from_index->getType() !== $to_index->getType();
				if (!empty($c_columns) || $type_changed) {
					$reason = $type_changed
						? \sprintf('index type changed from "%s" to "%s".', $from_index->getType() ?? 'default', $to_index->getType() ?? 'default')
						: \sprintf('index columns (%s) has changed: rename, addition or deletion.', \implode(', ', $c_columns));
					$diff[] = new IndexDeleted($from_index, $reason);
					$diff[] = new IndexAdded($to_index, $reason);
				}
			}
		}

		foreach ($to as $key => $to_index) {
			if (isset($from[$key])) {
				continue;
			}

			$diff[] = new IndexAdded($to_index);
		}
	}

	/**
	 * Renders a list of `DiffAction` objects into a single SQL string.
	 *
	 * Each action is converted via `$db`'s generator `buildDiffActionQuery()`, then the
	 * results are joined with newlines and wrapped in a database-definition block via
	 * `wrapDatabaseDefinitionQuery()`. Returns an empty string when all actions produce
	 * empty SQL (e.g. no-op actions).
	 *
	 * @param RDBMSInterface $db           the target database (determines the SQL dialect)
	 * @param DiffAction[]   $diff_actions
	 *
	 * @return string
	 */
	private function diffSql(RDBMSInterface $db, array $diff_actions): string
	{
		$gen = $db->getGenerator();
		$sql = \implode(\PHP_EOL, \array_map($gen->buildDiffActionQuery(...), $diff_actions));

		if (!empty(\trim($sql))) {
			$sql = $gen->wrapDatabaseDefinitionQuery($sql);
		}

		return $sql;
	}

	/**
	 * Builds a `diffKey => Table` map for all tables in `$db`.
	 *
	 * The diff key is obtained from `Table::getDiffKey()` and is used to match tables
	 * across the two schemas even when their names have changed (renames are tracked
	 * separately).
	 *
	 * @param RDBMSInterface $db
	 *
	 * @return array<string, Table>
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
	 * Builds a `diffKey => Column` map for all columns in `$table`.
	 *
	 * Used during `diffTable()` to match columns across old and new schema versions
	 * even after renames. The diff key is obtained from `Column::getDiffKey()`.
	 *
	 * @return array<string, Column>
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
