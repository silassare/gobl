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
use Gobl\ORM\ORMShape;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use OLIUP\CG\PHPEnum;
use Override;

/**
 * Class CSGeneratorTS.
 */
final class CSGeneratorTS extends CSGenerator
{
	protected static bool $templates_registered = false;

	/**
	 * Whether a shape is written beside each entity ({@see ORMShape}).
	 *
	 * Off unless asked, because the files it writes import a type from `@oweb-io/schema`: a
	 * project that does not have that package would be handed TypeScript it cannot compile, and
	 * the entities themselves have never needed it.
	 */
	protected bool $generate_shapes = false;

	/**
	 * Writes a shape beside each generated entity: what a row of it is, what writing one takes,
	 * and what identifies one.
	 *
	 * They go in their own directory and nothing in the bundle imports them, so what reads a
	 * table's structure is a build and never a page.
	 */
	public function generateShapes(bool $generate = true): static
	{
		$this->generate_shapes = $generate;

		return $this;
	}

	/**
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
				'ts.entity.shape'      => 'ts/MyEntityShape.ts.blate',
				'ts.shapes'            => 'ts/TSShapes.ts.blate',
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
		$shapes_inject            = [];
		$path_shapes              = $path_db . $ds . 'shapes';

		if ($this->generate_shapes) {
			$fs->mkdir($path_shapes);
		}

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

			// The first column, for the prefix every column of the table shares. `next()` answered
			// the second one, so a table with a single column left `columns_prefix` unset and the
			// template rendered an empty prefix.
			$column = \array_first($inject['columns']);

			if (null !== $column) {
				$inject['columns_prefix'] = $column['prefix'];
			}

			$entity_content                           = $ts_entity_class_tpl->runGet($inject);
			$entity_base_content                      = $ts_entity_base_class_tpl->runGet($inject);
			$bundle_inject['entities'][$entity_class] = $entity_content;
			$bundle_inject['rows'][$entity_class]     = $entity_base_class;
			$this->writeFile($path_db_base . $ds . $entity_base_class . '.ts', $entity_base_content);
			$this->writeFile($path_db . $ds . $entity_class . '.ts', $entity_content, false);

			if (!$this->generate_shapes) {
				continue;
			}

			$shape                           = $this->describeTableShape($table);
			$inject['shape']                 = $shape;
			$shapes_inject[$entity_class]    = $shape;
			$this->writeFile(
				$path_shapes . $ds . $entity_class . 'Shape.ts',
				Gobl::getTemplateCompiler('ts.entity.shape')
					->runGet($inject)
			);
		}

		$bundle_inject['gobl_header']  = $header;
		$bundle_inject['gobl_time']    = $time;
		$bundle_inject['gobl_version'] = GOBL_VERSION;
		$bundle_inject['enums']        = $this->enums_infos;

		$this->writeFile($path_gobl . $ds . 'enums.ts', $ts_enums_tpl->runGet($bundle_inject));
		$this->writeFile($path_gobl . $ds . 'index.ts', $ts_bundle_tpl->runGet($bundle_inject));

		if ([] !== $shapes_inject) {
			$shapes_bundle = $bundle_inject;

			$shapes_bundle['shapes'] = $shapes_inject;

			$this->writeFile(
				$path_shapes . $ds . 'index.ts',
				Gobl::getTemplateCompiler('ts.shapes')
					->runGet($shapes_bundle)
			);
		}

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
	 * A table's shapes, written out as the TypeScript the template prints.
	 *
	 * A shape **is** JSON, and JSON is a TypeScript object literal: there is nothing to translate,
	 * only to indent. So the mapping has one owner ({@see ORMShape}) and this is the printer.
	 *
	 * @return array{const: string, name: string, read: string, write: string, entity: string}
	 */
	private function describeTableShape(Table $table): array
	{
		$described = ORMShape::ofTable(
			$table,
			$this->ignore_private_columns,
			$this->ignore_sensitive_columns
		);

		$entity = $described;

		unset($entity['read'], $entity['write']);

		return [
			// `ACCOUNT`: the singular name the entity class is itself built from
			// ({@see ORMClassKind::ENTITY}), so the two always agree.
			'const'  => \strtoupper($table->getSingularName()),
			'name'   => $described['name'],
			'read'   => self::toTSLiteral($described['read']),
			'write'  => self::toTSLiteral($described['write']),
			'entity' => self::toTSLiteral($entity),
		];
	}

	/**
	 * A JSON-ready value as a TypeScript literal, indented with tabs as the generated files are.
	 */
	private static function toTSLiteral(array $value): string
	{
		$written = \json_encode(
			$value,
			\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR
		);

		// `json_encode()` indents with four spaces and the generated TypeScript uses tabs; only the
		// indent is touched, never four spaces inside a value.
		return (string) \preg_replace_callback(
			'~^(?: {4})+~m',
			static fn (array $m): string => \str_repeat("\t", \intdiv(\strlen($m[0]), 4)),
			$written
		);
	}

	/**
	 * The enums a table's columns are typed with, so the entity imports exactly those.
	 *
	 * It skips the columns the entity itself skips (private and sensitive ones, as
	 * {@see CSGenerator::describeTableColumns()} does), so that nothing is imported and left unused,
	 * which TypeScript reports under `noUnusedLocals`.
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
