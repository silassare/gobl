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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Table;
use Throwable;

/**
 * Trait QBFromTrait.
 */
trait QBFromTrait
{
	protected bool $disable_multiple_from  = false;
	protected bool $disable_duplicate_from = false;

	/**
	 * @var array<string, string[]>
	 */
	protected array $options_from = [];

	/**
	 * Gets from options.
	 *
	 * @return array
	 */
	public function getOptionsFrom(): array
	{
		return $this->options_from;
	}

	/**
	 * Sets the from clause.
	 *
	 * @param array|QBSelect|string|Table $table
	 * @param null|string                 $alias
	 *
	 * ```php
	 * $this->from('users');
	 * $this->from('users','u');
	 * $this->from(['users', 'articles' => 'a', 'another_table']);
	 * ```
	 *
	 * @return $this
	 */
	public function from(array|QBSelect|string|Table $table, ?string $alias = null): static
	{
		if (
			$this->disable_multiple_from
			&& (!empty($this->options_from) || (\is_array($table) && \count($table) > 1))
		) {
			throw new DBALRuntimeException(
				\sprintf(
					'Multiple table definition for "from" clause are disabled for query type "%s" provided in "%s".',
					$this->getType()->name,
					static::class
				)
			);
		}

		// it is a derived table
		if ($table instanceof QBSelect) {
			if (!$alias) {
				throw new DBALRuntimeException(
					\sprintf(
						'Alias is required for derived table in "from" clause for query type "%s" provided in "%s".',
						$this->getType()->name,
						static::class
					)
				);
			}

			$query = $table;
			$sql   = $query->getSqlQuery();
			if (empty($sql)) {
				throw (new DBALRuntimeException(
					\sprintf(
						'Derived table query is empty in "from" clause for query type "%s" provided in "%s".',
						$this->getType()->name,
						static::class
					)
				))->suspectObject($query);
			}

			$this->bindMergeFrom($query);

			$this->addFromOptions('(' . $sql . ')', $alias);
		} elseif (\is_array($table)) {
			foreach ($table as $key => $value) {
				if (\is_int($key)) {
					$this->addFromOptions($value);
				} else {
					$this->addFromOptions($key, $value);
				}
			}
		} elseif ($table instanceof Table) {
			$this->addFromOptions($table->getFullName(), $alias);
		} else {
			$this->addFromOptions($table, $alias);
		}

		return $this;
	}

	/**
	 * Checks if a table is in the from clause.
	 * If an alias is provided, it will check if the table is in the from clause
	 * with the provided alias.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param null|string             $alias
	 *
	 * @return bool
	 */
	public function inFromClause(string|Table $table, string $alias = null): bool
	{
		$table = $table instanceof Table ? $table->getFullName() : $table;

		$found_table = isset($this->options_from[$table]);

		if ($found_table && $alias) {
			$found_table = \in_array($alias, $this->options_from[$table], true);
		}

		return $found_table;
	}

	/**
	 * Adds a table to the from clause.
	 *
	 * @param string      $table
	 * @param null|string $alias
	 */
	private function addFromOptions(string $table, ?string $alias = null): void
	{
		$table = $this->resolveTable($table)
			?->getFullName() ?? $table;

		$duplicate = $this->inFromClause($table);

		if ($duplicate && $this->disable_duplicate_from) {
			throw new DBALRuntimeException(
				\sprintf(
					'Table "%s" is already defined in "from" clause for query type "%s" provided in "%s".'
					. 'If you need the same table twice keep it simple and use a JOIN clause.',
					$table,
					$this->getType()->name,
					static::class
				)
			);
		}

		if (!$alias) {
			try {
				// if the table has a main alias we use it
				$alias = $this->getMainAlias($table);
			} catch (Throwable) {
				// do nothing
			}
		}

		$alias = $alias ?? QBUtils::newAlias();

		$this->alias($table, $alias, !$duplicate);

		$this->options_from[$table][] = $alias;
	}
}
