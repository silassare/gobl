<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Relations;

use BackedEnum;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Table;
use Throwable;

/**
 * What a link's filters are about, read off the filters as applying the link parses them
 * ({@see LinkInterface::getFilters()}): which table each condition names, and which ones every
 * relative satisfies.
 *
 * Read without a query and before any request: what decides whether a relation's candidates can be
 * found by themselves, and what a write through a relation sets so that its row is a relative.
 * Filters that cannot be read -- a name neither table has, raw SQL -- qualify for nothing.
 */
final class LinkFilters
{
	/**
	 * Whether every condition names a column of this table, against a value written in the
	 * declaration: not a column, an expression or a query.
	 */
	public static function onlyOn(LinkInterface $link, Table $table): bool
	{
		$filters = self::read($link);

		if (null === $filters) {
			return false;
		}

		foreach ($filters->getConditions() as $condition) {
			if (!self::isOnWithWrittenValue($condition, $table)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether every condition is an equality on a column of this table that every relative
	 * satisfies: what a row can be written with, so that it holds.
	 */
	public static function onlyEqualitiesOn(LinkInterface $link, Table $table): bool
	{
		$filters = self::read($link);

		if (null === $filters) {
			return false;
		}

		$all = $filters->getConditions();

		if (\count($all) !== \count($filters->getRequiredConditions())) {
			return false;
		}

		foreach ($all as $condition) {
			if (!self::isOnWithWrittenValue($condition, $table) || Operator::EQ !== $condition->getOperator()) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The columns of this table the filters require a single value of, by full name: their `eq`
	 * conditions every relative satisfies, against a value written in the declaration.
	 *
	 * What a row written through the relation is written with. A condition this cannot write -- on
	 * another table, not an equality, inside an `or` -- is still checked once the row is written.
	 *
	 * @return array<string, mixed>
	 */
	public static function requiredEqualities(LinkInterface $link, Table $table): array
	{
		$filters = self::read($link);
		$out     = [];

		foreach ($filters?->getRequiredConditions() ?? [] as $condition) {
			if (self::isOnWithWrittenValue($condition, $table) && Operator::EQ === $condition->getOperator()) {
				$column = $condition->getLeftOperand()->getResolvedColumnOrFail()->getColumnName();
				$out[$table->getColumnOrFail($column)->getFullName()] = self::plain($condition->getRightOperand()?->getValueAsDefined());
			}
		}

		return $out;
	}

	/**
	 * The link's filters, or `null` where they cannot be read.
	 */
	private static function read(LinkInterface $link): ?Filters
	{
		try {
			return $link->getFilters();
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * Whether a condition is a comparison of a column of this table with a value written in the
	 * declaration.
	 *
	 * @psalm-assert-if-true Filter $condition
	 */
	private static function isOnWithWrittenValue(mixed $condition, Table $table): bool
	{
		if (!$condition instanceof Filter) {
			return false;
		}

		$left = $condition->getLeftOperand();

		if (!$left->hasResolvedColumn() || $left->getResolvedColumnOrFail()->getTable() !== $table) {
			return false;
		}

		$right = $condition->getRightOperand();

		return null === $right || self::isWrittenValue($right->getValueAsDefined());
	}

	/**
	 * A written value as a row holds it: an enum's case as its value.
	 */
	private static function plain(mixed $value): mixed
	{
		return $value instanceof BackedEnum ? $value->value : $value;
	}

	/**
	 * A value written in the declaration: a scalar, an enum's case, null, or a list of them (`in`).
	 */
	private static function isWrittenValue(mixed $value): bool
	{
		if (\is_array($value)) {
			foreach ($value as $entry) {
				if (!self::isWrittenValue($entry) || \is_array($entry)) {
					return false;
				}
			}

			return true;
		}

		return null === $value || \is_scalar($value) || $value instanceof BackedEnum;
	}
}
