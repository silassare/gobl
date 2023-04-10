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
	 * Returns tables definitions.
	 *
	 * @return array
	 */
	public function getTables(): array;

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
}
