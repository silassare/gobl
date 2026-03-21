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

namespace Gobl\DBAL\Types\Interfaces;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Validation\ValidationStatus;
use LogicException;
use PHPUtils\Lock\Interfaces\LockableInterface;
use Throwable;

/**
 * Interface ValidationSubjectInterface.
 *
 * A validation subject wraps the value traveling through the validation pipeline.
 * It tracks the current {@see ValidationStatus}, carries the safe clean value produced
 * by each stage, and records the rejection reason when validation fails.
 *
 * ### Typical usage
 *
 * ```php
 * $subject = $type->createValidationSubject($rawValue, 'email', 'users.email');
 * $type->applyValidation($subject);
 *
 * if ($subject->isValid()) {
 *     $clean = $subject->getCleanValue();
 * } else {
 *     throw $subject->getRejectionException();
 * }
 * ```
 *
 * ### ORM entity caching pattern
 *
 * Entities may cache a locked subject per column.  Cloning the locked subject
 * produces an **unlocked** copy in which `setUnsafeValue()` resets the state to
 * UNCHECKED when the value changes, enabling safe mutation detection without
 * re-running the full pipeline for an unchanged value.
 *
 * @template TUnsafe
 * @template TClean
 */
interface ValidationSubjectInterface extends LockableInterface
{
	/**
	 * Human-readable reference for error messages (e.g. the column short name).
	 */
	public function getReference(): string;

	/**
	 * Verbose debug reference (e.g. the column full name or FQCN).
	 */
	public function getReferenceDebug(): string;

	/**
	 * Returns the raw, unvalidated value.
	 *
	 * @return TUnsafe
	 */
	public function getUnsafeValue(): mixed;

	/**
	 * Replaces the unsafe value.
	 *
	 * When the new value differs from the current one the subject state is reset to
	 * {@see ValidationStatus::UNCHECKED} so the pipeline can run again on the new value.
	 * No-op when the new value is identical to the current one.
	 *
	 * @param TUnsafe $value
	 */
	public function setUnsafeValue(mixed $value): void;

	/**
	 * Marks this subject as rejected.
	 *
	 * When `$reason` is a `Throwable` it is stored directly; when it is a string a
	 * {@see TypesInvalidValueException} is created from it.
	 *
	 * @param string|Throwable $reason the rejection cause
	 * @param null|array       $debug  optional key-value context for the exception
	 */
	public function reject(string|Throwable $reason, ?array $debug = null): void;

	/**
	 * Advances the subject to the next state in the pipeline, carrying `$value` as the
	 * current working clean value.
	 *
	 * State progression: UNCHECKED -> PRE_VALIDATED -> VALIDATED -> POST_VALIDATED.
	 * Calling `next()` when the subject is already terminal (ACCEPTED or REJECTED) is a no-op.
	 *
	 * @param TClean $value the intermediate clean value at this stage
	 */
	public function next(mixed $value): void;

	/**
	 * Short-circuits the pipeline and marks the subject as fully accepted.
	 *
	 * @param null|TClean $value the final clean value
	 */
	public function accept(mixed $value): void;

	/**
	 * Returns the current validation status.
	 */
	public function getStatus(): ValidationStatus;

	/**
	 * Returns `true` when the subject has been accepted (status == ACCEPTED).
	 */
	public function isValid(): bool;

	/**
	 * Returns `true` when the subject is in a terminal state (ACCEPTED or REJECTED).
	 */
	public function isTerminal(): bool;

	/**
	 * Returns the exception that caused the rejection, or `null` if not rejected.
	 */
	public function getRejectionException(): ?Throwable;

	/**
	 * Returns the clean value produced by validation.
	 *
	 * @return null|TClean
	 *
	 * @throws LogicException when the subject is not yet accepted
	 */
	public function getCleanValue(): mixed;
}
