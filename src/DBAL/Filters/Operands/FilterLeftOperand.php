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

namespace Gobl\DBAL\Filters\Operands;

use Gobl\DBAL\Filters\FilterFieldNotation;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Override;

/**
 * Class FilterLeftOperand.
 */
final class FilterLeftOperand extends FilterOperand
{
	/**
	 * FilterLeftOperand constructor.
	 *
	 * @param FilterFieldNotation|string $user_defined The operand as provided by the user
	 */
	public function __construct(
		FilterFieldNotation|string $user_defined,
		QBInterface $qb,
		?FiltersScopeInterface $scope = null
	) {
		parent::__construct($user_defined, $qb, $scope);
	}

	/**
	 * Get user defined operand value.
	 *
	 * @return string
	 */
	#[Override]
	public function getValueAsDefined(): string
	{
		// we know this is a string because the constructor enforces it,
		// but we have to cast it for psalm
		return (string) $this->user_defined;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Left operands are always developer-supplied column references,
	 * so string auto-resolution as field notation is safe and desired.
	 */
	#[Override]
	protected function shouldResolveFieldNotation(): bool
	{
		return true;
	}
}
