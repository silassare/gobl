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

use Gobl\DBAL\Relations\Interfaces\LinkInterface;

/**
 * Class OneToOne.
 */
final class OneToOne extends Relation
{
	/**
	 * OneToOne constructor.
	 *
	 * @param string                                        $name
	 * @param \Gobl\DBAL\Relations\Interfaces\LinkInterface $link
	 */
	public function __construct(string $name, LinkInterface $link)
	{
		parent::__construct(RelationType::ONE_TO_ONE, $name, $link);
	}
}
