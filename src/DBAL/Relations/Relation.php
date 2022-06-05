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

namespace Gobl\DBAL\Relations;

use Gobl\DBAL\Table;
use InvalidArgumentException;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Str;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Relation.
 */
abstract class Relation implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	public const NAME_PATTERN = '[a-zA-Z](?:[a-zA-Z0-9_-]*[a-zA-Z0-9])?';

	public const NAME_REG = '~^' . self::NAME_PATTERN . '$~';

	protected array $relation_columns = [];

	protected bool $use_auto_detected_foreign_key = false;

	/**
	 * Relation constructor.
	 *
	 * @param RelationType     $type
	 * @param string           $name
	 * @param \Gobl\DBAL\Table $host_table
	 * @param \Gobl\DBAL\Table $target_table
	 * @param null|array       $columns
	 */
	public function __construct(
		protected RelationType $type,
		protected string $name,
		protected Table $host_table,
		protected Table $target_table,
		?array $columns = null,
	) {
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf(
				'Relation name "%s" should match: %s',
				$name,
				self::NAME_PATTERN
			));
		}

		$this->checkRelationColumns($columns);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$options['target'] = $this->target_table->getName();
		$options['type']   = $this->type->value;

		if (!$this->use_auto_detected_foreign_key) {
			foreach ($this->relation_columns as $from => $to) {
				$key = $this->host_table->getColumnOrFail($from)
					->getName();
				$options['columns'][$key] = $this->target_table->getColumnOrFail($to)
					->getName();
			}
		}

		return $options;
	}

	/**
	 * Checks if the relation returns paginated items.
	 *
	 * ie: all relation items can't be retrieved at once.
	 *
	 * @return bool
	 */
	public function isPaginated(): bool
	{
		return RelationType::ONE_TO_MANY === $this->type || RelationType::MANY_TO_MANY === $this->type;
	}

	/**
	 * Gets the relation host table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getHostTable(): Table
	{
		return $this->host_table;
	}

	/**
	 * Gets the relation target table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getTargetTable(): Table
	{
		return $this->target_table;
	}

	/**
	 * Gets relation columns.
	 *
	 * @return array
	 */
	public function getRelationColumns(): array
	{
		return $this->relation_columns;
	}

	/**
	 * Gets the relation type.
	 *
	 * @return RelationType
	 */
	public function getType(): RelationType
	{
		return $this->type;
	}

	/**
	 * Gets relation getter method name.
	 *
	 * @return string
	 */
	public function getGetterName(): string
	{
		return Str::toGetterName($this->getName());
	}

	/**
	 * Gets the relation name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Checks relations columns.
	 *
	 * @param null|array $columns
	 */
	abstract protected function checkRelationColumns(?array $columns = null): void;
}
