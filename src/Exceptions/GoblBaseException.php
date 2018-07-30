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
		 * @var array
		 */
		protected $_debug_data = [];

		/**
		 * GoblBaseException constructor.
		 *
		 * @param string          $message
		 * @param array           $data
		 * @param array           $debug
		 * @param null|\Throwable $preview
		 */
		public function __construct($message, array $data = [], array $debug = [], \Throwable $preview = null)
		{
			parent::__construct($message, 0, $preview);
			$this->_data       = $data;
			$this->_debug_data = $debug;
		}

		/**
		 * Gets data.
		 *
		 * @return array
		 */
		public function getData()
		{
			return $this->_data;
		}

		/**
		 * Gets debug data.
		 *
		 * @return array
		 */
		public function getDebugData()
		{
			return $this->_debug_data;
		}

		/**
		 * Sets debug data.
		 *
		 * @param array $debug
		 */
		public function setDebugData(array $debug)
		{
			$this->_debug_data = $debug;
		}

		/**
		 * Gobl exception to string formatter
		 *
		 * @return string
		 */
		public function __toString()
		{
			$e_data  = json_encode($this->getData());
			$e_debug = json_encode($this->getDebugData());
			$e_msg   = <<<STRING
\tFile    : {$this->getFile()}
\tLine    : {$this->getLine()}
\tCode    : {$this->getCode()}
\tMessage : {$this->getMessage()}
\tData    : $e_data
\tDebug   : $e_debug
\tTrace   : {$this->getTraceAsString()}
STRING;

			return $e_msg;
		}
	}