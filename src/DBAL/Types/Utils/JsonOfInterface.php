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

use JsonSerializable;

/**
 * Interface JsonOfInterface.
 *
 * Implement this interface on value-objects that are stored as JSON in the database
 * and need to be revived from their decoded representation on read.
 *
 * Contract:
 *  - {@see self::revive()} receives the decoded JSON payload `json_decode($json, true)` and
 *    must return an instance of the implementing class.
 *  - {@see JsonSerializable::jsonSerialize()} must produce a value that round-trips
 *    through `json_encode` -> `json_decode` -> `revive()` correctly.
 *
 * Usage with TypeJSON:
 * ```php
 * // schema (array syntax)
 * 'meta' => ['type' => 'json', 'json_of' => MyMeta::class]
 *
 * // fluent builder
 * $t->json('meta')->jsonOf(MyMeta::class);
 * ```
 *
 * Usage with TypeList list_of:
 * ```php
 * 'tags' => ['type' => 'list', 'list_of' => Tag::class]
 *
 * $t->list('tags')->listOf(Tag::class);
 * ```
 *
 * Compatibility with JsonPatch:
 *   {@see JsonPatch} calls {@see JsonSerializable::jsonSerialize()} via `set()`,
 *   which accepts `JsonSerializable` values. Instances of this interface are therefore
 *   valid `JsonPatch::set()` operands without any additional changes.
 */
interface JsonOfInterface extends JsonSerializable
{
	/**
	 * Revives an instance from a decoded JSON payload.
	 *
	 * @param mixed $payload the decoded JSON value `json_decode($json, true)`
	 *
	 * @return static
	 */
	public static function revive(mixed $payload): static;
}
