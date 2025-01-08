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

namespace Gobl\ORM\Utils;

use Gobl\DBAL\Table;
use PHPUtils\Str;

/**
 * Enum ORMClassKind.
 */
enum ORMClassKind: string
{
	case ENTITY = 'entity';

	case QUERY = 'query';

	case RESULTS = 'results';

	case CONTROLLER = 'controller';

	case CRUD = 'crud';

	case BASE_ENTITY = 'base.entity';

	case BASE_CRUD = 'base.crud';

	case BASE_QUERY = 'base.query';

	case BASE_RESULTS = 'base.results';

	case BASE_CONTROLLER = 'base.controller';

	/**
	 * Returns FQN class name for a given table.
	 *
	 * @param Table $table
	 * @param bool  $use
	 *
	 * @return string
	 */
	public function getClassFQN(Table $table, bool $use = false): string
	{
		$fqn = '\\' . $table->getNamespace() . '\\' . ($this->isBaseClass() ? 'Base\\' : '') . $this->getClassName(
			$table
		);

		return $use ? \ltrim($fqn, '\\') : $fqn;
	}

	/**
	 * Checks if this class kind is base class.
	 *
	 * @return bool
	 */
	public function isBaseClass(): bool
	{
		return \str_starts_with($this->value, 'base');
	}

	/**
	 * Returns class name for a given table.
	 *
	 * @param Table $table
	 *
	 * @return string
	 */
	public function getClassName(Table $table): string
	{
		return match ($this) {
			self::ENTITY, self::BASE_ENTITY         => Str::toClassName($table->getSingularName()),
			self::QUERY, self::BASE_QUERY           => Str::toClassName($table->getPluralName() . '_query'),
			self::CONTROLLER, self::BASE_CONTROLLER => Str::toClassName($table->getPluralName() . '_controller'),
			self::CRUD, self::BASE_CRUD             => Str::toClassName($table->getPluralName() . '_crud'),
			self::RESULTS, self::BASE_RESULTS       => Str::toClassName($table->getPluralName() . '_results')
		};
	}
}
