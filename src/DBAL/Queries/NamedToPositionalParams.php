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
	 * @param string $query
	 * @param array  $params
	 * @param array  $params_types
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
	 * Internal replacer.
	 *
	 * @param array $matches
	 *
	 * @return string
	 *
	 * @throws DBALException
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
