<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\CRUD;

	use Gobl\CRUD\Handler\CRUDHandler;
	use Gobl\CRUD\Handler\CRUDHandlerInterface;
	use Gobl\CRUD\Exceptions\CRUDException;
	use Gobl\DBAL\Table;

	/**
	 * Class CRUD
	 *
	 * @package Gobl\CRUD
	 */
	class CRUD
	{
		const CREATE        = 'create';
		const READ          = 'read';
		const UPDATE        = 'update';
		const DELETE        = 'delete';
		const READ_ALL      = 'read_all';
		const UPDATE_ALL    = 'update_all';
		const DELETE_ALL    = 'delete_all';
		const COLUMN_UPDATE = 'column_update';

		/**
		 * @var array
		 */
		private static $crud_options = [];

		/**
		 * @var \Gobl\DBAL\Table
		 */
		private $table;

		/**
		 * @var \Gobl\CRUD\Handler\CRUDHandlerInterface
		 */
		private $crud_handler;

		/**
		 * @var string
		 */
		private $message = "OK";

		/**
		 * @var array
		 */
		private $debug = [];

		/**
		 * CRUD constructor.
		 *
		 * @param \Gobl\DBAL\Table $table
		 *
		 * @throws \Exception
		 */
		public function __construct(Table $table)
		{
			$this->table = $table;
			$name        = $table->getName();

			if (isset(self::$crud_options[$name])) {
				$handler = self::$crud_options[$name];
				$rc      = new \ReflectionClass($handler);

				if ($rc->implementsInterface(CRUDHandlerInterface::class)) {
					$this->crud_handler = $rc->newInstance();
				} else {
					throw new \Exception(sprintf('Table %s CRUD handler class should implement %s', $name, CRUDHandlerInterface::class));
				}

			} else {
				$this->crud_handler = new CRUDHandler();
			}

			$this->debug = [
				'by'    => get_class($this->crud_handler),
				'table' => $table->getName()
			];
		}

		/**
		 * @return string
		 */
		public function getMessage()
		{
			return $this->message;
		}

		/**
		 * @param $form
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		private function checkFormColumnsForCreate($form)
		{
			$debug = $this->debug;

			foreach ($form as $field => $value) {
				if ($this->table->hasColumn($field)) {
					$debug['column'] = $field;
					$column          = $this->table->getColumn($field);

					if ($column->isPrivate() AND !$this->crud_handler->shouldWritePrivateColumn()) {
						$debug['why'] = 'column_is_private';
						throw new CRUDException("ERROR", [$field], $debug);
					} elseif ($value != null AND $this->table->isPartOfPrimaryKey($column) AND !$this->crud_handler->shouldWritePrivateColumn()) {
						$debug['why'] = 'column_is_part_of_pk';
						throw new CRUDException("ERROR", [$field], $debug);
					}
				}
			}
		}

		/**
		 * @param $form
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		private function checkFormColumnsForUpdate(&$form)
		{
			$debug = $this->debug;

			foreach ($form as $field => $value) {
				if ($this->table->hasColumn($field)) {
					$debug['column'] = $field;
					$column          = $this->table->getColumn($field);
					$action          = new CRUDColumnUpdate($this->table, $column, $form);

					if ($column->isPrivate() AND !$this->crud_handler->shouldWritePrivateColumn()) {
						$debug['why'] = 'column_is_private';
						throw new CRUDException('ERROR', [$field], $debug);
					} elseif ($this->table->isPartOfPrimaryKey($column) AND !$this->crud_handler->shouldWritePrivateColumn()) {
						$debug['why'] = 'column_is_part_of_pk';
						throw new CRUDException('ERROR', [$field], $debug);
					}
					if (!$this->crud_handler->onBeforeColumnUpdate($action)) {
						$debug['why'] = 'column_update_rejected';
						throw new CRUDException($action->getError(), [$field], $debug);
					}

					$form = $action->getForm();
				}
			}
		}

		/**
		 * @param array $form
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function assertCreate(array &$form)
		{
			$action = new CRUDCreate($this->table, $form);

			if (!$this->crud_handler->onBeforeCreate($action)) {
				throw new CRUDException($action->getError(), [], $this->debug);
			}

			$this->checkFormColumnsForCreate($form);

			$form = $action->getForm();

			$this->crud_handler->autoFillCreateForm($form);

			$this->message = $action->getSuccess();
		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertRead(array &$filters)
		{
			$action = new CRUDRead($this->table, $filters);

			if (!$this->crud_handler->onBeforeRead($action)) {
				throw new CRUDException($action->getError(), [], $this->debug);
			}

			$filters = $action->getFilters();

			$this->message = $action->getSuccess();
		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertReadAll(array &$filters)
		{
			$action = new CRUDReadAll($this->table, $filters);

			if (!$this->crud_handler->onBeforeReadAll($action)) {
				throw new CRUDException($action->getError(), [], $this->debug);
			}

			$filters = $action->getFilters();

			$this->message = $action->getSuccess();
		}

		/**
		 * @param array $filters
		 * @param array $form
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function assertUpdate(array &$filters, array &$form)
		{
			$action = new CRUDUpdate($this->table, $filters, $form);

			if (!$this->crud_handler->onBeforeUpdate($action)) {
				throw new CRUDException($action->getError(), [], $this->debug);
			}

			$this->checkFormColumnsForUpdate($form);

			$filters = $action->getFilters();
			$form    = $action->getForm();

			$this->message = $action->getSuccess();
		}

		/**
		 * @param array $filters
		 * @param       $form
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function assertUpdateAll(array &$filters, array &$form)
		{
			$action = new CRUDUpdateAll($this->table, $filters, $form);

			if (!$this->crud_handler->onBeforeUpdateAll($action)) {
				throw new CRUDException($action->getError(), [], $this->debug);
			}

			$this->checkFormColumnsForUpdate($form);

			$filters = $action->getFilters();
			$form    = $action->getForm();

			$this->message = $action->getSuccess();
		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertDelete(array &$filters)
		{
			$action = new CRUDDelete($this->table, $filters);

			if (!$this->crud_handler->onBeforeDelete($action)) {
				throw new CRUDException($action->getError(), [], $this->debug);
			}

			$filters = $action->getFilters();

			$this->message = $action->getSuccess();
		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertDeleteAll(array &$filters)
		{
			$action = new CRUDDeleteAll($this->table, $filters);

			if (!$this->crud_handler->onBeforeDeleteAll($action)) {
				throw new CRUDException($action->getError(), [], $this->debug);
			}

			$filters = $action->getFilters();

			$this->message = $action->getSuccess();
		}

		/**
		 * @return \Gobl\CRUD\Handler\CRUDHandlerInterface
		 */
		public function getHandler()
		{
			return $this->crud_handler;
		}

		/**
		 * @param array $options
		 */
		public static function loadOptions(array $options)
		{
			self::$crud_options = array_merge(self::$crud_options, $options);
		}
	}