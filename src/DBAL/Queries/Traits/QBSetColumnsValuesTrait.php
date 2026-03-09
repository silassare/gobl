<?php

/**
 * Copyright (c) Emile Silas Sare.
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
 * Trait QBSetColumnsValuesTrait.
 */
trait QBSetColumnsValuesTrait
{
	/**
	 * Binds column values for an INSERT or UPDATE statement.
	 *
	 * For each entry in `$values`:
	 * - `QBExpression` values are passed through as raw SQL fragments (no binding).
	 * - All other values are bound to uniquely generated named parameters and the
	 *   parameter map is merged into the query via `bindArray()` as a side effect.
	 *
	 * `TypeUtils::runEnforceQueryExpressionValueType()` is called on every column
	 * to coerce the PHP value/expression to the type expected by the column's type definition.
	 *
	 * When `$auto_prefix_column` is `true`, each column name is resolved to its full name
	 * (e.g. `user_name` -> `gobl_user_name`) using the registered table definition.
	 *
	 * @param string $table_name         the full table name (used for column resolution)
	 * @param array  $values             column -> PHP value or `QBExpression` map
	 * @param bool   $auto_prefix_column when `true`, short column names are resolved to full names
	 *
	 * @return array column_full_name -> SQL placeholder or raw expression map
	 */
	protected function bindColumnsValuesForInsertOrUpdate(string $table_name, array $values, bool $auto_prefix_column): array
	{
		$params  = [];
		$columns = [];

		if ($auto_prefix_column) {
			$table      = $this->db->getTableOrFail($table_name);
			$table_name = $table->getFullName();

			$tmp = [];
			foreach ($values as $column => $value) {
				$column = $table->getColumnOrFail($column)
					->getFullName();

				$tmp[$column] = $value;
			}

			$values = $tmp;
		}

		foreach ($values as $column => $value) {
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

		$this->bindArray($params);

		return $columns;
	}
}
