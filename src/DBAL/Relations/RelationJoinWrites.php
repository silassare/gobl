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
 * What a write through a join relation does: one across several tables writes no single row.
 */
enum RelationJoinWrites: string
{
	/** Refused. The default: there is no one row a create, an attach or a removal would mean. */
	case REFUSE = 'refuse';

	/**
	 * A join of two steps -- the host, a link table, the target -- written as a relation through
	 * that link table is. Refused for a longer join, where no single link row stands for the
	 * relation.
	 */
	case LAST_STEP = 'last_step';
}
