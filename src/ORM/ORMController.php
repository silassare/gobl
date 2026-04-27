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
use Gobl\ORM\Interfaces\ORMCreateOptionsInterface;
use Gobl\ORM\Interfaces\ORMDeleteOptionsInterface;
use Gobl\ORM\Interfaces\ORMSelectOptionsInterface;
use Gobl\ORM\Interfaces\ORMUpdateOptionsInterface;

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
	 * @return static
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
	 * @psalm-param array|ORMCreateOptionsInterface|TEntity $options
	 *
	 * @return TEntity
	 *
	 * @throws GoblException
	 */
	public function addItem(array|ORMCreateOptionsInterface|ORMEntity $options = []): ORMEntity
	{
		/** @var null|TEntity $instance */
		$instance = null;

		if ($options instanceof ORMCreateOptionsInterface) {
			$values = $options->getFormData($this->table);
		} elseif (\is_array($options)) {
			$values = $options;
		} else {
			/** @var TEntity $options */
			$instance = $options;

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
	 * @param ORMUpdateOptionsInterface $options
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function updateOneItem(ORMUpdateOptionsInterface $options): ?ORMEntity
	{
		return $this->db->runInTransaction(function () use ($options): ?ORMEntity {
			$new_values = $options->getFormData($this->table);

			/** @var TQuery $tq */
			$tq         = ORM::query($this->table);
			$action     = $this->crud->assertUpdate($tq, $new_values);
			$new_values = $action->getForm();
			$payload    = $this->scopeUpdateValues($new_values);

			$entity = $tq->find($options)->fetchClass();

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
	 * @param ORMUpdateOptionsInterface $options
	 *
	 * @return int affected row count
	 *
	 * @throws GoblException
	 */
	public function updateAllItems(ORMUpdateOptionsInterface $options): int
	{
		return $this->db->runInTransaction(function () use ($options): int {
			$new_values = $options->getFormData($this->table);

			/** @var TQuery $tq */
			$tq         = ORM::query($this->table);
			$action     = $this->crud->assertUpdateAll($tq, $new_values);
			$new_values = $action->getForm();

			$payload = $this->scopeUpdateValues($new_values);

			return $tq->update($options, $payload)->execute();
		});
	}

	/**
	 * Deletes one item from the table.
	 *
	 * @param ORMDeleteOptionsInterface $options
	 * @param null|bool                 $soft    soft delete
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function deleteOneItem(ORMDeleteOptionsInterface $options, ?bool $soft = null): ?ORMEntity
	{
		$soft = $this->clarifyUserSoftDeleteStrategy($soft);

		return $this->db->runInTransaction(function () use ($options, $soft): ?ORMEntity {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table);

			$this->crud->assertDelete($tq);

			// because soft deleted rows are not included by default
			// this make sure if the entity is already soft deleted we don't need to do it again
			// but when the user intention is a hard delete we need to include soft deleted rows
			if (!$soft) {
				$tq->includeSoftDeletedRows();
			}

			$entity = $tq->find($options)->fetchClass();

			if ($entity) {
				$this->crud->dispatchEntityEvent($entity, EntityEventType::BEFORE_DELETE);

				// we need to make sure that we delete the selected entity and only that
				/** @var TQuery $tq */
				$tq = ORM::query($this->table);

				// we want to make sure that we delete the same entity we fetched before
				$del_opt = ORMOptions::makePaginated(1)->setFilters($entity->toIdentityFilters());
				$qb      = $soft ? $tq->softDelete($del_opt) : $tq->delete($del_opt);

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
	 * @param ORMDeleteOptionsInterface $options
	 * @param null|bool                 $soft    soft delete
	 *
	 * @return int affected row count
	 *
	 * @throws GoblException
	 */
	public function deleteAllItems(ORMDeleteOptionsInterface $options, ?bool $soft = null): int
	{
		$soft = $this->clarifyUserSoftDeleteStrategy($soft);

		return $this->db->runInTransaction(function () use ($options, $soft): int {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table);

			$this->crud->assertDeleteAll($tq);

			$qb = $soft ? $tq->softDelete($options) : $tq->delete($options);

			return $qb->execute();
		});
	}

	/**
	 * Gets item from the table that match the given filters.
	 *
	 * @param ORMSelectOptionsInterface $options
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function getItem(ORMSelectOptionsInterface $options): ?ORMEntity
	{
		// we use transaction for reading too
		// https://stackoverflow.com/questions/308905/should-there-be-a-transaction-for-read-queries
		return $this->db->runInTransaction(function () use ($options): ?ORMEntity {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table);

			$this->crud->assertRead($tq);

			$entity = $tq->find($options)->fetchClass();

			if ($entity) {
				$this->crud->dispatchEntityEvent($entity, EntityEventType::AFTER_READ);
			}

			return $entity;
		});
	}

	/**
	 * Gets all items from the table that match the given filters.
	 *
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return TResults
	 *
	 * @throws GoblException
	 */
	public function getAllItems(?ORMSelectOptionsInterface $options = null): ORMResults
	{
		return $this->db->runInTransaction(function () use ($options): ORMResults {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table);

			$this->crud->assertReadAll($tq);

			return $tq->find($options);
		});
	}

	/**
	 * Gets all items from the table with a custom query builder instance.
	 *
	 * @param QBSelect $qb the custom select query instance
	 *
	 * @return TResults
	 *
	 * @throws GoblException
	 */
	public function getAllItemsCustom(QBSelect $qb): ORMResults
	{
		return $this->db->runInTransaction(function () use ($qb): ORMResults {
			$this->crud->assertReadAll(ORM::query($this->table));

			/** @var TResults $results */
			return ORM::results($this->table, $qb);
		});
	}

	/**
	 * Gets a given item relative.
	 *
	 * @param ORMEntity                      $host_entity
	 * @param Relation                       $relation
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return null|TEntity
	 *
	 * @throws GoblException
	 */
	public function getRelative(
		ORMEntity $host_entity,
		Relation $relation,
		?ORMSelectOptionsInterface $options = null
	): ?ORMEntity {
		return $this->db->runInTransaction(function () use ($host_entity, $relation, $options): ?ORMEntity {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table);

			$options?->setMax(1);

			$this->crud->assertReadRelative($tq, $relation);

			$results = $tq->findRelatives($relation, $host_entity, $options);

			if (null === $results) {
				return null;
			}

			$target_entity = $results->fetchClass();

			if ($target_entity) {
				$this->crud->dispatchEntityEvent($target_entity, EntityEventType::AFTER_READ);
			}

			return $target_entity;
		});
	}

	/**
	 * Gets all relatives for a given host entity.
	 *
	 * @param ORMEntity                      $host_entity
	 * @param Relation                       $relation
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return ?TResults
	 *
	 * @throws GoblException
	 */
	public function getAllRelatives(
		ORMEntity $host_entity,
		Relation $relation,
		?ORMSelectOptionsInterface $options = null
	): ?ORMResults {
		return $this->db->runInTransaction(
			function () use ($host_entity, $relation, $options): ?ORMResults {
				/** @var TQuery $tq */
				$tq = ORM::query($this->table);

				$this->crud->assertReadAllRelatives($tq, $relation);

				return $tq->findRelatives($relation, $host_entity, $options);
			}
		);
	}

	/**
	 * Loads one relative per host entity in a single batch query when possible.
	 *
	 * The returned map is keyed by {@see ORMEntity::toIdentityKey()} of each host entity.
	 *
	 * @param ORMEntity[]                    $host_entities
	 * @param Relation                       $relation
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return array<string, null|TEntity>
	 *
	 * @throws GoblException
	 */
	public function getRelativeBatch(
		array $host_entities,
		Relation $relation,
		?ORMSelectOptionsInterface $options = null
	): array {
		if (empty($host_entities)) {
			return [];
		}

		return $this->db->runInTransaction(
			function () use ($host_entities, $relation, $options): array {
				/** @var TQuery $tq */
				$tq = ORM::query($this->table);

				$this->crud->assertReadAllRelatives($tq, $relation);

				// Pre-fill map with null for every given host entity.
				$out = [];
				foreach ($host_entities as $entry) {
					$out[$entry->toIdentityKey()] = null;
				}

				$host_results = $tq->findRelativesBatch($relation, $host_entities, $options);

				if (null !== $host_results) {
					$key_factory = static fn (ORMEntity $entry) => $entry->getComputedValue(ORMTableQuery::BATCH_HOST_IDENTITY_KEY);

					foreach ($host_results->groupBy($key_factory) as $key => $target_entity) {
						$this->crud->dispatchEntityEvent($target_entity, EntityEventType::AFTER_READ);

						$out[$key] = $target_entity;
					}
				} else {
					// Fallback: one query per host.
					foreach ($host_entities as $host) {
						/** @var TQuery $tq */
						$tq      = ORM::query($this->table);
						$results = $tq->findRelatives($relation, $host, $options);

						if ($results) {
							$target_entity = $results->fetchClass();

							if ($target_entity) {
								$this->crud->dispatchEntityEvent($target_entity, EntityEventType::AFTER_READ);
							}

							$out[$host->toIdentityKey()] = $target_entity;
						}
					}
				}

				return $out;
			}
		);
	}

	/**
	 * Loads all relatives per host entity in a single batch query when possible.
	 *
	 * The returned map is keyed by {@see ORMEntity::toIdentityKey()} of each host entity.
	 *
	 * @param ORMEntity[]                    $host_entities
	 * @param Relation                       $relation
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return array<string, TEntity[]>
	 *
	 * @throws GoblException
	 */
	public function getAllRelativesBatch(
		array $host_entities,
		Relation $relation,
		?ORMSelectOptionsInterface $options =   null
	): array {
		if (empty($host_entities)) {
			return [];
		}

		return $this->db->runInTransaction(
			function () use ($host_entities, $relation, $options): array {
				/** @var TQuery $tq */
				$tq = ORM::query($this->table);

				$this->crud->assertReadAllRelatives($tq, $relation);

				// Pre-fill map with empty arrays for every given host entity.
				$out  = [];

				foreach ($host_entities as $host) {
					$out[$host->toIdentityKey()] = [];
				}

				// Attempt a single batch query.
				$results = $tq->findRelativesBatch($relation, $host_entities, $options);

				if (null !== $results) {
					foreach ($results->lazy() as $entry) {
						$key         = $entry->getComputedValue(ORMTableQuery::BATCH_HOST_IDENTITY_KEY);
						$out[$key][] = $entry;
					}
				} else {
					// Fallback: one query per host.
					foreach ($host_entities as $host) {
						/** @var TQuery $tq */
						$tq       = ORM::query($this->table);
						$results  = $tq->findRelatives($relation, $host, $options);

						if ($results) {
							$out[$host->toIdentityKey()] = $results->fetchAllClass();
						}
					}
				}

				return $out;
			}
		);
	}

	/**
	 * Counts the relatives of a single host entity.
	 *
	 * @param ORMEntity                      $host_entity
	 * @param Relation                       $relation
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return int
	 *
	 * @throws GoblException
	 */
	public function countRelatives(
		ORMEntity $host_entity,
		Relation $relation,
		?ORMSelectOptionsInterface $options = null
	): int {
		return $this->db->runInTransaction(function () use ($host_entity, $relation, $options): int {
			/** @var TQuery $tq */
			$tq = ORM::query($this->table);

			$this->crud->assertReadAllRelatives($tq, $relation);

			$results = $tq->findRelatives($relation, $host_entity, $options);

			if (!$results) {
				return 0;
			}

			return $results->getTotal($options, true);
		});
	}

	/**
	 * Counts relatives for multiple host entities.
	 *
	 * The returned map is keyed by {@see ORMEntity::toIdentityKey()} of each host entity.
	 *
	 * @param ORMEntity[]                    $host_entities
	 * @param Relation                       $relation
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return array<string, int>
	 *
	 * @throws GoblException
	 */
	public function countRelativesBatch(
		array $host_entities,
		Relation $relation,
		?ORMSelectOptionsInterface $options = null
	): array {
		if (empty($host_entities)) {
			return [];
		}

		return $this->db->runInTransaction(
			function () use ($host_entities, $relation, $options): array {
				/** @var TQuery $tq */
				$tq = ORM::query($this->table);

				$this->crud->assertReadAllRelatives($tq, $relation);

				$out = [];

				foreach ($host_entities as $host) {
					$results = $tq->findRelatives($relation, $host, $options);

					$out[$host->toIdentityKey()] = $results?->getTotal($options, true) ?? 0;
				}

				return $out;
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
	 * Persists (INSERT or UPDATE) the given entity to the database.
	 *
	 * - **INSERT path** (`entity->isNew()`):
	 *   - throws `ORMException` if the auto-increment column is non-null (caller must not pre-set it).
	 *   - after a successful insert, fetches `lastInsertId()` and hydrates the entity with it,
	 *     then marks it saved via `isSaved(true)`.
	 * - **UPDATE path** (entity exists and is not saved):
	 *   - builds an identity filter via `toIdentityFilters()` and updates with `limit(1)`.
	 *   - marks the entity saved after a successful update.
	 *
	 * A saved, non-new entity with no changes is silently skipped (no-op).
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
			$tq      = ORM::query($this->table);
			$options = new ORMOptions();
			$options->setFilters($entity->toIdentityFilters());
			$options->setMax(1);

			$tq->update($options, $values)
				->execute();

			$entity->isSaved(true);
		}
	}

	/**
	 * Determines whether to use soft-delete or hard-delete based on the caller's intent and table capabilities.
	 *
	 * | `$soft`  | Result |
	 * |----------|--------|
	 * | `true`   | Asserts the table is soft-deletable (throws if not), returns `true`. |
	 * | `null`   | Auto-detects: returns `true` if the table is soft-deletable, `false` otherwise. |
	 * | `false`  | Caller explicitly requests hard-delete; returns `false` unconditionally. |
	 *
	 * @throws DBALException when `$soft=true` but the table does not support soft-delete
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
