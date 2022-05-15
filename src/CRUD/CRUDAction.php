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

namespace Gobl\CRUD;

use Gobl\DBAL\Table;

/**
 * Class CRUDAction.
 */
class CRUDAction
{
	protected ?string $success_message = null;
	protected ?string $error_message   = null;

	/**
	 * CRUDAction constructor.
	 *
	 * @param \Gobl\CRUD\CRUDActionType $type
	 * @param \Gobl\DBAL\Table          $table
	 * @param array                     $form
	 */
	protected function __construct(
		protected CRUDActionType $type,
		protected Table $table,
		protected array $form = []
	) {
	}

	/**
	 * Returns CRUD action type.
	 *
	 * @return \Gobl\CRUD\CRUDActionType
	 */
	public function getType(): CRUDActionType
	{
		return $this->type;
	}

	/**
	 * Returns target table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * Form getter.
	 *
	 * @return array
	 */
	public function getForm(): array
	{
		return $this->form;
	}

	/**
	 * Form setter.
	 *
	 * @param array $form
	 *
	 * @return $this
	 */
	public function setForm(array $form): static
	{
		$this->form = $form;

		return $this;
	}

	/**
	 * Sets field value.
	 *
	 * @param string $field
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function setField(string $field, mixed $value): static
	{
		$field              = $this->table->getColumnOrFail($field)
			->getFullName();
		$this->form[$field] = $value;

		return $this;
	}

	/**
	 * Gets field value.
	 *
	 * @param string $field
	 *
	 * @return mixed
	 */
	public function getField(string $field): mixed
	{
		$field = $this->table->getColumnOrFail($field)
			->getFullName();

		return $this->form[$field] ?? null;
	}

	/**
	 * Gets error message.
	 *
	 * @return string
	 */
	public function getErrorMessage(): string
	{
		return $this->error_message ?? $this->type->getDefaultErrorMessage();
	}

	/**
	 * Sets error message.
	 *
	 * @param string $error
	 *
	 * @return $this
	 */
	public function setErrorMessage(string $error): static
	{
		$this->error_message = $error;

		return $this;
	}

	/**
	 * Gets success message.
	 *
	 * @return string
	 */
	public function getSuccessMessage(): string
	{
		return $this->success_message ?? $this->type->getDefaultSuccessMessage();
	}

	/**
	 * Sets success message.
	 *
	 * @param string $success
	 *
	 * @return $this
	 */
	public function setSuccessMessage(string $success): static
	{
		$this->success_message = $success;

		return $this;
	}
}
