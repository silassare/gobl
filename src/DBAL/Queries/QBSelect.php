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

namespace Gobl\DBAL\Queries;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\Traits\QBCommonTrait;
use Gobl\DBAL\Queries\Traits\QBFromTrait;
use Gobl\DBAL\Queries\Traits\QBGroupByTrait;
use Gobl\DBAL\Queries\Traits\QBHavingTrait;
use Gobl\DBAL\Queries\Traits\QBJoinsTrait;
use Gobl\DBAL\Queries\Traits\QBLimitTrait;
use Gobl\DBAL\Queries\Traits\QBOrderByTrait;
use Gobl\DBAL\Queries\Traits\QBWhereTrait;
use Gobl\DBAL\Table;
use PDOStatement;

/**
 * Class QBSelect.
 */
class QBSelect implements QBInterface
{
	use QBCommonTrait;
	use QBFromTrait;
	use QBGroupByTrait;
	use QBHavingTrait;
	use QBJoinsTrait;
	use QBLimitTrait;
	use QBOrderByTrait;
	use QBWhereTrait;

	protected array $options_select = [];

	/**
	 * QBSelect constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): QBType
	{
		return QBType::SELECT;
	}

	/**
	 * @return array
	 */
	public function getOptionsSelect(): array
	{
		return $this->options_select;
	}

	/**
	 * Returns the total rows count.
	 *
	 * @param bool $preserve_limit
	 *
	 * @return int
	 */
	public function runTotalRowsCount(bool $preserve_limit): int
	{
		$offset = $this->options_limit_offset;
		$max    = $this->options_limit_max;

		if (!$preserve_limit) {
			$this->options_limit_offset = null;
			$this->options_limit_max    = null;
		}

		$sql = $this->db->getGenerator()
			->buildTotalRowCountQuery($this);

		$req = $this->db->execute($sql, $this->getBoundValues(), $this->getBoundValuesTypes());

		// restore limit
		$this->options_limit_offset = $offset;
		$this->options_limit_max    = $max;

		return $req->fetchColumn();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return PDOStatement
	 */
	public function execute(): PDOStatement
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->select($sql, $values, $types);
	}

	/**
	 * Adds columns to select.
	 *
	 * @param null|\Gobl\DBAL\Table|string $table_name_or_alias
	 * @param array                        $columns
	 * @param bool                         $auto_prefix
	 *
	 * @return $this
	 */
	public function select(null|string|Table $table_name_or_alias = null, array $columns = [], bool $auto_prefix = true): static
	{
		if (!empty($table_name_or_alias)) {
			$table_name = $this->resolveTable($table_name_or_alias)
				?->getFullName() ?? $table_name_or_alias;
			// when empty, we select all columns from the table
			if (empty($columns)) {
				$this->options_select[] = $this->fullyQualifiedNameArray($table_name_or_alias)[0];
			} elseif ($auto_prefix) {
				if (\is_int(\array_key_first($columns))) {
					$columns = $this->fullyQualifiedNameArray($table_name_or_alias, $columns);
				} else {
					$keys    = $this->fullyQualifiedNameArray($table_name_or_alias, \array_keys($columns));
					$values  = \array_values($columns);
					$columns = \array_combine($keys, $values);
				}

				foreach ($columns as $key => $value) {
					$this->options_select[] = \is_int($key) ? $value : $key . ' as ' . $value;
				}
			} else {
				foreach ($columns as $key => $value) {
					$this->options_select[] = \is_int($key)
						? $table_name . '.' . $value : $table_name . '.' . $key . ' as ' . $value;
				}
			}
		} else {
			foreach ($columns as $key => $value) {
				$this->options_select[] = \is_int($key) ? $value : $key . ' as ' . $value;
			}
		}

		return $this;
	}
}
