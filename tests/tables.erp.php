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
	'type'           => 'bigint',
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
		'type'   => 'date',
		'format' => 'timestamp',
		'auto'   => true,
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
	'clients'          => [
		'plural_name'   => 'clients',
		'singular_name' => 'client',
		'column_prefix' => 'client',
		'relations'     => [
			'accounts' => ['type' => 'one-to-many', 'target' => 'accounts'],
			'orders'   => ['type' => 'one-to-many', 'target' => 'orders'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'         => $id,
			'first_name' => $string_as_a_name,
			'last_name'  => $string_as_a_name,
			'given_name' => $string_as_a_name,
			'gender'     => $string_as_a_name + [
				'one_of' => ['male', 'female', 'unknown'],
			],

			...$common_columns,
		],
	],
	'products'         => [
		'plural_name'   => 'products',
		'singular_name' => 'product',
		'column_prefix' => 'product',
		'relations'     => [
			'line_items' => ['type' => 'one-to-many', 'target' => 'line_items'],
			'variants'   => ['type' => 'one-to-many', 'target' => 'products', ['id' => 'variant_of']],
			'original'   => ['type' => 'many-to-one', 'target' => 'products', ['variant_of' => 'id']],
			'prices'     => ['type' => 'one-to-many', 'target' => 'prices'],
			'promos'     => [
				'type'   => 'many-to-many',
				'target' => 'promos',
				'link'   => [
					'type'        => 'through',
					'pivot_table' => 'promo_products',
				],
			],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique_key', 'columns' => ['slug']],
			['type' => 'foreign_key', 'reference' => 'products', 'columns' => ['variant_of' => 'id']],
		],
		'columns'       => [
			'id'         => $id,
			'name'       => $string_as_a_title,
			'slug'       => $string_as_a_title,
			'variant_of' => [
				'type'     => 'ref:products.id',
				'nullable' => true,
			],

			...$common_columns,
		],
	],
	'prices'           => [
		'plural_name'   => 'prices',
		'singular_name' => 'price',
		'column_prefix' => 'price',
		'relations'     => [
			'currency_code' => ['type' => 'many-to-one', 'target' => 'currencies'],
			'product'       => ['type' => 'many-to-one', 'target' => 'products'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'foreign_key', 'reference' => 'products', 'columns' => ['product_id' => 'id']],
			['type' => 'foreign_key', 'reference' => 'currencies', 'columns' => ['currency_code' => 'code']],
		],
		'columns'       => [
			'id'            => $id,
			'product_id'    => 'ref:products.id',
			'rate'          => $amount,
			'currency_code' => 'ref:currencies.code',
			'unit'          => $unit,
			'quantity_min'  => [
				'type'     => 'decimal',
				'unsigned' => true,
				'default'  => 0,
			],

			...$common_columns,
		],
	],
	'stores'           => [
		'plural_name'   => 'stores',
		'singular_name' => 'store',
		'column_prefix' => 'store',
		'relations'     => [
			'stocks'     => ['type' => 'one-to-many', 'target' => 'stocks'],
			'parent'     => ['type' => 'many-to-one', 'target' => 'stores', ['parent_id' => 'id']],
			'sub_stores' => ['type' => 'one-to-many', 'target' => 'stores', ['id' => 'parent_id']],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'          => $id,
			'name'        => $string_as_a_name,
			'description' => $string_as_a_description,
			'parent_id'   => [
				'type'     => 'ref:stores.id',
				'nullable' => true,
			],
			// 'location'    => ['type' => 'geo'],

			...$common_columns,
		],
	],
	'warehouses'       => [
		'plural_name'   => 'warehouses',
		'singular_name' => 'warehouse',
		'column_prefix' => 'warehouse',
		'relations'     => [
			'stocks'         => ['type' => 'one-to-many', 'target' => 'stocks'],
			'parent'         => ['type' => 'many-to-one', 'target' => 'warehouses', ['parent_id' => 'id']],
			'sub_warehouses' => ['type' => 'one-to-many', 'target' => 'warehouses', ['id' => 'parent_id']],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'          => $id,
			'label'       => $string_as_a_name,
			'description' => $string_as_a_description,
			'parent_id'   => [
				'type' => 'ref:warehouses.id',
			],
			// 'location'    => ['type' => 'geo'],

			...$common_columns,
		],
	],
	'stocks'           => [
		'plural_name'   => 'stocks',
		'singular_name' => 'stock',
		'column_prefix' => 'stock',
		'relations'     => [
			'product'   => ['type' => 'one-to-one', 'target' => 'products'],
			'warehouse' => ['type' => 'many-to-one', 'target' => 'warehouses'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['product_id']],
		],
		'columns'       => [
			'id'           => $id,
			'warehouse_id' => 'ref:warehouses.id',
			'product_id'   => 'ref:products.id',

			'quantity' => $quantity,
			'unit'     => $unit,

			...$common_columns,
		],
	],
	'stocks_histories' => [
		'plural_name'   => 'stocks_histories',
		'singular_name' => 'stock_history',
		'column_prefix' => 'sh',
		'relations'     => [
			'product' => ['type' => 'one-to-one', 'target' => 'products'],
			'store'   => ['type' => 'one-to-one', 'target' => 'stores'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'       => $id,
			'stock_id' => 'ref:stocks.id',

			'quantity' => $quantity,
			'unit'     => $unit,
			'type'     => $string_as_a_name + [
				'one_of' => ['in', 'out'],
			],
			...$common_columns,
		],
	],
	'promos'           => [
		'plural_name'   => 'promos',
		'singular_name' => 'promo',
		'column_prefix' => 'promo',
		'relations'     => [
			'products' => ['type' => 'one-to-many', 'target' => 'promos_products'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'               => $id,
			'code'             => $string_as_a_name,
			'label'            => $string_as_a_title,
			'description'      => $string_as_a_description,
			'discount_percent' => $percent,
			'discount_amount'  => $amount,
			'max_uses'         => [
				'type'     => 'int',
				'nullable' => true,
			],
			'uses_count'       => [
				'type'    => 'int',
				'default' => 0,
			],
			'start_at'         => [
				'type'   => 'date',
				'format' => 'timestamp',
			],
			'end_at'           => [
				'type'     => 'date',
				'format'   => 'timestamp',
				'nullable' => true,
			],

			...$common_columns,
		],
	],
	'promos_products'  => [
		'plural_name'   => 'promos_products',
		'singular_name' => 'promo_product',
		'column_prefix' => 'pp',
		'relations'     => [
			'promo'   => ['type' => 'many-to-one', 'target' => 'promos'],
			'product' => ['type' => 'one-to-one', 'target' => 'products'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['promo_id', 'product_id']],
		],
		'columns'       => [
			'promo_id'   => [
				'type' => 'ref:promos.id',
			],
			'product_id' => [
				'type' => 'ref:products.id',
			],

			...$common_columns,
		],
	],
	'currencies'       => [
		'plural_name'   => 'currencies',
		'singular_name' => 'currency',
		'column_prefix' => 'currency',
		'relations'     => [
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['code']],
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
	'orders'           => [
		'plural_name'   => 'orders',
		'singular_name' => 'order',
		'column_prefix' => 'order',
		'relations'     => [
			'client'     => [],
			'line_items' => [],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'               => $id,
			'client_id'        => 'ref:clients.id',
			'state'            => $string_as_a_name + [
				'one_of' => ['unpaid', 'cancelled', 'paid'],
			],
			'discount_percent' => $percent,
			'discount_amount'  => $amount,
			'tax_percent'      => $percent,
			'tax_amount'       => $amount,
			'currency_code'    => 'ref:currencies.code',

			...$common_columns,
		],
	],
	'line_items'       => [
		'plural_name'   => 'line_items',
		'singular_name' => 'line_item',
		'column_prefix' => 'line_item',
		'relations'     => [
			'order'   => [],
			'product' => [],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'       => $id,
			'order_id' => 'ref:orders.id',

			'product_id' => 'ref:products.id',

			'label'            => $string_as_a_title,
			'note'             => $string_as_a_description,
			'quantity'         => $quantity,
			'unit'             => $unit,
			'rate'             => $amount,
			'discount_percent' => $percent,
			'discount_amount'  => $amount,
			'tax_percent'      => $percent,
			'tax_amount'       => $amount,
			'currency_code'    => 'ref:currencies.code',

			...$common_columns,
		],
	],
	'payments'         => [
		'plural_name'   => 'payments',
		'singular_name' => 'payment',
		'column_prefix' => 'payment',
		'relations'     => [
			'order'   => [],
			'account' => [],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'       => $id,
			'note'     => $string_as_a_description,
			'order_id' => 'ref:orders.id',

			'account_id' => 'ref:accounts.id',

			'amount'        => $amount,
			'currency_code' => 'ref:currencies.code',

			...$common_columns,
		],
	],
	'accounts'         => [
		'plural_name'   => 'accounts',
		'singular_name' => 'account',
		'column_prefix' => 'account',
		'relations'     => [
			'transactions' => ['type' => 'one-to-many', 'target' => 'transactions'],
			'payments'     => ['type' => 'one-to-many', 'target' => 'payments'],
			'client'       => ['type' => 'many-to-one', 'target' => 'clients'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			//	['type' => 'unique_key', 'columns' => ['client_id', 'currency_code']],
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
	'transactions'     => [
		'plural_name'   => 'transactions',
		'singular_name' => 'transaction',
		'column_prefix' => 'transaction',
		'relations'     => [
			'account' => ['type' => 'many-to-one', 'target' => 'accounts'],
			'refunds' => ['type' => 'one-to-many', 'target' => 'refunds'],
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
	],
	'refunds'          => [
		'plural_name'   => 'refunds',
		'singular_name' => 'refund',
		'column_prefix' => 'refund',
		'relations'     => [
			'target_transaction' => [],
			'transaction'        => [],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
		],
		'columns'       => [
			'id'                    => $id,
			// the transaction for which we are making refund
			'target_transaction_id' => 'ref:transactions.id',
			// the refund transaction
			'transaction_id'        => 'ref:transactions.id',
			'note'                  => $string_as_a_description,
			'date'                  => [
				'type'   => 'date',
				'format' => 'timestamp',
			],

			...$common_columns,
		],
	],
];
