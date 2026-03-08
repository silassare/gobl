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

namespace Gobl\Tests\DBAL\Types\Utils;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Types\Utils\JsonPath;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;

/**
 * Class JsonPathTest.
 *
 * @covers \Gobl\DBAL\Types\Utils\JsonPath
 *
 * @internal
 */
final class JsonPathTest extends BaseTestCase
{
	// =========================================================================
	// JsonPath
	// =========================================================================

	public function testJsonPathSimple(): void
	{
		$p = JsonPath::fromString('column#foo.bar.baz');

		self::assertSame('column', $p->getColumnName());

		self::assertSame(['foo', 'bar', 'baz'], $p->getPathSegments());
	}

	public function testJsonPathSingleSegment(): void
	{
		$p = JsonPath::fromString('column#key');

		self::assertSame('column', $p->getColumnName());

		self::assertSame(['key'], $p->getPathSegments());
	}

	public function testJsonPathSegmentsEmpty(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid JSON path: path portion cannot be empty');

		JsonPath::fromString('column#');
	}

	public function testJsonPathColumnEmpty(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Field name cannot be empty');

		JsonPath::fromString('#key');
	}

	public function testJsonPathQuotedSegmentWithDot(): void
	{
		$p = JsonPath::fromString("column#foo['bar.baz'].qux");

		self::assertSame(['foo', 'bar.baz', 'qux'], $p->getPathSegments());
	}

	public function testJsonPathQuotedSegmentAtStart(): void
	{
		$p = JsonPath::fromString("column#['a.b'].c");

		self::assertSame(['a.b', 'c'], $p->getPathSegments());
	}

	public function testJsonPathQuotedSegmentAtEnd(): void
	{
		$p = JsonPath::fromString("column#a['b.c']");

		self::assertSame(['a', 'b.c'], $p->getPathSegments());
	}

	public function testJsonPathEscapedSingleQuoteInsideSegment(): void
	{
		$p = JsonPath::fromString("column#['it\\'s'].key");

		self::assertSame(["it's", 'key'], $p->getPathSegments());
	}

	public function testJsonPathSegmentWithSpace(): void
	{
		$p = JsonPath::fromString("column#['space key'].sub");

		self::assertSame(['space key', 'sub'], $p->getPathSegments());
	}

	public function testJsonPathSegmentAllQuoted(): void
	{
		$p = JsonPath::fromString("column#['a.b']['c.d']");

		self::assertSame(['a.b', 'c.d'], $p->getPathSegments());
	}

	public function testJsonPathWithTable(): void
	{
		$p = JsonPath::fromString('table.column#user.role');

		self::assertSame('table', $p->getTableOrAlias());
		self::assertSame(['user', 'role'], $p->getPathSegments());
		self::assertSame('column', $p->getColumnName());
	}

	public function testJsonPathWithInvalidTable(): void
	{
		// Empty table prefix is silently treated as no table.
		$p = JsonPath::fromString('.column#user.role');

		self::assertNull($p->getTableOrAlias());
		self::assertSame('column', $p->getColumnName());
		self::assertSame(['user', 'role'], $p->getPathSegments());
	}

	public function testJsonPathWithInvalidColumn(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Field name cannot be empty');

		JsonPath::fromString('table.#user.role');
	}

	public function testJsonPathWithInvalidTableAndColumn(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Field name cannot be empty');

		JsonPath::fromString('.#user.role');
	}

	// =========================================================================
	// Round-trip: parse -> build -> parse
	// =========================================================================

	/**
	 * @dataProvider provideBuildParseRoundTripCases
	 *
	 * @param string $path_str The original path string to parse and then rebuild
	 */
	public function testBuildParseRoundTrip(string $path_str): void
	{
		$p   = JsonPath::fromString($path_str);

		self::assertSame($path_str, (string) $p);
	}

	public static function provideBuildParseRoundTripCases(): iterable
	{
		return [
			['column#foo.bar'],
			['column#key'],
			["column#foo['bar.baz']"],
			["column#['space key'].sub"],
			["column#['it\\'s'].key"],
			["column#a['b c'].d"],
			['column#user_id.key_1.abc123'],
		];
	}

	/** EQ with value binds the param and produces a path = :pXXX expression. */
	public function testAddJsonPathEqBindsValue(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['user', 'name']);
		$filters->add(Operator::EQ, (string) $jp, 'alice');

		$bound = \array_values($qb->getBoundValues());
		self::assertCount(1, $bound, 'EQ must bind exactly one value');
		self::assertSame('alice', $bound[0]);

		$sql = (string) $filters;
		self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString("'$.user.name'", $sql);
		self::assertStringContainsString(' = :', $sql);
	}

	/** EQ null normalizes to IS_NULL - no param binding, IS NULL in SQL. */
	public function testAddJsonPathEqNullNormalizesToIsNull(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['status']);
		$filters->add(Operator::EQ, (string) $jp, null);

		self::assertEmpty($qb->getBoundValues(), 'IS_NULL must not bind any value');

		$sql = (string) $filters;
		self::assertStringContainsString('IS NULL', $sql);
	}

	/** NEQ null normalizes to IS_NOT_NULL - no param binding, IS NOT NULL in SQL. */
	public function testAddJsonPathNeqNullNormalizesToIsNotNull(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['status']);
		$filters->add(Operator::NEQ, (string) $jp, null);

		self::assertEmpty($qb->getBoundValues(), 'IS_NOT_NULL must not bind any value');

		$sql = (string) $filters;
		self::assertStringContainsString('IS NOT NULL', $sql);
	}

	/** CONTAINS builds JSON_CONTAINS expression and binds the value. */
	public function testAddJsonPathContainsBuildsSql(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['tags']);
		$filters->add(Operator::CONTAINS, (string) $jp, '"admin"');

		$bound = \array_values($qb->getBoundValues());
		self::assertCount(1, $bound);
		self::assertSame('"admin"', $bound[0]);

		$sql = (string) $filters;
		// Whole-column CONTAINS on a path uses JSON_CONTAINS(JSON_UNQUOTE(JSON_EXTRACT(...)))
		self::assertStringContainsString('JSON_CONTAINS(JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString("'$.tags'", $sql);
	}

	/** LIKE binds the value and produces a LIKE expression with path extraction. */
	public function testAddJsonPathLikeBindsValue(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['email']);
		$filters->add(Operator::LIKE, (string) $jp, '%@example.com');

		$bound = \array_values($qb->getBoundValues());
		self::assertCount(1, $bound);
		self::assertSame('%@example.com', $bound[0]);

		$sql = (string) $filters;
		self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString("'$.email'", $sql);
		self::assertStringContainsString(' LIKE :', $sql);
	}

	/** IS_NULL is unary - no param is bound, SQL contains the path expression and IS NULL. */
	public function testAddJsonPathIsNullUnaryNoBind(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['optional']);
		$filters->add(Operator::IS_NULL, (string) $jp);

		self::assertEmpty($qb->getBoundValues(), 'IS_NULL must not bind any value');

		$sql = (string) $filters;
		self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString("'$.optional'", $sql);
		self::assertStringContainsString(' IS NULL', $sql);
	}

	/** IS_NOT_NULL is unary - no param bound, SQL contains path expression and IS NOT NULL. */
	public function testAddJsonPathIsNotNullUnaryNoBind(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['required']);
		$filters->add(Operator::IS_NOT_NULL, (string) $jp);

		self::assertEmpty($qb->getBoundValues(), 'IS_NOT_NULL must not bind any value');

		$sql = (string) $filters;
		self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString("'$.required'", $sql);
		self::assertStringContainsString(' IS NOT NULL', $sql);
	}

	/** Table alias is included in the generated column expression. */
	public function testAddJsonPathWithTableAlias(): void
	{
		[$filters]  = $this->makeFiltersOnNativeJsonTable();
		$jp         = $this->makeFiltersNativeJsonColumnJsonPath(['role'], true);
		$filters->add(Operator::EQ, (string) $jp, 'admin');

		$sql = (string) $filters;
		// The quoted alias must appear, and the full column name must be included
		self::assertStringContainsString('`t`', $sql);
		self::assertStringContainsString('`td_data`', $sql);
		self::assertStringContainsString("'$.role'", $sql);
	}

	/** JSON path with column full name (td_data) resolves and generates correct path expression. */
	public function testAddJsonPathAcceptsColumnName(): void
	{
		[$filters, $qb]  = $this->makeFiltersOnNativeJsonTable();
		$jp              = $this->makeFiltersNativeJsonColumnJsonPath(['k']);

		// String form using full column name must resolve to a JSON path extraction and bind value.
		$filters->add(Operator::EQ, (string) $jp, 'v');

		$bound = \array_values($qb->getBoundValues());
		self::assertCount(1, $bound, 'JSON path EQ must bind exactly one value');
		self::assertSame('v', $bound[0]);

		$sql = (string) $filters;
		self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString('`td_data`', $sql);
		self::assertStringContainsString("'$.k'", $sql);
	}

	// =========================================================================
	// FilterOperand JSON path notation - error cases
	// =========================================================================

	/** `#` path notation on a non-native-JSON column (native_json=false) throws immediately. */
	public function testJsonPathOnNonNativeJsonColumnThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/requires native_json/');

		$db = self::getNewDbInstance();
		$db->ns('GoblPathErr1')->table('t_err', static function (TableBuilder $t) {
			$t->columnPrefix('te');
			$t->id();
			$t->map('data'); // no nativeJson()
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('t_err', 'e');
		$filters = new Filters($qb);
		$filters->add(Operator::EQ, 'e.te_data#user.name', 'alice');
	}

	/** `#` path notation on a non-JSON string column throws immediately. */
	public function testJsonPathOnNonJsonColumnThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/only allowed on JSON columns/');

		$db = self::getNewDbInstance();
		$db->ns('GoblPathErr2')->table('t_err', static function (TableBuilder $t) {
			$t->columnPrefix('te');
			$t->id();
			$t->string('name');
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('t_err', 'e');
		$filters = new Filters($qb);
		$filters->add(Operator::EQ, 'e.te_name#first', 'Alice');
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Creates a DB with a native_json map column, returns [Filters, QBSelect].
	 * The QBSelect accumulates bound values for assertions.
	 *
	 * @return array{0: Filters, 1: QBSelect}
	 */
	public static function makeFiltersOnNativeJsonTable(): array
	{
		$db = self::getNewDbInstance();
		$db->ns('GoblFiltersTest')->table('t_data', static function (TableBuilder $t) {
			$t->columnPrefix('td');
			$t->id();
			$t->map('data')->nativeJson();
		});
		$db->lock();

		$qb      = new QBSelect($db);
		$qb->from('t_data', 't');
		$filters = new Filters($qb);

		return [$filters, $qb];
	}

	public static function makeFiltersNativeJsonColumnJsonPath(array $path_segments, bool $with_table_alias = true): string
	{
		$col = self::makeFiltersNativeJsonColumn();

		// Use the full column name (prefix + name) so the SQL expression references the correct DB column.
		return (string) new JsonPath($col->getFullName(), $path_segments, $with_table_alias ? 't' : null);
	}

	/**
	 * Returns a `data` Column from a native_json-enabled table (full name: `td_data`).
	 * Used as the column argument in `addJsonPath` tests.
	 */
	public static function makeFiltersNativeJsonColumn(): Column
	{
		static $counter = 0;
		++$counter;
		$ns = 'GoblAddJsonPathCol' . $counter;
		$db = self::getNewDbInstance();
		$db->ns($ns)->table('t_data', static function (TableBuilder $t) {
			$t->columnPrefix('td');
			$t->id();
			$t->map('data')->nativeJson();
		});
		$db->lock();

		return $db->getTableOrFail('t_data')->getColumnOrFail('data');
	}
}
