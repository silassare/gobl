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

namespace Gobl\DBAL\Search;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeJson;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use JsonSerializable;
use PHPUtils\Store\Map;
use PHPUtils\Str;

/**
 * The folded text a searchable column is searched through, and the column that holds it.
 *
 * The folding happens here, in PHP, and not in SQL: it is the only way it is **identical on every
 * driver**, which is the whole point -- compiling it per engine, as an unindexed search must,
 * answers differently on each one. It is also the only indexable form, since no engine indexes an
 * accent-folding expression portably.
 *
 * One place folds, so the term a query searches with and the text a row holds are folded the same
 * way. A difference between the two is a search that finds nothing and says nothing.
 */
final class Searchable
{
	/**
	 * How deep a searchable value is followed: {@see self::collectScalars()}.
	 */
	public const int MAX_DOCUMENT_DEPTH = 64;

	/**
	 * What a searchable column's companion is named after it.
	 *
	 * Two underscores because one is what a column name normally holds: `title__search` cannot be
	 * mistaken for a column somebody declared.
	 */
	public const string COLUMN_SUFFIX = '__search';

	/**
	 * The name of the column holding a column's folded text.
	 */
	public static function columnName(string $column_name): string
	{
		return $column_name . self::COLUMN_SUFFIX;
	}

	/**
	 * Whether a type can be searched at all.
	 *
	 * Text, and the JSON kinds that hold it. A number or a date is not refused a `matches()` -- the
	 * unindexed search takes any column -- but there is no text in it to fold and index.
	 */
	public static function isSearchableType(string $type_name): bool
	{
		return \in_array($type_name, [
			TypeString::NAME,
			TypeEnum::NAME,
			TypeJson::NAME,
			TypeMap::NAME,
			TypeList::NAME,
		], true);
	}

	/**
	 * Folds a text to what it is searched as: lower case, no accents, single spaces.
	 *
	 * Accents are folded by one table ({@see Str::removeAccents()}), so what it does not know
	 * passes through as itself. A script it has no entry for keeps its letters and loses only its
	 * case, which is what the engines do on their own.
	 */
	public static function fold(string $text): string
	{
		$text    = Str::removeAccents($text);
		$text    = \mb_strtolower($text);
		$spaced  = \preg_replace('~\s+~u', ' ', $text);

		return \trim($spaced ?? $text);
	}

	/**
	 * Folds a column's value to the text its companion column holds.
	 *
	 * @param mixed      $value the value as the column holds it
	 * @param SearchText $text  what of a document is folded
	 *
	 * @return null|string the folded text, or null when the value holds none
	 */
	public static function foldValue(mixed $value, SearchText $text = SearchText::VALUES): ?string
	{
		if (null === $value || \is_bool($value)) {
			return null;
		}

		if (\is_string($value)) {
			return self::fold($value);
		}

		if (\is_int($value) || \is_float($value)) {
			return self::fold((string) $value);
		}

		$data = self::documentOf($value);

		if (null === $data) {
			return null;
		}

		if (SearchText::DOCUMENT === $text) {
			// The characters themselves, not their escapes: `\u00c9` would fold to nothing and the
			// folded text would hold the escape rather than the letter.
			$encoded = \json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

			return false === $encoded ? null : self::fold($encoded);
		}

		$scalars = [];
		self::collectScalars($data, $scalars);

		return self::fold(\implode(' ', $scalars));
	}

	/**
	 * The document a value carries, or null when it is not one.
	 */
	private static function documentOf(mixed $value): ?array
	{
		if (\is_array($value)) {
			return $value;
		}

		if ($value instanceof Map) {
			return $value->getData();
		}

		if ($value instanceof JsonSerializable) {
			$serialized = $value->jsonSerialize();

			return \is_array($serialized) ? $serialized : null;
		}

		return null;
	}

	/**
	 * Collects a document's text, in the order the document holds it.
	 *
	 * Keys are not collected: that is what {@see SearchText::VALUES} means. A boolean and a null
	 * carry no text somebody searches for.
	 *
	 * @param list<string> $out
	 */
	private static function collectScalars(array $data, array &$out, int $depth = 0): void
	{
		// A `Map` or a `JsonSerializable` can answer with something holding itself, so the depth of
		// this walk is the value a project writes to a searchable column and nothing else. A plain
		// decoded request body cannot be cyclic; server code can.
		if ($depth > self::MAX_DOCUMENT_DEPTH) {
			throw new DBALRuntimeException(\sprintf(
				'A searchable value nests more than %d levels deep: it may hold itself.',
				self::MAX_DOCUMENT_DEPTH
			));
		}

		foreach ($data as $value) {
			if (\is_array($value)) {
				self::collectScalars($value, $out, $depth + 1);
			} elseif (\is_string($value)) {
				$out[] = $value;
			} elseif (\is_int($value) || \is_float($value)) {
				$out[] = (string) $value;
			} elseif ($value instanceof Map || $value instanceof JsonSerializable) {
				$document = self::documentOf($value);

				if (null !== $document) {
					self::collectScalars($document, $out, $depth + 1);
				}
			}
		}
	}
}
