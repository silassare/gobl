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

namespace Gobl\DBAL\Types\Interfaces;

use Gobl\DBAL\Interfaces\RDBMSInterface;

/**
 * Interface BaseTypeInterface.
 *
 * @template TUnsafe
 * @template TClean
 *
 * @extends TypeInterface<TUnsafe, TClean>
 */
interface BaseTypeInterface extends TypeInterface
{
	/**
	 * Whether {@see castExpressionForQuery()} should wrap the SQL placeholder for this column.
	 *
	 * When `true`, {@see TypeUtils::runCastExpressionForQuery()} will call
	 * {@see castExpressionForQuery()} to wrap the bound parameter placeholder
	 * in a SQL-level cast (e.g. `CAST(:p AS TYPE)`). No built-in type activates
	 * this by default; it exists as an extension point for custom type providers.
	 *
	 * @param RDBMSInterface $rdbms the RDBMS
	 *
	 * @return bool
	 */
	public function shouldCastExpressionForQuery(RDBMSInterface $rdbms): bool;

	/**
	 * Wraps a SQL placeholder expression in a DB-level cast for this column's type.
	 *
	 * Called only when {@see shouldCastExpressionForQuery()} returns `true`.
	 * Receives the placeholder string (e.g. `:param_key`) and must return
	 * a valid SQL expression (e.g. `CAST(:param_key AS UNSIGNED)`).
	 *
	 * @param string         $expression the SQL placeholder or expression to wrap
	 * @param RDBMSInterface $rdbms      the RDBMS
	 *
	 * @return string
	 */
	public function castExpressionForQuery(string $expression, RDBMSInterface $rdbms): string;
}
