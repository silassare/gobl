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

class GeneratorORM extends Generator
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
			$inject                 = $this->describeTable($table);
			$inject['gobl_header']  = $header;
			$inject['gobl_time']    = $time;
			$inject['gobl_version'] = Gobl::VERSION;
			$query_class            = $inject['class']['query'];
			$entity_class           = $inject['class']['entity'];
			$results_class          = $inject['class']['results'];
			$ctrl_class             = $inject['class']['controller'];

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
}

GeneratorORM::setTemplates([
	'base.query.class'      => ['path' => Gobl::SAMPLES_DIR . '/php/Base/MyTableQuery.php'],
	'base.entity.class'     => ['path' => Gobl::SAMPLES_DIR . '/php/Base/MyEntity.php'],
	'base.results.class'    => ['path' => Gobl::SAMPLES_DIR . '/php/Base/MyResults.php'],
	'base.controller.class' => ['path' => Gobl::SAMPLES_DIR . '/php/Base/MyController.php'],
	'query.class'           => ['path' => Gobl::SAMPLES_DIR . '/php/MyTableQuery.php'],
	'entity.class'          => ['path' => Gobl::SAMPLES_DIR . '/php/MyEntity.php'],
	'results.class'         => ['path' => Gobl::SAMPLES_DIR . '/php/MyResults.php'],
	'controller.class'      => ['path' => Gobl::SAMPLES_DIR . '/php/MyController.php'],
]);
