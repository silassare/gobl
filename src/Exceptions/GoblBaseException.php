<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\Exceptions;

	/**
	 * Class GoblBaseException
	 *
	 * @package Gobl\Exceptions
	 */
	abstract class GoblBaseException extends \Exception
	{
		/**
		 * @var array
		 */
		protected $_data;

		/**
		 * Sensitive data prefix.
		 */
		const SENSITIVE_DATA_PREFIX = '_';

		/**
		 * @var array
		 */
		protected $_debug_data = [];

		/**
		 * GoblBaseException constructor.
		 *
		 * @param string          $message
		 * @param array           $data
		 * @param null|\Throwable $previous
		 */
		public function __construct($message, array $data = [], \Throwable $previous = null)
		{
			parent::__construct($message, 0, $previous);

			$this->_data = $data;
		}

		/**
		 * Gets data.
		 *
		 * We shouldn't expose all debug data to client, may contains sensitive data
		 * like table structure, table name etc...
		 * all sensitive data should be set with the sensitive data prefix
		 *
		 * @param bool $show_sensitive
		 *
		 * @return array
		 */
		public function getData($show_sensitive = false)
		{
			if (!$show_sensitive) {
				$data = [];
				foreach ($this->_data as $key => $value) {
					if (is_int($key) OR $key[0] !== self::SENSITIVE_DATA_PREFIX) {
						$data[$key] = $value;
					}
				}

				return $data;
			}

			return $this->_data;
		}

		/**
		 * Sets debug data.
		 *
		 * @param array $data
		 */
		public function setData(array $data)
		{
			$this->_data = $data;
		}

		/**
		 * Gobl exception to string formatter
		 *
		 * @return string
		 */
		public function __toString()
		{
			$e_data = json_encode($this->getData(true));
			$e_msg  = <<<STRING
\tFile    : {$this->getFile()}
\tLine    : {$this->getLine()}
\tCode    : {$this->getCode()}
\tMessage : {$this->getMessage()}
\tData    : $e_data
\tTrace   : {$this->getTraceAsString()}
STRING;

			return $e_msg;
		}
	}