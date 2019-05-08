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
	use Gobl\DBAL\Utils;
	use Gobl\ORM\Exceptions\ORMException;
	use OTpl\OTpl;

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
		private $ignore_private_table;
		private $ignore_private_column;

		/**
		 * RowClassGenerator constructor.
		 *
		 * @param \Gobl\DBAL\Db $db
		 * @param bool          $ignore_private_table
		 * @param bool          $ignore_private_column
		 */
		public function __construct(Db $db, $ignore_private_table = true, $ignore_private_column = true)
		{
			$this->db                    = $db;
			$this->ignore_private_table  = $ignore_private_table;
			$this->ignore_private_column = $ignore_private_column;
		}

		/**
		 * Generate classes for tables with a given namespace in the database.
		 *
		 * @param Table[] $tables the tables list
		 * @param string  $path   the destination folder path
		 * @param string  $header the source header to use
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function generateORMClasses(array $tables, $path, $header = '')
		{
			if (!file_exists($path) OR !is_dir($path)) {
				throw new \InvalidArgumentException(sprintf('"%s" is not a valid directory path.', $path));
			}

			$ds            = DIRECTORY_SEPARATOR;
			$templates_dir = $this->getTemplateDir();

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
		 * Generate TypeScript classes for tables with a given namespace in the database.
		 *
		 * @param Table[] $tables the tables list
		 * @param string  $path   the destination folder path
		 * @param string  $header the source header to use
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function generateTSClasses(array $tables, $path, $header = '')
		{
			if (!file_exists($path) OR !is_dir($path)) {
				throw new \InvalidArgumentException(sprintf('"%s" is not a valid directory path.', $path));
			}

			$ds            = DIRECTORY_SEPARATOR;
			$templates_dir = $this->getTemplateDir();

			$path_base = $path;

			if (!file_exists($path_base)) {
				mkdir($path_base);
			}

			$ts_entity_class_tpl = $this->getTemplate($templates_dir . 'ts.entity.class.otpl');
			$ts_bundle_tpl       = $this->getTemplate($templates_dir . 'ts.bundle.otpl');
			$bundle_inject       = [];
			foreach ($tables as $table) {
				if (!($table->isPrivate() && $this->ignore_private_table)) {
					$inject                 = $this->describeTable($table);
					$inject['header']       = $header;
					$inject['time']         = time();
					$entity_class           = $inject['class']['entity'];
					$inject['columns_list'] = implode("|", array_keys($inject["columns"]));

					foreach ($inject["columns"] as $column) {
						$inject['columns_prefix'] = $column['prefix'];
						break;
					}

					$bundle_inject["entities"][$entity_class] = $ts_entity_class_tpl->runGet($inject);
				}
			}

			$bundle_inject['header'] = $header;
			$bundle_inject['time']   = time();

			$this->writeFile($path . $ds . 'gobl.bundle.ts', $ts_bundle_tpl->runGet($bundle_inject), true);

			return $this;
		}

		/**
		 * Generate O'Zone service class for a given table.
		 *
		 * @param \Gobl\DBAL\Table $table             the table
		 * @param string           $service_namespace the service class namespace
		 * @param string           $service_dir       the destination folder path
		 * @param string           $service_name      the service name
		 * @param string           $service_class     the service class name to use
		 * @param string           $header            the source header to use
		 *
		 * @return array the O'Zone setting for the service
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
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
				throw new ORMException("You can generate O'Zone service only for tables with one column as primary key.");
			}

			if (empty($service_class)) {
				$service_class = Utils::toClassName($table->getName() . '_service');
			}

			$templates_dir                  = $this->getTemplateDir();
			$controller_class_tpl           = $this->getTemplate($templates_dir . 'ozone.service.class.otpl');
			$inject                         = $this->describeTable($table);
			$inject['header']               = $header;
			$inject['time']                 = time();
			$inject['service']['name']      = $service_name;
			$inject['service']['namespace'] = $service_namespace;
			$inject['service']['class']     = $service_class;
			$qualified_class                = $service_namespace . '\\' . $inject['service']['class'];
			$class_path                     = $service_dir . DIRECTORY_SEPARATOR . $service_class . '.php';

			if (file_exists($class_path)) {
				rename($class_path, $class_path . '.old');
			}

			$this->writeFile($class_path, $controller_class_tpl->runGet($inject));

			return [
				'provider' => $qualified_class
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
		 * @return \OTpl\OTpl
		 */
		private function getTemplate($source)
		{
			try {
				$o = new OTpl;
				$o->parse($source);
			} catch (\Exception $e) {
				throw new \RuntimeException('Template compile error.', null, $e);
			}

			return $o;
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
			$inject['columns']   = $this->describeTableColumns($table);
			$inject['relations'] = $this->describeTableRelations($table);

			return $inject;
		}

		/**
		 * Gets columns data to be used in template file for a given table.
		 *
		 * @param \Gobl\DBAL\Table $table the table object
		 *
		 * @return array
		 */
		private function describeTableColumns(Table $table)
		{
			$columns = $table->getColumns();
			$list    = [];
			foreach ($columns as $column) {
				if (!($column->isPrivate() && $this->ignore_private_column)) {
					$list[$column->getFullName()] = $this->describeColumn($column);
				}
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
		private function describeColumn(Column $column)
		{
			$name       = $column->getName();
			$type_const = $column->getTypeObject()
								 ->getTypeConstant();

			return [
				'private'      => $column->isPrivate(),
				'name'         => $name,
				'fullName'     => $column->getFullName(),
				'prefix'       => $column->getPrefix(),
				'methodSuffix' => Utils::toClassName($name),
				'const'        => 'COL_' . strtoupper($name),
				'columnType'   => $this->types_map[$type_const][0],
				'returnType'   => $this->types_map[$type_const][1],
				'argName'      => $name,
				'argType'      => $this->types_map[$type_const][1]
			];
		}

		/**
		 * Gets relations data to be used in template file for a given table.
		 *
		 * @param \Gobl\DBAL\Table $table the table object
		 *
		 * @return array
		 */
		private function describeTableRelations(Table $table)
		{
			$use            = [];
			$relation_types = [
				Relation::ONE_TO_ONE   => 'one-to-one',
				Relation::ONE_TO_MANY  => 'one-to-many',
				Relation::MANY_TO_ONE  => 'many-to-one',
				Relation::MANY_TO_MANY => 'many-to-many'
			];

			$relations = $table->getRelations();
			$list      = [];
			foreach ($relations as $relation_name => $relation) {
				$type         = $relation->getType();
				$master_table = $relation->getMasterTable();
				$slave_table  = $relation->getSlaveTable();
				$host_table   = $relation->getHostTable();
				$target_table = $relation->getTargetTable();
				$c            = $relation->getRelationColumns();
				$filters      = [];
				$left_right   = true;

				if ($host_table->hasColumn(key($c))) {
					$left_right = false;
				}

				foreach ($c as $left => $right) {
					if ($left_right) {
						$left   = $this->describeColumn($target_table->getColumn($left));
						$right  = $this->describeColumn($host_table->getColumn($right));
						$filter = ['from' => $left, 'to' => $right];
					} else {
						$left   = $this->describeColumn($host_table->getColumn($left));
						$right  = $this->describeColumn($target_table->getColumn($right));
						$filter = ['from' => $right, 'to' => $left];
					}

					$filters[] = $filter;
				}

				$r['name']         = $relation->getName();
				$r['type']         = $relation_types[$type];
				$r['methodSuffix'] = Utils::toClassName($r['name']);
				$r['master']       = $this->getTableInject($master_table);
				$r['slave']        = $this->getTableInject($slave_table);
				$r['host']         = $this->getTableInject($host_table);
				$r['target']       = $this->getTableInject($target_table);
				$r['filters']      = $filters;

				$use[] = $r["target"]["class"]["use_controller"] . " as " . $r["target"]["class"]["controller"] . "RealR";

				$list[] = $r;
			}

			$result["use"]  = array_unique($use);
			$result["list"] = $list;

			return $result;
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
			$query_class_name      = Utils::toClassName($table->getPluralName() . '_query');
			$entity_class_name     = Utils::toClassName($table->getSingularName());
			$results_class_name    = Utils::toClassName($table->getPluralName() . '_results');
			$controller_class_name = Utils::toClassName($table->getPluralName() . '_controller');

			$ns         = $table->getNamespace();
			$pk_columns = [];

			if ($table->hasPrimaryKeyConstraint()) {
				$pk = $table->getPrimaryKeyConstraint();
				foreach ($pk->getConstraintColumns() as $column_name) {
					$pk_columns[] = $this->describeColumn($table->getColumn($column_name));
				}
			}

			return [
				'namespace'  => $ns,
				'pk_columns' => $pk_columns,
				'private'    => $table->isPrivate(),
				'class'      => [
					'query'          => $query_class_name,
					'entity'         => $entity_class_name,
					'results'        => $results_class_name,
					'controller'     => $controller_class_name,
					'use_query'      => $ns . "\\" . $query_class_name,
					'use_entity'     => $ns . "\\" . $entity_class_name,
					'use_results'    => $ns . "\\" . $results_class_name,
					'use_controller' => $ns . "\\" . $controller_class_name
				],
				'table'      => [
					'name' => $table->getName()
				]
			];
		}

	}