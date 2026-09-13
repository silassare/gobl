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

use BadMethodCallException;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\Utils\ORMClassKind;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\Db\Base\CurrenciesQueryBase;
use Gobl\Tests\Db\CurrenciesQuery;
use PHPUtils\Str;
use ReflectionClassConstant;
use Throwable;

/**
 * Generated query classes list their filter methods, which {@see ORMTableQuery::__call()}
 * serves without building the name of every filter of the table.
 *
 * @covers \Gobl\ORM\Generators\CSGeneratorORM
 * @covers \Gobl\ORM\ORMTableQuery
 *
 * @internal
 */
final class ORMTableQueryFilterMethodsTest extends BaseTestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		try {
			ORM::getDatabase(self::TEST_DB_NAMESPACE);
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
			// not declared yet - expected
		}

		if (!\is_dir(GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'Base')) {
			\mkdir(GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
		}

		$db = self::getNewDbInstance(MySQL::NAME);
		$db->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions())
			->enableORM(GOBL_TEST_ORM_OUTPUT);

		(new CSGeneratorORM($db))->generate($db->getTables(), GOBL_TEST_ORM_OUTPUT);

		$db->lock();
	}

	public static function tearDownAfterClass(): void
	{
		try {
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
			// already undeclared
		}

		parent::tearDownAfterClass();
	}

	public function testTheGeneratedListHasEveryFilterMethodOfTheTable(): void
	{
		$db = ORM::getDatabase(self::TEST_DB_NAMESPACE);

		foreach ($db->getTables(self::TEST_DB_NAMESPACE) as $table) {
			$expected = [];

			foreach ($table->getColumns() as $column) {
				foreach ($column->getType()->getAllowedFilterOperators() as $operator) {
					$method            = Str::toMethodName('where_' . $operator->getFilterSuffix($column));
					$expected[$method] = [$column->getName(), $operator->value];
				}
			}

			$class = self::TEST_DB_NAMESPACE . '\Base\\' . ORMClassKind::BASE_QUERY->getClassName($table);

			self::assertSame($expected, (new ReflectionClassConstant($class, 'FILTER_METHODS'))->getValue(), $class);
		}
	}

	public function testAListedMethodFiltersAsTheTableWideListDoes(): void
	{
		$calls = [
			'whereCodeIs'      => ['XOF'],
			'whereCodeIsIn'    => [['XOF', 'EUR']],
			'whereValidIsTrue' => [],
			'whereDataIs'      => ['x', 'a.b'],
		];

		foreach ($calls as $method => $args) {
			QBUtils::resetIdentifierCounter();
			$listed = CurrenciesQuery::new()->{$method}(...$args);

			// As a class generated before the list: __call() builds the table-wide one.
			QBUtils::resetIdentifierCounter();
			$built = (new class extends CurrenciesQueryBase {
				protected const FILTER_METHODS = [];
			})->{$method}(...$args);

			self::assertSame((string) $built->getFilters(), (string) $listed->getFilters(), $method);
			self::assertSame(
				$built->getBindingSource()->getBoundValues(),
				$listed->getBindingSource()->getBoundValues(),
				$method
			);
		}
	}

	public function testAnUnknownMethodIsStillRefused(): void
	{
		$this->expectException(BadMethodCallException::class);

		CurrenciesQuery::new()->whereNothingIs(1);
	}
}
