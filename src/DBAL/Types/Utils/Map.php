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

namespace Gobl\DBAL\Types\Utils;

use ArrayAccess;
use IteratorAggregate;
use PHPUtils\Store\Store;

/**
 * Class Map.
 *
 * @template TOf of mixed
 *
 * @extends Store<array<string, TOf>>
 *
 * @implements ArrayAccess<string, TOf>
 * @implements IteratorAggregate<string, TOf>
 */
class Map extends Store implements ArrayAccess, IteratorAggregate
{
	/**
	 * Map constructor.
	 */
	public function __construct(array &$data = [])
	{
		$this->json_empty_array_is_object = true;

		parent::__construct($data);
	}
}
