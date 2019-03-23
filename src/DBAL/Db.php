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

	use Gobl\DBAL\Constraints\ForeignKey;
	use Gobl\DBAL\Exceptions\DBALException;
	use Gobl\DBAL\Relations\ManyToMany;
	use Gobl\DBAL\Relations\ManyToOne;
	use Gobl\DBAL\Relations\OneToMany;
	use Gobl\DBAL\Relations\OneToOne;

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
		 * Transaction state flag
		 *
		 * @var bool
		 */
		private $trans_on = false;

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
		protected function __construct(RDBMS $rdbms)
		{
			$this->rdbms = $rdbms;
		}

		/**
		 * Prevent external clone.
		 */
		private function __clone() { }

		/**
		 * Db destructor.
		 */
		public function __destruct()
		{
			$this->db_connection = null;
			$this->rdbms         = null;
		}

		/**
		 * Gets the rdbms.
		 *
		 * @return \Gobl\DBAL\RDBMS
		 */
		public function getRDBMS()
		{
			return $this->rdbms;
		}

		/**
		 * Gets database connection.
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
		 * Executes raw sql string.
		 *
		 * @param string     $sql              the sql query string
		 * @param array|null $params           Your sql params
		 * @param array|null $params_types     Your sql params type
		 * @param bool       $trans_on         run the query in a transaction
		 * @param bool       $trans_auto       auto commit or rollback
		 * @param bool       $is_multi_queries the sql string contains multiple query
		 *
		 * @return \PDOStatement
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function execute($sql, array $params = null, array $params_types = null, $is_multi_queries = false, $trans_on = false, $trans_auto = false)
		{
			if (empty($sql)) {
				throw new DBALException('Your query is empty.');
			}

			if ($trans_on) {
				$this->beginTransaction();
			}

			$connection = $this->getConnection();

			try {
				$stmt = $connection->prepare($sql);

				if ($params !== null) {
					foreach ($params as $key => $value) {
						if (isset($params_types[$key])) {
							$param_type = $params_types[$key];
						} else {
							$param_type = QueryTokenParser::paramType($value);
						}

						$stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $param_type);
					}
				}

				$stmt->execute();

				if ($is_multi_queries) {
					/* https://bugs.php.net/bug.php?id=61613 */
					$i = 0;
					while ($stmt->nextRowset()) {
						$i++;
					}
				}

				if ($this->trans_on AND $trans_auto) {
					$this->commit();
				}

				return $stmt;

			} catch (\PDOException $e) {
				if ($this->trans_on AND $trans_auto) {
					$this->rollBack();
				}

				throw $e;
			}
		}

		/**
		 * Executes sql string with multiples query.
		 *
		 * Suitable for sql file content.
		 *
		 * @param string $sql the sql query string
		 *
		 * @return \PDOStatement
		 * @throws \Exception
		 */
		public function executeMulti($sql)
		{
			return $this->execute($sql, null, null, true, true, true);
		}

		/**
		 * Begin transaction.
		 *
		 * @return bool
		 */
		public function beginTransaction()
		{
			$con = $this->getConnection();

			if (!$this->trans_on) {
				$this->trans_on = true;

				if ($con->inTransaction()) {
					return true;
				}

				return $con->beginTransaction();
			}

			return true;
		}

		/**
		 * Commit current transaction.
		 *
		 * @return bool
		 */

		public function commit()
		{
			if ($this->trans_on) {
				$this->trans_on = false;
				return $this->getConnection()->commit();
			}
			return true;
		}

		/**
		 * Rollback current transaction.
		 *
		 * @return bool
		 */
		public function rollBack()
		{
			if ($this->trans_on) {
				$this->trans_on = false;
				return $this->getConnection()->rollBack();
			}
			return true;
		}

		/**
		 * Executes a query and return affected row count.
		 *
		 * @param string     $sql          Your sql query
		 * @param array|null $params       Your sql params
		 * @param array      $params_types Your sql params types
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
		 * @return string The last insert id
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
			return is_array(static::parseColumnReference($str));
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
				$reg = '#^(ref|cp)[:]([a-zA-Z0-9_]+)[.]([a-zA-Z0-9_]+)$#';
				if (preg_match($reg, $str, $parts)) {
					$head  = $parts[1];
					$clone = ($head === 'cp' ? true : false);

					return [
						'clone'  => $clone,
						'table'  => $parts[2],
						'column' => $parts[3]
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function resolveReferenceColumn($ref_name, array $tables = [], array $circle = [])
		{
			if (in_array($ref_name, $circle)) {
				$circle[] = $ref_name;
				throw new DBALException(sprintf('Possible cyclic reference found for column "%s": "%s".', $circle[0], implode(' > ', $circle)));
			}

			$circle[] = $ref_name;
			$info     = static::parseColumnReference($ref_name);

			if ($info) {
				$_col_opt  = null;
				$clone     = $info['clone'];
				$ref_table = $info['table'];
				$ref_col   = $info['column'];

				if (isset($this->tables[$ref_table])) {
					/** @var $tbl \Gobl\DBAL\Table */
					$tbl = $this->tables[$ref_table];
					if ($tbl->hasColumn($ref_col)) {
						$_col_opt = $tbl->getColumn($ref_col)
										->getTypeObject()
										->getCleanOptions();
					}
				} elseif (isset($tables[$ref_table])) {
					$cols = $tables[$ref_table]['columns'];
					if (is_array($cols) AND isset($cols[$ref_col])) {
						$_col_opt = $cols[$ref_col];
						$type     = null;
						$opt_a    = [];
						$opt_b    = null;

						if (is_string($_col_opt)) {
							$type = $_col_opt;
						} elseif (is_array($_col_opt) AND isset($_col_opt['type'])) {
							$type  = $_col_opt['type'];
							$opt_a = $_col_opt;
						}

						if ($type AND static::isColumnReference($type)) {
							$opt_b = $this->resolveReferenceColumn($type, $tables, $circle);
						}

						if (is_array($opt_b)) {
							$_col_opt = array_merge($opt_b, $opt_a, ['type' => $opt_b['type']]);
						}
					}
				}

				if (is_array($_col_opt)) {
					if ($clone === false AND isset($_col_opt['auto_increment'])) {
						unset($_col_opt['auto_increment']);
					}

					return $_col_opt;
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function addTable(Table $table)
		{
			$name      = $table->getName();
			$full_name = $table->getFullname();

			if ($this->hasTable($name)) {
				throw new DBALException(sprintf('The table "%s" is already added.', $name));
			}

			// prevent table full name conflict
			if ($this->hasTable($full_name)) {
				$t = $this->tbl_full_name_map[$full_name];
				throw new DBALException(sprintf('The tables "%s" and "%s" has the same full name "%s".', $name, $t, $full_name));
			}

			$this->tbl_full_name_map[$full_name] = $name;
			$this->tables[$name]                 = $table;

			return $this;
		}

		/**
		 * Adds table from options.
		 *
		 * @param array  $tables        The tables options
		 * @param string $namespace     The namespace to use
		 * @param string $tables_prefix The tables prefix to use
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function addTablesFromOptions(array $tables, $namespace, $tables_prefix = '')
		{
			// we add tables and columns first
			foreach ($tables as $table_name => $table_options) {
				if (empty($table_options['columns']) OR !is_array($table_options['columns'])) {
					throw new DBALException(sprintf('You should define columns for table "%s".', $table_name));
				}

				$columns    = $table_options['columns'];
				$col_prefix = isset($table_options['column_prefix']) ? $table_options['column_prefix'] : null;
				$tbl        = new Table($table_name, $namespace, $tables_prefix, $table_options);

				foreach ($columns as $column_name => $value) {
					$col_options = is_array($value) ? $value : ['type' => $value];

					if (isset($col_options['type']) AND static::isColumnReference($col_options['type'])) {
						$ref_options = $this->resolveReferenceColumn($col_options['type'], $tables);
						if (is_array($ref_options)) {
							$col_options         = is_array($value) ? array_merge($ref_options, $value) : $ref_options;
							$col_options['type'] = $ref_options['type'];
						} else {
							throw new DBALException(sprintf('Type "%s" not resolved for column "%s" in table "%s".', $col_options['type'], $column_name, $table_name));
						}
					}

					if (is_array($col_options)) {
						$col = new Column($column_name, $col_prefix, $col_options);
					} else {
						throw new DBALException(sprintf('Invalid column "%s" options in table "%s".', $column_name, $table_name));
					}

					$tbl->addColumn($col);
				}

				$this->addTable($tbl);
			}

			// we add constraints after
			foreach ($tables as $table_name => $table_options) {
				$tbl = $this->tables[$table_name];

				if (!isset($table_options['constraints'])) {
					continue;
				}

				$constraints = $table_options['constraints'];

				foreach ($constraints as $constraint) {
					if (!isset($constraint['type'])) {
						throw new DBALException(sprintf('You should define constraint "type" in table "%s".', $table_name));
					}
					if (!isset($constraint['columns']) OR !is_array($constraint['columns']) OR !count($constraint['columns'])) {
						throw new DBALException(sprintf('Constraint "columns" is not defined or is empty (in the table "%s").', $table_name));
					}

					$columns = $constraint['columns'];
					$type    = $constraint['type'];

					switch ($type) {
						case 'unique':
							$tbl->addUniqueConstraint($columns);
							break;
						case 'primary_key':
							$tbl->addPrimaryKeyConstraint($columns);
							break;
						case 'foreign_key':
							if (!isset($constraint['reference'])) {
								throw new DBALException(sprintf('You should declare foreign key "reference" table in table "%s".', $table_name));
							}

							$reference = $constraint['reference'];

							if (!isset($this->tables[$reference])) {
								throw new DBALException(sprintf('Reference table "%s" for foreign key in table "%s" is not defined.', $reference, $table_name));
							}

							$reference_table = $this->tables[$reference];
							$update_action   = ForeignKey::ACTION_NO_ACTION;
							$delete_action   = ForeignKey::ACTION_NO_ACTION;
							$map             = [
								'set_null' => ForeignKey::ACTION_SET_NULL,
								'cascade'  => ForeignKey::ACTION_CASCADE,
								'restrict' => ForeignKey::ACTION_RESTRICT,
							];
							$name            = null;

							if (isset($constraint['name'])) {
								$name = $constraint['name'];
							}

							if (isset($constraint['update'])) {
								if (!isset($map[$constraint['update']])) {
									throw new DBALException(sprintf('Invalid update action "%s" for foreign key constraint.', $constraint['update']));
								}
								$update_action = $map[$constraint['update']];
							}
							if (isset($constraint['delete'])) {
								if (!isset($map[$constraint['delete']])) {
									throw new DBALException(sprintf('Invalid delete action "%s" for foreign key constraint.', $constraint['delete']));
								}
								$delete_action = $map[$constraint['delete']];
							}

							$tbl->addForeignKeyConstraint($name, $reference_table, $constraint['columns'], $update_action, $delete_action);

							break;
						default:
							throw new DBALException(sprintf('Unknown constraint type "%s" defined in table "%s".', $type, $table_name));
					}
				}
			}

			// we could now add relations
			foreach ($tables as $table_name => $table_options) {
				if (isset($table_options['relations']) AND is_array($table_options['relations']) AND count($table_options['relations'])) {
					$relations = $table_options['relations'];
					foreach ($relations as $relation_name => $rel_options) {
						$r = null;

						if (is_array($rel_options) AND isset($rel_options['type']) AND isset($rel_options['target'])) {
							$type    = $rel_options['type'];
							$target  = $rel_options['target'];
							$columns = isset($rel_options['columns']) ? $rel_options['columns'] : null;

							$this->assertHasTable($target);

							if ($type === 'one-to-one') {
								$r = new OneToOne($relation_name, $this->getTable($table_name), $this->getTable($target), $columns);
							} elseif ($type === 'one-to-many') {
								$r = new OneToMany($relation_name, $this->getTable($table_name), $this->getTable($target), $columns);
							} elseif ($type === 'many-to-one') {
								$r = new ManyToOne($relation_name, $this->getTable($table_name), $this->getTable($target), $columns);
							} elseif ($type === 'many-to-many') {
								$r = new ManyToMany($relation_name, $this->getTable($table_name), $this->getTable($target), $columns);
							}
						}

						if (is_null($r)) {
							throw new DBALException(sprintf('Invalid relation "%s" in table "%s".', $relation_name, $table_name));
						}

						$this->getTable($table_name)
							 ->addRelation($r);
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function generateDatabaseQuery($namespace = null)
		{
			// checks all foreign key constraints
			$tables = $this->getTables($namespace);
			foreach ($tables as $table) {
				$fk_list = $table->getForeignKeyConstraints();
				foreach ($fk_list as $fk) {
					$columns = array_values($fk->getConstraintColumns());
					// necessary when whe have
					// table_a.col_1 => table_b.col_x
					// table_a.col_2 => table_b.col_x
					$columns = array_unique($columns);

					if (!$fk->getReferenceTable()
							->isPrimaryKey($columns)) {
						$message  = 'Foreign key "%s" of table "%s" should be primary key in the reference table "%s".';
						$ref_name = $fk->getReferenceTable()
									   ->getName();
						throw new DBALException(sprintf($message, implode(',', $columns), $table->getName(), $ref_name));
					}
				}
			}

			return $this->getRDBMS()
						->buildDatabase($this, $namespace);
		}

		/**
		 * Gets tables.
		 *
		 * @param string|null $namespace
		 *
		 * @return \Gobl\DBAL\Table[]
		 */
		public function getTables($namespace = null)
		{
			if (!is_null($namespace)) {
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
			if (isset($this->tables[$name]) OR isset($this->tbl_full_name_map[$name])) {
				return true;
			}

			return false;
		}

		/**
		 * Asserts if a given table name is defined.
		 *
		 * @param string $name the table name or full name
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function assertHasTable($name)
		{
			if (!$this->hasTable($name)) {
				throw new DBALException(sprintf('The table "%s" is not defined.', $name));
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
		 * Gets a unique bind param id.
		 *
		 * @return int
		 */
		private static function getBindUniqueId()
		{
			return self::$bind_unique_id++;
		}

		/**
		 * @param array  $list        values to bind
		 * @param array &$bind_values where to store bind value
		 *
		 * @return string
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public static function getQueryBindForArray(array $list, array &$bind_values)
		{
			if (!count($list)) {
				throw new DBALException('Your list should not be empty array.');
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

		/**
		 * Help var_dump().
		 *
		 * @return array
		 */
		public function __debugInfo()
		{
			return ['instance_of' => static::class];
		}
	}
