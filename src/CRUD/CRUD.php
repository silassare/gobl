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

	use Gobl\CRUD\Exceptions\CRUDException;
	use Gobl\DBAL\Column;
	use Gobl\DBAL\Table;

	/**
	 * Class CRUD
	 *
	 * @package Gobl\CRUD
	 */
	class CRUD
	{
		const CREATE               = 'create';
		const READ                 = 'read';
		const UPDATE               = 'update';
		const DELETE               = 'delete';
		const CREATE_BULK          = 'create_bulk';
		const READ_ALL             = 'read_all';
		const UPDATE_ALL           = 'update_all';
		const DELETE_ALL           = 'delete_all';
		const READ_AS_RELATION     = 'read_as_relation';
		const READ_ALL_AS_RELATION = 'read_all_as_relation';
		const COLUMN_UPDATE        = 'column_update';

		//
		// |----------------------------------------------------------------------------
		// | options          | values                                  | description
		// |----------------------------------------------------------------------------
		// | enable           | true|false             					| enable or disable the action
		// | enable_private   | true|false             					| to enable private column update
		// | enable_pk        | true|false             					| to enable primary key column update
		// | by               | admin|user|anybody|assert_callable_name | who can access a table or update a column
		// | if               | assert_callable_name					| who can access a table entity, use this to validate ownership of an entity in the table
		// | auto_value       | value_callable_name						|
		// | auto_value_force | true|false								|
		// | error            | string									|
		// | success          | string									|
		// |----------------------------------------------------------------------------

		private static $default_table_options = [
			CRUD::CREATE               => [
				"enable"  => true,
				"by"      => "admin",
				"error"   => "CREATE_ERROR",
				"success" => "CREATED"
			],
			CRUD::READ                 => [
				"enable"  => true,
				"by"      => "admin",
				"if"      => "anybody",
				"error"   => "READ_ERROR",
				"success" => "OK"
			],
			CRUD::UPDATE               => [
				"enable"  => true,
				"by"      => "admin",
				"if"      => "anybody",
				"error"   => "UPDATE_ERROR",
				"success" => "UPDATED"
			],
			CRUD::DELETE               => [
				"enable"  => true,
				"by"      => "admin",
				"if"      => "anybody",
				"error"   => "DELETE_ERROR",
				"success" => "DELETED"
			],
			CRUD::CREATE_BULK          => [
				"enable"  => true,
				"by"      => "admin",
				"error"   => "CREATE_ERROR",
				"success" => "CREATED"
			],
			CRUD::READ_ALL             => [
				"enable"  => true,
				"by"      => "admin",
				"error"   => "READ_ERROR",
				"success" => "OK"
			],
			CRUD::UPDATE_ALL           => [
				"enable"  => true,
				"by"      => "admin",
				"error"   => "UPDATE_ERROR",
				"success" => "UPDATED"
			],
			CRUD::DELETE_ALL           => [
				"enable"  => true,
				"by"      => "admin",
				"error"   => "DELETE_ERROR",
				"success" => "DELETED"
			],
			CRUD::READ_AS_RELATION     => [
				"enable"  => true,
				"by"      => "admin",
				"error"   => "READ_ERROR",
				"success" => "OK"
			],
			CRUD::READ_ALL_AS_RELATION => [
				"enable"  => true,
				"by"      => "admin",
				"error"   => "READ_ERROR",
				"success" => "OK"
			]
		];

		private static $default_column_options = [
			"enable"           => true,
			"enable_private"   => false,
			"enable_pk"        => false,
			"by"               => "anybody",
			"error"            => "COLUMNS_UPDATE_ERROR",
			"success"          => "OK",
			"auto_value"       => null,
			"auto_value_force" => false
		];

		/**
		 * @var \Gobl\DBAL\Table
		 */
		private $table;
		/**
		 * @var array
		 */
		private $table_crud_rules;
		/**
		 * @var callable[]
		 */
		private static $assert_callable_map = [];
		/**
		 * @var callable[]
		 */
		private static $value_callable_map = [];

		/**
		 * @var string
		 */
		private $message = "OK";
		/**
		 * @var bool
		 */
		private $as_relation;

		/**
		 * CRUD constructor.
		 *
		 * @param \Gobl\DBAL\Table $table
		 * @param bool             $as_relation
		 */
		public function __construct(Table $table, $as_relation = false)
		{
			$this->table       = $table;
			$this->as_relation = $as_relation;

			$options = $table->getOptions();

			if (isset($options['crud']['table']) AND is_array($options['crud']['table'])) {
				$crud_rules = array_replace_recursive(self::$default_table_options, $options['crud']['table']);
				foreach ($crud_rules as $type => $value) {
					if (!is_array($value)) {
						$crud_rules[$type]           = self::$default_table_options[$type];
						$crud_rules[$type]["enable"] = boolval($value);
					}
				}
				$this->table_crud_rules = $crud_rules;
			} else {
				$this->table_crud_rules = self::$default_table_options;
			}
		}

		/**
		 * @return string
		 */
		public function getMessage()
		{
			return $this->message;
		}

		/**
		 * Returns column CRUD rules.
		 *
		 * @param \Gobl\DBAL\Column $column
		 *
		 * @return array
		 */
		private function getColumnCRUDRules(Column $column)
		{
			$options = $this->table->getOptions();
			$name    = $column->getName();

			if (isset($options['crud']['columns'][$name])) {
				$value = $options['crud']['columns'][$name];
				if (!is_array($value)) {
					$crud_rules           = self::$default_column_options;
					$crud_rules["enable"] = boolval($value);
					return $crud_rules;
				}

				return array_replace_recursive(self::$default_column_options, $value);
			} else {
				return self::$default_column_options;
			}
		}

		/**
		 * Assert if a given action can be authorized on the current table.
		 *
		 * @param \Gobl\CRUD\CRUDBase $action
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		private function assertTableAccess(CRUDBase $action)
		{
			$type  = $action->getType();
			$rules = $this->table_crud_rules[$type];

			$debug = [
				'on'     => 'table',
				'table'  => $this->table->getName(),
				'action' => $type,
				'rules'  => $rules
			];

			if (!$rules["enable"]) {
				$debug["why"] = ['enable' => $rules["enable"]];
				throw new CRUDException($rules["error"], [], $debug);
			}

			$by     = $rules['by'];
			$by     = empty($by) ? self::$default_table_options[$type]["by"] : $by;
			$c_list = explode("|", $by);

			$action->setSuccess($rules["success"])
				   ->setError($rules['error']);

			foreach ($c_list as $c) {
				$callable = self::assertion($c);

				$result = call_user_func($callable, $action);

				if (!$result) {
					$debug["why"] = ['by' => $c, "in" => $by];
					throw new CRUDException($action->getError(), [], $debug);
				}
			}

			$this->message = $action->getSuccess();
		}

		/**
		 * @param string $type
		 * @param mixed  $entity
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		private function assertEntity($type, $entity)
		{
			$debug = [
				'on'     => 'entity',
				'table'  => $this->table->getName(),
				'action' => $type
			];

			$rules = $this->table_crud_rules[$type];

			$_if    = $rules['if'];
			$_if    = empty($_if) ? self::$default_table_options[$type]["if"] : $_if;
			$c_list = explode("|", $_if);

			foreach ($c_list as $c) {
				$callable = self::assertion($c);
				$result   = call_user_func($callable, $entity);
				if (!$result) {
					$debug['why'] = ['if' => $c, "in" => $_if];
					throw new CRUDException($rules['error'], [], $debug);
				}
			}
		}

		/**
		 * Assert if a given action can be authorized on a given column.
		 *
		 * @param \Gobl\DBAL\Column   $column
		 * @param \Gobl\CRUD\CRUDBase $action
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		private function assertColumn(Column $column, CRUDBase $action)
		{
			$rules      = self::getColumnCRUDRules($column);
			$error_data = [$column->getFullName()];
			$debug      = [
				'table'  => $this->table->getName(),
				'column' => $column->getName(),
				'action' => $action->getType(),
				'rules'  => $rules
			];

			if (!$rules["enable_private"] AND $column->isPrivate()) {
				$debug["why"] = ['column' => "is_private"];
				throw new CRUDException($rules["error"], $error_data, $debug);
			}

			if (!$rules["enable_pk"] AND $this->table->isPartOfPrimaryKey($column)) {
				$debug["why"] = ['column' => "pk"];
				throw new CRUDException($rules["error"], $error_data, $debug);
			}

			if (!$rules["enable"]) {
				$debug["why"] = ['enable' => $rules["enable"]];
				throw new CRUDException($rules["error"], $error_data, $debug);
			}

			$by     = $rules['by'];
			$by     = empty($by) ? self::$default_column_options["by"] : $by;
			$c_list = explode("|", $by);

			$action->setSuccess($rules["success"])
				   ->setError($rules['error']);

			foreach ($c_list as $c) {
				$callable = self::assertion($c);
				$result   = call_user_func($callable, $action);

				if (!$result) {
					$debug['why'] = ['by' => $c, "in" => $by];
					throw new CRUDException($action->getError(), $error_data, $debug);
				}
			}
		}

		/**
		 * @param array $form
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		private function assertColumnUpdate(array &$form)
		{
			foreach ($form as $column_name => $value) {
				if ($this->table->hasColumn($column_name)) {
					$column = $this->table->getColumn($column_name);
					$assert = new CRUDColumnUpdate($this->table, $column, $form);

					$this->assertColumn($column, $assert);

					$form = $assert->getForm();
				}
			}
		}

		/**
		 * @param array $form
		 *
		 * @return \Gobl\CRUD\CRUD
		 * @throws \Exception
		 */
		private function fillColumnsAutoValue(array &$form)
		{
			$columns = $this->table->getColumns();
			foreach ($columns as $column) {
				$crud_rules  = self::getColumnCRUDRules($column);
				$column_name = $column->getFullName();
				$auto_value  = $crud_rules['auto_value'];

				if (!empty($auto_value) AND ($crud_rules["auto_value_force"] OR !isset($form[$column_name]))) {
					$callable = $this->autoValue($auto_value);
					$value    = call_user_func_array($callable, [$column, $form, $this->table]);

					$form[$column_name] = $value;
				}
			}

			return $this;
		}

		/**
		 * @param array $form
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function assertCreate(array &$form)
		{
			$this->fillColumnsAutoValue($form);

			$assert = new CRUDCreate($this->table, $form);

			$this->assertTableAccess($assert);

			$form = $assert->getForm();
		}

		/**
		 * @param array $form_list
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Exception
		 */
		public function assertCreateMultiple(array &$form_list)
		{
			foreach ($form_list as &$form) {
				$this->fillColumnsAutoValue($form);
			}

			$assert = new CRUDCreateBulk($this->table, $form_list);

			$this->assertTableAccess($assert);

			$form_list = $assert->getFormList();
		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertRead(array &$filters)
		{
			$assert = $this->as_relation ? new CRUDReadAsRelation($this->table, $filters) : new CRUDRead($this->table, $filters);

			$this->assertTableAccess($assert);

			$filters = $assert->getFilters();
		}

		/**
		 * @param mixed $entity
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertReadEntity($entity)
		{
			$this->assertEntity(CRUD::READ, $entity);
		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertReadAll(array &$filters)
		{
			$assert = $this->as_relation ? new CRUDReadAllAsRelation($this->table, $filters) : new CRUDReadAll($this->table, $filters);

			$this->assertTableAccess($assert);

			$filters = $assert->getFilters();
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
			$assert = new CRUDUpdate($this->table, $filters, $form);

			$this->assertTableAccess($assert);

			$filters = $assert->getFilters();
			$form    = $assert->getForm();

			$this->assertColumnUpdate($form);
		}

		/**
		 * @param mixed $entity
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertUpdateEntity($entity)
		{
			$this->assertEntity(CRUD::UPDATE, $entity);
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
			$this->fillColumnsAutoValue($form);

			$assert = new CRUDUpdateAll($this->table, $filters, $form);

			$this->assertTableAccess($assert);
			$filters = $assert->getFilters();
			$form    = $assert->getForm();

			$this->assertColumnUpdate($form);

		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertDelete(array &$filters)
		{
			$assert = new CRUDDelete($this->table, $filters);

			$this->assertTableAccess($assert);

			$filters = $assert->getFilters();
		}

		/**
		 * @param mixed $entity
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertDeleteEntity($entity)
		{
			$this->assertEntity(CRUD::DELETE, $entity);
		}

		/**
		 * @param array $filters
		 *
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function assertDeleteAll(array &$filters)
		{
			$assert = new CRUDDeleteAll($this->table, $filters);

			$this->assertTableAccess($assert);

			$filters = $assert->getFilters();
		}

		/**
		 * Fake anybody assertion.
		 *
		 * @return bool
		 */
		public static function anybodyAssertion()
		{
			return true;
		}

		/**
		 * Returns or define assertion callable.
		 *
		 * @param string        $name
		 * @param callable|null $callable
		 *
		 * @return callable
		 * @throws \Exception
		 */
		public static function assertion($name, callable $callable = null)
		{
			if (!is_null($callable)) {
				if (isset(self::$assert_callable_map[$name])) {
					throw new \Exception(sprintf('can\'t override CRUD assertion "%s".', $name));
				}

				if (!is_callable($callable)) {
					throw new \InvalidArgumentException(sprintf('CRUD assertion "%s" is not a valid callable.', $name));
				}

				self::$assert_callable_map[$name] = $callable;
			} else {

				if ($name === "anybody") {
					return [CRUD::class, "anybodyAssertion"];
				}

				if (!isset(self::$assert_callable_map[$name])) {
					throw new \Exception(sprintf('undefined CRUD assertion "%s".', $name));
				}
			}

			return self::$assert_callable_map[$name];
		}

		/**
		 * Returns or define auto value callable.
		 *
		 * @param string        $name
		 * @param callable|null $callable
		 *
		 * @return callable
		 * @throws \Exception
		 */
		public static function autoValue($name, callable $callable = null)
		{
			if (!is_null($callable)) {
				if (isset(self::$value_callable_map[$name])) {
					throw new \Exception(sprintf('can\'t override CRUD auto value "%s".', $name));
				}

				if (!is_callable($callable)) {
					throw new \InvalidArgumentException(sprintf('CRUD auto value "%s" is not a valid callable.', $name));
				}

				self::$value_callable_map[$name] = $callable;
			} else {
				if (!isset(self::$value_callable_map[$name])) {
					throw new \Exception(sprintf('undefined CRUD auto value "%s".', $name));
				}

			}

			return self::$value_callable_map[$name];
		}
	}