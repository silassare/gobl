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
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Table;
use PHPUtils\Str;

/**
 * Trait QBAliasTrait.
 */
trait QBAliasTrait
{
	/**
	 * Contains alias to table name or full name mapping.
	 * The full name is used when the table is registered in the db.
	 *
	 * @var array<string, string>
	 */
	private array $aliases_to_tables_map = [];

	/**
	 * Contains table name or full name to main alias mapping.
	 *
	 * @var array<string, string>
	 */
	private array $main_aliases = [];

	/**
	 * {@inheritDoc}
	 */
	public function setMainAlias(string|Table $table, string $alias): static
	{
		/** @var string $table_name */
		$table_name = $this->resolveTable($table)
			?->getFullName() ?? $table;

		if (isset($this->main_aliases[$table_name])) {
			if ($this->main_aliases[$table_name] !== $alias) {
				throw new DBALRuntimeException(\sprintf('The table "%s" already has "%s" as main alias.', $table_name, $alias));
			}
		} elseif (isset($this->aliases_to_tables_map[$alias])) {
			if ($this->aliases_to_tables_map[$alias] !== $table_name) {
				throw new DBALRuntimeException(\sprintf('The alias "%s" was not declared for the table "%s".', $alias, $table_name));
			}
		} else {
			throw new DBALRuntimeException(\sprintf('The alias "%s" was not yet declared for the table "%s".', $alias, $table_name));
		}

		$this->main_aliases[$table_name] = $alias;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMainAlias(string|Table $table, bool $declare = false): string
	{
		$table_name = $this->resolveTable($table)
			?->getFullName() ?? $table;

		if (isset($this->main_aliases[$table_name])) {
			return $this->main_aliases[$table_name];
		}

		if ($declare) {
			$alias = QBUtils::newAlias();

			$this->alias($table_name, $alias, true);

			return $alias;
		}

		throw new DBALRuntimeException(\sprintf(
			'The table "%s" has no main alias. This is probably because you did not declare it through the "%s", "%s" or using "from" clause.',
			$table_name,
			Str::callableName([$this, 'alias']),
			Str::callableName([$this, 'setMainAlias']),
		));
	}

	/**
	 * {@inheritDoc}
	 */
	public function isDeclaredAlias(string $str): bool
	{
		return isset($this->aliases_to_tables_map[$str]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAliasTable(string $alias): ?string
	{
		return $this->aliases_to_tables_map[$alias] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function alias(string|Table $table, string $alias, bool $main = false): static
	{
		$table_name = $this->resolveTable($table)
			?->getFullName() ?? $table;

		if (empty($alias) || !\preg_match(Table::ALIAS_REG, $alias)) {
			throw new DBALRuntimeException(\sprintf('alias "%s" should match: %s', $alias, Table::ALIAS_PATTERN));
		}

		/** @var string $table_name */
		$table_name = $this->resolveTable($table_name)
			?->getFullName() ?? $table_name;

		if (isset($this->aliases_to_tables_map[$alias])) {
			if ($this->aliases_to_tables_map[$alias] !== $table_name) {
				throw new DBALRuntimeException(\sprintf(
					'alias "%s" is already in use by "%s".',
					$alias,
					$this->aliases_to_tables_map[$alias]
				));
			}
		} else {
			$this->aliases_to_tables_map[$alias] = $table_name;
		}

		if ($main) {
			$this->setMainAlias($table, $alias);
		}

		return $this;
	}
}
