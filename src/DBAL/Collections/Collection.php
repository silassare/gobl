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

namespace Gobl\DBAL\Collections;

use Gobl\ORM\Interfaces\ORMOptionsInterface;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMResults;
use InvalidArgumentException;

/**
 * Class Collection.
 *
 * @template TEntity of ORMEntity
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
	 * @param ORMOptionsInterface $options
	 *
	 * @return ORMResults<TEntity>
	 */
	abstract public function getItems(ORMOptionsInterface $options): ORMResults;

	/**
	 * Creates a new collection using a callable.
	 *
	 * @template T of ORMEntity
	 *
	 * @param string                                      $name    the collection name
	 * @param callable(ORMOptionsInterface):ORMResults<T> $factory the collection factory
	 *
	 * @return CollectionFactory<T>
	 */
	final public static function fromFactory(string $name, callable $factory): CollectionFactory
	{
		return new CollectionFactory($name, $factory);
	}
}
