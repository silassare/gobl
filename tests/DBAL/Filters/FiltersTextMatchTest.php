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

namespace Gobl\Tests\DBAL\Filters;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\TextMatch;
use Gobl\DBAL\Filters\Traits\FiltersOperatorsHelpersTrait;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Types\TypeJson;
use Gobl\Tests\BaseTestCase;
use JsonSerializable;
use OLIUP\CG\PHPMethod;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * What the text search operator compiles to, and what it does to a term first.
 *
 * The operator exists because one name meant two comparisons: `like` folded case and accents on a
 * plain column and neither on a JSON one, with nothing to say so. What each engine folds is still
 * not the same, so it is declared here and asserted against a real server by the integration tests.
 *
 * @internal
 */
#[CoversClass(TextMatch::class)]
#[CoversClass(Filters::class)]
#[CoversClass(Operator::class)]
#[CoversClass(TypeJson::class)]
#[CoversClass(SQLQueryGeneratorBase::class)]
#[CoversTrait(FiltersOperatorsHelpersTrait::class)]
final class FiltersTextMatchTest extends BaseTestCase
{
	// =========================================================================
	// The term is a term, not a pattern
	// =========================================================================

	/** The wildcards are the compiler's, so an indexed search of the same column can replace them. */
	public function testATermIsBoundAsAPatternOfItsOwn(): void
	{
		[$filters, $qb] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.td_title', 'economie');

		self::assertSame(['%economie%'], \array_values($qb->getBoundValues()));
	}

	/** A term holding a wildcard searches for that character, not for anything. */
	public function testAWildcardInATermIsEscaped(): void
	{
		[$filters, $qb] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.td_title', '50%_net');

		self::assertSame(['%50!%!_net%'], \array_values($qb->getBoundValues()));
	}

	/** The escape character itself is escaped, or it would eat the character after it. */
	public function testTheEscapeCharacterInATermIsEscaped(): void
	{
		self::assertSame('%a!!b%', TextMatch::pattern('a' . TextMatch::ESCAPE_CHAR . 'b'));
	}

	/** An empty term matches every row, which is never what a search means. */
	public function testAnEmptyTermIsRefused(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('non-empty search term');

		[$filters] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.td_title', '');
	}

	/** A number is a term too: a reference typed into a search box. */
	public function testANumberIsAcceptedAsATerm(): void
	{
		[$filters, $qb] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.td_title', 2026);

		self::assertSame(['%2026%'], \array_values($qb->getBoundValues()));
	}

	/** Anything that is not a term is refused, rather than compared as one. */
	public function testWhatIsNotATermIsRefused(): void
	{
		$values = [
			'null'             => null,
			'bool'             => true,
			'array'            => ['a'],
			'JsonSerializable' => new class implements JsonSerializable {
				#[Override]
				public function jsonSerialize(): mixed
				{
					return 'a';
				}
			},
		];

		foreach ($values as $label => $value) {
			[$filters] = self::makeFilters(MySQL::NAME);

			try {
				$filters->add(Operator::MATCHES, 't.td_title', $value);
				self::fail(\sprintf('a %s right operand should be refused', $label));
			} catch (DBALRuntimeException) {
				self::assertTrue(true);
			}
		}
	}

	/**
	 * The term is text, so the column's type does not touch it.
	 *
	 * A date column would otherwise turn "2026" into a timestamp and search for that.
	 */
	public function testTheColumnsTypeDoesNotCastTheTerm(): void
	{
		[$filters, $qb] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.td_created_at', '2026');

		self::assertSame(['%2026%'], \array_values($qb->getBoundValues()));
	}

	// =========================================================================
	// What each engine compiles
	// =========================================================================

	/** MySQL gives the value a character set first: a JSON column has none to collate. */
	public function testMySQLConvertsAndCollates(): void
	{
		[$filters] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.td_title', 'economie');

		self::assertSame(
			"(CONVERT(t.td_title USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE :_val_a ESCAPE '!')",
			(string) $filters
		);
	}

	/** PostgreSQL folds case with lower(), and the cast is what lets it read a jsonb column. */
	public function testPostgreSQLLowersBothSides(): void
	{
		[$filters] = self::makeFilters(PostgreSQL::NAME);
		$filters->matches('t.td_title', 'economie');

		self::assertSame(
			"(lower((t.td_title)::text) LIKE lower(:_val_a) ESCAPE '!')",
			(string) $filters
		);
	}

	/** SQLite has only its ASCII lower(). */
	public function testSQLiteLowersBothSides(): void
	{
		[$filters] = self::makeFilters(SQLite::NAME);
		$filters->matches('t.td_title', 'economie');

		self::assertSame(
			"(lower(t.td_title) LIKE lower(:_val_a) ESCAPE '!')",
			(string) $filters
		);
	}

	/** A path compares the extracted value; the extraction is the driver's own. */
	public function testAPathComparesTheExtractedValue(): void
	{
		[$filters] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.td_data#fr', 'economie');

		$sql = (string) $filters;
		self::assertStringContainsString('CONVERT(JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString("'$.fr'", $sql);
		self::assertStringContainsString('COLLATE utf8mb4_unicode_ci LIKE', $sql);
	}

	/** The charset and collation are the project's, never a literal in the compiler. */
	public function testTheCharsetAndCollationComeFromTheConfiguration(): void
	{
		[$filters] = self::makeFilters(MySQL::NAME, [
			'db_charset' => 'utf8mb3',
			'db_collate' => 'utf8mb3_general_ci',
		]);
		$filters->matches('t.td_title', 'economie');

		self::assertStringContainsString(
			'CONVERT(t.td_title USING utf8mb3) COLLATE utf8mb3_general_ci',
			(string) $filters
		);
	}

	/** A table saying its own charset is the one that governs its columns. */
	public function testATablesOwnCharsetGoverns(): void
	{
		$db = self::makeDb(MySQL::NAME, []);
		$db->getTableOrFail('t_data')
			->setCharset('utf8mb3')
			->setCollate('utf8mb3_general_ci');
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('t_data', 't');
		$filters = new Filters($qb);
		$filters->matches('t.td_title', 'economie');

		self::assertStringContainsString(
			'CONVERT(t.td_title USING utf8mb3) COLLATE utf8mb3_general_ci',
			(string) $filters
		);
	}

	/** Both names go into SQL unquoted, so neither may be anything but a word. */
	public function testACharsetThatIsNotAWordIsRefused(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('not a usable charset or collation name');

		[$filters] = self::makeFilters(MySQL::NAME, ['db_collate' => "utf8mb4_unicode_ci' OR '1"]);
		$filters->matches('t.td_title', 'economie');

		// the expression is built when the filters are read, not when the filter is added
		(string) $filters;
	}

	// =========================================================================
	// The ORM surface
	// =========================================================================

	/** The generated method is whereTitleMatches(). */
	public function testTheFilterSuffixNamesTheOperator(): void
	{
		$db     = self::makeDb(MySQL::NAME, []);
		$column = $db->getTableOrFail('t_data')->getColumnOrFail('title');

		self::assertSame('title_matches', Operator::MATCHES->getFilterSuffix($column));
	}

	/** A JSON column answers the operator whether it stores JSON natively or as text. */
	public function testAJsonColumnAllowsTheOperatorInBothStorageModes(): void
	{
		$native = new TypeJson();
		$text   = new TypeJson()->nativeJson(false);

		self::assertContains(Operator::MATCHES, $native->getAllowedFilterOperators());
		self::assertContains(Operator::MATCHES, $text->getAllowedFilterOperators());
	}

	/** On a JSON column the path is optional: without one the whole document is searched. */
	public function testOnAJsonColumnThePathIsOptional(): void
	{
		$db     = self::makeDb(MySQL::NAME, []);
		$table  = $db->getTableOrFail('t_data');
		$column = $table->getColumnOrFail('data');
		$method = new PHPMethod('whereDataMatches');

		$column->getType()->queryBuilderEnhanceFilterMethod($table, $column, Operator::MATCHES, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(2, $args);
		self::assertSame('term', $args[0]->getName());
		self::assertSame('string|int|float', (string) $args[0]->getType());
		self::assertSame('path', $args[1]->getName());
		self::assertSame('?string', (string) $args[1]->getType());
		self::assertNotNull($args[1]->getValue(), 'the path should default to null');
	}

	/**
	 * @return array{0: Filters, 1: QBSelect}
	 */
	private static function makeFilters(string $driver, array $config_overrides = []): array
	{
		$db = self::makeDb($driver, $config_overrides);
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('t_data', 't');

		return [new Filters($qb), $qb];
	}

	private static function makeDb(string $driver, array $config_overrides): RDBMSInterface
	{
		static $counter = 0;
		++$counter;

		$config = self::getDbConfig($driver)->toArray();
		$db     = Db::newInstanceOf($driver, new DbConfig($config_overrides + $config));

		$db->ns('GoblTextMatch' . $counter)->table('t_data', static function (TableBuilder $t): void {
			$t->columnPrefix('td');
			$t->id();
			$t->map('data');
			$t->string('title')->max(255);
			$t->date('created_at');
		});

		return $db;
	}
}
