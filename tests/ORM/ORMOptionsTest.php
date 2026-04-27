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

use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;

/**
 * Class ORMRequestTest.
 *
 * Pure unit tests for ORMOptions: no database required.
 * Tests cover payload parsing, pagination, filters, form data,
 * relations, order_by, collections, and scoped instances.
 *
 * @covers \Gobl\ORM\ORMOptions
 *
 * @internal
 */
final class ORMOptionsTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// Tests: defaults
	// -------------------------------------------------------------------------

	/**
	 * An empty payload produces sensible defaults.
	 */
	public function testEmptyPayloadDefaults(): void
	{
		$req = new ORMOptions();

		self::assertSame(1, $req->getPage(), 'Page starts at 1');
		self::assertNotNull($req->getMax(), 'Max must have a default value');
		self::assertGreaterThan(0, $req->getMax());
		self::assertSame([], $req->getFilters(), 'No filters by default');
		self::assertSame([], $req->getRequestedRelations(), 'No relations by default');
		self::assertSame([], $req->getOrderBy(), 'No order_by by default');
		self::assertNull($req->getRequestedCollection(), 'No collection by default');
		self::assertSame(0, $req->getOffset(), 'Offset is 0 on page 1');
	}

	// -------------------------------------------------------------------------
	// Tests: pagination
	// -------------------------------------------------------------------------

	/**
	 * Explicit max and page are parsed correctly and offset is calculated.
	 */
	public function testExplicitMaxAndPage(): void
	{
		$req = new ORMOptions([
			ORMOptions::MAX_PARAM  => 20,
			ORMOptions::PAGE_PARAM => 3,
		]);

		self::assertSame(20, $req->getMax());
		self::assertSame(3, $req->getPage());
		// offset = (page - 1) * max = (3 - 1) * 20 = 40
		self::assertSame(40, $req->getOffset());
	}

	/**
	 * Invalid (zero) max throws an ORMQueryException.
	 */
	public function testInvalidMaxThrows(): void
	{
		$this->expectException(ORMQueryException::class);

		new ORMOptions([ORMOptions::MAX_PARAM => 0]);
	}

	/**
	 * Max exceeding the allowed ceiling throws an ORMQueryException.
	 */
	public function testMaxExceedingAllowedThrows(): void
	{
		$this->expectException(ORMQueryException::class);

		// max_allowed defaults to 2000; passing 2001 must throw
		new ORMOptions([ORMOptions::MAX_PARAM => 2001]);
	}

	/**
	 * Invalid (zero) page throws an ORMQueryException.
	 */
	public function testInvalidPageThrows(): void
	{
		$this->expectException(ORMQueryException::class);

		new ORMOptions([ORMOptions::PAGE_PARAM => 0]);
	}

	/**
	 * setMax() / setPage() mutate state and getOffset() reflects the change.
	 */
	public function testSetMaxAndSetPage(): void
	{
		$req = new ORMOptions();

		$req->setMax(50)->setPage(2);

		self::assertSame(50, $req->getMax());
		self::assertSame(2, $req->getPage());
		self::assertSame(50, $req->getOffset()); // offset = (2 - 1) * 50 = 50
	}

	// -------------------------------------------------------------------------
	// Tests: form data
	// -------------------------------------------------------------------------

	/**
	 * When 'form_data' key is present it is used as the form data source.
	 */
	public function testFormDataFromDedicatedKey(): void
	{
		$req = new ORMOptions([
			ORMOptions::FORM_DATA_PARAM => [
				'email' => 'a@example.com',
				'name'  => 'Alice',
			],
			// These sibling keys should NOT leak into form_data
			ORMOptions::FILTERS_PARAM => ['id' => 1],
		]);

		self::assertSame('a@example.com', $req->getFormField('email'));
		self::assertSame('Alice', $req->getFormField('name'));
		// filters key must NOT appear as form field
		self::assertNull($req->getFormField(ORMOptions::FILTERS_PARAM));
	}

	/**
	 * Without a 'form_data' key, root-level non-keyword keys become form data.
	 */
	public function testFormDataFromRootLevel(): void
	{
		$req = new ORMOptions([
			'email'                  => 'b@example.com',
			ORMOptions::MAX_PARAM    => 10,  // keyword must be excluded
			ORMOptions::PAGE_PARAM   => 1,   // keyword must be excluded
		]);

		self::assertSame('b@example.com', $req->getFormField('email'));
		// keywords must not appear in form data
		self::assertNull($req->getFormField(ORMOptions::MAX_PARAM));
		self::assertNull($req->getFormField(ORMOptions::PAGE_PARAM));
	}

	/**
	 * setFormField() and removeFormField() mutate form data correctly.
	 */
	public function testSetAndRemoveFormField(): void
	{
		$req = new ORMOptions([ORMOptions::FORM_DATA_PARAM => ['a' => 1]]);

		$req->setFormField('b', 2);
		self::assertSame(2, $req->getFormField('b'));

		$req->removeFormField('b');
		self::assertNull($req->getFormField('b'));
	}

	/**
	 * getFormField() with a default returns the default when the field is absent.
	 */
	public function testFormFieldDefault(): void
	{
		$req = new ORMOptions([ORMOptions::FORM_DATA_PARAM => []]);

		self::assertSame('default_value', $req->getFormField('missing', 'default_value'));
	}

	// -------------------------------------------------------------------------
	// Tests: filters
	// -------------------------------------------------------------------------

	/**
	 * Filters are captured from the 'filters' key.
	 */
	public function testFiltersArePreserved(): void
	{
		$filters = ['user_id' => 42, 'active' => true];
		$req     = new ORMOptions([ORMOptions::FILTERS_PARAM => $filters]);

		self::assertSame($filters, $req->getFilters());
	}

	/**
	 * ensureOnlyFilters() prepends mandatory filters that are always merged.
	 */
	public function testEnsureOnlyFilters(): void
	{
		$req = new ORMOptions();
		$req->ensureOnlyFilters(['tenant_id', 'eq', 7]);

		// With no user filters, ensure filters are returned directly
		self::assertNotEmpty($req->getFilters());
	}

	/**
	 * ensureOnlyFilters() and user filters are merged with 'and' in getFilters().
	 */
	public function testEnsureOnlyFiltersWithUserFilters(): void
	{
		$req     = new ORMOptions([ORMOptions::FILTERS_PARAM => ['status' => 'active']]);
		$req->ensureOnlyFilters(['tenant_id', 'eq', 99]);

		$filters = $req->getFilters();

		// Must combine: [ensure_filters, 'and', user_filters]
		self::assertIsArray($filters);
		self::assertCount(3, $filters);
		self::assertSame('and', $filters[1]);
	}

	// -------------------------------------------------------------------------
	// Tests: relations
	// -------------------------------------------------------------------------

	/**
	 * Relations are decoded from a pipe-separated string.
	 */
	public function testRelationsFromString(): void
	{
		$req = new ORMOptions([
			ORMOptions::RELATIONS_PARAM => 'accounts|currency',
		]);

		self::assertSame(['accounts', 'currency'], $req->getRequestedRelations());
	}

	/**
	 * Relations are decoded from an array.
	 */
	public function testRelationsFromArray(): void
	{
		$req = new ORMOptions([
			ORMOptions::RELATIONS_PARAM => ['accounts', 'currency', 'accounts'], // duplicate
		]);

		// Duplicates must be removed
		self::assertCount(2, $req->getRequestedRelations());
		self::assertContains('accounts', $req->getRequestedRelations());
		self::assertContains('currency', $req->getRequestedRelations());
	}

	/**
	 * addRequestedRelation() appends a new relation and ignores duplicates.
	 */
	public function testAddRequestedRelation(): void
	{
		$req = new ORMOptions([ORMOptions::RELATIONS_PARAM => 'accounts']);
		$req->addRequestedRelation('currency');
		$req->addRequestedRelation('accounts'); // duplicate

		$relations = $req->getRequestedRelations();

		self::assertCount(2, $relations);
		self::assertContains('currency', $relations);
	}

	/**
	 * An invalid relation name causes an exception.
	 */
	public function testInvalidRelationNameThrows(): void
	{
		$this->expectException(ORMQueryException::class);

		new ORMOptions([ORMOptions::RELATIONS_PARAM => '!!invalid-name']);
	}

	// -------------------------------------------------------------------------
	// Tests: order_by
	// -------------------------------------------------------------------------

	/**
	 * order_by is parsed from a pipe-separated 'field:asc|field2:desc' string.
	 */
	public function testOrderByParsing(): void
	{
		$req = new ORMOptions([
			ORMOptions::ORDER_BY_PARAM => 'name|created_at:desc',
		]);

		$ob = $req->getOrderBy();

		self::assertArrayHasKey('name', $ob);
		self::assertArrayHasKey('created_at', $ob);
		self::assertSame('ASC', $ob['name'], '"name" implies ASC (true)');
		self::assertSame('DESC', $ob['created_at'], '"created_at:desc" implies DESC (false)');
	}

	/**
	 * An empty order_by string returns an empty array.
	 */
	public function testEmptyOrderByString(): void
	{
		$req = new ORMOptions([ORMOptions::ORDER_BY_PARAM => '']);

		self::assertSame([], $req->getOrderBy());
	}

	// -------------------------------------------------------------------------
	// Tests: collection
	// -------------------------------------------------------------------------

	/**
	 * A valid collection name is preserved.
	 */
	public function testCollectionParsing(): void
	{
		$req = new ORMOptions([ORMOptions::COLLECTION_PARAM => 'my_collection']);

		self::assertSame('my_collection', $req->getRequestedCollection());
	}

	/**
	 * An empty collection string resolves to null.
	 */
	public function testEmptyCollectionIsNull(): void
	{
		$req = new ORMOptions([ORMOptions::COLLECTION_PARAM => '']);

		self::assertNull($req->getRequestedCollection());
	}

	/**
	 * setRequestedCollection() updates the collection.
	 */
	public function testSetRequestedCollection(): void
	{
		$req = new ORMOptions();
		$req->setRequestedCollection('other_collection');

		self::assertSame('other_collection', $req->getRequestedCollection());
	}

	// -------------------------------------------------------------------------
	// Tests: scoped instance
	// -------------------------------------------------------------------------

	/**
	 * createScopedInstance() reads from the correct sub-payload.
	 */
	public function testScopedInstance(): void
	{
		$req = new ORMOptions([
			ORMOptions::SCOPES_PARAM => [
				'user_scope' => [
					ORMOptions::MAX_PARAM  => 5,
					ORMOptions::PAGE_PARAM => 2,
				],
			],
		]);

		$scoped = $req->createScopedInstance('user_scope');

		self::assertSame(5, $scoped->getMax());
		self::assertSame(2, $scoped->getPage());
	}

	/**
	 * A non-existent scope key produces defaults (empty sub-payload falls through to defaults).
	 */
	public function testMissingScopeUsesDefaults(): void
	{
		$req    = new ORMOptions(['something' => 'value']);
		$scoped = $req->createScopedInstance('nonexistent_scope');

		self::assertSame(1, $scoped->getPage());
		self::assertNotNull($scoped->getMax());
	}

	// -------------------------------------------------------------------------
	// Tests: getParsedRequest round-trip
	// -------------------------------------------------------------------------

	/**
	 * getParsedRequest() always includes the page key, and max when explicitly set.
	 */
	public function testGetParsedRequestIncludesPage(): void
	{
		$req    = new ORMOptions([ORMOptions::PAGE_PARAM => 4]);
		$parsed = $req->getParsedRequest();

		self::assertArrayHasKey(ORMOptions::PAGE_PARAM, $parsed);
		self::assertSame(4, $parsed[ORMOptions::PAGE_PARAM]);
	}

	/**
	 * A default (auto) max does NOT appear in getParsedRequest(); an explicit one does.
	 */
	public function testGetParsedRequestExplicitMax(): void
	{
		$req    = new ORMOptions([ORMOptions::MAX_PARAM => 15]);
		$parsed = $req->getParsedRequest();

		self::assertArrayHasKey(ORMOptions::MAX_PARAM, $parsed);
		self::assertSame(15, $parsed[ORMOptions::MAX_PARAM]);
	}

	/**
	 * Custom max_default and max_allowed are respected.
	 */
	public function testCustomMaxDefaultAndAllowed(): void
	{
		// max_default=25, max_allowed=100
		$req = new ORMOptions([], '', 25, 100);

		self::assertSame(25, $req->getMax(), 'Custom max_default must be respected');

		// Exceeding max_allowed must throw
		$this->expectException(ORMQueryException::class);
		new ORMOptions([ORMOptions::MAX_PARAM => 101], '', 25, 100);
	}
}
