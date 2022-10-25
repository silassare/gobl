<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\ORM\Generators;

use Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\Events\ORMTableFilesGenerated;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMRequest;
use Gobl\ORM\ORMResults;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\Utils\ORMClassKind;
use Gobl\ORM\Utils\ORMTypeHint;
use OLIUP\CG\PHPClass;
use OLIUP\CG\PHPFile;
use OLIUP\CG\PHPNamespace;
use OLIUP\CG\PHPType;
use PHPUtils\Events\Event;
use PHPUtils\FS\FSUtils;
use PHPUtils\Str;

/**
 * Class CSGeneratorORM.
 */
class CSGeneratorORM extends CSGenerator
{
	private string $editable_body_comment;
	private string $not_editable_header;
	private string $editable_header;

	/**
	 * CSGeneratorORM constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 * @param bool                                 $ignore_private_table
	 * @param bool                                 $ignore_private_column
	 */
	public function __construct(
		RDBMSInterface $db,
		bool $ignore_private_table = true,
		bool $ignore_private_column = true
	) {
		parent::__construct($db, $ignore_private_table, $ignore_private_column);

		$version                   = GOBL_VERSION;
		$date                      = Gobl::getGeneratedAtDate();
		$this->not_editable_header = "Auto generated file

WARNING: please don't edit.

Proudly With: {$version}
Time: {$date}";
		$this->editable_header = "Auto generated file,

INFO: you are free to edit it,
but make sure to know what you are doing.

Proudly With: {$version}
Time: {$date}";

		$this->editable_body_comment = '
//====================================================
//=	Your custom implementation goes here
//====================================================
';
	}

	/**
	 * {@inheritDoc}
	 */
	public function toTypeHintString(ORMTypeHint $type_hint): string
	{
		return match ($type_hint) {
			ORMTypeHint::ARRAY, ORMTypeHint::MAP => 'array',
			ORMTypeHint::DECIMAL, ORMTypeHint::STRING, ORMTypeHint::BIGINT => 'string',
			ORMTypeHint::BOOL  => 'bool',
			ORMTypeHint::FLOAT => 'float',
			ORMTypeHint::INT   => 'int',
			ORMTypeHint::_NULL => 'null',
			ORMTypeHint::MIXED => 'mixed',
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate(array $tables, string $path, string $header = ''): self
	{
		$fs = new FSUtils($path);

		$fs->filter()
			->isDir()
			->isWritable()
			->assert('.');

		$path      = $fs->getRoot();
		$ds        = \DIRECTORY_SEPARATOR;
		$path_base = $path . $ds . 'Base';

		$fs->mkdir($path_base);

		foreach ($tables as $table) {
			$files = [];
			foreach (ORMClassKind::cases() as $kind) {
				$files[$kind->value] = $this->getClassFile($table, $kind);
			}

			Event::trigger(new ORMTableFilesGenerated($table, $files));

			foreach (ORMClassKind::cases() as $kind) {
				$code       = (string) $files[$kind->value];
				$class_name = $kind->getClassName($table);
				$is_base    = $kind->isBaseClass();

				$this->writeFile(($is_base ? $path_base : $path) . $ds . $class_name . '.php', $code, $is_base);
			}
		}

		return $this;
	}

	public function getClassFile(Table $table, ORMClassKind $kind): PHPFile
	{
		return match ($kind) {
			ORMClassKind::ENTITY,
			ORMClassKind::ENTITY_VR,
			ORMClassKind::CRUD,
			ORMClassKind::CONTROLLER,
			ORMClassKind::RESULTS,
			ORMClassKind::QUERY           => $this->getExtendsOf($table, $kind),
			ORMClassKind::BASE_ENTITY     => $this->getBaseEntity($table),
			ORMClassKind::BASE_CRUD       => $this->getBaseCRUD($table),
			ORMClassKind::BASE_ENTITY_VR  => $this->getBaseEntityVR($table),
			ORMClassKind::BASE_QUERY      => $this->getBaseQuery($table),
			ORMClassKind::BASE_RESULTS    => $this->getBaseResults($table),
			ORMClassKind::BASE_CONTROLLER => $this->getBaseController($table),
		};
	}

	private function getExtendsOf(Table $table, ORMClassKind $kind): PHPFile
	{
		$db_ns      = $table->getNamespace();
		$class_name = $kind->getClassName($table);
		$file       = new PHPFile();
		$namespace  = new PHPNamespace($db_ns);
		$class      = $namespace->newClass($class_name);

		$inject = [
			'class_name'   => $class_name,
			'db_namespace' => $db_ns,
		];

		$class->extends(Str::interpolate('{db_namespace}\Base\{class_name}', $inject))
			->setContent($this->editable_body_comment)
			->comment(Str::interpolate('Class {class_name}.', $inject));

		if (ORMClassKind::CRUD === $kind || ORMClassKind::ENTITY_VR === $kind) {
			$class->abstract();
		}

		return $file->setContent($namespace)
			->setComment($this->editable_header);
	}

	private function getBaseEntity(Table $table): PHPFile
	{
		$db_ns      = $table->getNamespace();
		$class_name = ORMClassKind::BASE_ENTITY->getClassName($table);
		$file       = new PHPFile();
		$namespace  = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class      = $namespace->newClass($class_name);

		$inject = [
			'class_name'   => $class->getName(),
			'db_namespace' => $db_ns,
		];

		$class->extends(new PHPClass(ORMEntity::class))
			->abstract();

		$class->setComment(Str::interpolate('Class {class_name}.' . \PHP_EOL, $inject));

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(Str::interpolate('parent::__construct(
	self::TABLE_NAMESPACE,
	self::TABLE_NAME,
	$is_new,
	$strict
);
', $inject));
		$construct->setComment(Str::interpolate('{class_name} constructor.

@param bool $is_new true for new entity false for entity fetched
                     from the database, default is true
@param bool $strict Enable/disable strict mode', $inject));

		$construct->newArgument('is_new')
			->setType('bool')
			->setValue(true);
		$construct->newArgument('strict')
			->setType('bool')
			->setValue(true);

		$create_instance = $class->newMethod('createInstance')
			->static()
			->public()
			->addChild(Str::interpolate('return new \{db_namespace}\{class_name}($is_new, $strict);', $inject))
			->setReturnType('static');
		$create_instance->addArgument($construct->getArgument('is_new'));
		$create_instance->addArgument($construct->getArgument('strict'));

		$create_instance->comment(Str::interpolate('@inheritDoc

@return static', $inject));

		$class->newConstant('TABLE_NAME', $table->getName())
			->public();
		$class->newConstant('TABLE_NAMESPACE', $db_ns)
			->public();

		foreach ($table->getColumns() as $column) {
			$column_name  = $column->getName();
			$column_const = self::toColumnNameConst($column);
			$col_inject   = $inject + [
				'table_name'        => $table->getName(),
				'column_name'       => $column_name,
				'column_name_const' => $column_const,
				'read_type_hint'    => \implode('|', $this->getColumnReadTypeHintStrings($column)),
				'write_type_hint'   => \implode('|', $this->getColumnWriteTypeHintStrings($column)),
			];

			$class->getComment()
				?->addLines(Str::interpolate(
					'@property-read {read_type_hint} ${column_name} Getter for column `{table_name}`.`{column_name}`.',
					$col_inject
				));
			$class->newConstant($column_const, $column->getFullName())
				->public();

			$get = $class->newMethod('get' . Str::toClassName($column_name))
				->public()
				->setComment(Str::interpolate('Getter for column `{table_name}`.`{column_name}`.

@return {read_type_hint}', $col_inject))
				->setContent(Str::interpolate('return $this->{self::{column_name_const}};', $col_inject));

			$get->setReturnType($col_inject['read_type_hint']);

			$set = $class->newMethod('set' . Str::toClassName($column_name))
				->public()
				->setComment(Str::interpolate('Setter for column `{table_name}`.`{column_name}`.

@param {write_type_hint} ${column_name}

@return static', $col_inject))
				->setContent(Str::interpolate('$this->{self::{column_name_const}} = ${column_name};

return $this;', $col_inject));

			$set->setReturnType('static')
				->newArgument($column_name)
				->setType($col_inject['write_type_hint']);
		}

		foreach ($table->getRelations() as $relation) {
			$relation_type = $relation->getType();
			$host          = $relation->getHostTable();
			$target        = $relation->getTargetTable();
			$m             = $class->newMethod('get' . Str::toClassName($relation->getName()))
				->public();
			$comment = \sprintf(
				'%s relation between `%s` and `%s`.',
				Str::toClassName($relation_type->value),
				$host->getName(),
				$target->getName()
			);
			$rel_inject = [
				'target_entity_class_fqn'            => ORMClassKind::ENTITY->getClassFQN($target),
				'target_entity_controller_class_fqn' => ORMClassKind::CONTROLLER->getClassFQN($target),
			];

			$filters = [];
			foreach ($relation->getRelationColumns() as $host_col_name => $target_column_name) {
				$h_col     = $host->getColumnOrFail($host_col_name);
				$t_col     = $target->getColumnOrFail($target_column_name);
				$filters[] = Str::interpolate(
					'{target_entity_class_fqn}::{target_column_const} => $this->get{host_column_getter}(...),',
					$rel_inject + [
						'target_column_const' => self::toColumnNameConst($t_col),
						'host_column_getter'  => Str::toClassName($h_col->getName()),
					]
				);
			}

			$rel_inject['filters_str'] = \implode(\PHP_EOL, $filters);

			if ($relation_type->isMultiple()) {
				$comment .= Str::interpolate('

@param array    $filters  the row filters
@param null|int $max      maximum row to retrieve
@param int      $offset   first row offset
@param array    $order_by order by rules
@param null|int $total    total rows without limit

@return {target_entity_class_fqn}[]', $rel_inject);

				$m->newArgument('filters')
					->setType('array')
					->setValue([]);
				$m->newArgument('max')
					->setType(new PHPType('null', 'int'))
					->setValue(null);
				$m->newArgument('offset')
					->setType('int')
					->setValue(0);
				$m->newArgument('order_by')
					->setType('array')
					->setValue([]);
				$m->newArgument('total')
					->setType(new PHPType('null', 'int'))
					->reference()
					->setValue(-1);

				$m->setReturnType('array');

				$m->addChild(Str::interpolate('$getters = [{filters_str}];
$filters_bundle = $this->buildRelationFilter($getters, $filters);

if (null === $filters_bundle) {
	return [];
}

return (new {target_entity_controller_class_fqn}())->getAllItems($filters_bundle, $max, $offset, $order_by, $total);', $rel_inject));
			} else {
				$comment .= Str::interpolate('

@return ?{target_entity_class_fqn}', $rel_inject);
				$m->setReturnType(new PHPType('null', $rel_inject['target_entity_class_fqn']));
				$m->addChild(Str::interpolate('$getters = [{filters_str}];
$filters_bundle = $this->buildRelationFilter($getters, []);

if (null === $filters_bundle) {
	return null;
}

return (new {target_entity_controller_class_fqn}())->getItem($filters_bundle);', $rel_inject));
			}

			$m->setComment($comment);
		}

		$file->setContent($namespace)
			->setComment($this->not_editable_header);

		return $file;
	}

	private function getBaseCRUD(Table $table): PHPFile
	{
		$db_ns             = $table->getNamespace();
		$class_name        = ORMClassKind::BASE_CRUD->getClassName($table);
		$entity_class_name = ORMClassKind::ENTITY->getClassName($table);
		$file              = new PHPFile();
		$namespace         = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class             = $namespace->newClass($class_name);

		$inject = [
			'class_name'        => $class->getName(),
			'entity_class_name' => $entity_class_name,
			'db_namespace'      => $db_ns,
		];
		$class->implements(CRUDHandlerInterface::class)
			->abstract()
			->comment(Str::interpolate('Class {class_name}.', $inject));

		$methods = [
			'onAfterCreateEntity',
			'onAfterReadEntity',
			'onBeforeUpdateEntity',
			'onAfterUpdateEntity',
			'onBeforeDeleteEntity',
			'onAfterDeleteEntity',
		];

		foreach ($methods as $method) {
			$m = $class->newMethod($method)
				->public()
				->abstract()
				->setReturnType('void');

			$m->comment(Str::interpolate('@inheritDoc
@param \{db_namespace}\{entity_class_name} $entity', $inject));
			$m->newArgument('entity');
		}

		return $file->setContent($namespace)
			->setComment($this->not_editable_header);
	}

	private function getBaseEntityVR(Table $table): PHPFile
	{
		$db_ns                = $table->getNamespace();
		$entity_vr_class_name = ORMClassKind::BASE_ENTITY_VR->getClassName($table);
		$entity_class_name    = ORMClassKind::ENTITY->getClassName($table);
		$file                 = new PHPFile();
		$namespace            = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class                = $namespace->newClass($entity_vr_class_name);

		$inject = [
			'entity_vr_class_name' => $class->getName(),
			'entity_class_name'    => $entity_class_name,
			'db_namespace'         => $db_ns,
		];

		$class->extends(VirtualRelation::class)
			->abstract()
			->comment(Str::interpolate('Class {entity_vr_class_name}.', $inject));

		$get = $class->newMethod('get')
			->public()
			->setReturnType('mixed');
		$get->comment('@inheritDoc');
		$get->newArgument('target')
			->setType(new PHPClass(ORMEntity::class));
		$get->newArgument('request')
			->setType(new PHPClass(ORMRequest::class));
		$get->newArgument('total_records')
			->setType(new PHPType('int', 'null'))
			->setValue(null)
			->reference();

		$get->addChild(Str::interpolate('if ($target instanceof \{db_namespace}\{entity_class_name}) {
	return $this->getItemRelation($target, $request, $total_records);
}
throw new \InvalidArgumentException(\'Target item should be an instance of: \' . \{db_namespace}\{entity_class_name}::class);
', $inject));

		$getItemRelation = clone $get;
		$getItemRelation->setName('getItemRelation')
			->abstract()
			->protected()
			->comment('Gets a relation for a given target item.');

		$class->addMethod($getItemRelation);

		return $file->setContent($namespace)
			->setComment($this->not_editable_header);
	}

	private function getBaseQuery(Table $table): PHPFile
	{
		$db_ns      = $table->getNamespace();
		$class_name = ORMClassKind::BASE_QUERY->getClassName($table);
		$file       = new PHPFile();
		$namespace  = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class      = $namespace->newClass($class_name);

		$inject = [
			'class_name'         => $class->getName(),
			'entity_class_name'  => ORMClassKind::ENTITY->getClassName($table),
			'results_class_name' => ORMClassKind::RESULTS->getClassName($table),
			'db_namespace'       => $db_ns,
		];

		$class->extends(new PHPClass(ORMTableQuery::class))
			->abstract()
			->setComment(Str::interpolate('Class {class_name}.

@method \{db_namespace}\{results_class_name} find(?int $max = null, int $offset = 0, array $order_by = [])', $inject));

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(Str::interpolate('parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME
);
', $inject));

		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$class->newMethod('createInstance')
			->static()
			->public()
			->addChild(Str::interpolate('return new \{db_namespace}\{class_name};', $inject))
			->setReturnType('static')
			->comment(Str::interpolate('@inheritDoc

@return static', $inject));

		$class->newMethod('subGroup')
			->public()
			->addChild('$instance              = new static();
$instance->qb          = $this->qb;
$instance->filters     = $this->filters->subGroup();
$instance->table_alias = $this->table_alias;

return $instance;')
			->setReturnType('static')
			->setComment('{@inheritDoc}');

		foreach ($table->getColumns() as $column) {
			$type = $column->getType();
			foreach ($type->getAllowedFilterOperators() as $operator) {
				$method = Str::toMethodName('where_' . $column->getName() . '_' . $operator->getFilterSuffix());

				$col_inject = $inject + [
					'rule_name'         => $operator->value,
					'table_name'        => $table->getName(),
					'column_name'       => $column->getName(),
					'column_name_const' => self::toColumnNameConst($column),
					'arg_type'          => \implode('|', \array_unique(\array_map($this->toTypeHintString(...), ORMTypeHint::getRightOperandTypesHint($type, $operator)))),
				];
				$no_arg = 1 === $operator->getOperandsCount();
				if ($no_arg) {
					$class->newMethod($method)
						->public()
						->setComment(Str::interpolate(
							'Filters rows with `{rule_name}` condition on column `{table_name}`.`{column_name}`.

@return static
@throws \Gobl\DBAL\Exceptions\DBALException',
							$col_inject
						))
						->setReturnType('self')
						->setContent(str::interpolate('return $this->filterBy(
	\Gobl\DBAL\Operator::from(\'{rule_name}\'),
	\{db_namespace}\{entity_class_name}::{column_name_const}
);', $col_inject));
				} else {
					$class->newMethod($method)
						->public()
						->setComment(Str::interpolate(
							'Filters rows with `{rule_name}` condition on column `{table_name}`.`{column_name}`.

@param {arg_type} $value the filter value

@return static
@throws \Gobl\DBAL\Exceptions\DBALException',
							$col_inject
						))
						->setReturnType('self')
						->setContent(str::interpolate('return $this->filterBy(
	\Gobl\DBAL\Operator::from(\'{rule_name}\'),
	\{db_namespace}\{entity_class_name}::{column_name_const},
	$value
);', $col_inject))
						->newArgument('value')
						->setType($col_inject['arg_type']);
				}
			}
		}

		$file->setContent($namespace)
			->setComment($this->not_editable_header);

		return $file;
	}

	private function getBaseResults(Table $table): PHPFile
	{
		$db_ns             = $table->getNamespace();
		$class_name        = ORMClassKind::BASE_RESULTS->getClassName($table);
		$entity_class_name = ORMClassKind::ENTITY->getClassName($table);
		$file              = new PHPFile();
		$namespace         = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class             = $namespace->newClass($class_name);

		$inject = [
			'class_name'        => $class->getName(),
			'entity_class_name' => $entity_class_name,
			'db_namespace'      => $db_ns,
		];

		$class->extends(new PHPClass(ORMResults::class))
			->abstract()
			->setComment(Str::interpolate('Class {class_name}.

@method null|\{db_namespace}\{entity_class_name} current()
@method null|\{db_namespace}\{entity_class_name} fetchClass(bool $strict = true)
@method \{db_namespace}\{entity_class_name}[] fetchAllClass(bool $strict = true)
@method null|\{db_namespace}\{entity_class_name} updateOneItem(array $filters, array $new_values)', $inject));

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(Str::interpolate('parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME,
	$query
);
', $inject));

		$construct->newArgument('query')
			->setType(new PHPClass(QBSelect::class));
		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$create_instance = $class->newMethod('createInstance')
			->static()
			->public()
			->addChild(Str::interpolate('return new \{db_namespace}\{class_name}($query);', $inject))
			->setReturnType('static');
		$create_instance->newArgument('query')
			->setType(new PHPClass(QBSelect::class));
		$create_instance->comment(Str::interpolate('@inheritDoc

@return static', $inject));

		$file->setContent($namespace)
			->setComment($this->not_editable_header);

		return $file;
	}

	private function getBaseController(Table $table): PHPFile
	{
		$db_ns             = $table->getNamespace();
		$class_name        = ORMClassKind::BASE_CONTROLLER->getClassName($table);
		$entity_class_name = ORMClassKind::ENTITY->getClassName($table);
		$file              = new PHPFile();
		$namespace         = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class             = $namespace->newClass($class_name);

		$inject = [
			'class_name'        => $class->getName(),
			'entity_class_name' => $entity_class_name,
			'db_namespace'      => $db_ns,
		];

		$class->extends(new PHPClass(ORMController::class))
			->abstract()
			->setComment(Str::interpolate('Class {class_name}.

@method \{db_namespace}\{entity_class_name} addItem(array|\{db_namespace}\{entity_class_name} $item = [])
@method null|\{db_namespace}\{entity_class_name} getItem(array $filters, array $order_by = [])
@method null|\{db_namespace}\{entity_class_name} deleteOneItem(array $filters)
@method \{db_namespace}\{entity_class_name}[] getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
@method \{db_namespace}\{entity_class_name}[] getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, ?int &$total = null)
@method null|\{db_namespace}\{entity_class_name} updateOneItem(array $filters, array $new_values)', $inject));

		$class->newMethod('__construct')
			->public()
			->addChild(Str::interpolate('parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME
);
', $inject))
			->comment(Str::interpolate('{class_name} constructor.', $inject));

		$class->newMethod('createInstance')
			->static()
			->public()
			->addChild(Str::interpolate('return new \{db_namespace}\{class_name}();', $inject))
			->setReturnType('static')
			->comment(Str::interpolate('@inheritDoc

@return static', $inject));

		$file->setContent($namespace)
			->setComment($this->not_editable_header);

		return $file;
	}
}
