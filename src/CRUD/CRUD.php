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

use Gobl\CRUD\Enums\EntityEventType;
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
use Gobl\CRUD\Events\EntityEvent;
use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Column;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMTableQuery;

/**
 * Class CRUD.
 */
class CRUD
{
	private Table $table;
	private string $event_channel;
	private string $message = 'OK';
	private array $debug;

	/**
	 * CRUD constructor.
	 *
	 * @param Table $table the target table
	 */
	public function __construct(Table $table)
	{
		$this->table         = $table;
		$this->event_channel = $table->getFullName();

		$this->debug = [
			'_table' => $table->getName(),
		];
	}

	/**
	 * Gets latest message.
	 *
	 * @return string
	 */
	public function getMessage(): string
	{
		return $this->message;
	}

	/**
	 * Create assertion.
	 *
	 * @param array $form
	 *
	 * @return BeforeCreateFlush
	 *
	 * @throws CRUDException
	 */
	public function assertCreate(array $form): BeforeCreateFlush
	{
		$action = new BeforeCreate($this->table, $form);

		if (!$this->authorize($action, true)) {
			throw new CRUDException($action, $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		$this->checkFormColumnsForCreate($action);

		return (new BeforeCreateFlush($this->table, $form))->dispatch(null, $this->event_channel);
	}

	/**
	 * Read assertion.
	 *
	 * @param ORMTableQuery $filters
	 *
	 * @return BeforeRead
	 *
	 * @throws CRUDException
	 */
	public function assertRead(ORMTableQuery $filters): BeforeRead
	{
		$action = new BeforeRead($this->table, $filters);

		if (!$this->authorize($action, true)) {
			throw new CRUDException($action, $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return $action;
	}

	/**
	 * Read all assertion.
	 *
	 * @param ORMTableQuery $filters
	 *
	 * @return BeforeReadAll
	 *
	 * @throws CRUDException
	 */
	public function assertReadAll(ORMTableQuery $filters): BeforeReadAll
	{
		$action = new BeforeReadAll($this->table, $filters);

		if (!$this->authorize($action, true)) {
			throw new CRUDException($action, $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return $action;
	}

	/**
	 * Update assertion.
	 *
	 * @param ORMTableQuery $filters
	 * @param array         $form
	 *
	 * @return BeforeUpdateFlush
	 *
	 * @throws CRUDException
	 */
	public function assertUpdate(ORMTableQuery $filters, array $form): BeforeUpdateFlush
	{
		$action = new BeforeUpdate($this->table, $filters, $form);

		if (!$this->authorize($action, true)) {
			throw new CRUDException($action, $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		$this->checkFormColumnsForUpdate($action);

		return (new BeforeUpdateFlush($this->table, $filters, $form))->dispatch(null, $this->event_channel);
	}

	/**
	 * Update all assertion.
	 *
	 * @param ORMTableQuery $filters
	 * @param array         $form
	 *
	 * @return BeforeUpdateAllFlush
	 *
	 * @throws CRUDException
	 */
	public function assertUpdateAll(ORMTableQuery $filters, array $form): BeforeUpdateAllFlush
	{
		$action = new BeforeUpdateAll($this->table, $filters, $form);

		if (!$this->authorize($action, true)) {
			throw new CRUDException($action, $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		$this->checkFormColumnsForUpdate($action);

		return (new BeforeUpdateAllFlush($this->table, $filters, $form))->dispatch(null, $this->event_channel);
	}

	/**
	 * Delete assertion.
	 *
	 * @param ORMTableQuery $filters
	 *
	 * @return BeforeDeleteFlush
	 *
	 * @throws CRUDException
	 */
	public function assertDelete(ORMTableQuery $filters): BeforeDeleteFlush
	{
		$action = new BeforeDelete($this->table, $filters);

		if (!$this->authorize($action, true)) {
			throw new CRUDException($action, $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return (new BeforeDeleteFlush($this->table, $filters))->dispatch(null, $this->event_channel);
	}

	/**
	 * Delete all assertion.
	 *
	 * @param ORMTableQuery $filters
	 *
	 * @return BeforeDeleteAllFlush
	 *
	 * @throws CRUDException
	 */
	public function assertDeleteAll(ORMTableQuery $filters): BeforeDeleteAllFlush
	{
		$action = new BeforeDeleteAll($this->table, $filters);

		if (!$this->authorize($action, false)) {
			throw new CRUDException($action, $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return (new BeforeDeleteAllFlush($this->table, $filters))->dispatch(null, $this->event_channel);
	}

	/**
	 * Dispatches entity events.
	 *
	 * @param ORMEntity       $entity
	 * @param EntityEventType $event_type
	 *
	 * @return EntityEvent
	 */
	public function dispatchEntityEvent(ORMEntity $entity, EntityEventType $event_type): EntityEvent
	{
		$event = match ($event_type) {
			EntityEventType::AFTER_CREATE  => new AfterEntityCreation($entity),
			EntityEventType::AFTER_READ    => new AfterEntityRead($entity),
			EntityEventType::BEFORE_UPDATE => new BeforeEntityUpdate($entity),
			EntityEventType::AFTER_UPDATE  => new AfterEntityUpdate($entity),
			EntityEventType::BEFORE_DELETE => new BeforeEntityDeletion($entity),
			EntityEventType::AFTER_DELETE  => new AfterEntityDeletion($entity),
		};

		return $event->dispatch(static function ($listener) use ($entity, $event): void {
			$listener($entity, $event);
		}, $this->event_channel);
	}

	/**
	 * Dispatches the given action for authorization.
	 *
	 * @param CRUDAction $action
	 * @param bool       $default
	 *
	 * @return bool
	 */
	private function authorize(CRUDAction $action, bool $default): bool
	{
		$allowed = $default;

		$action->dispatch(static function ($listener) use ($action, &$allowed): void {
			$allowed = $listener($action);

			if (!$allowed) {
				$action->stopPropagation();
			}
		}, $this->event_channel);

		return $allowed;
	}

	/**
	 * Checks columns values for create.
	 *
	 * @param BeforeCreate $create_action
	 *
	 * @throws CRUDException
	 */
	private function checkFormColumnsForCreate(BeforeCreate $create_action): void
	{
		$form = $create_action->getForm();

		foreach ($form as $field => $value) {
			if ($this->table->hasColumn($field)) {
				$column = $this->table->getColumnOrFail($field);

				$this->checkForColumnWrite($column, $form, $field, false);

				if (null !== $value) {
					$type = $column->getType();
					if ($type->isAutoIncremented()) {
						$debug            = $this->debug;
						$debug['field']   = $field;
						$debug['_why']    = 'column_is_auto_incremented';
						$debug['_column'] = $column->getFullName();

						throw new CRUDException('GOBL_COLUMN_WRITE_REFUSED', $debug);
					}

					$this->checkForPKColumnWrite($column, $form, $field, false);
				}
			}
		}
	}

	/**
	 * Checks if the given column can be written.
	 *
	 * @throws CRUDException
	 */
	private function checkForColumnWrite(Column $column, array $form, string $field, bool $updating): void
	{
		if ($column->isPrivate()) {
			$action = new BeforePrivateColumnWrite($this->table, $column, $form, $updating);

			if (!$this->authorize($action, false)) {
				$debug            = $this->debug;
				$debug['field']   = $field;
				$debug['_why']    = 'column_is_private';
				$debug['_column'] = $column->getFullName();

				throw new CRUDException($action, $debug);
			}
		}

		if ($column->isSensitive()) {
			$action = new BeforeSensitiveColumnWrite($this->table, $column, $form, $updating);

			if (!$this->authorize($action, false)) {
				$debug            = $this->debug;
				$debug['field']   = $field;
				$debug['_why']    = 'column_is_sensitive';
				$debug['_column'] = $column->getFullName();

				throw new CRUDException($action, $debug);
			}
		}
	}

	/**
	 * Checks if the given column is part of the primary key and can be written.
	 *
	 * @throws CRUDException
	 */
	private function checkForPKColumnWrite(Column $column, array $form, string $field, bool $updating): void
	{
		if ($this->table->isPartOfPrimaryKey($column)) {
			$action = new BeforePKColumnWrite($this->table, $column, $form, $updating);

			if (!$this->authorize($action, false)) {
				$debug            = $this->debug;
				$debug['field']   = $field;
				$debug['_why']    = 'column_is_part_of_pk';
				$debug['_column'] = $column->getFullName();

				throw new CRUDException($action, $debug);
			}
		}
	}

	/**
	 * Checks columns values for update.
	 *
	 * @param BeforeUpdate|BeforeUpdateAll $base_action
	 *
	 * @throws CRUDException
	 */
	private function checkFormColumnsForUpdate(BeforeUpdate|BeforeUpdateAll $base_action): void
	{
		$form = $base_action->getForm();

		foreach ($form as $field => $value) {
			if ($this->table->hasColumn($field)) {
				$column = $this->table->getColumnOrFail($field);

				$this->checkForColumnWrite($column, $form, $field, true);
				$this->checkForPKColumnWrite($column, $form, $field, true);

				$column_update_action = new BeforeColumnUpdate($this->table, $column, $base_action->getForm());

				if (!$this->authorize($column_update_action, true)) {
					$debug            = $this->debug;
					$debug['field']   = $field;
					$debug['_why']    = 'column_update_rejected';
					$debug['_column'] = $column->getFullName();

					throw new CRUDException($column_update_action, $debug);
				}
			}
		}
	}
}
