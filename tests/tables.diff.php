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

$id             = [
	// DIFF: TYPE CHANGED
	'type'           => 'int',
	'auto_increment' => true,
	'unsigned'       => true,
];
$common_columns = [
	'data'       => [
		'type'    => 'map',
		'default' => [],
	],
	'created_at' => [
		'type'   => 'date',
		'format' => 'timestamp',
		'auto'   => true,
	],
	'updated_at' => [
		'type'     => 'date',
		'format'   => 'timestamp',
		'nullable' => true,
	],
	'valid'      => [
		'type'    => 'bool',
		'default' => true,
	],
];
$quantity       = [
	'type'     => 'decimal',
	'unsigned' => true,
];
$unit           = [
	'type'    => 'string',
	'min'     => 1,
	'max'     => 32,
	'default' => 'N/A',
];
$amount         = [
	'type'     => 'decimal',
	'unsigned' => true,
	'default'  => 0,
];
$percent        = [
	'type'     => 'float',
	'unsigned' => true,
	'default'  => 0,
	'max'      => 100,
];

$string_as_a_name        = [
	'type' => 'string',
	'min'  => 1,
	'max'  => 60,
];
$string_as_a_title       = [
	'type' => 'string',
	'min'  => 1,
	'max'  => 128,
];
$string_as_a_summary     = [
	'type' => 'string',
	'min'  => 1,
	'max'  => 255,
];
$string_as_a_description = [
	'type' => 'string',
];

return [
	'clients'    => [
		'plural_name'   => 'clients',
		'singular_name' => 'client',
		'column_prefix' => 'client',
		'relations'     => [
			'accounts' => ['type' => 'one-to-many', 'target' => 'accounts'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'         => $id,
			// DIFF: COLUMN REMOVED
			// 'first_name' => $string_as_a_name,
			// 'last_name'  => $string_as_a_name,
			'given_name' => $string_as_a_name,
			// DIFF: COLUMN ADDED
			'name'       => $string_as_a_name,
			'gender'     => $string_as_a_name + [
				'one_of' => ['male', 'female', 'unknown'],
			],

			...$common_columns,
		],
	],
	'accounts'   => [
		'plural_name'   => 'accounts',
		'singular_name' => 'account',
		'column_prefix' => 'account',
		'relations'     => [
			// DIFF: RELATION REMOVED DUE TO TRANSACTIONS TABLE REMOVAL
			// 'transactions' => ['type' => 'one-to-many', 'target' => 'transactions'],
			'client' => ['type' => 'many-to-one', 'target' => 'clients'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique_key', 'columns' => ['client_id', 'currency_code']], // DIFF: UNIQUE KEY ADDED
			['type' => 'foreign_key', 'reference' => 'clients', 'columns' => ['client_id' => 'id']],
			['type' => 'foreign_key', 'reference' => 'currencies', 'columns' => ['currency_code' => 'code']],
		],
		'columns'       => [
			'id'        => $id,
			'client_id' => 'ref:clients.id',

			'label'         => $string_as_a_name,
			'balance'       => $amount,
			'currency_code' => 'ref:currencies.code',

			...$common_columns,
		],
	],
	// DIFF: TABLE DELETED
	/*
	 'transactions' => [
		'plural_name'   => 'transactions',
		'singular_name' => 'transaction',
		'column_prefix' => 'transaction',
		'relations'     => [
			'account' => ['type' => 'many-to-one', 'target' => 'accounts'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique_key', 'columns' => ['reference']],
			['type' => 'foreign_key', 'reference' => 'accounts', 'columns' => ['account_id' => 'id']],
		],
		'columns'       => [
			'id'         => $id,
			'account_id' => 'ref:accounts.id',

			'reference'     => $string_as_a_title,
			'source'        => $string_as_a_name + [
					'one_of' => ['bank_transfer', 'card', 'cash'],
				],
			'type'          => $string_as_a_name + [
					'one_of' => ['in', 'out'],
				],
			'state'         => $string_as_a_name + [
					'one_of' => ['in_error', 'pending_confirmation', 'confirmed', 'refunded', 'partially_refunded'],
				],
			'amount'        => $amount,
			'currency_code' => 'ref:currencies.code',
			'date'          => [
				'type'   => 'date',
				'format' => 'timestamp',
			],

			...$common_columns,
		],
	],*/
	'currencies' => [
		'plural_name'   => 'currencies',
		'singular_name' => 'currency',
		'column_prefix' => 'currency',
		'relations'     => [
		],
		'constraints'   => [
			// DIFF: PRIMARY KEY REMOVED
			// ['type' => 'primary_key', 'columns' => ['code']],
			// DIFF: UNIQUE KEY ADDED
			['type' => 'unique_key', 'columns' => ['code']],
		],
		'columns'       => [
			'code'   => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 30,
			],
			'name'   => $string_as_a_name,
			'symbol' => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 6,
			],

			...$common_columns,
		],
	],
	// DIFF: TABLE ADDED
	'orders'     => [
		'plural_name'   => 'orders',
		'singular_name' => 'order',
		'column_prefix' => 'order',
		'relations'     => [
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id' => $id,

			...$common_columns,
		],
	],
];
