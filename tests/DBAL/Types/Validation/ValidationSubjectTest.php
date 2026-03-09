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

namespace Gobl\Tests\DBAL\Types\Validation;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Validation\ValidationStatus;
use Gobl\DBAL\Types\Validation\ValidationSubject;
use Gobl\Tests\BaseTestCase;
use LogicException;

/**
 * Class ValidationSubjectTest.
 *
 * @covers \Gobl\DBAL\Types\Validation\ValidationSubject
 *
 * @internal
 */
final class ValidationSubjectTest extends BaseTestCase
{
	// =========================================================================
	// Initial state
	// =========================================================================

	public function testInitialStateIsUnchecked(): void
	{
		$s = new ValidationSubject('hello', 'col', 'ns.col');
		self::assertSame(ValidationStatus::UNCHECKED, $s->getStatus());
		self::assertFalse($s->isValid());
		self::assertFalse($s->isTerminal());
		self::assertSame('hello', $s->getUnsafeValue());
		self::assertSame('col', $s->getReference());
		self::assertSame('ns.col', $s->getReferenceDebug());
		self::assertNull($s->getRejectionException());
	}

	// =========================================================================
	// accept() => ACCEPTED
	// =========================================================================

	public function testAcceptSetsStatusAndCleanValue(): void
	{
		$s = new ValidationSubject('  hello  ');
		$s->accept('hello');
		self::assertSame(ValidationStatus::ACCEPTED, $s->getStatus());
		self::assertTrue($s->isValid());
		self::assertTrue($s->isTerminal());
		self::assertSame('hello', $s->getCleanValue());
	}

	public function testAcceptOverridesUnsafeValue(): void
	{
		$s = new ValidationSubject(42);
		$s->accept('42');
		self::assertSame('42', $s->getCleanValue());
	}

	// =========================================================================
	// reject() => REJECTED
	// =========================================================================

	public function testRejectWithStringCreatesException(): void
	{
		$s = new ValidationSubject('bad');
		$s->reject('invalid_value', ['key' => 'val']);
		self::assertSame(ValidationStatus::REJECTED, $s->getStatus());
		self::assertFalse($s->isValid());
		self::assertTrue($s->isTerminal());
		self::assertInstanceOf(TypesInvalidValueException::class, $s->getRejectionException());
	}

	public function testRejectWithThrowableStoresIt(): void
	{
		$s  = new ValidationSubject('bad');
		$ex = new TypesInvalidValueException('my_error');
		$s->reject($ex);
		self::assertSame($ex, $s->getRejectionException());
	}

	public function testGetCleanValueThrowsWhenRejected(): void
	{
		$s = new ValidationSubject('bad');
		$s->reject('error');
		$this->expectException(LogicException::class);
		$s->getCleanValue();
	}

	public function testGetCleanValueThrowsWhenUnchecked(): void
	{
		$s = new ValidationSubject('raw');
		$this->expectException(LogicException::class);
		$s->getCleanValue();
	}

	// =========================================================================
	// next() state progression
	// =========================================================================

	public function testNextAdvancesFromUncheckedToPreValidated(): void
	{
		$s = new ValidationSubject('x');
		$s->next('x1');
		self::assertSame(ValidationStatus::PRE_VALIDATED, $s->getStatus());
	}

	public function testNextAdvancesFromPreValidatedToValidated(): void
	{
		$s = new ValidationSubject('x');
		$s->next('x1');
		$s->next('x2');
		self::assertSame(ValidationStatus::VALIDATED, $s->getStatus());
	}

	public function testNextAdvancesFromValidatedToPostValidated(): void
	{
		$s = new ValidationSubject('x');
		$s->next('x1');
		$s->next('x2');
		$s->next('x3');
		self::assertSame(ValidationStatus::POST_VALIDATED, $s->getStatus());
	}

	public function testNextIsNoOpWhenTerminal(): void
	{
		$s = new ValidationSubject('x');
		$s->accept('clean');
		$s->next('overwrite');
		self::assertSame(ValidationStatus::ACCEPTED, $s->getStatus());
		self::assertSame('clean', $s->getCleanValue());
	}

	// =========================================================================
	// setUnsafeValue() - reset-on-change behaviour
	// =========================================================================

	public function testSetUnsafeValueResetsWhenValueChanges(): void
	{
		$s = new ValidationSubject('old');
		$s->accept('clean');
		self::assertTrue($s->isValid());

		$s->setUnsafeValue('new');
		self::assertSame(ValidationStatus::UNCHECKED, $s->getStatus());
		self::assertFalse($s->isValid());
	}

	public function testSetUnsafeValueNoOpWhenSameValue(): void
	{
		$s = new ValidationSubject('same');
		$s->accept('clean');
		$s->setUnsafeValue('same');
		// unchanged: still accepted
		self::assertTrue($s->isValid());
		self::assertSame('clean', $s->getCleanValue());
	}

	// =========================================================================
	// lock() / clone
	// =========================================================================

	public function testLockPreventsModification(): void
	{
		$s = new ValidationSubject('x');
		$s->accept('clean');
		$s->lock();

		$this->expectException(LogicException::class);
		$s->setUnsafeValue('other');
	}

	public function testLockThrowsWhenNotAccepted(): void
	{
		$s = new ValidationSubject('x');
		$this->expectException(LogicException::class);
		$s->lock();
	}

	public function testCloneProducesUnlockedCopy(): void
	{
		$s = new ValidationSubject('x');
		$s->accept('clean');
		$s->lock();

		$clone = clone $s;
		self::assertSame(ValidationStatus::ACCEPTED, $clone->getStatus());
		// clone is unlocked: can modify without throwing
		$clone->setUnsafeValue('other');
		self::assertSame(ValidationStatus::UNCHECKED, $clone->getStatus());
	}

	public function testLockThrowsWhenRejectAttemptAfterLock(): void
	{
		$s = new ValidationSubject('x');
		$s->accept('clean');
		$s->lock();

		$this->expectException(LogicException::class);
		$s->reject('too late');
	}

	public function testLockThrowsWhenAcceptAttemptAfterLock(): void
	{
		$s = new ValidationSubject('x');
		$s->accept('clean');
		$s->lock();

		$this->expectException(LogicException::class);
		$s->accept('another');
	}
}
