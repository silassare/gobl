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

namespace Gobl\CRUD\Handler;

use Gobl\CRUD\CRUDColumnUpdate;
use Gobl\CRUD\CRUDCreate;
use Gobl\CRUD\CRUDDelete;
use Gobl\CRUD\CRUDDeleteAll;
use Gobl\CRUD\CRUDEntityEvent;
use Gobl\CRUD\CRUDRead;
use Gobl\CRUD\CRUDReadAll;
use Gobl\CRUD\CRUDUpdate;
use Gobl\CRUD\CRUDUpdateAll;
use Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface;
use Gobl\DBAL\Column;
use Gobl\ORM\ORMEntity;

/**
 * Class CRUDHandlerDefault.
 */
class CRUDHandlerDefault implements CRUDHandlerInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function onBeforeCreate(CRUDCreate $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeRead(CRUDRead $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdate(CRUDUpdate $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDelete(CRUDDelete $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeReadAll(CRUDReadAll $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdateAll(CRUDUpdateAll $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDeleteAll(CRUDDeleteAll $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeColumnUpdate(CRUDColumnUpdate $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldWritePkColumn(Column $column, mixed $value): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldWritePrivateColumn(Column $column, mixed $value): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function autoFillCreateForm(CRUDCreate $action): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function autoFillUpdateFormAndFilters(CRUDUpdateAll|CRUDUpdate $action): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onEntityEvent(ORMEntity $entity, CRUDEntityEvent $event): void
	{
	}
}
