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

namespace Gobl\Tests;

/**
 * Class TestPaths.
 *
 * Where the suite's own files live, as constants rather than as globals the bootstrap defines: a
 * `define()` in one file is invisible to every other as far as any tool can tell, so a test reading
 * one was reading something nothing declared.
 *
 * `GOBL_TEST_MODE` stays a global constant, because `CSGeneratorORM` asks `defined()` for it: it is
 * the suite telling the library it is the suite.
 *
 * @internal
 */
final class TestPaths
{
	/**
	 * The suite's own directory.
	 */
	public const string ROOT = __DIR__;

	/**
	 * The schemas, fixtures and snapshots the tests read.
	 */
	public const string ASSETS = self::ROOT . \DIRECTORY_SEPARATOR . 'assets';

	/**
	 * The recorded output a test compares against.
	 */
	public const string SNAPSHOTS = self::ASSETS . \DIRECTORY_SEPARATOR . 'snapshots';

	/**
	 * What a run writes: emptied by `make test`.
	 */
	public const string PROJECT_DIR = self::ROOT . \DIRECTORY_SEPARATOR . 'tmp';

	/**
	 * Where a generator writes in a run.
	 */
	public const string OUTPUT = self::PROJECT_DIR . \DIRECTORY_SEPARATOR . 'output';

	/**
	 * Where the ORM classes a run generates land.
	 */
	public const string ORM_OUTPUT = self::OUTPUT . \DIRECTORY_SEPARATOR . 'ORM_Db';
}
