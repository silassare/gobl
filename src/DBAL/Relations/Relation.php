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
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Relations\Interfaces\RelationControllerInterface;
use Gobl\DBAL\Relations\Interfaces\RelationInterface;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMEntityRelationController;
use InvalidArgumentException;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Str;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Relation.
 *
 * @implements RelationInterface<ORMEntity,ORMEntity,array,array>
 */
abstract class Relation implements RelationInterface, ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * Relation constructor.
	 *
	 * @param RelationType  $type
	 * @param string        $name
	 * @param LinkInterface $link
	 */
	public function __construct(
		protected RelationType $type,
		protected string $name,
		protected LinkInterface $link,
	) {
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Relation name "%s" should match: %s',
					$name,
					self::NAME_PATTERN
				)
			);
		}
		if (!Gobl::isAllowedRelationName($name)) {
			throw new DBALRuntimeException(
				\sprintf(
					'Relation name "%s" is not allowed.',
					$this->name
				)
			);
		}
	}

	/**
	 * Create a relation link from options.
	 *
	 * @param RDBMSInterface $rdbms
	 * @param Table          $host_table
	 * @param Table          $target_table
	 * @param array          $options
	 *
	 * @return LinkInterface
	 *
	 * @throws DBALException
	 */
	public static function createLink(
		RDBMSInterface $rdbms,
		Table $host_table,
		Table $target_table,
		array $options
	): LinkInterface {
		$type    = $options['type'] ?? null;
		$filters = $options['filters'] ?? null;

		if (\is_string($type)) {
			$type = LinkType::tryFrom($type);
		}

		// It will be good if we can validate the filters here
		// for now we can't do that because the target table classes files may not be yet generated
		if ($filters && !\is_array($filters)) {
			throw new DBALException(
				\sprintf(
					'Property "filters" defined in relation link should be of array type not "%s".',
					\get_debug_type($filters)
				)
			);
		}

		if (!$type instanceof LinkType) {
			throw new DBALException('Invalid "type" property for relation link.');
		}

		switch ($type) {
			case LinkType::COLUMNS:
				return new LinkColumns($host_table, $target_table, $options);

			case LinkType::MORPH:
				return new LinkMorph($host_table, $target_table, $options);

			case LinkType::THROUGH:
				$pivot_table = $options['pivot_table'] ?? null;

				if (!$pivot_table) {
					throw new DBALException(
						\sprintf('Property "pivot_table" is required for relation link type "%s".', $type->value)
					);
				}

				if (\is_string($pivot_table)) {
					$pivot_table = $rdbms->getTableOrFail($pivot_table);
				}

				if (!$pivot_table instanceof Table) {
					throw new DBALException(
						\sprintf(
							'property "pivot_table" defined for relation link type "%s" should be of string|%s type not "%s".',
							$type->value,
							Table::class,
							\get_debug_type($pivot_table)
						)
					);
				}

				return new LinkThrough($rdbms, $host_table, $target_table, $pivot_table, $options);
		}

		throw new DBALException(\sprintf('Unsupported link type "%s".', $type->value));
	}

	/**
	 * Gets the relation link.
	 *
	 * @return LinkInterface
	 */
	public function getLink(): LinkInterface
	{
		return $this->link;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isPaginated(): bool
	{
		return $this->type->isMultiple();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHostTable(): Table
	{
		return $this->link->getHostTable();
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
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isPrivate(): bool
	{
		return $this->getTargetTable()->isPrivate();
	}

	/**
	 * Gets the relation target table.
	 *
	 * @return Table
	 */
	public function getTargetTable(): Table
	{
		return $this->link->getTargetTable();
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$options['type']   = $this->type->value;
		$options['target'] = $this->getTargetTable()
			->getName();
		$options['link'] = $this->link->toArray();

		return $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getController(): RelationControllerInterface
	{
		return new ORMEntityRelationController($this);
	}
}
