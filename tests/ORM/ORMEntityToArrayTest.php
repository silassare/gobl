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

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMEntityToArrayTest.
 *
 * Tests for {@see ORMEntity::toArray()} covering full and partial entities,
 * private column exclusion, and sensitive column redaction.
 *
 * @covers \Gobl\ORM\ORMEntity::toArray
 *
 * @internal
 */
final class ORMEntityToArrayTest extends BaseTestCase
{
	/** Separate namespace so this class does not conflict with other ORM test setups. */
	private const TOARRAY_NS = 'Gobl\Tests\ToArrayDb';

	/** @var null|callable Autoloader registered for TOARRAY_NS. */
	private static mixed $autoloader = null;

	private static bool $setupOk = false;

	// -------------------------------------------------------------------------
	// PHPUnit lifecycle
	// -------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		try {
			ORM::getDatabase(self::TOARRAY_NS);
			ORM::undeclareNamespace(self::TOARRAY_NS);
		} catch (Throwable) {
			// not declared yet - expected
		}

		$ormOutDir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'ToArray';

		if (!\is_dir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base')) {
			\mkdir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
		}

		try {
			$db = self::getNewDbInstance(MySQL::NAME);
			$ns = $db->ns(self::TOARRAY_NS);

			// Table with a private column and a sensitive column.
			$ns->table('credentials', static function (TableBuilder $t) {
				$t->columnPrefix('cred');
				$t->id();
				$t->string('username');
				$t->string('password_hash');
				$t->string('api_token');

				// private: excluded from toArray() always
				$t->useColumn('api_token')->setPrivate();

				// sensitive: replaced by redacted value in toArray(true)
				$t->useColumn('password_hash')->setSensitive(true, '***');
			});

			$ns->enableORM($ormOutDir);
			(new CSGeneratorORM($db))->generate($db->getTables(), $ormOutDir);
			$db->lock();

			// Register a PSR-like autoloader for the TOARRAY_NS namespace.
			$prefix           = self::TOARRAY_NS . '\\';
			self::$autoloader = static function (string $class) use ($ormOutDir, $prefix): void {
				if (!\str_starts_with($class, $prefix)) {
					return;
				}
				$rel  = \str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix)));
				$file = $ormOutDir . \DIRECTORY_SEPARATOR . $rel . '.php';
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
			ORM::undeclareNamespace(self::TOARRAY_NS);
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
			self::markTestSkipped('ORMEntityToArrayTest setup failed.');
		}
	}

	// -------------------------------------------------------------------------
	// Full entity
	// -------------------------------------------------------------------------

	/**
	 * toArray(true) must exclude private columns and redact sensitive ones.
	 */
	public function testToArrayFullEntityExcludesPrivateAndRedactsSensitive(): void
	{
		$entity = $this->makeFullCredentialsEntity();

		$result = $entity->toArray(true);

		self::assertArrayHasKey('cred_id', $result, 'Regular column must be present');
		self::assertArrayHasKey('cred_username', $result, 'Regular column must be present');

		// Private column must be absent.
		self::assertArrayNotHasKey('cred_api_token', $result, 'Private column must be absent');

		// Sensitive column must be redacted to the configured value.
		self::assertArrayHasKey('cred_password_hash', $result, 'Sensitive column must be present (redacted)');
		self::assertSame('***', $result['cred_password_hash'], 'Sensitive column must carry the redacted value');
	}

	/**
	 * toArray(false) disables ALL filtering: sensitive columns carry their real value and
	 * private columns are also included (the caller opted in by setting the flag to false).
	 */
	public function testToArrayFullEntityShowsSensitiveColumnsWhenFlagFalse(): void
	{
		$entity = $this->makeFullCredentialsEntity();

		$result = $entity->toArray(false);

		// Sensitive column must carry the real value (not redacted).
		self::assertArrayHasKey('cred_password_hash', $result);
		self::assertSame('hashed_value', $result['cred_password_hash']);

		// When hide_sensitive_data=false, no filtering is applied at all:
		// private columns are also included in the raw output.
		self::assertArrayHasKey('cred_api_token', $result, 'With hide_sensitive_data=false, private columns are included');
	}

	// -------------------------------------------------------------------------
	// Partial entity
	// -------------------------------------------------------------------------

	/**
	 * toArray() on a partial entity that includes a private column in its projection
	 * must still exclude the private column from the output.
	 */
	public function testToArrayPartialEntityExcludesPrivateColumnsEvenIfInPartialSet(): void
	{
		$db     = ORM::getDatabase(self::TOARRAY_NS);
		$table  = $db->getTableOrFail('credentials');
		$entity = ORM::entity($table, false);

		$entity->cred_id       = 1;
		$entity->cred_username = 'alice';
		$entity->isSaved(true);

		// Include the private column in the partial projection -- it should still be
		// excluded from toArray() because the private-column filter runs first.
		$entity->markAsPartial([
			'cred_id',
			'cred_username',
			'cred_api_token', // private -- must be filtered out
		]);

		$result = $entity->toArray(true);

		self::assertArrayHasKey('cred_id', $result);
		self::assertArrayHasKey('cred_username', $result);
		self::assertArrayNotHasKey('cred_api_token', $result, 'Private column must still be excluded from partial toArray()');
	}

	/**
	 * toArray() on a partial entity that includes a sensitive column must still redact it.
	 */
	public function testToArrayPartialEntityRedactsSensitiveColumnsInPartialSet(): void
	{
		$db     = ORM::getDatabase(self::TOARRAY_NS);
		$table  = $db->getTableOrFail('credentials');
		$entity = ORM::entity($table, false);

		$entity->cred_id            = 2;
		$entity->cred_password_hash = 'real_hash';
		$entity->isSaved(true);

		$entity->markAsPartial([
			'cred_id',
			'cred_password_hash', // sensitive
		]);

		$result = $entity->toArray(true);

		self::assertArrayHasKey('cred_id', $result);
		self::assertArrayHasKey('cred_password_hash', $result, 'Sensitive column must appear (redacted) in partial result');
		self::assertSame('***', $result['cred_password_hash'], 'Sensitive column must be redacted in partial toArray()');
	}

	/**
	 * toArray(false) on a partial entity returns the real sensitive value for projected columns.
	 */
	public function testToArrayPartialEntityShowsRealSensitiveValueWhenFlagFalse(): void
	{
		$db     = ORM::getDatabase(self::TOARRAY_NS);
		$table  = $db->getTableOrFail('credentials');
		$entity = ORM::entity($table, false);

		$entity->cred_id            = 3;
		$entity->cred_password_hash = 'real_hash_2';
		$entity->isSaved(true);

		$entity->markAsPartial([
			'cred_id',
			'cred_password_hash',
		]);

		$result = $entity->toArray(false);

		self::assertSame('real_hash_2', $result['cred_password_hash']);
		self::assertArrayNotHasKey('cred_username', $result, 'Non-projected column must still be absent');
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function makeFullCredentialsEntity(): ORMEntity
	{
		$db     = ORM::getDatabase(self::TOARRAY_NS);
		$table  = $db->getTableOrFail('credentials');
		// is_new = true initialises all columns with defaults; then override specific values.
		$entity = ORM::entity($table, true);

		$entity->cred_id            = 1;
		$entity->cred_username      = 'alice';
		$entity->cred_password_hash = 'hashed_value';

		return $entity;
	}
}
