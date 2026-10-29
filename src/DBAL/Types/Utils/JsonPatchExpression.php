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

use Gobl\DBAL\Interfaces\QueryGeneratorInterface;

/**
 * Class JsonPatchExpression.
 *
 * What {@see QueryGeneratorInterface::getJsonPatchUpdateExpression()} answers: the SET expression
 * that applies a patch's paths to the row the database holds, or the reason it cannot be expressed
 * that way.
 *
 * The reason is part of the answer rather than a silence, because the alternative -- writing the
 * whole document -- gives back the lost update the paths exist to prevent. A caller that falls back
 * has to be able to say so.
 */
final readonly class JsonPatchExpression
{
	private function __construct(
		public ?string $sql,
		public ?string $reason
	) {}

	/**
	 * The paths, as the SET expression that applies them.
	 */
	public static function paths(string $sql): self
	{
		return new self($sql, null);
	}

	/**
	 * No paths, and why: the caller writes the whole document, or refuses to.
	 *
	 * @param string $reason reads after "the patch cannot be sent as paths because ..."
	 */
	public static function notExpressible(string $reason): self
	{
		return new self(null, $reason);
	}

	/**
	 * Whether the patch is going out as paths.
	 *
	 * @psalm-assert-if-true !null $this->sql
	 * @psalm-assert-if-false !null $this->reason
	 */
	public function isExpressible(): bool
	{
		return null !== $this->sql;
	}
}
