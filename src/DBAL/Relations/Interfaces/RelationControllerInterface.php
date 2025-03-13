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

namespace Gobl\DBAL\Relations\Interfaces;

use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMRequest;

/**
 * Class RelationControllerInterface.
 *
 * The TRelative must be serializable to json.
 * The TRelativeIdentityPayload should contains base information to identify the relative
 *
 * @template TEntity of ORMEntity
 * @template TRelative of null|string|int|float|bool|array|\JsonSerializable
 * @template TRelativeCreatePayload of array
 * @template TRelativeIdentityPayload of array
 */
interface RelationControllerInterface
{
	/**
	 * Gets a relative of a given item.
	 *
	 * @psalm-param ORMEntity $host_entity
	 *
	 * @return null|TRelative
	 */
	public function get(ORMEntity $host_entity, ORMRequest $request): mixed;

	/**
	 * Gets a list of relatives of a given item.
	 *
	 * @psalm-param ORMEntity $host_entity
	 *
	 * @return TRelative[]
	 */
	public function list(ORMEntity $host_entity, ORMRequest $request, ?int &$total_records = null): array;

	/**
	 * Create a relative for a given item.
	 *
	 * @psalm-param ORMEntity $host_entity
	 * @psalm-param TRelativeCreatePayload $payload
	 *
	 * @return TRelative
	 */
	public function create(ORMEntity $host_entity, array $payload): mixed;

	/**
	 * Update a relative of a given item.
	 *
	 * @psalm-param ORMEntity $host_entity
	 * @psalm-param TRelativeIdentityPayload $payload
	 *
	 * @return TRelative
	 */
	public function update(ORMEntity $host_entity, array $payload): mixed;

	/**
	 * Delete a relative of a given item.
	 *
	 * @psalm-param ORMEntity $host_entity
	 * @psalm-param TRelativeIdentityPayload $payload
	 *
	 * @return TRelative
	 */
	public function delete(ORMEntity $host_entity, array $payload): mixed;

	/**
	 * Link a child entity to a parent entity using the relation.
	 *
	 * @param ORMEntity $parent_entity
	 * @param ORMEntity $child_entity
	 * @param bool      $auto_save
	 *
	 * @return static
	 */
	public function link(ORMEntity $parent_entity, ORMEntity $child_entity, bool $auto_save = true): static;
}
