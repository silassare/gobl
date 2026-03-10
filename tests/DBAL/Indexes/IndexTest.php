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

namespace Gobl\Tests\DBAL\Indexes;

use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Indexes\Index;
use Gobl\DBAL\Indexes\IndexType;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;

/**
 * Class IndexTest.
 *
 * @covers \Gobl\DBAL\Indexes\Index
 *
 * @internal
 */
final class IndexTest extends BaseTestCase
{
    private static function makeTable(): Table
    {
        $t = new Table('orders', 'o');
        $t->addColumn(new Column('id', 'o', ['type' => 'bigint', 'auto_increment' => true]));
        $t->addColumn(new Column('status', 'o'));
        $t->addColumn(new Column('created_at', 'o', ['type' => 'bigint']));

        return $t;
    }

    public function testBasicConstruction(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_status', $table);

        self::assertSame('idx_status', $index->getName());
        self::assertSame($table, $index->getHostTable());
        self::assertNull($index->getType());
        self::assertSame([], $index->getColumns());
    }

    public function testConstructionWithType(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_status', $table, IndexType::BTREE);

        self::assertSame(IndexType::BTREE, $index->getType());
    }

    public function testAddColumn(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_status', $table);
        $index->addColumn('status');

        self::assertSame(['o_status'], $index->getColumns());
    }

    public function testAddMultipleColumns(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_status_created', $table);
        $index->addColumn('status');
        $index->addColumn('created_at');

        self::assertSame(['o_status', 'o_created_at'], $index->getColumns());
    }

    public function testAddNonExistentColumnThrows(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_foo', $table);

        $this->expectException(DBALRuntimeException::class);
        $index->addColumn('nonexistent');
    }

    public function testToArray(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_status', $table, IndexType::HASH);
        $index->addColumn('status');

        $arr = $index->toArray();

        self::assertSame(['status'], $arr['columns']);
        self::assertSame(IndexType::HASH->value, $arr['type']);
    }

    public function testToArrayWithoutType(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_status', $table);
        $index->addColumn('status');

        $arr = $index->toArray();

        self::assertArrayNotHasKey('type', $arr);
        self::assertSame(['status'], $arr['columns']);
    }

    public function testLockPreventsAddColumn(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_status', $table);
        $index->addColumn('status');
        $index->lock();

        $this->expectException(DBALException::class);
        $index->addColumn('created_at');
    }

    public function testAssertIsValidThrowsWhenNoColumns(): void
    {
        $table = self::makeTable();
        $index = new Index('idx_empty', $table);

        $this->expectException(DBALException::class);
        $index->assertIsValid();
    }

    public function testInvalidNameThrows(): void
    {
        $table = self::makeTable();

        $this->expectException(InvalidArgumentException::class);
        new Index('_bad_name', $table);
    }

    public function testNameTooLongThrows(): void
    {
        $table    = self::makeTable();
        $longName = \str_repeat('a', Index::MAX_INDEX_NAME_LENGTH + 1);

        $this->expectException(InvalidArgumentException::class);
        new Index($longName, $table);
    }
}
