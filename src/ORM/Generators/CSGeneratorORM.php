<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\ORM\Generators;

use Exception;
use Gobl\DBAL\Column;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\Utils\JsonPatch;
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
use OLIUP\CG\PHPArgument;
use OLIUP\CG\PHPClass;
use OLIUP\CG\PHPFile;
use OLIUP\CG\PHPMethod;
use OLIUP\CG\PHPNamespace;
use OLIUP\CG\PHPType;
use Override;
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
	 *
	 * @throws Exception
	 */
	#[Override]
	public function generate(array $tables, ?string $path = null, string $header = ''): static
	{
		$fs        = self::outputDirFS($path);
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
	 * Converts an {@see ORMTypeHint} to a PHP type hint string.
	 */
	#[Override]
	public function toTypeHintString(ORMTypeHint $type_hint): string
	{
		if ($php_type = $type_hint->getPHPType()) {
			return (string) $php_type;
		}

		$types     = $type_hint->getUniversalTypes();
		$php_types = [];

		foreach ($types as $type) {
			if (ORMUniversalType::LIST === $type) {
				$list_class = $type_hint->getListOfClass();
				$sub_type   = null !== $list_class
					? '\\' . $list_class
					: ($type_hint->getListOfUniversalType())->toPHPType();

				$php_types[] = 'list<' . $sub_type . '>';

				continue;
			}

			$php_types[] = $type->toPHPType();
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
			ORMClassKind::QUERY           => $this->getExtendsOfBaseClass($table, $kind),
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

	private function getExtendsOfBaseClass(Table $table, ORMClassKind $kind): PHPFile
	{
		$db_ns           = $table->getNamespace();
		$class_name      = $kind->getClassName($table);
		$class_name_base = $kind->getBaseKind()->getClassName($table);
		$file            = new PHPFile();
		$namespace       = new PHPNamespace($db_ns);
		$class           = $namespace->newClass($class_name);

		$inject = [
			'class_name'      => $class_name,
			'class_name_base' => $class_name_base,
			'db_namespace'    => $db_ns,
		];

		$base_class       = Str::interpolate('{db_namespace}\Base\{class_name_base}', $inject);
		$namespace->use($base_class);

		$class->extends($base_class)
			->setContent($this->editable_body_comment)
			->comment(Str::interpolate('Class {class_name}.', $inject));

		return $file->setContent($namespace);
	}

	private function getBaseEntity(Table $table): PHPFile
	{
		$db_ns          = $table->getNamespace();
		$class_name     = ORMClassKind::BASE_ENTITY->getClassName($table);
		$class_name_sub = ORMClassKind::ENTITY->getClassName($table);
		$file           = new PHPFile();
		$namespace      = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class          = $namespace->newClass($class_name);

		$inject = [
			'class_name'         => $class_name,
			'class_name_sub'     => $class_name_sub,
			'ctrl_class_name'    => ORMClassKind::CONTROLLER->getClassName($table),
			'qb_class_name'      => ORMClassKind::QUERY->getClassName($table),
			'results_class_name' => ORMClassKind::RESULTS->getClassName($table),
			'crud_class_name'    => ORMClassKind::CRUD->getClassName($table),
			'db_namespace'       => $db_ns,
		];

		$sub_class  = Str::interpolate('{db_namespace}\{class_name_sub}', $inject);

		$namespace->use($sub_class);

		$class->extends(new PHPClass(ORMEntity::class))
			->abstract();

		$class->setComment(
			Str::interpolate(
				'Class {class_name}.' . \PHP_EOL,
				$inject
			)
		);

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(' . \PHP_EOL
						. '	self::TABLE_NAMESPACE,' . \PHP_EOL
						. '	self::TABLE_NAME,' . \PHP_EOL
						. '	$is_new,' . \PHP_EOL
						. '	$strict' . \PHP_EOL
						. ');',
					$inject
				)
			);
		$construct->setComment(
			Str::interpolate(
				'{class_name} constructor.' . \PHP_EOL
					. \PHP_EOL
					. '@param bool $is_new true for new entity false for entity fetched from the database, default is true'
					. \PHP_EOL
					. '@param bool $strict Enable/disable strict mode',
				$inject
			)
		);

		$construct->newArgument('is_new')
			->setType('bool')
			->setValue(true);
		$construct->newArgument('strict')
			->setType('bool')
			->setValue(true);

		/**
		 * @var array<string, array{comment: string, return: string, body: string, args?: PHPArgument[]}> $static_helpers
		 */
		$static_helpers = [
			'new' => [
				'comment' => '{@inheritDoc}'
					. \PHP_EOL
					. \PHP_EOL
					. '@return static',
				'return' => 'static',
				'body'   => 'return new {class_name_sub}($is_new, $strict);',
				'args'   => [$construct->getArgument('is_new'), $construct->getArgument('strict')],
			],
			'crud' => [
				'comment' => '{@inheritDoc}'
					. \PHP_EOL
					. \PHP_EOL
					. '@return \{db_namespace}\{crud_class_name}',
				'return' => '\{db_namespace}\{crud_class_name}',
				'body'   => 'return \{db_namespace}\{crud_class_name}::new();',
			],
			'ctrl' => [
				'comment' => '{@inheritDoc}'
					. \PHP_EOL
					. \PHP_EOL
					. '@return \{db_namespace}\{ctrl_class_name}',
				'return' => '\{db_namespace}\{ctrl_class_name}',
				'body'   => 'return \{db_namespace}\{ctrl_class_name}::new();',
			],
			'qb' => [
				'comment' => '{@inheritDoc}'
					. \PHP_EOL
					. \PHP_EOL
					. '@return \{db_namespace}\{qb_class_name}',
				'return' => '\{db_namespace}\{qb_class_name}',
				'body'   => 'return \{db_namespace}\{qb_class_name}::new();',
			],
			'results' => [
				'comment' => '{@inheritDoc}'
					. \PHP_EOL
					. \PHP_EOL
					. '@return \{db_namespace}\{results_class_name}',
				'return' => '\{db_namespace}\{results_class_name}',
				'body'   => 'return \{db_namespace}\{results_class_name}::new($query);',
				'args'   => [
					new PHPArgument('query', '\\' . QBSelect::class),
				],
			],
			'table' => [
				'comment' => '{@inheritDoc}'
					. \PHP_EOL
					. \PHP_EOL
					. '@return \\' . Table::class,
				'return'  => '\\' . Table::class,
				'body'    => 'return \\'
					. Str::callableName([ORM::class, 'table'])
					. '(static::TABLE_NAMESPACE, static::TABLE_NAME);',
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

			foreach ($args as $arg) {
				$helper_method->addArgument($arg);
			}

			$helper_method->newAttribute('\\' . Override::class);
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
						'Getter for column `{table_name}.{column_name}`.'
							. \PHP_EOL
							. \PHP_EOL
							. '@return {read_type_hint}',
						$col_inject
					)
				)
				->setContent(Str::interpolate('return $this->{column_name};', $col_inject));

			$get->setReturnType($col_inject['read_type_hint']);

			$set = $class->newMethod('set' . Str::toClassName($column_name))
				->public()
				->setComment(
					Str::interpolate(
						'Setter for column `{table_name}.{column_name}`.'
							. \PHP_EOL
							. \PHP_EOL
							. '@param {write_type_hint} ${column_name}'
							. \PHP_EOL
							. \PHP_EOL
							. '@return static',
						$col_inject
					)
				)
				->setContent(
					Str::interpolate(
						'$this->{column_name} = ${column_name};'
							. \PHP_EOL
							. \PHP_EOL
							. 'return $this;',
						$col_inject
					)
				);

			$set->setReturnType('static')
				->newArgument($column_name)
				->setType($col_inject['write_type_hint']);

			// For JSON-based columns (TypeJSON, TypeMap, TypeList) generate
			// patchColumnName(), setColumnNameKey(), and removeColumnNameKey().
			if ($type->getBaseType() instanceof TypeJSON) {
				$this->addJsonPatchMethods($class, $column);
			}
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

	/**
	 * Adds patchColumnName(), setColumnNameKey(), and removeColumnNameKey() methods
	 * for JSON-based columns (TypeJSON, TypeMap, TypeList).
	 *
	 * Because JsonPatch expect array/Map we add patch methods only for:
	 *  - TypeMap
	 *  - TypeJSON if json_of is defined and equal to universal type: map or list
	 *  - TypeList if list_of is defined and equal to universal type: map or list
	 *
	 * When we have list of scalar, we support patch at index only.
	 */
	private function addJsonPatchMethods(PHPClass $class, Column $column): void
	{
		$column_name = $column->getName();
		$type        = $column->getType();
		$value_type  = 'array|float|int|string|\JsonSerializable|null';

		if ($type instanceof TypeList) {
			$of =  $type->getListOfUniversalType();

			if (null === $of || !\in_array($of, [ORMUniversalType::MAP, ORMUniversalType::LIST], true)) {
				return;
			}
		} elseif ($type instanceof TypeJSON) {
			$of =  $type->getJsonOfUniversalType();

			if (!\in_array($of, [ORMUniversalType::MAP, ORMUniversalType::LIST], true)) {
				return;
			}
		}

		$getter_name       = self::propertyGetterName($column_name);
		$setter_name       = 'set' . Str::toClassName($column_name);
		$patch_method      = 'patch' . Str::toClassName($column_name);
		$set_key_method    = 'set' . Str::toClassName($column_name) . 'Key';
		$remove_key_method = 'remove' . Str::toClassName($column_name) . 'Key';
		$json_patch_type   = '\\' . JsonPatch::class;

		// patchColumnName(): seeds a JsonPatch from the current column value.
		$class->newMethod($patch_method)
			->public()
			->setComment('Seeds a ' . $json_patch_type . ' from the current `' . $column_name . '` value.')
			->setReturnType($json_patch_type)
			->addChild('return new ' . $json_patch_type . '($this->' . $getter_name . '() ?? []);');

		// setColumnNameKey(): one-liner set at a dot-notation path.
		$sk = $class->newMethod($set_key_method)
			->public()
			->setComment('Sets a value at the given dot-notation path in `' . $column_name . '`.')
			->setReturnType('static')
			->addChild('return $this->' . $setter_name . '($this->' . $patch_method . '()->set($path, $value));');
		$sk->newArgument('path')->setType('string');
		$sk->newArgument('value')->setType($value_type);

		// removeColumnNameKey(): one-liner remove at a dot-notation path.
		$rk = $class->newMethod($remove_key_method)
			->public()
			->setComment('Removes the key at the given dot-notation path from `' . $column_name . '`.')
			->setReturnType('static')
			->addChild('return $this->' . $setter_name . '($this->' . $patch_method . '()->remove($path));');
		$rk->newArgument('path')->setType('string');
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
				\PHP_EOL
					. \PHP_EOL
					. '@param array    $filters  the row filters' . \PHP_EOL
					. '@param null|int $max      maximum row to retrieve' . \PHP_EOL
					. '@param int      $offset   first row offset' . \PHP_EOL
					. '@param array    $order_by order by rules' . \PHP_EOL
					. '@param null|int $total    total number of items that match the filters' . \PHP_EOL
					. \PHP_EOL
					. '@throws \\' . GoblException::class . \PHP_EOL
					. '@return {target_entity_class_fqn}[]',
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
					'return {target_entity_class_fqn}::ctrl()->getAllRelatives(' . \PHP_EOL
						. '	$this,' . \PHP_EOL
						. '	static::table()->getRelation(\'{relation_name}\'),' . \PHP_EOL
						. '	$filters,' . \PHP_EOL
						. '	$max,' . \PHP_EOL
						. '	$offset,' . \PHP_EOL
						. '	$order_by,' . \PHP_EOL
						. '	$total' . \PHP_EOL
						. ');',
					$rel_inject
				)
			);
		} else {
			$comment .= Str::interpolate(
				\PHP_EOL
					. \PHP_EOL
					. '@throws \\' . GoblException::class . \PHP_EOL
					. '@return ?{target_entity_class_fqn}',
				$rel_inject
			);
			$m->setReturnType(new PHPType('null', $rel_inject['target_entity_class_fqn']));
			$m->addChild(
				Str::interpolate(
					'return {target_entity_class_fqn}::ctrl()->getRelative(' . \PHP_EOL
						. '	$this,' . \PHP_EOL
						. '	static::table()->getRelation(\'{relation_name}\')' . \PHP_EOL
						. ');',
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
				\PHP_EOL
					. \PHP_EOL
					. '@param {target_entity_class_fqn} $entry the entry to add to the relation' . \PHP_EOL
					. '@param bool $auto_save should the modified entity be saved automatically?' . \PHP_EOL
					. \PHP_EOL
					. '@return static',
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
					'static::table()->getRelation(\'{relation_name}\')->getController()->link($this, $entry, $auto_save);'
						. \PHP_EOL
						. 'return $this;',
					$rel_inject
				)
			);
		} else {
			$comment .= Str::interpolate(
				\PHP_EOL
					. \PHP_EOL
					. '@param {target_entity_class_fqn} ${relation_name} the entry to set for the relation' . \PHP_EOL
					. '@param bool $auto_save should the modified entity be saved automatically?' . \PHP_EOL
					. \PHP_EOL
					. '@return static',
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
					'static::table()->getRelation(\'{relation_name}\')->getController()->link(${relation_name}, $this, $auto_save);'
						. \PHP_EOL
						. \PHP_EOL
						. 'return $this;',
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
				\PHP_EOL
					. \PHP_EOL
					. ' @param null|{request_class_fqn} $request the request object' . \PHP_EOL
					. \PHP_EOL
					. '@return array<{read_type_hint}>',
				$rel_inject
			);

			$m->newArgument('total')
				->setType(new PHPType('null', 'int'))
				->reference()
				->setValue(-1);

			$m->addChild(
				Str::interpolate(
					\PHP_EOL
						. '$request =  $request ?? new {request_class_fqn}();' . \PHP_EOL
						. \PHP_EOL
						. 'return static::table()->getVirtualRelation(\'{relation_name}\')->getController()->list($this, $request, $total);',
					$rel_inject
				)
			);
		} else {
			$comment .= Str::interpolate(
				\PHP_EOL
					. \PHP_EOL
					. '@param null|{request_class_fqn} $request the request object' . \PHP_EOL
					. \PHP_EOL
					. '@return {read_type_hint}',
				$rel_inject
			);
			$m->setReturnType($read_type_hint);
			$m->addChild(
				Str::interpolate(
					\PHP_EOL
						. '$request =  $request ?? new {request_class_fqn}();' . \PHP_EOL
						. \PHP_EOL
						. 'return static::table()->getVirtualRelation(\'{relation_name}\')->getController()->get($this, $request);',
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
		$class_name_sub    = ORMClassKind::CRUD->getClassName($table);
		$entity_class_name = ORMClassKind::ENTITY->getClassName($table);
		$file              = new PHPFile();
		$namespace         = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class             = $namespace->newClass($class_name);

		$inject = [
			'class_name'        => $class_name,
			'class_name_sub'    => $class_name_sub,
			'entity_class_name' => $entity_class_name,
			'db_namespace'      => $db_ns,
		];

		$sub_class = Str::interpolate('{db_namespace}\{class_name_sub}', $inject);

		$namespace->use($sub_class);

		$class->extends(ORMEntityCRUD::class)
			->comment(
				Str::interpolate(
					'Class {class_name}.'
						. \PHP_EOL
						. \PHP_EOL
						. '@extends \\' . ORMEntityCRUD::class . '<\{db_namespace}\{entity_class_name}>' . \PHP_EOL,
					$inject
				)
			);

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAME' . \PHP_EOL
						. ');',
					$inject
				)
			);

		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$class->newMethod('new')
			->static()
			->public()
			->addAttribute('\\' . Override::class)
			->addChild(Str::interpolate('return new {class_name_sub}();', $inject))
			->setReturnType('static')
			->comment(
				Str::interpolate(
					'{@inheritDoc}'
						. \PHP_EOL
						. \PHP_EOL
						. '@return static',
					$inject
				)
			);

		return $file->setContent($namespace);
	}

	private function getBaseQuery(Table $table): PHPFile
	{
		$db_ns          = $table->getNamespace();
		$class_name     = ORMClassKind::BASE_QUERY->getClassName($table);
		$class_name_sub = ORMClassKind::QUERY->getClassName($table);
		$file           = new PHPFile();
		$namespace      = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class          = $namespace->newClass($class_name);

		$inject = [
			'class_name'        => $class_name,
			'class_name_sub'    => $class_name_sub,
			'entity_class_name' => ORMClassKind::ENTITY->getClassName($table),
			'db_namespace'      => $db_ns,
		];

		$sub_class = Str::interpolate('{db_namespace}\{class_name_sub}', $inject);

		$namespace->use($sub_class);

		$class_comment_lines   = [];
		$class_comment_lines[] = Str::interpolate('Class {class_name}.', $inject);
		$class_comment_lines[] = '';
		$class_comment_lines[] = Str::interpolate(
			'@extends \\' . ORMTableQuery::class . '<\{db_namespace}\{entity_class_name}>',
			$inject
		);
		$class_comment_lines[] = '';

		$class->extends(new PHPClass(ORMTableQuery::class))
			->abstract();

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAME' . \PHP_EOL
						. ');',
					$inject
				)
			);

		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$class->newMethod('new')
			->static()
			->public()
			->addAttribute('\\' . Override::class)
			->addChild(Str::interpolate('return new {class_name_sub}();', $inject))
			->setReturnType('static')
			->comment(
				Str::interpolate(
					'{@inheritDoc}'
						. \PHP_EOL
						. \PHP_EOL
						. '@return static',
					$inject
				)
			);

		foreach ($table->getColumns() as $column) {
			$type = $column->getType();
			foreach ($type->getAllowedFilterOperators() as $operator) {
				$method_name = Str::toMethodName('where_' . $operator->getFilterSuffix($column));

				$col_inject = $inject + [
					'rule_name'   => $operator->value,
					'table_name'  => $table->getName(),
					'column_name' => $column->getName(),
				];

				// Let the type customize the method signature before we build the @method tag.
				// Types may prepend a $path arg (e.g. TypeJSON for native JSON columns).
				$php_method = new PHPMethod($method_name);

				// comment may be enhanced by the type
				$php_method->comment(Str::interpolate(
					'Filters rows with `{rule_name}` condition on column `{table_name}.{column_name}`.',
					$col_inject
				));

				$type->queryBuilderEnhanceFilterMethod($table, $column, $operator, $php_method);

				// If the type added body children to the method, render it as a real method in the class.
				// Otherwise, build a @method docblock tag so IDEs see the correct signature while
				// __call dispatches the call at runtime.
				if (!empty($php_method->getChildren())) {
					$class->addMethod($php_method);
				} else {
					$enhanced_args = $php_method->getArguments();
					// Get plain comment text (without docblock delimiters) for the @method tag.
					$comment =   $php_method->getComment()?->getContent() ?? '';

					if (!empty($enhanced_args)) {
						// Type provided a custom signature: render each argument (with optional default value)
						$args_str = \implode(', ', \array_map(
							static function ($a): string {
								$s = (null !== $a->getType() ? (string) $a->getType() : 'mixed') . ' $' . $a->getName();
								$v = $a->getValue();
								if (null !== $v) {
									$raw = $v->getValue();
									$s .= ' = ' . (null === $raw ? 'null' : (\is_string($raw) ? "'" . \addslashes($raw) . "'" : \var_export($raw, true)));
								}

								return $s;
							},
							$enhanced_args
						));
						$class_comment_lines[] = '@method static ' . $method_name . '(' . $args_str . ') ' . $comment;
					} elseif ($operator->isUnary()) {
						// No args: unary operator with no right operand
						$class_comment_lines[] = '@method static ' . $method_name . '() ' . $comment;
					} else {
						// Fallback: standard single $value arg
						$arg_type = $this->toTypeHintString(
							ORMTypeHint::getOperatorRightOperandTypesHint($type, $operator)
						);
						$class_comment_lines[] = '@method static ' . $method_name . '(' . $arg_type . ' $value) ' . $comment;
					}
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
		$class_name_sub    = ORMClassKind::RESULTS->getClassName($table);
		$entity_class_name = ORMClassKind::ENTITY->getClassName($table);
		$file              = new PHPFile();
		$namespace         = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class             = $namespace->newClass($class_name);

		$inject = [
			'class_name'        => $class_name,
			'class_name_sub'    => $class_name_sub,
			'entity_class_name' => $entity_class_name,
			'db_namespace'      => $db_ns,
		];

		$sub_class = Str::interpolate('{db_namespace}\{class_name_sub}', $inject);

		$namespace->use($sub_class);

		$class->extends(new PHPClass(ORMResults::class))
			->abstract()
			->setComment(
				Str::interpolate(
					'Class {class_name}.'
						. \PHP_EOL
						. \PHP_EOL
						. '@extends \\' . ORMResults::class . '<\{db_namespace}\{entity_class_name}>',
					$inject
				)
			);

		$construct = $class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAME,' . \PHP_EOL
						. '	$query' . \PHP_EOL
						. ');',
					$inject
				)
			);

		$construct->newArgument('query')
			->setType('\\' . (QBSelect::class));
		$construct->comment(Str::interpolate('{class_name} constructor.', $inject));

		$create_instance = $class->newMethod('new')
			->static()
			->public()
			->addAttribute('\\' . Override::class)
			->addChild(
				Str::interpolate('return new {class_name_sub}($query);', $inject)
			)
			->setReturnType('static');
		$create_instance->newArgument('query')
			->setType('\\' . (QBSelect::class));
		$create_instance->comment(
			Str::interpolate(
				'{@inheritDoc}'
					. \PHP_EOL
					. \PHP_EOL
					. '@return static',
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
		$class_name_sub     = ORMClassKind::CONTROLLER->getClassName($table);
		$entity_class_name  = ORMClassKind::ENTITY->getClassName($table);
		$results_class_name = ORMClassKind::RESULTS->getClassName($table);
		$query_class_name   = ORMClassKind::QUERY->getClassName($table);
		$file               = new PHPFile();
		$namespace          = new PHPNamespace(\sprintf('%s\Base', $db_ns));
		$class              = $namespace->newClass($class_name);

		$inject = [
			'class_name'         => $class_name,
			'class_name_sub'     => $class_name_sub,
			'entity_class_name'  => $entity_class_name,
			'results_class_name' => $results_class_name,
			'query_class_name'   => $query_class_name,
			'db_namespace'       => $db_ns,
		];

		$sub_class = Str::interpolate('{db_namespace}\{class_name_sub}', $inject);

		$namespace->use($sub_class);

		$class->extends(new PHPClass(ORMController::class))
			->abstract()
			->setComment(
				Str::interpolate(
					'Class {class_name}.'
						. \PHP_EOL
						. \PHP_EOL
						. '@extends \\' . ORMController::class . '<\{db_namespace}\{entity_class_name}, \{db_namespace}\{query_class_name}, \{db_namespace}\{results_class_name}>'
						. \PHP_EOL,
					$inject
				)
			);

		$class->newMethod('__construct')
			->public()
			->addChild(
				Str::interpolate(
					'parent::__construct(' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAMESPACE,' . \PHP_EOL
						. '	\{db_namespace}\{entity_class_name}::TABLE_NAME' . \PHP_EOL
						. ');',
					$inject
				)
			)
			->comment(Str::interpolate('{class_name} constructor.', $inject));

		$class->newMethod('new')
			->static()
			->public()
			->addChild(Str::interpolate('return new {class_name_sub}();', $inject))
			->setReturnType('static')
			->addAttribute('\\' . Override::class)
			->comment(
				Str::interpolate(
					'{@inheritDoc}'
						. \PHP_EOL
						. \PHP_EOL
						. '@return static',
					$inject
				)
			);

		return $file->setContent($namespace);
	}
}
