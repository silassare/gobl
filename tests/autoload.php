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

use Blate\Blate;
use Gobl\Tests\TestLog;
use Gobl\Tests\TestPaths;

require_once \dirname(__DIR__) . '/vendor/autoload.php';

// The one global the library itself reads: `CSGeneratorORM` asks `defined()` for it, which is the
// suite telling the library it is the suite. Every path the tests use is a constant of TestPaths.
\define('GOBL_TEST_MODE', true);

Blate::setCacheDir(TestPaths::PROJECT_DIR);

\set_exception_handler(TestLog::exceptionHandler(...));
\set_error_handler(TestLog::errorHandler(...));
\register_shutdown_function(TestLog::shutdown(...));
