<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Generators;

	use Gobl\DBAL\Column;
	use Gobl\DBAL\Constraints\ForeignKey;
	use Gobl\DBAL\Constraints\PrimaryKey;
	use Gobl\DBAL\Constraints\Unique;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\Table;

	/**
	 * Class BaseSQLGenerator
	 *
	 * Thanks to Contributors of Doctrine DBAL
	 * some concept and code form Doctrine DBAL query builder
	 * are fully or partially used here.
	 *
	 * @package Gobl\DBAL\Generators
	 */
	abstract class BaseSQLGenerator implements Generator
	{
		/** @var \Gobl\DBAL\QueryBuilder */
		protected $query;

		/** @var array */
		protected $options;

		/**
		 * BaseSQLGenerator constructor.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $query
		 */
		public function __construct(QueryBuilder $query)
		{
			$this->query   = $query;
			$this->options = $query->getOptions();
		}

		/**
		 * Gets bool column definition query string.
		 *
		 * @param \Gobl\DBAL\Column $column
		 *
		 * @return string
		 */
		protected function getBoolColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$type        = $column->getTypeObject();
			$null        = $type->isNullAble();
			$default     = $type->getDefault();

			$sql[] = "`$column_name` tinyint(1)";

			if (!$null) {
				$sql[] = 'NOT NULL';

				if (!is_null($default)) {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} else {
				$sql[] = 'NULL';
				if (is_null($default)) {
					$sql[] = 'DEFAULT NULL';
				} else {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			}

			return implode(' ', $sql);
		}

		/**
		 * Gets int column definition query string.
		 *
		 * @param \Gobl\DBAL\Column $column
		 *
		 * @return string
		 */
		protected function getIntColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$type        = $column->getTypeObject();
			$options     = $type->getCleanOptions();
			$null        = $type->isNullAble();
			$default     = $type->getDefault();
			$unsigned    = $options['unsigned'];
			$min         = isset($options['min']) ? $options['min'] : -INF;
			$max         = isset($options['max']) ? $options['max'] : INF;

			$sql[] = "`$column_name`";

			if ($unsigned) {
				if ($max <= 255) {
					$sql[] = "tinyint";
				} elseif ($max <= 65535) {
					$sql[] = "smallint";
				} else {
					$sql[] = 'int(11)';
				}

				$sql[] = 'unsigned';
			} else {
				if ($min >= -128 AND $max <= 127) {
					$sql[] = "tinyint";
				} elseif ($min >= -32768 AND $max <= 32767) {
					$sql[] = "smallint";
				} else {
					$sql[] = 'integer(11)';
				}
			}

			if (!$null) {
				$sql[] = 'NOT NULL';

				if (!is_null($default)) {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} else {
				$sql[] = 'NULL';
				if (is_null($default)) {
					$sql[] = 'DEFAULT NULL';
				} else {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			}

			if ($type->isAutoIncremented()) {
				$sql[] = 'AUTO_INCREMENT';
			}

			return implode(' ', $sql);
		}

		/**
		 * Gets bigint column definition query string.
		 *
		 * @param \Gobl\DBAL\Column $column
		 *
		 * @return string
		 */
		protected function getBigintColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$type        = $column->getTypeObject();
			$options     = $type->getCleanOptions();
			$null        = $type->isNullAble();
			$default     = $type->getDefault();
			$unsigned    = $options['unsigned'];

			$sql[] = "`$column_name` bigint(20)";

			if ($unsigned) {
				$sql[] = 'unsigned';
			}

			if (!$null) {
				$sql[] = 'NOT NULL';

				if (!is_null($default)) {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} else {
				$sql[] = 'NULL';
				if (is_null($default)) {
					$sql[] = 'DEFAULT NULL';
				} else {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			}

			if ($type->isAutoIncremented()) {
				$sql[] = 'AUTO_INCREMENT';
			}

			return implode(' ', $sql);
		}

		/**
		 * Gets float column definition query string.
		 *
		 * @param \Gobl\DBAL\Column $column
		 *
		 * @return string
		 */
		protected function getFloatColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$type        = $column->getTypeObject();
			$options     = $type->getCleanOptions();
			$null        = $type->isNullAble();
			$default     = $type->getDefault();
			$unsigned    = $options['unsigned'];
			$mantissa    = isset($options['mantissa']) ? $options['mantissa'] : 53;

			$sql[] = "`$column_name` float($mantissa)";

			if ($unsigned) {
				$sql[] = 'unsigned';
			}

			if (!$null) {
				$sql[] = 'NOT NULL';

				if (!is_null($default)) {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} else {
				$sql[] = 'NULL';
				if (is_null($default)) {
					$sql[] = 'DEFAULT NULL';
				} else {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			}

			return implode(' ', $sql);
		}

		/**
		 * Gets string column definition query string.
		 *
		 * @param \Gobl\DBAL\Column $column
		 *
		 * @return string
		 */
		protected function getStringColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$type        = $column->getTypeObject();
			$options     = $type->getCleanOptions();
			$null        = $type->isNullAble();
			$default     = $type->getDefault();
			$min         = isset($options['min']) ? $options['min'] : 0;
			$max         = isset($options['max']) ? $options['max'] : INF;
			// char(c) c in range(0,255);
			// varchar(c) c in range(0,65535);
			$c     = $max;
			$sql[] = "`$column_name`";

			if ($c <= 255 AND $min === $max) {
				$sql[] = "char($c)";
			} elseif ($c <= 65535) {
				$sql[] = "varchar($c)";
			} else {
				$sql[] = 'text';
			}

			if (!$null) {
				$sql[] = 'NOT NULL';

				if (!is_null($default)) {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} else {
				$sql[] = 'NULL';
				if (is_null($default)) {
					$sql[] = 'DEFAULT NULL';
				} else {
					$sql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			}

			return implode(' ', $sql);
		}

		/**
		 * Gets unique constraint definition query string.
		 *
		 * @param \Gobl\DBAL\Table              $table the table
		 * @param \Gobl\DBAL\Constraints\Unique $uc    the unique constraint
		 * @param bool                          $alter use alter syntax
		 *
		 * @return string
		 */
		protected function getUniqueSQL(Table $table, Unique $uc, $alter = true)
		{
			$table_name   = $table->getFullName();
			$columns_list = self::quoteCols($uc->getConstraintColumns());
			$sql          = $alter ? 'ALTER' . ' TABLE `' . $table_name . '` ADD ' : '';
			$sql          .= 'CONSTRAINT ' . $uc->getName() . ' UNIQUE (' . $columns_list . ')' . ($alter ? ';' : '');

			return $sql;
		}

		/**
		 * Gets primary key constraint definition query string.
		 *
		 * @param \Gobl\DBAL\Table                  $table the table
		 * @param \Gobl\DBAL\Constraints\PrimaryKey $pk    the primary key constraint
		 * @param bool                              $alter use alter syntax
		 *
		 * @return string
		 */
		protected function getPrimaryKeySQL(Table $table, PrimaryKey $pk, $alter = true)
		{
			$columns_list = self::quoteCols($pk->getConstraintColumns());
			$table_name   = $table->getFullName();
			$sql          = $alter ? 'ALTER' . ' TABLE `' . $table_name . '` ADD ' : '';
			$sql          .= 'CONSTRAINT ' . $pk->getName() . ' PRIMARY KEY (' . $columns_list . ')' . ($alter ? ';' : '');

			return $sql;
		}

		/**
		 * Gets foreign key constraint definition query string.
		 *
		 * @param \Gobl\DBAL\Table                  $table the table
		 * @param \Gobl\DBAL\Constraints\ForeignKey $fk    the foreign key constraint
		 * @param bool                              $alter use alter syntax
		 *
		 * @return string
		 */
		protected function getForeignKeySQL(Table $table, ForeignKey $fk, $alter = true)
		{
			$table_name    = $table->getFullName();
			$columns       = $fk->getConstraintColumns();
			$ref_table     = $fk->getReferenceTable();
			$update_action = $fk->getUpdateAction();
			$delete_action = $fk->getDeleteAction();
			$columns_list  = self::quoteCols(array_keys($columns));
			$references    = self::quoteCols(array_values($columns));
			$sql           = $alter ? 'ALTER' . ' TABLE `' . $table_name . '` ADD ' : '';
			$sql           .= 'CONSTRAINT ' . $fk->getName() . ' FOREIGN KEY (' . $columns_list . ') REFERENCES ' . $ref_table->getFullName() . ' (' . $references . ')';

			$sql .= ' ON UPDATE ';
			if ($update_action === ForeignKey::ACTION_SET_NULL) {
				$sql .= 'SET NULL';
			} elseif ($update_action === ForeignKey::ACTION_CASCADE) {
				$sql .= 'CASCADE';
			} elseif ($update_action === ForeignKey::ACTION_RESTRICT) {
				$sql .= 'RESTRICT';
			} else {
				$sql .= 'NO ACTION';
			}

			$sql .= ' ON DELETE ';
			if ($delete_action === ForeignKey::ACTION_SET_NULL) {
				$sql .= 'SET NULL';
			} elseif ($delete_action === ForeignKey::ACTION_CASCADE) {
				$sql .= 'CASCADE';
			} elseif ($delete_action === ForeignKey::ACTION_RESTRICT) {
				$sql .= 'RESTRICT';
			} else {
				$sql .= 'NO ACTION';
			}

			$sql .= ($alter ? ';' : '');

			return $sql;
		}

		/**
		 * Quote columns name in a given list.
		 *
		 * @param array $list
		 *
		 * @return string
		 */
		protected static function quoteCols(array $list)
		{
			return '`' . implode('` , `', $list) . '`';
		}

		/**
		 * Wrap string, int... in single quote.
		 *
		 * @param mixed $value
		 *
		 * @return string
		 */
		protected static function quote($value)
		{
			return "'" . str_replace("'", "''", $value) . "'";
		}

		/**
		 * Returns sql SELECT query.
		 *
		 * @return string
		 */
		protected function getSelectQuery()
		{
			$columns = $this->getSelectedColumnsQuery();
			$where   = $this->getWhereQuery();
			$from    = $this->getFromQuery();
			$groupBy = $this->getGroupByQuery();
			$having  = $this->getHavingQuery();
			$orderBy = $this->getOrderByQuery();
			$limit   = $this->getLimitQuery();
			$sql     = 'SELECT ' . $columns . ' FROM ' . $from . ' WHERE ' . $where
					   . $groupBy . $having . $orderBy . $limit;

			return $sql;
		}

		/**
		 * Returns sql HAVING query part.
		 *
		 * @return string
		 */
		protected function getHavingQuery()
		{
			$having = (string)$this->options['having'];
			if (!empty($having)) {
				return ' HAVING ' . $having;
			}

			return '';
		}

		/**
		 * Returns sql LIMIT query part.
		 *
		 * @return string
		 */
		protected function getLimitQuery()
		{
			$offset = $this->options['limitOffset'];
			$max    = $this->options['limitMax'];
			$sql    = '';
			if (is_int($max)) {
				$sql = ' LIMIT ' . $max;
				if (is_int($offset)) {
					$sql .= ' OFFSET ' . $offset;
				}
			}

			return $sql;
		}

		/**
		 * Returns sql SET query part for table UPDATE.
		 *
		 * @return string
		 */
		protected function getSetQuery()
		{
			$x = [];
			foreach ($this->options['columns'] as $column => $key_bind_name) {
				$x[] = $column . ' = ' . $key_bind_name;
			}

			return implode(' , ', $x);
		}

		/**
		 * Returns sql UPDATE query.
		 *
		 * @return string
		 */
		protected function getUpdateQuery()
		{
			$set   = $this->getSetQuery();
			$where = $this->getWhereQuery();
			$sql   = 'UPDATE ' . $this->options['table'] . ' ' . $this->options['updateTableAlias'] . ' SET ' . $set . ' WHERE ' . $where;

			return $sql;
		}

		/**
		 * Returns sql DELETE query.
		 *
		 * @return string
		 */
		protected function getDeleteQuery()
		{
			$where = $this->getWhereQuery();
			$from  = $this->getFromQuery();

			$x = [];
			foreach ($this->options['from'] as $table => $alias) {
				if (!is_null($alias)) {
					$x[] = $alias;
				}
			}
			$delete_alias = implode(' , ', $x);

			$sql = 'DELETE ' . $delete_alias . ' FROM ' . $from . ' WHERE ' . $where;

			return $sql;
		}

		/**
		 * Returns sql INSERT query.
		 *
		 * @return string
		 */
		protected function getInsertQuery()
		{
			$columns = implode(' , ', array_keys($this->options['columns']));
			$values  = implode(' , ', $this->options['columns']);
			$sql     = 'INSERT' . ' INTO ' . $this->options['table'] . ' (' . $columns . ') VALUES(' . $values . ')';

			return $sql;
		}

		/**
		 * Returns sql WHERE query part.
		 *
		 * @return string
		 */
		protected function getWhereQuery()
		{
			$rule = $this->options['where'];

			if (!is_null($rule)) {
				return (string)$rule;
			}

			return '1 = 1';
		}

		/**
		 * Returns sql selected columns.
		 *
		 * @return string
		 */
		protected function getSelectedColumnsQuery()
		{
			$columns = array_unique($this->options['select']);
			if (count($columns)) {
				return implode(' , ', $columns);
			} else {
				return '*';
			}
		}

		/**
		 * Returns sql FROM query part.
		 *
		 * @return string
		 */
		protected function getFromQuery()
		{
			$from = $this->options['from'];
			$x    = [];
			foreach ($from as $table => $alias) {
				if (is_null($alias)) {
					$x[] = $table . $this->getJoinQueryFor($table);
				} else {
					$x[] = $table . ' ' . $alias . ' ' . $this->getJoinQueryFor($alias);
				}
			}

			return implode(' , ', $x);
		}

		/**
		 * Returns sql GROUP BY query part.
		 *
		 * @return string
		 */
		protected function getGroupByQuery()
		{
			$groupBy = $this->options['groupBy'];

			if (count($groupBy)) {
				return ' GROUP BY ' . implode(' , ', $groupBy);
			}

			return '';
		}

		/**
		 * Returns sql ORDER BY query part.
		 *
		 * @return string
		 */
		protected function getOrderByQuery()
		{
			$orderBy = $this->options['orderBy'];

			if (count($orderBy)) {
				return ' ORDER BY ' . implode(' , ', $orderBy);
			}

			return '';
		}

		/**
		 * Returns sql JOIN for a given table.
		 *
		 * @param string $table the table name.
		 *
		 * @return string
		 */
		protected function getJoinQueryFor($table)
		{
			$sql = '';
			if (isset($this->options['joins'][$table])) {
				$joins = $this->options['joins'][$table];
				foreach ($joins as $join) {
					$type      = $join['type'];
					$condition = (string)$join['condition'];
					$sql       .= ' ' . $type
								  . ' JOIN ' . $join['secondTable'] . ' ' . $join['secondTableAlias']
								  . ' ON ' . (!empty($condition) ? $condition : '1 = 1');
				}

				foreach ($joins as $join) {
					$sql .= $this->getJoinQueryFor($join['secondTableAlias']);
				}
			}

			return $sql;
		}
	}
