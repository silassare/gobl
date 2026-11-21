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

namespace Gobl\ORM;

use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Relations\LinkColumns;
use Gobl\DBAL\Relations\LinkMorph;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeJson;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use InvalidArgumentException;
use PHPUtils\PortablePattern;

/**
 * What a table holds, as a shape: the declaration a client checks a value against, generates a form
 * from, and wires one step of a graph to another with.
 *
 * A shape is JSON, so this answers arrays and nothing else: what reads them is a TypeScript
 * package, a template, a seeder and whatever comes next, and a value object in the middle would
 * only have to be unwrapped by each of them.
 *
 * **Two shapes, not one.** Reading a row and writing one are not the same contract: a saved row
 * carries every column it was asked for, while creating one may leave out whatever the database or
 * the type fills in, and an auto-incremented column is nothing until it is saved. So a table
 * answers a read shape and a write shape, and they differ exactly where the generated entity's own
 * accessors differ ({@see Generators\CSGenerator::getReadTypeHintString()} and its write twin).
 * The read one describes a **full** row: a projection that selected three columns is a subset of
 * it, which is a different and narrower shape. The one place the two hold different **columns** and
 * not different rules about the same ones is a sensitive column: reading it answers the redacted
 * value its declaration names, so the read half leaves it out, while writing it is ordinary.
 *
 * **Keyed as a row is keyed.** The properties are the prefixed column names (`account_label`),
 * because a REST answer, a form submission and a definition's `$record` all carry them that way.
 * The unprefixed name belongs to the generated entity class's accessors and appears nowhere on the
 * wire; the prefix travels beside the shape for whoever wants the short name.
 *
 * **The relations travel beside the shapes, never inside them.** A row carries no relation until a
 * caller asks for one, and what may be asked for is the table's list: each name, the morph type it
 * points at, and whether it is one row or many. That is what lets a reader of a reference read the
 * record it points at -- `$record.event.event_title` rather than an id and a hope, by the prefixed
 * name that record really carries -- and what lets one
 * that asks for a to-many relation beside the row be told it cannot have it. Where a column carries
 * the relation, it says which: a form then knows a foreign column is a reference to pick, from the
 * foreign key and with nothing declared again.
 *
 * **A type this does not know is mapped by what it is stored as.** A project declares its own
 * column types (`email`, `phone`, `password`, ..., and a project may add more), and
 * every one of them has a base type among the seven {@see Types\Utils\TypeUtils::getBaseTypes()}
 * allows -- which is already checked when the type is built, and which already carries the
 * constraints, since that is what validates the value. So there is nothing to register and no
 * second list to keep: the base answers the shape, and the type's own name travels beside it, for
 * the form control and the seed provider that know what an `email` is.
 */
final class ORMShape
{
	/** Reading a row: every column it holds, as the API answers them. */
	public const string SIDE_READ = 'read';

	/** Writing one: what creating or updating it takes. */
	public const string SIDE_WRITE = 'write';

	/**
	 * The table meta naming the column whose value stands for a record: what a picker shows.
	 *
	 * Read where it is declared and left out where it is not, rather than guessed from a column
	 * called `name` or `label`: a guess that is wrong shows a list of the wrong thing, and a table
	 * whose records have no one readable column is ordinary.
	 */
	public const string META_LABEL = 'shape.label';

	/** The column meta a prop's prose comes from, which is where a schema's documentation lives. */
	public const string META_DOC = 'api.doc.description';

	/**
	 * Everything a client needs of one table: its name, what identifies a record, and the two shapes.
	 *
	 * @param Table $table            the table
	 * @param bool  $ignore_private   skip private columns, as the generators do by default
	 * @param bool  $ignore_sensitive skip sensitive ones on **both** halves; a sensitive column is
	 *                                left out of the read half either way, since reading one
	 *                                answers its redacted value and not what it holds
	 *
	 * @return array{
	 *     name: string,
	 *     table: string,
	 *     prefix: string,
	 *     key: list<string>,
	 *     label: null|string,
	 *     types: array<string, string>,
	 *     relations: array<string, array{
	 *         target: string,
	 *         multiple: bool,
	 *         columns?: array<string, string>,
	 *         morph?: array{type_column: string, parent_type: string}
	 *     }>,
	 *     read: array<string, mixed>,
	 *     write: array<string, mixed>,
	 * }
	 */
	public static function ofTable(
		Table $table,
		bool $ignore_private = true,
		bool $ignore_sensitive = false
	): array {
		$columns = [];

		foreach ($table->getColumns() as $column) {
			if ($ignore_private && $column->isPrivate()) {
				continue;
			}

			if ($ignore_sensitive && $column->isSensitive()) {
				continue;
			}

			$columns[] = $column;
		}

		// The column a record is shown as, by the same name a row carries it under, so that whoever
		// draws a picker reads it off a row without knowing the prefix. The meta names the column as
		// the schema declares it, which is the unprefixed name a developer writes.
		$label = $table->getMeta()
			->get(self::META_LABEL);
		$label = \is_string($label) && $table->hasColumn($label)
			? $table->getColumnOrFail($label)
				->getFullName()
			: null;

		$types = [];

		foreach ($columns as $column) {
			$types[$column->getFullName()] = $column->getType()
				->getName();
		}

		// The prefix every column of the table shares, carried beside the shape for whoever wants
		// the short name of a property. Read off a column, as the generator reads it: the table's
		// own prefix is the one on its name, which is a different thing.
		$first = $columns[0] ?? null;

		return [
			// The morph type, which gobl keeps unique across the database (it refuses one another
			// table uses, and one that collides with a table's name), and already looks up.
			'name'      => $table->getMorphType(),
			'table'     => $table->getName(),
			'prefix'    => null === $first ? '' : $first->getPrefix(),
			'key'       => self::keyOf($table),
			'label'     => $label,
			'types'     => $types,
			'relations' => self::relationsOf($table, $ignore_private),
			'read'      => self::objectOf($columns, self::SIDE_READ),
			'write'     => self::objectOf($columns, self::SIDE_WRITE),
		];
	}

	/**
	 * One column's shape, for one half of its contract.
	 *
	 * @return array<string, mixed>
	 */
	public static function ofColumn(Column $column, string $side): array
	{
		$type  = $column->getType();
		$shape = self::ofType($type, $side);

		// An auto-incremented column is given on write or left to the database, and a saved row
		// has it: the same rule the generated accessors are written with.
		if ($type->isNullable() || (self::SIDE_WRITE === $side && $type->isAutoIncremented())) {
			$shape['nullable'] = true;
		}

		$doc = $column->getMeta()
			->get(self::META_DOC);

		if (\is_string($doc) && '' !== $doc) {
			$shape['doc'] = $doc;
		}

		return $shape;
	}

	/**
	 * What a value of a type is, without what only a column knows (nullable, its prose).
	 *
	 * @return array<string, mixed>
	 */
	public static function ofType(TypeInterface $type, string $side): array
	{
		switch ($type->getName()) {
			case TypeString::NAME:
				return self::ofString($type);

			case TypeBigint::NAME:
				return self::ofExact($type, 0);

			case TypeDecimal::NAME:
				$scale = $type->getOption('scale');

				return self::ofExact($type, \is_int($scale) ? $scale : null);

			case TypeInt::NAME:
				return self::ofNumber($type, true);

			case TypeFloat::NAME:
				return self::ofNumber($type, false);

			case TypeBool::NAME:
				return ['t' => 'boolean'];

			case TypeDate::NAME:
				return self::ofDate($type);

			case TypeEnum::NAME:
				return self::ofEnum($type);

			case TypeList::NAME:
				return [
					't'     => 'array',
					'items' => self::ofUniversal(
						$type instanceof TypeList ? $type->getListOfUniversalType() : null
					),
				];

			case TypeMap::NAME:
				return [
					't'      => 'record',
					'values' => self::ofUniversal(
						$type instanceof TypeMap ? $type->getMapOfUniversalType() : null
					),
				];

			case TypeJson::NAME:
				return self::ofUniversal(
					$type instanceof TypeJson && null === $type->getJsonOfClass()
						? $type->getJsonOfUniversalType()
						: null
				);

			default:
				break;
		}

		$base = $type->getBaseType();

		// A type whose base is itself is one of gobl's own, and every one of those is named above:
		// so this is a base type a project subclassed, and what it is stored as is all that is
		// known about it here.
		return $base === $type ? ['t' => 'json'] : self::ofType($base, $side);
	}

	/**
	 * The columns of a primary key, by the name a row carries them under, or none.
	 *
	 * @return list<string>
	 */
	private static function keyOf(Table $table): array
	{
		if (!$table->hasPrimaryKeyConstraint()) {
			return [];
		}

		/** @var PrimaryKey $pk */
		$pk   = $table->getPrimaryKeyConstraint();
		$list = [];

		foreach ($pk->getColumns() as $name) {
			$list[] = $table->getColumnOrFail($name)
				->getFullName();
		}

		return $list;
	}

	/**
	 * The relations of a table, by name: what each points at, and whether it is one row or many.
	 *
	 * Beside the shape and not in it, because a relation is not a column: a row carries none of
	 * them until a caller asks for it, and what a caller may ask for is exactly this list. The
	 * target is the **morph type**, so a reader joins it to another table's shape with the one word
	 * that is unique across the database.
	 *
	 * `multiple` is what decides whether a relation can be read with the row at all: a to-many one
	 * is paginated and has its own endpoint, so whoever asks for it beside the row is asking for
	 * something no answer can carry. A private relation is left out, as a private column is, since
	 * asking for one is refused.
	 *
	 * Virtual relations are left out too. What one answers is a method of the project's own, so
	 * nothing here can say its shape, and listing it would invite a reader to type what it cannot.
	 *
	 * `columns` is which column carries the relation, this table's to the target's, by the prefixed
	 * names rows carry: what tells a form that `account_client_id` **is** a client, read off the
	 * foreign key the relation was built from rather than declared a second time. A morph relation
	 * carries it too, with `morph` beside it: the column holding the type and what it holds for this
	 * target, so the morph relations over one pair of columns are the list of what it may point at.
	 * A relation through another table or a join has neither, since no column of either side holds
	 * it.
	 *
	 * @return array<string, array{
	 *     target: string,
	 *     multiple: bool,
	 *     columns?: array<string, string>,
	 *     morph?: array{type_column: string, parent_type: string}
	 * }>
	 */
	private static function relationsOf(Table $table, bool $ignore_private): array
	{
		$relations = [];

		foreach ($table->getRelations(!$ignore_private) as $relation) {
			$described = [
				'target'   => $relation->getTargetTable()
					->getMorphType(),
				'multiple' => $relation->isPaginated(),
			];

			$link = $relation->getLink();

			if ($link instanceof LinkColumns) {
				$described['columns'] = $link->getColumnsMapping();
			} elseif ($link instanceof LinkMorph) {
				$described += self::morphOf($link);
			}

			$relations[$relation->getName()] = $described;
		}

		return $relations;
	}

	/**
	 * A morph link's columns, as a columns link writes its mapping, and what makes it a morph one.
	 *
	 * The link keeps its columns by the names a schema declares them under (`taggable_id`), and a
	 * row carries the prefixed ones, so each is looked up on the side that owns it: the key and the
	 * type on the child, the key it points at on the parent.
	 *
	 * @return array{columns: array<string, string>, morph: array{type_column: string, parent_type: string}}
	 */
	private static function morphOf(LinkMorph $link): array
	{
		$host_is_parent = $link->isHostParent();
		$parent         = $host_is_parent ? $link->getHostTable() : $link->getTargetTable();
		$child          = $host_is_parent ? $link->getTargetTable() : $link->getHostTable();
		$child_key      = $child->getColumnOrFail($link->getMorphChildKeyColumn())
			->getFullName();
		$parent_key = $parent->getColumnOrFail($link->getMorphParentKeyColumn())
			->getFullName();

		return [
			'columns' => $host_is_parent ? [$parent_key => $child_key] : [$child_key => $parent_key],
			'morph'   => [
				'type_column' => $child->getColumnOrFail($link->getMorphChildTypeColumn())
					->getFullName(),
				'parent_type' => $link->getMorphParentType(),
			],
		];
	}

	/**
	 * An object of those columns, by the names a row carries them under, with what one half of the
	 * contract requires.
	 *
	 * The names are the **prefixed** ones (`account_label`), because that is what a REST answer, a
	 * form submission and a definition's `$record` hold -- the unprefixed name is the generated
	 * entity class's accessor and nothing on the wire. A shape keyed the other way would refuse
	 * every property of every real row.
	 *
	 * @param list<Column> $columns
	 *
	 * @return array<string, mixed>
	 */
	private static function objectOf(array $columns, string $side): array
	{
		$props    = [];
		$required = [];

		foreach ($columns as $column) {
			$type = $column->getType();

			// Reading a sensitive column answers the redacted value its declaration names, which is
			// nothing by default: so there is nothing truthful for the read half to say about it,
			// and a shape describing what it holds would be refused by every row that carries it.
			// Writing it is ordinary, and stays.
			if (self::SIDE_READ === $side && $column->isSensitive()) {
				continue;
			}

			$name         = $column->getFullName();
			$props[$name] = self::ofColumn($column, $side);

			// Reading, every column of a row is there. Writing, what the type or the database
			// fills in may be left out -- which `hasDefault()` answers for an `auto` date too,
			// since it is its own default.
			if (
				self::SIDE_READ === $side
				|| (!$type->isNullable() && !$type->hasDefault() && !$type->isAutoIncremented())
			) {
				$required[] = $name;
			}
		}

		return ['t' => 'object', 'props' => $props, 'required' => $required];
	}

	/**
	 * Text: its length in bytes, whether it is cut rather than refused, its pattern and its list.
	 *
	 * @return array<string, mixed>
	 */
	private static function ofString(TypeInterface $type): array
	{
		$shape = ['t' => 'string'];
		$min   = $type->getOption('min');
		$max   = $type->getOption('max');
		$one_of = $type->getOption('one_of');

		if (\is_array($one_of) && [] !== $one_of) {
			$shape['values'] = \array_values($one_of);
		}

		$pattern = $type->getOption('pattern');

		if (\is_string($pattern) && '' !== $pattern) {
			try {
				[$source, $flags] = PortablePattern::toJavaScript($pattern);

				// A pattern's flags have nowhere to go: a shape's pattern is a source read in
				// Unicode mode and nothing else, and JavaScript has no inline flags. So a flagged
				// pattern is not carried, and the shape says what still checks it rather than
				// claiming a rule it would not apply.
				if ('' === $flags) {
					$shape['pattern'] = $source;
				} else {
					$shape['doc'] = \sprintf(
						'The server also checks this against the pattern %s.',
						$pattern
					);
				}
			} catch (InvalidArgumentException) {
				// Declaring one already refuses a pattern that is not portable, so this is a type
				// built another way: nothing is claimed about it.
				$shape['doc'] = 'The server also checks this against a pattern.';
			}
		}

		if (\is_int($min)) {
			$shape['min'] = $min;
		}

		if (\is_int($max)) {
			$shape['max'] = $max;
		}

		if ($type->getOption('truncate')) {
			$shape['truncate'] = true;
		}

		return $shape;
	}

	/**
	 * A number held exactly, as a bigint or a decimal column travels: its scale and its bounds.
	 *
	 * @return array<string, mixed>
	 */
	private static function ofExact(TypeInterface $type, ?int $scale): array
	{
		$shape = ['t' => 'exact'];

		if (null !== $scale) {
			$shape['scale'] = $scale;
		}

		$min = $type->getOption('min');
		$max = $type->getOption('max');

		// Unsigned is a bound and not a flag of its own here: the column refuses a negative value,
		// which `min` says in the one way the language has.
		if (null === $min && $type->getOption('unsigned')) {
			$min = '0';
		}

		if (null !== $min) {
			$shape['min'] = (string) $min;
		}

		if (null !== $max) {
			$shape['max'] = (string) $max;
		}

		return $shape;
	}

	/**
	 * A number a JSON number holds: an `int` or a `float` column.
	 *
	 * @return array<string, mixed>
	 */
	private static function ofNumber(TypeInterface $type, bool $integer): array
	{
		$shape = ['t' => 'number'];

		if ($integer) {
			$shape['integer'] = true;
		}

		$min = $type->getOption('min');
		$max = $type->getOption('max');

		if (null === $min && $type->getOption('unsigned')) {
			$min = 0;
		}

		if (null !== $min) {
			$shape['min'] = $integer ? (int) $min : (float) $min;
		}

		if (null !== $max) {
			$shape['max'] = $integer ? (int) $max : (float) $max;
		}

		return $shape;
	}

	/**
	 * A moment: the timestamp the column stores, with the bounds as timestamps too.
	 *
	 * @return array<string, mixed>
	 */
	private static function ofDate(TypeInterface $type): array
	{
		$format = $type->getOption('format', TypeDate::FORMAT_DEFAULT);

		// A column that asked to be read back formatted answers that text and not a timestamp
		// ({@see TypeDate::dbToPhp()}), so it is text here. The kind means a timestamp, and
		// saying it of a value that reads `2024-01-15` would be a lie about both halves.
		if (TypeDate::FORMAT_TIMESTAMP !== $format) {
			return [
				't'   => 'string',
				'doc' => \sprintf('A date, as the column asks to be read: %s.', (string) $format),
			];
		}

		$micros = $type instanceof TypeDate && $type->isMicroseconds();
		$shape  = ['t' => 'date'];

		if ($micros) {
			$shape['precision'] = 'microseconds';
		}

		// The declared bounds are whatever was written (`'2024-01-01'`, a timestamp); the base
		// type holds what they parsed to, which is what a timestamp is compared against.
		$base = $type->getBaseType();
		$min  = $base->getOption('min');
		$max  = $base->getOption('max');

		if (null !== $min) {
			$shape['min'] = $micros ? (float) $min : (int) $min;
		}

		if (null !== $max) {
			$shape['max'] = $micros ? (float) $max : (int) $max;
		}

		return $shape;
	}

	/**
	 * An enum: the values of its cases, which the type keeps whether or not its class is still there.
	 *
	 * @return array<string, mixed>
	 */
	private static function ofEnum(TypeInterface $type): array
	{
		$cases = $type->getOption(TypeEnum::OPTION_ENUM_CASES);

		if (!\is_array($cases) || [] === $cases) {
			return ['t' => 'string'];
		}

		$values = \array_values($cases);

		return 'int' === $type->getOption(TypeEnum::OPTION_ENUM_BACKING)
			? ['t' => 'number', 'integer' => true, 'values' => \array_map('intval', $values)]
			: ['t' => 'string', 'values' => \array_map('strval', $values)];
	}

	/**
	 * What one element of a list, one value of a map or a whole json column is.
	 *
	 * A universal type says what the far side **accepts**, and for a big number that is the text
	 * or the number alike ({@see ORMUniversalType::isValidValue()}), unlike a column of its own
	 * where the wire form is settled. So that one is written as the alternative it really is.
	 *
	 * @return array<string, mixed>
	 */
	private static function ofUniversal(?ORMUniversalType $of): array
	{
		if (null === $of) {
			return ['t' => 'json'];
		}

		return match ($of) {
			ORMUniversalType::STRING => ['t' => 'string'],
			ORMUniversalType::INT    => ['t' => 'number', 'integer' => true],
			ORMUniversalType::FLOAT  => ['t' => 'number'],
			ORMUniversalType::BOOL   => ['t' => 'boolean'],
			ORMUniversalType::BIGINT => [
				't'        => 'oneOf',
				'of'       => [['t' => 'exact', 'scale' => 0], ['t' => 'number', 'integer' => true]],
				'expected' => 'a whole number',
			],
			ORMUniversalType::DECIMAL => [
				't'        => 'oneOf',
				'of'       => [['t' => 'exact'], ['t' => 'number']],
				'expected' => 'an exact number',
			],
			ORMUniversalType::LIST => ['t' => 'array', 'items' => ['t' => 'json']],
			ORMUniversalType::MAP  => ['t' => 'record', 'values' => ['t' => 'json']],
			ORMUniversalType::NULL, ORMUniversalType::ANY, ORMUniversalType::UNKNOWN => [
				't' => 'json',
			],
		};
	}
}
