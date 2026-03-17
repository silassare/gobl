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

namespace Gobl\DBAL\Filters\Operands;

use BackedEnum;
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\FilterFieldNotation;
use Gobl\DBAL\Filters\FilterResolvedColumn;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\Utils\JsonPath;
use Throwable;

/**
 * Class FilterOperand.
 */
abstract class FilterOperand
{
	/**
	 * The concrete operand value after normalization (e.g. QBExpression cast to string, BackedEnum unwrapped to scalar value, etc.).
	 */
	protected array|bool|float|int|string|null $normalized = null;

	/**
	 * Whether this operand can be safely bound as a parameter.
	 *
	 * Set to false for operands that should not be bound and must be injected verbatim into the query.
	 */
	protected bool $can_be_bound = true;

	/**
	 * @var null|FilterResolvedColumn The resolved column for this operand
	 */
	protected ?FilterResolvedColumn $resolved_column = null;

	/**
	 * FilterOperand constructor.
	 *
	 * @param null|array|BackedEnum|bool|Column|FilterFieldNotation|float|int|QBExpression|QBInterface|string $user_defined The operand as provided by the user
	 */
	public function __construct(
		protected array|BackedEnum|bool|Column|FilterFieldNotation|float|int|QBExpression|QBInterface|string|null $user_defined,
		protected QBInterface $qb,
		protected ?FiltersScopeInterface $scope = null
	) {
		$this->normalizeOperand();
	}

	/**
	 * FilterOperand destructor.
	 */
	public function __destruct()
	{
		unset($this->user_defined, $this->normalized, $this->resolved_column);
	}

	/**
	 * Get user defined operand value.
	 *
	 * @return null|array|BackedEnum|bool|Column|FilterFieldNotation|float|int|QBExpression|QBInterface|string
	 */
	public function getValueAsDefined(): array|BackedEnum|bool|Column|FilterFieldNotation|float|int|QBExpression|QBInterface|string|null
	{
		return $this->user_defined;
	}

	/**
	 * Get filter operand value after normalization (ready for binding).
	 *
	 * @return null|array|bool|float|int|string
	 */
	public function getValueNormalized(): array|bool|float|int|string|null
	{
		return $this->normalized;
	}

	/**
	 * Sets the operand normalized value.
	 *
	 * @param null|array|bool|float|int|string $normalized the normalized operand value
	 *
	 * @return static
	 */
	public function setValueNormalized(array|bool|float|int|string|null $normalized): static
	{
		$this->normalized = $normalized;

		return $this;
	}

	/**
	 * Get filter operand value for query.
	 *
	 * @return null|string
	 */
	public function getValueForQuery(): ?string
	{
		$nz = $this->normalized;

		if (\is_array($nz)) {
			return '(' . \implode(', ', $nz) . ')';
		}

		return null === $nz ? null : (string) $nz;
	}

	/**
	 * Checks if a field notation was detected for this operand.
	 */
	public function hasResolvedColumn(): bool
	{
		return null !== $this->resolved_column;
	}

	/**
	 * Get resolved column (if any).
	 *
	 * @return null|FilterResolvedColumn
	 */
	public function getResolvedColumn(): ?FilterResolvedColumn
	{
		return $this->resolved_column;
	}

	/**
	 * Get resolved column (if any).
	 *
	 * @return FilterResolvedColumn
	 */
	public function getResolvedColumnOrFail(): FilterResolvedColumn
	{
		if (null === $this->resolved_column) {
			throw new DBALRuntimeException('No resolved column for this operand.', [
				'operand_value' => $this->getValueAsDefined(),
			]);
		}

		return $this->resolved_column;
	}

	/**
	 * Returns whether this operand can be safely bound as a parameter.
	 *
	 * An operand can be bound if and only if:
	 * - it is not explicitly marked as non-bindable
	 * - it does not have a resolved column
	 * - it is not already a binding (IMPORTANT to recheck: because it can be the case)
	 *
	 * @return bool
	 */
	public function canBeSafelyBound(): bool
	{
		return $this->can_be_bound && !$this->hasResolvedColumn() && !$this->isABinding();
	}

	/**
	 * Whether this operand should attempt to resolve a plain string as a field notation.
	 *
	 * Returns `false` in the base class (safe for right operands carrying user data).
	 * Overridden to `true` in {@see FilterLeftOperand}.
	 */
	protected function shouldResolveFieldNotation(): bool
	{
		return false;
	}

	/**
	 * Returns whether the right operand is already a bound named parameter.
	 *
	 * A value is considered a binding when it is a string starting with `:` and
	 * the remainder is a registered parameter name in the current QB.
	 * Used to avoid double-binding values that were already bound by `bindArrayForInList`.
	 *
	 * @return bool
	 */
	protected function isABinding(): bool
	{
		$v = $this->normalized;

		return \is_string($v) && $v && ':' === $v[0] && $this->qb->isBoundParam(\substr($v, 1));
	}

	/**
	 * Resolves and normalizes a filter operand.
	 *
	 * - `QBExpression` -> cast to string verbatim.
	 * - `BackedEnum` -> unwrapped to its scalar `value`.
	 * - `Column` instance -> resolved to its FQN using the current QB alias map.
	 * - `FilterFieldNotation` instance -> always resolved to its column reference (or JSON extraction
	 *   expression) regardless of operand position; safe because the caller explicitly constructed it.
	 * - `table.column` string notation -> resolved to the fully qualified reference
	 *   (e.g. `users.name` -> `u.gobl_name`) when the table is registered in the QB.
	 *   Only auto-resolved for left operands (see `shouldResolveFieldNotation()`); right operands
	 *   carrying user input are never auto-resolved to prevent IDOR-style column probing.
	 * - `table.column#json_path` | `column#json_path` -> resolved to the dialect-specific JSON-path extraction
	 *   expression (requires `native_json` enabled on the column's type).
	 */
	protected function normalizeOperand(): void
	{
		$uv = $this->user_defined;

		/** @var null|FilterFieldNotation $dfn */
		$dfn = null;

		/** @var null|FilterResolvedColumn $resolved */
		$resolved = null;

		if ($uv instanceof QBExpression) {
			$this->can_be_bound = false;

			$uv = (string) $uv;
		} elseif ($uv instanceof QBInterface) {
			$this->can_be_bound = false;

			$this->qb->bindMergeFrom($uv);
			$uv = '(' . $uv->getSqlQuery() . ')';
		} elseif ($uv instanceof Column) {
			$this->can_be_bound = false;

			$column = $uv;
			$table  = $column->getTable();

			if (null === $table) {
				throw new DBALRuntimeException(
					\sprintf('attempt to use unlocked column "%s" in a query.', $column->getFullName())
				);
			}

			$uv  = $this->qb->fullyQualifiedName($table->getFullName(), $column->getFullName());

			try {
				$dfn      = FilterFieldNotation::fromString($uv, $this->qb, $this->scope);
				$resolved = $dfn->getResolvedColumnOrFail();
			} catch (DBALRuntimeException $e) {
				throw new DBALRuntimeException(
					\sprintf('attempt to use a column "%s" out of context in a filter.', $column->getFullName()),
					[
						'_column' => $column->getFullName(),
						'_table'  => $table->getFullName(),
					],
					$e
				);
			}
		} elseif ($uv instanceof FilterFieldNotation) {
			// Explicit FilterFieldNotation: the caller deliberately constructed this notation object,
			// so resolving it is safe regardless of which side (left or right) it is used on.
			// Unlike plain strings (which may carry user input on the right side), this is always
			// developer-controlled and not subject to the shouldResolveFieldNotation() guard.
			$this->can_be_bound = false;
			$dfn                = $uv;

			if (!$dfn->isResolved()) {
				// Notation was created without QB/scope context; resolve it now.
				$dfn = FilterFieldNotation::fromString((string) $dfn, $this->qb, $this->scope);
			}

			[$uv, $resolved] = $this->notationToSqlExpr($dfn);
		} elseif ($uv instanceof BackedEnum) {
			$uv = $uv->value;
		} elseif (\is_string($uv) && \strlen($uv) > 2 && ':' !== $uv[0] && '(' !== $uv[0]) {
			if ($this->shouldResolveFieldNotation()) {
				$rethrow = false;

				try {
					// if invalid it will fail with an exception which we catch and ignore to keep the original string as-is
					$dfn = FilterFieldNotation::fromString($uv, $this->qb, $this->scope);

					if ($dfn->isResolved()) {
						$rethrow         = true;
						[$uv, $resolved] = $this->notationToSqlExpr($dfn);
					}
				} catch (Throwable $t) {
					if ($rethrow) {
						throw $t;
					}
				}
			}
		}

		if ($resolved) {
			$this->can_be_bound    = false;
			$this->resolved_column = $resolved;
		} else {
			$this->resolved_column = null;
		}

		$this->normalized = $uv;
	}

	/**
	 * Converts a resolved FilterFieldNotation to its SQL expression string and resolved-column pair.
	 *
	 * - Plain column reference: returns the fully qualified name (e.g. `_a_.col_name`).
	 * - JSON path notation: returns the dialect-specific extraction expression
	 *   (e.g. `JSON_UNQUOTE(JSON_EXTRACT(...))`).
	 *
	 * @return array{0: string, 1: FilterResolvedColumn}
	 *
	 * @throws DBALRuntimeException when the JSON path is used on a non-JSON or non-native-JSON column
	 */
	private function notationToSqlExpr(FilterFieldNotation $dfn): array
	{
		$resolved = $dfn->getResolvedColumnOrFail();

		if ($dfn->hasPathSegments()) {
			$tbl       = $this->qb->resolveTable($resolved->getTableOrAlias());
			$col       = $tbl->getColumnOrFail($resolved->getColumnName());
			$base_type = $col->getType()->getBaseType();

			if (!$base_type instanceof TypeJSON) {
				throw new DBALRuntimeException(\sprintf(
					'JSON path filter "%s" is only allowed on JSON columns; column "%s" has base type "%s".',
					(string) $dfn,
					$col->getFullName(),
					$base_type->getName()
				));
			}

			if (!$base_type->isNativeJson()) {
				throw new DBALRuntimeException(\sprintf(
					'JSON path filter "%s" requires native_json to be enabled on column "%s".',
					(string) $dfn,
					$col->getFullName()
				));
			}

			$rdbms = $this->qb->getRDBMS();
			$gen   = $rdbms->getGenerator();
			$sql   = $gen->getJsonPathExtractionExpression(JsonPath::fromFieldNotation($dfn));
		} else {
			// Plain column reference: use the alias (e.g. `_a_`) so fullyQualifiedName
			// recognizes it as a declared QB alias rather than a raw table name.
			$sql = $this->qb->fullyQualifiedName(
				$resolved->getTableOrAlias(),
				$resolved->getColumnName()
			);
		}

		return [$sql, $resolved];
	}
}
