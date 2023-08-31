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
use Gobl\DBAL\Queries\Traits\QBSetColumnsValuesTrait;
use Gobl\DBAL\Table;
use InvalidArgumentException;
use LogicException;
use PHPUtils\Str;

/**
 * Class QBInsert.
 */
final class QBInsert implements QBInterface
{
	use QBCommonTrait;
	use QBSetColumnsValuesTrait;

	protected ?string $options_table = null;

	/**
	 * @var string[]
	 */
	protected array $options_columns_names = [];

	/**
	 * @var array<int, string[]>
	 */
	protected array $options_values_params = [];

	protected bool $auto_prefix = true;

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
	 * {@inheritDoc}
	 */
	public function getType(): QBType
	{
		return QBType::INSERT;
	}

	/**
	 * Specify the table to insert into.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param bool                    $auto_prefix
	 *
	 * @return $this
	 */
	public function into(string|Table $table, bool $auto_prefix = true): self
	{
		$table_name          = $this->resolveTable($table)
			?->getFullName() ?? $table;
		$this->options_table = $table_name;
		$this->auto_prefix   = $auto_prefix;

		return $this;
	}

	/**
	 * Specify values to insert.
	 *
	 * @param array<string, mixed> $values the column => value map
	 *
	 * @return $this
	 */
	public function values(array $values): self
	{
		if (!isset($this->options_table)) {
			throw new LogicException(\sprintf('You must call "%s" method first', Str::callableName([$this, 'into'])));
		}

		$map           = $this->bindColumnsValuesForInsertOrUpdate($this->options_table, $values, $this->auto_prefix);
		$columns       = \array_keys($map);
		$values_params = \array_values($map);

		if (empty($this->options_columns_names)) {
			$this->options_columns_names = $columns;
		} elseif ($this->options_columns_names !== $columns) {
			throw new InvalidArgumentException(
				\sprintf(
					'Invalid columns for multi insert, expected "(%s)" got "(%s)"',
					\implode(', ', $this->options_columns_names),
					\implode(', ', $columns)
				)
			);
		}

		$this->options_values_params[] = $values_params;

		return $this;
	}

	/**
	 * Gets the table to insert into.
	 *
	 * @return null|string
	 */
	public function getOptionsTable(): ?string
	{
		return $this->options_table;
	}

	/**
	 * Gets the inserted columns names.
	 *
	 * @return string[]
	 */
	public function getOptionsColumnsNames(): array
	{
		return $this->options_columns_names;
	}

	/**
	 * Gets the inserted values params.
	 *
	 * @return array<int, string[]>
	 */
	public function getOptionsValuesParams(): array
	{
		return $this->options_values_params;
	}
}
