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

namespace Gobl\Tests\ORM;

use Gobl\ORM\Generators\CSGeneratorTS;
use Gobl\ORM\ORMShape;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\TestPaths;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class CSGeneratorTSShapeTest.
 *
 * The shapes written beside the generated entities: that they are written only when asked, that
 * nothing in the bundle imports them, and that the printer loses nothing of the mapping.
 *
 * @internal
 */
#[CoversClass(CSGeneratorTS::class)]
final class CSGeneratorTSShapeTest extends BaseTestCase
{
	/**
	 * Off unless asked: a project without `@oweb-io/schema` would be handed TypeScript it cannot
	 * compile, and the entities have never needed it.
	 */
	public function testNoShapeIsWrittenUnlessAsked(): void
	{
		$out = $this->generate(false);

		self::assertDirectoryDoesNotExist($out . '/gobl/db/shapes');
		self::assertFileExists($out . '/gobl/db/base/AccountBase.ts');
	}

	public function testEachEntityGetsItsTwoShapesAndWhatIdentifiesIt(): void
	{
		$out = $this->generate(true);

		foreach (['Account', 'Client', 'Currency', 'Transaction'] as $entity) {
			self::assertFileExists($out . "/gobl/db/shapes/{$entity}Shape.ts");
		}

		$written = (string) \file_get_contents($out . '/gobl/db/shapes/AccountShape.ts');

		// A type-only import: erased at compile time, so nothing of this reaches a bundle.
		self::assertStringContainsString(
			"import type { Shape } from '@oweb-io/schema';",
			$written
		);
		self::assertStringContainsString('export const ACCOUNT_SHAPE: Shape = {', $written);
		self::assertStringContainsString('export const ACCOUNT_WRITE_SHAPE: Shape = {', $written);
		self::assertStringContainsString('export const ACCOUNT_ENTITY = {', $written);
		// Indented as the generated TypeScript is, not as `json_encode()` indents.
		self::assertStringContainsString("\n\t\"t\": \"object\",", $written);
		self::assertStringNotContainsString("\n    \"t\"", $written);
	}

	/**
	 * What a page is handed is the data and the view, never the structure behind them: so the
	 * bundle every page imports says nothing about any of this.
	 */
	public function testTheBundleDoesNotImportThem(): void
	{
		$out    = $this->generate(true);
		$bundle = (string) \file_get_contents($out . '/gobl/index.ts');

		self::assertStringNotContainsString('shapes', $bundle);
		self::assertStringNotContainsString('SHAPE', $bundle);

		// Their own index is where a build reads them from.
		$index = (string) \file_get_contents($out . '/gobl/db/shapes/index.ts');

		self::assertStringContainsString("'accounts': {", $index);
		self::assertStringContainsString('read: ACCOUNT_SHAPE,', $index);
		self::assertStringContainsString('write: ACCOUNT_WRITE_SHAPE,', $index);
	}

	/**
	 * The printed literal is the mapping, exactly: a shape is JSON, and JSON is a TypeScript
	 * object literal, so reading the one back answers the other.
	 */
	public function testThePrintedLiteralIsTheMappingItself(): void
	{
		$out      = $this->generate(true);
		$written  = (string) \file_get_contents($out . '/gobl/db/shapes/AccountShape.ts');
		$expected = ORMShape::ofTable(
			self::getNewDbInstanceWithSchema()
				->getTableOrFail('accounts')
		);

		self::assertSame($expected['read'], $this->literalOf($written, 'ACCOUNT_SHAPE: Shape'));
		self::assertSame(
			$expected['write'],
			$this->literalOf($written, 'ACCOUNT_WRITE_SHAPE: Shape')
		);
	}

	/**
	 * Generates the whole TypeScript bundle into a directory of its own and answers it.
	 */
	private function generate(bool $shapes): string
	{
		$out = TestPaths::OUTPUT . \DIRECTORY_SEPARATOR . 'shapes-' . ($shapes ? 'on' : 'off');

		self::rmDirRecursive($out);
		\mkdir($out, 0o755, true);

		$db = self::getNewDbInstanceWithSchema();

		new CSGeneratorTS($db)
			->generateShapes($shapes)
			->generate($db->getTables(), $out);

		return $out;
	}

	/**
	 * The object literal a named constant is declared with, read back as data.
	 *
	 * @return array<string, mixed>
	 */
	private function literalOf(string $written, string $declaration): array
	{
		$at = \strpos($written, "export const {$declaration} = ");

		self::assertNotFalse($at, "No declaration of {$declaration}.");

		$from = \strpos($written, '{', $at);

		self::assertNotFalse($from);

		// To the line that closes it at column zero, which is how the literal is printed.
		$to = \strpos($written, "\n};", $from);

		self::assertNotFalse($to);

		$literal = \substr($written, $from, $to - $from + 2);
		$read    = \json_decode($literal, true, 512, \JSON_THROW_ON_ERROR);

		self::assertIsArray($read);

		return $read;
	}
}
