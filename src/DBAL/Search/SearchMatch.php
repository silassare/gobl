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

/**
 * What a searchable column is indexed for.
 *
 * An intent and never a mechanism: one schema builds on MySQL, PostgreSQL and SQLite, so a
 * declaration naming an engine's index kind would leak one engine into a portable file. Each
 * driver renders the intent its own way, and {@see Gobl\DBAL\Indexes\IndexType} carries it.
 */
enum SearchMatch: string
{
	/**
	 * A term at the start of the text is found fast.
	 *
	 * Served by `where<Column>StartsWith()`. A substring search of the same column still works and
	 * is still a scan, since no engine's B-tree can serve one.
	 */
	case PREFIX = 'prefix';

	/**
	 * A term anywhere in the text is found fast.
	 *
	 * Served by `where<Column>Matches()`, and by `where<Column>StartsWith()` as a scan.
	 */
	case SUBSTRING = 'substring';
}
