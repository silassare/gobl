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

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Relations\Interfaces\VirtualRelationInterface;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Class VirtualRelation.
 *
 * @template TEntity of ORMEntity
 * @template TRelative of null|string|int|float|bool|array|JsonSerializable
 * @template TRelativeCreatePayload of array
 * @template TRelativeIdentityPayload of array
 *
 * @implements VirtualRelationInterface<TEntity,TRelative,TRelativeCreatePayload,TRelativeIdentityPayload>
 */
abstract class VirtualRelation implements VirtualRelationInterface
{
	/** @var string */
	protected string $name;

	/** @var Table */
	protected Table $host_table;

	/** @var bool */
	protected bool $paginated;

	/**
	 * VirtualRelation constructor.
	 *
	 * @param string $namespace  the host table namespace
	 * @param string $table_name the host table name
	 * @param string $name       the relation name
	 * @param bool   $paginated  true means the relation returns paginated items
	 */
	public function __construct(string $namespace, string $table_name, string $name, bool $paginated)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Virtual relation name "%s" should match: %s',
					$name,
					self::NAME_PATTERN
				)
			);
		}
		if (!Gobl::isAllowedRelationName($name)) {
			throw new DBALRuntimeException(
				\sprintf(
					'Virtual relation name "%s" is not allowed.',
					$this->name
				)
			);
		}

		$this->name       = $name;
		$this->host_table = ORM::table($namespace, $table_name);
		$this->paginated  = $paginated;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isPaginated(): bool
	{
		return $this->paginated;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHostTable(): Table
	{
		return $this->host_table;
	}
}
