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

namespace Gobl\Tests\Psalm;

use Gobl\DBAL\Types\TypeString;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMTableQuery;
use Override;

/**
 * A query class in the shape the generator emits: a subclass of a generic base, with methods
 * of its own that callers reach by chaining the base's fluent ones.
 *
 * @extends ORMTableQuery<ORMEntity>
 */
final class GuardQuery extends ORMTableQuery
{
	#[Override]
	public static function new(): static
	{
		// Nothing calls this: the names only have to be strings for the type-check.
		return new self('Gobl\Tests\Psalm', 'guarded');
	}

	/**
	 * Stands for a generated `whereXIs()` filter method.
	 */
	public function whereGuarded(): static
	{
		return $this;
	}
}

/**
 * Class FluentChainGuard.
 *
 * `Type` and `ORMTableQuery` are generic classes whose fluent methods return `static`, and both
 * are subclassed: the concrete column types, and the query class the generator writes per table.
 * psalm reads a generic class's native `static` as the class that *declares* the method unless
 * that method also says `@return static`, so dropping the tag makes every chain below an
 * undefined method -- in a project's code, not here, which is why nothing in `src/` catches it.
 * psalm reads this file (`psalm.xml`) so that it is caught here instead.
 *
 * Nothing calls these: they exist to be type-checked.
 */
final class FluentChainGuard
{
	/**
	 * A column type keeps its own methods reachable across the base's fluent ones.
	 */
	public function columnType(): TypeString
	{
		return (new TypeString())
			->nullable()
			->max(32)
			->default('x')
			->min(1)
			->configure([])
			->trim();
	}

	/**
	 * A generated query class keeps its filter methods reachable across the base's fluent ones.
	 */
	public function tableQuery(GuardQuery $query): GuardQuery
	{
		return $query->where('1 = 1')
			->whereGuarded()
			->and()
			->whereGuarded()
			->or()
			->whereGuarded()
			->reset()
			->whereGuarded();
	}
}
