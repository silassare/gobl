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

namespace Gobl\DBAL\Builders;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\LinkThrough;
use Gobl\DBAL\Relations\LinkType;
use Gobl\DBAL\Relations\ManyToMany;
use Gobl\DBAL\Relations\ManyToOne;
use Gobl\DBAL\Relations\OneToMany;
use Gobl\DBAL\Relations\OneToOne;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\RelationType;
use Gobl\DBAL\Table;
use PHPUtils\Str;

/**
 * Class RelationBuilder.
 */
final class RelationBuilder
{
	private ?Table    $target_table = null;
	private ?Relation $relation     = null;

	/**
	 * RelationBuilder constructor.
	 *
	 * @param \Gobl\DBAL\Relations\RelationType    $type
	 * @param string                               $name
	 * @param \Gobl\DBAL\Table                     $host_table
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $rdbms
	 */
	public function __construct(
		private readonly RelationType $type,
		private readonly string $name,
		private readonly Table $host_table,
		private readonly RDBMSInterface $rdbms,
	) {
	}

	/**
	 * Gets the relation.
	 *
	 * @return \Gobl\DBAL\Relations\Relation
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function getRelation(): Relation
	{
		return $this->relation ?? $this->usingColumns();
	}

	/**
	 * Sets the target table.
	 *
	 * @param string|Table $table
	 *
	 * @return $this
	 */
	public function from(string|Table $table): self
	{
		if ($table instanceof Table) {
			$this->target_table = $table;
		} else {
			$this->target_table = $this->rdbms->getTableOrFail($table);
		}

		return $this;
	}

	/**
	 * Specify a relation link of type "columns".
	 *
	 * @param array<string, string> $host_to_target_columns_map
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function usingColumns(array $host_to_target_columns_map = []): Relation
	{
		return $this->using([
			'type'    => LinkType::COLUMNS->value,
			'columns' => $host_to_target_columns_map,
		]);
	}

	/**
	 * Specify a relation link of type "morph".
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function usingMorph(string $prefix, ?string $host_type = null): Relation
	{
		$options = [
			'type'   => LinkType::MORPH->value,
			'prefix' => $prefix,
		];

		if ($host_type) {
			$options['host_type'] = $host_type;
		}

		return $this->using($options);
	}

	/**
	 * Specify a relation link of type "through".
	 *
	 * @param string|Table                   $pivot_table
	 * @param array<string, LinkType|string> $host_to_pivot_link_options
	 * @param array<string, LinkType|string> $pivot_to_target_link_options
	 *
	 * @return \Gobl\DBAL\Relations\Relation
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function through(
		string|Table $pivot_table,
		array $host_to_pivot_link_options = [],
		array $pivot_to_target_link_options = []
	): Relation {
		if (\is_string($pivot_table)) {
			$pivot_table = $this->rdbms->getTableOrFail($pivot_table);
		}

		$options = [
			'type'            => LinkType::THROUGH->value,
			'pivot_table'     => $pivot_table,
			'host_to_pivot'   => $host_to_pivot_link_options,
			'pivot_to_target' => $pivot_to_target_link_options,
		];

		return $this->using($options);
	}

	/**
	 * Creates a relation.
	 *
	 * @param array $link_options
	 *
	 * @return \Gobl\DBAL\Relations\Relation
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function using(array $link_options): Relation
	{
		if (!$this->target_table) {
			throw new DBALRuntimeException(\sprintf(
				'Target table is not set you must call the "%s" method first.',
				Str::callableName([$this, 'from'])
			));
		}

		$link = Relation::createLink($this->rdbms, $this->host_table, $this->target_table, $link_options);

		if (RelationType::ONE_TO_ONE === $this->type) {
			$r = new OneToOne($this->name, $link);
		} elseif (RelationType::ONE_TO_MANY === $this->type) {
			$r = new OneToMany($this->name, $link);
		} elseif (RelationType::MANY_TO_ONE === $this->type) {
			$r = new ManyToOne($this->name, $link);
		} elseif (RelationType::MANY_TO_MANY === $this->type) {
			if (!$link instanceof LinkThrough) {
				throw new DBALRuntimeException(\sprintf(
					'Many to many relation must be linked through a pivot table using the "%s" method.',
					Str::callableName([$this, 'through'])
				));
			}

			$r = new ManyToMany($this->name, $link);
		} else {
			throw new DBALRuntimeException('Unknown relation type.');
		}

		$this->relation = $r;

		return $r;
	}
}
