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

namespace Gobl\Tests\ORM;

use Gobl\DBAL\Column;
use Gobl\DBAL\Table;
use Gobl\ORM\Utils\ORMClassKind;
use Gobl\Tests\BaseTestCase;
use LogicException;
use PHPUtils\Str;

/**
 * Class ORMClassKindTest.
 *
 * @covers \Gobl\ORM\Utils\ORMClassKind
 *
 * @internal
 */
final class ORMClassKindTest extends BaseTestCase
{
    private static function makeTable(): Table
    {
        $t = new Table('clients', 'client');
        $t->addColumn(new Column('id', 'client', ['type' => 'bigint', 'auto_increment' => true]));

        return $t;
    }

    // -------------------------------------------------------------------
    // getClassName
    // -------------------------------------------------------------------

    public function testGetClassNameEntity(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getSingularName()),
            ORMClassKind::ENTITY->getClassName($table)
        );
    }

    public function testGetClassNameBaseEntity(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getSingularName() . '_base'),
            ORMClassKind::BASE_ENTITY->getClassName($table)
        );
    }

    public function testGetClassNameQuery(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_query'),
            ORMClassKind::QUERY->getClassName($table)
        );
    }

    public function testGetClassNameBaseQuery(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_query_base'),
            ORMClassKind::BASE_QUERY->getClassName($table)
        );
    }

    public function testGetClassNameController(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_controller'),
            ORMClassKind::CONTROLLER->getClassName($table)
        );
    }

    public function testGetClassNameBaseController(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_controller_base'),
            ORMClassKind::BASE_CONTROLLER->getClassName($table)
        );
    }

    public function testGetClassNameCrud(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_crud'),
            ORMClassKind::CRUD->getClassName($table)
        );
    }

    public function testGetClassNameBaseCrud(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_crud_base'),
            ORMClassKind::BASE_CRUD->getClassName($table)
        );
    }

    public function testGetClassNameResults(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_results'),
            ORMClassKind::RESULTS->getClassName($table)
        );
    }

    public function testGetClassNameBaseResults(): void
    {
        $table = self::makeTable();

        self::assertSame(
            Str::toClassName($table->getPluralName() . '_results_base'),
            ORMClassKind::BASE_RESULTS->getClassName($table)
        );
    }

    // -------------------------------------------------------------------
    // Non-base and base class names are always different
    // -------------------------------------------------------------------

    public function testBaseAndNonBaseClassNamesAreDifferent(): void
    {
        $table = self::makeTable();

        $pairs = [
            [ORMClassKind::ENTITY, ORMClassKind::BASE_ENTITY],
            [ORMClassKind::QUERY, ORMClassKind::BASE_QUERY],
            [ORMClassKind::CONTROLLER, ORMClassKind::BASE_CONTROLLER],
            [ORMClassKind::CRUD, ORMClassKind::BASE_CRUD],
            [ORMClassKind::RESULTS, ORMClassKind::BASE_RESULTS],
        ];

        foreach ($pairs as [$kind, $baseKind]) {
            self::assertNotSame(
                $kind->getClassName($table),
                $baseKind->getClassName($table),
                "Class names for {$kind->value} and {$baseKind->value} must differ"
            );
        }
    }

    // -------------------------------------------------------------------
    // isBaseClass
    // -------------------------------------------------------------------

    public function testIsBaseClassForBaseKinds(): void
    {
        foreach (
            [
                ORMClassKind::BASE_ENTITY,
                ORMClassKind::BASE_QUERY,
                ORMClassKind::BASE_CONTROLLER,
                ORMClassKind::BASE_CRUD,
                ORMClassKind::BASE_RESULTS,
            ] as $kind
        ) {
            self::assertTrue($kind->isBaseClass(), "{$kind->value} should be a base class");
        }
    }

    public function testIsBaseClassForNonBaseKinds(): void
    {
        foreach (
            [
                ORMClassKind::ENTITY,
                ORMClassKind::QUERY,
                ORMClassKind::CONTROLLER,
                ORMClassKind::CRUD,
                ORMClassKind::RESULTS,
            ] as $kind
        ) {
            self::assertFalse($kind->isBaseClass(), "{$kind->value} should not be a base class");
        }
    }

    // -------------------------------------------------------------------
    // getBaseKind
    // -------------------------------------------------------------------

    public function testGetBaseKindReturnsCorrectPair(): void
    {
        $table = self::makeTable();

        self::assertSame(ORMClassKind::BASE_ENTITY, ORMClassKind::ENTITY->getBaseKind());
        self::assertSame(ORMClassKind::BASE_QUERY, ORMClassKind::QUERY->getBaseKind());
        self::assertSame(ORMClassKind::BASE_CONTROLLER, ORMClassKind::CONTROLLER->getBaseKind());
        self::assertSame(ORMClassKind::BASE_CRUD, ORMClassKind::CRUD->getBaseKind());
        self::assertSame(ORMClassKind::BASE_RESULTS, ORMClassKind::RESULTS->getBaseKind());
    }

    public function testGetBaseKindOnBaseKindThrows(): void
    {
        $this->expectException(LogicException::class);
        ORMClassKind::BASE_ENTITY->getBaseKind();
    }

    // -------------------------------------------------------------------
    // getClassFQN
    // -------------------------------------------------------------------

    public function testGetClassFQNForEntity(): void
    {
        $table = self::makeTable();
        $table->setNamespace('App\\Db');

        $fqn = ORMClassKind::ENTITY->getClassFQN($table);

        self::assertStringStartsWith('\\App\\Db\\', $fqn);
        self::assertStringNotContainsString('Base\\', $fqn);
    }

    public function testGetClassFQNForBaseEntity(): void
    {
        $table = self::makeTable();
        $table->setNamespace('App\\Db');

        $fqn = ORMClassKind::BASE_ENTITY->getClassFQN($table);

        self::assertStringContainsString('\\Base\\', $fqn);
    }

    public function testGetClassFQNUseTrimLeadingSlash(): void
    {
        $table = self::makeTable();
        $table->setNamespace('App\\Db');

        $fqnWithSlash    = ORMClassKind::ENTITY->getClassFQN($table, false);
        $fqnWithoutSlash = ORMClassKind::ENTITY->getClassFQN($table, true);

        self::assertStringStartsWith('\\', $fqnWithSlash);
        self::assertStringStartsWith('App', $fqnWithoutSlash);
    }
}
