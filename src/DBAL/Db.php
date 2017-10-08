<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	/**
	 * Class Db
	 *
	 * @package Gobl\DBAL
	 */
	class Db
	{
		/**
		 * Database tables.
		 *
		 * @var \Gobl\DBAL\Table[]
		 */
		private $tables = [];

		/**
		 * @var array
		 */
		private $tbl_full_name_map = [];

		/**
		 * Relational database management system.
		 *
		 * @var \Gobl\DBAL\RDBMS
		 */
		private $rdbms;

		/**
		 * PDO database connection instance.
		 *
		 * @var \PDO
		 */
		private $db_connection;

		/**
		 * Used to generate bind param id.
		 *
		 * @var int
		 */
		private static $bind_unique_id = 0;

		/**
		 * Db constructor.
		 *
		 * @param \Gobl\DBAL\RDBMS $rdbms
		 */
		public function __construct(RDBMS $rdbms)
		{
			$this->rdbms = $rdbms;
		}

		/**
		 * Db destructor.
		 */
		public function __destruct()
		{
			if (isset($this->rdbms)) {
				$this->db_connection = null;
				$this->rdbms         = null;
			}
		}

		/**
		 * Get the rdbms.
		 *
		 * @return \Gobl\DBAL\RDBMS
		 */
		public function getRDBMS()
		{
			return $this->rdbms;
		}

		/**
		 * Get database connection.
		 *
		 * @return \PDO
		 */
		public function getConnection()
		{
			if (empty($this->db_connection)) {
				$this->db_connection = $this->rdbms->connect();
			}

			return $this->db_connection;
		}

		/**
		 * Executes a given sql query and params.
		 *
		 * @param string     $sql          Your sql query
		 * @param array|null $params       Your sql params
		 * @param array      $params_types Your sql params type
		 *
		 * @return \PDOStatement
		 */
		public function execute($sql, array $params = null, array $params_types = [])
		{
			$stmt = $this->getConnection()
						 ->prepare($sql);

			if ($params !== null) {
				foreach ($params as $key => $value) {
					$param_type = \PDO::PARAM_STR;

					if (isset($params_types[$key])) {
						$param_type = $params_types[$key];
					} elseif (is_int($value)) {
						$param_type = \PDO::PARAM_INT;
					} elseif (is_bool($value)) {
						$param_type = \PDO::PARAM_BOOL;
					} elseif (is_null($value)) {
						$param_type = \PDO::PARAM_NULL;
					}

					$stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $param_type);
				}
			}

			$stmt->execute();

			return $stmt;
		}

		/**
		 * Executes a query and return affected row count.
		 *
		 * @param string     $sql          Your sql query
		 * @param array|null $params       Your sql params
		 * @param array      $params_types Your sql params types
		 *
		 * @return int    Affected row count.
		 */
		private function query($sql, array $params = null, array $params_types = [])
		{
			$stmt = $this->execute($sql, $params, $params_types);

			return $stmt->rowCount();
		}

		/**
		 * Executes select queries.
		 *
		 * @param string     $sql          Your sql select query
		 * @param array|null $params       Your sql select params
		 * @param array      $params_types Your sql params types
		 *
		 * @return \PDOStatement
		 */
		public function select($sql, array $params = null, array $params_types = [])
		{
			return $this->execute($sql, $params, $params_types);
		}

		/**
		 * Executes delete queries.
		 *
		 * @param string     $sql          Your sql select query
		 * @param array|null $params       Your sql select params
		 * @param array      $params_types Your sql params types
		 *
		 * @return int    Affected row count
		 */
		public function delete($sql, array $params = null, array $params_types = [])
		{
			return $this->query($sql, $params, $params_types);
		}

		/**
		 * Executes insert queries.
		 *
		 * @param string     $sql          Your sql select query
		 * @param array|null $params       Your sql select params
		 * @param array      $params_types Your sql params types
		 *
		 * @return int    The last inserted row id
		 */
		public function insert($sql, array $params = null, array $params_types = [])
		{
			$stmt    = $this->execute($sql, $params, $params_types);
			$last_id = $this->getConnection()
							->lastInsertId();

			$stmt->closeCursor();

			return $last_id;
		}

		/**
		 * Executes update queries.
		 *
		 * @param string     $sql          Your sql select query
		 * @param array|null $params       Your sql select params
		 * @param array      $params_types Your sql params types
		 *
		 * @return int    Affected row count
		 */
		public function update($sql, array $params = null, array $params_types = [])
		{
			return $this->query($sql, $params, $params_types);
		}

		/**
		 * Checks if a given string is a column reference.
		 *
		 * @param string $str
		 *
		 * @return bool
		 */
		public static function isColumnReference($str)
		{
			return is_array(self::parseColumnReference($str));
		}

		/**
		 * Parse a column reference.
		 *
		 * @param string $str The column reference
		 *
		 * @return array|null
		 */
		public static function parseColumnReference($str)
		{
			if (is_string($str)) {
				$parts = explode('.', $str);
				if (count($parts) === 2) {
					$clone = ($str[0] === ':') ? false : true;
					$tbl   = ($clone === false) ? substr($parts[0], 1) : $parts[0];
					$col   = $parts[1];

					return [
						'clone'  => $clone,
						'table'  => $tbl,
						'column' => $col
					];
				}
			}

			return null;
		}

		/**
		 * Resolve reference column.
		 *
		 * You don't need to define param circle
		 * it is for internal use only
		 * to prevent cyclic search that may cause infinite loop
		 *
		 * @param string $ref_name The reference column name
		 * @param array  $tables   Tables config array
		 * @param array  $circle   Contains all references, to prevent infinite loop
		 *
		 * @return array|null
		 * @throws \Exception
		 */
		public function resolveReferenceColumn($ref_name, array $tables = [], array $circle = [])
		{
			if (in_array($ref_name, $circle)) {
				$circle[] = $ref_name;
				throw new \Exception(sprintf('Possible cyclic reference found for column "%s": "%s".', $circle[0], implode(' > ', $circle)));
			}

			$circle[] = $ref_name;
			$info     = self::parseColumnReference($ref_name);

			if ($info) {
				$_col      = null;
				$clone     = $info['clone'];
				$ref_table = $info['table'];
				$ref_col   = $info['column'];

				if (isset($this->tables[$ref_table])) {
					/** @var $tbl \Gobl\DBAL\Table */
					$tbl = $this->tables[$ref_table];
					if ($tbl->hasColumn($ref_col)) {
						$_col = $tbl->getColumn($ref_col)
									->getOptions();
					}
				} elseif (isset($tables[$ref_table])) {
					$cols = $tables[$ref_table]['columns'];
					if (is_array($cols) AND isset($cols[$ref_col])) {
						$_col  = $cols[$ref_col];
						$type  = null;
						$opt_a = [];
						$opt_b = null;

						if (is_string($_col)) {
							$type = $_col;
						} elseif (is_array($_col) AND isset($_col['type'])) {
							$type  = $_col['type'];
							$opt_a = $_col;
						}

						if ($type AND self::isColumnReference($type)) {
							$opt_b = $this->resolveReferenceColumn($type, $tables, $circle);
						}

						if (is_array($opt_b)) {
							$_col = array_merge($opt_b, $opt_a, ['type' => $opt_b['type']]);
						}
					}
				}

				if (is_array($_col)) {
					if (!$clone) {
						$_col['auto_increment'] = false;
					}

					return $_col;
				}
			}

			return null;
		}

		/**
		 * Adds table.
		 *
		 * @param \Gobl\DBAL\Table $table The table to add
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addTable(Table $table)
		{
			$name      = $table->getName();
			$full_name = $table->getFullname();

			if ($this->hasTable($name)) {
				throw new \Exception(sprintf('The table "%s" is already added.', $name));
			}

			// prevent table full name conflict
			if ($this->hasTable($full_name)) {
				$t = $this->tbl_full_name_map[$full_name];
				throw new \Exception(sprintf('The tables "%s" and "%s" has the same full name "%s".', $name, $t, $full_name));
			}

			$this->tbl_full_name_map[$full_name] = $name;
			$this->tables[$name]                 = $table;

			return $this;
		}

		/**
		 * Adds table from options.
		 *
		 * @param array  $tables        The tables options
		 * @param string $tables_prefix The tables prefix to use
		 * @param string $namespace     The namespace to use
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addTablesFromOptions(array $tables, $tables_prefix = '', $namespace = '')
		{
			// we add tables and columns first
			foreach ($tables as $table_name => $table) {
				if (empty($table['columns']) OR !is_array($table['columns'])) {
					throw new \Exception(sprintf('You should define columns for table "%s".', $table_name));
				}

				if (empty($table['plural_name']) OR empty($table['singular_name'])) {
					throw new \Exception(sprintf('You should define "plural_name" and "singular_name" for table "%s".', $table_name));
				}

				$plural_name   = (string)$table['plural_name'];
				$singular_name = (string)$table['singular_name'];
				$columns       = $table['columns'];
				$col_prefix    = isset($table['column_prefix']) ? $table['column_prefix'] : null;
				$tbl           = new Table($table_name, $plural_name, $singular_name, $namespace, $tables_prefix);

				foreach ($columns as $column_name => $value) {
					$column = is_array($value) ? $value : ['type' => $value];

					if (isset($column['type']) AND self::isColumnReference($column['type'])) {
						$options        = $this->resolveReferenceColumn($column['type'], $tables);
						$column         = is_array($value) ? array_merge($options, $value) : $options;
						$column['type'] = $options['type'];
					}

					if (is_array($column)) {
						$col = new Column($column_name, $col_prefix);
						$col->setOptions($column);
					} else {
						throw new \Exception(sprintf('Invalid column "%s" options in table "%s".', $column_name, $table_name));
					}

					$tbl->addColumn($col);
				}

				$this->addTable($tbl);
			}

			// we add constraints after
			foreach ($tables as $table_name => $table) {
				$tbl = $this->tables[$table_name];

				if (!isset($table['constraints']))
					continue;

				$constraints = $table['constraints'];

				foreach ($constraints as $constraint) {
					$name = isset($constraint['name']) ? $constraint['name'] : null;
					if (!isset($constraint['type']))
						throw new \Exception(sprintf('You should declare constraint "type" in table "%s".', $table_name));
					if (!isset($constraint['columns']) OR !is_array($constraint['columns']))
						throw new \Exception(sprintf('You should declare constraint "columns" list in table "%s".', $table_name));

					$type = $constraint['type'];
					switch ($type) {
						case 'unique':

							$tbl->addUniqueConstraint($constraint['columns'], $name);
							break;
						case 'primary_key':

							$tbl->addPrimaryKeyConstraint($constraint['columns'], $name);
							break;
						case 'foreign_key':
							if (!isset($constraint['reference']))
								throw new \Exception(sprintf('You should declare foreign key "reference" table in table "%s".', $table_name));

							$c_ref = $constraint['reference'];

							if (!isset($this->tables[$c_ref]))
								throw new \Exception(sprintf('Reference table "%s" for foreign key in table "%s" is not defined.', $c_ref, $table_name));

							$c_ref_tbl = $this->tables[$c_ref];
							$tbl->addForeignKeyConstraint($c_ref_tbl, $constraint['columns'], $name);

							break;
						default:
							throw new \Exception(sprintf('Unknown constraint type "%s" defined in table "%s".', $type, $table_name));
					}
				}
			}

			return $this;
		}

		/**
		 * Generate database file.
		 *
		 * When namespace is not empty,
		 * only tables with the given namespace will be generated.
		 *
		 * @param string|null $namespace the table namespace to generate
		 *
		 * @return string
		 */
		public function generateDatabaseQuery($namespace = null)
		{
			return $this->rdbms->buildDatabase($this, $namespace);
		}

		/**
		 * Get tables.
		 *
		 * @param string|null $namespace
		 *
		 * @return \Gobl\DBAL\Table[]
		 */
		public function getTables($namespace = null)
		{
			if (!empty($namespace)) {
				$results = [];
				foreach ($this->tables as $name => $table) {
					if ($namespace !== $table->getNamespace()) {
						continue;
					}

					$results[$name] = $table;
				}

				return $results;
			}

			return $this->tables;
		}

		/**
		 * Checks if a given table is defined.
		 *
		 * @param string $name the table name or full name
		 *
		 * @return bool
		 */
		public function hasTable($name)
		{
			if (isset($this->tables[$name]) OR isset($this->tbl_full_name_map[$name]))
				return true;

			return false;
		}

		/**
		 * Asserts if a given table name is defined.
		 *
		 * @param string $name the table name or full name
		 *
		 * @throws \Exception
		 */
		public function assertHasTable($name)
		{
			if (!$this->hasTable($name)) {
				throw new \Exception(sprintf('The table "%s" is not defined.', $name));
			}
		}

		/**
		 * Gets table with a given name.
		 *
		 * @param string $name the table name or table full name
		 *
		 * @return \Gobl\DBAL\Table
		 */
		public function getTable($name)
		{
			if ($this->hasTable($name)) {
				if (isset($this->tbl_full_name_map[$name])) {
					$name = $this->tbl_full_name_map[$name];
				}

				return $this->tables[$name];
			}

			return null;
		}

		/**
		 * Get a unique bind param id.
		 *
		 * @return int
		 */
		private static function getBindUniqueId()
		{
			return self::$bind_unique_id++;
		}

		/**
		 * @param array $list         values to bind
		 * @param array &$bind_values where to store bind value
		 *
		 * @return string
		 * @throws \Exception
		 */
		public static function getQueryBindForArray(array $list, array &$bind_values)
		{
			if (!count($list)) {
				throw new \Exception('Your list should not be empty array.');
			}

			$list      = array_values($list);
			$bind_keys = [];

			foreach ($list as $i => $value) {
				$bind_key               = '_' . self::getBindUniqueId() . '_';
				$bind_keys[]            = ':' . $bind_key;
				$bind_values[$bind_key] = $value;
			}

			return '(' . implode(',', $bind_keys) . ')';
		}
	}
