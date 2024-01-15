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
use Gobl\DBAL\Table;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Link.
 */
abstract class Link implements LinkInterface
{
	use ArrayCapableTrait;

	/**
	 * Link constructor.
	 *
	 * @param LinkType $type
	 * @param Table    $host_table
	 * @param Table    $target_table
	 */
	public function __construct(
		protected readonly LinkType $type,
		protected readonly Table $host_table,
		protected readonly Table $target_table,
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function getHostTable(): Table
	{
		return $this->host_table;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTargetTable(): Table
	{
		return $this->target_table;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): LinkType
	{
		return $this->type;
	}
}
