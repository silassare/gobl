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

namespace Gobl\DBAL\Types\Interfaces;

/**
 * Interface TypeProviderInterface.
 */
interface TypeProviderInterface
{
	/**
	 * Gets type instance based on given type name and options.
	 *
	 * @param string $name    the type name
	 * @param array  $options the options
	 *
	 * @return null|\Gobl\DBAL\Types\Interfaces\TypeInterface
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException when a type with the given name was found
	 *                                                    but options is invalid
	 */
	public function getTypeInstance(string $name, array $options): ?TypeInterface;

	/**
	 * Checks if a given type name exists.
	 *
	 * @param string $name the type name
	 *
	 * @return bool
	 */
	public function hasType(string $name): bool;
}
