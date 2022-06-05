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

namespace Gobl\DBAL\Relations;

/**
 * Enum RelationType.
 */
enum RelationType: string
{
	case ONE_TO_ONE = 'one-to-one';

	case ONE_TO_MANY = 'one-to-many';

	case MANY_TO_ONE = 'many-to-one';

	case MANY_TO_MANY = 'many-to-many';

	/**
	 * Checks if the relation returns multiple items.
	 *
	 * @return bool
	 */
	public function isMultiple(): bool
	{
		return self::ONE_TO_MANY === $this || self::MANY_TO_MANY === $this;
	}
}
