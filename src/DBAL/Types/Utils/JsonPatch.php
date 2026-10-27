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
use InvalidArgumentException;
use JsonSerializable;
use PHPUtils\DotPath;
use PHPUtils\Store\Map;

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
 * A patch keeps the operations it was given, not only their result, so an UPDATE can send the
 * paths instead of the document: the database applies them to the row it holds, and two
 * transactions patching different paths of one column no longer overwrite each other. The whole
 * document is still what an INSERT writes, what the entity reads back, and what an UPDATE falls
 * back to when the operations cannot be expressed as paths (see
 * {@see QueryGeneratorInterface::getJsonPatchUpdateExpression()}).
 */
final class JsonPatch
{
	private array $data;

	/**
	 * The operations, in the order they were asked for.
	 *
	 * @var list<array{op: 'remove'|'set', path: non-empty-list<string>, value?: mixed}>
	 */
	private array $operations = [];

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
	 * @param string                                            $path  dot-notation path (e.g. `'user.name'`, `'tags.0'`)
	 * @param null|array|bool|float|int|JsonSerializable|string $value the value to store
	 */
	public function set(string $path, array|bool|float|int|JsonSerializable|string|null $value): static
	{
		return $this->setAt(self::parsePath($path), $value);
	}

	/**
	 * Sets a value at the given path, as segments.
	 *
	 * For a caller that has the segments already, and for a key a path string cannot hold: one with
	 * a dot or a bracket in it is a single segment here, where `set()` would read it as a path.
	 *
	 * @param list<string> $segments
	 */
	public function setAt(array $segments, array|bool|float|int|JsonSerializable|string|null $value): static
	{
		self::assertSegments($segments);

		self::doSet($this->data, $segments, $value);

		$this->operations[] = ['op' => 'set', 'path' => $segments, 'value' => $value];

		return $this;
	}

	/**
	 * Removes the key at the given dot-notation path.
	 *
	 * Silently does nothing when the path or any intermediate key does not exist.
	 *
	 * @param string $path dot-notation path (e.g. `'temp'`, `'meta.cache_key'`)
	 */
	public function remove(string $path): static
	{
		return $this->removeAt(self::parsePath($path));
	}

	/**
	 * Removes the key at the given path, as segments.
	 *
	 * The counterpart of {@see self::setAt()}.
	 *
	 * @param list<string> $segments
	 */
	public function removeAt(array $segments): static
	{
		self::assertSegments($segments);

		self::doRemove($this->data, $segments);

		$this->operations[] = ['op' => 'remove', 'path' => $segments];

		return $this;
	}

	/**
	 * The operations this patch holds, in the order they were asked for.
	 *
	 * @return list<array{op: 'remove'|'set', path: list<string>, value?: mixed}>
	 */
	public function getOperations(): array
	{
		return $this->operations;
	}

	/**
	 * Whether anything was asked of this patch.
	 */
	public function hasOperations(): bool
	{
		return [] !== $this->operations;
	}

	/**
	 * Takes another patch's operations, applied to this one's document in the order they were given.
	 *
	 * How successive patches of one column become one patch: the operations are what the UPDATE
	 * sends, so dropping the earlier ones would leave their paths unwritten.
	 */
	public function append(self $other): static
	{
		foreach ($other->operations as $operation) {
			if ('remove' === $operation['op']) {
				self::doRemove($this->data, $operation['path']);
			} else {
				self::doSet($this->data, $operation['path'], $operation['value']);
			}

			$this->operations[] = $operation;
		}

		return $this;
	}

	/**
	 * Returns the patched data as a plain PHP array.
	 */
	public function toArray(): array
	{
		return $this->data;
	}

	/**
	 * Returns the patched data as a {@see Map} instance.
	 */
	public function toMap(): Map
	{
		return new Map($this->data);
	}

	/**
	 * Asserts that a path is addressable: at least one segment, none of them empty.
	 *
	 * @param list<string> $segments
	 *
	 * @psalm-assert non-empty-list<string> $segments
	 */
	private static function assertSegments(array $segments): void
	{
		if ([] === $segments) {
			throw new InvalidArgumentException('A JSON patch path needs at least one segment.');
		}

		foreach ($segments as $segment) {
			if ('' === $segment) {
				throw new InvalidArgumentException('A JSON patch path segment cannot be empty.');
			}
		}
	}

	/**
	 * Parses a path string into an array of segment strings.
	 *
	 * Delegates to {@see DotPath::parse()} which supports
	 * plain segments (`foo.bar`), bracket-integer segments (`[0]`),
	 * and bracket-quoted segments (`['key with spaces']` or `["key"]`).
	 * Empty segments (e.g. from consecutive dots `a..b`) throw an exception.
	 * Plain segments must be [a-zA-Z0-9_]+ only; anything else requires bracket notation.
	 *
	 * @return list<string>
	 */
	private static function parsePath(string $path): array
	{
		return DotPath::parse($path)->getSegments();
	}

	/**
	 * Recursively navigates to the target location and sets the value.
	 *
	 * @param non-empty-list<string> $keys
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
	 * @param non-empty-list<string> $keys
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
