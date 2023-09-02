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

namespace Gobl\CRUD;

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
use Gobl\CRUD\Events\BeforeUpdate;
use Gobl\CRUD\Events\BeforeUpdateAll;
use Gobl\CRUD\Events\BeforeUpdateAllFlush;
use Gobl\CRUD\Events\BeforeUpdateFlush;
use Gobl\CRUD\Interfaces\CRUDEventListenerInterface;
use Gobl\ORM\ORM;
use PHPUtils\Events\Event;
use PHPUtils\Events\EventManager;

/**
 * Class CRUDEventProducer.
 *
 * @template TEntity of \Gobl\ORM\ORMEntity
 */
class CRUDEventProducer
{
	protected string $event_channel;

	/**
	 * CRUDEventProducer constructor.
	 *
	 * @param string $namespace
	 * @param string $table_name
	 */
	public function __construct(string $namespace, string $table_name)
	{
		$this->event_channel = ORM::table($namespace, $table_name)
			->getFullName();
	}

	/**
	 * Register a listener for an event.
	 *
	 * @param \Gobl\CRUD\Interfaces\CRUDEventListenerInterface $listener
	 *
	 * @return $this
	 */
	public function listen(CRUDEventListenerInterface $listener): static
	{
		// for each method starting by "on" in the passed listener object
		// call the corresponding method in this object if it exists
		// passing the listener method as argument
		$methods = \get_class_methods($listener);

		foreach ($methods as $method) {
			if (\str_starts_with($method, 'on') && \method_exists($this, $method)) {
				$this->{$method}([$listener, $method]);
			}
		}

		return $this;
	}

	/**
	 * Called to allow CREATE action on a table.
	 *
	 * @param callable(BeforeCreate):bool $listener
	 */
	public function onBeforeCreate(callable $listener): void
	{
		$this->addListener(BeforeCreate::class, $listener);
	}

	/**
	 * Called after we allow CREATE action on a table and before the flush.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be updated though request
	 *     should not be added before a call to this
	 *
	 * @param callable(BeforeCreateFlush):void $listener
	 */
	public function onBeforeCreateFlush(callable $listener): void
	{
		$this->addListener(BeforeCreateFlush::class, $listener);
	}

	/**
	 * Called to allow READ action on a table.
	 *
	 * @param callable(BeforeRead):bool $listener
	 */
	public function onBeforeRead(callable $listener): void
	{
		$this->addListener(BeforeRead::class, $listener);
	}

	/**
	 * Called to allow READ_ALL action on a table.
	 *
	 * @param callable(BeforeReadAll):bool $listener
	 */
	public function onBeforeReadAll(callable $listener): void
	{
		$this->addListener(BeforeReadAll::class, $listener);
	}

	/**
	 * Called to allow UPDATE action on a table.
	 *
	 * @param callable(BeforeUpdate):bool $listener
	 */
	public function onBeforeUpdate(callable $listener): void
	{
		$this->addListener(BeforeUpdate::class, $listener);
	}

	/**
	 * Called after we allow UPDATE action on a table and before the flush.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be updated though request
	 *     should not be added before a call to this
	 *
	 * @param callable(BeforeUpdateFlush):void $listener
	 */
	public function onBeforeUpdateFlush(callable $listener): void
	{
		$this->addListener(BeforeUpdateFlush::class, $listener);
	}

	/**
	 * Called to allow UPDATE_ALL action on a table.
	 *
	 * @param callable(BeforeUpdateAll):bool $listener
	 */
	public function onBeforeUpdateAll(callable $listener): void
	{
		$this->addListener(BeforeUpdateAll::class, $listener);
	}

	/**
	 * Called after we allow UPDATE_ALL action on a table and before the flush.
	 *
	 * PS: any column that is private or part of the primary key
	 *     that can't be updated though request
	 *     should not be added before a call to this
	 *
	 * @param callable(BeforeUpdateAllFlush):void $listener
	 */
	public function onBeforeUpdateAllFlush(callable $listener): void
	{
		$this->addListener(BeforeUpdateAllFlush::class, $listener);
	}

	/**
	 * Called to allow DELETE action on a table.
	 *
	 * @param callable(BeforeDelete):bool $listener
	 */
	public function onBeforeDelete(callable $listener): void
	{
		$this->addListener(BeforeDelete::class, $listener);
	}

	/**
	 * Called after we allow DELETE action on a table and before the flush.
	 *
	 * This is the last chance to modify the entities before they go into database.
	 *
	 * @param callable(BeforeDeleteFlush):void $listener
	 */
	public function onBeforeDeleteFlush(callable $listener): void
	{
		$this->addListener(BeforeDeleteFlush::class, $listener);
	}

	/**
	 * Called to allow DELETE_ALL action on a table.
	 *
	 * @param callable(BeforeDeleteAll):bool $listener
	 */
	public function onBeforeDeleteAll(callable $listener): void
	{
		$this->addListener(BeforeDeleteAll::class, $listener);
	}

	/**
	 * Called after we allow DELETE_ALL action on a table and before the flush.
	 *
	 * This is the last chance to modify the entities before they go into database.
	 *
	 * @param callable(BeforeDeleteAllFlush):void $listener
	 */
	public function onBeforeDeleteAllFlush(callable $listener): void
	{
		$this->addListener(BeforeDeleteAllFlush::class, $listener);
	}

	/**
	 * Called to allow COLUMN_UPDATE action on a table.
	 *
	 * PS: You can filter who can update a column
	 * or when a column can be updated
	 *
	 * @param callable(BeforeColumnUpdate):bool $listener
	 */
	public function onBeforeColumnUpdate(callable $listener): void
	{
		$this->addListener(BeforeColumnUpdate::class, $listener);
	}

	/**
	 * Called to allow write of a column that is the primary key or is part of the primary key.
	 *
	 * @param callable(BeforePKColumnWrite):bool $listener
	 */
	public function onBeforePKColumnWrite(callable $listener): void
	{
		$this->addListener(BeforePKColumnWrite::class, $listener);
	}

	/**
	 * Called to allow write of a column that is private.
	 *
	 * @param callable(BeforePrivateColumnWrite):bool $listener
	 */
	public function onBeforePrivateColumnWrite(callable $listener): void
	{
		$this->addListener(BeforePrivateColumnWrite::class, $listener);
	}

	/**
	 * Called when we read an entity.
	 *
	 * PS: You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param callable(TEntity, AfterEntityRead<TEntity>):void $listener
	 */
	public function onAfterEntityRead(callable $listener): void
	{
		$this->addListener(AfterEntityRead::class, $listener);
	}

	/**
	 * Called before an entity is updated.
	 *
	 * PS: You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param callable(TEntity, BeforeEntityUpdate<TEntity>):void $listener
	 */
	public function onBeforeEntityUpdate(callable $listener): void
	{
		$this->addListener(BeforeEntityUpdate::class, $listener);
	}

	/**
	 * Called after an entity is updated.
	 *
	 * @param callable(TEntity, AfterEntityUpdate<TEntity>):void $listener
	 */
	public function onAfterEntityUpdate(callable $listener): void
	{
		$this->addListener(AfterEntityUpdate::class, $listener);
	}

	/**
	 * Called before an entity is deleted.
	 *
	 * PS: You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param callable(TEntity, BeforeEntityDeletion<TEntity>):void $listener
	 */
	public function onBeforeEntityDeletion(callable $listener): void
	{
		$this->addListener(BeforeEntityDeletion::class, $listener);
	}

	/**
	 * Called after an entity is deleted.
	 *
	 * @param callable(TEntity, AfterEntityDeletion<TEntity>):void $listener
	 */
	public function onAfterEntityDeletion(callable $listener): void
	{
		$this->addListener(AfterEntityDeletion::class, $listener);
	}

	/**
	 * Called when an entity is created.
	 *
	 * @param callable(TEntity, AfterEntityCreation<TEntity>):void $listener
	 */
	public function onAfterEntityCreation(callable $listener): void
	{
		$this->addListener(AfterEntityCreation::class, $listener);
	}

	/**
	 * Register a listener for an event.
	 *
	 * @param class-string<\PHPUtils\Events\Interfaces\EventInterface> $event
	 * @param callable                                                 $listener
	 */
	protected function addListener(string $event, callable $listener): void
	{
		EventManager::listen($event, $listener, Event::RUN_DEFAULT, $this->event_channel);
	}
}
