<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\CRUD\Handler;

use Gobl\CRUD\CRUDColumnUpdate;
use Gobl\CRUD\CRUDCreate;
use Gobl\CRUD\CRUDDelete;
use Gobl\CRUD\CRUDDeleteAll;
use Gobl\CRUD\CRUDRead;
use Gobl\CRUD\CRUDReadAll;
use Gobl\CRUD\CRUDUpdate;
use Gobl\CRUD\CRUDUpdateAll;
use Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface;

class CRUDHandlerDefault implements CRUDHandlerInterface
{
	/**
	 * CRUDHandler constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return bool
	 */
	public function onBeforeCreate(CRUDCreate $action)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function onBeforeRead(CRUDRead $action)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function onBeforeUpdate(CRUDUpdate $action)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function onBeforeDelete(CRUDDelete $action)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function onBeforeReadAll(CRUDReadAll $action)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function onBeforeUpdateAll(CRUDUpdateAll $action)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function onBeforeDeleteAll(CRUDDeleteAll $action)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function onBeforeColumnUpdate(CRUDColumnUpdate $action)
	{
		return true;
	}

	public function onAfterCreateEntity($entity)
	{
	}

	public function onAfterReadEntity($entity)
	{
	}

	public function onBeforeUpdateEntity($entity)
	{
	}

	public function onAfterUpdateEntity($entity)
	{
	}

	public function onBeforeDeleteEntity($entity)
	{
	}

	public function onAfterDeleteEntity($entity)
	{
	}

	/**
	 * @return bool
	 */
	public function shouldWritePkColumn()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function shouldWritePrivateColumn()
	{
		return false;
	}

	public function autoFillCreateForm(array &$form)
	{
	}
}
