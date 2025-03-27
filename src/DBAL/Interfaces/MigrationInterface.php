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

namespace Gobl\DBAL\Interfaces;

use Gobl\DBAL\MigrationMode;

/**
 * Interface MigrationInterface.
 */
interface MigrationInterface
{
	/**
	 * Returns migration label.
	 *
	 * @return string
	 */
	public function getLabel(): string;

	/**
	 * Returns migration version.
	 *
	 * @return int
	 */
	public function getVersion(): int;

	/**
	 * Returns migration timestamp.
	 *
	 * @return int
	 */
	public function getTimestamp(): int;

	/**
	 * Returns migration schema.
	 *
	 * @return array
	 */
	public function getSchema(): array;

	/**
	 * Returns configs.
	 *
	 * @return array
	 */
	public function getConfigs(): array;

	/**
	 * Returns up query.
	 *
	 * @return string
	 */
	public function up(): string;

	/**
	 * Returns down query.
	 *
	 * @return string
	 */
	public function down(): string;

	/**
	 * Called before a migration runs.
	 *
	 * @param MigrationMode $mode
	 * @param string        $query the query to be run
	 *
	 * @return bool|string if the query should be run or not, or a new query to be run
	 */
	public function beforeRun(MigrationMode $mode, string $query): bool|string;

	/**
	 * Called after a migration runs.
	 *
	 * @param MigrationMode $mode
	 */
	public function afterRun(MigrationMode $mode): void;
}
