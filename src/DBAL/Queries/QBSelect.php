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
use Gobl\DBAL\Queries\Traits\QBSetColumnsTrait;
use Gobl\DBAL\Queries\Traits\QBWhereTrait;
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
	use QBSetColumnsTrait;
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
	 * @return array
	 */
	public function getOptionsSelect(): array
	{
		return $this->options_select;
	}

	/**
	 * Returns query string to be executed by the rdbms.
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
	 * @param null|string $table
	 * @param array       $columns
	 * @param bool        $auto_prefix
	 *
	 * @return $this
	 */
	public function select(?string $table = null, array $columns = [], bool $auto_prefix = true): static
	{
		if (\is_string($table) && !empty($table)) {
			$table = $this->resolveTableFullName($table) ?? $table;

			if (empty($columns)) {
				$this->options_select[] = $table . '.*';
			} elseif ($auto_prefix) {
				if (\is_int(\array_key_first($columns))) {
					$columns = $this->prefixColumnsArray($table, $columns, true);
				} else {
					$keys    = $this->prefixColumnsArray($table, \array_keys($columns), true);
					$values  = \array_values($columns);
					$columns = \array_combine($keys, $values);
				}

				foreach ($columns as $key => $value) {
					$this->options_select[] = \is_int($key) ? $value : $key . ' as ' . $value;
				}
			} else {
				foreach ($columns as $key => $value) {
					$this->options_select[] = \is_int($key)
						? $table . '.' . $value : $table . '.' . $key . ' as ' . $value;
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
