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

/**
 * Interface WithPaginationInterface.
 */
interface WithPaginationInterface
{
	/**
	 * Determines if the request is cursor-based.
	 */
	public function isCursorBased(): bool;

	/**
	 * Gets the page number when using page-based pagination.
	 */
	public function getPage(): ?int;

	/**
	 * Sets the page number when using page-based pagination.
	 *
	 * When page is non-null, any cursor-based pagination parameters
	 * (cursor, cursor direction and cursor column) will be cleared.
	 *
	 * @param null|int $page the page number to set, or null to unset it
	 *
	 * @return $this
	 */
	public function setPage(?int $page): static;

	/**
	 * Gets the offset for page-based pagination, computed from the page number and max items per page.
	 */
	public function getOffset(): ?int;

	/**
	 * Sets the raw offset for page-based pagination.
	 *
	 * This overrides the computed offset from the page number and max items per page, allowing for custom pagination logic.
	 *
	 * @param null|int $offset the raw offset to set, or null to unset it
	 *
	 * @return $this
	 */
	public function setRawOffset(?int $offset): static;

	/**
	 * Gets the maximum number of items limit per result.
	 */
	public function getMax(): ?int;

	/**
	 * Sets the maximum number of items limit per result.
	 *
	 * @param null|int $max the maximum number of items to return per result, or null to unset it
	 *
	 * @return $this
	 */
	public function setMax(?int $max): static;

	/**
	 * Sets whether to ignore the max limit and load all items.
	 *
	 * @param bool $ignore_max whether to ignore the max limit and load all items
	 *
	 * @return static
	 */
	public function ignoreMax(bool $ignore_max = true): static;

	/**
	 * Gets the cursor value when using cursor-based pagination.
	 *
	 * @return null|int|string
	 */
	public function getCursor(): int|string|null;

	/**
	 * Sets the cursor value when using cursor-based pagination.
	 *
	 * @param null|int|string $cursor the cursor value to set, or null to unset it
	 *
	 * @return $this
	 */
	public function setCursor(int|string|null $cursor): static;

	/**
	 * Gets the cursor column name when using cursor-based pagination.
	 *
	 * @return null|string
	 */
	public function getCursorColumn(): ?string;

	/**
	 * Sets the cursor column name when using cursor-based pagination.
	 *
	 * @param null|string $cursor_column the cursor column name to set, or null to unset it
	 *
	 * @return $this
	 */
	public function setCursorColumn(?string $cursor_column): static;

	/**
	 * Gets the cursor direction when using cursor-based pagination.
	 *
	 * @return null|'ASC'|'DESC'
	 */
	public function getCursorDirection(): ?string;

	/**
	 * Sets the cursor direction when using cursor-based pagination.
	 *
	 * @param null|'ASC'|'DESC' $direction the cursor direction to set, or null to unset it
	 *
	 * @return $this
	 */
	public function setCursorDirection(?string $direction): static;

	/**
	 * Gets the order by clause as an associative array of column names and their sort direction.
	 *
	 * @return null|array<string, 'ASC'|'DESC'> an associative array of column names and their sort direction
	 */
	public function getOrderBy(): ?array;

	/**
	 * Sets the order by clause as an associative array of column names and their sort direction.
	 *
	 * @param null|array<string, 'ASC'|'DESC'> $order_by an associative array of column names and their sort direction
	 *
	 * @return $this
	 */
	public function setOrderBy(?array $order_by): static;
}
