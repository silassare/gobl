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

namespace Gobl\DBAL\Queries;

use Gobl\DBAL\Exceptions\DBALException;

/**
 * Class NamedToPositionalParams.
 *
 * Converts a named-parameter SQL statement (`:param_name` placeholders) into a
 * positional-parameter statement (`?` placeholders) for drivers such as SQLite
 * that do not support named parameters natively.
 *
 * Conversion happens eagerly in the constructor. The results are available via
 * {@see getNewQuery()} and {@see getNewParams()} / {@see getNewParamsTypes()}.
 *
 * **IN-list expansion:** when a named parameter's value is an array, the single
 * `?` placeholder is expanded to `?, ?, ?` (one `?` per element), allowing the
 * same class to handle IN-list bindings transparently.
 */
class NamedToPositionalParams
{
	private string $new_query;

	private array $new_params = [];

	private array $new_params_types = [];

	private array $tokens = [];

	/**
	 * NamedToPositionalParams constructor.
	 *
	 * The conversion (`:name` -> `?`) is performed immediately in the constructor.
	 * After construction, {@see getNewQuery()} and {@see getNewParams()} hold the
	 * positional equivalents.
	 *
	 * @param string $query        named-parameter SQL string
	 * @param array  $params       parameter name -> value map
	 * @param array  $params_types parameter name -> `\PDO::PARAM_*` type map
	 */
	public function __construct(
		protected string $query,
		protected array $params = [],
		protected array $params_types = []
	) {
		$this->new_query = (string) \preg_replace_callback('~:(\w+)~', [$this, 'replacer'], $query);
	}

	/**
	 * Returns the original query string.
	 *
	 * @return string
	 */
	public function getQuery(): string
	{
		return $this->query;
	}

	/**
	 * Returns the original parameters.
	 *
	 * @return array
	 */
	public function getParams(): array
	{
		return $this->params;
	}

	/**
	 * Returns the original parameters types.
	 *
	 * @return array
	 */
	public function getParamsTypes(): array
	{
		return $this->params_types;
	}

	/**
	 * Returns the new query string.
	 *
	 * @return null|string
	 */
	public function getNewQuery(): ?string
	{
		return $this->new_query;
	}

	/**
	 * Returns the new parameters.
	 *
	 * @return array
	 */
	public function getNewParams(): array
	{
		return $this->new_params;
	}

	/**
	 * Returns the new parameters types.
	 *
	 * @return array
	 */
	public function getNewParamsTypes(): array
	{
		return $this->new_params_types;
	}

	/**
	 * Returns the tokens list.
	 *
	 * @return array
	 */
	public function getTokens(): array
	{
		return $this->tokens;
	}

	/**
	 * `preg_replace_callback` handler: replaces a single `:param_name` token with one or more `?`.
	 *
	 * When the bound value is a scalar, a single `?` is emitted.
	 * When the bound value is an **array** (e.g. for an IN-list), the placeholder is expanded
	 * to `?, ?, ?` (one `?` per element) and all array values are appended to `$new_params`
	 * in order - enabling seamless IN-list support without extra pre-processing.
	 *
	 * @param array $matches regex matches: `[0 => ':token', 1 => 'token']`
	 *
	 * @return string one or more `?` placeholders
	 *
	 * @throws DBALException when the parameter is not present in `$params`
	 */
	private function replacer(array $matches): string
	{
		[$token, $key]  = $matches;
		$replacement    = '?';
		$this->tokens[] = $token;

		if (!isset($this->params[$key])) {
			throw new DBALException(\sprintf('Missing query token "%s" in parameters.', $key));
		}

		$value = $this->params[$key];

		if (\is_array($value)) {
			$replacement = \implode(', ', \array_fill(0, \count($value), '?'));
			$values      = \array_values($value);

			foreach ($values as $v) {
				$this->new_params[]       = $v;
				$this->new_params_types[] = QBUtils::paramType($v);
			}
		} else {
			$this->new_params[]       = $value;
			$this->new_params_types[] = $this->params_types[$key] ?? QBUtils::paramType($value);
		}

		return $replacement;
	}
}
