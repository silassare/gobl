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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use PDO;

/**
 * Trait QBCommonTrait.
 */
trait QBCommonTrait
{
	use QBAliasTrait;
	use QBBindTrait;

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class];
	}

	/**
	 * Disable clone.
	 */
	private function __clone()
	{
	}

	/**
	 * Returns query string to be executed by the rdbms.
	 *
	 * @return string
	 */
	public function getSqlQuery(): string
	{
		return $this->db->getGenerator()
			->buildQuery($this);
	}

	/**
	 * Alias for {@see \PDO::quote()}.
	 *
	 * @param int   $type
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function quote(mixed $value, int $type = PDO::PARAM_STR): string
	{
		return $this->db->getConnection()
			->quote($value, $type);
	}

	/**
	 * Returns the RDBMS.
	 *
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
	 */
	public function getRDBMS(): RDBMSInterface
	{
		return $this->db;
	}
}
