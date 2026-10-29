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

namespace Gobl\DBAL\Events;

use PHPUtils\Events\Event;

/**
 * Class JsonPatchNotExpressible.
 *
 * An UPDATE could not send a JSON column's patch as the paths it changed, so it sent the whole
 * document: every key as the request that wrote it read them. A key another request wrote meanwhile
 * is undone by whichever of the two answers last.
 *
 * That is a lost update, and the one event in the patch mechanism that must never pass unnoticed:
 * it is silent in the data and visible only here. A host listens and logs it. A column that may not
 * take the risk is declared patch-only ({@see \Gobl\DBAL\Types\TypeJson::patchOnly()}) and raises
 * instead.
 */
final class JsonPatchNotExpressible extends Event
{
	/**
	 * JsonPatchNotExpressible constructor.
	 *
	 * @param string       $table_name  the table holding the column
	 * @param string       $column_name the column's full name
	 * @param string       $driver      the RDBMS the expression was being built for
	 * @param string       $reason      why the operations could not be expressed as paths
	 * @param list<string> $paths       the operations, as `op path` lines
	 */
	public function __construct(
		public readonly string $table_name,
		public readonly string $column_name,
		public readonly string $driver,
		public readonly string $reason,
		public readonly array $paths
	) {}

	/**
	 * The event as one line, for a log that takes a message.
	 */
	public function message(): string
	{
		return \sprintf(
			'The patch of "%s"."%s" was sent as the whole document on %s because %s. Paths: %s.',
			$this->table_name,
			$this->column_name,
			$this->driver,
			$this->reason,
			[] === $this->paths ? 'none' : \implode(', ', $this->paths)
		);
	}
}
