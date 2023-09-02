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

namespace Gobl\ORM;

use Gobl\CRUD\CRUDEventProducer;

/**
 * Class ORMEntityCRUD.
 *
 * @template TEntity of \Gobl\ORM\ORMEntity
 *
 * @extends \Gobl\CRUD\CRUDEventProducer<TEntity>
 */
abstract class ORMEntityCRUD extends CRUDEventProducer
{
	/**
	 * Returns new instance.
	 *
	 * @return static<TEntity>
	 */
	abstract public static function new(): static;
}
