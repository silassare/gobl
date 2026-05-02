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
use Override;

/**
 * Class CollectionFactory.
 *
 * @template TEntity of ORMEntity
 *
 * @extends Collection<TEntity>
 */
final class CollectionFactory extends Collection
{
	/**
	 * @var callable(ORMOptionsInterface):ORMResults<TEntity>
	 */
	protected $factory;

	/**
	 * CollectionFactory constructor.
	 *
	 * @param string                                            $name    the collection name
	 * @param callable(ORMOptionsInterface):ORMResults<TEntity> $factory the collection factory
	 */
	public function __construct(string $name, callable $factory)
	{
		parent::__construct($name);
		$this->factory = $factory;
	}

	/**
	 * CollectionFactory destructor.
	 */
	public function __destruct()
	{
		unset($this->factory);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getItems(ORMOptionsInterface $options): ORMResults
	{
		return \call_user_func($this->factory, $options);
	}
}
