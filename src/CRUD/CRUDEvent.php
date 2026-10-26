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

namespace Gobl\CRUD;

use Gobl\DBAL\Table;
use PHPUtils\Events\Event;

/**
 * Class CRUDEvent.
 */
abstract class CRUDEvent extends Event
{
	/**
	 * CRUDEvent constructor.
	 */
	public function __construct(
		protected Table $table,
		protected array $form = []
	) {}

	/**
	 * Returns target table.
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * Gets form.
	 */
	public function getForm(): array
	{
		return $this->form;
	}

	/**
	 * Gets field value.
	 */
	public function getField(string $field): mixed
	{
		$field = $this->table->getColumnOrFail($field)
			->getFullName();

		return $this->form[$field] ?? null;
	}
}
