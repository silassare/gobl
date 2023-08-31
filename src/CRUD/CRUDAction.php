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

use Gobl\CRUD\Enums\ActionType;
use Gobl\DBAL\Table;

/**
 * Class CRUDAction.
 */
class CRUDAction extends CRUDEvent
{
	protected ?string $success_message = null;
	protected ?string $error_message   = null;

	/**
	 * CRUDAction constructor.
	 *
	 * @param \Gobl\CRUD\Enums\ActionType $type
	 * @param \Gobl\DBAL\Table            $table
	 * @param array                       $form
	 */
	protected function __construct(
		protected ActionType $type,
		protected Table $table,
		protected array $form = []
	) {
		parent::__construct($table, $form);
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
	 * @param string $message
	 *
	 * @return $this
	 */
	public function setErrorMessage(string $message): static
	{
		$this->error_message = $message;

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
	 * @param string $message
	 *
	 * @return $this
	 */
	public function setSuccessMessage(string $message): static
	{
		$this->success_message = $message;

		return $this;
	}
}
