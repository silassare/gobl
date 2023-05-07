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
	/**
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function testTableBuilder(): void
	{
		$db = self::getSampleDB();

		static::assertSame([
			'users',
			'roles',
			'tags',
			'taggables',
			'articles',
		], \array_keys($db->getTables()));

		$tbl_users = $db->getTable('users');

		static::assertSame([
			'id',
			'name',
		], \array_keys($tbl_users->getColumns()));

		$tbl_roles = $db->getTable('roles');

		static::assertSame([
			'id',
			'title',
			'user_id',
		], \array_keys($tbl_roles->getColumns()));

		$role_user_id_column = $tbl_roles->getColumn('user_id');

		static::assertSame([
			'type'     => 'bigint',
			'unsigned' => true,
		], $role_user_id_column->getType()
			->toArray());

		static::assertSame([
			'diff_key' => $role_user_id_column->getDiffKey(),
			'type'     => 'ref:users.id',
			'unsigned' => true,
		], $role_user_id_column->toArray());
	}
}
