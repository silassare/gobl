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
use Gobl\DBAL\Queries\Traits\QBLimitTrait;
use Gobl\DBAL\Queries\Traits\QBOrderByTrait;
use Gobl\DBAL\Queries\Traits\QBSetColumnsValuesTrait;
use Gobl\DBAL\Queries\Traits\QBWhereTrait;
use LogicException;
use PHPUtils\Str;

/**
 * Class QBUpdate.
 */
final class QBUpdate implements QBInterface
{
	use QBCommonTrait;
	use QBLimitTrait;
	use QBOrderByTrait;
	use QBSetColumnsValuesTrait;
	use QBWhereTrait;

	protected ?string $options_table = null;

	/**
	 * Map of columns and bound parameters.
	 *
	 * @var array<string, string>
	 */
	protected array $options_columns              = [];
	protected ?string $options_update_table_alias = '';

	/**
	 * QBUpdate constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db) {}

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
	 * {@inheritDoc}
	 */
	public function getType(): QBType
	{
		return QBType::UPDATE;
	}

	/**
	 * Sets the table to update.
	 *
	 * @param string      $table
	 * @param null|string $alias
	 *
	 * @return $this
	 */
	public function update(string $table, ?string $alias = null): self
	{
		$this->options_table = $this->resolveTable($table)
			?->getFullName() ?? $table;

		if (!empty($alias)) {
			$this->alias($this->options_table, $alias);
			$this->options_update_table_alias = $alias;
		}

		return $this;
	}

	/**
	 * Gets the table to update alias.
	 *
	 * @return null|string
	 */
	public function getOptionsUpdateTableAlias(): ?string
	{
		return $this->options_update_table_alias;
	}

	/**
	 * Gets the table to update.
	 *
	 * @return null|string
	 */
	public function getOptionsTable(): ?string
	{
		return $this->options_table;
	}

	/**
	 * Gets the map of columns and bound parameters.
	 *
	 * @return array<string, string>
	 */
	public function getOptionsColumns(): array
	{
		return $this->options_columns;
	}

	/**
	 * Sets the columns and values to update.
	 *
	 * @param array $values      the column => value map
	 * @param bool  $auto_prefix if true, columns will be auto prefixed
	 *
	 * @return $this
	 */
	public function set(array $values, bool $auto_prefix = true): self
	{
		if (!isset($this->options_table)) {
			throw new LogicException(\sprintf('You must call "%s" method first', Str::callableName([$this, 'update'])));
		}

		$this->options_columns = $this->bindColumnsValuesForInsertOrUpdate($this->options_table, $values, $auto_prefix);

		return $this;
	}
}
