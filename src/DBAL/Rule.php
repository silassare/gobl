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

	/**
	 * Class Rule
	 *
	 * @package Gobl\DBAL
	 */
	class Rule
	{
		/** @var string */
		private $expr = '';

		/**
		 * The last unused glue (and,or).
		 *
		 * @var string|null
		 */
		private $unused_glue = null;

		/**
		 * The last used glue (and,or).
		 *
		 * @var string|null
		 */
		private $last_used_glue = null;

		/**
		 * The query builder.
		 *
		 * @var \Gobl\DBAL\QueryBuilder
		 */
		private $qb;

		/**
		 * Rule constructor.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $query
		 */
		public function __construct(QueryBuilder $query)
		{
			$this->qb = $query;
		}

		/**
		 * Adds rule part.
		 *
		 * @param string $part
		 *
		 * @return $this
		 * @throws \Exception
		 */
		private function add($part)
		{
			if ($this->unused_glue) {
				$this->expr           = $this->getSelf($this->unused_glue) . ' ' . $this->unused_glue . ' ' . $part;
				$this->last_used_glue = $this->unused_glue;
				$this->unused_glue    = null;
			} elseif (empty($this->expr)) {
				$this->expr = $part;
			} else {
				throw new \Exception('Ambiguous nested conditions.');
			}

			return $this;
		}

		/**
		 * Returns this rule expression to be used in another rule.
		 *
		 * The expression will be wrapped in parentheses when necessary.
		 *
		 * @param string $glue target glue
		 *
		 * @return string
		 */
		private function getSelf($glue)
		{
			if ($this->expr AND $this->last_used_glue AND $this->last_used_glue !== $glue) {
				return '(' . $this->expr . ')';
			}

			return $this->expr;
		}

		/**
		 * Join a list of condition with a given glue.
		 *
		 * @param string $glue
		 * @param array  $list
		 *
		 * @return $this
		 * @throws \Exception
		 */
		private function multiple($glue, array $list)
		{
			$len = count($list);
			$x   = '(' . implode(' ' . $glue . ' ', $list) . ')';

			if (!$len AND !$this->unused_glue AND !empty($this->expr)) {
				$this->unused_glue = $glue;
			} elseif ($len === 1 AND !$this->unused_glue) {
				$this->expr           = $this->getSelf($glue) . ' ' . $glue . ' ' . $x;
				$this->last_used_glue = $glue;
			} elseif ($len > 1) {
				if ($this->unused_glue) {
					$this->expr           = $this->getSelf($this->unused_glue) . ' ' . $this->unused_glue . ' ' . $x;
					$this->last_used_glue = $this->unused_glue;
					$this->unused_glue    = null;
				} else {
					if (!empty($this->expr)) {
						$this->expr = $this->getSelf($glue) . ' ' . $glue . ' ';
					}
					$this->expr           .= $x;
					$this->last_used_glue = $glue;
				}
			} else {
				throw new \Exception('Ambiguous nested conditions.');
			}

			return $this;
		}

		/**
		 * Prefix operand.
		 *
		 * @param mixed $a
		 *
		 * @return string
		 */
		private function prefixOperand($a)
		{
			if (is_string($a) AND strlen($a) > 2) {
				$parts = explode('.', $a);
				if (count($parts) === 2 AND !empty($parts[0]) AND !empty($parts[1])) {
					try {
						$table = $this->qb->prefixTable($parts[0]);
						$a     = $this->qb->prefix($table, $parts[1]);
					} catch (\Exception $e) {
						return $a;
					}
				}
			}

			return $a;
		}

		/**
		 * Join a list of operation.
		 *
		 * @param array  $list     map left operand to right operand
		 * @param string $operator the operator
		 * @param bool   $use_and  whether to join multiple operations with 'and' or 'or'
		 *
		 * @return $this
		 */
		private function operator($list, $operator, $use_and = true)
		{
			$c = 0;
			foreach ($list as $left => $right) {
				$left  = $this->prefixOperand($left);
				$right = $this->prefixOperand($right);
				if ($c) {
					if ($use_and) {
						$this->andX();
					} else {
						$this->orX();
					}
				}
				$this->add($left . $operator . $right);
				$c++;
			}

			return $this;
		}

		/**
		 * List items to be used in condition(IN and NOT IN).
		 *
		 * @param array $items
		 *
		 * @return string
		 */
		private function listItems(array $items)
		{
			$list  = [];
			$items = array_unique($items);

			foreach ($items as $item) {
				$list = $this->qb->quote($item);
			}

			return '(' . implode(', ', $list) . ')';
		}

		/**
		 * Adds AND condition.
		 *
		 * @return $this
		 */
		public function andX()
		{
			return $this->multiple('AND', func_get_args());
		}

		/**
		 * Adds OR condition.
		 *
		 * @return $this
		 */
		public function orX()
		{
			return $this->multiple('OR', func_get_args());
		}

		/**
		 * Adds equal condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function eq($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' = ');
		}

		/**
		 * Adds not equal condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function neq($a, $b)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' <> ');
		}

		/**
		 * Adds like condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function like($a, $b)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' LIKE ');
		}

		/**
		 * Adds not like condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function notLike($a, $b)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' NOT LIKE ');
		}

		/**
		 * Adds less than condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function lt($a, $b)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' < ');
		}

		/**
		 * Adds less than or equal condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function lte($a, $b)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' <= ');
		}

		/**
		 * Adds greater than condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function gt($a, $b)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' > ');
		}

		/**
		 * Adds greater than or equal condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function gte($a, $b)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->operator($items, ' >= ');
		}

		/**
		 * Adds IS NULL condition.
		 *
		 * @param string $a
		 *
		 * @return $this
		 */
		public function isNull($a)
		{
			$a = $this->prefixOperand($a);

			return $this->add($a . ' IS NULL');
		}

		/**
		 * Adds IS NOT NULL condition.
		 *
		 * @param string $a
		 *
		 * @return $this
		 */
		public function isNotNull($a)
		{
			$a = $this->prefixOperand($a);

			return $this->add($a . ' IS NOT NULL');
		}

		/**
		 * Adds IN condition.
		 *
		 * @param string $a
		 * @param array  $b
		 *
		 * @return $this
		 */
		public function in($a, array $b)
		{
			$a = $this->prefixOperand($a);

			return $this->add($a . ' IN ' . $this->listItems($b));
		}

		/**
		 * Adds NOT IN condition.
		 *
		 * @param string $a
		 * @param array  $b
		 *
		 * @return $this
		 */
		public function notIn($a, array $b)
		{
			$a = $this->prefixOperand($a);

			return $this->add($a . ' NOT IN ' . $this->listItems($b));
		}

		/**
		 * Simply returns rule expression.
		 *
		 * @return string
		 */
		public function __toString()
		{
			return $this->expr;
		}
	}