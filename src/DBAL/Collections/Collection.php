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

namespace Gobl\DBAL\Collections;

use Gobl\ORM\ORMRequest;
use InvalidArgumentException;

/**
 * Class Collection.
 */
abstract class Collection
{
	public const NAME_PATTERN = '[a-zA-Z](?:[a-zA-Z0-9_-]*[a-zA-Z0-9])?';

	public const NAME_REG = '~^' . self::NAME_PATTERN . '$~';

	/**
	 * @var string
	 */
	protected string $name;

	/**
	 * Collection constructor.
	 *
	 * @param string $name
	 */
	public function __construct(string $name)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf(
				'Collection name "%s" should match: %s',
				$name,
				self::NAME_PATTERN
			));
		}

		$this->name = $name;
	}

	/**
	 * Gets collection name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Returns the collection items.
	 *
	 * @param ORMRequest $request
	 * @param null|int   &$total_records
	 *
	 * @return \Gobl\ORM\ORMEntity[]
	 */
	abstract public function getItems(ORMRequest $request, int &$total_records = null): array;

	/**
	 * Creates a new collection using a callable.
	 *
	 * @param string   $name    the collection name
	 * @param callable $factory the collection factory
	 *
	 * @return CollectionFactory
	 */
	final public static function fromFactory(string $name, callable $factory): CollectionFactory
	{
		return new CollectionFactory($name, $factory);
	}
}
