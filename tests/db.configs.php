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

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\SQLLite\SQLLite;

return [
	MySQL::NAME   => [
		'db_table_prefix' => 'gObL',
		'db_host'         => 'localhost',
		'db_name'         => 'gobl_test_db',
		'db_user'         => 'gobl_user',
		'db_pass'         => 'gobl_user_pass',
		'db_charset'      => 'utf8mb4',
		'db_collate'      => 'utf8mb4_unicode_ci',
	],
	SQLLite::NAME => [
		'db_table_prefix' => 'gObL',
		'db_host'         => '',
		'db_name'         => 'gobl_test_db',
		'db_user'         => '',
		'db_pass'         => '',
		'db_charset'      => 'utf8mb4',
		'db_collate'      => 'utf8mb4_unicode_ci',
	],
];
