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
 * What removing a relative through a relation does, declared on the relation the way a foreign key
 * declares `ON DELETE`.
 */
enum RelationOnRemove: string
{
	/**
	 * The link goes, the record stays: the link-table row is deleted, or the key that points from
	 * one row to the other is set to null. Refused where that key cannot be null. The default,
	 * because the record may be another row's too.
	 */
	case UNLINK = 'unlink';

	/** The link goes, and the record with it. */
	case DELETE = 'delete';
}
