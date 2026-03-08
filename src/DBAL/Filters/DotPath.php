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

namespace Gobl\DBAL\Filters;

use InvalidArgumentException;
use Stringable;

/**
 * Value object representing a parsed JSON path with JS-like dot/bracket notation.
 *
 * Path syntax:
 *   - Plain segment: `foo` - must be [a-zA-Z0-9_]+ only.
 *     Segments with spaces, dots, or any other special character MUST use bracket notation.
 *   - Bracket-integer segment: `[0]` - non-negative integer index.
 *   - Bracket-quoted segment: `['...']` or `["..."]` - any key, with `\'` or `\"` for escaping.
 *   - Segments are separated by `.` (optional after a `]`).
 *   - Empty segments (from consecutive dots) throw.
 *
 * Examples:
 *   `foo.bar`                   -> `['foo', 'bar']`
 *   `foo[0].bar`                -> `['foo', '0', 'bar']`
 *   `foo['bar.baz'].qux`        -> `['foo', 'bar.baz', 'qux']`
 *   `['it\'s'].key`             -> `["it's", 'key']`
 *   `['space key'].sub`         -> `['space key', 'sub']`
 *   `foo["bar"]["baz"]`         -> `['foo', 'bar', 'baz']`
 */
final class DotPath implements Stringable
{
	/**
	 * DotPath constructor.
	 *
	 * @param array<int,string> $segments
	 */
	public function __construct(private readonly array $segments) {}

	/**
	 * Serializes path segments back to a path string using JS-like notation.
	 *
	 * Plain identifiers ([a-zA-Z0-9_]+) are emitted as-is and joined by `.`.
	 * Everything else is emitted as `['...']` with `'` escaped as `\'`.
	 * Bracket segments are concatenated without a dot separator.
	 */
	public function __toString(): string
	{
		$out   = '';
		$first = true;

		foreach ($this->segments as $seg) {
			if (\preg_match('/^[a-zA-Z0-9_]+$/', $seg)) {
				// Plain segment: emit with a dot separator
				$out .= ($first ? '' : '.') . $seg;
				$first = false;
			} else {
				// Bracket-quoted: no dot separator before '['
				$out .= "['" . \str_replace("'", "\\'", $seg) . "']";
				$first = false;
			}
		}

		return $out;
	}

	/**
	 * Parse a path string into a DotPath instance using JS-like notation.
	 *
	 * Grammar:
	 *   path       := segment ( ('.' segment) | bracket )*
	 *   segment    := plain | bracket
	 *   plain      := [a-zA-Z0-9_]+
	 *   bracket    := '[' ( sq-content | dq-content | integer ) ']'
	 *   sq-content := ['] ( [^'\\] | [\\]['] )* [']   (escape: \')
	 *   dq-content := ["] ( [^"\\] | [\\]["] )* ["]   (escape: \")
	 *   integer    := [0-9]+
	 *
	 * Rules:
	 *   - A `.` separator between segments is optional after a `]`.
	 *   - Empty plain segments (e.g. consecutive dots) throw.
	 *   - Plain segments must match [a-zA-Z0-9_]+; anything else requires bracket notation.
	 *
	 * @return static
	 *
	 * @throws InvalidArgumentException if the path is empty, has empty/invalid segments, or is malformed
	 */
	public static function parse(string $path): static
	{
		if ('' === $path) {
			throw new InvalidArgumentException('Invalid JSON path: path portion cannot be empty');
		}

		$segments = [];
		$len      = \strlen($path);
		$i        = 0;

		while ($i < $len) {
			if ('[' === $path[$i]) {
				// Bracket segment: [integer], ['...'], or ["..."]
				++$i;

				if ($i >= $len) {
					throw new InvalidArgumentException('Invalid JSON path: unexpected end after `[`');
				}

				$quote = $path[$i];

				if ("'" === $quote || '"' === $quote) {
					// Bracket-quoted segment: ['...'] or ["..."]
					++$i;
					$seg = '';

					while ($i < $len) {
						$ch = $path[$i];

						if ('\\' === $ch && $i + 1 < $len && $path[$i + 1] === $quote) {
							// Escaped quote: \' or \"
							$seg .= $quote;
							$i += 2;
						} elseif ($ch === $quote) {
							// Closing quote
							++$i;

							break;
						} else {
							$seg .= $ch;
							++$i;
						}
					}

					if ($i >= $len || ']' !== $path[$i]) {
						throw new InvalidArgumentException('Invalid JSON path: missing closing `]` after quoted segment');
					}

					++$i; // consume ']'
					$segments[] = $seg;
				} elseif (\ctype_digit($path[$i])) {
					// Bracket-integer segment: [0], [42], ...
					$start = $i;

					while ($i < $len && \ctype_digit($path[$i])) {
						++$i;
					}

					if ($i >= $len || ']' !== $path[$i]) {
						throw new InvalidArgumentException('Invalid JSON path: missing closing `]` after integer index');
					}

					++$i; // consume ']'
					$segments[] = \substr($path, $start, $i - $start - 1);
				} else {
					throw new InvalidArgumentException(
						\sprintf('Invalid JSON path: expected quote or integer after `[`, got `%s`', $path[$i])
					);
				}
			} else {
				// Plain segment: collect until '.', '[', or end; must be [a-zA-Z0-9_]+
				$start = $i;

				while ($i < $len && '.' !== $path[$i] && '[' !== $path[$i]) {
					++$i;
				}

				if ($i === $start) {
					throw new InvalidArgumentException('Invalid JSON path: empty segment (consecutive dots not allowed)');
				}

				$seg = \substr($path, $start, $i - $start);

				if (!\preg_match('/^[a-zA-Z0-9_]+$/', $seg)) {
					throw new InvalidArgumentException(
						\sprintf(
							"Invalid JSON path: plain segment `%s` contains invalid characters; use bracket notation `['key']`.",
							$seg
						)
					);
				}

				$segments[] = $seg;
			}

			// Consume the optional dot separator between segments (mandatory after plain, optional after ']')
			if ($i < $len && '.' === $path[$i]) {
				++$i;

				// A trailing dot (nothing after it) is an error
				if ($i >= $len) {
					throw new InvalidArgumentException('Invalid JSON path: trailing dot');
				}
			}
		}

		return new self($segments);
	}

	/**
	 * Returns the parsed path segments.
	 *
	 * @return array<int,string>
	 */
	public function getSegments(): array
	{
		return $this->segments;
	}

	/**
	 * Checks if this path has no segments.
	 */
	public function isEmpty(): bool
	{
		return empty($this->segments);
	}
}
