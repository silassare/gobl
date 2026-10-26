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

use Error;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\Fixtures\LegacyShapedEntity;
use PHPUtils\Store\Map;
use ReflectionClass;
use Throwable;
use TypeError;

/**
 * Class ORMEntityPropertyHooksTest.
 *
 * A generated column is a virtual property with `get` and `set` hooks, so its type is the engine's
 * business instead of an `@property` line's. These tests pin both what that buys and what it costs:
 * the type is enforced, and `isset()`, `??` and `unset()` answer differently than magic access did.
 *
 * Two tables, because the hazard lives in the difference: `hooked` carries a column prefix, so its
 * property names and its DB column names differ; `bare` carries none, so they are the same word, and
 * a row read from the database would reach the hooks if anything still wrote entity properties.
 *
 * @covers \Gobl\ORM\Generators\CSGeneratorORM
 * @covers \Gobl\ORM\ORMEntity
 *
 * @internal
 */
final class ORMEntityPropertyHooksTest extends BaseTestCase
{
	/** Separate namespace so this class does not conflict with other ORM test setups. */
	private const string HOOKS_NS = LegacyShapedEntity::TABLE_NAMESPACE;

	/** @var null|callable Autoloader registered for HOOKS_NS. */
	private static mixed $autoloader = null;

	private static bool $setupOk = false;

	// -------------------------------------------------------------------------
	// PHPUnit lifecycle
	// -------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		try {
			ORM::getDatabase(self::HOOKS_NS);
			ORM::undeclareNamespace(self::HOOKS_NS);
		} catch (Throwable) {
			// not declared yet - expected
		}

		$orm_out_dir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'Hooks';

		if (!\is_dir($orm_out_dir . \DIRECTORY_SEPARATOR . 'Base')) {
			\mkdir($orm_out_dir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
		}

		try {
			$db = self::getNewDbInstance(MySQL::NAME);
			$ns = $db->ns(self::HOOKS_NS);

			$ns->table('hooked', static function (TableBuilder $t): void {
				$t->columnPrefix('hk');
				$t->id();
				$t->string('label');
				$t->string('note')->nullable();
				$t->map('data');
				$t->bool('active')->default(true);
			});

			// No column prefix: every property name is also the DB column name, which is the case a
			// row coming back from the database could collide with.
			$ns->table('bare', static function (TableBuilder $t): void {
				$t->id();
				$t->string('label');
				$t->map('data');
			});

			$ns->enableORM($orm_out_dir);
			(new CSGeneratorORM($db))->generate($db->getTables(self::HOOKS_NS), $orm_out_dir);
			$db->lock();

			$prefix           = self::HOOKS_NS . '\\';
			self::$autoloader = static function (string $class) use ($orm_out_dir, $prefix): void {
				if (!\str_starts_with($class, $prefix)) {
					return;
				}
				$rel  = \str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix)));
				$file = $orm_out_dir . \DIRECTORY_SEPARATOR . $rel . '.php';
				if (\is_file($file)) {
					require_once $file;
				}
			};
			\spl_autoload_register(self::$autoloader);

			self::$setupOk = true;
		} catch (Throwable) {
			self::$setupOk = false;
		}
	}

	public static function tearDownAfterClass(): void
	{
		try {
			ORM::undeclareNamespace(self::HOOKS_NS);
		} catch (Throwable) {
			// already undeclared
		}

		if (null !== self::$autoloader) {
			\spl_autoload_unregister(self::$autoloader);
			self::$autoloader = null;
		}

		self::$setupOk = false;
		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (!self::$setupOk) {
			self::markTestSkipped('ORMEntityPropertyHooksTest setup failed.');
		}
	}

	// -------------------------------------------------------------------------
	// The property is there, and it is typed
	// -------------------------------------------------------------------------

	/**
	 * Every column is a declared property of the generated base class, and none of them is backed:
	 * the value lives in the entity's row, not in the property.
	 */
	public function testEveryColumnIsAVirtualTypedProperty(): void
	{
		$reflection = new ReflectionClass($this->makeHooked());

		foreach (['id', 'label', 'note', 'data', 'active'] as $name) {
			self::assertTrue($reflection->hasProperty($name), \sprintf('column "%s" must be a property', $name));

			$property = $reflection->getProperty($name);

			self::assertTrue($property->hasType(), \sprintf('property "%s" must be typed', $name));
			self::assertTrue($property->isVirtual(), \sprintf('property "%s" must be virtual', $name));
			self::assertTrue($property->hasHooks(), \sprintf('property "%s" must have hooks', $name));
		}
	}

	/**
	 * The declared type is the column's read type.
	 */
	public function testThePropertyTypeIsTheColumnReadType(): void
	{
		$reflection = new ReflectionClass($this->makeHooked());

		self::assertSame('?string', (string) $reflection->getProperty('id')->getType(), 'auto-increment reads nullable');
		self::assertSame('string', (string) $reflection->getProperty('label')->getType());
		self::assertSame('?string', (string) $reflection->getProperty('note')->getType());
		self::assertSame(Map::class, (string) $reflection->getProperty('data')->getType());
		self::assertSame('bool', (string) $reflection->getProperty('active')->getType());
	}

	/**
	 * A wrong type is refused by the engine at the assignment, which is the point of the change.
	 */
	public function testAWrongTypeIsATypeError(): void
	{
		$entity = $this->makeHooked();

		$this->expectException(TypeError::class);

		/* @phpstan-ignore-next-line */
		$entity->label = [];
	}

	/**
	 * Null is refused on a non-nullable column, by the engine rather than by validation.
	 */
	public function testNullOnANonNullableColumnIsATypeError(): void
	{
		$entity = $this->makeHooked();

		$this->expectException(TypeError::class);

		/* @phpstan-ignore-next-line */
		$entity->label = null;
	}

	/**
	 * Null is accepted on a nullable column.
	 */
	public function testNullOnANullableColumnIsAccepted(): void
	{
		$entity = $this->makeHooked();

		$entity->note = 'something';
		$entity->note = null;

		self::assertNull($entity->note, 'a nullable column accepts null through its hook');
	}

	/**
	 * The set hook takes the column's write type, which is wider than the read type for a map: an
	 * array goes in, a Map comes back.
	 */
	public function testTheSetHookTakesTheWiderWriteType(): void
	{
		$entity = $this->makeHooked();

		$entity->data = ['a' => 1];

		self::assertInstanceOf(Map::class, $entity->data, 'an array written to a map column reads back as a Map');
		self::assertSame(1, $entity->data->get('a'));
	}

	/**
	 * A JsonPatch is part of the write type too, so patching through the property still works.
	 */
	public function testTheSetHookTakesAJsonPatch(): void
	{
		$entity = $this->makeHooked();

		$entity->data = ['a' => 1];
		$entity->data = (new JsonPatch())->set('b', 2);

		self::assertSame(1, $entity->data->get('a'), 'the patch did not replace the whole value');
		self::assertSame(2, $entity->data->get('b'));
	}

	// -------------------------------------------------------------------------
	// The generated accessors are unchanged
	// -------------------------------------------------------------------------

	/**
	 * The generated getter and setter keep their signatures, and the setter stays fluent: a set hook
	 * returns void, so `$entity->col = $v` could never chain.
	 */
	public function testTheGeneratedAccessorsKeepTheirShape(): void
	{
		$entity = $this->makeHooked();

		$returned = $entity->setLabel('a label');

		self::assertSame($entity, $returned, 'the generated setter stays fluent');
		self::assertSame('a label', $entity->getLabel());

		$reflection = new ReflectionClass($entity);

		self::assertSame(
			'string',
			(string) $reflection->getMethod('getLabel')->getReturnType(),
			'the getter keeps its return type'
		);
		self::assertSame(
			'static',
			(string) $reflection->getMethod('setLabel')->getReturnType(),
			'the setter keeps its return type'
		);
	}

	/**
	 * The class docblock no longer carries an `@property` line for a column: the type is in the code.
	 */
	public function testTheClassDocblockHasNoPropertyLine(): void
	{
		$comment = (string) (new ReflectionClass($this->makeHooked()))->getParentClass()?->getDocComment();

		self::assertStringNotContainsString('@property', $comment, 'the generator writes no @property line any more');
	}

	// -------------------------------------------------------------------------
	// What it costs: isset(), ??, unset() and var_dump()
	// -------------------------------------------------------------------------

	/**
	 * `isset()` used to answer a schema question through `__isset()`. On a hooked property PHP runs
	 * the get hook and tests its result, so a nullable column holding null now answers false.
	 */
	public function testIssetOnANullableColumnHoldingNullIsFalse(): void
	{
		$entity = $this->makeHooked();

		self::assertFalse(isset($entity->note), 'isset() answers a value question now');
	}

	/**
	 * Which makes `??` reach its fallback where it used to answer null.
	 */
	public function testNullCoalesceOnANullableColumnReachesTheFallback(): void
	{
		$entity = $this->makeHooked();

		self::assertSame('fallback', $entity->note ?? 'fallback');
	}

	/**
	 * `isset()` is true for a column that holds a value.
	 */
	public function testIssetIsTrueForAColumnHoldingAValue(): void
	{
		$entity = $this->makeHooked();

		$entity->label = 'a label';

		self::assertTrue(isset($entity->label));
	}

	/**
	 * The partial-load guard still throws through the hook, and `isset()` cannot opt out of it: it
	 * runs the get hook and propagates the exception. Asserted so it is a decision on record.
	 */
	public function testIssetOnAPartialEntityThrows(): void
	{
		$entity = $this->makeHooked(false)
			->markAsPartial(['hk_id']);

		$this->expectException(ORMRuntimeException::class);

		isset($entity->label);
	}

	/**
	 * Reading an unloaded column of a partial entity still throws, through the hook.
	 */
	public function testReadingAnUnloadedColumnOfAPartialEntityThrows(): void
	{
		$entity = $this->makeHooked(false)
			->markAsPartial(['hk_id']);

		$this->expectException(ORMRuntimeException::class);

		/* @phpstan-ignore-next-line */
		$entity->label;
	}

	/**
	 * `unset()` was a silent no-op, since `ORMEntity` declares no `__unset`. A hooked property cannot
	 * be unset at all, which is louder and better.
	 */
	public function testUnsetAColumnIsAnError(): void
	{
		$entity = $this->makeHooked();

		$this->expectException(Error::class);
		$this->expectExceptionMessage('Cannot unset hooked property');

		unset($entity->label);
	}

	/**
	 * Column names now appear in `get_object_vars()`, where they used not to.
	 */
	public function testColumnNamesAppearInGetObjectVars(): void
	{
		$entity = $this->makeHooked();

		$entity->label = 'a label';
		$entity->data  = ['a' => 1];

		$vars = \get_object_vars($entity);

		self::assertArrayHasKey('label', $vars);
		self::assertArrayHasKey('data', $vars);
		self::assertSame('a label', $vars['label']);
	}

	// -------------------------------------------------------------------------
	// Magic access still answers for what has no property
	// -------------------------------------------------------------------------

	/**
	 * A column's full name is not a property, so it still reaches the magic getter and setter.
	 */
	public function testTheFullNameSpellingStillReachesTheMagicAccessors(): void
	{
		$entity = $this->makeHooked();

		$entity->hk_label = 'written by full name';

		self::assertSame('written by full name', $entity->hk_label, 'the full name reaches __get');
		self::assertSame('written by full name', $entity->label, 'and lands on the same column');
	}

	/**
	 * A non-nullable column whose stored value is null still resolves its default through the hook.
	 */
	public function testANonNullableColumnResolvesItsDefaultThroughTheHook(): void
	{
		$entity = $this->makeHooked();

		self::assertTrue($entity->active, 'the column default is resolved on read');
	}

	// -------------------------------------------------------------------------
	// Hydration does not go through the hooks
	// -------------------------------------------------------------------------

	/**
	 * A row the database answers with carries DB-form values. It is placed by `_oebHydrate()`, where
	 * `Type::dbToPhp()` runs, and not by a property write: a raw JSON string would not satisfy the
	 * set hook's type, and a raw `'1'` would be coerced by PHP instead of converted by the type.
	 */
	public function testHydrationConvertsDbValuesWithoutTouchingTheHooks(): void
	{
		$entity = $this->makeHooked(false);

		$entity->_oebHydrate([
			'hk_id'     => '7',
			'hk_label'  => 'from the row',
			'hk_note'   => null,
			'hk_data'   => '{"a":1}',
			'hk_active' => '1',
		]);

		self::assertSame('7', $entity->id, 'a bigint reads back in its PHP form');
		self::assertSame('from the row', $entity->label);
		self::assertNull($entity->note);
		self::assertInstanceOf(Map::class, $entity->data, 'the raw JSON string became a Map');
		self::assertSame(1, $entity->data->get('a'));
		self::assertTrue($entity->active, 'the raw "1" became a bool');
	}

	/**
	 * The same for a table with no column prefix, where a property name and its DB column name are
	 * the same word. This is the case that breaks if anything ever writes entity properties from a
	 * database row again.
	 */
	public function testHydrationOfATableWithoutAColumnPrefix(): void
	{
		$entity = $this->makeBare();

		$entity->_oebHydrate([
			'id'    => '3',
			'label' => 'bare row',
			'data'  => '{"k":"v"}',
		]);

		self::assertSame('3', $entity->id);
		self::assertSame('bare row', $entity->label);
		self::assertInstanceOf(Map::class, $entity->data, 'the raw JSON string became a Map');
		self::assertSame('v', $entity->data->get('k'));
	}

	/**
	 * A hydrated entity is clean: hydration snapshots the hashes, so nothing is dirty and the entity
	 * is not new.
	 */
	public function testAHydratedEntityIsNotDirty(): void
	{
		$entity = $this->makeHooked(false);

		$entity->_oebHydrate(['hk_id' => '1', 'hk_label' => 'x', 'hk_data' => '{}', 'hk_active' => '1']);

		self::assertTrue($entity->isSaved(), 'a hydrated entity has nothing to save');
	}

	/**
	 * A `_gobl_*` key is a computed SELECT alias: it goes to its slot, never to a column.
	 */
	public function testHydrationRoutesComputedAliases(): void
	{
		$entity = $this->makeHooked(false);

		$entity->_oebHydrate(['hk_id' => '1', '_gobl_batch_key' => '42']);

		self::assertTrue($entity->hasComputedValue('batch_key'));
		self::assertSame('42', $entity->getComputedValue('batch_key'));
	}

	/**
	 * Anything else that is not a column of the table is a programming error, loud in strict mode.
	 */
	public function testHydrationOfAnUnknownColumnThrowsInStrictMode(): void
	{
		$entity = $this->makeHooked(false);

		$this->expectException(ORMRuntimeException::class);

		$entity->_oebHydrate(['not_a_column' => 'x']);
	}

	// -------------------------------------------------------------------------
	// A class generated before the hooks
	// -------------------------------------------------------------------------

	/**
	 * A project that has not regenerated its classes yet runs on entities whose columns are
	 * `@property` lines and nothing else. They declare no property, so every read and write reaches
	 * the magic accessors, which reach the same code the hooks reach.
	 */
	public function testAClassGeneratedBeforeTheHooksStillWorks(): void
	{
		$entity = LegacyShapedEntity::new();

		$entity->label = 'written through __set';

		self::assertSame('written through __set', $entity->label, 'read through __get');
		self::assertTrue($entity->active, 'a column default is still resolved');
		self::assertTrue(isset($entity->note), 'isset() still answers the schema question here');
		self::assertNull($entity->note);

		$vars = \get_object_vars($entity);

		self::assertArrayNotHasKey('label', $vars, 'no declared property, so no key');
	}

	/**
	 * And a row from the database still reaches it, since hydration never wrote properties.
	 */
	public function testAClassGeneratedBeforeTheHooksIsStillHydrated(): void
	{
		$entity = LegacyShapedEntity::new(false);

		$entity->_oebHydrate(['hk_label' => 'from the row', 'hk_active' => '1']);

		self::assertSame('from the row', $entity->label);
		self::assertTrue($entity->active, 'the raw "1" became a bool');
		self::assertTrue($entity->isSaved());
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function makeHooked(bool $is_new = true): ORMEntity
	{
		return ORM::entity(ORM::getDatabase(self::HOOKS_NS)->getTableOrFail('hooked'), $is_new);
	}

	private function makeBare(bool $is_new = false): ORMEntity
	{
		return ORM::entity(ORM::getDatabase(self::HOOKS_NS)->getTableOrFail('bare'), $is_new);
	}
}
