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

namespace Gobl\DBAL\Diff\Actions;

use Gobl\DBAL\Constraints\Unique;
use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Diff\DiffActionType;

/**
 * Class UniqueConstraintAdded.
 */
final class UniqueConstraintDeleted extends DiffAction
{
	public function __construct(protected Unique $constraint, string $reason = '')
	{
		parent::__construct(DiffActionType::UNIQUE_CONSTRAINT_DELETED, $reason);
	}

	/**
	 * @return \Gobl\DBAL\Constraints\Unique
	 */
	public function getConstraint(): Unique
	{
		return $this->constraint;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'action'            => $this->type->value,
			'reason'            => $this->reason,
			'constraintName'    => $this->constraint->getName(),
			'constraintOptions' => $this->constraint->toArray(),
		];
	}
}
