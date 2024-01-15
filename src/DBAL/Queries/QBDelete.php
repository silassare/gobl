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

namespace Gobl\DBAL\Queries;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\Traits\QBCommonTrait;
use Gobl\DBAL\Queries\Traits\QBFromTrait;
use Gobl\DBAL\Queries\Traits\QBJoinsTrait;
use Gobl\DBAL\Queries\Traits\QBLimitTrait;
use Gobl\DBAL\Queries\Traits\QBOrderByTrait;
use Gobl\DBAL\Queries\Traits\QBWhereTrait;

/**
 * Class QBDelete.
 */
class QBDelete implements QBInterface
{
	use QBCommonTrait;
	use QBFromTrait;
	use QBJoinsTrait;
	use QBLimitTrait;
	use QBOrderByTrait;
	use QBWhereTrait;

	/**
	 * QBDelete constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db)
	{
		$this->disable_multiple_from  = true;
		$this->disable_duplicate_from = true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): QBType
	{
		return QBType::DELETE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return int
	 */
	public function execute(): int
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->delete($sql, $values, $types);
	}
}
