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

namespace Gobl\Tests\DBAL\Relations;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\Tests\DBAL\Relations\Stubs\NamedVirtualRelation;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The name a virtual relation is declared with, and the two refusals it meets.
 *
 * Both are about the name, and both are met before the relation holds one: the reserved-name
 * refusal read the name back from the object and found a property that had not been set yet, so
 * what a developer got was an uninitialized-property error instead of the name they chose.
 *
 * @internal
 */
#[CoversClass(VirtualRelation::class)]
final class VirtualRelationNameTest extends BaseTestCase
{
	public function testANameTheOrmKeepsForItselfIsRefusedByThatName(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('Virtual relation name "save" is not allowed.');

		self::virtualRelationNamed('save');
	}

	public function testANameThatIsNotOneIsRefusedByThatName(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Virtual relation name "not a name"');

		self::virtualRelationNamed('not a name');
	}

	/**
	 * A virtual relation of this suite, which never gets past its constructor here.
	 */
	private static function virtualRelationNamed(string $name): VirtualRelation
	{
		return new NamedVirtualRelation(self::TEST_DB_NAMESPACE, 'users', $name, false);
	}
}
