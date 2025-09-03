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

use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMRequest;
use JsonSerializable;

/**
 * Class RelationControllerInterface.
 *
 * The TRelative must be serializable to json.
 * The TRelativeIdentityPayload should contains base information to identify the relative
 *
 * @template TEntity of ORMEntity
 * @template TRelative of null|string|int|float|bool|array|JsonSerializable
 * @template TRelativeCreatePayload of array
 * @template TRelativeIdentityPayload of array
 */
interface RelationControllerInterface
{
	/**
	 * Return the table that stores the relatives.
	 *
	 * With this we are sure that relatives returned by the relation controller are subclass of {@see ORMEntity} stored in the target table.
	 *
	 * @return ?Table the target table or null if the relation is not linked to any table
	 *                (virtual relation without a target table, that returns data not related to any table, etc.)
	 */
	public function getRelativesStoreTable(): ?Table;

	/**
	 * Gets a relative of a given item.
	 *
	 * @param ORMEntity  $host_entity the host entity
	 * @param ORMRequest $request     the request
	 *
	 * @return null|TRelative
	 */
	public function get(ORMEntity $host_entity, ORMRequest $request): mixed;

	/**
	 * Gets a list of relatives of a given item.
	 *
	 * @param ORMEntity  $host_entity the host entity
	 * @param ORMRequest $request     the request
	 * @param null|int   &$total      total number of items that match the filters
	 *
	 * @return TRelative[]
	 */
	public function list(ORMEntity $host_entity, ORMRequest $request, ?int &$total = null): array;

	/**
	 * Create a relative for a given item.
	 *
	 * @psalm-param ORMEntity $host_entity the host entity
	 * @psalm-param TRelativeCreatePayload $payload the relative payload
	 *
	 * @return TRelative
	 */
	public function create(ORMEntity $host_entity, array $payload): mixed;

	/**
	 * Update a relative of a given item.
	 *
	 * @psalm-param ORMEntity $host_entity the host entity
	 * @psalm-param TRelativeIdentityPayload $payload the relative identity payload
	 *
	 * @return TRelative
	 */
	public function update(ORMEntity $host_entity, array $payload): mixed;

	/**
	 * Delete a relative of a given item.
	 *
	 * @psalm-param ORMEntity $host_entity the host entity
	 * @psalm-param TRelativeIdentityPayload $payload the relative identity payload
	 *
	 * @return TRelative
	 */
	public function delete(ORMEntity $host_entity, array $payload): mixed;

	/**
	 * Link a child entity to a parent entity using the relation.
	 *
	 * @param ORMEntity $parent_entity the parent entity
	 * @param ORMEntity $child_entity  the child entity
	 * @param bool      $auto_save     should the modified entity be saved automatically?
	 *
	 * @return static
	 */
	public function link(ORMEntity $parent_entity, ORMEntity $child_entity, bool $auto_save = true): static;
}
