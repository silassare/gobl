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

/**
 * Enum ValidationStatus.
 *
 * State machine for the validation pipeline:
 *
 *  UNCHECKED -> (pre-validator) -> PRE_VALIDATED
 *            -> (main validation) -> VALIDATED
 *            -> (post-validator) -> POST_VALIDATED
 *
 * At any stage, the subject may be short-circuited to ACCEPTED (via accept()) or REJECTED (via reject()).
 * The post-validator always runs regardless of the state.
 */
enum ValidationStatus: string
{
	/** No validation has been attempted yet. */
	case UNCHECKED = 'unchecked';

	/** Pre-validator ran and advanced the subject without short-circuiting. */
	case PRE_VALIDATED = 'pre_validated';

	/** Main type validation ran and advanced the subject without short-circuiting. */
	case VALIDATED = 'validated';

	/** Post-validator ran and advanced the subject without short-circuiting. */
	case POST_VALIDATED = 'post_validated';

	/** Validation rejected the value -- the subject is terminal and invalid. */
	case REJECTED = 'rejected';

	/** Validation accepted the value -- the subject is terminal and valid. */
	case ACCEPTED = 'accepted';
}
