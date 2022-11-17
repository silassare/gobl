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

use Gobl\DBAL\Exceptions\DBALException;

/**
 * Trait QBFromTrait.
 */
trait QBFromTrait
{
	protected bool  $disable_multiple_from = false;
	protected array $options_from          = [];

	/**
	 * @return array
	 */
	public function getOptionsFrom(): array
	{
		return $this->options_from;
	}

	/**
	 * @param array|string $table
	 * @param null|string  $alias
	 *
	 * ```php
	 * $this->from('users');
	 * $this->from('users','u');
	 * $this->from(['users', 'articles' => 'a', 'another_table']);
	 * ```
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function from(array|string $table, ?string $alias = null): static
	{
		if (
			$this->disable_multiple_from
			&& (!empty($this->options_from) || (\is_array($table) && \count($table) > 1))
		) {
			throw new DBALException(
				\sprintf(
					'Multiple table definition for "from" clause are disabled for query type "%s" provided in "%s".',
					$this->getType()->name,
					static::class
				)
			);
		}

		if (\is_array($table)) {
			foreach ($table as $key => $value) {
				if (\is_int($key)) {
					$this->addFromOptions($value);
				} else {
					$this->addFromOptions($key, $value);
				}
			}
		} else {
			$this->addFromOptions($table, $alias);
		}

		return $this;
	}

	/**
	 * @param string      $table
	 * @param null|string $alias
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	private function addFromOptions(string $table, ?string $alias = null): void
	{
		$table = $this->resolveTableFullName($table) ?? $table;

		if (!empty($alias)) {
			$this->useAlias($table, $alias);
		}

		$this->options_from[$table] = $alias;
	}
}
