<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl;

if (!\defined('GOBL_ROOT')) {
	\define('GOBL_ROOT', \realpath(__DIR__ . '/..'));
}

if (!\defined('GOBL_VERSION')) {
	\define('GOBL_VERSION', \trim(\file_get_contents(__DIR__ . '/../VERSION')));
}

class Gobl
{
	const VERSION       = GOBL_VERSION;

	const ROOT_DIR      = GOBL_ROOT;

	const SAMPLES_DIR   = GOBL_ROOT . '/samples';

	const TEMPLATES_DIR = GOBL_ROOT . '/templates';
}
