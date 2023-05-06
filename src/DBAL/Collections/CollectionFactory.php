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

/**
 * Class CollectionFactory.
 */
class CollectionFactory extends Collection
{
	/**
	 * @var callable
	 */
	protected $factory;

	/**
	 * CollectionFactory constructor.
	 *
	 * @param string   $name
	 * @param callable $factory
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
	public function getItems(ORMRequest $request, int &$total_records = null): array
	{
		return ($this->factory)($request, $total_records);
	}
}
