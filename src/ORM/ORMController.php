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
use Gobl\CRUD\Enums\EntityEventType;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;

/**
 * Class ORMController.
 *
 * @template TEntity of \Gobl\ORM\ORMEntity
 * @template TQuery of \Gobl\ORM\ORMTableQuery<TEntity>
 * @template TResults of \Gobl\ORM\ORMResults<TEntity>
 */
abstract class ORMController
{
	/**
	 * @var array
	 */
	protected array $form_fields = [];

	/**
	 * @var RDBMSInterface
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
	 * Returns new instance.
	 *
	 * @return static<TEntity, TQuery, TResults>
	 */
	abstract public static function new(): static;

	/**
	 * Returns CRUD.
	 *
	 * @return CRUD
	 */
	public function getCRUD(): CRUD
	{
		return $this->crud;
	}

	/**
	 * Adds item to the table.
	 *
	 * @psalm-param array|TEntity $item
	 *
	 * @return TEntity
	 *
	 * @throws GoblException
	 */
	public function addItem(array|ORMEntity $item = []): ORMEntity
	{
		/** @var null|TEntity $instance */
		$instance = null;

		if (\is_array($item)) {
			$values = $item;
		} else {
			/** @var TEntity $item */
			$instance = $item;

			if ($instance->isSaved()) {
				return $instance;
			}

			$values = $instance->toRow();
		}

		return $this->db->runInTransaction(function () use ($instance, $values): ORMEntity {
			$values = $this->crud->assertCreate($values)
				->getForm();

			$this->fillRequiredFields($values);

			if (!$instance) {
				/** @var TEntity $instance */
				$instance = ORM::entity($this->table);

				$instance->hydrate($values);
			}

			$this->persistItem($instance);

			$this->crud->dispatchEntityEvent($instance, EntityEventType::AFTER_CREATE);

			return $instance;
		});
	}

	/**
	 * Updates one item in the table.
	 *
	 * @param array $filters    the row filters
	 * @param array $new_values the new values
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function updateOneItem(array $filters, array $new_values): ?ORMEntity
	{
		return $this->db->runInTransaction(function () use ($filters, $new_values): ?ORMEntity {
			/** @var TQuery $tq */
			$tq         = ORM::query($this->table, $filters);
			$action     = $this->crud->assertUpdate($tq, $new_values);
			$new_values = $action->getForm();

			static::assertFiltersNotEmpty($tq);
			$payload = $this->scopeUpdateValues($new_values);

			$entity = $tq->find(1)
				->fetchClass();

			if ($entity) {
				$this->crud->dispatchEntityEvent($entity, EntityEventType::BEFORE_UPDATE);

				$entity->hydrate($payload);

				$this->persistItem($entity);

				$this->crud->dispatchEntityEvent($entity, EntityEventType::AFTER_UPDATE);
			}

			return $entity ?: null;
		});
	}

	/**
	 * Updates all items in the table that match the given item filters.
	 *
	 * @param array    $filters    the row filters
	 * @param array    $new_values the new values
	 * @param null|int $max        maximum row to update. If null, all rows will be updated.
	 * @param array    $order_by   order by rules
	 *
	 * @return int affected row count
	 *
	 * @throws GoblException
	 */
	public function updateAllItems(
		array $filters,
		array $new_values,
		?int $max = null,
		array $order_by = []
	): int {
		return $this->db->runInTransaction(function () use ($filters, $new_values, $max, $order_by): int {
			/** @var TQuery $tq */
			$tq         = ORM::query($this->table, $filters);
			$action     = $this->crud->assertUpdateAll($tq, $new_values);
			$new_values = $action->getForm();

			static::assertFiltersNotEmpty($tq);

			$payload = $this->scopeUpdateValues($new_values);

			return $tq->update($payload)
				->limit($max)
				->orderBy($order_by)
				->execute();
		});
	}

	/**
	 * Deletes one item from the table.
	 *
	 * @param array     $filters the row filters
	 * @param null|bool $soft    soft delete
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function deleteOneItem(array $filters, ?bool $soft = null): ?ORMEntity
	{
		$soft = $this->clarifyUserSoftDeleteStrategy($soft);

		return $this->db->runInTransaction(function () use ($filters, $soft): ?ORMEntity {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table, $filters);

			$this->crud->assertDelete($tq);

			static::assertFiltersNotEmpty($tq);

			// because soft deleted rows are not included by default
			// this make sure if the entity is already soft deleted we don't need to do it again
			// but when the user intention is a hard delete we need to include soft deleted rows
			if (!$soft) {
				$tq->includeSoftDeletedRows();
			}

			$entity = $tq->find(1)
				->fetchClass();

			if ($entity) {
				$this->crud->dispatchEntityEvent($entity, EntityEventType::BEFORE_DELETE);

				// we need to make sure that we delete the selected entity and only that
				/** @var TQuery $tq */
				$tq = ORM::query($this->table, $entity->toIdentityFilters());

				$qb = $soft ? $tq->softDelete() : $tq->delete();

				$qb->limit(1)->execute();

				$this->crud->dispatchEntityEvent($entity, EntityEventType::AFTER_DELETE);

				return $entity;
			}

			return null;
		});
	}

	/**
	 * Deletes all items in the table that match the given item filters.
	 *
	 * @param array     $filters  the row filters
	 * @param null|int  $max      maximum row to delete. If null, all rows will be deleted.
	 * @param array     $order_by order by rules
	 * @param null|bool $soft     soft delete
	 *
	 * @return int affected row count
	 *
	 * @throws GoblException
	 */
	public function deleteAllItems(
		array $filters,
		?int $max = null,
		array $order_by = [],
		?bool $soft = null
	): int {
		$soft = $this->clarifyUserSoftDeleteStrategy($soft);

		return $this->db->runInTransaction(function () use ($order_by, $max, $filters, $soft): int {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table, $filters);

			$this->crud->assertDeleteAll($tq);

			static::assertFiltersNotEmpty($tq);

			$qb = $soft ? $tq->softDelete() : $tq->delete();

			return $qb->limit($max)
				->orderBy($order_by)
				->execute();
		});
	}

	/**
	 * Gets item from the table that match the given filters.
	 *
	 * @param array $filters  the row filters
	 * @param array $order_by order by rules
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function getItem(array $filters, array $order_by = []): ?ORMEntity
	{
		// we use transaction for reading too
		// https://stackoverflow.com/questions/308905/should-there-be-a-transaction-for-read-queries
		return $this->db->runInTransaction(function () use ($filters, $order_by): ?ORMEntity {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table, $filters);

			$this->crud->assertRead($tq);

			static::assertFiltersNotEmpty($tq);

			$entity = $tq->find(1, 0, $order_by)
				->fetchClass();

			if ($entity) {
				$this->crud->dispatchEntityEvent($entity, EntityEventType::AFTER_READ);
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
	 * @param null|int &$total   total number of items that match the filters
	 *
	 * @return TEntity[]
	 *
	 * @throws GoblException
	 */
	public function getAllItems(
		array $filters = [],
		?int $max = null,
		int $offset = 0,
		array $order_by = [],
		?int &$total = null
	): array {
		return $this->db->runInTransaction(function () use ($filters, $max, $offset, $order_by, &$total): array {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table, $filters);

			$this->crud->assertReadAll($tq);

			$results = $tq->find($max, $offset, $order_by);

			$items = $results->fetchAllClass();

			$total = static::lazyTotalResultsCount($results, \count($items), $max, $offset);

			return $items;
		});
	}

	/**
	 * Gets all items from the table with a custom query builder instance.
	 *
	 * @param QBSelect $qb     the custom select query instance
	 * @param null|int $max    maximum row to retrieve
	 * @param int      $offset first row offset
	 * @param null|int $total  total number of items that match the filters
	 *
	 * @return TEntity[]
	 *
	 * @throws GoblException
	 */
	public function getAllItemsCustom(QBSelect $qb, ?int $max = null, int $offset = 0, ?int &$total = null): array
	{
		return $this->db->runInTransaction(function () use ($qb, $max, $offset, &$total): array {
			$this->crud->assertReadAll(ORM::query($this->table));

			$qb->limit($max, $offset);

			/** @var TResults $results */
			$results = ORM::results($this->table, $qb);

			$items = $results->fetchAllClass(false);

			$total = static::lazyTotalResultsCount($results, \count($items), $max, $offset);

			return $items;
		});
	}

	/**
	 * Gets a given item relative.
	 *
	 * @param ORMEntity $host_entity
	 * @param Relation  $relation
	 * @param array     $filters
	 * @param array     $order_by
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function getRelative(
		ORMEntity $host_entity,
		Relation $relation,
		array $filters = [],
		array $order_by = []
	): ?ORMEntity {
		return $this->db->runInTransaction(function () use ($host_entity, $relation, $filters, $order_by): ?ORMEntity {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table, $filters);

			$this->crud->assertRead($tq);

			$qb = $tq->selectRelatives($relation, $host_entity, 1, 0, $order_by);

			if (!$qb) {
				return null;
			}

			/** @var TResults $results */
			$results       = ORM::results($this->table, $qb);
			$target_entity = $results->fetchClass();

			if ($target_entity) {
				$this->crud->dispatchEntityEvent($target_entity, EntityEventType::AFTER_READ);
			}

			return $target_entity;
		});
	}

	/**
	 * Gets a given item relatives.
	 *
	 * @param ORMEntity $host_entity
	 * @param Relation  $relation
	 * @param array     $filters
	 * @param null|int  $max
	 * @param int       $offset
	 * @param array     $order_by
	 * @param null|int  $total
	 *
	 * @return TEntity[]
	 *
	 * @throws GoblException
	 */
	public function getAllRelatives(
		ORMEntity $host_entity,
		Relation $relation,
		array $filters = [],
		?int $max = null,
		int $offset = 0,
		array $order_by = [],
		?int &$total = null
	): array {
		return $this->db->runInTransaction(
			function () use ($host_entity, $relation, $filters, $max, $offset, $order_by, &$total): array {
				/** @var TQuery $tq */
				$tq = ORM::query($this->table, $filters);

				$this->crud->assertReadAll($tq);

				$qb = $tq->selectRelatives($relation, $host_entity, $max, $offset, $order_by);

				if (!$qb) {
					return [];
				}

				/** @var TResults $results */
				$results = ORM::results($this->table, $qb);

				$items = $results->fetchAllClass();

				$total = static::lazyTotalResultsCount($results, \count($items), $max, $offset);

				return $items;
			}
		);
	}

	/**
	 * Ensure if all required field are filled, try to fill it with default value or throw error.
	 *
	 * @param array &$form The form
	 *
	 * @throws ORMQueryException
	 * @throws ORMException
	 */
	protected function fillRequiredFields(array &$form): void
	{
		$required_fields = $this->getRequiredFields();
		$completed       = true;
		$missing         = [];

		foreach ($required_fields as $field) {
			if (isset($form[$field])) {
				continue;
			}

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
			if (true !== $required) {
				continue;
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Asserts that the filters are not empty.
	 *
	 * @param ORMTableQuery $filters the row filters
	 *
	 * @throws ORMQueryException
	 */
	protected static function assertFiltersNotEmpty(ORMTableQuery $filters): void
	{
		if ($filters->getFilters()
			->isEmpty()) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_FILTERS_EMPTY');
		}
	}

	/**
	 * Keeps only the values for fields that are columns in the table.
	 * If no column is found, an exception is thrown.
	 *
	 * @param array $values the values
	 *
	 * @throws ORMQueryException
	 */
	protected function scopeUpdateValues(array $values = []): array
	{
		$scope_values = [];
		foreach ($values as $column => $value) {
			if (!$this->table->hasColumn($column)) {
				continue;
			}

			$scope_values[$column] = $value;
		}

		if (empty($scope_values)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_NO_FIELDS_TO_UPDATE');
		}

		return $scope_values;
	}

	/**
	 * Lazily count total row that match a select query according to the current results and current pagination info.
	 *
	 * @param ORMResults $results
	 * @param int        $found
	 * @param null|int   $max
	 * @param int        $offset
	 *
	 * @return int
	 */
	protected static function lazyTotalResultsCount(
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
	 * Persists (CREATE or UPDATE) a given entity instance in the database.
	 *
	 * @psalm-param TEntity $entity
	 *
	 * @throws ORMException
	 */
	private function persistItem(ORMEntity $entity): void
	{
		$values = $entity->toRow();

		if ($entity->isNew()) {
			// it is a creation
			$aic_full_name = $this->ai_column_full_name;

			if (!empty($aic_full_name) && null !== $values[$aic_full_name]) {
				throw new ORMException(
					\sprintf(
						'Auto increment column "%s" should be set to null.',
						$aic_full_name
					)
				);
			}

			/** @var TQuery $tq */
			$tq = ORM::query($this->table);

			$qb = $tq->insert($values);

			$result = $qb->execute();

			if (!empty($aic_full_name)) {
				if (!\is_string($result)) {
					throw new ORMException(
						\sprintf(
							'Unable to get last insert id for column "%s" in table "%s". Got "%s" while expecting "string|int".',
							$aic_full_name,
							$this->table->getName(),
							\get_debug_type($result),
						)
					);
				}

				$last_insert_id = $result;
				$entity->hydrate([$aic_full_name => $last_insert_id])
					->isSaved(true);
			}
		} elseif (!empty($values) && !$entity->isSaved()) {
			// its an update
			/** @var TQuery $tq */
			$tq = ORM::query($this->table, $entity->toIdentityFilters());

			$tq->update($values)
				->limit(1)
				->execute();

			$entity->isSaved(true);
		}
	}

	/**
	 * Clarify the user soft delete strategy intention.
	 *
	 * @throws DBALException
	 */
	private function clarifyUserSoftDeleteStrategy(?bool $soft): bool
	{
		if (true === $soft) {
			// the user clearly defined the soft delete strategy
			// so we ensure it's possible
			$this->table->assertSoftDeletable();

			return true;
		}

		if (null === $soft) {
			// we choose depending on the table capabilities
			return $this->table->isSoftDeletable();
		}

		return false;
	}
}
