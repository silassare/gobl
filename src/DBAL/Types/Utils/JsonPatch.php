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

namespace Gobl\DBAL\Types\Utils;

use Gobl\DBAL\Filters\FilterFieldNotation;
use JsonSerializable;

/**
 * Class JsonPatch.
 *
 * A fluent, mutable value object that wraps a PHP array and exposes
 * set/remove operations addressed by dot-notation paths.
 *
 * Intended use: modify a JSON / Map / List column value before assigning it back.
 *
 * ```php
 * // Obtain a patch pre-seeded with the current column value:
 * $patch = $entity->patchData();
 *
 * $patch->set('user.name', 'Alice')
 *       ->set('config.theme', 'dark')
 *       ->remove('temp');
 *
 * // Assign back - all of the following forms are equivalent:
 * $entity->data   = $patch;
 * $entity->setData($patch);
 * $entity->hydrate(['data' => $patch]);
 *
 * $entity->save();
 * ```
 *
 * Or use the generated single-line shortcuts:
 *
 * ```php
 * $entity->setDataKey('user.name', 'Alice')->save();
 * $entity->removeDataKey('temp')->save();
 * ```
 *
 * Path syntax:
 *   - Segments are separated by `'.'` (e.g. `'user.profile.name'`).
 *   - Numeric segments address indexed array entries (e.g. `'tags.0'`).
 *   - Intermediate arrays are created automatically on `set()`.
 *   - An intermediate key that exists but is not an array is overwritten by `set()`.
 *   - `remove()` on a non-existent path is a silent no-op.
 *
 * > **Note:** JsonPatch operates purely in PHP - no SQL-level `JSON_SET` / `JSON_REMOVE`
 * > expressions are generated. The full JSON document is sent to the database on every save,
 * > which is adequate for small-to-medium payloads (up to a few KB). This is safe and
 * > efficient for the typical pattern of loading an entity, patching it, then saving it.
 * > If you need atomic concurrent updates on individual JSON keys (to prevent a lost-update
 * > when two transactions modify different keys of the same column), SQL-level path updates
 * > can be added in a future iteration.
 */
final class JsonPatch
{
	private array $data;

	/**
	 * JsonPatch constructor.
	 *
	 * @param array|Map $initial The current data to base the patch on.
	 *                           Typically obtained from the entity's JSON column getter.
	 */
	public function __construct(array|Map $initial = [])
	{
		$this->data = $initial instanceof Map ? $initial->toArray() : $initial;
	}

	/**
	 * Sets a value at the given dot-notation path.
	 *
	 * Intermediate objects/arrays are created as needed.
	 * If an intermediate key exists but is not an array it is overwritten.
	 *
	 * @param string                                       $path  dot-notation path (e.g. `'user.name'`, `'tags.0'`)
	 * @param null|array|float|int|JsonSerializable|string $value the value to store
	 *
	 * @return $this
	 */
	public function set(string $path, array|float|int|JsonSerializable|string|null $value): static
	{
		$keys = self::parsePath($path);
		self::doSet($this->data, $keys, $value);

		return $this;
	}

	/**
	 * Removes the key at the given dot-notation path.
	 *
	 * Silently does nothing when the path or any intermediate key does not exist.
	 *
	 * @param string $path dot-notation path (e.g. `'temp'`, `'meta.cache_key'`)
	 *
	 * @return $this
	 */
	public function remove(string $path): static
	{
		$keys = self::parsePath($path);
		self::doRemove($this->data, $keys);

		return $this;
	}

	/**
	 * Returns the patched data as a plain PHP array.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return $this->data;
	}

	/**
	 * Returns the patched data as a {@see Map} instance.
	 *
	 * @return Map
	 */
	public function toMap(): Map
	{
		return new Map($this->data);
	}

	/**
	 * Parses a path string into an array of segment strings.
	 *
	 * Delegates to {@see FilterFieldNotation::parsePath()} which supports
	 * plain segments (`foo.bar`), bracket-integer segments (`[0]`),
	 * and bracket-quoted segments (`['key with spaces']` or `["key"]`).
	 * Empty segments (e.g. from consecutive dots `a..b`) throw an exception.
	 *
	 * @param string $path
	 *
	 * @return list<string>
	 */
	private static function parsePath(string $path): array
	{
		return FilterFieldNotation::parsePath($path);
	}

	/**
	 * Recursively navigates to the target location and sets the value.
	 *
	 * @param array    $data
	 * @param string[] $keys
	 * @param mixed    $value
	 */
	private static function doSet(array &$data, array $keys, mixed $value): void
	{
		$key = \array_shift($keys);

		if (empty($keys)) {
			$data[$key] = $value;
		} else {
			if (!isset($data[$key]) || !\is_array($data[$key])) {
				$data[$key] = [];
			}

			self::doSet($data[$key], $keys, $value);
		}
	}

	/**
	 * Recursively navigates to the target location and unsets the key.
	 *
	 * @param array    $data
	 * @param string[] $keys
	 */
	private static function doRemove(array &$data, array $keys): void
	{
		$key = \array_shift($keys);

		if (empty($keys)) {
			unset($data[$key]);
		} elseif (isset($data[$key]) && \is_array($data[$key])) {
			self::doRemove($data[$key], $keys);
		}
	}
}
