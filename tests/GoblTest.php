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

namespace Gobl\Tests;

use Gobl\Exceptions\GoblRuntimeException;
use Gobl\Gobl;

/**
 * Class GoblTest.
 *
 * @covers \Gobl\Gobl
 *
 * @internal
 */
final class GoblTest extends BaseTestCase
{
	public function testSetRootDir(): void
	{
		$non_existent_path = GOBL_TEST_PROJECT_DIR . '/nothing';

		Gobl::setProjectCacheDir(GOBL_TEST_PROJECT_DIR);
		self::assertSame(GOBL_TEST_PROJECT_DIR, Gobl::getProjectCacheDir());

		Gobl::setProjectCacheDir(GOBL_TEST_PROJECT_DIR . \DIRECTORY_SEPARATOR);
		self::assertSame(GOBL_TEST_PROJECT_DIR, Gobl::getProjectCacheDir());

		$this->expectException(GoblRuntimeException::class);
		Gobl::setProjectCacheDir($non_existent_path);
	}

	public function testGetRootDir(): void
	{
		Gobl::setProjectCacheDir(GOBL_TEST_PROJECT_DIR);
		self::assertSame(GOBL_TEST_PROJECT_DIR, Gobl::getProjectCacheDir());
	}

	public function testGetCacheDir(): void
	{
		Gobl::setProjectCacheDir(GOBL_TEST_PROJECT_DIR);
		$expected = GOBL_TEST_PROJECT_DIR . \DIRECTORY_SEPARATOR . '.gobl' . \DIRECTORY_SEPARATOR . 'cache';
		self::assertSame($expected, Gobl::getGoblCacheDir());
	}

	public function testAddTemplate(): void
	{
		$template_name              = 'test_template';
		$template_path              = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'test_template.txt';
		$non_existent_template_path = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'test_non_existent.txt';

		Gobl::addTemplate($template_name, $template_path);

		self::assertFileExists(Gobl::getTemplateFilePath($template_name));

		$this->expectException(GoblRuntimeException::class);
		$this->expectExceptionMessage(\sprintf(
			'Template "%s" path "%s" should be a valid file path.',
			$template_name,
			$non_existent_template_path,
		));

		Gobl::addTemplate($template_name, $non_existent_template_path);
	}

	public function testAddTemplates(): void
	{
		$template = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'test_template.txt';

		Gobl::addTemplates(['test_template_array' => ['path' => $template]]);

		self::assertFileExists(Gobl::getTemplateFilePath('test_template_array'));

		Gobl::addTemplates(['test_template_array' => ['path' => $template]]);
	}

	public function testGetTemplateFilePath(): void
	{
		$expected = Gobl::getGoblCacheDir() . \DIRECTORY_SEPARATOR . 'test_template.otpl';

		self::assertSame($expected, Gobl::getTemplateFilePath('test_template'));
	}

	public function testGetUnknownTemplateCompiler(): void
	{
		$this->expectException(GoblRuntimeException::class);
		$this->expectExceptionMessage('Gobl template parse error.');

		Gobl::getTemplateCompiler('unknown');
	}
}
