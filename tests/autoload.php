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

require_once \dirname(__DIR__) . '/vendor/autoload.php';

\define('GOBL_TEST_MODE', true);
\define('GOBL_TEST_ROOT', __DIR__);
\define('GOBL_TEST_ASSETS', GOBL_TEST_ROOT . \DIRECTORY_SEPARATOR . 'assets');
\define('GOBL_TEST_PROJECT_DIR', GOBL_TEST_ROOT . \DIRECTORY_SEPARATOR . 'tmp');
\define('GOBL_TEST_OUTPUT', GOBL_TEST_PROJECT_DIR . \DIRECTORY_SEPARATOR . 'output');

if (!\function_exists('gobl_test_log')) {
	function gobl_test_log(mixed $value): void
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
				\chmod($log_file, 0660);
			}
		}
	}

	function gobl_test_error_handler(int $code, string $message, string $file, int $line): mixed
	{
		gobl_test_log(
			"\n\tFile    : {$file}"
			. "\n\tLine    : {$line}"
			. "\n\tCode    : {$code}"
			. "\n\tMessage : {$message}"
		);

		return null;
	}

	function gobl_test_exception_handler(Exception $t): void
	{
		gobl_test_log($t);
	}

	function gobl_shutdown_function(): void
	{
		$error = \error_get_last();

		if ($error) {
			gobl_test_error_handler($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}

	\set_exception_handler('gobl_test_exception_handler');
	\set_error_handler('gobl_test_error_handler');
	\register_shutdown_function('gobl_shutdown_function');
}
