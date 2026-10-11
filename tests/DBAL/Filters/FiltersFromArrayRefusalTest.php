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

namespace Gobl\Tests\DBAL\Filters;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\FiltersTableScope;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;

/**
 * Class FiltersFromArrayRefusalTest.
 *
 * A refused flat filter says which item was refused and why, in its message and in its public data.
 *
 * @covers \Gobl\DBAL\Filters\Filters::fromArray
 * @covers \Gobl\DBAL\Types\TypeBool::castValueForFilter
 *
 * @internal
 */
final class FiltersFromArrayRefusalTest extends BaseTestCase
{
	public function testMissingGlueBetweenTwoConditionsNamesItsPosition(): void
	{
		$e = $this->refusal([['kind', 'eq', 'format'], ['kind', 'eq', 'format']]);

		self::assertSame(
			'item at [1]: expected "and" or "or" between two conditions, found "array".',
			$e->getMessage()
		);
		self::assertSame(['position' => [1]], $e->getData());
	}

	public function testMissingGlueInsideAGroupNamesTheFullPosition(): void
	{
		$e = $this->refusal([['kind', 'eq', 'a', 'kind', 'eq', 'b']]);

		self::assertSame(
			'item at [0][3]: expected "and" or "or" between two conditions, found "kind".',
			$e->getMessage()
		);
		self::assertSame(['position' => [0, 3]], $e->getData());
	}

	public function testUnknownOperatorListsTheOnesTheColumnTakes(): void
	{
		$e = $this->refusal(['is_active', 'between', 1]);

		self::assertSame('condition at [0]: "between" is not an operator.', $e->getMessage());
		self::assertSame([
			'position'          => [0],
			'column'            => 'is_active',
			'operator'          => 'between',
			'allowed_operators' => ['eq', 'neq', 'is_false', 'is_true'],
		], $e->getData());
	}

	public function testRefusedValueSaysWhyAndListsTheOperators(): void
	{
		$e = $this->refusal(['kind', 'eq', 'a', 'and', 'is_active', 'eq', 'maybe']);

		self::assertStringStartsWith('condition at [4] ("is_active" eq) refused: ', $e->getMessage());
		self::assertSame([
			'position'          => [4],
			'column'            => 'is_active',
			'operator'          => 'eq',
			'allowed_operators' => ['eq', 'neq', 'is_false', 'is_true'],
		], $e->getData());
		self::assertNotNull($e->getPrevious());
	}

	public function testMissingValueIsNamed(): void
	{
		$e = $this->refusal(['kind', 'lt']);

		self::assertSame('condition at [0]: operator "lt" on "kind" needs a value.', $e->getMessage());
	}

	public function testTrailingGlueIsNamed(): void
	{
		$e = $this->refusal(['kind', 'eq', 'a', 'or']);

		self::assertSame('item at [3]: "or" must be followed by a condition.', $e->getMessage());
	}

	public function testUnknownGlueIsNamed(): void
	{
		$e = $this->refusal(['kind', 'eq', 'a', 'xor', 'kind', 'eq', 'b']);

		self::assertSame(
			'item at [3]: expected "and" or "or" between two conditions, found "xor".',
			$e->getMessage()
		);
	}

	/**
	 * A filter from a query string is a string: a boolean column takes the words a caller writes.
	 */
	public function testBooleanColumnTakesEqWithQueryStringWords(): void
	{
		foreach (['1' => 1, '0' => 0, 'true' => 1, 'false' => 0, 'TRUE' => 1, 'False' => 0] as $word => $db) {
			$qb      = $this->makeQB();
			$filters = Filters::fromArray(['is_active', 'eq', (string) $word], $qb, $this->scope($qb));

			self::assertStringContainsString('=', (string) $filters);
			self::assertSame([$db], \array_values($qb->getBoundValues()), 'word: ' . $word);
		}
	}

	/**
	 * Parses the filters, expecting a refusal, and returns it.
	 */
	private function refusal(array $filters): DBALRuntimeException
	{
		$qb = $this->makeQB();

		try {
			Filters::fromArray($filters, $qb, $this->scope($qb));
		} catch (DBALRuntimeException $e) {
			return $e;
		}

		self::fail('The filters were not refused.');
	}

	private function scope(QBSelect $qb): FiltersTableScope
	{
		return new FiltersTableScope($qb->getRDBMS()->getTableOrFail('terms'), 't');
	}

	private function makeQB(): QBSelect
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');
		$ns->table('terms', static function (TableBuilder $t): void {
			$t->columnPrefix('trm');
			$t->id();
			$t->string('kind');
			$t->bool('is_active');
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('terms', 't');

		return $qb;
	}
}
