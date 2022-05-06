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

use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Interfaces\TypeInterface;

/**
 * Enum ORMTypeHint.
 */
enum ORMTypeHint: string
{
	case ARRAY = 'ARRAY';

	case MAP = 'MAP';

	case BIGINT = 'BIGINT';

	case BOOL = 'BOOL';

	case DECIMAL = 'DECIMAL';

	case FLOAT = 'FLOAT';

	case INT = 'INT';

	case STRING = 'STRING';

	case _NULL = 'NULL';

	case MIXED = 'MIXED';

	/**
	 * @param \Gobl\DBAL\Types\Interfaces\TypeInterface $type
	 * @param \Gobl\DBAL\Operator                       $operator
	 *
	 * @return \Gobl\ORM\Utils\ORMTypeHint[]
	 */
	public static function getRightOperandTypesHint(TypeInterface $type, Operator $operator): array
	{
		return match ($operator) {
			Operator::EQ, Operator::NEQ, Operator::LT, Operator::LTE, Operator::GT, Operator::GTE => $type->getWriteTypeHint(),
			Operator::LIKE, Operator::NOT_LIKE => [self::STRING],
			Operator::IS_NULL, Operator::IS_NOT_NULL => [self::_NULL],
			Operator::IN, Operator::NOT_IN => [self::ARRAY],
			Operator::IS_TRUE, Operator::IS_FALSE => [self::BOOL],
		};
	}
}
