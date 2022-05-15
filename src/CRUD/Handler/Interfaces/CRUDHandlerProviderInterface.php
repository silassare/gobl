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

namespace Gobl\CRUD\Handler\Interfaces;

use Gobl\DBAL\Table;

/**
 * Interface CRUDHandlerProviderInterface.
 */
interface CRUDHandlerProviderInterface
{
	/**
	 * Returns an instance of crud handler for a given table or null if none.
	 *
	 * @param \Gobl\DBAL\Table $table
	 *
	 * @return null|\Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface
	 */
	public function getCRUDHandler(Table $table): ?CRUDHandlerInterface;
}
