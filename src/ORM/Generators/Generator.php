<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\ORM\Generators;

use Exception;
use Gobl\DBAL\Column;
use Gobl\DBAL\Db;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Utils;
use InvalidArgumentException;
use OTpl\OTpl;
use RuntimeException;

if (!\defined('GOBL_ROOT')) {
	\define('GOBL_ROOT', \realpath(__DIR__ . '/../../..'));
}

if (!\defined('GOBL_TEMPLATE_DIR')) {
	\define('GOBL_TEMPLATE_DIR', \realpath(GOBL_ROOT . '/templates'));
}

if (!\defined('GOBL_SAMPLE_DIR')) {
	\define('GOBL_SAMPLE_DIR', \realpath(__DIR__ . '/../Sample'));
}

class Generator
{
	private static $tpl_ext = '.otpl';

	private static $templates_cache;

	private $types_map = [
		TypeInterface::TYPE_INT    => ['int', 'int'],
		TypeInterface::TYPE_BIGINT => ['bigint', 'string'],
		TypeInterface::TYPE_STRING => ['string', 'string'],
		TypeInterface::TYPE_FLOAT  => ['float', 'string'],
		TypeInterface::TYPE_BOOL   => ['bool', 'bool'],
	];

	/** @var \Gobl\DBAL\Db */
	private $db;

	private $ignore_private_table;

	private $ignore_private_column;

	/**
	 * Generator constructor.
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
	 * @throws \Exception
	 *
	 * @return $this
	 */
	public function generateORMClasses(array $tables, $path, $header = '')
	{
		if (!\file_exists($path) || !\is_dir($path)) {
			throw new InvalidArgumentException(\sprintf('"%s" is not a valid directory path.', $path));
		}

		$ds = \DIRECTORY_SEPARATOR;

		$path_base = $path . $ds . 'Base';

		if (!\file_exists($path_base)) {
			\mkdir($path_base);
		}

		$base_query_class_tpl   = self::getTemplateCompiler('base.query.class');
		$base_entity_class_tpl  = self::getTemplateCompiler('base.entity.class');
		$base_results_class_tpl = self::getTemplateCompiler('base.results.class');
		$base_ctrl_class_tpl    = self::getTemplateCompiler('base.controller.class');

		$query_class_tpl   = self::getTemplateCompiler('query.class');
		$entity_class_tpl  = self::getTemplateCompiler('entity.class');
		$results_class_tpl = self::getTemplateCompiler('results.class');
		$ctrl_class_tpl    = self::getTemplateCompiler('controller.class');

		$time = \time();

		foreach ($tables as $table) {
			$inject           = $this->describeTable($table);
			$inject['header'] = $header;
			$inject['time']   = $time;
			$query_class      = $inject['class']['query'];
			$entity_class     = $inject['class']['entity'];
			$results_class    = $inject['class']['results'];
			$ctrl_class       = $inject['class']['controller'];

			$this->writeFile($path_base . $ds . $query_class . '.php', $base_query_class_tpl->runGet($inject));
			$this->writeFile($path_base . $ds . $entity_class . '.php', $base_entity_class_tpl->runGet($inject));
			$this->writeFile($path_base . $ds . $results_class . '.php', $base_results_class_tpl->runGet($inject));
			$this->writeFile($path_base . $ds . $ctrl_class . '.php', $base_ctrl_class_tpl->runGet($inject));

			$this->writeFile($path . $ds . $query_class . '.php', $query_class_tpl->runGet($inject), false);
			$this->writeFile($path . $ds . $entity_class . '.php', $entity_class_tpl->runGet($inject), false);
			$this->writeFile($path . $ds . $results_class . '.php', $results_class_tpl->runGet($inject), false);
			$this->writeFile($path . $ds . $ctrl_class . '.php', $ctrl_class_tpl->runGet($inject), false);
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
	 * @throws \Exception
	 *
	 * @return $this
	 */
	public function generateTSClasses(array $tables, $path, $header = '')
	{
		if (!\file_exists($path) || !\is_dir($path)) {
			throw new InvalidArgumentException(\sprintf('"%s" is not a valid directory path.', $path));
		}

		$ds = \DIRECTORY_SEPARATOR;

		$path_gobl = $path . $ds . 'gobl';
		$path_entities = $path_gobl . $ds . 'entities';

		if (!\file_exists($path_entities)) {
			\mkdir($path_entities, 0755, true);
		}

		$ts_entity_class_tpl = self::getTemplateCompiler('ts.entity.class');
		$ts_bundle_tpl       = self::getTemplateCompiler('ts.bundle');
		$bundle_inject       = [];
		$time                = \time();

		foreach ($tables as $table) {
			if (!($table->isPrivate() && $this->ignore_private_table)) {
				$inject                 = $this->describeTable($table);
				$inject['header']       = $header;
				$inject['time']         = $time;
				$entity_class           = $inject['class']['entity'];
				$inject['columns_list'] = \implode('|', \array_keys($inject['columns']));

				foreach ($inject['columns'] as $column) {
					$inject['columns_prefix'] = $column['prefix'];

					break;
				}

				$entity_content = $ts_entity_class_tpl->runGet($inject);
				$bundle_inject['entities'][$entity_class] = $entity_content;
				$this->writeFile($path_entities . $ds . $entity_class . '.ts', $entity_content, true);
			}
		}

		$bundle_inject['header'] = $header;
		$bundle_inject['time']   = $time;

		$this->writeFile($path_gobl . $ds . 'index.ts', $ts_bundle_tpl->runGet($bundle_inject), true);

		return $this;
	}

	/**
	 * Returns array that can be used to generate file.
	 *
	 * @param \Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	public function describeTable(Table $table)
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
	public function describeTableColumns(Table $table)
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
	public function describeColumn(Column $column)
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
			'const'        => 'COL_' . \strtoupper($name),
			'columnType'   => $this->types_map[$type_const][0],
			'returnType'   => $this->types_map[$type_const][1],
			'argName'      => $name,
			'argType'      => $this->types_map[$type_const][1],
		];
	}

	/**
	 * Gets relations data to be used in template file for a given table.
	 *
	 * @param \Gobl\DBAL\Table $table the table object
	 *
	 * @return array
	 */
	public function describeTableRelations(Table $table)
	{
		$use            = [];
		$relation_types = [
			Relation::ONE_TO_ONE   => 'one-to-one',
			Relation::ONE_TO_MANY  => 'one-to-many',
			Relation::MANY_TO_ONE  => 'many-to-one',
			Relation::MANY_TO_MANY => 'many-to-many',
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

			if ($host_table->hasColumn(\key($c))) {
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

			$use[] = $r['target']['class']['use_controller'] . ' as ' . $r['target']['class']['controller'] . 'RealR';

			$list[] = $r;
		}

		$result['use']  = \array_unique($use);
		$result['list'] = $list;

		return $result;
	}

	/**
	 * Gets data to be used in template file for a given table.
	 *
	 * @param \Gobl\DBAL\Table $table the table object
	 *
	 * @return array
	 */
	public function getTableInject(Table $table)
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
				'use_query'      => $ns . '\\' . $query_class_name,
				'use_entity'     => $ns . '\\' . $entity_class_name,
				'use_results'    => $ns . '\\' . $results_class_name,
				'use_controller' => $ns . '\\' . $controller_class_name,
			],
			'table'      => [
				'name' => $table->getName(),
			],
		];
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
		if (!$overwrite && \file_exists($path)) {
			return;
		}

		\file_put_contents($path, $content);
	}

	/**
	 * Gets template compiler instance for a given template name.
	 *
	 * @param string $name the template name
	 *
	 * @return \OTpl\OTpl
	 */
	public static function getTemplateCompiler($name)
	{
		try {
			$path = GOBL_TEMPLATE_DIR . \DIRECTORY_SEPARATOR . $name . self::$tpl_ext;
			$o    = new OTpl();
			$o->parse($path);
		} catch (Exception $e) {
			throw new RuntimeException('Template compile error.', null, $e);
		}

		return $o;
	}

	/**
	 * Add or overwrite a template.
	 *
	 * @param string $name
	 * @param string $path
	 * @param array  $replaces
	 */
	public static function setTemplate($name, $path, $replaces = [])
	{
		self::setTemplates([
			$name => [
				'path'     => $path,
				'replaces' => $replaces,
			],
		]);
	}

	/**
	 * Add or overwrite template list.
	 *
	 * @param array $list
	 */
	public static function setTemplates(array $list)
	{
		$cache_file = GOBL_TEMPLATE_DIR . \DIRECTORY_SEPARATOR . 'gobl.templates.cache.json';

		if (!isset(self::$templates_cache)) {
			if (\file_exists($cache_file)) {
				self::$templates_cache = \json_decode(\file_get_contents($cache_file), true);
			} else {
				self::$templates_cache = [];
			}
		}

		$changed = false;

		foreach ($list as $name => $item) {
			$path     = \realpath($item['path']);
			$replaces = isset($item['replaces']) ? $item['replaces'] : [];

			if (\file_exists($path) && \is_file($path)) {
				$sum = \md5($name . \json_encode($replaces) . \md5_file($path));

				if (!isset(self::$templates_cache[$path]['md5']) || self::$templates_cache[$path]['md5'] !== $sum) {
					$changed = true;

					self::$templates_cache[$name] = ['path' => $path, 'md5' => $sum];

					$output = self::toTemplate(\file_get_contents($path), $replaces);

					\file_put_contents(GOBL_TEMPLATE_DIR . \DIRECTORY_SEPARATOR . $name . self::$tpl_ext, $output);
				}
			} else {
				throw new InvalidArgumentException(\sprintf('Template path "%s" is not a valid file path.', $path));
			}
		}

		if ($changed) {
			\file_put_contents($cache_file, \json_encode(self::$templates_cache));
		}
	}

	/**
	 * Converts source code to templates.
	 *
	 * @param string $source
	 * @param array  $replaces
	 *
	 * @return string|string[]
	 */
	public static function toTemplate($source, $replaces = [])
	{
		$replaces = [
			'//__GOBL_HEAD_COMMENT__'               => '<%@import(\'include/head.comment.otpl\',$)%>',
			'//__GOBL_RELATIONS_USE_CLASS__'        => '<%@import(\'include/entity.relations.use.class.otpl\',$)%>',
			'//__GOBL_COLUMNS_CONST__'              => '<%@import(\'include/entity.columns.const.otpl\',$)%>',
			'//__GOBL_RELATIONS_PROPERTIES__'       => '<%@import(\'include/entity.relations.properties.otpl\',$)%>',
			'//__GOBL_RELATIONS_GETTERS__'          => '<%@import(\'include/entity.relations.getters.otpl\',$)%>',
			'//__GOBL_COLUMNS_GETTERS_SETTERS__'    => '<%@import(\'include/entity.getters.setters.otpl\',$)%>',
			'//__GOBL_QUERY_FILTER_BY_COLUMNS__'    => '<%@import(\'include/query.filter.by.columns.otpl\',$)%>',
			'//__GOBL_TS_COLUMNS_CONST__'           => '<%@import(\'include/ts.columns.const.otpl\',$)%>',
			'//__GOBL_TS_COLUMNS_GETTERS_SETTERS__' => '<%@import(\'include/ts.getters.setters.otpl\',$)%>',
			'//__GOBL_TS_ENTITIES_IMPORT__'         => '<%@import(\'include/ts.entities.import.otpl\',$)%>',
			'//__GOBL_TS_ENTITIES_EXPORT__'         => '<%@import(\'include/ts.entities.export.otpl\',$)%>',
			'//__GOBL_TS_ENTITIES_REGISTER__'       => '<%@import(\'include/ts.entities.register.otpl\',$)%>',
			'//__GOBL_VERSION__'                    => \trim(\file_get_contents(GOBL_ROOT . '/VERSION')),

			'MY_DB_NS'               => '<%$.namespace%>',
			'MyTableQuery'           => '<%$.class.query%>',
			'MyEntity'               => '<%$.class.entity%>',
			'MyResults'              => '<%$.class.results%>',
			'MyController'           => '<%$.class.controller%>',
			'my_table'               => '<%$.table.name%>',
			'my_id'                  => '<%$.pk_columns[0].fullName%>',
			'\'my_pk_column_const\'' => '<%$.class.entity%>::<%$.pk_columns[0].const%>',

		] + $replaces;

		$search      = \array_keys($replaces);
		$replacement = \array_values($replaces);

		return \str_replace($search, $replacement, $source);
	}
}

Generator::setTemplates([
	'base.query.class'      => ['path' => GOBL_SAMPLE_DIR . '/php/Base/MyTableQuery.php'],
	'base.entity.class'     => ['path' => GOBL_SAMPLE_DIR . '/php/Base/MyEntity.php'],
	'base.results.class'    => ['path' => GOBL_SAMPLE_DIR . '/php/Base/MyResults.php'],
	'base.controller.class' => ['path' => GOBL_SAMPLE_DIR . '/php/Base/MyController.php'],
	'query.class'           => ['path' => GOBL_SAMPLE_DIR . '/php/MyTableQuery.php'],
	'entity.class'          => ['path' => GOBL_SAMPLE_DIR . '/php/MyEntity.php'],
	'results.class'         => ['path' => GOBL_SAMPLE_DIR . '/php/MyResults.php'],
	'controller.class'      => ['path' => GOBL_SAMPLE_DIR . '/php/MyController.php'],
	'ts.bundle'             => ['path' => GOBL_SAMPLE_DIR . '/ts/TSBundle.ts'],
	'ts.entity.class'       => ['path' => GOBL_SAMPLE_DIR . '/ts/MyEntity.ts'],
]);
