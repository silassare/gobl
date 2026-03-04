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

namespace Gobl\DBAL\Filters\Operands;

use BackedEnum;
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Types\TypeJSON;
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
	 * Detected table and column names (if any).
	 *
	 * @var null|array{table: string, column: string}
	 */
	protected ?array $detected_table_and_column = null;

	/**
	 * FilterOperand constructor.
	 *
	 * @param null|array|BackedEnum|bool|Column|float|int|QBExpression|QBInterface|string $user_defined The operand as provided by the user
	 */
	public function __construct(
		protected array|BackedEnum|bool|Column|float|int|QBExpression|QBInterface|string|null $user_defined,
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
		unset($this->user_defined, $this->normalized, $this->detected_table_and_column);
	}

	/**
	 * Get user defined operand value.
	 *
	 * @return null|array|BackedEnum|bool|Column|float|int|QBExpression|QBInterface|string
	 */
	public function getValueAsDefined(): array|BackedEnum|bool|Column|float|int|QBExpression|QBInterface|string|null
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
	 * @return $this
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
	 * Checks if a table and column were detected for this operand.
	 */
	public function hasDetectedTableAndColumn(): bool
	{
		return null !== $this->detected_table_and_column;
	}

	/**
	 * Get detected table and column names (if any).
	 *
	 * @return null|array{table: string, column: string}
	 */
	public function getDetectedTableAndColumn(): ?array
	{
		return $this->detected_table_and_column;
	}

	/**
	 * Set detected table and column names.
	 */
	public function setDetectedTableAndColumn(string $table_full_name, string $column_full_name): static
	{
		$this->detected_table_and_column = [
			'table'  => $table_full_name,
			'column' => $column_full_name,
		];

		return $this;
	}

	/**
	 * Returns whether this operand can be safely bound as a parameter.
	 *
	 * An operand can be bound if and only if:
	 * - it is not explicitly marked as non-bindable
	 * - it does not have a detected table and column
	 * - it is not already a binding (IMPORTANT to recheck: because it can be the case)
	 *
	 * @return bool
	 */
	public function canBeSafelyBound(): bool
	{
		return $this->can_be_bound && !$this->hasDetectedTableAndColumn() && !$this->isABinding();
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
	 * - `QBExpression` → cast to string verbatim.
	 * - `BackedEnum` → unwrapped to its scalar `value`.
	 * - `Column` instance → resolved to its FQN using the current QB alias map.
	 * - `table.column` string notation → resolved to the fully qualified reference
	 *   (e.g. `users.name` → `u.gobl_name`) when the table is registered in the QB.
	 * - `table.column.json.path` string (3+ dot segments) → resolved to the
	 *   dialect-specific JSON-path extraction expression (requires `native_json` enabled
	 *   on the column's type).
	 */
	protected function normalizeOperand(): void
	{
		$uv = $this->user_defined;

		/** @var null|string $found_table */
		$found_table = null;

		/** @var null|string $found_column */
		$found_column = null;

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

			$found_table  = $table->getFullName();
			$found_column = $column->getFullName();
			$uv           = $this->qb->fullyQualifiedName($found_table, $found_column);
		} elseif ($uv instanceof BackedEnum) {
			$uv = $uv->value;
		} elseif (\is_string($uv) && \strlen($uv) > 2 && ':' !== $uv[0] && '(' !== $uv[0]) {
			if ($this->scope && !\str_contains($uv, '.')) {
				// try resolve as column in scope when no dot notation is used,
				// this is for column name only filters, `name` instead of `users.name`
				// e.g. [name, 'eq', 'foo'] instead of [name, 'eq', 'foo'] or [u.name, 'eq', 'foo']
				// this also allow using column aliases in filters without table prefix
				$uv = $this->scope->tryGetColumnFQName($uv) ?? $uv;
			}

			$parts = \explode('.', $uv);
			$len   = \count($parts);

			if (2 === $len && !empty($parts[0]) && !empty($parts[1])) {
				try {
					$table_name = $this->qb->resolveTable($parts[0])
						?->getFullName();

					if ($table_name) {
						$uv        = $this->qb->fullyQualifiedName($parts[0], $parts[1]);
						$fqn_parts = \explode('.', $uv);

						// we found a table and column
						if (2 === \count($fqn_parts)) {
							$found_table  = $table_name;
							$found_column = $fqn_parts[1];
						}
					}
				} catch (Throwable) {
				}
			} elseif ($len >= 3 && !empty($parts[0]) && !empty($parts[1])) {
				// Detect table.column.json_path notation for JSON path filtering.

				$rethrow = false;

				try {
					$table = $this->qb->resolveTable($parts[0]);

					if ($table) {
						$col_name  = $parts[1];
						$json_path = \array_slice($parts, 2);
						$col_obj   = $table->getColumn($col_name);

						if ($col_obj) {
							$base_type = $col_obj->getType()->getBaseType();

							if (!$base_type instanceof TypeJSON) {
								$rethrow = true;

								throw new DBALRuntimeException(\sprintf(
									'JSON path filter "%s" is only allowed on JSON columns; column "%s" has base type "%s".',
									$uv,
									$col_name,
									$base_type->getName()
								));
							}

							if (!$base_type->isNativeJson()) {
								$rethrow = true;

								throw new DBALRuntimeException(\sprintf(
									'JSON path filter "%s" requires native_json to be enabled on column "%s".',
									$uv,
									$col_name
								));
							}

							$col_fqn = $this->qb->fullyQualifiedName($parts[0], $col_obj->getFullName());
							$gen     = $this->qb->getRDBMS()->getGenerator();

							$uv           = $gen->getJsonPathExpression($col_fqn, $json_path);
							$found_table  = $table->getFullName();
							$found_column = $col_obj->getFullName();
						}
					}
				} catch (Throwable $t) {
					if ($rethrow) {
						throw $t;
					}
				}
			}
		}

		if ($found_table && $found_column) {
			$this->can_be_bound              = false;
			$this->detected_table_and_column =  ['table' => $found_table, 'column' => $found_column];
		} else {
			$this->detected_table_and_column =   null;
		}

		$this->normalized = $uv;
	}
}
