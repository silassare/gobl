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

namespace Gobl\DBAL;

use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class DbConfig.
 */
final class DbConfig implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/** @var array */
	private array $config;

	/**
	 * DbConfig constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		$this->config = \array_merge([
			'db_table_prefix'    => '',
			'db_host'            => '',
			'db_port'            => '',
			'db_name'            => '',
			'db_user'            => '',
			'db_pass'            => '',
			'db_charset'         => 'utf8mb4',
			'db_collate'         => 'utf8mb4_unicode_ci',
		], $config);
	}

	/**
	 * @return string
	 */
	public function getDbHost(): string
	{
		return $this->config['db_host'];
	}

	/**
	 * @return string
	 */
	public function getDbPort(): int|string
	{
		return $this->config['db_port'];
	}

	/**
	 * @return string
	 */
	public function getDbName(): string
	{
		return $this->config['db_name'];
	}

	/**
	 * @return string
	 */
	public function getDbUser(): string
	{
		return $this->config['db_user'];
	}

	/**
	 * @return string
	 */
	public function getDbPass(): string
	{
		return $this->config['db_pass'];
	}

	/**
	 * @return string
	 */
	public function getDbCharset(): string
	{
		return $this->config['db_charset'];
	}

	/**
	 * @return string
	 */
	public function getDbCollate(): string
	{
		return $this->config['db_collate'];
	}

	/**
	 * @return string
	 */
	public function getDbTablePrefix(): string
	{
		return $this->config['db_table_prefix'];
	}

	/**
	 * Returns a safe array.
	 *
	 * @return array
	 */
	public function toSafeArray(): array
	{
		$config = $this->toArray();

		$config['db_host'] = $config['db_port'] = $config['db_name'] = $config['db_user'] = $config['db_pass'] = '***';

		return $config;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return $this->config;
	}
}
