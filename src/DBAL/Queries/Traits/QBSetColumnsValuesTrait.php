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

use Gobl\DBAL\Column;
use Gobl\DBAL\Events\JsonPatchNotExpressible;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBType;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeJson;
use Gobl\DBAL\Types\Utils\JsonPatch;
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
	 * - `JsonPatch` values become a path expression in an UPDATE, so that the database applies them
	 *   to the row it holds rather than being handed a document read before another transaction
	 *   wrote to it. A patch that cannot be expressed as paths, and any patch in an INSERT, is
	 *   written as its whole document.
	 * - `QBExpression` values are passed through as raw SQL fragments (no binding).
	 * - All other values are bound to uniquely generated named parameters and the
	 *   parameter map is merged into the query via `bindArray()` as a side effect.
	 *
	 * {@see TypeUtils::runCastExpressionForQuery()} is called on every column
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
		$table   = $this->db->getTableOrFail($table_name);

		if ($auto_prefix_column) {
			$table_name = $table->getFullName();

			$tmp = [];
			foreach ($values as $column => $value) {
				$column = $table->getColumnOrFail($column)
					->getFullName();

				$tmp[$column] = $value;
			}

			$values = $tmp;
		}

		foreach ($values as $column_full_name => $value) {
			$col = $table->getColumnOrFail($column_full_name);

			if (
				QBType::UPDATE === $this->getType()
				&& !$value instanceof JsonPatch
				&& !$value instanceof QBExpression
				&& self::isPatchOnlyColumn($col)
			) {
				// A whole value is every key as this caller read them, so it undoes whatever another
				// request wrote meanwhile. A column declared patch-only is written by its paths or
				// not at all; an INSERT is the document either way, since there is no row yet.
				throw new DBALRuntimeException(\sprintf(
					'The column "%s"."%s" is declared patch-only and cannot be written whole by an'
					. ' UPDATE: write it through a JsonPatch, so the paths reach the row.',
					$table->getFullName(),
					$col->getFullName()
				), [
					'_table'  => $table->getFullName(),
					'_column' => $col->getFullName(),
				]);
			}

			if ($value instanceof JsonPatch) {
				if (QBType::UPDATE === $this->getType()) {
					$patch_params = [];
					$expression   = $this->db->getGenerator()
						->getJsonPatchUpdateExpression(
							$col,
							$column_full_name,
							$value,
							static function (mixed $patch_value) use (&$patch_params): string {
								$param_key                = QBUtils::newParamKey();
								$patch_params[$param_key] = $patch_value;

								return ':' . $param_key;
							}
						);

					if ($expression->isExpressible()) {
						// The expression is already of the column's type, so it skips the cast a
						// value goes through.
						$params                     = \array_merge($params, $patch_params);
						$columns[$column_full_name] = $expression->sql;

						continue;
					}

					// Writing the document gives back the lost update the paths exist to prevent, so
					// it is reported, and refused on a column that may not take it.
					$this->reportJsonPatchFallback($table, $col, $value, (string) $expression->reason);
				}

				// An INSERT gets the document: there is no row to patch yet. The conversion the
				// patch was spared on its way here (see Table::doPhpToDbConversion()) happens now,
				// since what goes out is a value and no longer a set of paths.
				$value = $col->getType()
					->phpToDb($value->toMap(), $this->db);
			}

			if ($value instanceof QBExpression) {
				$value = (string) $value;
			} else {
				$param_key          = QBUtils::newParamKey();
				$params[$param_key] = $value;
				$value              = ':' . $param_key;
			}

			$columns[$column_full_name] = TypeUtils::runCastExpressionForQuery($col, $value, $this->db);
		}

		$this->bindArray($params);

		return $columns;
	}

	/**
	 * Says that a patch went out as the whole document, and refuses it when the column may not.
	 *
	 * @throws DBALRuntimeException when the column is declared patch-only
	 */
	private function reportJsonPatchFallback(Table $table, Column $col, JsonPatch $patch, string $reason): void
	{
		$paths = [];

		foreach ($patch->getOperations() as $operation) {
			$path = '';

			foreach ($operation['path'] as $segment) {
				$path .= \is_int($segment) ? '[' . $segment . ']' : ('' === $path ? '' : '.') . $segment;
			}

			$paths[] = $operation['op'] . ' ' . $path;
		}

		$event = new JsonPatchNotExpressible(
			$table->getFullName(),
			$col->getFullName(),
			$this->db->getType(),
			$reason,
			$paths
		);

		if (self::isPatchOnlyColumn($col)) {
			throw new DBALRuntimeException($event->message(), [
				'_table'  => $event->table_name,
				'_column' => $event->column_name,
				'_reason' => $event->reason,
				'_paths'  => $event->paths,
			]);
		}

		$event->dispatch();
	}

	/**
	 * Whether the column may only be written by a patch's paths.
	 */
	private static function isPatchOnlyColumn(Column $col): bool
	{
		$base = $col->getType()->getBaseType();

		return $base instanceof TypeJson && $base->isPatchOnly();
	}
}
