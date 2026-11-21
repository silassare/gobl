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

namespace Gobl\ORM\Utils;

use Gobl\DBAL\Relations\Relation;
use PHPUtils\Str;

/**
 * Where the ORM generator writes the candidates context of a relation, and what it names it.
 *
 * One class per relation that names a provider -- `AccountManagerCandidates` for an account's
 * `manager` -- in the host's namespace, under `Base\Candidates`: generated and not edited, as every
 * class under `Base` is.
 */
final class ORMCandidatesClass
{
	/** The sub-namespace, under the host's `Base`. */
	public const string SUB_NAMESPACE = 'Candidates';

	/**
	 * The class name: the host's singular name, the relation's, and `Candidates`.
	 */
	public static function name(Relation $relation): string
	{
		return Str::toClassName($relation->getHostTable()->getSingularName() . '_' . $relation->getName() . '_candidates');
	}

	/**
	 * The fully qualified name, with its leading backslash.
	 */
	public static function fqn(Relation $relation): string
	{
		return '\\' . $relation->getHostTable()->getNamespace() . '\Base\\' . self::SUB_NAMESPACE . '\\' . self::name($relation);
	}
}
