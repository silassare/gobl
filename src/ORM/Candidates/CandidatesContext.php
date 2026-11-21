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

namespace Gobl\ORM\Candidates;

use Gobl\DBAL\Relations\Relation;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMTableQuery;

/**
 * What a candidates provider decides with: the target's query to narrow, the host, and the caller's
 * context.
 *
 * The ORM generator writes a class extending this one for each relation that names a provider,
 * typing all three, and that class is what the provider receives ({@see CandidatesProviderInterface}).
 */
class CandidatesContext
{
	public function __construct(
		private readonly Relation $relation,
		private readonly ORMTableQuery $selector,
		private readonly ORMEntity $host,
		private readonly mixed $context = null,
	) {}

	/**
	 * The relation the candidates are for.
	 */
	public function relation(): Relation
	{
		return $this->relation;
	}

	/**
	 * The query of the relation's target, to narrow: what it still finds once the provider is done
	 * are the candidates.
	 */
	public function selector(): ORMTableQuery
	{
		return $this->selector;
	}

	/**
	 * The host: its stored row under what the form being filled in sent, or what the form sent for
	 * a row not saved yet. Not a stored row to save.
	 */
	public function host(): ORMEntity
	{
		return $this->host;
	}

	/**
	 * What the caller handed in, typed by the generated class as the generation was told.
	 */
	public function context(): mixed
	{
		return $this->context;
	}
}
