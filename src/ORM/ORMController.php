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

namespace Gobl\ORM;

use Gobl\CRUD\CRUD;
use Gobl\CRUD\CRUDEntityEvent;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Utils\ORMClassKind;

/**
 * Class ORMController.
 */
abstract class ORMController
{
	/**
	 * @var array
	 */
	protected array $form_fields = [];

	/**
	 * @var \Gobl\DBAL\Interfaces\RDBMSInterface
	 */
	protected RDBMSInterface $db;

	protected CRUD $crud;

	protected Table $table;

	/**
	 * The auto_increment column full name.
	 *
	 * @var null|string
	 */
	protected ?string $ai_column_full_name = null;

	/**
	 * ORMController constructor.
	 *
	 * @param string $namespace  the table namespace
	 * @param string $table_name the table name
	 */
	protected function __construct(
		string $namespace,
		string $table_name
	) {
		$this->db = ORM::getDatabase($namespace);

		$table   = $this->db->getTableOrFail($table_name);
		$columns = $table->getColumns();

		// we finds all required fields
		foreach ($columns as $column) {
			$full_name = $column->getFullName();
			$type      = $column->getType();
			$required  = !($type->isAutoIncremented() || $type->isNullable() || $type->hasDefault());

			$this->form_fields[$full_name] = $required;

			if ($type->isAutoIncremented()) {
				$this->ai_column_full_name = $full_name;
			}
		}

		$this->crud  = new CRUD($table);
		$this->table = $table;
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		unset($this->db, $this->crud);
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return ['instance_of' => static::class];
	}

	/**
	 * Returns CRUD.
	 *
	 * @return \Gobl\CRUD\CRUD
	 */
	public function getCRUD(): CRUD
	{
		return $this->crud;
	}

	/**
	 * Adds item to the table.
	 *
	 * @param array|\Gobl\ORM\ORMEntity $item
	 *
	 * @return \Gobl\ORM\ORMEntity
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function addItem(array|ORMEntity $item = []): ORMEntity
	{
		$instance = null;

		if (\is_array($item)) {
			$values = $item;
		} else {
			if ($item->isSaved()) {
				return $item;
			}

			$instance = $item;
			$values   = $item->toRow();
		}

		return $this->db->runInTransaction(function () use ($instance, $values): ORMEntity {
			$values = $this->crud->assertCreate($values)
				->getForm();

			$this->fillRequiredFields($values);

			if (!$instance) {
				/** @var \Gobl\ORM\ORMEntity $entity_class */
				$entity_class = ORMClassKind::ENTITY->getClassFQN($this->table);

				$instance = $entity_class::createInstance();

				$instance->hydrate($values);
			}

			$this->persistItem($instance);

			$this->crud->getHandler()
				->onEntityEvent($instance, CRUDEntityEvent::AFTER_CREATE);

			return $instance;
		});
	}

	/**
	 * Creates new instance.
	 *
	 * @return static
	 */
	abstract public static function createInstance(): static;

	/**
	 * Updates one item in the table.
	 *
	 * @param array $filters    the row filters
	 * @param array $new_values the new values
	 *
	 * @return null|\Gobl\ORM\ORMEntity
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function updateOneItem(array $filters, array $new_values): ?ORMEntity
	{
		return $this->db->runInTransaction(function () use ($filters, $new_values): ?ORMEntity {
			$tsf        = $this->getScopedFiltersInstance($filters);
			$action     = $this->crud->assertUpdate($tsf, $new_values);
			$new_values = $action->getForm();

			static::assertFiltersNotEmpty($tsf);
			static::assertUpdateColumns($this->table, \array_keys($new_values));

			$entity = $tsf->find(1)
				->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
					->onEntityEvent($entity, CRUDEntityEvent::BEFORE_UPDATE);

				$entity->hydrate($new_values);

				$this->persistItem($entity);

				$this->crud->getHandler()
					->onEntityEvent($entity, CRUDEntityEvent::AFTER_UPDATE);
			}

			return $entity ?: null;
		});
	}

	/**
	 * Asserts that the filters are not empty.
	 *
	 * @param \Gobl\ORM\ORMTableQuery $filters the row filters
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public static function assertFiltersNotEmpty(ORMTableQuery $filters): void
	{
		if ($filters->getFilters()
			->isEmpty()) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_FILTERS_EMPTY');
		}
	}

	/**
	 * Asserts that there is at least one column to update and
	 * the column(s) to update really exists in the table.
	 *
	 * @param Table $table   The table
	 * @param array $columns The columns to update
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public static function assertUpdateColumns(Table $table, array $columns = []): void
	{
		if (empty($columns)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_NO_FIELDS_TO_UPDATE');
		}

		foreach ($columns as $column) {
			if (!$table->hasColumn($column)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_UNKNOWN_FIELDS', [$column]);
			}
		}
	}

	/**
	 * Updates all items in the table that match the given item filters.
	 *
	 * @param array $filters    the row filters
	 * @param array $new_values the new values
	 *
	 * @return int affected row count
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function updateAllItems(array $filters, array $new_values): int
	{
		return $this->db->runInTransaction(function () use ($filters, $new_values): int {
			$tsf        = $this->getScopedFiltersInstance($filters);
			$action     = $this->crud->assertUpdateAll($tsf, $new_values);
			$new_values = $action->getForm();

			static::assertFiltersNotEmpty($tsf);
			static::assertUpdateColumns($this->table, \array_keys($new_values));

			return $tsf->update($new_values)
				->execute();
		});
	}

	/**
	 * Deletes one item from the table.
	 *
	 * @param array $filters the row filters
	 *
	 * @return null|\Gobl\ORM\ORMEntity
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function deleteOneItem(array $filters): ?ORMEntity
	{
		return $this->db->runInTransaction(function () use ($filters): ?ORMEntity {
			$tsf = $this->getScopedFiltersInstance($filters);

			$this->crud->assertDelete($tsf);

			static::assertFiltersNotEmpty($tsf);

			$entity = $tsf->find(1)
				->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
					->onEntityEvent($entity, CRUDEntityEvent::BEFORE_DELETE);

				// we need to make sure that we delete the selected entity and only that
				$tsf = $this->getScopedFiltersInstance($entity->toIdentityFilters());

				$tsf->delete()
					->limit(1)
					->execute();

				$this->crud->getHandler()
					->onEntityEvent($entity, CRUDEntityEvent::AFTER_DELETE);

				return $entity;
			}

			return null;
		});
	}

	/**
	 * Deletes all items in the table that match the given item filters.
	 *
	 * @param array $filters the row filters
	 *
	 * @return int affected row count
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function deleteAllItems(array $filters): int
	{
		return $this->db->runInTransaction(function () use ($filters): int {
			$tsf = $this->getScopedFiltersInstance($filters);

			$this->crud->assertDeleteAll($tsf);

			static::assertFiltersNotEmpty($tsf);

			return $tsf->delete()
				->execute();
		});
	}

	/**
	 * Gets item from the table that match the given filters.
	 *
	 * @param array $filters  the row filters
	 * @param array $order_by order by rules
	 *
	 * @return null|\Gobl\ORM\ORMEntity
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getItem(array $filters, array $order_by = []): ?ORMEntity
	{
		// we use transaction for reading too
		// https://stackoverflow.com/questions/308905/should-there-be-a-transaction-for-read-queries
		return $this->db->runInTransaction(function () use ($filters, $order_by): ?ORMEntity {
			$tsf = $this->getScopedFiltersInstance($filters);

			$this->crud->assertRead($tsf);

			static::assertFiltersNotEmpty($tsf);

			$entity = $tsf->find(1, 0, $order_by)
				->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
					->onEntityEvent($entity, CRUDEntityEvent::AFTER_READ);
			}

			return $entity;
		});
	}

	/**
	 * Gets all items from the table that match the given filters.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param null|int &$total   total rows without limit
	 *
	 * @return \Gobl\ORM\ORMEntity[]
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getAllItems(
		array $filters = [],
		?int $max = null,
		int $offset = 0,
		array $order_by = [],
		?int &$total = null
	): array {
		return $this->db->runInTransaction(function () use ($filters, $max, $offset, $order_by, &$total): array {
			$tsf = $this->getScopedFiltersInstance($filters);

			$this->crud->assertReadAll($tsf);

			$results = $tsf->find($max, $offset, $order_by);

			$items = $results->fetchAllClass();

			$total = static::lazyTotalResultsCount($results, \count($items), $max, $offset);

			return $items;
		});
	}

	/**
	 * Lazily count total row that match a select query according to the current results and current pagination info.
	 *
	 * @param \Gobl\ORM\ORMResults $results
	 * @param int                  $found
	 * @param null|int             $max
	 * @param int                  $offset
	 *
	 * @return int
	 */
	public static function lazyTotalResultsCount(
		ORMResults $results,
		int $found = 0,
		?int $max = null,
		int $offset = 0
	): int {
		if (isset($max)) {
			if ($found < $max) {
				$total = $offset + $found;
			} else {
				$total = $results->totalCount();
			}
		} elseif (0 === $offset) {
			$total = $found;
		} else {
			$total = $results->totalCount();
		}

		return $total;
	}

	/**
	 * Gets all items from the table with a custom query builder instance.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb     the custom select query instance
	 * @param null|int                    $max    maximum row to retrieve
	 * @param int                         $offset first row offset
	 * @param null|int                    $total  total rows without limit
	 *
	 * @return \Gobl\ORM\ORMEntity[]
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getAllItemsCustom(QBSelect $qb, ?int $max = null, int $offset = 0, ?int &$total = null): array
	{
		return $this->db->runInTransaction(function () use ($qb, $max, $offset, &$total): array {
			$this->crud->assertReadAll($this->getScopedFiltersInstance([]));

			$qb->limit($max, $offset);

			$results = $this->getResultsInstance($qb);

			$items = $results->fetchAllClass(false);

			$total = static::lazyTotalResultsCount($results, \count($items), $max, $offset);

			return $items;
		});
	}

	/**
	 * Gets a given item relative.
	 *
	 * @param \Gobl\ORM\ORMEntity           $entity
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param array                         $filters
	 * @param array                         $order_by
	 *
	 * @return null|\Gobl\ORM\ORMEntity
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getRelative(ORMEntity $entity, Relation $relation, array $filters = [], array $order_by = []): ?ORMEntity
	{
		return $this->db->runInTransaction(function () use ($entity, $relation, $filters, $order_by): ?ORMEntity {
			$tsf = $this->getScopedFiltersInstance($filters);

			$this->crud->assertRead($tsf);

			$qb = $tsf->selectRelatives($relation, $entity, 1, 0, $order_by);

			if (!$qb) {
				return null;
			}

			$results = $this->getResultsInstance($qb);
			$entity  = $results->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
					->onEntityEvent($entity, CRUDEntityEvent::AFTER_READ);
			}

			return $entity;
		});
	}

	/**
	 * Gets a given item relatives.
	 *
	 * @param \Gobl\ORM\ORMEntity           $entity
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param array                         $filters
	 * @param null|int                      $max
	 * @param int                           $offset
	 * @param array                         $order_by
	 * @param null|int                      $total
	 *
	 * @return \Gobl\ORM\ORMEntity[]
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getAllRelatives(
		ORMEntity $entity,
		Relation $relation,
		array $filters = [],
		?int $max = null,
		int $offset = 0,
		array $order_by = [],
		?int &$total = null
	): array {
		return $this->db->runInTransaction(function () use ($entity, $relation, $filters, $max, $offset, $order_by, &$total): array {
			$tsf = $this->getScopedFiltersInstance($filters);

			$this->crud->assertReadAll($tsf);

			$qb = $tsf->selectRelatives($relation, $entity, $max, $offset, $order_by);

			if (!$qb) {
				return [];
			}

			$results = $this->getResultsInstance($qb);

			$items = $results->fetchAllClass();

			$total = static::lazyTotalResultsCount($results, \count($items), $max, $offset);

			return $items;
		});
	}

	/**
	 * Ensure if all required field are filled, try to fill it with default value or throw error.
	 *
	 * @param array &$form The form
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	protected function fillRequiredFields(array &$form): void
	{
		$required_fields = $this->getRequiredFields();
		$completed       = true;
		$missing         = [];

		foreach ($required_fields as $field) {
			if (!isset($form[$field])) {
				$column  = $this->table->getColumnOrFail($field);
				$default = $column->getType()
					->getDefault();

				if (null === $default) {
					$completed = false;
					$missing[] = $field;
				} else {
					$form[$field] = $default;
				}
			}
		}

		if (!$completed) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_MISSING_FIELDS', $missing);
		}
	}

	/**
	 * Gets required forms fields.
	 *
	 * @return array
	 */
	protected function getRequiredFields(): array
	{
		$fields = [];

		foreach ($this->form_fields as $field => $required) {
			if (true === $required) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Gets filters class instance.
	 *
	 * @param array $filters
	 *
	 * @return \Gobl\ORM\ORMTableQuery
	 */
	protected function getScopedFiltersInstance(array $filters): ORMTableQuery
	{
		/** @var \Gobl\ORM\ORMTableQuery $class */
		$class = ORMClassKind::QUERY->getClassFQN($this->table);

		return $class::createInstance()
			->where($filters);
	}

	/**
	 * Gets results class instance.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return \Gobl\ORM\ORMResults
	 */
	protected function getResultsInstance(QBSelect $qb): ORMResults
	{
		/** @var \Gobl\ORM\ORMResults $results_class */
		$results_class = ORMClassKind::RESULTS->getClassFQN($this->table);

		return $results_class::createInstance($qb);
	}

	/**
	 * Persists (CREATE or UPDATE) a given entity instance in the database.
	 *
	 * @param \Gobl\ORM\ORMEntity $entity
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	private function persistItem(ORMEntity $entity): void
	{
		$values = $entity->toRow();

		if ($entity->isNew()) {
			// it is a creation
			$aic_full_name = $this->ai_column_full_name;

			if (!empty($aic_full_name) && null !== $values[$aic_full_name]) {
				throw new ORMException(\sprintf(
					'Auto increment column "%s" should be set to null.',
					$aic_full_name
				));
			}

			/** @var \Gobl\ORM\ORMTableQuery $class */
			$class = ORMClassKind::QUERY->getClassFQN($this->table);

			$qb = $class::createInstance()
				->insert($values);

			$result = $qb->execute();

			if (!empty($aic_full_name)) {
				if (!\is_string($result)) {
					throw new ORMException(\sprintf(
						'Unable to get last insert id for column "%s" in table "%s". Got "%s" while expecting "string|int".',
						$aic_full_name,
						$this->table->getName(),
						\get_debug_type($result),
					));
				}

				$last_insert_id = $result;
				$entity->hydrate([$aic_full_name => $last_insert_id])
					->isSaved(true);
			}
		} elseif (!empty($values) && !$entity->isSaved()) {
			// its an update
			$tsf = $this->getScopedFiltersInstance($entity->toIdentityFilters());

			$tsf->update($values)
				->limit(1)
				->execute();

			$entity->isSaved(true);
		}
	}
}
