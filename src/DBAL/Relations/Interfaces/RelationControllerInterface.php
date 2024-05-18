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
	 * Gets a relative for a given target item.
	 *
	 * @psalm-param ORMEntity $entity
	 *
	 * @return TRelative
	 */
	public function get(
		ORMEntity $entity,
		ORMRequest $request,
	): mixed;

	/**
	 * Gets a list of relatives for a given target item.
	 *
	 * @psalm-param ORMEntity $entity
	 *
	 * @return TRelative[]
	 */
	public function list(
		ORMEntity $entity,
		ORMRequest $request,
		?int &$total_records = null
	): array;

	/**
	 * Create a relative to a target item relation.
	 *
	 * @psalm-param ORMEntity $entity
	 * @psalm-param TRelativeCreatePayload $payload
	 *
	 * @return TRelative
	 */
	public function create(ORMEntity $entity, array $payload): mixed;

	/**
	 * Update a relative in a target item relation.
	 *
	 * @psalm-param ORMEntity $entity
	 * @psalm-param TRelativeIdentityPayload $payload
	 *
	 * @return TRelative
	 */
	public function update(ORMEntity $entity, array $payload): mixed;

	/**
	 * Delete a relative from a target item relation.
	 *
	 * @psalm-param ORMEntity $entity
	 * @psalm-param TRelativeIdentityPayload $payload
	 *
	 * @return TRelative
	 */
	public function delete(ORMEntity $entity, array $payload): mixed;
}
