<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MY_DB_NS\Base;

use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntityBase;
use MY_DB_NS\MyTableQuery as MyTableQueryReal;

//__GOBL_RELATIONS_USE_CLASS__

/**
 * Class MyEntity
 */
abstract class MyEntity extends ORMEntityBase
{
	const TABLE_NAME = 'my_table';

	//__GOBL_COLUMNS_CONST__
	//__GOBL_RELATIONS_PROPERTIES__

	/**
	 * MyEntity constructor.
	 *
	 * @param bool $is_new true for new entity false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct($is_new = true, $strict = true)
	{
		parent::__construct(
			ORM::getDatabase('MY_DB_NS'),
			$is_new,
			$strict,
			self::TABLE_NAME,
			MyTableQueryReal::class
		);
	}

	//__GOBL_RELATIONS_GETTERS__
	//__GOBL_COLUMNS_GETTERS_SETTERS__
}
