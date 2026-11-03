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

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Search\Searchable;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Interfaces\TypeProviderInterface;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\Tests\BaseTestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Declaring a schema runs a project's own code: a table factory, a column type provider.
 *
 * @internal
 */
#[CoversClass(TableBuilder::class)]
#[CoversClass(NamespaceBuilder::class)]
final class BuilderCycleTest extends BaseTestCase
{
	public function testTwoTableFactoriesThatDeclareEachOtherWithoutEndAreRefused(): void
	{
		// Cross-table set-up inside a factory is supported, and `NamespaceBuilder::table()`
		// memoises the builder but runs the factory on every call -- outside that memo. So two
		// factories naming each other ran each other for ever, and the duplicate checks that would
		// have caught it only run once the nested call has returned.
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');

		$a = null;
		$b = null;

		$a = static function (TableBuilder $t) use ($ns, &$b): void {
			$t->columnPrefix('ta');
			$ns->table('tb', $b);
		};

		$b = static function (TableBuilder $t) use ($ns, &$a): void {
			$t->columnPrefix('tb');
			$ns->table('ta', $a);
		};

		try {
			$ns->table('ta', $a);

			self::fail('Two factories that declare each other without end must be refused.');
		} catch (DBALRuntimeException $e) {
			self::assertStringContainsString(
				\sprintf('nested more than %d deep', TableBuilder::MAX_FACTORY_DEPTH),
				$e->getMessage()
			);
		}
	}

	public function testATableFactoryMayDeclareBackTheTableThatDeclaredIt(): void
	{
		// The documented cross-table shape, and the reason this is a depth and not a refusal: the
		// table being declared names another, whose factory adds the other side of the relation to
		// the first. That is a second factory running on a table whose first factory has not
		// returned, it is different work, and it ends.
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');

		$ns->table('authors', static function (TableBuilder $t) use ($ns): void {
			$t->columnPrefix('ath');
			$t->id();
			$t->string('name');

			$ns->table('books', static function (TableBuilder $b) use ($ns): void {
				$b->columnPrefix('bok');
				$b->id();
				$b->string('title');
				$b->int('author_id');

				// Back to the table whose factory is still running, to add the other side.
				$ns->table('authors', static function (TableBuilder $a): void {
					$a->int('books_count')
						->nullable();
				});
			});
		});

		$db->lock();

		$authors = $db->getTable('authors');

		self::assertNotNull($authors);
		self::assertNotNull($db->getTable('books'));
		self::assertTrue($authors->hasColumn('books_count'));
		self::assertTrue($authors->hasColumn('name'));
	}

	public function testAFactoryMayStillDeclareAnotherTable(): void
	{
		// The guard is about a factory inside itself, not about declaring a second table: that is
		// the documented way to set up a relation.
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');

		$ns->table('authors', static function (TableBuilder $t) use ($ns): void {
			$t->columnPrefix('ath');
			$t->id();
			$t->string('name');

			$ns->table('books', static function (TableBuilder $b): void {
				$b->columnPrefix('bok');
				$b->id();
				$b->string('title');
			});
		});

		$db->lock();

		self::assertNotNull($db->getTable('authors'));
		self::assertNotNull($db->getTable('books'));
	}

	public function testATypeProviderThatReadsTheTableBeingBuiltIsRefused(): void
	{
		// With a lazy schema a table is built when it is first used, and building it constructs its
		// columns -- whose types come from a provider a project registers. A provider that asks the
		// same instance for the table it is defining used to find it still unbuilt (the "not done"
		// reading of the memo) and start the whole build again, provider calls included.
		$db = self::getNewDbInstance();

		$db->setLazySchema();

		$provider = new class($db) implements TypeProviderInterface {
			public function __construct(private readonly RDBMSInterface $db) {}

			#[Override]
			public function getTypeInstance(string $name, array $options): ?TypeInterface
			{
				if ('cycle_probe' !== $name) {
					return null;
				}

				// The table this column is in, asked for while it is being built.
				$this->db->getTable('probes');

				return new TypeString();
			}

			#[Override]
			public function hasType(string $name): bool
			{
				return 'cycle_probe' === $name;
			}
		};

		TypeUtils::addTypeProvider($provider);

		$db->ns('Probe\\Db')
			->schema([
				'probes' => [
					'column_prefix' => 'prb',
					'columns'       => [
						'id'    => ['type' => 'int', 'auto_increment' => true, 'primary_key' => true],
						'probe' => ['type' => 'cycle_probe'],
					],
				],
			]);

		try {
			$db->getTable('probes');

			self::fail('A provider reading the table being built must be refused.');
		} catch (DBALException $e) {
			// The column initialiser wraps whatever its type provider raised, so the reason is in
			// the chain rather than in the message.
			$reasons = [];

			for ($t = $e; null !== $t; $t = $t->getPrevious()) {
				$reasons[] = $t->getMessage();
			}

			self::assertStringContainsString(
				'while it was being built',
				\implode(' | ', $reasons),
				\implode(' | ', $reasons)
			);
		}
	}

	public function testASearchableValueThatNestsPastTheLimitIsRefused(): void
	{
		$deep = ['leaf' => 'text'];

		for ($i = 0; $i < Searchable::MAX_DOCUMENT_DEPTH + 5; ++$i) {
			$deep = ['down' => $deep];
		}

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('nests more than');

		Searchable::foldValue($deep);
	}
}
