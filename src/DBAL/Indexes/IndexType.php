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
}
