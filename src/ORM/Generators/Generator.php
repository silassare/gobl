<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\ORM\Generators;

	use Gobl\DBAL\Db;
	use Gobl\DBAL\Relations\Relation;
	use Gobl\DBAL\Table;
	use Gobl\DBAL\Types\Type;

	class Generator
	{
		private $types_map = [
			Type::TYPE_INT    => ['int', 'int'],
			Type::TYPE_BIGINT => ['bigint', 'string'],
			Type::TYPE_STRING => ['string', 'string'],
			Type::TYPE_FLOAT  => ['float', 'string'],
			Type::TYPE_BOOL   => ['bool', 'bool']
		];

		/** @var \Gobl\DBAL\Db */
		private $db;

		/**
		 * RowClassGenerator constructor.
		 *
		 * @param \Gobl\DBAL\Db $db
		 */
		public function __construct(Db $db)
		{
			$this->db = $db;
		}

		/**
		 * Generate all classes for tables with a given namespace in the database.
		 *
		 * @param string $namespace the classes php namespace
		 * @param string $path      the destination folder path
		 * @param string $header    the source header to use
		 *
		 * @return $this
		 */
		public function generateClasses($namespace, $path, $header = '')
		{
			if (!file_exists($path) OR !is_dir($path)) {
				throw new \InvalidArgumentException(sprintf('"%s" is not a valid directory path.', $path));
			}

			$ds            = DIRECTORY_SEPARATOR;
			$templates_dir = __DIR__ . $ds . '..' . $ds . 'templates' . $ds;
			$tables        = $this->db->getTables($namespace);

			$path_base = $path . $ds . 'Base';

			if (!file_exists($path_base)) {
				mkdir($path_base);
			}

			$base_table_class_tpl   = $this->getTemplate($templates_dir . 'base.table.class.otpl');
			$base_entity_class_tpl  = $this->getTemplate($templates_dir . 'base.entity.class.otpl');
			$base_results_class_tpl = $this->getTemplate($templates_dir . 'base.results.class.otpl');

			$table_class_tpl   = $this->getTemplate($templates_dir . 'table.class.otpl');
			$entity_class_tpl  = $this->getTemplate($templates_dir . 'entity.class.otpl');
			$results_class_tpl = $this->getTemplate($templates_dir . 'results.class.otpl');

			foreach ($tables as $table) {
				$inject              = $this->getTableInject($table);
				$inject['header']    = $header;
				$inject['time']      = time();
				$inject['use']       = [];
				$inject['columns']   = $this->columnsProperties($table);
				$inject['relations'] = $this->relationsProperties($table);
				$table_class         = $inject['class']['table'];
				$entity_class        = $inject['class']['entity'];
				$results_class       = $inject['class']['results'];

				$this->writeFile($path_base . $ds . $table_class . '.php', $base_table_class_tpl->runGet($inject));
				$this->writeFile($path_base . $ds . $entity_class . '.php', $base_entity_class_tpl->runGet($inject));
				$this->writeFile($path_base . $ds . $results_class . '.php', $base_results_class_tpl->runGet($inject));

				$this->writeFile($path . $ds . $table_class . '.php', $table_class_tpl->runGet($inject), false);
				$this->writeFile($path . $ds . $entity_class . '.php', $entity_class_tpl->runGet($inject), false);
				$this->writeFile($path . $ds . $results_class . '.php', $results_class_tpl->runGet($inject), false);
			}

			return $this;
		}

		/**
		 * Write contents to file.
		 *
		 * @param string $path      the file path
		 * @param mixed  $content   the file content
		 * @param bool   $overwrite overwrite file if exists, default is true
		 */
		private function writeFile($path, $content, $overwrite = true)
		{
			if (!$overwrite AND file_exists($path)) {
				return;
			}

			file_put_contents($path, $content);
		}

		/**
		 * Gets template object instance with a given template source.
		 *
		 * @param string $source the template source
		 *
		 * @return \OTpl
		 */
		private function getTemplate($source)
		{
			$o = new \OTpl;
			$o->parse($source);

			return $o;
		}

		/**
		 * Gets columns data to be used in template file for a given table.
		 *
		 * @param \Gobl\DBAL\Table $table the table object
		 *
		 * @return array
		 */
		private function columnsProperties(Table $table)
		{
			$columns = $table->getColumns();
			$list    = [];
			foreach ($columns as $column) {
				$name       = $column->getName();
				$type_const = $column->getTypeObject()
									 ->getTypeConstant();

				$c['name']       = $name;
				$c['fullName']   = $column->getFullName();
				$c['methodName'] = $this->toCamelCase($name);
				$c['const']      = 'COL_' . strtoupper($name);
				$c['columnType'] = $this->types_map[$type_const][0];
				$c['returnType'] = $this->types_map[$type_const][1];
				$c['argName']    = $name;
				$c['argType']    = $c['returnType'];

				$list[] = $c;
			}

			return $list;
		}

		/**
		 * Gets relations data to be used in template file for a given table.
		 *
		 * @param \Gobl\DBAL\Table $table the table object
		 *
		 * @return array
		 */
		private function relationsProperties(Table $table)
		{
			$relation_types = [
				Relation::ONE_TO_ONE   => 'one-to-one',
				Relation::ONE_TO_MANY  => 'one-to-many',
				Relation::MANY_TO_ONE  => 'many-to-one',
				Relation::MANY_TO_MANY => 'many-to-many'
			];
			$relations      = $table->getRelations();
			$list           = [];
			foreach ($relations as $relation_name => $relation) {
				$type         = $relation->getType();
				$master_table = $relation->getMasterTable();
				$slave_table  = $relation->getSlaveTable();
				$host_table   = $relation->getHostTable();
				$target_table = $relation->getTargetTable();
				$c            = $relation->getRelationColumns();

				$filters    = [];
				$left_right = true;

				if ($host_table->hasColumn(key($c))) {
					$left_right = false;
				}

				foreach ($c as $left => $right) {
					if ($left_right) {
						$left           = $this->toCamelCase($target_table->getColumn($left)
																		  ->getName());
						$right          = $this->toCamelCase($host_table->getColumn($right)
																		->getName());
						$filters[$left] = $right;
					} else {
						$left            = $this->toCamelCase($host_table->getColumn($left)
																		 ->getName());
						$right           = $this->toCamelCase($target_table->getColumn($right)
																		   ->getName());
						$filters[$right] = $left;
					}
				}

				$r['name']       = $relation->getName();
				$r['type']       = $relation_types[$type];
				$r['methodName'] = $this->toCamelCase($r['name']);
				$r['master']     = $this->getTableInject($master_table);
				$r['slave']      = $this->getTableInject($slave_table);
				$r['master']     = $this->getTableInject($master_table);
				$r['slave']      = $this->getTableInject($slave_table);
				$r['host']       = $this->getTableInject($host_table);
				$r['target']     = $this->getTableInject($target_table);
				$r['filters']    = $filters;
				$list[]          = $r;
			}

			return $list;
		}

		/**
		 * Gets data to be used in template file for a given table.
		 *
		 * @param \Gobl\DBAL\Table $table the table object
		 *
		 * @return array
		 */
		private function getTableInject(Table $table)
		{
			$table_class_name   = $this->toCamelCase($table->getPluralName() . '_table');
			$entity_class_name  = $this->toCamelCase($table->getSingularName());
			$results_class_name = $this->toCamelCase($table->getPluralName() . '_results');

			return [
				'namespace' => $table->getNamespace(),
				'class'     => [
					'table'   => $table_class_name,
					'entity'  => $entity_class_name,
					'results' => $results_class_name
				],
				'table'     => [
					'name' => $table->getName()
				]
			];
		}

		/**
		 * Converts string to CamelCase.
		 *
		 * example:
		 *    my_table_name => MyTableName
		 *    my_column_name => MyColumnName
		 *
		 * @param string $str the table or column name
		 *
		 * @return string
		 */
		private function toCamelCase($str)
		{
			return implode('', array_map('ucfirst', explode('_', $str)));
		}
	}