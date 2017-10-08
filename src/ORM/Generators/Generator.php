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
				throw new \InvalidArgumentException('You should provide a valid directory path.');
			}

			$ds         = DIRECTORY_SEPARATOR;
			$assets_dir = __DIR__ . $ds . '..' . $ds . 'assets' . $ds;
			$tables     = $this->db->getTables($namespace);
			$ot         = new \OTpl;
			$ot->parse($assets_dir . 'table.class.otpl');

			$oe = new \OTpl;
			$oe->parse($assets_dir . 'entity.class.otpl');

			$of = new \OTpl;
			$of->parse($assets_dir . 'results.class.otpl');

			foreach ($tables as $table) {
				$columns            = $table->getColumns();
				$table_class_name   = $table->getPluralName();
				$table_class_path   = $path . $ds . $table_class_name . '.php';
				$entity_class_name  = $table->getSingularName();
				$entity_class_path  = $path . $ds . $entity_class_name . '.php';
				$results_class_name = $table_class_name . 'Results';
				$results_class_path = $path . $ds . $results_class_name . '.php';

				$inject = [
					'header'      => $header,
					'time'        => time(),
					'namespace' => $namespace,
					'class'       => [
						'table'     => $table_class_name,
						'entity'    => $entity_class_name,
						'results'   => $results_class_name
					],
					'table'       => [
						'name'     => $table->getName(),
						'fullName' => $table->getFullName()
					],
					'db_provider' => get_class($this->db)
				];

				foreach ($columns as $column) {
					$name            = $column->getName();
					$type_const      = $column->getTypeObject()
											  ->getTypeConstant();
					$c               = [];
					$c['name']       = $name;
					$c['fullName']   = $column->getFullName();
					$c['methodName'] = $this->asMethodName($name);
					$c['columnType'] = $this->types_map[$type_const][0];
					$c['returnType'] = $this->types_map[$type_const][1];
					$c['argName']    = $name;
					$c['argType']    = $c['returnType'];

					$inject['columns'][] = $c;
				}

				file_put_contents($table_class_path, $ot->runGet($inject));
				file_put_contents($entity_class_path, $oe->runGet($inject));
				file_put_contents($results_class_path, $of->runGet($inject));
			}

			return $this;
		}

		/**
		 * Converts string to php class or method name.
		 *
		 * example:
		 *    my_table_name => MyTableName
		 *    my_column_name => MyColumnName
		 *
		 * @param string $str the table or column name
		 *
		 * @return string
		 */
		private function asMethodName($str)
		{
			return implode('', array_map('ucfirst', explode('_', $str)));
		}
	}