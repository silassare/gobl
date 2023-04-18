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

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
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

	protected ?array $target_custom_filters = null;

	/**
	 * Relation constructor.
	 *
	 * @param RelationType                                  $type
	 * @param string                                        $name
	 * @param \Gobl\DBAL\Relations\Interfaces\LinkInterface $link
	 */
	public function __construct(
		protected RelationType $type,
		protected string $name,
		protected LinkInterface $link,
	) {
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf(
				'Relation name "%s" should match: %s',
				$name,
				self::NAME_PATTERN
			));
		}
	}

	/**
	 * Gets the relation link.
	 *
	 * @return \Gobl\DBAL\Relations\Interfaces\LinkInterface
	 */
	public function getLink(): LinkInterface
	{
		return $this->link;
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
		return $this->type->isMultiple();
	}

	/**
	 * Gets the relation host table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getHostTable(): Table
	{
		return $this->link->getHostTable();
	}

	/**
	 * Gets the relation target table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getTargetTable(): Table
	{
		return $this->link->getTargetTable();
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
	 * Gets relation target custom filters.
	 *
	 * @return null|array
	 */
	public function getTargetCustomFilters(): ?array
	{
		return $this->target_custom_filters;
	}

	/**
	 * Sets relation target custom filters.
	 *
	 * @param null|array $filters
	 *
	 * @return $this
	 */
	public function setTargetCustomFilters(?array $filters): static
	{
		// we can't check if the filters are valid here
		// because the target table classes files may not be yet generated

		$this->target_custom_filters = $filters;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$options['type']   = $this->type->value;
		$options['target'] = $this->getTargetTable()
			->getName();
		$options['link']   = $this->link->toArray();

		if ($this->target_custom_filters) {
			$options['filters'] = $this->target_custom_filters;
		}

		return $options;
	}

	/**
	 * Create a relation link from options.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $rdbms
	 * @param Table                                $host_table
	 * @param Table                                $target_table
	 * @param array                                $options
	 *
	 * @return LinkInterface
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public static function createLink(RDBMSInterface $rdbms, Table $host_table, Table $target_table, array $options): LinkInterface
	{
		$type = $options['type'] ?? null;

		if (\is_string($type)) {
			$type = LinkType::tryFrom($type);
		}

		if (!$type instanceof LinkType) {
			throw new DBALException('Invalid "type" for relation link.');
		}

		if (LinkType::COLUMNS === $type) {
			return new LinkColumns($host_table, $target_table, $options);
		}

		if (LinkType::MORPH === $type) {
			return new LinkMorph($host_table, $target_table, $options);
		}

		if (LinkType::THROUGH === $type) {
			$pivot_table = $options['pivot_table'] ?? null;

			if (!$pivot_table) {
				throw new DBALException(\sprintf('property "pivot_table" is required for relation link type "%s".', $type->value));
			}

			if (\is_string($pivot_table)) {
				$pivot_table = $rdbms->getTableOrFail($pivot_table);
			}

			if (!$pivot_table instanceof Table) {
				throw new DBALException(\sprintf(
					'property "pivot_table" defined for relation link type "%s" should be of string|%s type not "%s".',
					$type->value,
					Table::class,
					\get_debug_type($pivot_table)
				));
			}

			return new LinkThrough($rdbms, $host_table, $target_table, $pivot_table, $options);
		}

		throw new DBALException(\sprintf('Unsupported link type "%s".', $type->value));
	}
}
