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

namespace Gobl\CRUD\Traits;

/**
 * Trait HasFormEditable.
 */
trait HasFormEditable
{
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
}
