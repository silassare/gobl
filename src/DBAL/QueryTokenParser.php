<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	use Gobl\DBAL\Exceptions\DBALException;

	/**
	 * Class QueryTokenParser
	 *
	 * @package Gobl\DBAL
	 */
	class QueryTokenParser
	{
		private $query            = "";
		private $params           = [];
		private $params_types     = [];
		private $new_query        = null;
		private $new_params       = [];
		private $new_params_types = [];
		private $tokens           = [];

		/**
		 * QueryTokenParser constructor.
		 *
		 * @param string $query
		 * @param array  $params
		 * @param array  $params_types
		 */
		public function __construct($query, array $params = [], array $params_types = [])
		{
			$this->query        = $query;
			$this->params       = $params;
			$this->params_types = $params_types;
			$this->new_query    = preg_replace_callback("#:(\w+)#", [$this, 'replacer'], $this->query);
		}

		/**
		 * Returns the original query string
		 *
		 * @return string
		 */
		public function getQuery()
		{
			return $this->query;
		}

		/**
		 * Returns the original parameters
		 *
		 * @return array
		 */
		public function getParams()
		{
			return $this->params;
		}

		/**
		 * Returns the original parameters types
		 *
		 * @return array
		 */
		public function getParamsTypes()
		{
			return $this->params_types;
		}

		/**
		 * Returns the new query string
		 *
		 * @return string|null
		 */
		public function getNewQuery()
		{
			return $this->new_query;
		}

		/**
		 * Returns the new parameters
		 *
		 * @return array
		 */
		public function getNewParams()
		{
			return $this->new_params;
		}

		/**
		 * Returns the new parameters types
		 *
		 * @return array
		 */
		public function getNewParamsTypes()
		{
			return $this->new_params_types;
		}

		/**
		 * Returns the tokens list
		 *
		 * @return array
		 */
		public function getTokens()
		{
			return $this->tokens;
		}

		/**
		 * Internal replacer
		 *
		 * @param $matches
		 *
		 * @return string
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 *
		 */
		private function replacer($matches)
		{
			$this->tokens[] = $matches[0];
			$key            = $matches[1];
			$replacement    = '?';

			if (!isset($this->params[$key])) {
				throw new DBALException(sprintf('Missing query token "%s" in parameters.', $key));
			}

			$value = $this->params[$key];

			if (is_array($value)) {
				$replacement = implode(", ", array_fill(0, count($value), '?'));
				$values      = array_values($value);

				foreach ($values as $v) {
					$this->new_params[]       = $v;
					$this->new_params_types[] = self::paramType($v);
				}
			} else {
				$this->new_params[]       = $value;
				$type                     = (isset($params_types[$key])) ? $this->params_types[$key] : self::paramType($value);
				$this->new_params_types[] = $type;
			}

			return $replacement;
		}

		/**
		 * Returns PDO type for a given value
		 *
		 * @param mixed $value
		 *
		 * @return int
		 */
		public static function paramType($value)
		{
			$param_type = \PDO::PARAM_STR;

			if (is_int($value)) {
				$param_type = \PDO::PARAM_INT;
			} elseif (is_bool($value)) {
				$param_type = \PDO::PARAM_BOOL;
			} elseif (is_null($value)) {
				$param_type = \PDO::PARAM_NULL;
			}

			return $param_type;
		}
	}