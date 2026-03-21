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

namespace Gobl\DBAL\Types\Validation;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use LogicException;
use Override;
use PHPUtils\FuncUtils;
use PHPUtils\Lock\Traits\PermanentlyLockableTrait;
use Throwable;

/**
 * Class ValidationSubject.
 *
 * Default implementation of {@see ValidationSubjectInterface}.
 *
 * @template TUnsafe
 * @template TClean
 *
 * @implements ValidationSubjectInterface<TUnsafe, TClean>
 */
class ValidationSubject implements ValidationSubjectInterface
{
	use PermanentlyLockableTrait {
		PermanentlyLockableTrait::lock as private traitLock;
	}

	private ValidationStatus $status = ValidationStatus::UNCHECKED;

	/** @var null|TClean */
	private mixed $cleanValue = null;

	private ?Throwable $rejectionException = null;

	/**
	 * ValidationSubject constructor.
	 *
	 * @param TUnsafe $unsafeValue    the raw (unvalidated) value
	 * @param string  $reference      short name used in error messages (e.g. column short name)
	 * @param string  $referenceDebug verbose name for debug context (e.g. column full name)
	 */
	public function __construct(
		private mixed $unsafeValue,
		private readonly string $reference = '',
		private readonly string $referenceDebug = '',
	) {}

	/**
	 * Cloning a subject always produces an unlocked copy.
	 *
	 * The clone preserves the current state and values so that ORM entities can compare
	 * a new raw value against the cached clean result without re-running the full pipeline
	 * when the value has not changed.
	 */
	public function __clone()
	{
		$this->lock_instance = $this->createLock();
	}

	#[Override]
	public function getReference(): string
	{
		return $this->reference;
	}

	#[Override]
	public function getReferenceDebug(): string
	{
		return $this->referenceDebug;
	}

	#[Override]
	public function getUnsafeValue(): mixed
	{
		return $this->unsafeValue;
	}

	#[Override]
	public function setUnsafeValue(mixed $value): void
	{
		$this->assertNotLocked();

		if ($this->unsafeValue !== $value) {
			$this->unsafeValue = $value;
			$this->reset();
		}
	}

	#[Override]
	public function reject(string|Throwable $reason, ?array $debug = null): void
	{
		$this->assertNotLocked();

		$this->status = ValidationStatus::REJECTED;

		if ($reason instanceof Throwable) {
			$this->rejectionException = $reason;
		} else {
			$reject_from             = FuncUtils::getCallerLocation();
			$debug                   = $debug ?? [];
			$debug['_rejected_from'] = $reject_from;

			$this->rejectionException = new TypesInvalidValueException($reason, $debug);
		}
	}

	#[Override]
	public function next(mixed $value): void
	{
		$this->assertNotLocked();

		// true no-op when already terminal: neither status nor clean value change
		if ($this->isTerminal()) {
			return;
		}

		$this->cleanValue = $value;

		$this->status = match ($this->status) {
			ValidationStatus::UNCHECKED     => ValidationStatus::PRE_VALIDATED,
			ValidationStatus::PRE_VALIDATED => ValidationStatus::VALIDATED,
			ValidationStatus::VALIDATED     => ValidationStatus::POST_VALIDATED,
			default                         => $this->status,
		};
	}

	#[Override]
	public function accept(mixed $value): void
	{
		$this->assertNotLocked();

		$this->cleanValue = $value;
		$this->status     = ValidationStatus::ACCEPTED;
	}

	#[Override]
	public function getStatus(): ValidationStatus
	{
		return $this->status;
	}

	#[Override]
	public function isValid(): bool
	{
		return ValidationStatus::ACCEPTED === $this->status;
	}

	#[Override]
	public function isTerminal(): bool
	{
		return ValidationStatus::ACCEPTED === $this->status || ValidationStatus::REJECTED === $this->status;
	}

	#[Override]
	public function getRejectionException(): ?Throwable
	{
		return $this->rejectionException;
	}

	#[Override]
	public function getCleanValue(): mixed
	{
		if (!$this->isValid()) {
			throw new LogicException(
				\sprintf(
					'Cannot get clean value from a validation subject in "%s" state.',
					$this->status->value
				)
			);
		}

		return $this->cleanValue;
	}

	#[Override]
	public function lock(): static
	{
		if (!$this->isValid()) {
			throw new LogicException('Only accepted validation subjects can be locked.');
		}

		$this->isLocked() || $this->traitLock();

		return $this;
	}

	/**
	 * Resets the subject to the initial UNCHECKED state.
	 */
	private function reset(): void
	{
		$this->status             = ValidationStatus::UNCHECKED;
		$this->cleanValue         = null;
		$this->rejectionException = null;
	}
}
