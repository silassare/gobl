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

namespace Gobl\ORM\Interfaces;

use Gobl\DBAL\Table;

/**
 * Interface WithPayloadInterface.
 */
interface WithPayloadInterface
{
	/**
	 * Gets the form data.
	 *
	 * @param null|Table $table optional table to filter form data keys (when provided only keys matching table columns are returned)
	 *
	 * @return array
	 */
	public function getFormData(?Table $table = null): array;

	/**
	 * Sets the form data.
	 *
	 * @param array $form_data the form data to set, as an associative array of field names and their values
	 *
	 * @return static
	 */
	public function setFormData(array $form_data): static;

	/**
	 * Gets a specific form field value.
	 *
	 * @param string $name    the form field name
	 * @param mixed  $default the default value to return if the field is not set
	 *
	 * @return mixed the form field value, or the default value if the field is not set
	 */
	public function getFormField(string $name, $default = null): mixed;

	/**
	 * Sets a specific form field value.
	 *
	 * @param string $name  the form field name
	 * @param mixed  $value the value to set
	 *
	 * @return static
	 */
	public function setFormField(string $name, mixed $value): static;

	/**
	 * Unsets a specific form field value.
	 *
	 * @param string $name the form field name
	 *
	 * @return static
	 */
	public function unsetFormField(string $name): static;
}
