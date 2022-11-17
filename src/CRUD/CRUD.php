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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\CRUD\Exceptions\CRUDRuntimeException;
use Gobl\CRUD\Handler\CRUDHandlerDefault;
use Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface;
use Gobl\CRUD\Handler\Interfaces\CRUDHandlerProviderInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;

/**
 * Class CRUD.
 */
class CRUD
{
	private static ?CRUDHandlerProviderInterface $handler_provider;
	private Table                                $table;
	private CRUDHandlerInterface                 $crud_handler;
	private string                               $message = 'OK';
	private array                                $debug;

	/**
	 * CRUD constructor.
	 *
	 * @param \Gobl\DBAL\Table                                        $table   the target table
	 * @param null|\Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface $handler the default handler to use
	 */
	public function __construct(Table $table, ?CRUDHandlerInterface $handler = null)
	{
		$this->table = $table;

		if (null === $handler && isset(self::$handler_provider)) {
			$handler = self::$handler_provider->getCRUDHandler($table);
		}

		$this->crud_handler = $handler ?: new CRUDHandlerDefault();

		$this->debug = [
			'_by'    => \get_class($this->crud_handler),
			'_table' => $table->getName(),
		];
	}

	/**
	 * Gets the CRUD handler provider.
	 *
	 * @return null|CRUDHandlerProviderInterface
	 */
	public static function getHandlerProvider(): ?CRUDHandlerProviderInterface
	{
		return self::$handler_provider;
	}

	/**
	 * Sets the CRUD handler provider.
	 *
	 * @param CRUDHandlerProviderInterface $provider
	 */
	public static function setHandlerProvider(CRUDHandlerProviderInterface $provider): void
	{
		if (isset(self::$handler_provider)) {
			throw new CRUDRuntimeException(
				'CRUD handler provider cannot be overwritten. Current provider is: '
				. \get_class(self::$handler_provider)
			);
		}

		self::$handler_provider = $provider;
	}

	/**
	 * Gets latest message.
	 *
	 * @return string
	 */
	public function getMessage(): string
	{
		return $this->message;
	}

	/**
	 * Create assertion.
	 *
	 * @param array $form
	 *
	 * @return \Gobl\CRUD\CRUDCreate
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function assertCreate(array $form): CRUDCreate
	{
		$action = new CRUDCreate($this->table, $form);

		if (!$this->crud_handler->onBeforeCreate($action)) {
			throw new CRUDException($action->getErrorMessage(), $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		$this->checkFormColumnsForCreate($action);

		$this->crud_handler->autoFillCreateForm($action);

		return $action;
	}

	/**
	 * Read assertion.
	 *
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 *
	 * @return \Gobl\CRUD\CRUDRead
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function assertRead(ORMTableQuery $filters): CRUDRead
	{
		$action = new CRUDRead($this->table, $filters);

		if (!$this->crud_handler->onBeforeRead($action)) {
			throw new CRUDException($action->getErrorMessage(), $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return $action;
	}

	/**
	 * Read all assertion.
	 *
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 *
	 * @return \Gobl\CRUD\CRUDReadAll
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function assertReadAll(ORMTableQuery $filters): CRUDReadAll
	{
		$action = new CRUDReadAll($this->table, $filters);

		if (!$this->crud_handler->onBeforeReadAll($action)) {
			throw new CRUDException($action->getErrorMessage(), $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return $action;
	}

	/**
	 * Update assertion.
	 *
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 * @param array                   $form
	 *
	 * @return \Gobl\CRUD\CRUDUpdate
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function assertUpdate(ORMTableQuery $filters, array $form): CRUDUpdate
	{
		$action = new CRUDUpdate($this->table, $filters, $form);

		if (!$this->crud_handler->onBeforeUpdate($action)) {
			throw new CRUDException($action->getErrorMessage(), $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		$this->checkFormColumnsForUpdate($action);

		$this->crud_handler->autoFillUpdateFormAndFilters($action);

		return $action;
	}

	/**
	 * Update all assertion.
	 *
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 * @param array                   $form
	 *
	 * @return \Gobl\CRUD\CRUDUpdateAll
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function assertUpdateAll(ORMTableQuery $filters, array $form): CRUDUpdateAll
	{
		$action = new CRUDUpdateAll($this->table, $filters, $form);

		if (!$this->crud_handler->onBeforeUpdateAll($action)) {
			throw new CRUDException($action->getErrorMessage(), $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		$this->checkFormColumnsForUpdate($action);

		$this->crud_handler->autoFillUpdateFormAndFilters($action);

		return $action;
	}

	/**
	 * Delete assertion.
	 *
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 *
	 * @return \Gobl\CRUD\CRUDDelete
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function assertDelete(ORMTableQuery $filters): CRUDDelete
	{
		$action = new CRUDDelete($this->table, $filters);

		if (!$this->crud_handler->onBeforeDelete($action)) {
			throw new CRUDException($action->getErrorMessage(), $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return $action;
	}

	/**
	 * Delete all assertion.
	 *
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 *
	 * @return \Gobl\CRUD\CRUDDeleteAll
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function assertDeleteAll(ORMTableQuery $filters): CRUDDeleteAll
	{
		$action = new CRUDDeleteAll($this->table, $filters);

		if (!$this->crud_handler->onBeforeDeleteAll($action)) {
			throw new CRUDException($action->getErrorMessage(), $this->debug);
		}

		$this->message = $action->getSuccessMessage();

		return $action;
	}

	/**
	 * Gets the CRUD handler.
	 *
	 * @return \Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface
	 */
	public function getHandler(): CRUDHandlerInterface
	{
		return $this->crud_handler;
	}

	/**
	 * Checks columns values for create.
	 *
	 * @param \Gobl\CRUD\CRUDCreate $action
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	private function checkFormColumnsForCreate(CRUDCreate $action): void
	{
		$debug = $this->debug;
		$form  = $action->getForm();

		foreach ($form as $field => $value) {
			if ($this->table->hasColumn($field)) {
				$debug['column'] = $field;

				$column = $this->table->getColumnOrFail($field);

				if ($column->isPrivate() && !$this->crud_handler->shouldWritePrivateColumn($column, $value)) {
					$debug['_why'] = 'column_is_private';

					throw new CRUDException('GOBL_COLUMN_WRITE_REFUSED', $debug);
				}

				if (
					null !== $value
					&& $this->table->isPartOfPrimaryKey($column)
					&& !$this->crud_handler->shouldWritePkColumn($column, $value)
				) {
					$debug['_why'] = 'column_is_part_of_pk';

					throw new CRUDException('GOBL_COLUMN_WRITE_REFUSED', $debug);
				}
			}
		}
	}

	/**
	 * Checks columns values for update.
	 *
	 * @param \Gobl\CRUD\CRUDUpdate|\Gobl\CRUD\CRUDUpdateAll $action
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	private function checkFormColumnsForUpdate(CRUDUpdateAll|CRUDUpdate $action): void
	{
		$debug = $this->debug;
		$form  = $action->getForm();

		foreach ($form as $field => $value) {
			if ($this->table->hasColumn($field)) {
				$debug['column'] = $field;

				$column               = $this->table->getColumnOrFail($field);
				$column_update_action = new CRUDColumnUpdate($this->table, $column, $action->getForm());

				if ($column->isPrivate() && !$this->crud_handler->shouldWritePrivateColumn($column, $value)) {
					$debug['_why'] = 'column_is_private';

					throw new CRUDException('GOBL_COLUMN_WRITE_ERROR', $debug);
				}

				if ($this->table->isPartOfPrimaryKey($column) && !$this->crud_handler->shouldWritePkColumn($column, $value)) {
					$debug['_why'] = 'column_is_part_of_pk';

					throw new CRUDException('GOBL_COLUMN_WRITE_ERROR', $debug);
				}

				if (!$this->crud_handler->onBeforeColumnUpdate($column_update_action)) {
					$debug['_why'] = 'column_update_rejected';

					throw new CRUDException($action->getErrorMessage(), $debug);
				}

				$action->setForm($column_update_action->getForm());
			}
		}
	}
}
