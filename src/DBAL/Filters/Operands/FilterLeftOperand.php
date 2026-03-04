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

namespace Gobl\DBAL\Filters\Operands;

use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;

/**
 * Class FilterLeftOperand.
 */
class FilterLeftOperand extends FilterOperand
{
	/**
	 * FilterLeftOperand constructor.
	 *
	 * @param string $user_defined The operand as provided by the user
	 */
	public function __construct(
		string $user_defined,
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
	public function getValueAsDefined(): string
	{
		// we know this is a string because the constructor enforces it,
		// but we have to cast it for psalm
		return (string) $this->user_defined;
	}

	/**
	 * Get detected column or value as defined (if any).
	 *
	 * @return string
	 */
	public function getDetectedColumnOrValueAsDefined(): string
	{
		/** @var string $ud */
		$ud = $this->user_defined;

		return $this->detected_table_and_column['column'] ?? $ud;
	}
}
