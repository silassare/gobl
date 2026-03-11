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
use Gobl\DBAL\Db;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\Gobl;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;

/**
 * Class SchemaFileTest.
 *
 * Tests for {@see NamespaceBuilder::schemaFile()}, {@see Db::toSchemaArray()},
 * {@see Db::toSchemaJson()}, and {@see Gobl::setDefaultSchemaUrl()}.
 *
 * @covers \Gobl\DBAL\Builders\NamespaceBuilder::schemaFile
 * @covers \Gobl\DBAL\Db::toSchemaArray
 * @covers \Gobl\DBAL\Db::toSchemaJson
 * @covers \Gobl\Gobl::getDefaultSchemaUrl
 * @covers \Gobl\Gobl::setDefaultSchemaUrl
 *
 * @internal
 */
final class SchemaFileTest extends BaseTestCase
{
	/** @var string[] */
	private array $temp_files = [];

	protected function tearDown(): void
	{
		parent::tearDown();

		foreach ($this->temp_files as $f) {
			if (\is_file($f)) {
				\unlink($f);
			}
		}
		$this->temp_files = [];

		// reset the global default schema URL after each test
		Gobl::setDefaultSchemaUrl(null);
	}

	// -------------------------------------------------------------------------
	// schemaFile() - happy paths
	// -------------------------------------------------------------------------

	public function testSchemaFileLoadsJson(): void
	{
		$schema = self::minimalSchema();
		$json   = \json_encode($schema, \JSON_PRETTY_PRINT);
		$path   = $this->writeTempFile($json, 'json');

		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);

		self::assertTrue($db->hasTable('items'));
	}

	public function testSchemaFileLoadsPhp(): void
	{
		$path = $this->writeTempFile(
			'<?php return ' . \var_export(self::minimalSchema(), true) . ';',
			'php'
		);

		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);

		self::assertTrue($db->hasTable('items'));
	}

	public function testSchemaFileStripsJsonSchemaKey(): void
	{
		$schema              = self::minimalSchema();
		$schema['$schema']   = 'https://example.com/schema.json';
		$json                = \json_encode($schema, \JSON_PRETTY_PRINT);
		$path                = $this->writeTempFile($json, 'json');

		$db = self::getNewDbInstance();
		// must not throw - $schema key should be silently stripped
		$db->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);

		self::assertTrue($db->hasTable('items'));
	}

	// -------------------------------------------------------------------------
	// schemaFile() - error paths
	// -------------------------------------------------------------------------

	public function testSchemaFileThrowsForMissingFile(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/not found/');

		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schemaFile('/nonexistent/path/schema.json');
	}

	public function testSchemaFileThrowsForUnsupportedExtension(): void
	{
		$path = $this->writeTempFile('{}', 'yaml');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/Unsupported schema file extension/');

		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);
	}

	public function testSchemaFileThrowsForInvalidJson(): void
	{
		$path = $this->writeTempFile('{ not valid json }', 'json');

		$this->expectException(DBALException::class);
		$this->expectExceptionMessageMatches('/Failed to parse JSON schema file/');

		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);
	}

	public function testSchemaFileThrowsWhenPhpDoesNotReturnArray(): void
	{
		$path = $this->writeTempFile('<?php return "not an array";', 'php');

		$this->expectException(DBALException::class);
		$this->expectExceptionMessageMatches('/must return an array/');

		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);
	}

	// -------------------------------------------------------------------------
	// toSchemaArray() round-trip
	// -------------------------------------------------------------------------

	public function testToSchemaArrayRoundTrip(): void
	{
		$db1 = self::getNewDbInstance();
		$db1->ns(self::TEST_DB_NAMESPACE)->schema(self::minimalSchema());

		$exported = $db1->toSchemaArray(self::TEST_DB_NAMESPACE);

		// the exported array must contain our table
		self::assertArrayHasKey('items', $exported);

		// import into a fresh db
		$db2 = self::getNewDbInstance();
		$db2->ns(self::TEST_DB_NAMESPACE)->schema($exported);

		self::assertTrue($db2->hasTable('items'));
		self::assertSame(
			$db1->getTable('items')->getFullName(),
			$db2->getTable('items')->getFullName()
		);
	}

	public function testToSchemaArrayFiltersByNamespace(): void
	{
		$db = self::getNewDbInstance();
		$db->ns('ns_a')->schema(self::minimalSchema());
		$db->ns('ns_b')->schema([
			'widgets' => [
				'singular_name' => 'widget',
				'plural_name'   => 'widgets',
				'column_prefix' => 'widget',
				'columns'       => [
					'id' => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
				],
				'constraints' => [
					['type' => 'primary_key', 'columns' => ['id']],
				],
			],
		]);

		$ns_a = $db->toSchemaArray('ns_a');
		$ns_b = $db->toSchemaArray('ns_b');

		self::assertArrayHasKey('items', $ns_a);
		self::assertArrayNotHasKey('widgets', $ns_a);

		self::assertArrayHasKey('widgets', $ns_b);
		self::assertArrayNotHasKey('items', $ns_b);
	}

	// -------------------------------------------------------------------------
	// toSchemaJson() + setDefaultSchemaUrl()
	// -------------------------------------------------------------------------

	public function testToSchemaJsonRoundTripViaFile(): void
	{
		$db1 = self::getNewDbInstance();
		$db1->ns(self::TEST_DB_NAMESPACE)->schema(self::minimalSchema());

		$json = $db1->toSchemaJson(self::TEST_DB_NAMESPACE);
		$path = $this->writeTempFile($json, 'json');

		$db2 = self::getNewDbInstance();
		$db2->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);

		self::assertTrue($db2->hasTable('items'));
	}

	public function testToSchemaJsonIncludesSchemaUrlWhenSet(): void
	{
		$url = 'https://example.com/gobl-schema.json';
		Gobl::setDefaultSchemaUrl($url);

		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schema(self::minimalSchema());

		$decoded = \json_decode($db->toSchemaJson(self::TEST_DB_NAMESPACE), true);

		self::assertArrayHasKey('$schema', $decoded);
		self::assertSame($url, $decoded['$schema']);
		// $schema must be the first key
		self::assertSame('$schema', \array_key_first($decoded));
	}

	public function testToSchemaJsonOmitsSchemaUrlWhenNotSet(): void
	{
		$db = self::getNewDbInstance();
		$db->ns(self::TEST_DB_NAMESPACE)->schema(self::minimalSchema());

		$decoded = \json_decode($db->toSchemaJson(self::TEST_DB_NAMESPACE), true);

		self::assertArrayNotHasKey('$schema', $decoded);
	}

	public function testToSchemaJsonWithSchemaUrlRoundTripsCleanly(): void
	{
		Gobl::setDefaultSchemaUrl('https://example.com/schema.json');

		$db1 = self::getNewDbInstance();
		$db1->ns(self::TEST_DB_NAMESPACE)->schema(self::minimalSchema());

		$json = $db1->toSchemaJson(self::TEST_DB_NAMESPACE);
		$path = $this->writeTempFile($json, 'json');

		// loading back must not choke on the $schema key
		$db2 = self::getNewDbInstance();
		$db2->ns(self::TEST_DB_NAMESPACE)->schemaFile($path);

		self::assertTrue($db2->hasTable('items'));
	}

	// -------------------------------------------------------------------------
	// Gobl::setDefaultSchemaUrl / getDefaultSchemaUrl
	// -------------------------------------------------------------------------

	public function testSetAndGetDefaultSchemaUrl(): void
	{
		self::assertNull(Gobl::getDefaultSchemaUrl());

		$url = 'https://gobl.example.com/schema.json';
		Gobl::setDefaultSchemaUrl($url);

		self::assertSame($url, Gobl::getDefaultSchemaUrl());
	}

	// -------------------------------------------------------------------------
	// helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a temporary file with the given content and registers it for cleanup.
	 */
	private function writeTempFile(string $content, string $extension): string
	{
		$path = \tempnam(\sys_get_temp_dir(), 'gobl_test_') . '.' . $extension;
		\file_put_contents($path, $content);
		$this->temp_files[] = $path;

		return $path;
	}

	/**
	 * Returns a minimal table schema array usable in tests.
	 *
	 * @return array<string, array>
	 */
	private static function minimalSchema(): array
	{
		return [
			'items' => [
				'singular_name' => 'item',
				'plural_name'   => 'items',
				'column_prefix' => 'item',
				'columns'       => [
					'id'    => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
					'label' => ['type' => 'string', 'min' => 1, 'max' => 255],
				],
				'constraints' => [
					['type' => 'primary_key', 'columns' => ['id']],
				],
			],
		];
	}
}
