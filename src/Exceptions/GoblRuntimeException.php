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

namespace Gobl\Exceptions;

use PHPUtils\Interfaces\RichExceptionInterface;
use PHPUtils\Traits\RichExceptionTrait;
use RuntimeException;

/**
 * Class GoblRuntimeException.
 */
class GoblRuntimeException extends RuntimeException implements RichExceptionInterface
{
	use RichExceptionTrait;
}
