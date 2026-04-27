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

namespace Gobl\ORM\Utils;

use Gobl\DBAL\Column;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Interfaces\WithPaginationInterface;
use Gobl\ORM\ORMEntity;

/**
 * Class Helpers.
 *
 * @internal
 */
final class Helpers
{
	/**
	 * When using cursor-based pagination, this method returns the cursor column as a `Column` instance.
	 *
	 * If the cursor column is not explicitly provided in the options, it defaults to the single primary key column of the table.
	 */
	public static function requireCursorColumn(Table $table, WithPaginationInterface $options): Column
	{
		$cursor_column = $options->getCursorColumn();

		if (null === $cursor_column) {
			// get single column primary key as default cursor column when not provided
			if (!$table->hasSinglePKColumn()) {
				throw new ORMQueryException('GOBL_ORM_CURSOR_COLUMN_NOT_PROVIDED', [
					'_table'  => $table->getFullName(),
					'_reason' => 'The table does not have a single primary key column, so a cursor column must be explicitly provided in the pagination options.',
				]);
			}
			$cursor_column = $table->getPrimaryKeyConstraint()->getColumns()[0];
		}

		return $table->getColumnOrFail($cursor_column);
	}

	/**
	 * Assert if relatives can be retrieved using the expected target table and host entities.
	 *
	 * @param Table           $expected_target_table
	 * @param Relation        $relation
	 * @param list<ORMEntity> $host_entities
	 *
	 * @internal
	 */
	public static function assertCanManageRelatives(Table $expected_target_table, Relation $relation, array $host_entities): void
	{
		$target_table = $relation->getTargetTable();
		$host_table   = $relation->getHostTable();

		if ($target_table !== $expected_target_table) {
			throw new ORMRuntimeException(
				\sprintf(
					'The relation "%s" target table "%s" is not the same as the expected target table "%s".',
					$relation->getName(),
					$target_table->getFullName(),
					$expected_target_table->getFullName()
				)
			);
		}

		foreach ($host_entities as $index => $entry) {
			if (!$entry->isSaved()) {
				throw new ORMRuntimeException(\sprintf('Entity at index "%s" should be persisted to get relatives.', $index));
			}

			if ($entry::table() !== $host_table) {
				$expected_entity_class = ORMClassKind::ENTITY->getClassFQN($host_table);

				throw new ORMRuntimeException(
					\sprintf(
						'To get relatives for the relation "%s" the entity at index "%s" should be an instance of "%s" not "%s".',
						$relation->getName(),
						$index,
						$expected_entity_class,
						\get_class($entry)
					)
				);
			}
		}
	}
}
