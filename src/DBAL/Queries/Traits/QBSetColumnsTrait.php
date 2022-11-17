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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Types\Utils\TypeUtils;

/**
 * Trait QBSetColumnsTrait.
 */
trait QBSetColumnsTrait
{
	protected array $options_columns = [];

	/**
	 * @return array
	 */
	public function getOptionsColumns(): array
	{
		return $this->options_columns;
	}

	/**
	 * @param string $table
	 * @param array  $columns_values_map
	 * @param bool   $auto_prefix
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function setInsertOrUpdateColumnsValues(string $table, array $columns_values_map, bool $auto_prefix): static
	{
		if ($auto_prefix) {
			$columns            = $this->prefixColumnsArray($table, \array_keys($columns_values_map));
			$columns_values_map = \array_combine($columns, \array_values($columns_values_map));
		}

		$params  = [];
		$columns = [];

		foreach ($columns_values_map as $column => $value) {
			if ($value instanceof QBExpression) {
				$value = (string) $value;
			} else {
				$param_key          = QBUtils::newParamKey();
				$params[$param_key] = $value;
				$value              = ':' . $param_key;
			}

			$columns[$column] = TypeUtils::runEnforceQueryExpressionValueType(
				$table,
				$column,
				$value,
				$this->db
			);
		}

		$this->options_columns = $columns;

		return $this->bindArray($params);
	}
}
