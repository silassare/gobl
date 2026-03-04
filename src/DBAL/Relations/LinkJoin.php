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

use Gobl\DBAL\Builders\LinkBuilder;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Throwable;

/**
 * Class LinkJoin.
 */
final class LinkJoin extends Link
{
	/**
	 * @var LinkInterface[]
	 */
	private array $links = [];

	/**
	 * LinkJoin constructor.
	 *
	 * @param RDBMSInterface $rdbms
	 * @param Table          $host_table
	 * @param Table          $target_table
	 * @param array{
	 * 			steps: array{join: string, link: array|\Gobl\DBAL\Builders\LinkBuilder} }[],
	 *          filters?: null|array,
	 *       } $options
	 *
	 * @throws DBALException
	 */
	public function __construct(
		RDBMSInterface $rdbms,
		Table $host_table,
		Table $target_table,
		array $options,
	) {
		parent::__construct(LinkType::JOIN, $rdbms, $host_table, $target_table, $options);

		$steps  = $this->options['steps'] ?? [];

		if (empty($steps) || !\is_array($steps)) {
			throw new DBALException('The "steps" option must be a non-empty array of steps.');
		}

		$from_table = $this->host_table;

		foreach ($steps as $key => $opt) {
			$to_table_name = $opt['join'] ?? null;
			$link_option   = $opt['link'] ?? [];

			if (empty($to_table_name)) {
				throw new DBALException(\sprintf(
					'The "join" option is missing for the step %d.',
					$key
				));
			}

			if ($link_option instanceof LinkBuilder) {
				$link_option = $link_option->toArray();
			} elseif (!\is_array($link_option)) {
				throw new DBALException(\sprintf(
					'The "link" option must be an array|%s for the step %d not "%s".',
					LinkBuilder::class,
					$key,
					\gettype($link_option)
				));
			}

			$to_table = $this->rdbms->getTable($to_table_name);

			if (null === $to_table) {
				throw new DBALException(\sprintf(
					'The table "%s" used in the step %d of the link does not exist.',
					$to_table_name,
					$key
				));
			}

			try {
				$this->links[] = $this->subLink($from_table, $to_table, $link_option);
			} catch (Throwable $t) {
				throw new DBALException(\sprintf(
					'Failed to create the link for the step %d going from "%s" to table "%s".',
					$key,
					$from_table->getName(),
					$to_table->getName()
				), null, $t);
			}

			$from_table = $to_table;
		}

		// final check to ensure we end at the target table
		// if not, we try to auto create the last link
		if ($from_table->getName() !== $this->target_table->getName()) {
			try {
				$this->links[] = $this->subLink($from_table, $this->target_table, [
					'type' => LinkType::COLUMNS->value,
				]);
			} catch (Throwable $t) {
				throw new DBALException(\sprintf(
					'You did not specify the last link to the target table "%s" from table "%s". Automatic link creation failed.',
					$from_table->getName(),
					$this->target_table->getName()
				), null, $t);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Always returns `false` — multi-step join links span several intermediate tables,
	 * so a single host entity cannot deterministically populate target data.
	 */
	public function fillRelation(ORMEntity $host_entity, array &$target_data = []): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Applies each step's sub-link in order. The host entity is forwarded **only to the first
	 * step** (host → first pivot), so that concrete entity values filter the entry point of
	 * the chain. Subsequent step links receive `null` and operate in join mode.
	 *
	 * Returns `false` as soon as any step's `apply()` returns `false`.
	 */
	public function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		$host_to_first_pivot = true;

		foreach ($this->links as $link) {
			if (!$link->apply($target_qb, $host_to_first_pivot ? $host_entity : null)) {
				return false;
			}

			$host_to_first_pivot = false;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'type'        => $this->type->value,
		] + $this->options;
	}
}
