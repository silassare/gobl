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

return new class() implements \Gobl\DBAL\Interfaces\MigrationInterface {
	/**
	 * {@inheritDoc}
	 */
	public function getVersion(): int
	{
		return 1681548787;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLabel(): string
	{
		return 'Auto generated.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTimestamp(): int
	{
		return 1681548787;
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): string
	{
		return <<<'DIFF_SQL'
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_clients;
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_currencies;
		ALTER TABLE `gObL_transactions` DROP FOREIGN KEY fk_transactions_accounts;
		ALTER TABLE `gObL_currencies` DROP PRIMARY KEY;
		ALTER TABLE `gObL_transactions` DROP INDEX uc_gObL_transactions_0;
		DROP TABLE `gObL_transactions`;
		ALTER TABLE `gObL_clients` DROP `client_first_name`;
		ALTER TABLE `gObL_clients` DROP `client_last_name`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code_code` TO `currency_code`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code_name` TO `currency_name`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code_symbol` TO `currency_symbol`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code_data` TO `currency_data`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code_created_at` TO `currency_created_at`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code_updated_at` TO `currency_updated_at`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code_valid` TO `currency_valid`;
		ALTER TABLE `gObL_clients` CHANGE `client_id` `client_id` int(11) unsigned NOT NULL AUTO_INCREMENT;
		ALTER TABLE `gObL_accounts` CHANGE `account_id` `account_id` int(11) unsigned NOT NULL AUTO_INCREMENT;
		ALTER TABLE `gObL_accounts` CHANGE `account_client_id` `account_client_id` int(11) unsigned NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code` `currency_code` varchar(30) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_name` `currency_name` varchar(60) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_symbol` `currency_symbol` varchar(6) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_data` `currency_data` text NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_created_at` `currency_created_at` bigint(20) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_updated_at` `currency_updated_at` bigint(20) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_valid` `currency_valid` tinyint(1) NOT NULL DEFAULT '1';
		ALTER TABLE `gObL_clients` ADD `client_name` varchar(60) NOT NULL;
		--
		-- Table structure for table `gObL_orders`
		--
		DROP TABLE IF EXISTS `gObL_orders`;
		CREATE TABLE `gObL_orders` (
		`order_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`order_data` text NOT NULL,
		`order_created_at` bigint(20) NOT NULL,
		`order_updated_at` bigint(20) NOT NULL,
		`order_valid` tinyint(1) NOT NULL DEFAULT '1',

		--
		-- Primary key constraints definition for table `gObL_orders`
		--
		CONSTRAINT pk_gObL_orders PRIMARY KEY (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



		ALTER TABLE `gObL_accounts` ADD CONSTRAINT uc_gObL_accounts_0 UNIQUE (`account_client_id` , `account_currency_code`);
		ALTER TABLE `gObL_currencies` ADD CONSTRAINT uc_gObL_currencies_0 UNIQUE (`currency_code`);
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_clients FOREIGN KEY (`account_client_id`) REFERENCES gObL_clients (`client_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY (`account_currency_code`) REFERENCES gObL_currencies (`currency_code`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		DIFF_SQL;
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): string
	{
		return <<<'DIFF_SQL'
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_clients;
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_currencies;
		ALTER TABLE `gObL_accounts` DROP INDEX uc_gObL_accounts_0;
		ALTER TABLE `gObL_currencies` DROP INDEX uc_gObL_currencies_0;
		DROP TABLE `gObL_orders`;
		ALTER TABLE `gObL_clients` DROP `client_name`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code` TO `currency_code_code`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_name` TO `currency_code_name`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_symbol` TO `currency_code_symbol`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_data` TO `currency_code_data`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_created_at` TO `currency_code_created_at`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_updated_at` TO `currency_code_updated_at`;
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_valid` TO `currency_code_valid`;
		ALTER TABLE `gObL_clients` CHANGE `client_id` `client_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
		ALTER TABLE `gObL_accounts` CHANGE `account_id` `account_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
		ALTER TABLE `gObL_accounts` CHANGE `account_client_id` `account_client_id` bigint(20) unsigned NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code_code` `currency_code_code` varchar(30) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code_name` `currency_code_name` varchar(60) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code_symbol` `currency_code_symbol` varchar(6) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code_data` `currency_code_data` text NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code_created_at` `currency_code_created_at` bigint(20) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code_updated_at` `currency_code_updated_at` bigint(20) NOT NULL;
		ALTER TABLE `gObL_currencies` CHANGE `currency_code_valid` `currency_code_valid` tinyint(1) NOT NULL DEFAULT '1';
		ALTER TABLE `gObL_clients` ADD `client_first_name` varchar(60) NOT NULL;
		ALTER TABLE `gObL_clients` ADD `client_last_name` varchar(60) NOT NULL;
		--
		-- Table structure for table `gObL_transactions`
		--
		DROP TABLE IF EXISTS `gObL_transactions`;
		CREATE TABLE `gObL_transactions` (
		`transaction_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`transaction_account_id` bigint(20) unsigned NOT NULL,
		`transaction_reference` varchar(128) NOT NULL,
		`transaction_source` varchar(60) NOT NULL,
		`transaction_type` varchar(60) NOT NULL,
		`transaction_state` varchar(60) NOT NULL,
		`transaction_amount` decimal unsigned NOT NULL DEFAULT '0',
		`transaction_currency_code` varchar(30) NOT NULL,
		`transaction_date` bigint(20) NOT NULL,
		`transaction_data` text NOT NULL,
		`transaction_created_at` bigint(20) NOT NULL,
		`transaction_updated_at` bigint(20) NOT NULL,
		`transaction_valid` tinyint(1) NOT NULL DEFAULT '1',

		--
		-- Primary key constraints definition for table `gObL_transactions`
		--
		CONSTRAINT pk_gObL_transactions PRIMARY KEY (`transaction_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



		ALTER TABLE `gObL_currencies` ADD CONSTRAINT pk_gObL_currencies PRIMARY KEY (`currency_code_code`);
		ALTER TABLE `gObL_transactions` ADD CONSTRAINT uc_gObL_transactions_0 UNIQUE (`transaction_reference`);
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_clients FOREIGN KEY (`account_client_id`) REFERENCES gObL_clients (`client_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY (`account_currency_code`) REFERENCES gObL_currencies (`currency_code_code`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		ALTER TABLE `gObL_transactions` ADD CONSTRAINT fk_transactions_accounts FOREIGN KEY (`transaction_account_id`) REFERENCES gObL_accounts (`account_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		DIFF_SQL;
	}

	public function getConfigs(): array
	{
		return [
			'db_table_prefix' => 'gObL',
			'db_host'         => '***',
			'db_name'         => '***',
			'db_user'         => '***',
			'db_pass'         => '***',
			'db_charset'      => 'utf8mb4',
			'db_collate'      => 'utf8mb4_unicode_ci',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTables(): array
	{
		return [
			'clients' => [
				'diff_key'      => '1030eaf4a93ce212b53289274b285311',
				'singular_name' => 'client',
				'plural_name'   => 'clients',
				'prefix'        => 'gObL',
				'column_prefix' => 'client',
				'columns'       => [
					'id' => [
						'diff_key'       => 'ac89f5d15b09a727f4d96c3226e4f716',
						'type'           => 'int',
						'prefix'         => 'client',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'given_name' => [
						'diff_key' => 'f54ebfe64b1dc8bd75e3e1a1b9488612',
						'type'     => 'string',
						'prefix'   => 'client',
						'min'      => 1,
						'max'      => 60,
					],
					'name' => [
						'diff_key' => 'de883589a8b066be6aa0942ad5fb6aa5',
						'type'     => 'string',
						'prefix'   => 'client',
						'min'      => 1,
						'max'      => 60,
					],
					'gender' => [
						'diff_key' => '446ab1df5b2171d16d9e4770aa34c495',
						'type'     => 'string',
						'prefix'   => 'client',
						'min'      => 1,
						'max'      => 60,
						'one_of'   => [
							0 => 'male',
							1 => 'female',
							2 => 'unknown',
						],
					],
					'data' => [
						'diff_key' => 'da6de70efee6a380ba42ba5a8b0d11a3',
						'type'     => 'map',
						'prefix'   => 'client',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => 'b8558a1d6864f3de1967ec455d805947',
						'type'     => 'date',
						'prefix'   => 'client',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => 'e09564495f5ca443f1bec89808fd990f',
						'type'     => 'date',
						'prefix'   => 'client',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid' => [
						'diff_key' => 'b581fef44947bb76ecbf7108beab0868',
						'type'     => 'bool',
						'prefix'   => 'client',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints' => [
					0 => [
						'type'    => 'primary_key',
						'columns' => [
							0 => 'id',
						],
					],
				],
				'relations' => [
					'accounts' => [
						'target' => 'accounts',
						'type'   => 'one-to-many',
					],
				],
			],
			'accounts' => [
				'diff_key'      => '3fd679e282b742a1ce3fa837ff2177d4',
				'singular_name' => 'account',
				'plural_name'   => 'accounts',
				'prefix'        => 'gObL',
				'column_prefix' => 'account',
				'columns'       => [
					'id' => [
						'diff_key'       => '4084751e9adf6afde0a65eda969fe9c7',
						'type'           => 'int',
						'prefix'         => 'account',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'client_id' => [
						'diff_key' => '323cc250e0e207e298448ab721dacbe5',
						'type'     => 'ref:clients.id',
						'prefix'   => 'account',
						'unsigned' => true,
					],
					'label' => [
						'diff_key' => 'f33de172b0efdf81d25315a704678244',
						'type'     => 'string',
						'prefix'   => 'account',
						'min'      => 1,
						'max'      => 60,
					],
					'balance' => [
						'diff_key' => '5243a1475886f51020570eb3050ec572',
						'type'     => 'decimal',
						'prefix'   => 'account',
						'unsigned' => true,
						'default'  => 0,
					],
					'currency_code' => [
						'diff_key' => '449574b12912772f9694b06cff350f71',
						'type'     => 'ref:currencies.code',
						'prefix'   => 'account',
						'min'      => 1,
						'max'      => 30,
					],
					'data' => [
						'diff_key' => '5d80cbb834318daa5d3f4c16ccee2aa7',
						'type'     => 'map',
						'prefix'   => 'account',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '50051a283c1447458dd9a6be29c5cee9',
						'type'     => 'date',
						'prefix'   => 'account',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => '907383803c97e758970ae9d7d9cb1ece',
						'type'     => 'date',
						'prefix'   => 'account',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid' => [
						'diff_key' => 'd5295bfd3168e0f12a3535f5a08266cb',
						'type'     => 'bool',
						'prefix'   => 'account',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints' => [
					0 => [
						'type'    => 'primary_key',
						'columns' => [
							0 => 'id',
						],
					],
					1 => [
						'type'    => 'unique_key',
						'columns' => [
							0 => 'client_id',
							1 => 'currency_code',
						],
					],
					2 => [
						'type'      => 'foreign_key',
						'reference' => 'clients',
						'columns'   => [
							'client_id' => 'id',
						],
						'update' => 'none',
						'delete' => 'none',
					],
					3 => [
						'type'      => 'foreign_key',
						'reference' => 'currencies',
						'columns'   => [
							'currency_code' => 'code',
						],
						'update' => 'none',
						'delete' => 'none',
					],
				],
				'relations' => [
					'client' => [
						'target' => 'clients',
						'type'   => 'many-to-one',
					],
				],
			],
			'currencies' => [
				'diff_key'      => '8c462e314b627ea818ac85dc479ee25b',
				'singular_name' => 'currency',
				'plural_name'   => 'currencies',
				'prefix'        => 'gObL',
				'column_prefix' => 'currency',
				'columns'       => [
					'code' => [
						'diff_key' => 'a2a65acae76e672ddaad35d3532e549a',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 30,
					],
					'name' => [
						'diff_key' => '4cf6e25dbfd995a9a15e5288396be172',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 60,
					],
					'symbol' => [
						'diff_key' => '33b6156b039d600a149dea2e457d3409',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 6,
					],
					'data' => [
						'diff_key' => '8ae1412f71b1517f4f4497d535f12944',
						'type'     => 'map',
						'prefix'   => 'currency',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '9e84a422ebbf672ba38d67afc3610b2d',
						'type'     => 'date',
						'prefix'   => 'currency',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => '677370a95430f3e9d3c47f739f1d713d',
						'type'     => 'date',
						'prefix'   => 'currency',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid' => [
						'diff_key' => '85fe67d039da14703d1f7fe9081530d0',
						'type'     => 'bool',
						'prefix'   => 'currency',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints' => [
					0 => [
						'type'    => 'unique_key',
						'columns' => [
							0 => 'code',
						],
					],
				],
			],
			'orders' => [
				'diff_key'      => 'fd0c78875a21ece834c4445928dc4ed5',
				'singular_name' => 'order',
				'plural_name'   => 'orders',
				'prefix'        => 'gObL',
				'column_prefix' => 'order',
				'columns'       => [
					'id' => [
						'diff_key'       => 'e16e6798f80c067f13ca5edcbf889dc4',
						'type'           => 'int',
						'prefix'         => 'order',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'data' => [
						'diff_key' => 'dde8b97afac8ec5895b718e48acf0661',
						'type'     => 'map',
						'prefix'   => 'order',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '154720b7e0bfe623023ad576654476fc',
						'type'     => 'date',
						'prefix'   => 'order',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => '262055c9294efa1937c573b51be2dedc',
						'type'     => 'date',
						'prefix'   => 'order',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid' => [
						'diff_key' => '585bd46eb0d40725f73f5beab6c3f49d',
						'type'     => 'bool',
						'prefix'   => 'order',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints' => [
					0 => [
						'type'    => 'primary_key',
						'columns' => [
							0 => 'id',
						],
					],
				],
			],
		];
	}
};
