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

namespace Gobl\DBAL\Relations;

use Gobl\DBAL\Relations\Traits\FilterableRelationTrait;
use Gobl\DBAL\Relations\Traits\SimpleRelationTrait;
use Gobl\DBAL\Table;

/**
 * Class OneToMany.
 */
final class OneToMany extends Relation
{
	use FilterableRelationTrait;
	use SimpleRelationTrait;

	/**
	 * OneToMany constructor.
	 *
	 * @param string           $name
	 * @param \Gobl\DBAL\Table $host_table
	 * @param \Gobl\DBAL\Table $target_table
	 * @param null|array       $columns
	 * @param null|array       $filters
	 */
	public function __construct(
		string $name,
		Table $host_table,
		Table $target_table,
		?array $columns = null,
		?array $filters = null
	) {
		parent::__construct(RelationType::ONE_TO_MANY, $name, $host_table, $target_table, $columns);

		$this->filters = $filters;
	}
}
