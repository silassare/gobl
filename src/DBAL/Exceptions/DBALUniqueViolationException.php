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

namespace Gobl\DBAL\Exceptions;

use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\UniqueKey;
use Gobl\DBAL\Table;
use PDOException;

/**
 * Class DBALUniqueViolationException.
 *
 * A write met a unique key (or the primary key) of a table: another row holds the same values. Raised
 * by the drivers ({@see self::of()}) in place of the database's own error, so a caller learns which table and which
 * columns, whatever the database. A check made before the write ("this email is not registered")
 * cannot replace it: two writes may both pass the check before either is done.
 */
final class DBALUniqueViolationException extends DBALException
{
	/** The key the write met. */
	private PrimaryKey|UniqueKey $constraint;

	/**
	 * The error of a write that met a key.
	 *
	 * @param PrimaryKey|UniqueKey $constraint the key the write met
	 * @param PDOException         $previous   the database's own error
	 */
	public static function of(PrimaryKey|UniqueKey $constraint, PDOException $previous): self
	{
		$e = new self('GOBL_UNIQUE_KEY_VIOLATION', [
			'table'      => $constraint->getHostTable()->getName(),
			'constraint' => $constraint->getName(),
			'columns'    => $constraint->getColumns(),
		], $previous);

		$e->constraint = $constraint;

		return $e;
	}

	/**
	 * The table the write met the key of.
	 */
	public function getTable(): Table
	{
		return $this->constraint->getHostTable();
	}

	/**
	 * The key the write met.
	 */
	public function getConstraint(): PrimaryKey|UniqueKey
	{
		return $this->constraint;
	}

	/**
	 * The full names of the key's columns.
	 *
	 * @return list<string>
	 */
	public function getColumns(): array
	{
		return $this->constraint->getColumns();
	}
}
