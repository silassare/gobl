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

namespace Gobl;

use Blate\Blate;
use Gobl\DBAL\Db;
use Gobl\Exceptions\GoblRuntimeException;
use InvalidArgumentException;
use PHPUtils\Exceptions\RuntimeException;
use PHPUtils\FS\FSUtils;
use Throwable;

/**
 * Class Gobl.
 */
final class Gobl
{
	/**
	 * @var string
	 */
	private static string $def_output_dir = GOBL_ROOT . '.gobl';

	/**
	 * @var array<string, string>
	 */
	private static array $templates = [];

	/**
	 * @var null|string
	 */
	private static ?string $default_schema_url = 'https://raw.githubusercontent.com/silassare/gobl/main/docs/public/schema.json';

	/**
	 * Returns the default JSON Schema URL embedded in exported schema files.
	 *
	 * @return null|string
	 */
	public static function getDefaultSchemaUrl(): ?string
	{
		return self::$default_schema_url;
	}

	/**
	 * Sets the default JSON Schema URL to embed in exported schema files.
	 *
	 * When set, {@see Db::toSchemaJson()} will include a `$schema`
	 * key pointing to this URL, enabling IDE validation and auto-complete.
	 * Pass `null` to clear a previously set URL.
	 *
	 * Example (pointing to the hosted Gobl JSON Schema):
	 *
	 * ```php
	 * Gobl::setDefaultSchemaUrl('https://raw.githubusercontent.com/silassare/gobl/main/docs/public/schema.json');
	 * ```
	 *
	 * @param null|string $url the URL of the JSON Schema document, or null to clear
	 */
	public static function setDefaultSchemaUrl(?string $url): void
	{
		self::$default_schema_url = $url;
	}

	/**
	 * Returns default output directory path.
	 *
	 * @return string
	 */
	public static function getDefaultOutputDir(): string
	{
		return self::$def_output_dir;
	}

	/**
	 * Sets default output directory path.
	 *
	 * @param string $dir
	 */
	public static function setDefaultOutputDir(string $dir): void
	{
		if (self::$def_output_dir !== $dir) {
			$fs = new FSUtils(GOBL_ROOT);

			try {
				$fs->filter()
					->isReadable()
					->isWritable()
					->isDir()
					->assert($dir);
			} catch (RuntimeException $e) {
				throw new GoblRuntimeException(\sprintf('Can\'t set "%s" as gobl cache dir.', $dir), null, $e);
			}

			self::$def_output_dir = $fs->resolve($dir);
		}
	}

	/**
	 * Registers or overwrites named templates file.
	 *
	 * @param array<string,string> $templates map of template name -> path
	 *
	 * @throws InvalidArgumentException when template path is not a string or is empty
	 */
	public static function addTemplates(array $templates): void
	{
		foreach ($templates as $name => $path) {
			if (!\is_string($path) || empty($path)) {
				throw new InvalidArgumentException(
					\sprintf(
						'Template "%s" path is invalid, got "%s" while expecting a non-empty string.',
						$name,
						\get_debug_type($path)
					)
				);
			}

			self::addTemplate($name, $path);
		}
	}

	/**
	 * Gets template file path for a given template name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public static function getTemplateFilePath(string $name): string
	{
		$path = self::$templates[$name] ?? null;

		if (empty($path)) {
			throw new InvalidArgumentException(\sprintf('Unknown template "%s".', $name));
		}

		return $path;
	}

	/**
	 * Add or overwrite a template.
	 *
	 * @param string $name The template name
	 * @param string $path The template path
	 */
	public static function addTemplate(string $name, string $path): void
	{
		$fs = new FSUtils(GOBL_ASSETS_DIR);

		try {
			$fs->filter()
				->isFile()
				->isReadable()
				->assert($path);
		} catch (Throwable $t) {
			throw new GoblRuntimeException(
				\sprintf(
					'Template "%s" path "%s" should be a valid file path.',
					$name,
					$path,
				),
				null,
				$t
			);
		}

		$path = $fs->resolve($path);

		self::$templates[$name] = $path;
	}

	/**
	 * Parse and run a given template with a given inject data.
	 *
	 * @param string $name
	 * @param array  $inject
	 *
	 * @return string
	 */
	public static function runTemplate(string $name, array $inject): string
	{
		try {
			return self::getTemplateCompiler($name)
				->runGet($inject);
		} catch (Throwable $t) {
			throw new GoblRuntimeException(\sprintf('Unable to run template "%s".', $name), $inject, $t);
		}
	}

	/**
	 * Gets template compiler instance for a given template name.
	 *
	 * @param string $name the template name
	 *
	 * @return Blate
	 */
	public static function getTemplateCompiler(string $name): Blate
	{
		try {
			$path = self::getTemplateFilePath($name);

			$fs = new FSUtils();

			$fs->filter()
				->isReadable()
				->isFile()
				->assert($path);

			$b = Blate::fromPath($path);
		} catch (Throwable $t) {
			throw new GoblRuntimeException('Gobl template parse error.', null, $t);
		}

		return $b;
	}

	/**
	 * Returns forbidden column name.
	 *
	 * @return array<string, int>
	 */
	public static function getForbiddenColumnsName(): array
	{
		return [
			'save'          => 1,
			'saved'         => 1,
			'is_saved'      => 1,
			'new'           => 1,
			'is_new'        => 1,
			'hydrate'       => 1,
			// static helpers to get some instances
			'table'          => 1,
			'crud'           => 1,
			'qb'             => 1,
			'ctrl'           => 1,
			'results'        => 1,
			// computed value slot: would collide with getComputedValue() / hasComputedValue()
			'computed_value' => 1,
		];
	}

	/**
	 * Returns forbidden relation name.
	 *
	 * @return array<string, int>
	 */
	public static function getForbiddenRelationsName(): array
	{
		return self::getForbiddenColumnsName();
	}

	/**
	 * Checks if a given column name is allowed.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public static function isAllowedColumnName(string $name): bool
	{
		$list = self::getForbiddenColumnsName();

		return !isset($list[$name]);
	}

	/**
	 * Checks if a given relation name is allowed.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public static function isAllowedRelationName(string $name): bool
	{
		$list = self::getForbiddenRelationsName();

		return !isset($list[$name]);
	}

	/**
	 * Returns a formatted date string to be used in generated files.
	 *
	 * @return string
	 */
	public static function getGeneratedAtDate(): string
	{
		return \date(\DATE_ATOM);
	}

	/**
	 * Gets the queries logger instance.
	 *
	 * @return QueriesLogger
	 */
	public static function ql(): QueriesLogger
	{
		return QueriesLogger::get();
	}
}
