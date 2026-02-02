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

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\Tests\BaseTestCase;

/**
 * Class DbConfigTest.
 *
 * @covers \Gobl\DBAL\DBConfig
 *
 * @internal
 */
final class DbConfigTest extends BaseTestCase
{
	private DbConfig $config;
	private array $declared;

	protected function setUp(): void
	{
		parent::setUp();

		$all = include __DIR__ . '/../db.configs.php';

		$this->declared = $all[MySQL::NAME];
		$this->config   = new DbConfig($this->declared);
	}

	public function testGetDbUser(): void
	{
		self::assertSame($this->declared['db_user'], $this->config->getDbUser());
	}

	public function testGetDbCharset(): void
	{
		self::assertSame($this->declared['db_charset'], $this->config->getDbCharset());
	}

	public function testGetDbCollate(): void
	{
		self::assertSame($this->declared['db_collate'], $this->config->getDbCollate());
	}

	public function testGetDbHost(): void
	{
		self::assertSame($this->declared['db_host'], $this->config->getDbHost());
	}

	public function testGetDbPort(): void
	{
		self::assertSame($this->declared['db_port'], $this->config->getDbPort());
	}

	public function testGetDbName(): void
	{
		self::assertSame($this->declared['db_name'], $this->config->getDbName());
	}

	public function testGetDbPass(): void
	{
		self::assertSame($this->declared['db_pass'], $this->config->getDbPass());
	}

	public function testGetDbTablePrefix(): void
	{
		self::assertSame($this->declared['db_table_prefix'], $this->config->getDbTablePrefix());
	}
}
