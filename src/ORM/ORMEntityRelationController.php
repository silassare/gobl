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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Relations\Interfaces\RelationControllerInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Interfaces\ORMOptionsInterface;
use Gobl\ORM\Utils\Helpers;
use Override;
use Throwable;

/**
 * Class ORMEntityRelationController.
 *
 * @implements RelationControllerInterface<ORMEntity,array,array>
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

	#[Override]
	public function getRelativesStoreTable(): Table
	{
		return $this->table;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function get(ORMEntity $host_entity, ORMOptionsInterface $options): ?ORMEntity
	{
		return $this->controller->getRelative($host_entity, $this->relation, $options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function list(ORMEntity $host_entity, ORMOptionsInterface $options): ?ORMResults
	{
		return $this->controller->getAllRelatives($host_entity, $this->relation, $options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 * @throws GoblException
	 */
	#[Override]
	public function create(ORMEntity $host_entity, array $payload): mixed
	{
		Helpers::assertCanManageRelatives($this->table, $this->relation, [$host_entity]);

		$link          = $this->relation->getLink();
		$target_entity = ORM::entity($this->relation->getTargetTable());

		if (!$link->fillRelation($host_entity, $target_entity)) {
			throw new ORMException('Unable to fill relation.');
		}

		$target_entity->hydrate($payload);

		return $this->controller->addItem($target_entity);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 * @throws ORMException
	 * @throws CRUDException
	 */
	#[Override]
	public function link(ORMEntity $parent_entity, ORMEntity $child_entity, bool $auto_save = true): static
	{
		$link = $this->relation->getLink();

		if (!$link->fillRelation($parent_entity, $child_entity)) {
			throw new ORMException('Unable to link entities.');
		}

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
	#[Override]
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
	#[Override]
	public function delete(ORMEntity $host_entity, array $payload): ORMEntity
	{
		return $this->identify($host_entity, $payload)->selfDelete();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function getBatch(array $host_entities, ORMOptionsInterface $options): array
	{
		return $this->controller->getAllRelativesBatch(
			$host_entities,
			$this->relation,
			$options
		);
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
		$options = new ORMOptions();
		$options->setFilters($filters);

		$target = $this->controller->getRelative(
			$host_entity,
			$this->relation,
			$options
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
