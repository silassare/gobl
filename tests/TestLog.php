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

namespace Gobl\Tests;

use JsonSerializable;
use Throwable;

/**
 * Class TestLog.
 *
 * The suite's own log, and the handlers that write to it. A class rather than functions a bootstrap
 * declares: a global function is invisible to every other file as far as any tool can tell, so a
 * test calling one was calling something nothing declared.
 *
 * @internal
 */
final class TestLog
{
	/**
	 * Writes a value to the suite's own log file, for a test being looked at.
	 */
	public static function write(mixed $value): void
	{
		$log_file = './gobl.test.log.txt';
		$prev_sep = "\n========previous========\n";
		$date     = \date('Y-m-d H:i:s');

		if (\is_scalar($value)) {
			$log = (string) $value;
		} elseif (\is_array($value)) {
			$log = \var_export($value, true);
		} elseif ($value instanceof Throwable) {
			$e   = $value;
			$log = (string) $e;

			while ($e = $e->getPrevious()) {
				$log .= $prev_sep . \PHP_EOL . $e;
			}
		} elseif ($value instanceof JsonSerializable) {
			$log = \json_encode($value, \JSON_PRETTY_PRINT);
		} else {
			$log = \get_debug_type($value);
		}
		$log = \str_replace(['\n', '\t', '\/'], ["\n", "\t", '/'], $log);
		$log = "================================================================================\n"
			. $date . "\n"
			. "========================\n"
			. $log . "\n\n";

		$mode = (\file_exists($log_file) && \filesize($log_file) <= 254000) ? 'a' : 'w';

		if ($fp = \fopen($log_file, $mode)) {
			\fwrite($fp, $log);
			\fclose($fp);

			if ('w' === $mode) {
				\chmod($log_file, 0o660);
			}
		}
	}

	/**
	 * Logs a raised error and lets the run carry on.
	 */
	public static function errorHandler(int $code, string $message, string $file, int $line): mixed
	{
		self::write(
			"\n\tFile    : {$file}"
				. "\n\tLine    : {$line}"
				. "\n\tCode    : {$code}"
				. "\n\tMessage : {$message}"
		);

		return null;
	}

	/**
	 * Logs an uncaught throwable.
	 */
	public static function exceptionHandler(Throwable $t): void
	{
		self::write($t);
	}

	/**
	 * Logs the error the run ended on, if it ended on one.
	 */
	public static function shutdown(): void
	{
		$error = \error_get_last();

		if ($error) {
			self::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}
}
