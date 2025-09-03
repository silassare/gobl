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

namespace Gobl;

use Closure;
use Gobl\Exceptions\GoblRuntimeException;

/**
 * Class QueriesLogger.
 */
class QueriesLogger
{
	/**
	 * @var array<int, array{
	 *     query: string,
	 *     params: array<int, mixed>,
	 *     params_types: array<int, mixed>,
	 *     start:float,
	 *     executed?:float,
	 *     fetched?:float,
	 *     end?:float,
	 *     duration?: float
	 * }>
	 */
	private array $queries = [];

	private bool $enabled = false;

	/**
	 * QueriesLogger constructor.
	 */
	protected function __construct() {}

	/**
	 * Creates a new instance of {@see QueriesLogger}.
	 *
	 * @return self
	 */
	public static function get(): self
	{
		return new self();
	}

	/**
	 * Enables or disables the logger.
	 *
	 * @param bool $enabled
	 *
	 * @return $this
	 */
	public function enable(bool $enabled = true): self
	{
		$this->reset();
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * Checks if the logger is enabled.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	/**
	 * Gets the logged queries.
	 *
	 * @return array<int, array{
	 *      query: string,
	 *      params: array<int, mixed>,
	 *      params_types: array<int, mixed>,
	 *      start:float,
	 *      executed?:float,
	 *      fetched?:float,
	 *      end?:float,
	 *      duration?: float
	 *}>
	 */
	public function getLogs(): array
	{
		return $this->queries;
	}

	/**
	 * Resets the logger.
	 */
	public function reset(): static
	{
		$this->queries = [];

		return $this;
	}

	/**
	 * Marks the start of a query.
	 *
	 * @param string     $query
	 * @param null|array $params
	 * @param null|array $params_types
	 *
	 * @return Closure('end'|'executed'|'fetched' $step): QueriesLogger A closure to mark the end of the query or a specific step (prepared, fetched)
	 */
	public function start(string $query, ?array $params = [], ?array $params_types = []): Closure
	{
		if ($this->enabled) {
			$i               = \count($this->queries);
			$this->queries[] = [
				'query'        => $query,
				'params'       => $params,
				'params_types' => $params_types,
				'start'        => \microtime(true),
			];

			return fn (string $step = 'end') => $this->update($i, $step);
		}

		return fn (string $step = 'end') => $this;
	}

	/**
	 * Updates a query log entry with a specific step.
	 *
	 * @param int                        $index
	 * @param 'end'|'executed'|'fetched' $step
	 *
	 * @return $this
	 */
	private function update(int $index, string $step = 'end'): static
	{
		if ($this->enabled) {
			$t = \microtime(true);

			if (isset($this->queries[$index][$step])) {
				throw new GoblRuntimeException(\sprintf('The step "%s" completion time has already been set for the query.', $step), [
					'_query' => $this->queries[$index],
				]);
			}

			$this->queries[$index][$step] = $t;

			if ('end' === $step) {
				$this->queries[$index]['duration'] = $t - $this->queries[$index]['start'];
			}
		}

		return $this;
	}
}
