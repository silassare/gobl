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

class GeneratorTS extends Generator
{
	/**
	 * @inheritDoc
	 */
	public function generate(array $tables, $path, $header = '')
	{
		if (!\file_exists($path) || !\is_dir($path)) {
			throw new InvalidArgumentException(\sprintf('"%s" is not a valid directory path.', $path));
		}

		$ds = \DIRECTORY_SEPARATOR;

		$path_gobl    = $path . $ds . 'gobl';
		$path_db      = $path_gobl . $ds . 'db';
		$path_db_base = $path_db . $ds . 'base';

		if (!\file_exists($path_db_base)) {
			\mkdir($path_db_base, 0755, true);
		}

		$ts_entity_class_tpl      = self::getTemplateCompiler('ts.entity.class');
		$ts_entity_base_class_tpl = self::getTemplateCompiler('ts.entity.base.class');
		$ts_bundle_tpl            = self::getTemplateCompiler('ts.bundle');
		$bundle_inject            = [];
		$time                     = \time();

		foreach ($tables as $table) {
			if (!($table->isPrivate() && $this->ignore_private_table)) {
				$inject                 = $this->describeTable($table);
				$inject['gobl_header']  = $header;
				$inject['gobl_time']    = $time;
				$inject['gobl_version'] = Gobl::VERSION;
				$entity_class           = $inject['class']['entity'];
				$entity_base_class      = $entity_class . 'Base';
				$inject['columns_list'] = \implode('|', \array_keys($inject['columns']));

				foreach ($inject['columns'] as $column) {
					$inject['columns_prefix'] = $column['prefix'];

					break;
				}

				$entity_content                           = $ts_entity_class_tpl->runGet($inject);
				$entity_base_content                      = $ts_entity_base_class_tpl->runGet($inject);
				$bundle_inject['entities'][$entity_class] = $entity_content;
				$this->writeFile($path_db . $ds . $entity_class . '.ts', $entity_content, false);
				$this->writeFile($path_db_base . $ds . $entity_base_class . '.ts', $entity_base_content, true);
			}
		}

		$bundle_inject['gobl_header']  = $header;
		$bundle_inject['gobl_time']    = $time;
		$bundle_inject['gobl_version'] = Gobl::VERSION;

		$this->writeFile($path_gobl . $ds . 'index.ts', $ts_bundle_tpl->runGet($bundle_inject), true);

		return $this;
	}
}

GeneratorTS::setTemplates([
	'ts.bundle'            => ['path' => Gobl::SAMPLES_DIR . '/ts/TSBundle.ts'],
	'ts.entity.base.class' => ['path' => Gobl::SAMPLES_DIR . '/ts/MyEntityBase.ts'],
	'ts.entity.class'      => ['path' => Gobl::SAMPLES_DIR . '/ts/MyEntity.ts'],
]);
