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

use Exception;
use Gobl\Gobl;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use PHPUtils\FS\FSUtils;

/**
 * Class CSGeneratorDart.
 */
class CSGeneratorDart extends CSGenerator
{
	/**
	 * @var bool
	 */
	protected static bool $templates_registered = false;

	/**
	 * {@inheritDoc}
	 */
	public function toTypeHintString(ORMTypeHint $type_hint): string
	{
		$types      = $type_hint->getUniversalTypes();
		$dart_types = [];

		foreach ($types as $type) {
			$dart_types[] = match ($type) {
				ORMUniversalType::ARRAY => 'List',
				ORMUniversalType::MAP   => 'Map',
				ORMUniversalType::STRING, ORMUniversalType::DECIMAL, ORMUniversalType::BIGINT => 'String',
				ORMUniversalType::FLOAT, ORMUniversalType::INT => 'num',
				ORMUniversalType::BOOL  => 'bool',
				ORMUniversalType::NULL  => 'null',
				ORMUniversalType::MIXED => 'dynamic',
			};
		}

		return \implode('|', $dart_types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Exception
	 */
	public function generate(array $tables, string $path, string $header = ''): self
	{
		if (!self::$templates_registered) {
			self::$templates_registered = true;

			Gobl::addTemplates([
				'dart.entity.class'       => ['path' => GOBL_ASSETS_DIR . '/dart/my_entity.dart'],
				'dart.entity.base.class'  => ['path' => GOBL_ASSETS_DIR . '/dart/my_entity_base.dart'],
				'dart.entity.mixin.class' => ['path' => GOBL_ASSETS_DIR . '/dart/my_entity_mixin.dart'],
				'dart.bundle'             => ['path' => GOBL_ASSETS_DIR . '/dart/bundle.dart'],
				'dart.register'           => ['path' => GOBL_ASSETS_DIR . '/dart/register.dart'],
			]);
		}

		$fs = new FSUtils($path);

		$fs->filter()
			->isDir()
			->isWritable()
			->assert('.');

		$path             = $fs->getRoot();
		$ds               = \DIRECTORY_SEPARATOR;
		$path_gobl        = $path . $ds . 'gobl';
		$path_db          = $path_gobl . $ds . 'db';
		$path_db_base     = $path_db . $ds . 'base';
		$path_db_entities = $path_db . $ds . 'entities';
		$path_db_mixins   = $path_db . $ds . 'mixins';

		$fs->mkdir($path_db_base);
		$fs->mkdir($path_db_entities);
		$fs->mkdir($path_db_mixins);

		$dart_entity_class_tpl       = Gobl::getTemplateCompiler('dart.entity.class');
		$dart_entity_base_class_tpl  = Gobl::getTemplateCompiler('dart.entity.base.class');
		$dart_entity_mixin_class_tpl = Gobl::getTemplateCompiler('dart.entity.mixin.class');
		$dart_bundle_tpl             = Gobl::getTemplateCompiler('dart.bundle');
		$dart_register_tpl           = Gobl::getTemplateCompiler('dart.register');
		$bundle_inject               = [];
		$time                        = Gobl::getGeneratedAtDate();

		foreach ($tables as $table) {
			if (!($this->ignore_private_table && $table->isPrivate())) {
				$inject                 = $this->describeTable($table);
				$inject['gobl_header']  = $header;
				$inject['gobl_time']    = $time;
				$inject['gobl_version'] = GOBL_VERSION;
				$entity_class           = $inject['table']['singular'];
				$entity_base_class      = $entity_class . '_base';
				$entity_mixin_class     = $entity_class . '_mixin';
				$inject['columns_list'] = \implode('|', \array_keys($inject['columns']));

				$column = \next($inject['columns']);

				if ($column) {
					$inject['columns_prefix'] = $column['prefix'];
				}
				\reset($inject['columns']);

				$entity_content                           = $dart_entity_class_tpl->runGet($inject);
				$entity_base_content                      = $dart_entity_base_class_tpl->runGet($inject);
				$entity_mixin_content                     = $dart_entity_mixin_class_tpl->runGet($inject);
				$bundle_inject['entities'][$entity_class] = $inject['class']['entity'];
				$this->writeFile($path_db_entities . $ds . $entity_class . '.dart', $entity_content);
				$this->writeFile($path_db_base . $ds . $entity_base_class . '.dart', $entity_base_content);
				$this->writeFile($path_db_mixins . $ds . $entity_mixin_class . '.dart', $entity_mixin_content, false);
			}
		}

		$bundle_inject['gobl_header']  = $header;
		$bundle_inject['gobl_time']    = $time;
		$bundle_inject['gobl_version'] = GOBL_VERSION;

		$this->writeFile($path_gobl . $ds . 'bundle.dart', $dart_bundle_tpl->runGet($bundle_inject));
		$this->writeFile($path_gobl . $ds . 'register.dart', $dart_register_tpl->runGet($bundle_inject));

		return $this;
	}
}
