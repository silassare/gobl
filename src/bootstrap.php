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

use Gobl\DBAL\Types\Utils\TypeProviderDefault;
use Gobl\DBAL\Types\Utils\TypeUtils;

if (!\defined('GOBL_ROOT')) {
	\define('GOBL_ROOT', \dirname(__DIR__));
}

if (!\defined('GOBL_VERSION')) {
	\define('GOBL_VERSION', \trim(\file_get_contents(GOBL_ROOT . \DIRECTORY_SEPARATOR . 'VERSION')));
}

if (!\defined('GOBL_ASSETS_DIR')) {
	\define('GOBL_ASSETS_DIR', GOBL_ROOT . \DIRECTORY_SEPARATOR . 'assets');
}

if (!\defined('GOBL_TEMPLATES_DIR')) {
	\define('GOBL_TEMPLATES_DIR', GOBL_ROOT . \DIRECTORY_SEPARATOR . 'templates');
}

TypeUtils::addTypeProvider(new TypeProviderDefault());
