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

namespace Gobl\Tests\DBAL\Queries;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Queries\NamedToPositionalParams;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBSelectTest.
 *
 * @covers \Gobl\DBAL\Queries\QBSelect
 *
 * @internal
 */
final class QBSelectTest extends BaseTestCase
{
	/**
	 * @throws DBALException
	 */
	public function testFullyQualifiedNameArray(): void
	{
		$db    = self::getEmptyDb();
		$ns    = $db->ns('test');
		$users = $ns->table('users', static function (TableBuilder $t) {
			$t->columnPrefix('usr');
			$t->id();
			$t->string('name');
			$t->string('phone');
		});

		$commands = $ns->table('commands', static function (TableBuilder $t) {
			$t->columnPrefix('cmd');
			$t->id();
			$t->string('title');
			$t->string('phone');
		});

		$db->lock();

		$qb = new QBSelect($db);

		$qb->from('users', 'u'); // main alias
		$qb->alias('users', 'b'); // another alias
		$qb->from('commands', 'c');

		self::assertSame('u.usr_name', $qb->fullyQualifiedName('u', 'name'));
		self::assertSame(['c.cmd_id', 'c.cmd_phone', 'c.cmd_title'], $qb->fullyQualifiedNameArray('c', [
			'id',
			'phone',
			'title',
		]));

		self::assertSame(['foo.bar', 'foo.tar'], $qb->fullyQualifiedNameArray('foo', ['bar', 'tar']));

		self::assertSame(['u.usr_name'], $qb->fullyQualifiedNameArray('u', ['name']));
		self::assertSame(['c.cmd_id', 'c.cmd_phone', 'c.cmd_title'], $qb->fullyQualifiedNameArray('commands', [
			'id',
			'phone',
			'title',
		]));

		self::assertSame(['c.*'], $qb->fullyQualifiedNameArray('c'));
		self::assertSame(['foo.*'], $qb->fullyQualifiedNameArray('foo'));

		// when a specific alias is provided it should use it
		self::assertSame(['b.usr_name'], $qb->fullyQualifiedNameArray('b', ['name']));

		// correctly resolve main alias
		self::assertSame(['u.usr_name'], $qb->fullyQualifiedNameArray('users', ['name']));
	}

	/**
	 * @throws DBALException
	 */
	public function testRelationQuery(): void
	{
		$db = self::getSampleDB();

		$qb = new QBSelect($db);
		$qb->select()
			->from('tags');

		$db->getTableOrFail('articles')
			->getRelation('tags')
			->getLink()
			->apply($qb);

		$tags_full_name      = $db->getTableOrFail('tags')
			->getFullName();
		$tags_alias          = $qb->getMainAlias($tags_full_name);
		$articles_full_name  = $db->getTableOrFail('articles')
			->getFullName();
		$articles_alias      = $qb->getMainAlias($articles_full_name);
		$taggables_full_name = $db->getTableOrFail('taggables')
			->getFullName();
		$taggables_alias     = $qb->getMainAlias($taggables_full_name);

		$bound_values = $qb->getBoundValues();

		$sql = $qb->getSqlQuery();
		$n   = new NamedToPositionalParams($sql, $bound_values, $qb->getBoundValuesTypes());

		self::assertSame("SELECT * FROM {$tags_full_name} {$tags_alias}  INNER JOIN {$taggables_full_name} {$taggables_alias} ON ({$tags_alias}.id = {$taggables_alias}.tag_id) INNER JOIN {$articles_full_name} {$articles_alias} ON ({$taggables_alias}.taggable_id = {$articles_alias}.id AND {$taggables_alias}.taggable_type = ?) WHERE 1 = 1", $n->getNewQuery());

		self::assertSame(['articles'], $n->getNewParams());
	}
}
