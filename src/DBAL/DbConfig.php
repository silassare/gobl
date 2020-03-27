<?php

	namespace Gobl\DBAL;

	final class DbConfig
	{
		/** @var array */
		protected $config;

		public function __construct(array $config)
		{
			$this->config = array_merge([
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
		public function getConfig() { return $this->config; }

		/**
		 * @return string
		 */
		public function getDbHost() { return $this->config['db_host']; }

		/**
		 * @return string
		 */
		public function getDbName() { return $this->config['db_name']; }

		/**
		 * @return string
		 */
		public function getDbUser() { return $this->config['db_user']; }

		/**
		 * @return string
		 */
		public function getDbPass() { return $this->config['db_pass']; }

		/**
		 * @return string
		 */
		public function getDbCharset() { return $this->config['db_charset']; }
	}