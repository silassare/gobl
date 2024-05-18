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
		$db = self::getEmptyDb();
		$ns = $db->ns('test');
		$ns->table('users', static function (TableBuilder $t) {
			$t->columnPrefix('usr');
			$t->id();
			$t->string('name');
			$t->string('phone');
		});

		$ns->table('commands', static function (TableBuilder $t) {
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
		$db       = self::getSampleDB();
		$articles = $db->getTableOrFail('articles');

		$qb_a = new QBSelect($db);
		$qb_a->select()
			->from('tags');

		$articles->getRelation('tags')
			->getLink()
			->apply($qb_a);

		self::assertSame('SELECT * FROM gObL_tags _a_  INNER JOIN gObL_taggables _b_ ON (_a_.id = _b_.tag_id) INNER JOIN gObL_articles _c_ ON (_b_.taggable_id = _c_.id AND _b_.taggable_type = :_val_d) WHERE 1 = 1', $qb_a->getSqlQuery());
		self::assertSame(['_val_d' => 'articles'], $qb_a->getBoundValues());

		$qb_b = new QBSelect($db);
		$qb_b->select()
			->from('tags');

		$articles->getRelation('recently_added_tags')
			->getLink()
			->apply($qb_b);

		self::assertSame('SELECT * FROM gObL_tags _e_  INNER JOIN gObL_taggables _f_ ON (_e_.id = _f_.tag_id) INNER JOIN gObL_articles _g_ ON (_f_.taggable_id = _g_.id AND _f_.taggable_type = :_val_h) WHERE ((_f_.created_at > :_val_i))', $qb_b->getSqlQuery());
		self::assertSame([
			'_val_h' => 'articles',
			'_val_i' => '2020-01-01',
		], $qb_b->getBoundValues());
	}
}
