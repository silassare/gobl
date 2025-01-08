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
 * Trait QBSetColumnsValuesTrait.
 */
trait QBSetColumnsValuesTrait
{
	/**
	 * Bind columns values for insert or update.
	 *
	 * @param string $table_name         the table name
	 * @param array  $values             the column => value map
	 * @param bool   $auto_prefix_column if true, columns will be auto prefixed
	 *
	 * @return array
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
