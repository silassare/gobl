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
 * Class CSGeneratorTS.
 */
class CSGeneratorTS extends CSGenerator
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
		$types    = $type_hint->getUniversalTypes();
		$ts_types = [];

		foreach ($types as $type) {
			$ts_types[] = match ($type) {
				ORMUniversalType::ARRAY => 'unknown[]',
				ORMUniversalType::MAP => 'Record<string, unknown>',
				ORMUniversalType::STRING, ORMUniversalType::DECIMAL, ORMUniversalType::BIGINT => 'string',
				ORMUniversalType::FLOAT, ORMUniversalType::INT => 'number',
				ORMUniversalType::BOOL => 'boolean',
				ORMUniversalType::_NULL => 'null',
				ORMUniversalType::MIXED => 'any',
			};
		}

		return \implode('|', $ts_types);
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
				'ts.bundle'            => ['path' => GOBL_ASSETS_DIR . '/ts/TSBundle.ts'],
				'ts.entity.base.class' => ['path' => GOBL_ASSETS_DIR . '/ts/MyEntityBase.ts'],
				'ts.entity.class'      => ['path' => GOBL_ASSETS_DIR . '/ts/MyEntity.ts'],
			]);
		}

		$fs = new FSUtils($path);

		$fs->filter()
		   ->isDir()
		   ->isWritable()
		   ->assert('.');

		$path         = $fs->getRoot();
		$ds           = \DIRECTORY_SEPARATOR;
		$path_gobl    = $path . $ds . 'gobl';
		$path_db      = $path_gobl . $ds . 'db';
		$path_db_base = $path_db . $ds . 'base';

		$fs->mkdir($path_db_base);

		$ts_entity_class_tpl      = Gobl::getTemplateCompiler('ts.entity.class');
		$ts_entity_base_class_tpl = Gobl::getTemplateCompiler('ts.entity.base.class');
		$ts_bundle_tpl            = Gobl::getTemplateCompiler('ts.bundle');
		$bundle_inject            = [];
		$time                     = Gobl::getGeneratedAtDate();

		foreach ($tables as $table) {
			if (!($this->ignore_private_table && $table->isPrivate())) {
				$inject                 = $this->describeTable($table);
				$inject['gobl_header']  = $header;
				$inject['gobl_time']    = $time;
				$inject['gobl_version'] = GOBL_VERSION;
				$entity_class           = $inject['class']['entity'];
				$entity_base_class      = $entity_class . 'Base';
				$inject['columns_list'] = \implode('|', \array_keys($inject['columns']));

				$column = \next($inject['columns']);

				if ($column) {
					$inject['columns_prefix'] = $column['prefix'];
				}
				\reset($inject['columns']);

				$entity_content                           = $ts_entity_class_tpl->runGet($inject);
				$entity_base_content                      = $ts_entity_base_class_tpl->runGet($inject);
				$bundle_inject['entities'][$entity_class] = $entity_content;
				$this->writeFile($path_db_base . $ds . $entity_base_class . '.ts', $entity_base_content);
				$this->writeFile($path_db . $ds . $entity_class . '.ts', $entity_content, false);
			}
		}

		$bundle_inject['gobl_header']  = $header;
		$bundle_inject['gobl_time']    = $time;
		$bundle_inject['gobl_version'] = GOBL_VERSION;

		$this->writeFile($path_gobl . $ds . 'index.ts', $ts_bundle_tpl->runGet($bundle_inject));

		return $this;
	}
}
