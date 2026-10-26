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

namespace Gobl\Tests\Fixtures;

use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMEntityCRUD;
use Gobl\ORM\ORMResults;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\Utils\ORMClassKind;
use Override;

/**
 * An entity class in the shape the generator produced before a column became a typed property: the
 * columns are `@property` lines on the docblock and nothing else, so every read and write goes
 * through the magic accessors.
 *
 * A project that has not regenerated its classes yet runs on exactly this. Hand-written, so the
 * shape stays pinned once the generator stops producing it.
 *
 * @property null|string $id     Getter for column `hooked.id`.
 * @property string      $label  Getter for column `hooked.label`.
 * @property null|string $note   Getter for column `hooked.note`.
 * @property bool        $active Getter for column `hooked.active`.
 */
final class LegacyShapedEntity extends ORMEntity
{
	public const string TABLE_NAMESPACE = 'Gobl\Tests\HooksDb';

	public const string TABLE_NAME = 'hooked';

	public function __construct(bool $is_new = true, bool $strict = true)
	{
		parent::__construct(self::TABLE_NAMESPACE, self::TABLE_NAME, $is_new, $strict);
	}

	#[Override]
	public static function new(bool $is_new = true, bool $strict = true): static
	{
		return new self($is_new, $strict);
	}

	#[Override]
	public static function table(): Table
	{
		return ORM::table(self::TABLE_NAMESPACE, self::TABLE_NAME);
	}

	#[Override]
	public static function qb(): ORMTableQuery
	{
		return ORM::query(self::table());
	}

	#[Override]
	public static function results(QBSelect $query): ORMResults
	{
		return ORM::results(self::table(), $query);
	}

	#[Override]
	public static function crud(): ORMEntityCRUD
	{
		/** @var ORMEntityCRUD $class */
		$class = ORMClassKind::CRUD->getClassFQN(self::table());

		return $class::new();
	}

	#[Override]
	public static function ctrl(): ORMController
	{
		return ORM::ctrl(self::table());
	}
}
