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
use PDOStatement;

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
	 * RETURNING options.
	 *
	 * @var array{enabled: bool, columns: string[]}
	 */
	protected array $options_returning = ['enabled' => false, 'columns' => ['*']];

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

	/**
	 * Marks this DELETE to return columns from the deleted rows (requires PostgreSQL or SQLite >= 3.35.0).
	 *
	 * MySQL does not support RETURNING; calling this method on a MySQL connection will throw at query-generation time.
	 *
	 * @param string|string[] $columns Column names, or `'*'` for all columns. Defaults to `['*']`.
	 *
	 * @return $this
	 */
	public function returning(array|string $columns = ['*']): static
	{
		$this->options_returning = [
			'enabled' => true,
			'columns' => (array) $columns,
		];

		return $this;
	}

	/**
	 * Gets the RETURNING options.
	 *
	 * @return array{enabled: bool, columns: string[]}
	 */
	public function getOptionsReturning(): array
	{
		return $this->options_returning;
	}

	/**
	 * Executes the query and returns a {@see PDOStatement} for iterating the deleted rows.
	 *
	 * Call this instead of {@see execute()} when you want to read back the affected rows via RETURNING.
	 *
	 * @return PDOStatement
	 */
	public function executeReturning(): PDOStatement
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->execute($sql, $values, $types);
	}
}
