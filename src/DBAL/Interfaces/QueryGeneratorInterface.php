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

namespace Gobl\DBAL\Interfaces;

use Gobl\DBAL\Column;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\Interfaces\FilterInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Types\Utils\JsonPath;

/**
 * Interface QueryGeneratorInterface.
 */
interface QueryGeneratorInterface
{
	/**
	 * QueryGeneratorInterface constructor.
	 *
	 * @param RDBMSInterface $db
	 * @param DbConfig       $config
	 */
	public function __construct(RDBMSInterface $db, DbConfig $config);

	/**
	 * Converts query object into the rdbms query language.
	 *
	 * @param QBInterface $qb
	 *
	 * @return string
	 */
	public function buildQuery(QBInterface $qb): string;

	/**
	 * Converts diff action object into the rdbms query language.
	 *
	 * @param DiffAction $action
	 *
	 * @return string
	 */
	public function buildDiffActionQuery(DiffAction $action): string;

	/**
	 * Builds database query.
	 *
	 * When namespace is not empty,
	 * only tables with the given namespace should be generated.
	 *
	 * @param null|string $namespace the table namespace to generate
	 *
	 * @return string
	 */
	public function buildDatabase(?string $namespace = null): string;

	/**
	 * Gets total row count query with a given select query object.
	 *
	 * @param QBSelect $qb
	 *
	 * @return string
	 */
	public function buildTotalRowCountQuery(QBSelect $qb): string;

	/**
	 * Returns the SQL expression that extracts a scalar value from a JSON column at the given path.
	 *
	 * @param JsonPath $json_path The JSON path object
	 *
	 * @return string The dialect-specific JSON-path extraction expression
	 */
	public function getJsonPathExtractionExpression(JsonPath $json_path): string;

	/**
	 * Returns the dialect-specific SQL expression for JSON containment.
	 *
	 * @param string $left  The left operand (JSON column expression or path expression)
	 * @param string $right The right operand (already-quoted JSON value or bound placeholder)
	 *
	 * @return string The dialect-specific JSON containment SQL expression
	 */
	public function getJsonContainsExpression(string $left, string $right): string;

	/**
	 * Returns the dialect-specific SQL expression that checks for top-level key existence.
	 *
	 * @param string $col_sql_expression The already-qualified SQL column reference
	 * @param string $key_expression     The bound-parameter placeholder for the key string
	 *
	 * @return string The dialect-specific key-existence SQL expression
	 */
	public function getJsonHasKeyExpression(string $col_sql_expression, string $key_expression): string;

	/**
	 * Converts filters to sql expression.
	 *
	 * @param FilterInterface|Filters $filter
	 *
	 * @return string
	 */
	public function filterToExpression(FilterInterface|Filters $filter): string;

	/**
	 * Checks if two given columns has the same type definition.
	 *
	 * @param Column $a
	 * @param Column $b
	 *
	 * @return bool
	 */
	public function hasSameColumnTypeDefinition(Column $a, Column $b): bool;

	/**
	 * Wraps a database definition query.
	 *
	 * @param string $query
	 *
	 * @return string
	 */
	public function wrapDatabaseDefinitionQuery(string $query): string;

	/**
	 * Wraps an identifier (table name, column name, etc.) in the appropriate
	 * quote characters for this RDBMS dialect.
	 *
	 * @param string $name The raw identifier to quote
	 *
	 * @return string
	 */
	public function quoteIdentifier(string $name): string;

	/**
	 * Quotes a scalar value as a SQL string literal using this RDBMS dialect's
	 * escaping rules.
	 *
	 * Unlike `PDO::quote()`, this method requires no live database connection
	 * and is safe to call during query building or schema generation.
	 *
	 * Implementations must escape any characters that would break the SQL
	 * string literal (e.g. single-quotes) and wrap the result in the
	 * appropriate delimiters.
	 *
	 * @param string $value raw string to quote
	 *
	 * @return string e.g. `'O''Brien'`
	 */
	public function quoteLiteral(string $value): string;
}
