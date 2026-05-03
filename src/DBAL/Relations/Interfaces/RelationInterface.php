<?php

/**
 * Copyright (c) Emile Silas Sare.
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
use JsonSerializable;

/**
 * Class RelationInterface.
 *
 * @template TRelative of null|string|int|float|bool|array|JsonSerializable|ORMEntity
 * @template TRelativeCreatePayload of array
 * @template TRelativeIdentityPayload of array
 */
interface RelationInterface
{
	public const NAME_PATTERN = '[a-zA-Z](?:[a-zA-Z0-9_-]*[a-zA-Z0-9])?';

	public const NAME_REG = '~^' . self::NAME_PATTERN . '$~';

	/**
	 * Gets the relation name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Gets the relation host table.
	 *
	 * @return Table
	 */
	public function getHostTable(): Table;

	/**
	 * Is the relation private?
	 *
	 * @return bool
	 */
	public function isPrivate(): bool;

	/**
	 * Checks if the relation returns paginated items.
	 *
	 * ie: all relation items can't be retrieved at once.
	 *
	 * @return bool
	 */
	public function isPaginated(): bool;

	/**
	 * Should return the relatives controller instance.
	 *
	 * @return RelationControllerInterface<TRelative,TRelativeCreatePayload,TRelativeIdentityPayload>
	 */
	public function getController(): RelationControllerInterface;
}
