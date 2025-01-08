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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBUtils;

/**
 * Trait QBBindTrait.
 */
trait QBBindTrait
{
	private array $bound_values = [];

	/** @var int[]|null[] */
	private array $bound_values_types = [];

	/**
	 * {@inheritDoc}
	 */
	public function getBoundValues(): array
	{
		return $this->bound_values;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBoundValuesTypes(): array
	{
		return $this->bound_values_types;
	}

	/**
	 * {@inheritDoc}
	 */
	public function resetParameters(): void
	{
		$this->bound_values       = [];
		$this->bound_values_types = [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function bindArray(array $params, array $types = []): static
	{
		foreach ($params as $param => $value) {
			$type = $types[$param] ?? null;
			$this->bind($param, $value, $type);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function bindNamed(string $name, mixed $value, ?int $type = null): static
	{
		$this->bind($name, $value, $type);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function bindPositional(int $offset, mixed $value, ?int $type = null): static
	{
		$this->bind($offset, $value, $type, true);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function bindMergeFrom(QBInterface $qb): static
	{
		if ($qb === $this) {
			return $this;
		}

		return $this->bindArray($qb->getBoundValues(), $qb->getBoundValuesTypes());
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function bindArrayForInList(array $list, array $types = [], bool $return_placeholders = false): array
	{
		$list         = \array_unique($list);
		$param_prefix = QBUtils::newParamKey();
		$count        = 0;
		$params       = [];
		$placeholders = [];

		foreach ($list as $k => $value) {
			$params[]       = $param_key = $param_prefix . '_' . ($count++);
			$placeholders[] = ':' . $param_key;
			$type           = $types[$k] ?? null;
			$this->bind($param_key, $value, $type);
		}

		return $return_placeholders ? $placeholders : $params;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isBoundParam(string $name): bool
	{
		return isset($this->bound_values[$name]);
	}

	/**
	 * Binds parameter to query.
	 *
	 * @param int|string $param                The parameter to bind
	 * @param mixed      $value                The value to bind
	 * @param null|int   $type                 Any \PDO::PARAM_* constants
	 * @param bool       $overwrite_positional To force overwrite positional parameter
	 *
	 * @throws DBALException
	 */
	protected function bind(int|string $param, mixed $value, ?int $type = null, bool $overwrite_positional = false): void
	{
		if (null === $type) {
			$type = QBUtils::paramType($value);
		}

		$dirty = false;
		$key0  = \array_key_first($this->bound_values);

		if (\is_int($param)) {
			if (null === $key0 || \is_int($key0)) {
				if ($overwrite_positional) {
					$this->bound_values[$param]       = $value;
					$this->bound_values_types[$param] = $type;
				} else {
					$this->bound_values[]       = $value;
					$this->bound_values_types[] = $type;
				}
			} else {
				$dirty = true;
			}
		} elseif (null === $key0 || \is_string($key0)) {
			$this->bound_values[$param]       = $value;
			$this->bound_values_types[$param] = $type;
		} else {
			$dirty = true;
		}

		if (true === $dirty) {
			throw new DBALException('You should not use both named and positional parameters in the same query.');
		}
	}
}
