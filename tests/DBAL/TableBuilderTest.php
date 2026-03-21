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

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\Tests\BaseTestCase;

/**
 * Class TableBuilderTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TableBuilderTest extends BaseTestCase
{
	public function testTableBuilder(): void
	{
		$db = self::getSampleDB();

		self::assertSame([
			'users',
			'roles',
			'tags',
			'taggables',
			'articles',
		], \array_keys($db->getTables()));

		$tbl_users = $db->getTable('users');

		self::assertSame([
			'id',
			'name',
			'deleted',
			'deleted_at',
		], \array_keys($tbl_users->getColumns()));

		$tbl_roles = $db->getTable('roles');

		self::assertSame([
			'id',
			'title',
			'user_id',
		], \array_keys($tbl_roles->getColumns()));

		$role_user_id_column = $tbl_roles->getColumn('user_id');

		self::assertSame([
			'type'     => 'bigint',
			'unsigned' => true,
			'nullable' => false,
		], $role_user_id_column->getType()
			->toArray());

		self::assertSame([
			'type'     => 'ref:users.id',
			'unsigned' => true,
			'nullable' => false,
			'diff_key' => $role_user_id_column->getDiffKey(),
		], $role_user_id_column->toArray());

		self::assertTrue($tbl_users->isSoftDeletable());
	}

	public function testUseColumnReturnsExistingColumn(): void
	{
		$db = self::getNewDbInstance();

		$db->ns('App\Db')->table('users', static function (TableBuilder $t): void {
			$t->id();
			$t->string('email');
			$col = $t->useColumn('email');
			self::assertInstanceOf(Column::class, $col);
			self::assertSame('email', $col->getName());
		});
	}

	public function testUseColumnThrowsForUnknownColumn(): void
	{
		$db = self::getNewDbInstance();

		$this->expectException(DBALRuntimeException::class);

		$db->ns('App\Db')->table('users', static function (TableBuilder $t): void {
			$t->id();
			$t->useColumn('nonexistent');
		});
	}
}
