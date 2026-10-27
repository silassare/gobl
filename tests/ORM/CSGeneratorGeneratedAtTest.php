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

use Gobl\Gobl;
use Gobl\ORM\Generators\CSGenerator;
use Gobl\ORM\Generators\CSGeneratorDart;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\Generators\CSGeneratorTS;
use Gobl\Tests\BaseTestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Class CSGeneratorGeneratedAtTest.
 *
 * With the generation time disabled, a header no longer changes from one run to the next.
 *
 * @internal
 */
#[CoversMethod(Gobl::class, 'enableGeneratedAtDate')]
final class CSGeneratorGeneratedAtTest extends BaseTestCase
{
	#[Override]
	protected function tearDown(): void
	{
		Gobl::enableGeneratedAtDate();

		parent::tearDown();
	}

	public function testGeneratedAtDateIsNullWhenDisabled(): void
	{
		self::assertIsString(Gobl::getGeneratedAtDate());

		Gobl::enableGeneratedAtDate(false);

		self::assertNull(Gobl::getGeneratedAtDate());
	}

	public function testGeneratorsWriteTheTimeByDefault(): void
	{
		$db = self::getNewDbInstanceWithSchema();

		$headers = $this->withHeader($this->generate('time-on'));

		self::assertNotEmpty($headers);

		foreach ($headers as $path => $content) {
			self::assertStringContainsString('Time: ', $content, $path);
		}

		self::assertStringContainsString('-- Time: ', $db->getGenerator()->buildDatabase());
	}

	public function testGeneratorsOmitTheTimeWhenDisabled(): void
	{
		$with_time = $this->withHeader($this->generate('time-on'));

		Gobl::enableGeneratedAtDate(false);

		$db = self::getNewDbInstanceWithSchema();

		$files = $this->generate('time-off');

		foreach ($files as $path => $content) {
			self::assertStringNotContainsString('Time:', $content, $path);
		}

		// The version line stays: it changes only when Gobl does.
		self::assertSame(\array_keys($with_time), \array_keys($this->withHeader($files)));

		$sql = $db->getGenerator()->buildDatabase();

		self::assertStringNotContainsString('Time:', $sql);
		self::assertStringContainsString('-- With: ', $sql);

		// A second run from the same schema writes the same bytes.
		self::assertSame($files, $this->generate('time-off-again'));
	}

	/**
	 * The generated files that carry a header (a class a developer edits has none).
	 *
	 * @param array<string, string> $files
	 *
	 * @return array<string, string>
	 */
	private function withHeader(array $files): array
	{
		return \array_filter($files, static fn (string $content) => \str_contains($content, 'Proudly With: '));
	}

	/**
	 * Runs every generator into its own directory and returns each generated file's content, keyed by
	 * its path relative to that directory.
	 *
	 * @return array<string, string>
	 */
	private function generate(string $name): array
	{
		$db  = self::getNewDbInstanceWithSchema();
		$out = [];

		/** @var array<string, CSGenerator> $generators */
		$generators = [
			'orm'  => new CSGeneratorORM($db),
			'ts'   => new CSGeneratorTS($db),
			'dart' => new CSGeneratorDart($db),
		];

		foreach ($generators as $kind => $generator) {
			$dir = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'generated-at' . \DIRECTORY_SEPARATOR . $name
				. \DIRECTORY_SEPARATOR . $kind;

			self::rmDirRecursive($dir);
			\mkdir($dir, 0755, true);

			$generator->generate($db->getTables(), $dir);

			foreach (self::scanFiles($dir) as $path) {
				$out[$kind . \substr($path, \strlen($dir))] = (string) \file_get_contents($path);
			}
		}

		self::assertNotEmpty($out);

		return $out;
	}
}
