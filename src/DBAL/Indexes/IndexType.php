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

namespace Gobl\DBAL\Indexes;

/**
 * Enum IndexType.
 */
enum IndexType: string
{
	/** Standard B-Tree index (default) : supported by MySQL, PostgreSQL, SQLite and most drivers. */
	case BTREE = 'BTREE';

	/** Hash index : equality comparisons only. Supported by MySQL (MEMORY/NDB) and PostgreSQL. */
	case HASH = 'HASH';

	/** Full-text index for CHAR / VARCHAR / TEXT columns (MySQL / MariaDB). */
	case MYSQL_FULLTEXT = 'MYSQL_FULLTEXT';

	/** Spatial index for geometry columns : InnoDB / MyISAM (MySQL). */
	case MYSQL_SPATIAL = 'MYSQL_SPATIAL';

	/** GIN (Generalized Inverted Index) : arrays, jsonb, full-text search. */
	case PGSQL_GIN = 'PGSQL_GIN';

	/** GiST (Generalized Search Tree) : geometry, ranges, full-text search. */
	case PGSQL_GIST = 'PGSQL_GIST';

	/** BRIN (Block Range Index) : large append-only tables. */
	case PGSQL_BRIN = 'PGSQL_BRIN';

	/** SP-GiST (Space-Partitioned GiST) : partitioned searches (points, ranges). */
	case PGSQL_SPGIST = 'PGSQL_SPGIST';

	/**
	 * A term at the start of a folded text column is found fast.
	 *
	 * An intent, not a mechanism: each driver renders it as its own index, so one schema builds on
	 * all of them. MySQL indexes a prefix of the column (a `TEXT` column has no key length of its
	 * own), PostgreSQL uses `text_pattern_ops` so a `LIKE 'term%'` is served under any collation,
	 * SQLite takes a plain index.
	 */
	case TEXT_SEARCH_PREFIX = 'TEXT_SEARCH_PREFIX';

	/**
	 * A term anywhere in a folded text column is found fast.
	 *
	 * An intent, as above. MySQL uses a `FULLTEXT` index with the `ngram` parser, which is what
	 * makes a substring findable and is also why a match is n-gram based and not literal;
	 * PostgreSQL a GIN index with `gin_trgm_ops`, which serves `LIKE '%term%'` from three
	 * consecutive characters on; SQLite has no equivalent and reads the column.
	 */
	case TEXT_SEARCH_SUBSTRING = 'TEXT_SEARCH_SUBSTRING';
}
