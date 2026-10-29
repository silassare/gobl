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

/**
 * Enum JsonContainerKind.
 *
 * What a JSON patch path needs a container to be for its next segment to address anything: an
 * object when that segment names a member, an array when it is a position.
 *
 * The engines do not coerce one into the other and do not complain either -- a path asking for a
 * member of an array writes nothing at all -- so a patch says which it means and the container is
 * made that way before it is written into.
 */
enum JsonContainerKind: string
{
	case ARRAY  = 'array';
	case OBJECT = 'object';

	/**
	 * The kind a container has to be to hold the given path segment.
	 */
	public static function of(int|string $segment): self
	{
		return \is_int($segment) ? self::ARRAY : self::OBJECT;
	}

	/**
	 * The empty container of this kind, as a JSON document.
	 */
	public function emptyJson(): string
	{
		return self::ARRAY === $this ? '[]' : '{}';
	}
}
