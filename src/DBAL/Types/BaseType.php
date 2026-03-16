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

namespace Gobl\DBAL\Types;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Override;

/**
 * Class BaseType.
 *
 * @template TUnsafe
 * @template TClean
 *
 * @implements BaseTypeInterface<TUnsafe, TClean>
 *
 * @extends Type<TUnsafe, TClean>
 */
abstract class BaseType extends Type implements BaseTypeInterface
{
	#[Override]
	public function shouldCastExpressionForQuery(RDBMSInterface $rdbms): bool
	{
		return false;
	}

	#[Override]
	public function castExpressionForQuery(string $expression, RDBMSInterface $rdbms): string
	{
		return $expression;
	}
}
