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
use Gobl\Tests\BaseTestCase;

/**
 * Class DBConfigTest.
 *
 * @covers \Gobl\DBAL\DBConfig
 *
 * @internal
 */
final class DBConfigTest extends BaseTestCase
{
	private DBConfig $config;
	private array    $declared = [
		'db_table_prefix' => 'gObL',
		'db_host'         => 'localhost',
		'db_name'         => 'gobl_test_db',
		'db_user'         => 'gobl_user',
		'db_pass'         => 'gobl_user_pass',
		'db_charset'      => 'utf8mb4',
		'db_collate'      => 'utf8mb4_unicode_ci',
	];

	protected function setUp(): void
	{
		parent::setUp();
		$this->config = new DbConfig($this->declared);
	}

	public function testGetDbUser(): void
	{
		static::assertSame($this->declared['db_user'], $this->config->getDbUser());
	}

	public function testGetDbCharset(): void
	{
		static::assertSame($this->declared['db_charset'], $this->config->getDbCharset());
	}

	public function testGetDbCollate(): void
	{
		static::assertSame($this->declared['db_collate'], $this->config->getDbCollate());
	}

	public function testGetDbHost(): void
	{
		static::assertSame($this->declared['db_host'], $this->config->getDbHost());
	}

	public function testGetDbName(): void
	{
		static::assertSame($this->declared['db_name'], $this->config->getDbName());
	}

	public function testGetDbPass(): void
	{
		static::assertSame($this->declared['db_pass'], $this->config->getDbPass());
	}

	public function testGetDbTablePrefix(): void
	{
		static::assertSame($this->declared['db_table_prefix'], $this->config->getDbTablePrefix());
	}
}
