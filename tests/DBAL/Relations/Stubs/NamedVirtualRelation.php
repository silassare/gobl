<?php

/**
 * Copyright (c) 2018-present, Emile Silas Sare
 *
 * This file is part of Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\DBAL\Relations\Stubs;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Relations\Interfaces\RelationControllerInterface;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\ORM\ORMEntity;
use Override;

/**
 * A virtual relation that exists for its constructor: what a test declares to see the refusals of a
 * name. Nothing of it is reached, since a refused name never gets as far as a relative.
 *
 * @extends VirtualRelation<ORMEntity, array, array>
 *
 * @internal
 */
final class NamedVirtualRelation extends VirtualRelation
{
	#[Override]
	public function getRelativeType(): Table|TypeInterface
	{
		throw new DBALRuntimeException('Not reached.');
	}

	#[Override]
	public function isPrivate(): bool
	{
		return false;
	}

	#[Override]
	public function getController(): RelationControllerInterface
	{
		throw new DBALRuntimeException('Not reached.');
	}
}
