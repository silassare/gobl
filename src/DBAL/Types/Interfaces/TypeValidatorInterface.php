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

use Gobl\DBAL\Types\Type;

/**
 * Interface TypeValidatorInterface.
 *
 * Implement this interface to attach pre- and/or post-validation hooks to any type.
 *
 * ### Execution order inside {@see Type::validate()}
 *
 *  1. `preValidate()` -- runs before the type's own `runValidation()`, skipped when the subject
 *     is already terminal (ACCEPTED or REJECTED).
 *  2. `runValidation()` -- the type's core logic, skipped when the subject is already terminal.
 *  3. `postValidate()` -- **always** runs, even when the subject was accepted or rejected earlier.
 *     Use it for auditing, logging, or post-processing of the clean value.
 *
 * ### Registration
 *
 * ```php
 * $type->preValidator(new MyPreValidator());
 * $type->postValidator(new MyPostValidator());
 * // or via a single class implementing both hooks:
 * $type->preValidator($combined)->postValidator($combined);
 * ```
 *
 * Via options (FQCN strings, for schema round-trip serialization):
 * ```php
 * $type->configure(['validator:pre' => MyPreValidator::class]);
 * ```
 */
interface TypeValidatorInterface
{
	/**
	 * Called before the type's core validation.
	 *
	 * Skipped when the subject is already terminal (ACCEPTED or REJECTED).
	 * May call `$subject->accept()`, `$subject->reject()`, or `$subject->next()` to influence
	 * what the core validation receives.
	 */
	public function preValidate(ValidationSubjectInterface $subject): void;

	/**
	 * Called after the type's core validation (always, even on rejection).
	 *
	 * Use it for logging, security auditing, or side-effects that must run regardless of outcome.
	 * May also transform or override the final clean value by calling `$subject->accept()` again.
	 */
	public function postValidate(ValidationSubjectInterface $subject): void;
}
