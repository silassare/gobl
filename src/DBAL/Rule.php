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
	 * Class Rule
	 *
	 * @package Gobl\DBAL
	 */
	class Rule
	{
		const OP_EQ          = 1;
		const OP_NEQ         = 2;
		const OP_LT          = 3;
		const OP_LTE         = 4;
		const OP_GT          = 5;
		const OP_GTE         = 6;
		const OP_LIKE        = 7;
		const OP_NOT_LIKE    = 8;
		const OP_IS_NULL     = 9;
		const OP_IS_NOT_NULL = 10;
		const OP_IN          = 11;
		const OP_NOT_IN      = 12;

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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
				throw new DBALException('Ambiguous nested conditions.');
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		private function multiple($glue, array $list)
		{
			$len = count($list);

			if ($len > 1) {
				foreach ($list as $k => $r) {
					if ($r instanceof Rule) {
						$list[$k] = '(' . $r . ')';
					}
				}
			}

			$x = '(' . implode(' ' . $glue . ' ', $list) . ')';

			if (!$len AND !$this->unused_glue AND !empty($this->expr)) {
				$this->unused_glue = $glue;
			} elseif ($len === 1 AND !$this->unused_glue) {
				$this->expr = $this->getSelf($glue);

				if (!empty($this->expr)) {
					$this->expr .= ' ' . $glue . ' ';
				}

				$this->expr           .= $x;
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
				throw new DBALException('Ambiguous nested conditions.');
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
			if (is_string($a) AND strlen($a) > 2 AND $a[0] !== ':') {
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
		 * Adds a list of conditions.
		 *
		 * @param array $items    map left operand to right operand
		 * @param int   $operator the operator to use, Rule::OP_* constants
		 * @param bool  $use_and  whether to join multiple operations with 'and' or 'or'
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function conditions(array $items, $operator, $use_and = true)
		{
			$map = [
				Rule::OP_EQ          => ['=', true],
				Rule::OP_NEQ         => ['<>', true],
				Rule::OP_LT          => ['<', true],
				Rule::OP_LTE         => ['<=', true],
				Rule::OP_GT          => ['>', true],
				Rule::OP_GTE         => ['>=', true],
				Rule::OP_LIKE        => ['LIKE', true],
				Rule::OP_NOT_LIKE    => ['NOT LIKE', true],
				Rule::OP_IN          => ['IN', true],
				Rule::OP_NOT_IN      => ['NOT IN', true],
				Rule::OP_IS_NULL     => ['IS NULL', false],
				Rule::OP_IS_NOT_NULL => ['IS NOT NULL', false]
			];

			if (!isset($map[$operator])) {
				throw new DBALException('unknown operator used in query rule.');
			}

			$op           = $map[$operator][0];
			$two_operands = $map[$operator][1];
			$counter      = 0;

			if ($two_operands === true) {
				foreach ($items as $left => $right) {
					$left = $this->prefixOperand($left);
					if ($operator !== Rule::OP_IN AND $operator !== Rule::OP_NOT_IN) {
						$right = $this->prefixOperand($right);
					}

					if ($counter) {
						if ($use_and) {
							$this->andX();
						} else {
							$this->orX();
						}
					}

					$this->add($left . ' ' . $op . ' ' . $right);
					$counter++;
				}
			} else {
				foreach ($items as $item) {
					$item = $this->prefixOperand($item);
					if ($counter) {
						if ($use_and) {
							$this->andX();
						} else {
							$this->orX();
						}
					}
					$this->add($item . ' ' . $op);
					$counter++;
				}
			}

			return $this;
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

			return $this->conditions($items, Rule::OP_EQ);
		}

		/**
		 * Adds not equal condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function neq($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->conditions($items, Rule::OP_NEQ);
		}

		/**
		 * Adds like condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function like($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->conditions($items, Rule::OP_LIKE);
		}

		/**
		 * Adds not like condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function notLike($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->conditions($items, Rule::OP_NOT_LIKE);
		}

		/**
		 * Adds less than condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function lt($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->conditions($items, Rule::OP_LT);
		}

		/**
		 * Adds less than or equal condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function lte($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->conditions($items, Rule::OP_LTE);
		}

		/**
		 * Adds greater than condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function gt($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->conditions($items, Rule::OP_GT);
		}

		/**
		 * Adds greater than or equal condition.
		 *
		 * @param string|array $a
		 * @param string|null  $b
		 *
		 * @return $this
		 */
		public function gte($a, $b = null)
		{
			$items = is_array($a) ? $a : [$a => $b];

			return $this->conditions($items, Rule::OP_GTE);
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
			$items = is_array($a) ? $a : [$a];

			return $this->conditions($items, Rule::OP_IS_NULL);
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
			$items = is_array($a) ? $a : [$a];

			return $this->conditions($items, Rule::OP_IS_NOT_NULL);
		}

		/**
		 * Adds IN condition.
		 *
		 * @param string $a
		 * @param array  $b
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function in($a, array $b)
		{
			if (!is_string($a) OR !strlen($a)) {
				throw new DBALException('the first argument must be a non-empty string.');
			}

			if (!count($b)) {
				throw new DBALException('the second argument must be a non-empty array.');
			}

			$items = [$a => $this->qb->arrayToListItems($b)];

			return $this->conditions($items, Rule::OP_IN);
		}

		/**
		 * Adds NOT IN condition.
		 *
		 * @param string $a
		 * @param array  $b
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function notIn($a, array $b)
		{
			if (!is_string($a) OR !strlen($a)) {
				throw new DBALException('the left operand must be a non-empty string.');
			}

			if (!count($b)) {
				throw new DBALException('the right operand should be a non-empty array.');
			}

			$items = [$a => $this->qb->arrayToListItems($b)];

			return $this->conditions($items, Rule::OP_NOT_IN);
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