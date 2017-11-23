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

	use Gobl\DBAL\Column;
	use Gobl\DBAL\Db;
	use Gobl\DBAL\Relations\Relation;
	use Gobl\DBAL\Table;
	use Gobl\DBAL\Types\Type;
	use Gobl\ORM\Exceptions\ORMException;

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
		 * Generate classes for tables with a given namespace in the database.
		 *
		 * @param string $namespace the classes namespace
		 * @param string $path      the destination folder path
		 * @param string $header    the source header to use
		 *
		 * @return $this
		 */
		public function generateORMClasses($namespace, $path, $header = '')
		{
			if (!file_exists($path) OR !is_dir($path)) {
				throw new \InvalidArgumentException(sprintf('"%s" is not a valid directory path.', $path));
			}

			$ds            = DIRECTORY_SEPARATOR;
			$templates_dir = $this->getTemplateDir();
			$tables        = $this->db->getTables($namespace);

			$path_base = $path . $ds . 'Base';

			if (!file_exists($path_base)) {
				mkdir($path_base);
			}

			$base_query_class_tpl      = $this->getTemplate($templates_dir . 'base.query.class.otpl');
			$base_entity_class_tpl     = $this->getTemplate($templates_dir . 'base.entity.class.otpl');
			$base_results_class_tpl    = $this->getTemplate($templates_dir . 'base.results.class.otpl');
			$base_controller_class_tpl = $this->getTemplate($templates_dir . 'base.controller.class.otpl');

			$query_class_tpl      = $this->getTemplate($templates_dir . 'query.class.otpl');
			$entity_class_tpl     = $this->getTemplate($templates_dir . 'entity.class.otpl');
			$results_class_tpl    = $this->getTemplate($templates_dir . 'results.class.otpl');
			$controller_class_tpl = $this->getTemplate($templates_dir . 'controller.class.otpl');

			foreach ($tables as $table) {
				$inject           = $this->describeTable($table);
				$inject['header'] = $header;
				$inject['time']   = time();
				$query_class      = $inject['class']['query'];
				$entity_class     = $inject['class']['entity'];
				$results_class    = $inject['class']['results'];
				$controller_class = $inject['class']['controller'];

				$this->writeFile($path_base . $ds . $query_class . '.php', $base_query_class_tpl->runGet($inject));
				$this->writeFile($path_base . $ds . $entity_class . '.php', $base_entity_class_tpl->runGet($inject));
				$this->writeFile($path_base . $ds . $results_class . '.php', $base_results_class_tpl->runGet($inject));
				$this->writeFile($path_base . $ds . $controller_class . '.php', $base_controller_class_tpl->runGet($inject));

				$this->writeFile($path . $ds . $query_class . '.php', $query_class_tpl->runGet($inject), false);
				$this->writeFile($path . $ds . $entity_class . '.php', $entity_class_tpl->runGet($inject), false);
				$this->writeFile($path . $ds . $results_class . '.php', $results_class_tpl->runGet($inject), false);
				$this->writeFile($path . $ds . $controller_class . '.php', $controller_class_tpl->runGet($inject), false);
			}

			return $this;
		}

		/**
		 * Generate ozone class for a given table.
		 *
		 * @param \Gobl\DBAL\Table $table             the table
		 * @param string           $service_namespace the service class namespace
		 * @param string           $service_dir       the destination folder path
		 * @param string           $service_name      the service name
		 * @param string           $service_class     the service class name to use
		 * @param string           $header            the source header to use
		 *
		 * @return array the ozone setting for the service
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function generateOZServiceClass(Table $table, $service_namespace, $service_dir, $service_name, $service_class = '', $header = '')
		{
			if (!file_exists($service_dir) OR !is_dir($service_dir)) {
				throw new \InvalidArgumentException(sprintf('"%s" is not a valid directory path.', $service_dir));
			}

			if (!$table->hasPrimaryKeyConstraint()) {
				throw new ORMException(sprintf('There is no primary key in the table "%s".', $table->getName()));
			}

			$pk      = $table->getPrimaryKeyConstraint();
			$columns = $pk->getConstraintColumns();

			if (count($columns) !== 1) {
				throw new ORMException('You can generate ozone service only for tables with one column as primary key.');
			}

			if (empty($service_class)) {
				$service_class = $this->toCamelCase($table->getName() . '_service');
			}

			$templates_dir                  = $this->getTemplateDir();
			$controller_class_tpl           = $this->getTemplate($templates_dir . 'ozone.service.class.otpl');
			$inject                         = $this->describeTable($table);
			$inject['header']               = $header;
			$inject['time']                 = time();
			$inject['service']['name']      = $service_name;
			$inject['service']['namespace'] = $service_namespace;
			$inject['service']['class']     = $service_class;
			$inject['pk']                   = $this->columnProperties($table->getColumn($columns[0]));
			$qualified_class                = $service_namespace . '\\' . $inject['service']['class'];
			$class_path                     = $service_dir . DIRECTORY_SEPARATOR . $service_class . '.php';

			if (file_exists($class_path)) {
				rename($class_path, $class_path . '.old');
			}

			$this->writeFile($class_path, $controller_class_tpl->runGet($inject));

			return [
				"service_class"   => $qualified_class,
				"is_file_service" => false,
				"can_serve_resp"  => false,
				"cross_site"      => false,
				"require_session"  => true,
				"request_methods"     => ['POST', 'GET', 'PUT', 'PATCH', 'DELETE']
			];
		}

		/**
		 * Gets templates directory absolute path.
		 *
		 * @return string
		 */
		private function getTemplateDir()
		{
			$ds = DIRECTORY_SEPARATOR;

			return __DIR__ . $ds . '..' . $ds . 'templates' . $ds;
		}

		/**
		 * Returns array that can be used to generate file.
		 *
		 * @param \Gobl\DBAL\Table $table
		 *
		 * @return array
		 */
		private function describeTable(Table $table)
		{
			$inject              = $this->getTableInject($table);
			$inject['columns']   = $this->getTableColumnsProperties($table);
			$inject['relations'] = $this->relationsProperties($table);

			return $inject;
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
		private function getTableColumnsProperties(Table $table)
		{
			$columns = $table->getColumns();
			$list    = [];
			foreach ($columns as $column) {
				$list[] = $this->columnProperties($column);
			}

			return $list;
		}

		/**
		 * Gets column data to be used in template file
		 *
		 * @param \Gobl\DBAL\Column $column
		 *
		 * @return array
		 */
		private function columnProperties(Column $column)
		{
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

			return $c;
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
			$query_class_name      = $this->toCamelCase($table->getPluralName() . '_query');
			$entity_class_name     = $this->toCamelCase($table->getSingularName());
			$results_class_name    = $this->toCamelCase($table->getPluralName() . '_results');
			$controller_class_name = $this->toCamelCase($table->getPluralName() . '_controller');

			return [
				'namespace' => $table->getNamespace(),
				'class'     => [
					'query'      => $query_class_name,
					'entity'     => $entity_class_name,
					'results'    => $results_class_name,
					'controller' => $controller_class_name
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
		 *    my_table_query_name    => MyTableQueryName
		 *    my_column_name        => MyColumnName
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