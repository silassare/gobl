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
use Gobl\DBAL\Table;

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
	 * @param \Gobl\DBAL\Table|string $table
	 * @param array                   $columns_values_map
	 * @param bool                    $auto_prefix
	 *
	 * @return $this
	 */
	public function into(string|Table $table, array $columns_values_map = [], bool $auto_prefix = true): self
	{
		$table_name          = $this->resolveTable($table)
			?->getFullName() ?? $table;
		$this->options_table = $table_name;

		return $this->setInsertOrUpdateColumnsValues($table_name, $columns_values_map, $auto_prefix);
	}

	/**
	 * @return null|string
	 */
	public function getOptionsTable(): ?string
	{
		return $this->options_table;
	}
}
