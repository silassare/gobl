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
 * A class that is not a candidates provider, named as one.
 */
final class NotCandidates
{
	public function __invoke(): void {}
}
