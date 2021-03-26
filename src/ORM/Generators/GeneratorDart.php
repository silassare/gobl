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

use Gobl\Gobl;
use InvalidArgumentException;

class GeneratorDart extends Generator
{
	/**
	 * @var array
	 */
	private $dart_types_map = [
		'string' => 'String',
		'bigint' => 'String',
		'float'  => 'num',
		'int'    => 'num',
		'bool'   => 'bool',
	];

	/**
	 * @inheritDoc
	 */
	public function generate(array $tables, $path, $header = '')
	{
		if (!\file_exists($path) || !\is_dir($path)) {
			throw new InvalidArgumentException(\sprintf('"%s" is not a valid directory path.', $path));
		}

		$ds = \DIRECTORY_SEPARATOR;

		$path_gobl        = $path . $ds . 'gobl';
		$path_db          = $path_gobl . $ds . 'db';
		$path_db_base     = $path_db . $ds . 'base';
		$path_db_entities = $path_db . $ds . 'entities';
		$path_db_mixins   = $path_db . $ds . 'mixins';

		if (!\file_exists($path_db_base)) {
			\mkdir($path_db_base, 0755, true);
		}

		if (!\file_exists($path_db_entities)) {
			\mkdir($path_db_entities, 0755, true);
		}

		if (!\file_exists($path_db_mixins)) {
			\mkdir($path_db_mixins, 0755, true);
		}

		$dart_entity_class_tpl       = self::getTemplateCompiler('dart.entity.class');
		$dart_entity_base_class_tpl  = self::getTemplateCompiler('dart.entity.base.class');
		$dart_entity_mixin_class_tpl = self::getTemplateCompiler('dart.entity.mixin.class');
		$dart_bundle_tpl             = self::getTemplateCompiler('dart.bundle');
		$dart_register_tpl           = self::getTemplateCompiler('dart.register');
		$bundle_inject               = [];
		$time                        = \time();

		foreach ($tables as $table) {
			if (!($table->isPrivate() && $this->ignore_private_table)) {
				$inject                 = $this->describeTable($table);
				$inject['gobl_header']  = $header;
				$inject['gobl_time']    = $time;
				$inject['gobl_version'] = Gobl::VERSION;
				$entity_class           = $inject['table']['singular'];
				$entity_base_class      = $entity_class . '_base';
				$entity_mixin_class     = $entity_class . '_mixin';
				$inject['columns_list'] = \implode('|', \array_keys($inject['columns']));
				$inject['dart_types']   = $this->dart_types_map;

				foreach ($inject['columns'] as $column) {
					$inject['columns_prefix'] = $column['prefix'];

					break;
				}

				$entity_content                           = $dart_entity_class_tpl->runGet($inject);
				$entity_base_content                      = $dart_entity_base_class_tpl->runGet($inject);
				$entity_mixin_content                     = $dart_entity_mixin_class_tpl->runGet($inject);
				$bundle_inject['entities'][$entity_class] = $inject['class']['entity'];
				$this->writeFile($path_db_entities . $ds . $entity_class . '.dart', $entity_content, true);
				$this->writeFile($path_db_base . $ds . $entity_base_class . '.dart', $entity_base_content, true);
				$this->writeFile($path_db_mixins . $ds . $entity_mixin_class . '.dart', $entity_mixin_content, false);
			}
		}

		$bundle_inject['gobl_header']  = $header;
		$bundle_inject['gobl_time']    = $time;
		$bundle_inject['gobl_version'] = Gobl::VERSION;

		$this->writeFile($path_gobl . $ds . 'bundle.dart', $dart_bundle_tpl->runGet($bundle_inject), true);
		$this->writeFile($path_gobl . $ds . 'register.dart', $dart_register_tpl->runGet($bundle_inject), true);

		return $this;
	}
}

GeneratorDart::setTemplates([
	'dart.entity.class'       => ['path' => Gobl::SAMPLES_DIR . '/dart/my_entity.dart'],
	'dart.entity.base.class'  => ['path' => Gobl::SAMPLES_DIR . '/dart/my_entity_base.dart'],
	'dart.entity.mixin.class' => ['path' => Gobl::SAMPLES_DIR . '/dart/my_entity_mixin.dart'],
	'dart.bundle'             => ['path' => Gobl::SAMPLES_DIR . '/dart/bundle.dart'],
	'dart.register'           => ['path' => Gobl::SAMPLES_DIR . '/dart/register.dart'],
]);
