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

use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\ORM\ORMEntity;
use JsonSerializable;

/**
 * Class VirtualRelationInterface.
 *
 * @template TEntity of ORMEntity
 * @template TRelative of null|string|int|float|bool|array|JsonSerializable
 * @template TRelativeCreatePayload of array
 * @template TRelativeIdentityPayload of array
 *
 * @extends RelationInterface<TEntity,TRelative,TRelativeCreatePayload,TRelativeIdentityPayload>
 */
interface VirtualRelationInterface extends RelationInterface
{
	/**
	 * Gets the relative type.
	 *
	 * If it is a paginated relation, the relative type should be the type of a single item.
	 *
	 * This is useful for ORM generated getters/setters.
	 * And for generated API documentation.
	 *
	 * @return TypeInterface
	 */
	public function getRelativeType(): TypeInterface;
}
