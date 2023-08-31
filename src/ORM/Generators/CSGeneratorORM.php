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

use Gobl\CRUD\CRUDEventProducer;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\Events\ORMTableFilesGenerated;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMResults;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use Gobl\ORM\Utils\ORMClassKind;
use OLIUP\CG\PHPClass;
use OLIUP\CG\PHPFile;
use OLIUP\CG\PHPNamespace;
use OLIUP\CG\PHPType;
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
		$this->editable_header     = "Auto generated file,

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
	public function generate(array $tables, string $path, string $header = ''): static
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

			(new ORMTableFilesGenerated($table, $files))->dispatch();

			foreach (ORMClassKind::cases() as $kind) {
				$code       = (string) $files[$kind->value];
				$class_name = $kind->getClassName($table);
				$is_base    = $kind->isBaseClass();

				$this->writeFile(($is_base ? $path_base : $path) . $ds . $class_name . '.php', $code, $is_base);
			}
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toTypeHintString(ORMTypeHint $type_hint): string
	{
		if ($php_type = $type_hint->getPHPType()) {
			return (string) $php_type;
		}

		$types     = $type_hint->getUniversalTypes();
		$php_types = [];

		foreach ($types as $type) {
			$php_types[] = match ($type) {
				ORMUniversalType::ARRAY, ORMUniversalType::MAP => 'array',
				ORMUniversalType::DECIMAL, ORMUniversalType::STRING, ORMUniversalType::BIGINT => 'string',
				ORMUniversalType::BOOL  => 'bool',
				ORMUniversalType::FLOAT => 'float',
				ORMUniversalType::INT   => 'int',
				ORMUniversalType::NULL  => 'null',
				ORMUniversalType::MIXED => 'mixed',
			};
		}

		return \implode('|', $php_types);
	}

	private function getClassFile(Table $table, ORMClassKind $kind): PHPFile
	{
		$file = match ($kind) {
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

		$comment = $kind->isBaseClass() ? $this->not_editable_header : $this->editable_header;

		$file->setComment($comment);

		return $file;
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

		return $file->setContent($namespace);
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

		$class->setComment(
			Str::interpolate(
				'Class {class_name}.

@psalm-suppress UndefinedThisPropertyFetch' . \PHP_EOL,
				$inject
			)
		);

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(
	self::TABLE_NAMESPACE,
	self::TABLE_NAME,
	$is_new,
	$strict
);
',
					$inject
				)
			);
		$construct->setComment(
			Str::interpolate(
				'{class_name} constructor.

@param bool $is_new true for new entity false for entity fetched
                     from the database, default is true
@param bool $strict Enable/disable strict mode',
				$inject
			)
		);

		$construct->newArgument('is_new')
			->setType('bool')
			->setValue(true);
		$construct->newArgument('strict')
			->setType('bool')
			->setValue(true);

		$create_instance = $class->newMethod('createInstance')
			->static()
			->public()
			->addChild(
				Str::interpolate(
					'return new \{db_namespace}\{class_name}($is_new, $strict);',
					$inject
				)
			)
			->setReturnType('static');
		$create_instance->addArgument($construct->getArgument('is_new'));
		$create_instance->addArgument($construct->getArgument('strict'));

		$create_instance->comment(
			Str::interpolate(
				'@inheritDoc

@return static',
				$inject
			)
		);

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
				'read_type_hint'    => $this->getColumnReadTypeHintString($column),
				'write_type_hint'   => $this->getColumnWriteTypeHintString($column),
			];

			$class->getComment()
				?->addLines(
					Str::interpolate(
						'@property {read_type_hint} ${column_name} Getter for column `{table_name}`.`{column_name}`.',
						$col_inject
					)
				);
			$class->newConstant($column_const, $column->getFullName())
				->public();

			$get = $class->newMethod(self::propertyGetterName($column_name))
				->public()
				->setComment(
					Str::interpolate(
						'Getter for column `{table_name}`.`{column_name}`.

@return {read_type_hint}',
						$col_inject
					)
				)
				->setContent(Str::interpolate('return $this->{self::{column_name_const}};', $col_inject));

			$get->setReturnType($col_inject['read_type_hint']);

			$set = $class->newMethod('set' . Str::toClassName($column_name))
				->public()
				->setComment(
					Str::interpolate(
						'Setter for column `{table_name}`.`{column_name}`.

@param {write_type_hint} ${column_name}

@return static',
						$col_inject
					)
				)
				->setContent(
					Str::interpolate(
						'$this->{self::{column_name_const}} = ${column_name};

return $this;',
						$col_inject
					)
				);

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
			$comment       = \sprintf(
				'%s relation between `%s` and `%s`.',
				Str::toClassName($relation_type->value),
				$host->getName(),
				$target->getName()
			);
			$rel_inject    = [
				'target_entity_class_fqn'            => ORMClassKind::ENTITY->getClassFQN($target),
				'target_entity_controller_class_fqn' => ORMClassKind::CONTROLLER->getClassFQN($target),
				'relation_name'                      => $relation->getName(),
			];

			if ($relation_type->isMultiple()) {
				$comment .= Str::interpolate(
					'

@param array    $filters  the row filters
@param null|int $max      maximum row to retrieve
@param int      $offset   first row offset
@param array    $order_by order by rules
@param null|int $total    total rows without limit

@throws \Gobl\CRUD\Exceptions\CRUDException
@return {target_entity_class_fqn}[]',
					$rel_inject
				);

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

				$m->addChild(
					Str::interpolate(
						'return (new {target_entity_controller_class_fqn}())->getAllRelatives(
	$this,
	$this->_oeb_table->getRelation(\'{relation_name}\'),
	$filters,
	$max,
	$offset,
	$order_by,
	$total
);',
						$rel_inject
					)
				);
			} else {
				$comment .= Str::interpolate(
					'

@throws \Gobl\CRUD\Exceptions\CRUDException
@return ?{target_entity_class_fqn}',
					$rel_inject
				);
				$m->setReturnType(new PHPType('null', $rel_inject['target_entity_class_fqn']));
				$m->addChild(
					Str::interpolate(
						'return (new {target_entity_controller_class_fqn}())->getRelative(
	$this,
	$this->_oeb_table->getRelation(\'{relation_name}\')
);',
						$rel_inject
					)
				);
			}

			$m->setComment($comment);
		}

		return $file->setContent($namespace);
	}

	/**
	 * Returns property getter name.
	 *
	 * @param string $column_name
	 *
	 * @return string
	 */
	private static function propertyGetterName(string $column_name): string
	{
		if (\str_starts_with($column_name, 'is_')) {
			$verb = \substr($column_name, 3);

			return 'is' . Str::toClassName($verb);
		}
		if (\str_ends_with($column_name, 'ed')) {
			return 'is' . Str::toClassName($column_name);
		}

		return 'get' . Str::toClassName($column_name);
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
		$class->extends(CRUDEventProducer::class)
			->comment(
				Str::interpolate(
					'Class {class_name}.

@extends \Gobl\CRUD\CRUDEventProducer<\{db_namespace}\{entity_class_name}>
',
					$inject
				)
			);

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME
);
',
					$inject
				)
			);

		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		return $file->setContent($namespace);
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
			->comment(
				Str::interpolate(
					'Class {entity_vr_class_name}.

@template TRelationResult
@extends \Gobl\DBAL\Relations\VirtualRelation<\{db_namespace}\{entity_class_name}, TRelationResult>
',
					$inject
				)
			);

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME,
	$name,
	$paginated
);
',
					$inject
				)
			);

		$construct->comment(
			Str::interpolate(
				'{class_name} constructor.
@param string $name      the relation name
@param bool   $paginated true if the relation returns paginated items',
				$inject
			)
		);

		$construct->newArgument('name')
			->setType('string');
		$construct->newArgument('paginated')
			->setType('bool');

		return $file->setContent($namespace);
	}

	private function getBaseQuery(Table $table): PHPFile
	{
		$db_ns      = $table->getNamespace();
		$class_name = ORMClassKind::BASE_QUERY->getClassName($table);
		$file       = new PHPFile();
		$namespace  = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class      = $namespace->newClass($class_name);

		$inject = [
			'class_name'        => $class->getName(),
			'entity_class_name' => ORMClassKind::ENTITY->getClassName($table),
			'db_namespace'      => $db_ns,
		];

		$namespace->use(Operator::class);

		$class_comment_lines   = [];
		$class_comment_lines[] = Str::interpolate('Class {class_name}.', $inject);
		$class_comment_lines[] = '';
		$class_comment_lines[] = Str::interpolate(
			'@extends \Gobl\ORM\ORMTableQuery<\{db_namespace}\{entity_class_name}>',
			$inject
		);

		$class->extends(new PHPClass(ORMTableQuery::class))
			->abstract();

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME
);
',
					$inject
				)
			);

		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$class->newMethod('createInstance')
			->static()
			->public()
			->addChild(Str::interpolate('return new \{db_namespace}\{class_name};', $inject))
			->setReturnType('static')
			->comment(
				Str::interpolate(
					'@inheritDoc

@return static',
					$inject
				)
			);

		foreach ($table->getColumns() as $column) {
			$type = $column->getType();
			foreach ($type->getAllowedFilterOperators() as $operator) {
				$method = Str::toMethodName('where_' . $operator->getFilterSuffix($column));

				$col_inject = $inject + [
					'rule_name'   => $operator->value,
					'table_name'  => $table->getName(),
					'column_name' => $column->getName(),
				];

				$comment = Str::interpolate(
					'Filters rows with `{rule_name}` condition on column `{table_name}`.`{column_name}`.',
					$col_inject
				);

				$has_no_arg = 1 === $operator->getOperandsCount();

				if ($has_no_arg) {
					$class_comment_lines[] = '@method $this ' . $method . '() ' . $comment;
				} else {
					$arg_type              = $this->toTypeHintString(
						ORMTypeHint::getOperatorRightOperandTypesHint($type, $operator)
					);
					$class_comment_lines[] = '@method $this ' . $method . '(' . $arg_type . ' $value) ' . $comment;
				}
			}
		}

		$class->setComment(\implode(\PHP_EOL, $class_comment_lines));

		$file->setContent($namespace);

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
			->setComment(
				Str::interpolate(
					'Class {class_name}.

@extends \Gobl\ORM\ORMResults<\{db_namespace}\{entity_class_name}>',
					$inject
				)
			);

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME,
	$query
);
',
					$inject
				)
			);

		$construct->newArgument('query')
			->setType(new PHPClass(QBSelect::class));
		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$create_instance = $class->newMethod('createInstance')
			->static()
			->public()
			->addChild(
				Str::interpolate('return new \{db_namespace}\{class_name}($query);', $inject)
			)
			->setReturnType('static');
		$create_instance->newArgument('query')
			->setType(new PHPClass(QBSelect::class));
		$create_instance->comment(
			Str::interpolate(
				'@inheritDoc

@return static',
				$inject
			)
		);

		$file->setContent($namespace);

		return $file;
	}

	private function getBaseController(Table $table): PHPFile
	{
		$db_ns              = $table->getNamespace();
		$class_name         = ORMClassKind::BASE_CONTROLLER->getClassName($table);
		$entity_class_name  = ORMClassKind::ENTITY->getClassName($table);
		$results_class_name = ORMClassKind::RESULTS->getClassName($table);
		$query_class_name   = ORMClassKind::QUERY->getClassName($table);
		$file               = new PHPFile();
		$namespace          = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class              = $namespace->newClass($class_name);

		$inject = [
			'class_name'         => $class->getName(),
			'entity_class_name'  => $entity_class_name,
			'results_class_name' => $results_class_name,
			'query_class_name'   => $query_class_name,
			'db_namespace'       => $db_ns,
		];

		$namespace->use(QBSelect::class)
			->use(ORMEntity::class)
			->use(Relation::class);

		$class->extends(new PHPClass(ORMController::class))
			->abstract()
			->setComment(
				Str::interpolate(
					'Class {class_name}.

@extends \Gobl\ORM\ORMController<\{db_namespace}\{entity_class_name}, \{db_namespace}\{query_class_name}, \{db_namespace}\{results_class_name}>
',
					$inject
				)
			);

		$class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(
	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,
	\{db_namespace}\{entity_class_name}::TABLE_NAME
);
',
					$inject
				)
			)
			->comment(Str::interpolate('{class_name} constructor.', $inject));

		$class->newMethod('createInstance')
			->static()
			->public()
			->addChild(Str::interpolate('return new \{db_namespace}\{class_name}();', $inject))
			->setReturnType('static')
			->comment(
				Str::interpolate(
					'@inheritDoc

@return static',
					$inject
				)
			);

		return $file->setContent($namespace);
	}
}
