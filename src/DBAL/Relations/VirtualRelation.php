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

use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMRequest;
use InvalidArgumentException;

/**
 * Class VirtualRelation.
 */
abstract class VirtualRelation
{
	public const NAME_PATTERN = Relation::NAME_PATTERN;

	public const NAME_REG = Relation::NAME_REG;

	/** @var string */
	protected string $name;

	/** @var Table */
	protected Table $host_table;

	/** @var bool */
	protected bool $paginated;

	/**
	 * VirtualRelation constructor.
	 *
	 * @param string           $name
	 * @param \Gobl\DBAL\Table $host_table
	 * @param bool             $paginated
	 */
	public function __construct(string $name, Table $host_table, bool $paginated)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf(
				'Virtual relation name "%s" should match: %s',
				$name,
				self::NAME_PATTERN
			));
		}

		$this->name       = $name;
		$this->host_table = $host_table;
		$this->paginated  = $paginated;
	}

	/**
	 * Checks if the virtual relation returns paginated items.
	 *
	 * ie: all relation items can't be retrieved at once.
	 *
	 * @return bool
	 */
	public function isPaginated(): bool
	{
		return $this->paginated;
	}

	/**
	 * Gets the virtual relation name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Gets the virtual relation host table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getHostTable(): Table
	{
		return $this->host_table;
	}

	/**
	 * Gets a relation for a given target item.
	 *
	 * @param \Gobl\ORM\ORMEntity  $target
	 * @param \Gobl\ORM\ORMRequest $request
	 * @param null|int             &$total_records
	 *
	 * @return mixed
	 */
	abstract public function get(
		ORMEntity $target,
		ORMRequest $request,
		?int &$total_records = null
	): mixed;
}
