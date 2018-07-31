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
	use Gobl\CRUD\CRUDReadAllAsRelation;
	use Gobl\CRUD\CRUDReadAsRelation;
	use Gobl\CRUD\CRUDUpdate;
	use Gobl\CRUD\CRUDUpdateAll;

	interface CRUDHandlerInterface
	{
		/**
		 * @param \Gobl\CRUD\CRUDCreate $action
		 *
		 * @return boolean
		 */
		public function onBeforeCreate(CRUDCreate $action);

		/**
		 * @param mixed $entity
		 */
		public function onAfterCreate($entity);

		/**
		 * @param \Gobl\CRUD\CRUDRead $action
		 *
		 * @return boolean
		 */
		public function onBeforeRead(CRUDRead $action);

		/**
		 * @param mixed $entity
		 */
		public function onAfterRead($entity);

		/**
		 * @param \Gobl\CRUD\CRUDUpdate $action
		 *
		 * @return boolean
		 */
		public function onBeforeUpdate(CRUDUpdate $action);

		/**
		 * @param mixed $entity
		 */
		public function onBeforeUpdateEntity($entity);

		/**
		 * @param mixed $entity
		 */
		public function onAfterUpdate($entity);

		/**
		 * @param \Gobl\CRUD\CRUDDelete $action
		 *
		 * @return boolean
		 */
		public function onBeforeDelete(CRUDDelete $action);

		/**
		 * @param mixed $entity
		 */
		public function onBeforeDeleteEntity($entity);

		/**
		 * @param mixed $entity
		 */
		public function onAfterDelete($entity);

		/**
		 * @param \Gobl\CRUD\CRUDReadAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeReadAll(CRUDReadAll $action);

		/**
		 * @param \Gobl\CRUD\CRUDUpdateAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeUpdateAll(CRUDUpdateAll $action);

		/**
		 * @param \Gobl\CRUD\CRUDDeleteAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeDeleteAll(CRUDDeleteAll $action);

		/**
		 * @param \Gobl\CRUD\CRUDReadAsRelation $action
		 *
		 * @return boolean
		 */
		public function onBeforeReadAsRelation(CRUDReadAsRelation $action);

		/**
		 * @param \Gobl\CRUD\CRUDReadAllAsRelation $action
		 *
		 * @return boolean
		 */
		public function onBeforeReadAllAsRelation(CRUDReadAllAsRelation $action);

		/**
		 * @param \Gobl\CRUD\CRUDColumnUpdate $action
		 *
		 * @return boolean
		 */
		public function onBeforeColumnUpdate(CRUDColumnUpdate $action);

		/**
		 * @return boolean
		 */
		public function shouldWritePkColumn();

		/**
		 * @return boolean
		 */
		public function shouldWritePrivateColumn();

		/**
		 * @param array $form
		 */
		public function autoFillCreateForm(array &$form);
	}