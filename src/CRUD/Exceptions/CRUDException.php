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

namespace Gobl\CRUD\Exceptions;

use Gobl\CRUD\CRUDAction;
use Gobl\Exceptions\GoblException;
use Throwable;

/**
 * Class CRUDException.
 */
class CRUDException extends GoblException
{
	/**
	 * CRUDException constructor.
	 *
	 * @param \Gobl\CRUD\CRUDAction|string $message
	 * @param null|array                   $data
	 * @param null|Throwable               $previous
	 * @param int                          $code
	 */
	public function __construct(string|CRUDAction $message, array $data = null, Throwable $previous = null, int $code = 0)
	{
		$action  = \is_string($message) ? null : $message;
		$message = $action ? $action->getErrorMessage() : $message;
		$suspect = $action?->getPropagationStopper();

		if ($suspect) {
			$this->suspectCallable($suspect);
		}

		parent::__construct($message, $data, $previous, $code);
	}
}
