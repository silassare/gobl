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

namespace Gobl\ORM;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Gobl;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use PHPUtils\FS\FSUtils;
use Throwable;

/**
 * Class ORM.
 */
class ORM
{
	/** @var array<string, array{db:RDBMSInterface, out_dir:string}> */
	private static array $namespaces = [];

	/**
	 * @param string                               $namespace
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 * @param string                               $out_dir
	 */
	public static function declareNamespace(string $namespace, RDBMSInterface $db, string $out_dir): void
	{
		if (isset(self::$namespaces[$namespace])) {
			throw new ORMRuntimeException(\sprintf('Namespace "%s" is already declared.', $namespace));
		}

		$fs       = new FSUtils(Gobl::getProjectCacheDir());
		$abs_path = $fs->resolve($out_dir);

		try {
			$fs->filter()
				->isWritable()
				->isDir()
				->assert($abs_path);

			self::$namespaces[$namespace] = [
				'db'      => $db,
				'out_dir' => $abs_path,
			];
		} catch (Throwable $t) {
			throw new ORMRuntimeException(\sprintf('Invalid ORM output directory declared for "%s".', $namespace), [
				'out_dir'       => $out_dir,
				'absolute_path' => $abs_path,
				'namespace'     => $namespace,
			], $t);
		}
	}

	/**
	 * Returns the db instance for the given namespace.
	 *
	 * @param string $namespace the database namespace
	 *
	 * @return RDBMSInterface
	 */
	public static function getDatabase(string $namespace): RDBMSInterface
	{
		if (!isset(self::$namespaces[$namespace])) {
			throw new ORMRuntimeException(\sprintf('Namespace "%s" was not declared.', $namespace));
		}

		return self::$namespaces[$namespace]['db'];
	}

	/**
	 * Returns the output directory for the given namespace.
	 *
	 * @param string $namespace the database namespace
	 *
	 * @return string
	 */
	public static function getOutputDirectory(string $namespace): string
	{
		if (!isset(self::$namespaces[$namespace])) {
			throw new ORMRuntimeException(\sprintf('Namespace "%s" was not declared.', $namespace));
		}

		return self::$namespaces[$namespace]['out_dir'];
	}
}
