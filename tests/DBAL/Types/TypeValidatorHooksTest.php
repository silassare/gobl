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

namespace Gobl\Tests\DBAL\Types;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\TypeValidatorInterface;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Validation\ValidationStatus;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeValidatorHooksTest.
 *
 * Tests for the pre- and post-validator hook pipeline in {@see Type::validate()}.
 *
 * @covers \Gobl\DBAL\Types\Type::postValidator
 * @covers \Gobl\DBAL\Types\Type::preValidator
 * @covers \Gobl\DBAL\Types\Type::validate
 *
 * @internal
 */
final class TypeValidatorHooksTest extends BaseTestCase
{
	// =========================================================================
	// pre-validator
	// =========================================================================

	public function testPreValidatorRunsBeforeCoreValidation(): void
	{
		$called        = false;
		$statusOnEntry = null;

		$pre = new class($called, $statusOnEntry) implements TypeValidatorInterface {
			public function __construct(private bool &$called, private ?ValidationStatus &$statusOnEntry) {}

			public function preValidate(ValidationSubjectInterface $subject): void
			{
				$this->called        = true;
				$this->statusOnEntry = $subject->getStatus();
			}

			public function postValidate(ValidationSubjectInterface $subject): void {}
		};

		$t      = (new TypeString())->preValidator($pre);
		$result = $t->validate('hello')->getCleanValue();

		self::assertTrue($called);
		// pre-validator runs when the subject is still UNCHECKED (before core validation)
		self::assertSame(ValidationStatus::UNCHECKED, $statusOnEntry);
		// core TypeString validation produces the final result
		self::assertSame('hello', $result);
	}

	public function testPreValidatorCanAcceptAndSkipCoreValidation(): void
	{
		// When the pre-validator calls accept(), runValidation() is skipped.
		$coreRan = false;

		$pre = new class implements TypeValidatorInterface {
			public function preValidate(ValidationSubjectInterface $subject): void
			{
				$subject->accept('forced');
			}

			public function postValidate(ValidationSubjectInterface $subject): void {}
		};

		$t = (new TypeString())->preValidator($pre);
		// TypeString normally requires min-length >= 1, but the pre-validator short-circuits.
		$result = $t->validate('')->getCleanValue();

		self::assertSame('forced', $result);
	}

	public function testPreValidatorCanRejectAndSkipCoreValidation(): void
	{
		$pre = new class implements TypeValidatorInterface {
			public function preValidate(ValidationSubjectInterface $subject): void
			{
				$subject->reject('pre_blocked');
			}

			public function postValidate(ValidationSubjectInterface $subject): void {}
		};

		$t = (new TypeString())->preValidator($pre);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('hello')->getCleanValue();
	}

	public function testPreValidatorSkippedWhenAlreadyTerminal(): void
	{
		// When the subject is already accepted before validate() is called, the preValidator
		// should not run. This is enforced via the isTerminal() guard inside validate().
		// We simulate it with a pre-validator that would reject anything:
		$pre = new class implements TypeValidatorInterface {
			public function preValidate(ValidationSubjectInterface $subject): void
			{
				// If this runs, the test fails.
				$subject->reject('should not run');
			}

			public function postValidate(ValidationSubjectInterface $subject): void {}
		};

		$t       = (new TypeString())->preValidator($pre);
		$subject = $t->createValidationSubject('hello');
		$subject->accept('already-clean'); // terminal before validate()

		$t->applyValidation($subject);

		self::assertTrue($subject->isValid());
		self::assertSame('already-clean', $subject->getCleanValue());
	}

	// =========================================================================
	// post-validator
	// =========================================================================

	public function testPostValidatorAlwaysRunsOnAccepted(): void
	{
		$called = false;

		$post = new class($called) implements TypeValidatorInterface {
			public function __construct(private bool &$called) {}

			public function preValidate(ValidationSubjectInterface $subject): void {}

			public function postValidate(ValidationSubjectInterface $subject): void
			{
				$this->called = true;
			}
		};

		$t = (new TypeString())->postValidator($post);
		$t->validate('hello')->getCleanValue();
		self::assertTrue($called);
	}

	public function testPostValidatorAlwaysRunsOnRejected(): void
	{
		$postRan = false;

		$post = new class($postRan) implements TypeValidatorInterface {
			public function __construct(private bool &$postRan) {}

			public function preValidate(ValidationSubjectInterface $subject): void {}

			public function postValidate(ValidationSubjectInterface $subject): void
			{
				$this->postRan = true;
			}
		};

		$t = (new TypeString())->postValidator($post);

		try {
			$t->validate(null)->getCleanValue(); // null without nullable -> will be rejected by TypeString
		} catch (TypesInvalidValueException) {
			// expected
		}

		self::assertTrue($postRan);
	}

	public function testPostValidatorCanOverrideCleanValue(): void
	{
		$post = new class implements TypeValidatorInterface {
			public function preValidate(ValidationSubjectInterface $subject): void {}

			public function postValidate(ValidationSubjectInterface $subject): void
			{
				if ($subject->isValid()) {
					$subject->accept('post-override');
				}
			}
		};

		$t      = (new TypeString())->postValidator($post);
		$result = $t->validate('original')->getCleanValue();
		self::assertSame('post-override', $result);
	}

	// =========================================================================
	// Registration via FQCN string (options round-trip)
	// =========================================================================

	public function testPreValidatorRegisteredByFqcn(): void
	{
		$t = (new TypeString())->configure([
			'validator:pre' => SpyPreValidator::class,
		]);

		SpyPreValidator::reset();
		$t->validate('hello')->getCleanValue();
		self::assertTrue(SpyPreValidator::$ran);
	}

	public function testPostValidatorRegisteredByFqcn(): void
	{
		$t = (new TypeString())->configure([
			'validator:post' => SpyPostValidator::class,
		]);

		SpyPostValidator::reset();
		$t->validate('hello')->getCleanValue();
		self::assertTrue(SpyPostValidator::$ran);
	}

	// =========================================================================
	// Status progression through full pipeline
	// =========================================================================

	public function testFullPipelineStatusProgression(): void
	{
		$statuses = [];

		$pre = new class($statuses) implements TypeValidatorInterface {
			public function __construct(private array &$statuses) {}

			public function preValidate(ValidationSubjectInterface $subject): void
			{
				$this->statuses[] = 'pre:' . $subject->getStatus()->value;
				$subject->next($subject->getUnsafeValue());
			}

			public function postValidate(ValidationSubjectInterface $subject): void {}
		};

		$post = new class($statuses) implements TypeValidatorInterface {
			public function __construct(private array &$statuses) {}

			public function preValidate(ValidationSubjectInterface $subject): void {}

			public function postValidate(ValidationSubjectInterface $subject): void
			{
				$this->statuses[] = 'post:' . $subject->getStatus()->value;
			}
		};

		$t = (new TypeString())->preValidator($pre)->postValidator($post);
		$t->validate('test')->getCleanValue();

		self::assertSame('pre:' . ValidationStatus::UNCHECKED->value, $statuses[0]);
		// After runValidation, subject is ACCEPTED -> post sees accepted
		self::assertSame('post:' . ValidationStatus::ACCEPTED->value, $statuses[1]);
	}
}

// ---------------------------------------------------------------------------
// FQCN spy helpers (defined at file scope so the FQCN is stable)
// ---------------------------------------------------------------------------

final class SpyPreValidator implements TypeValidatorInterface
{
	public static bool $ran = false;

	public static function reset(): void
	{
		self::$ran = false;
	}

	public function preValidate(ValidationSubjectInterface $subject): void
	{
		self::$ran = true;
	}

	public function postValidate(ValidationSubjectInterface $subject): void {}
}

final class SpyPostValidator implements TypeValidatorInterface
{
	public static bool $ran = false;

	public static function reset(): void
	{
		self::$ran = false;
	}

	public function preValidate(ValidationSubjectInterface $subject): void {}

	public function postValidate(ValidationSubjectInterface $subject): void
	{
		self::$ran = true;
	}
}
