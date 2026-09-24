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
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use OLIUP\CG\PHPEnum;
use Override;

/**
 * Class CSGeneratorTS.
 */
final class CSGeneratorTS extends CSGenerator
{
	/**
	 * @var bool
	 */
	protected static bool $templates_registered = false;

	/**
	 * {@inheritDoc}
	 *
	 * @throws Exception
	 */
	#[Override]
	public function generate(array $tables, ?string $path = null, string $header = ''): static
	{
		if (!self::$templates_registered) {
			self::$templates_registered = true;

			Gobl::addTemplates([
				'ts.bundle'            => 'ts/TSBundle.ts.blate',
				'ts.enums'             => 'ts/TSEnums.ts.blate',
				'ts.entity.base.class' => 'ts/MyEntityBase.ts.blate',
				'ts.entity.class'      => 'ts/MyEntity.ts.blate',
			]);
		}

		$fs           = self::outputDirFS($path);
		$path         = $fs->getRoot();
		$ds           = \DIRECTORY_SEPARATOR;
		$path_gobl    = $path . $ds . 'gobl';
		$path_db      = $path_gobl . $ds . 'db';
		$path_db_base = $path_db . $ds . 'base';

		$fs->mkdir($path_db_base);

		$ts_entity_class_tpl      = Gobl::getTemplateCompiler('ts.entity.class');
		$ts_entity_base_class_tpl = Gobl::getTemplateCompiler('ts.entity.base.class');
		$ts_bundle_tpl            = Gobl::getTemplateCompiler('ts.bundle');
		$ts_enums_tpl             = Gobl::getTemplateCompiler('ts.enums');
		$bundle_inject            = [];
		$time                     = Gobl::getGeneratedAtDate();

		foreach ($tables as $table) {
			if ($this->ignore_private_tables && $table->isPrivate()) {
				continue;
			}

			$inject                 = $this->describeTable($table);
			$inject['gobl_header']  = $header;
			$inject['gobl_time']    = $time;
			$inject['gobl_version'] = GOBL_VERSION;
			$entity_class           = $inject['class']['entity'];
			$entity_base_class      = $inject['class']['entity_base'];
			$inject['columns_list'] = \implode('|', \array_keys($inject['columns']));
			$inject['enums_used']   = $this->enumsUsedBy($table);

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

		$bundle_inject['gobl_header']  = $header;
		$bundle_inject['gobl_time']    = $time;
		$bundle_inject['gobl_version'] = GOBL_VERSION;
		$bundle_inject['enums']        = $this->enums_infos;

		$this->writeFile($path_gobl . $ds . 'enums.ts', $ts_enums_tpl->runGet($bundle_inject));
		$this->writeFile($path_gobl . $ds . 'index.ts', $ts_bundle_tpl->runGet($bundle_inject));

		return $this;
	}

	#[Override]
	public function toTypeHintString(ORMTypeHint $type_hint): string
	{
		$types    = $type_hint->getUniversalTypes();
		$ts_types = [];

		// An enum column is a string to the database, and the enum to everything else. The enum class
		// travels on the PHP type (`TypeEnum::getReadTypeHint()`), which only the PHP generator used to
		// read, so TypeScript saw `string` even though `enums.ts` had just generated the enum next to
		// it. The name is built the way `declareEnum()` builds it, so the two always agree.
		$enum = $this->enumNameOf($type_hint);

		if (null !== $enum) {
			return \in_array(ORMUniversalType::NULL, $types, true) ? $enum . '|null' : $enum;
		}

		foreach ($types as $type) {
			if (ORMUniversalType::LIST === $type) {
				// list_of_class: TS can't import foreign PHP classes, fall back to unknown[].
				$element    = null !== $type_hint->getListOfClass()
					? 'unknown'
					: $this->toTSType($type_hint->getListOfUniversalType());
				$ts_types[] = $element . '[]';

				continue;
			}

			if (ORMUniversalType::MAP === $type) {
				// map_of_class: TS can't import foreign PHP classes, fall back to Record<string, unknown>.
				$element    = null !== $type_hint->getMapOfClass()
					? 'unknown'
					: $this->toTSType($type_hint->getMapOfUniversalType());

				$ts_types[] = 'Record<string, ' . $element . '>';

				continue;
			}

			$ts_types[] = $this->toTSType($type);
		}

		return \implode('|', $ts_types);
	}

	/**
	 * The enums a table's columns are typed with, so the entity imports exactly those.
	 *
	 * It skips the columns the entity itself skips (private and sensitive ones, as
	 * {@see CSGenerator::describeTableColumns()} does), so that nothing is imported and left unused,
	 * which TypeScript reports under `noUnusedLocals`.
	 *
	 * @param Table $table
	 *
	 * @return string[]
	 */
	private function enumsUsedBy(Table $table): array
	{
		$names = [];

		foreach ($table->getColumns() as $column) {
			if ($this->ignore_private_columns && $column->isPrivate()) {
				continue;
			}

			if ($this->ignore_sensitive_columns && $column->isSensitive()) {
				continue;
			}

			$type = $column->getType();

			foreach ([$type->getReadTypeHint(), $type->getWriteTypeHint()] as $hint) {
				$name = $this->enumNameOf($hint);

				if (null !== $name) {
					$names[$name] = true;
				}
			}
		}

		return \array_keys($names);
	}

	/**
	 * The name of the generated TS enum a type hint refers to, if it refers to one.
	 *
	 * @param ORMTypeHint $type_hint
	 *
	 * @return null|string
	 */
	private function enumNameOf(ORMTypeHint $type_hint): ?string
	{
		$php_type = $type_hint->getPHPType();

		if (null === $php_type) {
			return null;
		}

		foreach ($php_type->getTypes() as $one) {
			if ($one instanceof PHPEnum) {
				return $one->getName();
			}
		}

		return null;
	}

	/**
	 * Maps a single ORMUniversalType to its TypeScript string representation.
	 */
	private function toTSType(ORMUniversalType $type): string
	{
		return match ($type) {
			ORMUniversalType::LIST                                                        => 'unknown[]',
			ORMUniversalType::MAP                                                         => 'Record<string, unknown>',
			ORMUniversalType::STRING, ORMUniversalType::DECIMAL, ORMUniversalType::BIGINT => 'string',
			ORMUniversalType::FLOAT, ORMUniversalType::INT                                => 'number',
			ORMUniversalType::BOOL                                                        => 'boolean',
			ORMUniversalType::NULL                                                        => 'null',
			ORMUniversalType::ANY                                                         => 'any',
			ORMUniversalType::UNKNOWN                                                     => 'unknown',
		};
	}
}
