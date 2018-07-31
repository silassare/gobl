<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
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

	interface CRUDHandlerInterface
	{
		/**
		 * Called to allow CREATE action on a table
		 *
		 * @param \Gobl\CRUD\CRUDCreate $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeCreate(CRUDCreate $action);

		/**
		 * Called when an entity is added
		 *
		 * @param mixed $entity
		 */
		public function onAfterCreateEntity($entity);

		/**
		 * Called to allow READ action on a table
		 *
		 * @param \Gobl\CRUD\CRUDRead $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeRead(CRUDRead $action);

		/**
		 * Called when we read an entity
		 *
		 * @param mixed $entity
		 */
		public function onAfterReadEntity($entity);

		/**
		 * Called to allow UPDATE action on a table
		 *
		 * @param \Gobl\CRUD\CRUDUpdate $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeUpdate(CRUDUpdate $action);

		/**
		 * Called before an entity is updated
		 *
		 * PS: You can run your own logic, verify ownership,
		 * or other right on the entity
		 *
		 * @param mixed $entity
		 */
		public function onBeforeUpdateEntity($entity);

		/**
		 * Called after an entity is updated
		 *
		 * @param mixed $entity
		 */
		public function onAfterUpdateEntity($entity);

		/**
		 * Called to allow DELETE action on a table
		 *
		 * @param \Gobl\CRUD\CRUDDelete $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeDelete(CRUDDelete $action);

		/**
		 * Called before an entity is deleted
		 *
		 * PS: You can run your own logic, verify ownership,
		 * or other right on the entity
		 *
		 * @param mixed $entity
		 */
		public function onBeforeDeleteEntity($entity);

		/**
		 * Called after an entity is deleted
		 *
		 * @param mixed $entity
		 */
		public function onAfterDeleteEntity($entity);

		/**
		 * Called to allow READ_ALL action on a table
		 *
		 * @param \Gobl\CRUD\CRUDReadAll $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeReadAll(CRUDReadAll $action);

		/**
		 * Called to allow UPDATE_ALL action on a table
		 *
		 * @param \Gobl\CRUD\CRUDUpdateAll $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeUpdateAll(CRUDUpdateAll $action);

		/**
		 * Called to allow DELETE_ALL action on a table
		 *
		 * @param \Gobl\CRUD\CRUDDeleteAll $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeDeleteAll(CRUDDeleteAll $action);

		/**
		 * Called to allow COLUMN_UPDATE action on a table
		 *
		 * PS: You can filter who can update a column
		 * or when a column can be updated
		 *
		 * @param \Gobl\CRUD\CRUDColumnUpdate $action
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function onBeforeColumnUpdate(CRUDColumnUpdate $action);

		/**
		 * Called to allow write of a column that is the primary key or is in primary key
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function shouldWritePkColumn();

		/**
		 * Called to allow write of a column that is private
		 *
		 * @return boolean true to allow or false to reject
		 */
		public function shouldWritePrivateColumn();

		/**
		 * Called when creating, to let you complete the form before it goes into database
		 *
		 * PS: any column that is private or primary key
		 *     should not be added before a call to this
		 *
		 * @param array $form
		 */
		public function autoFillCreateForm(array &$form);
	}