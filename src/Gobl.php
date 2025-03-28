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

namespace Gobl;

use Gobl\Exceptions\GoblRuntimeException;
use InvalidArgumentException;
use JsonException;
use OTpl\OTpl;
use PHPUtils\Exceptions\RuntimeException;
use PHPUtils\FS\FSUtils;
use Throwable;

/**
 * Class Gobl.
 */
class Gobl
{
	/**
	 * @var string
	 */
	private static string $project_cache_dir = GOBL_ROOT;

	/**
	 * @var array
	 */
	private static array $tpl_cache;

	/**
	 * @var array
	 */
	private static array $templates = [];

	/**
	 * Returns project cache directory.
	 *
	 * @return string
	 */
	public static function getProjectCacheDir(): string
	{
		return self::$project_cache_dir;
	}

	/**
	 * Sets project cache directory.
	 *
	 * @param string $dir
	 */
	public static function setProjectCacheDir(string $dir): void
	{
		if (self::$project_cache_dir !== $dir) {
			$fs = new FSUtils($dir);

			try {
				$fs->filter()
					->isReadable()
					->isWritable()
					->isDir()
					->assert($dir);
			} catch (RuntimeException $e) {
				throw new GoblRuntimeException(\sprintf('Can\'t set "%s" as gobl root dir.', $dir), null, $e);
			}

			self::$project_cache_dir = $fs->resolve('.');

			if (!empty(self::$templates)) {
				$templates       = self::$templates;
				self::$templates = [];
				self::addTemplates($templates);
			}
		}
	}

	/**
	 * Add or overwrite templates.
	 *
	 * @param array $templates
	 */
	public static function addTemplates(array $templates): void
	{
		try {
			$cache_file = (new FSUtils(self::getGoblCacheDir()))->resolve('templates.cache.json');
			$fs         = new FSUtils(self::getProjectCacheDir());

			if (empty(self::$tpl_cache) && \file_exists($cache_file)) {
				$fs->filter()
					->isFile()
					->isReadable()
					->isWritable()
					->assert($cache_file);
				self::$tpl_cache = \json_decode(\file_get_contents($cache_file), true, 512, \JSON_THROW_ON_ERROR);
			}

			$changed = false;

			foreach ($templates as $name => $template) {
				$replaces = $template['replaces'] ?? [];
				$path     = $template['path'] ?? null;

				if (!\is_string($path)) {
					throw new InvalidArgumentException(
						\sprintf(
							'Template "%s" option "path" is invalid, expect "string".',
							$name,
						)
					);
				}

				if (!\is_array($replaces)) {
					throw new InvalidArgumentException(
						\sprintf(
							'Template "%s" option "replaces" is invalid, expect "array".',
							$name,
						)
					);
				}

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

				$path = $fs->resolve($template['path']);

				$sum = \md5($name . \json_encode($replaces, \JSON_THROW_ON_ERROR) . \md5_file($path));

				if (!isset(self::$tpl_cache[$path]['md5']) || self::$tpl_cache[$path]['md5'] !== $sum) {
					$changed = true;

					self::$tpl_cache[$name] = ['path' => $path, 'md5' => $sum];
					self::$templates[$name] = $template;

					$output   = self::toTemplate(\file_get_contents($path), $replaces);
					$out_path = self::getTemplateFilePath($name);

					$fs->wf($out_path, $output);
				}
			}

			if ($changed) {
				$fs->wf($cache_file, \json_encode(self::$tpl_cache, \JSON_THROW_ON_ERROR));
			}
		} catch (JsonException $e) {
			throw new GoblRuntimeException('JSON error.', null, $e);
		}
	}

	/**
	 * Returns Gobl cache directory.
	 *
	 * @return string
	 */
	public static function getGoblCacheDir(): string
	{
		$fu = new FSUtils(self::$project_cache_dir);

		return $fu->cd('.gobl', true)
			->cd('cache', true)
			->getRoot();
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
		return (new FSUtils(self::getGoblCacheDir()))->resolve($name . '.otpl');
	}

	/**
	 * Add or overwrite a template.
	 *
	 * @param string $name
	 * @param string $path
	 * @param array  $replaces
	 */
	public static function addTemplate(string $name, string $path, array $replaces = []): void
	{
		self::addTemplates([
			$name => [
				'path'     => $path,
				'replaces' => $replaces,
			],
		]);
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
	 * @return OTpl
	 */
	public static function getTemplateCompiler(string $name): OTpl
	{
		$path = self::getTemplateFilePath($name);

		try {
			$fs = new FSUtils();

			$fs->filter()
				->isReadable()
				->isFile()
				->assert($path);

			$o = new OTpl();
			$o->parse($path);
		} catch (Throwable $t) {
			throw new GoblRuntimeException('Gobl template parse error.', null, $t);
		}

		return $o;
	}

	/**
	 * Returns forbidden column name.
	 *
	 * @return array<string, int>
	 */
	public static function getForbiddenColumnsName(): array
	{
		return [
			'save'     => 1,
			'saved'    => 1,
			'is_saved' => 1,
			'new'      => 1,
			'is_new'   => 1,
			'hydrate'  => 1,
			// static helpers to get some instances
			'table'    => 1,
			'crud'     => 1,
			'qb'       => 1,
			'ctrl'     => 1,
			'results'  => 1,
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
	 * Converts source code to templates.
	 *
	 * @param string $source
	 * @param array  $replaces
	 *
	 * @return string
	 */
	private static function toTemplate(string $source, array $replaces = []): string
	{
		$replaces = [
			'//@'                    => '',
			'MY_DB_NS'               => '<%$.namespace%>',
			'MyCRUDHandler'          => '<%$.class.crud%>',
			'MyTableQuery'           => '<%$.class.query%>',
			'MyEntity'               => '<%$.class.entity%>',
			'MyResults'              => '<%$.class.results%>',
			'MyController'           => '<%$.class.controller%>',
			'my_table'               => '<%$.table.name%>',
			'my_entity'              => '<%$.table.singular%>',
			'my_id'                  => '<%$.pk_columns[0].fullName%>',
			'\'my_pk_column_const\'' => '<%$.class.entity%>::<%$.pk_columns[0].const%>',
		] + $replaces;

		$search      = \array_keys($replaces);
		$replacement = \array_values($replaces);

		return \str_replace($search, $replacement, $source);
	}
}
