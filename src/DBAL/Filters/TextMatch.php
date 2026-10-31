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

namespace Gobl\DBAL\Filters;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Operator;

/**
 * What {@see Operator::MATCHES} does to a search term before it is bound.
 *
 * The term is a term and never a pattern, so the wrapping is here and not at the call site:
 * an indexed search of the same column needs the bare term, and a term holding `%` or `_` would
 * otherwise be a wildcard by accident.
 *
 * One place holds the escape character, because the term is escaped here while every driver
 * emits the `ESCAPE` clause that reads it.
 */
final class TextMatch
{
	/**
	 * The `LIKE ... ESCAPE` character.
	 *
	 * Not a backslash: how a backslash in a SQL string literal reads depends on the engine and on
	 * its settings (MySQL `NO_BACKSLASH_ESCAPES`, PostgreSQL `standard_conforming_strings`), and a
	 * compiled query must not depend on either.
	 */
	public const string ESCAPE_CHAR = '!';

	/**
	 * The `LIKE` pattern a term searches with: the escaped term, between two wildcards.
	 *
	 * @throws DBALRuntimeException when the term is empty
	 */
	public static function pattern(string $term): string
	{
		if ('' === $term) {
			throw new DBALRuntimeException(\sprintf(
				'operator "%s" requires a non-empty search term: an empty one matches every row, which is never what a search means.',
				Operator::MATCHES->name
			));
		}

		return '%' . self::escape($term) . '%';
	}

	/**
	 * The `LIKE` pattern a term the text begins with searches with.
	 *
	 * @throws DBALRuntimeException when the term is empty
	 */
	public static function prefixPattern(string $term): string
	{
		if ('' === $term) {
			throw new DBALRuntimeException(\sprintf(
				'operator "%s" requires a non-empty search term: an empty one matches every row, which is never what a search means.',
				Operator::STARTS_WITH->name
			));
		}

		return self::escape($term) . '%';
	}

	/**
	 * The pattern an operator searches a term with.
	 *
	 * @throws DBALRuntimeException when the operator is not a text search, or the term is empty
	 */
	public static function patternFor(Operator $operator, string $term): string
	{
		return match ($operator) {
			Operator::MATCHES     => self::pattern($term),
			Operator::STARTS_WITH => self::prefixPattern($term),
			default               => throw new DBALRuntimeException(\sprintf(
				'operator "%s" is not a text search.',
				$operator->name
			)),
		};
	}

	/**
	 * Escapes what `LIKE` would otherwise read as a wildcard.
	 */
	public static function escape(string $term): string
	{
		return \str_replace(
			[self::ESCAPE_CHAR, '%', '_'],
			[self::ESCAPE_CHAR . self::ESCAPE_CHAR, self::ESCAPE_CHAR . '%', self::ESCAPE_CHAR . '_'],
			$term
		);
	}
}
