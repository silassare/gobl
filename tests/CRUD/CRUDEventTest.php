<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\CRUD;

use Gobl\CRUD\CRUDAction;
use Gobl\CRUD\Enums\ActionType;
use Gobl\CRUD\Events\BeforeCreate;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;

/**
 * Class CRUDEventTest.
 *
 * Tests the CRUD event hierarchy: event construction, data accessors,
 * and message customisation on CRUDAction subclasses.
 *
 * @covers \Gobl\CRUD\CRUDAction
 * @covers \Gobl\CRUD\CRUDEvent
 * @covers \Gobl\CRUD\Events\BeforeCreate
 *
 * @internal
 */
final class CRUDEventTest extends BaseTestCase
{
	// ------------------------------------------------------------------
	// BeforeCreate
	// ------------------------------------------------------------------

	public function testBeforeCreateGetTable(): void
	{
		$table = $this->getClientsTable();
		$event = new BeforeCreate($table, []);

		self::assertSame($table, $event->getTable());
	}

	public function testBeforeCreateGetForm(): void
	{
		$table = $this->getClientsTable();
		$form  = ['client_name' => 'Alice', 'client_email' => 'alice@example.com'];
		$event = new BeforeCreate($table, $form);

		self::assertSame($form, $event->getForm());
	}

	public function testBeforeCreateGetField(): void
	{
		$table = $this->getClientsTable();

		// Use the full column prefix name that the schema uses
		$full_name = $table->getColumnOrFail('first_name')->getFullName();
		$event     = new BeforeCreate($table, [$full_name => 'Alice']);

		self::assertSame('Alice', $event->getField('first_name'));
	}

	public function testBeforeCreateMissingFieldReturnsNull(): void
	{
		$table = $this->getClientsTable();
		$event = new BeforeCreate($table, []);

		self::assertNull($event->getField('first_name'));
	}

	// ------------------------------------------------------------------
	// ActionType covered by each event
	// ------------------------------------------------------------------

	public function testActionTypeValues(): void
	{
		// ActionType enum values are stable strings used as event channels
		self::assertSame('CREATE', ActionType::CREATE->name);
		self::assertSame('UPDATE', ActionType::UPDATE->name);
		self::assertSame('DELETE', ActionType::DELETE->name);
		self::assertSame('READ', ActionType::READ->name);
	}
	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/** Returns a real Table from the test schema. */
	private function getClientsTable(): Table
	{
		return self::getNewDbInstanceWithSchema()->getTableOrFail('clients');
	}
}
