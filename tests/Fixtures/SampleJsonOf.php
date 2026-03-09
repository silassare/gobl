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

use Gobl\DBAL\Types\Utils\JsonOfInterface;

/**
 * Minimal fixture class implementing JsonOfInterface, used across type tests.
 *
 * @internal
 */
final class SampleJsonOf implements JsonOfInterface
{
	public function __construct(
		public readonly string $name,
		public readonly int $score = 0,
	) {}

	/**
	 * {@inheritDoc}
	 */
	public static function revive(mixed $payload): static
	{
		$data = \is_array($payload) ? $payload : [];

		return new self((string) ($data['name'] ?? ''), (int) ($data['score'] ?? 0));
	}

	/**
	 * {@inheritDoc}
	 */
	public function jsonSerialize(): mixed
	{
		return ['name' => $this->name, 'score' => $this->score];
	}
}
