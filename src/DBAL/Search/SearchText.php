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
 * What of a JSON column goes into its folded text.
 *
 * It says what a search of the column can find, so it is the schema author's choice and not a
 * default to be discovered. It means nothing on a column holding plain text.
 */
enum SearchText: string
{
	/**
	 * The document's values, and not its keys.
	 *
	 * A term never matches a key name or a stored id, which is the better search. It is also why a
	 * search of a column declared this way answers less than a search of the document text: the
	 * difference is the keys.
	 */
	case VALUES = 'values';

	/**
	 * The document as it is written, keys included.
	 *
	 * What an unindexed whole-column search reads, so declaring a column searchable changes how
	 * fast an answer comes and not what the answer is. A term can match a key name.
	 */
	case DOCUMENT = 'document';
}
