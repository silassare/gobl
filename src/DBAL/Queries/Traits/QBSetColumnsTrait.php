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
	 * @param string $table_name
	 * @param array  $columns_values_map
	 * @param bool   $auto_prefix_column
	 *
	 * @return $this
	 */
	protected function setInsertOrUpdateColumnsValues(string $table_name, array $columns_values_map, bool $auto_prefix_column): static
	{
		$params  = [];
		$columns = [];

		if ($auto_prefix_column) {
			$table      = $this->db->getTableOrFail($table_name);
			$table_name = $table->getFullName();

			$tmp = [];
			foreach ($columns_values_map as $column => $value) {
				$column = $table->getColumnOrFail($column)
					->getFullName();

				$tmp[$column] = $value;
			}

			$columns_values_map = $tmp;
		}

		foreach ($columns_values_map as $column => $value) {
			if ($value instanceof QBExpression) {
				$value = (string) $value;
			} else {
				$param_key          = QBUtils::newParamKey();
				$params[$param_key] = $value;
				$value              = ':' . $param_key;
			}

			$columns[$column] = TypeUtils::runEnforceQueryExpressionValueType(
				$table_name,
				$column,
				$value,
				$this->db
			);
		}

		$this->options_columns = $columns;

		return $this->bindArray($params);
	}
}
