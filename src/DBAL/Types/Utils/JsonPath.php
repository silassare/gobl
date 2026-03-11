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

namespace Gobl\DBAL\Types\Utils;

use Gobl\DBAL\Filters\FilterFieldNotation;
use InvalidArgumentException;

/**
 * Utility class for JSON path string handling.
 *
 * The difference between this and {@see FilterFieldNotation} is that this class
 * is specifically for representing JSON paths and require a column and a JSON path segments.
 */
final class JsonPath
{
	/**
	 * JsonPath constructor.
	 *
	 * @param string            $column_name    The column name
	 * @param array<int,string> $path_segments  The path segments as an array of strings (e.g. `['foo', 'bar', 'baz.qux']`)
	 * @param null|string       $table_or_alias Optional table name or alias for disambiguation in filters
	 */
	public function __construct(
		private string $column_name,
		private array $path_segments,
		private ?string $table_or_alias = null
	) {
		if ('' === $column_name) {
			throw new InvalidArgumentException('Column name cannot be empty');
		}

		if (empty($path_segments)) {
			throw new InvalidArgumentException('Path segments cannot be empty');
		}

		if ('' === $table_or_alias) {
			throw new InvalidArgumentException('Table name cannot be empty');
		}
	}

	/**
	 * Convert the JsonPath to a string in the `table.column#json.path` notation.
	 */
	public function __toString(): string
	{
		$fn = new FilterFieldNotation(
			$this->column_name,
			$this->table_or_alias,
			$this->column_name,
			$this->path_segments
		);

		return (string) $fn;
	}

	/**
	 * Get the table name or alias, or null if not set.
	 *
	 * @return null|string
	 */
	public function getTableOrAlias(): ?string
	{
		return $this->table_or_alias;
	}

	/**
	 * Get the column name.
	 */
	public function getColumnName(): string
	{
		return $this->column_name;
	}

	/**
	 * Get the path segments as an array of strings.
	 *
	 * @return array<int, string>
	 */
	public function getPathSegments(): array
	{
		return $this->path_segments;
	}

	/**
	 * Create an instance from {@see FilterFieldNotation}.
	 */
	public static function fromFieldNotation(FilterFieldNotation $fn): static
	{
		$name = $fn->getColumnName() ?? $fn->getField();

		return new self($name, $fn->getPathSegments(), $fn->getTableOrAlias());
	}

	/**
	 * Create an instance from a string representation of a JSON path.
	 */
	public static function fromString(string $json_path): static
	{
		$fn = FilterFieldNotation::fromString($json_path);

		return self::fromFieldNotation($fn);
	}
}
