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
use Gobl\Gobl;
use InvalidArgumentException;
use OTpl\OTpl;
use RuntimeException;

abstract class Generator
{
	private static $tpl_ext = '.otpl';

	private static $templates_cache;

	protected $types_map = [
		TypeInterface::TYPE_INT    => ['int', 'int'],
		TypeInterface::TYPE_BIGINT => ['bigint', 'string'],
		TypeInterface::TYPE_STRING => ['string', 'string'],
		TypeInterface::TYPE_FLOAT  => ['float', 'string'],
		TypeInterface::TYPE_BOOL   => ['bool', 'bool'],
	];

	/** @var \Gobl\DBAL\Db */
	protected $db;

	protected $ignore_private_table;

	protected $ignore_private_column;

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
				'name'     => $table->getName(),
				'singular' => $table->getSingularName(),
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
	protected function writeFile($path, $content, $overwrite = true)
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
			$path = Gobl::TEMPLATES_DIR . \DIRECTORY_SEPARATOR . $name . self::$tpl_ext;
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
		$cache_file = Gobl::TEMPLATES_DIR . \DIRECTORY_SEPARATOR . 'gobl.templates.cache.json';

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

					\file_put_contents(Gobl::TEMPLATES_DIR . \DIRECTORY_SEPARATOR . $name . self::$tpl_ext, $output);
				}
			} else {
				throw new InvalidArgumentException(\sprintf('Template path "%s" is not a valid file path.', $item['path']));
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
						'//@'                    => '',
						'MY_DB_NS'               => '<%$.namespace%>',
						'MyTableQuery'           => '<%$.class.query%>',
						'MyEntity'               => '<%$.class.entity%>',
						'MyResults'              => '<%$.class.results%>',
						'MyController'           => '<%$.class.controller%>',
						'my_table'               => '<%$.table.name%>',
						'my_entity'              => '<%$.table.singular%>',
						'my_id'                  => '<%$.pk_columns[0].fullName%>',
						'\'my_pk_column_const\'' => '<%$.class.entity%>::<%$.pk_columns[0].const%>',

					] + $replaces;

		$search      = \array_keys($replaces);
		$replacement = \array_values($replaces);

		return \str_replace($search, $replacement, $source);
	}

	/**
	 * Generate classes for tables in the database.
	 *
	 * @param Table[] $tables the tables list
	 * @param string  $path   the destination folder path
	 * @param string  $header the source header to use
	 *
	 * @return $this
	 * @throws \Exception
	 *
	 */
	abstract public function generate(array $tables, $path, $header = '');
}
