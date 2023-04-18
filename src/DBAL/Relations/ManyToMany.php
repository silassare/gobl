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
 * Class ManyToMany.
 */
final class ManyToMany extends Relation
{
	/**
	 * ManyToMany constructor.
	 *
	 * @param string                           $name
	 * @param \Gobl\DBAL\Relations\LinkThrough $link_through
	 */
	public function __construct(string $name, LinkThrough $link_through)
	{
		parent::__construct(RelationType::MANY_TO_MANY, $name, $link_through);
	}
}
