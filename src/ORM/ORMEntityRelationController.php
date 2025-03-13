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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Relations\Interfaces\RelationControllerInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Throwable;

/**
 * Class ORMEntityRelationController.
 *
 * @template TEntity of ORMEntity
 *
 * @implements RelationControllerInterface<TEntity,ORMEntity,array,array>
 */
class ORMEntityRelationController implements RelationControllerInterface
{
	protected Table $table;
	protected ORMController $controller;

	/**
	 * ORMRelatives constructor.
	 */
	public function __construct(protected Relation $relation)
	{
		$table            = $relation->getTargetTable();
		$this->controller = ORM::ctrl($table);
		$this->table      = $table;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	public function get(ORMEntity $host_entity, ORMRequest $request): ?ORMEntity
	{
		$order_by = $request->getOrderBy();
		$filters  = $request->getFilters();

		return $this->controller->getRelative($host_entity, $this->relation, $order_by, $filters);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	public function list(ORMEntity $host_entity, ORMRequest $request, ?int &$total_records = null): array
	{
		$max      = $request->getMax();
		$offset   = $request->getOffset();
		$order_by = $request->getOrderBy();
		$filters  = $request->getFilters();

		return $this->controller->getAllRelatives(
			$host_entity,
			$this->relation,
			$filters,
			$max,
			$offset,
			$order_by,
			$total_records
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 * @throws GoblException
	 */
	public function create(ORMEntity $host_entity, array $payload): mixed
	{
		ORMTableQuery::assertCanManageRelatives($this->table, $this->relation, $host_entity);

		$link = $this->relation->getLink();

		if (!$link->fillRelation($host_entity, $payload)) {
			throw new ORMException('Unable to fill relation.');
		}

		return $this->controller->addItem($payload);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 * @throws ORMException
	 * @throws CRUDException
	 */
	public function link(ORMEntity $parent_entity, ORMEntity $child_entity, bool $auto_save = true): static
	{
		$link = $this->relation->getLink();

		$payload = [];
		if (!$link->fillRelation($parent_entity, $payload)) {
			throw new ORMException('Unable to link entities.');
		}

		$child_entity->hydrate($payload);

		if ($auto_save) {
			$child_entity->save();
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ORMException
	 * @throws GoblException
	 */
	public function update(ORMEntity $host_entity, array $payload): ORMEntity
	{
		$target = $this->identify($host_entity, $payload)->hydrate($payload);

		$target->save();

		return $target;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ORMException
	 * @throws GoblException
	 */
	public function delete(ORMEntity $host_entity, array $payload): ORMEntity
	{
		return $this->identify($host_entity, $payload)->selfDelete();
	}

	/**
	 * Identify the relative.
	 *
	 * @throws GoblException
	 * @throws ORMException
	 */
	private function identify(ORMEntity $host_entity, array $relative): ORMEntity
	{
		$filters = $this->extractFilters($relative);

		$target = $this->controller->getRelative(
			$host_entity,
			$this->relation,
			$filters
		);

		if (!$target) {
			throw new ORMException('Unable to identify relative.');
		}

		return $target;
	}

	/**
	 * Try to extract the relative identity filters from the payload.
	 *
	 * @param mixed $payload
	 *
	 * @return array
	 *
	 * @throws ORMException
	 */
	private function extractFilters(array $payload): array
	{
		try {
			if (empty($payload)) {
				throw new ORMException('Empty relative payload.');
			}

			$instance = ORM::entity($this->table, false, false);

			return $instance->hydrate($payload)->toIdentityFilters();
		} catch (Throwable $t) {
			throw new ORMException('Invalid relative data.', null, $t);
		}
	}
}
