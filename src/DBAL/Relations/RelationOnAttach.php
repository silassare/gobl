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

namespace Gobl\DBAL\Relations;

/**
 * What attaching, through a relation, a record that another row already holds does.
 *
 * Only a record that holds its own link can be held elsewhere -- a child whose key names another
 * parent; a record reached through a link table, or one the host points at, can be linked to any
 * number of rows.
 */
enum RelationOnAttach: string
{
	/** Refused: the record is another row's, and taking it is not this request's to decide. */
	case REFUSE = 'refuse';

	/** The record is moved: its link to the other row is replaced by the link to this one. */
	case MOVE = 'move';
}
