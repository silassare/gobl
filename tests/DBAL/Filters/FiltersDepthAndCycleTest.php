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
use Gobl\DBAL\Filters\FiltersMultiScope;
use Gobl\DBAL\Filters\FiltersTableScope;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Filters arrive in a request body, and both readers of them recurse.
 *
 * @internal
 */
#[CoversMethod(Filters::class, 'fromArray')]
#[CoversMethod(Filters::class, 'fromString')]
final class FiltersDepthAndCycleTest extends BaseTestCase
{
	public function testAnExpressionOfOpeningBracketsIsRefusedInsteadOfEndingTheProcess(): void
	{
		// What a request can send: a run of "(". Each one is a frame of a recursive-descent parser,
		// and it recurses before looking at whether the expression is even well formed -- so a
		// megabyte of them was a million frames and an uncatchable stack fatal, taking the worker
		// down mid-request instead of answering "refused filter".
		$qb = $this->makeQB();

		$deep = \str_repeat('(', Filters::MAX_GROUP_DEPTH + 5) . 'kind eq :k';

		try {
			Filters::fromString($deep, ['k' => 'x'], $qb, $this->scope($qb));

			self::fail('A filter expression nested past the limit must be refused.');
		} catch (DBALRuntimeException $e) {
			self::assertStringContainsString('nests groups more than', $e->getMessage());
		}
	}

	public function testAnExpressionWithinTheLimitStillParses(): void
	{
		$qb = $this->makeQB();

		$depth = 8;
		$ok    = \str_repeat('(', $depth) . 'kind eq :k' . \str_repeat(')', $depth);

		$filters = Filters::fromString($ok, ['k' => 'x'], $qb, $this->scope($qb));

		self::assertStringContainsString('kind', (string) $filters);
	}

	public function testANestedArrayOfGroupsIsRefusedPastTheLimit(): void
	{
		// The same door, as an array: one frame per level, and `json_decode()` allows 512.
		$qb = $this->makeQB();

		$deep = ['kind', 'eq', 'x'];

		for ($i = 0; $i < Filters::MAX_GROUP_DEPTH + 5; ++$i) {
			$deep = [$deep];
		}

		try {
			Filters::fromArray($deep, $qb, $this->scope($qb));

			self::fail('Filters nested past the limit must be refused.');
		} catch (DBALRuntimeException $e) {
			self::assertStringContainsString('nest groups more than', $e->getMessage());
		}
	}

	public function testTwoFilterGroupsThatHoldEachOtherAreRefusedWhenRendered(): void
	{
		// `Filters::where()` refuses only a direct self-reference, and a sub-group shares the query
		// builder and the scope, so this passes every check it has. Rendering it recursed.
		$qb = $this->makeQB();

		$a = $qb->filters($this->scope($qb));
		$b = $a->subGroup();

		$a->where($b);
		$b->where($a);

		try {
			$rendered = (string) $a;

			self::fail('A filter group holding itself must be refused, rendered: ' . $rendered);
		} catch (DBALRuntimeException $e) {
			self::assertStringContainsString('holds itself', $e->getMessage());
		}
	}

	public function testAMultiScopeCannotHoldItself(): void
	{
		$qb    = $this->makeQB();
		$table = $qb->getRDBMS()
			->getTableOrFail('terms');

		$multi = new FiltersMultiScope();

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('cannot hold itself');

		$multi->push($multi, $table);
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
