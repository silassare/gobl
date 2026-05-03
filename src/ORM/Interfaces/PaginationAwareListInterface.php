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

namespace Gobl\ORM\Interfaces;

use Gobl\ORM\ORMEntity;
use JsonSerializable;
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface PaginationAwareListInterface.
 *
 * This represents a list of ORM entities, with support for pagination and total count.
 *
 * @template T of null|string|int|float|bool|array|JsonSerializable|ORMEntity
 */
interface PaginationAwareListInterface extends ArrayCapableInterface
{
	/**
	 * Returns all items as an iterable.
	 *
	 * @return iterable<T>
	 */
	public function getItems(bool $strict = true): iterable;

	/**
	 * Returns items with cursor metadata.
	 *
	 * @param WithPaginationInterface $options
	 *
	 * @return array{items: T[], next_cursor: null|int|string, cursor_column: null|string, has_more: bool}
	 */
	public function getItemsWithCursorMeta(WithPaginationInterface $options, bool $strict = true): array;

	/**
	 * Gets the total count of items in the list.
	 *
	 * This is useful for paginated lists, to know how many items there are in total.
	 *
	 * @param null|WithPaginationInterface $options the pagination options, if any
	 * @param bool                         $force   whether to force a recount even if a cached value is available
	 *
	 * @return int the total count of items in the list
	 */
	public function getTotal(?WithPaginationInterface $options = null, bool $force = false): int;
}
