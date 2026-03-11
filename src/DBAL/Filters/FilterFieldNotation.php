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

namespace Gobl\DBAL\Filters;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use InvalidArgumentException;
use PHPUtils\DotPath;

/**
 * Utility class for operand notation in filters, specifically for handling column
 * references with JSON path segments in the Gobl JSON filters.
 *
 * Operand string syntax:
 *  - `table.column#json_path`
 *  - `column#json_path` here column may be a custom field name not real column name
 *    and later resolved via {@see FiltersScopeInterface::tryResolveFieldNotation()} to the real column name
 *
 * The path portion after `#` is parsed into segments using JS-like notation:
 *
 * Rules:
 *   - Plain segment: `foo` - identifier chars (no `.`, `[`, `'`, `"`).
 *   - Bracket-integer segment: `[0]` - non-negative integer index.
 *   - Bracket-quoted segment: `['...']` or `["..."]` - any key, with `\'` or `\"` for escaping.
 *   - Segments are separated by `.` (optional after a `]`).
 *   - Empty segments (from consecutive dots) are not allowed and will throw.
 *
 * Examples:
 *   `table.column#foo.bar`                -> `['foo', 'bar']`
 *   `table.column#foo[0].bar`             -> `['foo', '0', 'bar']`
 *   `table.column#foo['bar.baz'].qux`     -> `['foo', 'bar.baz', 'qux']`
 *   `table.column#['it\'s'].key`          -> `["it's", 'key']`
 *   `table.column#['space key'].sub`      -> `['space key', 'sub']`
 *   `table.column#foo["bar"]["baz"]`      -> `['foo', 'bar', 'baz']`
 */
final class FilterFieldNotation
{
	/**
	 * FilterFieldNotation constructor.
	 *
	 * @param string                     $field          The field name
	 * @param null|string                $table_or_alias Optional table name or alias for disambiguation in filters
	 * @param null|string                $column_name    Optional real column name if different from field name (used when field is a custom name resolved via scope)
	 * @param array<int,string>          $path_segments  The path segments as an array of strings (e.g. `['foo', 'bar', 'baz.qux']`)
	 * @param null|QBInterface           $qb             Optional query builder for alias resolution in scope checks
	 * @param null|FiltersScopeInterface $scope          Optional filters scope for resolving field names
	 */
	public function __construct(
		private string $field,
		private ?string $table_or_alias = null,
		private ?string $column_name = null,
		private array $path_segments = [],
		private ?QBInterface $qb = null,
		private ?FiltersScopeInterface $scope = null,
	) {
		if ('' === $this->field) {
			throw new InvalidArgumentException('Field name cannot be empty');
		}

		if ('' === $this->table_or_alias) {
			$this->table_or_alias = null;
		}

		if ('' === $this->column_name) {
			$this->column_name = null;
		}

		$this->resolve();
	}

	/**
	 * Converts back to its string representation.
	 */
	public function __toString(): string
	{
		$head = $this->column_name ?? $this->field;

		if ($this->table_or_alias) {
			$head = $this->table_or_alias . '.' . $head;
		}

		$path = $this->getPathSegmentsAsString();

		if ('' === $path) {
			return $head;
		}

		return $head . '#' . $path;
	}

	/**
	 * Factory method to create an instance from a string notation.
	 *
	 * @return static
	 */
	public static function fromString(string $notation, ?QBInterface $qb = null, ?FiltersScopeInterface $scope = null)
	{
		$field          = \trim($notation);
		$hashPos        = \strpos($field, '#');
		$path           = null;
		$segments       = [];

		if (false === $hashPos) {
			// just a string without segments
			$head = $notation;
		} else {
			$head = \substr($notation, 0, $hashPos);
			$path = \substr($notation, $hashPos + 1);
		}

		$head_parts = \explode('.', $head, 2);

		if (2 === \count($head_parts)) {
			$table_or_alias  = $head_parts[0];
			$field           = $head_parts[1];
		} else {
			$table_or_alias  = null;
			$field           = $head_parts[0];
		}

		if (null !== $path) {
			$segments = DotPath::parse($path)->getSegments();
		}

		return new self($field, $table_or_alias, null, $segments, $qb, $scope);
	}

	/**
	 * Parse a JSON path string into an array of segments using JS-like notation.
	 *
	 * @deprecated use {@see DotPath::parse()} instead
	 *
	 * @return array<int,string> The parsed path segments
	 *
	 * @throws InvalidArgumentException if the path is empty, has empty/invalid segments, or is malformed
	 */
	public static function parsePath(string $path): array
	{
		return DotPath::parse($path)->getSegments();
	}

	/**
	 * Get the original field name as provided in the filter operand string (before resolution).
	 */
	public function getField(): string
	{
		return $this->field;
	}

	/**
	 * Get the resolved column name for this field, or null if it cannot be resolved
	 * (e.g. if the field is a custom name that couldn't be resolved to a real column).
	 *
	 * @return null|string
	 */
	public function getColumnName(): ?string
	{
		return $this->column_name;
	}

	/**
	 * Mark the field as resolved with the resolved table or alias and column name for this field notation.
	 *
	 * A field is not resolved until we have both the table/alias and the real column name,
	 * which are needed for generating the correct SQL.
	 *
	 * This method is typically called after resolving the field name via
	 * the filters scope {@see FiltersScopeInterface::tryResolveFieldNotation()} and
	 * determining the correct table/alias from the query builder context.
	 *
	 * @param string            $table_or_alias The resolved table name or alias
	 * @param string            $column_name    The resolved real column name corresponding to the field
	 * @param array<int,string> $path_segments  The resolved path segments if this field includes a JSON path (useful for shortcut field: stats => tabla.data#stats)
	 *
	 * @return static
	 *
	 * @throws DBALRuntimeException if the table/alias cannot be resolved in the provided query builder context,
	 *                              or if the column does not exist in the resolved table
	 */
	public function markAsResolved(string $table_or_alias, string $column_name, array $path_segments = []): static
	{
		$this->table_or_alias = $table_or_alias;
		$this->column_name    = $column_name;
		$this->path_segments  = $path_segments;

		$this->resolve();

		return $this;
	}

	/**
	 * Checks if the field notation has been resolved to a specific table/alias and column name.
	 */
	public function isResolved(): bool
	{
		return null !== $this->table_or_alias && null !== $this->column_name;
	}

	/**
	 * Get the resolved column.
	 *
	 * @throws DBALRuntimeException when not resolved
	 */
	public function getResolvedColumnOrFail(): FilterResolvedColumn
	{
		if (null === $this->table_or_alias || null === $this->column_name) {
			throw new DBALRuntimeException('Field notation is not resolved to a specific column.');
		}

		return new FilterResolvedColumn($this->table_or_alias, $this->column_name, $this, $this->qb);
	}

	/**
	 * Checks if this field notation includes any JSON path segments.
	 */
	public function hasPathSegments(): bool
	{
		return !empty($this->path_segments);
	}

	/**
	 * Get the table or alias name if set, otherwise null.
	 */
	public function getTableOrAlias(): ?string
	{
		return $this->table_or_alias;
	}

	/**
	 * Get the path segments as an array of strings.
	 *
	 * Each segment is a key in the JSON structure, in order from top-level to leaf.
	 *
	 * @return array<int,string> The path segments (e.g. `['foo', 'bar', 'baz.qux']` for `table.column#foo.bar['baz.qux']`)
	 */
	public function getPathSegments(): array
	{
		return $this->path_segments;
	}

	/**
	 * Serializes path_segments back to a path string using JS-like bracket notation.
	 *
	 * Delegates to {@see DotPath::__toString()}.
	 *
	 * @return string The path string
	 */
	public function getPathSegmentsAsString(): string
	{
		return (string) new DotPath($this->path_segments);
	}

	/**
	 * Resolves the column by using the provided query builder and filters scope.
	 *
	 * Resolution steps:
	 * 1. If a table or alias is provided and we have a query builder, attempt to resolve and check if the column exists in that table.
	 *   - resolve the table
	 * 	 - get the column from the resolved table using the field name (or column name if already set)
	 * 2. If resolution via query builder fails (we don't have a valid column) and we have a filters scope, attempt to resolve the column
	 *    via the scope's {@see FiltersScopeInterface::tryResolveFieldNotation()} method.
	 */
	private function resolve(): void
	{
		if ($this->table_or_alias && $this->qb) {
			$table = $this->qb->resolveTable($this->table_or_alias);

			if ($table) {
				$name  = $this->column_name ?? $this->field;
				$found = $table->getColumn($name);

				if ($found) {
					// we were able to resolve the table/alias and find the column in that table,
					// we can mark this field as resolved by setting the real column name
					$this->column_name = $found->getFullName();

					return;
				}
			}
		}

		if ($this->scope) {
			$name = $this->column_name ?? $this->field;

			$tmp = clone $this;
			// we do not provide scope to prevent infinite loop if tryResolveFieldNotation
			// try to mark as resolved with invalid table/alias or column name
			$tmp->scope = null;

			try {
				$this->scope->tryResolveFieldNotation($tmp, $this->qb);

				if (!$tmp->isResolved()) {
					throw new DBALRuntimeException(\sprintf(
						"Attempt to resolve field via scope failed for field '%s'. "
							. 'Ensure that the field can be resolved to a real column in the scope.',
						$name
					));
				}
			} catch (DBALRuntimeException $e) {
				throw (new DBALRuntimeException(\sprintf(
					"Failed to resolve field '%s' via scope: %s",
					$name,
					$e->getMessage()
				), null, $e))->suspectCallable([$this->scope, 'tryResolveFieldNotation']);
			}

			$this->table_or_alias = $tmp->table_or_alias;
			$this->column_name    = $tmp->column_name;
			$this->path_segments  = $tmp->path_segments;
		}
	}
}
