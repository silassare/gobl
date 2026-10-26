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

namespace Gobl\DBAL\Interfaces;

use Gobl\DBAL\MigrationMode;

/**
 * Interface MigrationInterface.
 */
interface MigrationInterface
{
	/**
	 * Returns migration label.
	 */
	public function getLabel(): string;

	/**
	 * Returns migration version.
	 */
	public function getVersion(): int;

	/**
	 * Returns migration timestamp.
	 */
	public function getTimestamp(): int;

	/**
	 * Returns migration schema.
	 */
	public function getSchema(): array;

	/**
	 * Returns configs.
	 */
	public function getConfigs(): array;

	/**
	 * Returns up query.
	 */
	public function up(): string;

	/**
	 * Returns down query.
	 */
	public function down(): string;

	/**
	 * Called before a migration query is run.
	 *
	 * @param string $query the query to be run
	 *
	 * @return bool|string if the query should be run or not, or a new query to be run
	 */
	public function beforeRun(MigrationMode $mode, string $query): bool|string;

	/**
	 * Called after a migration query is run.
	 */
	public function afterRun(MigrationMode $mode): void;
}
