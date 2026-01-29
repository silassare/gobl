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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\Exceptions\GoblException;
use Gobl\Gobl;
use Gobl\ORM\Events\ORMTableFilesGenerated;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMEntityCRUD;
use Gobl\ORM\ORMRequest;
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
	 * @param RDBMSInterface $db
	 */
	public function __construct(
		RDBMSInterface $db
	) {
		parent::__construct($db);

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
				$code = (string) $files[$kind->value];

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
				ORMUniversalType::ARRAY => 'array',
				ORMUniversalType::MAP   => '\\' . Map::class,
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
			ORMClassKind::CRUD,
			ORMClassKind::CONTROLLER,
			ORMClassKind::RESULTS,
			ORMClassKind::QUERY           => $this->getExtendsOf($table, $kind),
			ORMClassKind::BASE_ENTITY     => $this->getBaseEntity($table),
			ORMClassKind::BASE_CRUD       => $this->getBaseCRUD($table),
			ORMClassKind::BASE_QUERY      => $this->getBaseQuery($table),
			ORMClassKind::BASE_RESULTS    => $this->getBaseResults($table),
			ORMClassKind::BASE_CONTROLLER => $this->getBaseController($table),
		};

		if (!\defined('GOBL_TEST_MODE')) {
			$comment = $kind->isBaseClass() ? $this->not_editable_header : $this->editable_header;

			$file->setComment($comment);
		}

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
			'class_name'   => $class->getName(),
			'db_namespace' => $db_ns,
		];

		$base_class       = Str::interpolate('{db_namespace}\Base\{class_name}', $inject);
		$base_class_alias = Str::interpolate('{class_name}Base', $inject);

		$namespace->use($base_class, $base_class_alias);

		$class->extends($base_class_alias)
			->setContent($this->editable_body_comment)
			->comment(Str::interpolate('Class {class_name}.', $inject));

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
			'class_name'         => $class->getName(),
			'ctrl_class_name'    => ORMClassKind::CONTROLLER->getClassName($table),
			'qb_class_name'      => ORMClassKind::QUERY->getClassName($table),
			'results_class_name' => ORMClassKind::RESULTS->getClassName($table),
			'crud_class_name'    => ORMClassKind::CRUD->getClassName($table),
			'db_namespace'       => $db_ns,
		];

		$sub_class       = Str::interpolate('{db_namespace}\{class_name}', $inject);
		$sub_class_alias = Str::interpolate('{class_name}Real', $inject);

		$namespace->use($sub_class, $sub_class_alias);

		$class->extends(new PHPClass(ORMEntity::class))
			->abstract();

		$class->setComment(
			Str::interpolate(
				'Class {class_name}.',
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

		$create_instance = $class->newMethod('new')
			->static()
			->public()
			->addChild(
				Str::interpolate(
					'return new {class_name}Real($is_new, $strict);',
					$inject
				)
			)
			->setReturnType('static');

		$create_instance->addArgument($construct->getArgument('is_new'));
		$create_instance->addArgument($construct->getArgument('strict'));

		$create_instance->comment(
			Str::interpolate(
				'{@inheritDoc}

@return static',
				$inject
			)
		);

		$static_helpers = [
			'crud' => [
				'comment' => '{@inheritDoc}

@return \{db_namespace}\{crud_class_name}',
				'return' => '\{db_namespace}\{crud_class_name}',
				'body'   => 'return \{db_namespace}\{crud_class_name}::new();',
			],
			'ctrl' => [
				'comment' => '{@inheritDoc}

@return \{db_namespace}\{ctrl_class_name}',
				'return' => '\{db_namespace}\{ctrl_class_name}',
				'body'   => 'return \{db_namespace}\{ctrl_class_name}::new();',
			],
			'qb' => [
				'comment' => '{@inheritDoc}

@return \{db_namespace}\{qb_class_name}',
				'return' => '\{db_namespace}\{qb_class_name}',
				'body'   => 'return \{db_namespace}\{qb_class_name}::new();',
			],
			'results' => [
				'comment' => '{@inheritDoc}

@return \{db_namespace}\{results_class_name}',
				'return' => '\{db_namespace}\{results_class_name}',
				'body'   => 'return \{db_namespace}\{results_class_name}::new($query);',
				'args'   => [
					'query' => '\\' . QBSelect::class,
				],
			],
			'table' => [
				'comment' => '{@inheritDoc}',
				'return'  => '\\' . Table::class,
				'body'    => 'return \\' . Str::callableName(
					[ORM::class, 'table']
				) . '(static::TABLE_NAMESPACE, static::TABLE_NAME);',
			],
		];

		foreach ($static_helpers as $helper => $helper_opt) {
			$helper_method = $class->newMethod($helper)
				->static()
				->public()
				->addChild(
					Str::interpolate(
						$helper_opt['body'],
						$inject
					)
				)
				->setReturnType(
					Str::interpolate(
						$helper_opt['return'],
						$inject
					)
				);

			$helper_method->comment(
				Str::interpolate(
					$helper_opt['comment'],
					$inject
				)
			);

			$args = $helper_opt['args'] ?? [];

			foreach ($args as $arg_name => $arg_type) {
				$helper_method->newArgument($arg_name)
					->setType($arg_type);
			}
		}

		$class->newConstant('TABLE_NAME', $table->getName())
			->public();
		$class->newConstant('TABLE_NAMESPACE', $db_ns)
			->public();

		foreach ($table->getColumns() as $column) {
			$column_name  = $column->getName();
			$column_const = self::toColumnNameConst($column);
			$type         = $column->getType();
			$col_inject   = $inject + [
				'table_name'        => $table->getName(),
				'column_name'       => $column_name,
				'column_name_const' => $column_const,
				'read_type_hint'    => $this->getReadTypeHintString($type),
				'write_type_hint'   => $this->getWriteTypeHintString($type),
			];

			$class->getComment()
				?->addLines(
					Str::interpolate(
						'@property {read_type_hint} ${column_name} Getter for column `{table_name}.{column_name}`.',
						$col_inject
					)
				);
			$class->newConstant($column_const, $column->getFullName())
				->public();

			$get = $class->newMethod(self::propertyGetterName($column_name))
				->public()
				->setComment(
					Str::interpolate(
						'Getter for column `{table_name}.{column_name}`.

@return {read_type_hint}',
						$col_inject
					)
				)
				->setContent(Str::interpolate('return $this->{column_name};', $col_inject));

			$get->setReturnType($col_inject['read_type_hint']);

			$set = $class->newMethod('set' . Str::toClassName($column_name))
				->public()
				->setComment(
					Str::interpolate(
						'Setter for column `{table_name}.{column_name}`.

@param {write_type_hint} ${column_name}

@return static',
						$col_inject
					)
				)
				->setContent(
					Str::interpolate(
						'$this->{column_name} = ${column_name};

return $this;',
						$col_inject
					)
				);

			$set->setReturnType('static')
				->newArgument($column_name)
				->setType($col_inject['write_type_hint']);
		}

		foreach ($table->getRelations() as $relation) {
			$this->addRelationGetterMethod($class, $relation);
			$this->addRelationSetterMethod($class, $relation);
		}

		foreach ($table->getVirtualRelations() as $v_relation) {
			$this->addVirtualRelationGetterMethod($class, $v_relation);
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

	private function addRelationGetterMethod(PHPClass $class, Relation $relation): void
	{
		$relation_type = $relation->getType();
		$host          = $relation->getHostTable();
		$target        = $relation->getTargetTable();
		$m             = $class->newMethod('get' . Str::toClassName($relation->getName()))
			->public();
		$comment = \sprintf(
			'`%s` relation between `%s` and `%s`.',
			Str::toClassName($relation_type->value),
			$host->getName(),
			$target->getName()
		);
		$rel_inject = [
			'target_entity_class_fqn' => ORMClassKind::ENTITY->getClassFQN($target),
			'relation_name'           => $relation->getName(),
		];

		if ($relation_type->isMultiple()) {
			$comment .= Str::interpolate(
				'

@param array    $filters  the row filters
@param null|int $max      maximum row to retrieve
@param int      $offset   first row offset
@param array    $order_by order by rules
@param null|int $total    total number of items that match the filters

@throws \\' . GoblException::class . '
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
					'return {target_entity_class_fqn}::ctrl()->getAllRelatives(
	$this,
	static::table()->getRelation(\'{relation_name}\'),
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

@throws \\' . GoblException::class . '
@return ?{target_entity_class_fqn}',
				$rel_inject
			);
			$m->setReturnType(new PHPType('null', $rel_inject['target_entity_class_fqn']));
			$m->addChild(
				Str::interpolate(
					'return {target_entity_class_fqn}::ctrl()->getRelative(
	$this,
	static::table()->getRelation(\'{relation_name}\')
);',
					$rel_inject
				)
			);
		}

		$m->setComment($comment);
	}

	private function addRelationSetterMethod(PHPClass $class, Relation $relation): void
	{
		$relation_type = $relation->getType();
		$host          = $relation->getHostTable();
		$target        = $relation->getTargetTable();
		$is_multiple   = $relation_type->isMultiple();
		$m             = $class->newMethod($is_multiple ? 'add' . Str::toClassName($relation->getName() . '_entry') : 'set' . Str::toClassName($relation->getName()))
			->public();
		$comment = \sprintf(
			'Create `%s` relationship between `%s` and `%s`.',
			Str::toClassName($relation_type->value),
			$host->getName(),
			$target->getName()
		);
		$rel_inject = [
			'target_entity_class_fqn' => ORMClassKind::ENTITY->getClassFQN($target),
			'relation_name'           => $relation->getName(),
		];

		if ($is_multiple) {
			$comment .= Str::interpolate(
				'

@param {target_entity_class_fqn} $entry
@param bool $auto_save should the modified entity be saved automatically?

@return $this',
				$rel_inject
			);

			$m->newArgument('entry')
				->setType(new PHPType($rel_inject['target_entity_class_fqn']));
			$m->newArgument('auto_save')
				->setType(new PHPType('bool'))
				->setValue(true);

			$m->setReturnType('static');

			$m->addChild(
				Str::interpolate(
					'static::table()->getRelation(\'{relation_name}\')->getController()->link($this, $entry, $auto_save);
return $this;',
					$rel_inject
				)
			);
		} else {
			$comment .= Str::interpolate(
				'

@param {target_entity_class_fqn} ${relation_name}
@param bool $auto_save should the modified entity be saved automatically?

@return $this',
				$rel_inject
			);

			$m->newArgument($rel_inject['relation_name'])
				->setType(new PHPType($rel_inject['target_entity_class_fqn']));
			$m->newArgument('auto_save')
				->setType(new PHPType('bool'))
				->setValue(true);

			$m->setReturnType('static');

			$m->addChild(
				Str::interpolate(
					'static::table()->getRelation(\'{relation_name}\')->getController()->link(${relation_name}, $this, $auto_save);

return $this;',
					$rel_inject
				)
			);
		}

		$m->setComment($comment);
	}

	private function addVirtualRelationGetterMethod(PHPClass $class, VirtualRelation $relation): void
	{
		$host           = $relation->getHostTable();
		$relative_type  = $relation->getRelativeType();
		$relation_name  = $relation->getName();
		$read_type_hint = $this->getReadTypeHintString($relative_type);
		$m              = $class->newMethod('get' . Str::toClassName($relation_name))
			->public();
		$comment = \sprintf(
			'`%s` relation for `%s`.',
			$relation_name,
			$host->getName(),
		);
		$paginated  = $relation->isPaginated();
		$rel_inject = [
			'read_type_hint'    => $read_type_hint,
			'relation_name'     => $relation->getName(),
			'request_class_fqn' => '\\' . ORMRequest::class,
		];

		$m->newArgument('request')
			->setType(new PHPType('null', '\\' . ORMRequest::class))
			->setValue(null);

		$m->setReturnType($paginated ? 'array' : $read_type_hint);

		if ($paginated) {
			$comment .= Str::interpolate(
				'

@param null|{request_class_fqn} $request the request object

@return array<{read_type_hint}>',
				$rel_inject
			);

			$m->newArgument('total')
				->setType(new PHPType('null', 'int'))
				->reference()
				->setValue(-1);

			$m->addChild(
				Str::interpolate(
					'
$request =  $request ?? new {request_class_fqn}();

return static::table()->getVirtualRelation(\'{relation_name}\')->getController()->list($this, $request, $total);',
					$rel_inject
				)
			);
		} else {
			$comment .= Str::interpolate(
				'

@param null|{request_class_fqn} $request the request object

@return {read_type_hint}',
				$rel_inject
			);
			$m->setReturnType($read_type_hint);
			$m->addChild(
				Str::interpolate(
					'
$request =  $request ?? new {request_class_fqn}();

return static::table()->getVirtualRelation(\'{relation_name}\')->getController()->get($this, $request);',
					$rel_inject
				)
			);
		}

		$m->setComment($comment);
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

		$sub_class       = Str::interpolate('{db_namespace}\{class_name}', $inject);
		$sub_class_alias = Str::interpolate('{class_name}Real', $inject);

		$namespace->use($sub_class, $sub_class_alias);

		$class->extends(ORMEntityCRUD::class)
			->comment(
				Str::interpolate(
					'Class {class_name}.

@extends \\' . ORMEntityCRUD::class . '<\{db_namespace}\{entity_class_name}>
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

		$class->newMethod('new')
			->static()
			->public()
			->addChild(Str::interpolate('return new {class_name}Real();', $inject))
			->setReturnType('static')
			->comment(
				Str::interpolate(
					'{@inheritDoc}

@return static',
					$inject
				)
			);

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

		$sub_class       = Str::interpolate('{db_namespace}\{class_name}', $inject);
		$sub_class_alias = Str::interpolate('{class_name}Real', $inject);

		$namespace->use($sub_class, $sub_class_alias);

		$class_comment_lines   = [];
		$class_comment_lines[] = Str::interpolate('Class {class_name}.', $inject);
		$class_comment_lines[] = '';
		$class_comment_lines[] = Str::interpolate(
			'@extends \\' . ORMTableQuery::class . '<\{db_namespace}\{entity_class_name}>',
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

		$class->newMethod('new')
			->static()
			->public()
			->addChild(Str::interpolate('return new {class_name}Real();', $inject))
			->setReturnType('static')
			->comment(
				Str::interpolate(
					'{@inheritDoc}

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
					'Filters rows with `{rule_name}` condition on column `{table_name}.{column_name}`.',
					$col_inject
				);

				$has_no_arg = 1 === $operator->getOperandsCount();

				if ($has_no_arg) {
					$class_comment_lines[] = '@method $this ' . $method . '() ' . $comment;
				} else {
					$arg_type = $this->toTypeHintString(
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

		$sub_class       = Str::interpolate('{db_namespace}\{class_name}', $inject);
		$sub_class_alias = Str::interpolate('{class_name}Real', $inject);

		$namespace->use($sub_class, $sub_class_alias);

		$class->extends(new PHPClass(ORMResults::class))
			->abstract()
			->setComment(
				Str::interpolate(
					'Class {class_name}.

@extends \\' . ORMResults::class . '<\{db_namespace}\{entity_class_name}>',
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
			->setType('\\' . (QBSelect::class));
		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$create_instance = $class->newMethod('new')
			->static()
			->public()
			->addChild(
				Str::interpolate('return new {class_name}Real($query);', $inject)
			)
			->setReturnType('static');
		$create_instance->newArgument('query')
			->setType('\\' . (QBSelect::class));
		$create_instance->comment(
			Str::interpolate(
				'{@inheritDoc}

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

		$sub_class       = Str::interpolate('{db_namespace}\{class_name}', $inject);
		$sub_class_alias = Str::interpolate('{class_name}Real', $inject);

		$namespace->use($sub_class, $sub_class_alias);

		$class->extends(new PHPClass(ORMController::class))
			->abstract()
			->setComment(
				Str::interpolate(
					'Class {class_name}.

@extends \\' . ORMController::class . '<\{db_namespace}\{entity_class_name}, \{db_namespace}\{query_class_name}, \{db_namespace}\{results_class_name}>
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

		$class->newMethod('new')
			->static()
			->public()
			->addChild(Str::interpolate('return new {class_name}Real();', $inject))
			->setReturnType('static')
			->comment(
				Str::interpolate(
					'{@inheritDoc}

@return static',
					$inject
				)
			);

		return $file->setContent($namespace);
	}
}
