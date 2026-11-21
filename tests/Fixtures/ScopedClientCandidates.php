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

use ArrayObject;
use Gobl\ORM\Candidates\CandidatesContext;
use Gobl\ORM\Candidates\CandidatesProviderInterface;

/**
 * Narrows a relation's candidates to the client the caller's context names.
 *
 * Typed with the base context, since the generated one exists only once the ORM classes are: the
 * class it is handed is kept, for a test to check it is the generated one.
 */
final class ScopedClientCandidates implements CandidatesProviderInterface
{
	/** @var list<class-string> the classes of the contexts it was handed */
	private static array $handed = [];

	/**
	 * The classes of the contexts it was handed since {@see forget()}.
	 *
	 * @return list<class-string>
	 */
	public static function handed(): array
	{
		return self::$handed;
	}

	/**
	 * Forgets what it was handed.
	 */
	public static function forget(): void
	{
		self::$handed = [];
	}

	public function __invoke(CandidatesContext $context): void
	{
		self::$handed[] = $context::class;

		$wanted = $context->context();

		$context->selector()->where(['client_name', 'eq', $wanted instanceof ArrayObject ? $wanted['name'] : null]);
	}
}
