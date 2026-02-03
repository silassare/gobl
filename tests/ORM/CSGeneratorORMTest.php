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

namespace Gobl\Tests\ORM;

use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\Tests\BaseTestCase;

/**
 * Class CSGeneratorORMTest.
 *
 * @covers \Gobl\ORM\Generators\CSGeneratorORM
 *
 * @internal
 */
final class CSGeneratorORMTest extends BaseTestCase
{
	public function testClassFiles(): void
	{
		$db = self::getDb();

		// $expect_dir = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'Db';
		$out_dir = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'Db';

		if (!\is_dir($out_dir)) {
			@\mkdir($out_dir, 0o777, true);
		}

		$generator = new CSGeneratorORM($db);

		$generator->generate($db->getTables(), $out_dir);

		self::assertDirectoryExists($out_dir . \DIRECTORY_SEPARATOR . 'Base');
	}
}
