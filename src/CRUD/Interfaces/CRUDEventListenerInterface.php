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

namespace Gobl\CRUD\Interfaces;

use Gobl\CRUD\Events\AfterEntityCreation;
use Gobl\CRUD\Events\AfterEntityDeletion;
use Gobl\CRUD\Events\AfterEntityRead;
use Gobl\CRUD\Events\AfterEntityUpdate;
use Gobl\CRUD\Events\BeforeColumnUpdate;
use Gobl\CRUD\Events\BeforeCreate;
use Gobl\CRUD\Events\BeforeCreateFlush;
use Gobl\CRUD\Events\BeforeDelete;
use Gobl\CRUD\Events\BeforeDeleteAll;
use Gobl\CRUD\Events\BeforeDeleteAllFlush;
use Gobl\CRUD\Events\BeforeDeleteFlush;
use Gobl\CRUD\Events\BeforeEntityDeletion;
use Gobl\CRUD\Events\BeforeEntityUpdate;
use Gobl\CRUD\Events\BeforePKColumnWrite;
use Gobl\CRUD\Events\BeforePrivateColumnWrite;
use Gobl\CRUD\Events\BeforeRead;
use Gobl\CRUD\Events\BeforeReadAll;
use Gobl\CRUD\Events\BeforeSensitiveColumnWrite;
use Gobl\CRUD\Events\BeforeUpdate;
use Gobl\CRUD\Events\BeforeUpdateAll;
use Gobl\CRUD\Events\BeforeUpdateAllFlush;
use Gobl\CRUD\Events\BeforeUpdateFlush;
use Gobl\ORM\ORMEntity;

/**
 * Interface CRUDEventListenerInterface.
 *
 * @template TEntity of ORMEntity
 */
interface CRUDEventListenerInterface
{
	/**
	 * Called to allow CREATE action on a table.
	 *
	 * @param BeforeCreate $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeCreate(BeforeCreate $action): bool;

	/**
	 * Called after we allow CREATE action on a table and before the flush.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be updated though request
	 *     should not be added before a call to this
	 *
	 * @param BeforeCreateFlush $action
	 */
	public function onBeforeCreateFlush(BeforeCreateFlush $action): void;

	/**
	 * Called to allow READ action on a table.
	 *
	 * @param BeforeRead $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeRead(BeforeRead $action): bool;

	/**
	 * Called to allow READ_ALL action on a table.
	 *
	 * @param BeforeReadAll $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeReadAll(BeforeReadAll $action): bool;

	/**
	 * Called to allow UPDATE action on a table.
	 *
	 * @param BeforeUpdate $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeUpdate(BeforeUpdate $action): bool;

	/**
	 * Called after we allow UPDATE action on a table and before the flush.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be updated though request
	 *     should not be added before a call to this
	 *
	 * @param BeforeUpdateFlush $action
	 */
	public function onBeforeUpdateFlush(BeforeUpdateFlush $action): void;

	/**
	 * Called to allow UPDATE_ALL action on a table.
	 *
	 * @param BeforeUpdateAll $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeUpdateAll(BeforeUpdateAll $action): bool;

	/**
	 * Called after we allow UPDATE_ALL action on a table and before the flush.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be updated though request
	 *     should not be added before a call to this
	 *
	 * @param BeforeUpdateAllFlush $action
	 */
	public function onBeforeUpdateAllFlush(BeforeUpdateAllFlush $action): void;

	/**
	 * Called to allow DELETE action on a table.
	 *
	 * @param BeforeDelete $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeDelete(BeforeDelete $action): bool;

	/**
	 * Called after we allow DELETE action on a table and before the flush.
	 *
	 * @param BeforeDeleteFlush $action
	 */
	public function onBeforeDeleteFlush(BeforeDeleteFlush $action): void;

	/**
	 * Called to allow DELETE_ALL action on a table.
	 *
	 * @param BeforeDeleteAll $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeDeleteAll(BeforeDeleteAll $action): bool;

	/**
	 * Called after we allow DELETE_ALL action on a table and before the flush.
	 *
	 * @param BeforeDeleteAllFlush $action
	 */
	public function onBeforeDeleteAllFlush(BeforeDeleteAllFlush $action): void;

	/**
	 * Called to allow COLUMN_UPDATE action on a table.
	 *
	 * PS: You can filter who can update a column
	 * or when a column can be updated
	 *
	 * @param BeforeColumnUpdate $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeColumnUpdate(BeforeColumnUpdate $action): bool;

	/**
	 * Called to allow write of a column that is the primary key or is part of the primary key.
	 *
	 * @param BeforePKColumnWrite $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforePKColumnWrite(BeforePKColumnWrite $action): bool;

	/**
	 * Called to allow write of a column that is private.
	 *
	 * @param BeforePrivateColumnWrite $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforePrivateColumnWrite(BeforePrivateColumnWrite $action): bool;

	/**
	 * Called to allow write of a column that is sensitive.
	 *
	 * @param BeforeSensitiveColumnWrite $action
	 *
	 * @return bool true to allow or false to reject
	 */
	public function onBeforeSensitiveColumnWrite(BeforeSensitiveColumnWrite $action): bool;

	/**
	 * Called when we read an entity.
	 *
	 * PS: You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @psalm-param TEntity                  $entity
	 * @psalm-param AfterEntityRead<TEntity> $event
	 */
	public function onAfterEntityRead(ORMEntity $entity, AfterEntityRead $event): void;

	/**
	 * Called before an entity is updated.
	 *
	 * PS: You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @psalm-param TEntity                     $entity
	 * @psalm-param BeforeEntityUpdate<TEntity> $event
	 */
	public function onBeforeEntityUpdate(ORMEntity $entity, BeforeEntityUpdate $event): void;

	/**
	 * Called after an entity is updated.
	 *
	 * @psalm-param TEntity                    $entity
	 * @psalm-param AfterEntityUpdate<TEntity> $event
	 */
	public function onAfterEntityUpdate(ORMEntity $entity, AfterEntityUpdate $event): void;

	/**
	 * Called before an entity is deleted.
	 *
	 * PS: You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @psalm-param TEntity                       $entity
	 * @psalm-param BeforeEntityDeletion<TEntity> $event
	 */
	public function onBeforeEntityDeletion(ORMEntity $entity, BeforeEntityDeletion $event): void;

	/**
	 * Called after an entity is deleted.
	 *
	 * @psalm-param TEntity                      $entity
	 * @psalm-param AfterEntityDeletion<TEntity> $event
	 */
	public function onAfterEntityDeletion(ORMEntity $entity, AfterEntityDeletion $event): void;

	/**
	 * Called when an entity is created.
	 *
	 * @psalm-param TEntity                      $entity
	 * @psalm-param AfterEntityCreation<TEntity> $event
	 */
	public function onAfterEntityCreation(ORMEntity $entity, AfterEntityCreation $event): void;
}
