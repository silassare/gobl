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

namespace Gobl\Tests\Fixtures;

/**
 * Enum SampleKind.
 *
 * A backed enum for the client generators: a column typed with it must be typed with the generated
 * enum on the client side too, not with a bare string.
 */
enum SampleKind: string
{
	case IMAGE = 'image';
	case VIDEO = 'video';
}
