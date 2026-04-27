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
 * Interface ORMOptionsInterface.
 */
interface ORMOptionsInterface extends ORMSelectOptionsInterface, ORMDeleteOptionsInterface, ORMUpdateOptionsInterface, ORMCreateOptionsInterface
{
	/**
	 * Gets requested relations.
	 *
	 * @return null|list<string> an array of requested relation names, or null if no relations are requested
	 */
	public function getRequestedRelations(): ?array;

	/**
	 * Adds requested relation.
	 *
	 * @param string $name the name of the relation
	 *
	 * @return $this
	 */
	public function addRequestedRelation(string $name): static;

	/**
	 * Gets requested collection.
	 *
	 * @return null|string the requested collection name, or null if no collection is requested
	 */
	public function getRequestedCollection(): ?string;

	/**
	 * Sets the requested collection.
	 *
	 * @param null|string $name the requested collection name, or null to unset it
	 *
	 * @return $this
	 */
	public function setRequestedCollection(?string $name): static;
}
