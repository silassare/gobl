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
use Gobl\DBAL\Queries\Traits\QBJoinsTrait;
use Gobl\DBAL\Queries\Traits\QBLimitTrait;
use Gobl\DBAL\Queries\Traits\QBOrderByTrait;
use Gobl\DBAL\Queries\Traits\QBSetColumnsTrait;
use Gobl\DBAL\Queries\Traits\QBWhereTrait;

/**
 * Class QBUpdate.
 */
class QBUpdate implements QBInterface
{
	use QBCommonTrait;
	use QBJoinsTrait;
	use QBLimitTrait;
	use QBOrderByTrait;
	use QBSetColumnsTrait;
	use QBWhereTrait;

	protected ?string $options_table              = null;
	protected ?string $options_update_table_alias = '';

	/**
	 * QBUpdate constructor.
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
		return QBType::INSERT;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return int
	 */
	public function execute(): int
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->update($sql, $values, $types);
	}

	/**
	 * @return null|string
	 */
	public function getOptionsUpdateTableAlias(): ?string
	{
		return $this->options_update_table_alias;
	}

	/**
	 * @return null|string
	 */
	public function getOptionsTable(): ?string
	{
		return $this->options_table;
	}

	/**
	 * @param string      $table
	 * @param null|string $alias
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function update(string $table, ?string $alias = null): self
	{
		$this->options_table = $this->resolveTableFullName($table) ?? $table;

		if (!empty($alias)) {
			$this->useAlias($this->options_table, $alias);
			$this->options_update_table_alias = $alias;
		}

		return $this;
	}

	/**
	 * @param array $columns_values_map
	 * @param bool  $auto_prefix
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function set(array $columns_values_map, bool $auto_prefix = true): self
	{
		$table = $this->options_table;

		return $this->setInsertOrUpdateColumnsValues($table, $columns_values_map, $auto_prefix);
	}
}
