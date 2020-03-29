<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL;

final class DbConfig
{
	/** @var array */
	private $config;

	public function __construct(array $config)
	{
		$this->config = \array_merge([
			'db_host'    => '',
			'db_name'    => '',
			'db_user'    => '',
			'db_pass'    => '',
			'db_charset' => '',
		], $config);
	}

	/**
	 * @return array
	 */
	public function getConfig()
	{
		return $this->config;
	}

	/**
	 * @return string
	 */
	public function getDbHost()
	{
		return $this->config['db_host'];
	}

	/**
	 * @return string
	 */
	public function getDbName()
	{
		return $this->config['db_name'];
	}

	/**
	 * @return string
	 */
	public function getDbUser()
	{
		return $this->config['db_user'];
	}

	/**
	 * @return string
	 */
	public function getDbPass()
	{
		return $this->config['db_pass'];
	}

	/**
	 * @return string
	 */
	public function getDbCharset()
	{
		return $this->config['db_charset'];
	}
}
