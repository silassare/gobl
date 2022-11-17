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
use Gobl\DBAL\Queries\Traits\QBSetColumnsTrait;

/**
 * Class QBInsert.
 */
class QBInsert implements QBInterface
{
	use QBCommonTrait;
	use QBSetColumnsTrait;

	protected ?string $options_table = null;

	/**
	 * QBInsert constructor.
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
	 * @return false|string
	 */
	public function execute(): string|false
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->insert($sql, $values, $types);
	}

	/**
	 * @param string $table
	 * @param array  $columns_values_map
	 * @param bool   $auto_prefix
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function into(string $table, array $columns_values_map = [], bool $auto_prefix = true): self
	{
		$this->options_table = $this->resolveTableFullName($table) ?? $table;

		return $this->setInsertOrUpdateColumnsValues($table, $columns_values_map, $auto_prefix);
	}

	/**
	 * @return null|string
	 */
	public function getOptionsTable(): ?string
	{
		return $this->options_table;
	}
}
