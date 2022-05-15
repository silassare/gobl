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

namespace Gobl\CRUD\Handler\Interfaces;

use Gobl\CRUD\CRUDColumnUpdate;
use Gobl\CRUD\CRUDCreate;
use Gobl\CRUD\CRUDDelete;
use Gobl\CRUD\CRUDDeleteAll;
use Gobl\CRUD\CRUDRead;
use Gobl\CRUD\CRUDReadAll;
use Gobl\CRUD\CRUDUpdate;
use Gobl\CRUD\CRUDUpdateAll;
use Gobl\DBAL\Column;
use Gobl\ORM\ORMEntity;

/**
 * Interface CRUDHandlerInterface.
 */
interface CRUDHandlerInterface
{
	/**
	 * Called to allow CREATE action on a table.
	 *
	 * @param \Gobl\CRUD\CRUDCreate $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeCreate(CRUDCreate $action): bool;

	/**
	 * Called when an entity is created.
	 *
	 * @param \Gobl\ORM\ORMEntity $entity
	 */
	public function onAfterCreateEntity(ORMEntity $entity): void;

	/**
	 * Called to allow READ action on a table.
	 *
	 * @param \Gobl\CRUD\CRUDRead $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeRead(CRUDRead $action): bool;

	/**
	 * Called when we read an entity.
	 *
	 * @param \Gobl\ORM\ORMEntity $entity
	 */
	public function onAfterReadEntity(ORMEntity $entity): void;

	/**
	 * Called to allow UPDATE action on a table.
	 *
	 * @param \Gobl\CRUD\CRUDUpdate $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeUpdate(CRUDUpdate $action): bool;

	/**
	 * Called before an entity is updated.
	 *
	 * PS: You can run your own logic, verify ownership,
	 * or other right on the entity
	 *
	 * @param \Gobl\ORM\ORMEntity $entity
	 */
	public function onBeforeUpdateEntity(ORMEntity $entity): void;

	/**
	 * Called after an entity is updated.
	 *
	 * @param \Gobl\ORM\ORMEntity $entity
	 */
	public function onAfterUpdateEntity(ORMEntity $entity): void;

	/**
	 * Called to allow DELETE action on a table.
	 *
	 * @param \Gobl\CRUD\CRUDDelete $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeDelete(CRUDDelete $action): bool;

	/**
	 * Called before an entity is deleted.
	 *
	 * PS: You can run your own logic, verify ownership,
	 * or other right on the entity
	 *
	 * @param \Gobl\ORM\ORMEntity $entity
	 */
	public function onBeforeDeleteEntity(ORMEntity $entity): void;

	/**
	 * Called after an entity is deleted.
	 *
	 * @param \Gobl\ORM\ORMEntity $entity
	 */
	public function onAfterDeleteEntity(ORMEntity $entity): void;

	/**
	 * Called to allow READ_ALL action on a table.
	 *
	 * @param \Gobl\CRUD\CRUDReadAll $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeReadAll(CRUDReadAll $action): bool;

	/**
	 * Called to allow UPDATE_ALL action on a table.
	 *
	 * @param \Gobl\CRUD\CRUDUpdateAll $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeUpdateAll(CRUDUpdateAll $action): bool;

	/**
	 * Called to allow DELETE_ALL action on a table.
	 *
	 * @param \Gobl\CRUD\CRUDDeleteAll $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeDeleteAll(CRUDDeleteAll $action): bool;

	/**
	 * Called to allow COLUMN_UPDATE action on a table.
	 *
	 * PS: You can filter who can update a column
	 * or when a column can be updated
	 *
	 * @param \Gobl\CRUD\CRUDColumnUpdate $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeColumnUpdate(CRUDColumnUpdate $action): bool;

	/**
	 * Called to allow write of a column that is the primary key or is part of the primary key.
	 *
	 * @return bool true to allow or false to reject
	 */
	public function shouldWritePkColumn(Column $column, mixed $value): bool;

	/**
	 * Called to allow write of a column that is private.
	 *
	 * @return bool true to allow or false to reject
	 */
	public function shouldWritePrivateColumn(Column $column, mixed $value): bool;

	/**
	 * Called when creating, to let you complete the form before it goes into database.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be set though request
	 *     should not be added before a call to this
	 *
	 * @param \Gobl\CRUD\CRUDCreate $action
	 */
	public function autoFillCreateForm(CRUDCreate $action): void;

	/**
	 * Called when updating (single or multiple), to let you complete the form before it goes into database.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be updated though request
	 *     should not be added before a call to this
	 *
	 * @param \Gobl\CRUD\CRUDUpdate|\Gobl\CRUD\CRUDUpdateAll $action
	 */
	public function autoFillUpdateFormAndFilters(CRUDUpdate|CRUDUpdateAll $action): void;
}
