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

use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Diff\DiffActionType;

/**
 * Class ForeignKeyConstraintDeleted.
 */
final class ForeignKeyConstraintDeleted extends DiffAction
{
	public function __construct(protected ForeignKey $constraint, string $reason = 'foreign key constraint deleted')
	{
		parent::__construct(DiffActionType::FOREIGN_KEY_CONSTRAINT_DELETED, $reason);
	}

	/**
	 * @return \Gobl\DBAL\Constraints\ForeignKey
	 */
	public function getConstraint(): ForeignKey
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
