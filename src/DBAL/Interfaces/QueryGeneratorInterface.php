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

namespace Gobl\DBAL\Interfaces;

use Gobl\DBAL\Column;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Filters\FilterGroup;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBSelect;

/**
 * Interface QueryGeneratorInterface.
 */
interface QueryGeneratorInterface
{
	/**
	 * QueryGeneratorInterface constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 * @param \Gobl\DBAL\DbConfig                  $config
	 */
	public function __construct(RDBMSInterface $db, DbConfig $config);

	/**
	 * Converts query object into the rdbms query language.
	 *
	 * @param \Gobl\DBAL\Queries\Interfaces\QBInterface $qb
	 *
	 * @return string
	 */
	public function buildQuery(QBInterface $qb): string;

	/**
	 * Converts diff action object into the rdbms query language.
	 *
	 * @param \Gobl\DBAL\Diff\DiffAction $action
	 *
	 * @return string
	 */
	public function buildDiffActionQuery(DiffAction $action): string;

	/**
	 * Builds database query.
	 *
	 * When namespace is not empty,
	 * only tables with the given namespace should be generated.
	 *
	 * @param null|string $namespace the table namespace to generate
	 *
	 * @return string
	 */
	public function buildDatabase(?string $namespace = null): string;

	/**
	 * Gets total row count query with a given select query object.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return string
	 */
	public function buildTotalRowCountQuery(QBSelect $qb): string;

	/**
	 * Converts filter or a filter group to sql expression.
	 *
	 * @param \Gobl\DBAL\Filters\Filter|\Gobl\DBAL\Filters\FilterGroup $filter
	 *
	 * @return string
	 */
	public function toExpression(FilterGroup|Filter $filter): string;

	/**
	 * Checks if two given columns has the same type definition.
	 *
	 * @param \Gobl\DBAL\Column $a
	 * @param \Gobl\DBAL\Column $b
	 *
	 * @return bool
	 */
	public function hasSameColumnTypeDefinition(Column $a, Column $b): bool;
}
