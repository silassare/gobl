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

namespace Gobl\Tests\DBAL\Constraints;

use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Constraints\ForeignKeyAction;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\UniqueKey;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;

/**
 * Class ConstraintsTest.
 *
 * @covers \Gobl\DBAL\Constraints\PrimaryKey
 * @covers \Gobl\DBAL\Constraints\UniqueKey
 * @covers \Gobl\DBAL\Constraints\ForeignKey
 *
 * @internal
 */
final class ConstraintsTest extends BaseTestCase
{
    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private static function makeUsersTable(): Table
    {
        $t = new Table('users', 'u');
        $t->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
        $t->addColumn(new Column('email', 'u'));
        $t->addColumn(new Column('name', 'u'));
        $t->addPrimaryKeyConstraint(['id']);
        return $t;
    }

    private static function makePostsTable(): Table
    {
        $t = new Table('posts', 'p');
        $t->addColumn(new Column('id', 'p', ['type' => 'bigint', 'auto_increment' => true]));
        $t->addColumn(new Column('user_id', 'p', ['type' => 'bigint']));
        $t->addColumn(new Column('title', 'p'));

        return $t;
    }

    // -------------------------------------------------------------------
    // PrimaryKey
    // -------------------------------------------------------------------

    public function testPrimaryKeyAddColumnAndGetColumns(): void
    {
        $users = self::makeUsersTable();
        $pk    = new PrimaryKey('pk_users', $users);

        $pk->addColumn('id');

        self::assertSame(['u_id'], $pk->getColumns());
    }

    public function testPrimaryKeyRejectsNullableColumn(): void
    {
        $table  = new Table('items', 'i');
        $column = new Column('optional', 'i', ['type' => 'string', 'nullable' => true]);
        $table->addColumn($column);

        $pk = new PrimaryKey('pk_items', $table);

        $this->expectException(DBALException::class);
        $pk->addColumn('optional');
    }

    public function testPrimaryKeyToArray(): void
    {
        $users = self::makeUsersTable();
        $pk    = new PrimaryKey('pk_users', $users);
        $pk->addColumn('id');

        $arr = $pk->toArray();

        self::assertSame('primary_key', $arr['type']);
        self::assertSame(['id'], $arr['columns']);
    }

    public function testPrimaryKeyLockPreventsEdit(): void
    {
        $users = self::makeUsersTable();
        $pk    = new PrimaryKey('pk_users', $users);
        $pk->addColumn('id');
        $pk->lock();

        $this->expectException(DBALException::class);
        $pk->addColumn('email');
    }

    public function testPrimaryKeyIgnoresDuplicateColumn(): void
    {
        $users = self::makeUsersTable();
        $pk    = new PrimaryKey('pk_users', $users);
        $pk->addColumn('id');
        $pk->addColumn('id'); // duplicate

        self::assertCount(1, $pk->getColumns());
    }

    // -------------------------------------------------------------------
    // UniqueKey
    // -------------------------------------------------------------------

    public function testUniqueKeyAddColumnAndGetColumns(): void
    {
        $users = self::makeUsersTable();
        $uk    = new UniqueKey('uk_users_email', $users);

        $uk->addColumn('email');

        self::assertSame(['u_email'], $uk->getColumns());
    }

    public function testUniqueKeyToArray(): void
    {
        $users = self::makeUsersTable();
        $uk    = new UniqueKey('uk_users_email', $users);
        $uk->addColumn('email');

        $arr = $uk->toArray();

        self::assertSame('unique_key', $arr['type']);
        self::assertSame(['email'], $arr['columns']);
    }

    public function testUniqueKeyMultipleColumns(): void
    {
        $users = self::makeUsersTable();
        $uk    = new UniqueKey('uk_users_email_name', $users);
        $uk->addColumn('email');
        $uk->addColumn('name');

        self::assertSame(['u_email', 'u_name'], $uk->getColumns());
        self::assertSame(['email', 'name'], $uk->toArray()['columns']);
    }

    public function testUniqueKeyLockPreventsEdit(): void
    {
        $users = self::makeUsersTable();
        $uk    = new UniqueKey('uk_users_email', $users);
        $uk->addColumn('email');
        $uk->lock();

        $this->expectException(DBALException::class);
        $uk->addColumn('name');
    }

    // -------------------------------------------------------------------
    // ForeignKey
    // -------------------------------------------------------------------

    public function testForeignKeyBasics(): void
    {
        $users  = self::makeUsersTable();
        $posts  = self::makePostsTable();
        $fk     = new ForeignKey('fk_posts_user_id', $posts, $users);

        $fk->addColumn('user_id', 'id');

        self::assertSame($users, $fk->getReferenceTable());
        self::assertSame(['p_user_id'], $fk->getHostColumns());
        self::assertSame(['u_id'], $fk->getReferenceColumns());
        self::assertSame(['p_user_id' => 'u_id'], $fk->getColumnsMapping());
    }

    public function testForeignKeyDefaultActions(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);

        self::assertSame(ForeignKeyAction::NO_ACTION, $fk->getUpdateAction());
        self::assertSame(ForeignKeyAction::NO_ACTION, $fk->getDeleteAction());
    }

    public function testForeignKeyOnUpdateCascade(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);
        $fk->onUpdateCascade();

        self::assertSame(ForeignKeyAction::CASCADE, $fk->getUpdateAction());
    }

    public function testForeignKeyOnDeleteSetNull(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);
        $fk->onDeleteSetNull();

        self::assertSame(ForeignKeyAction::SET_NULL, $fk->getDeleteAction());
    }

    public function testForeignKeyOnUpdateRestrict(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);
        $fk->onUpdateRestrict();

        self::assertSame(ForeignKeyAction::RESTRICT, $fk->getUpdateAction());
    }

    public function testForeignKeyOnDeleteSetDefault(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);
        $fk->onDeleteSetDefault();

        self::assertSame(ForeignKeyAction::SET_DEFAULT, $fk->getDeleteAction());
    }

    public function testForeignKeyToArray(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);
        $fk->addColumn('user_id', 'id');
        $fk->onDeleteCascade();

        $arr = $fk->toArray();

        self::assertSame('foreign_key', $arr['type']);
        self::assertSame(['user_id' => 'id'], $arr['columns']);
        self::assertSame(ForeignKeyAction::CASCADE->value, $arr['delete']);
    }

    public function testForeignKeyAssertIsValidThrowsWhenNoColumns(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);

        $this->expectException(DBALException::class);
        $fk->assertIsValid();
    }

    public function testForeignKeyLockPreventsEdit(): void
    {
        $users = self::makeUsersTable();
        $posts = self::makePostsTable();
        $fk    = new ForeignKey('fk_posts_user_id', $posts, $users);
        $fk->addColumn('user_id', 'id');
        $fk->lock(); // users has PK on id, so assertIsValid passes

        $this->expectException(DBALException::class);
        $fk->addColumn('user_id', 'id');
    }
}
