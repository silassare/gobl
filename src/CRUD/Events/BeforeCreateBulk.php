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

namespace Gobl\CRUD\Events;

use Gobl\CRUD\CRUDAction;
use Gobl\CRUD\Enums\ActionType;
use Gobl\DBAL\Table;

/**
 * Class BeforeCreateBulk.
 *
 * Asked once before several rows the caller gives are created together, with all their forms: what
 * decides is the batch, before any row is. Each row is then asked for as a `BeforeCreate` of its
 * own, so a batch allowed still creates only the rows a create allows.
 */
final class BeforeCreateBulk extends CRUDAction
{
	/**
	 * BeforeCreateBulk constructor.
	 *
	 * @param list<array<string, mixed>> $forms the rows to create, one form each
	 */
	public function __construct(Table $table, private readonly array $forms)
	{
		parent::__construct(ActionType::CREATE_BULK, $table);
	}

	/**
	 * The rows to create, one form each, in the order given.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function getForms(): array
	{
		return $this->forms;
	}
}
