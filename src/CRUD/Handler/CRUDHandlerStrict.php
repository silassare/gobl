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

	class CRUDHandlerStrict implements CRUDHandlerInterface
	{
		/**
		 * CRUDHandlerStrict constructor.
		 */
		public function __construct() { }

		/**
		 * @param \Gobl\CRUD\CRUDCreate $action
		 *
		 * @return boolean
		 */
		public function onBeforeCreate(CRUDCreate $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDRead $action
		 *
		 * @return boolean
		 */
		public function onBeforeRead(CRUDRead $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDUpdate $action
		 *
		 * @return boolean
		 */
		public function onBeforeUpdate(CRUDUpdate $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDDelete $action
		 *
		 * @return boolean
		 */
		public function onBeforeDelete(CRUDDelete $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDReadAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeReadAll(CRUDReadAll $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDUpdateAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeUpdateAll(CRUDUpdateAll $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDDeleteAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeDeleteAll(CRUDDeleteAll $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDReadAsRelation $action
		 *
		 * @return boolean
		 */
		public function onBeforeReadAsRelation(CRUDReadAsRelation $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDReadAllAsRelation $action
		 *
		 * @return boolean
		 */
		public function onBeforeReadAllAsRelation(CRUDReadAllAsRelation $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDColumnUpdate $action
		 *
		 * @return boolean
		 */
		public function onBeforeColumnUpdate(CRUDColumnUpdate $action)
		{
			return false;
		}

		/**
		 * @param mixed $entity
		 */
		public function onAfterCreateEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onAfterReadEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onBeforeUpdateEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onAfterUpdateEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onBeforeDeleteEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onAfterDeleteEntity($entity) { }

		/**
		 * @return boolean
		 */
		public function shouldWritePkColumn()
		{
			return false;
		}

		/**
		 * @return boolean
		 */
		public function shouldWritePrivateColumn()
		{
			return false;
		}

		/**
		 * @param array $form
		 */
		public function autoFillCreateForm(array &$form) { }
	}