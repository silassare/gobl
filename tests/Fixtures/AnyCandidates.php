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

use Gobl\ORM\Candidates\CandidatesContext;
use Gobl\ORM\Candidates\CandidatesProviderInterface;

/**
 * A candidates provider that narrows nothing, for a declaration that only needs one to exist.
 */
final class AnyCandidates implements CandidatesProviderInterface
{
	public function __invoke(CandidatesContext $context): void
	{
		// Narrows by nothing: every row of the target stays a candidate.
		$context->selector()->where();
	}
}
