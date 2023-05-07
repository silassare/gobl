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

use Gobl\DBAL\Interfaces\MigrationInterface;

return new class() implements MigrationInterface {
	/**
	 * {@inheritDoc}
	 */
	public function getVersion(): int
	{
		return 1;
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
		return 1683494035;
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): string
	{
		return <<<'DIFF_SQL'
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_clients;
		-- constraints column mapping changed.
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_currencies;
		-- table "gObL_transactions" was deleted.
		ALTER TABLE `gObL_transactions` DROP FOREIGN KEY fk_transactions_accounts;
		-- constraint deleted.
		ALTER TABLE `gObL_currencies` DROP PRIMARY KEY;
		-- table "gObL_transactions" was deleted.
		ALTER TABLE `gObL_transactions` DROP INDEX uc_gObL_transactions_0;
		-- table deleted
		DROP TABLE `gObL_transactions`;
		-- column deleted
		ALTER TABLE `gObL_clients` DROP `client_first_name`;
		-- column deleted
		ALTER TABLE `gObL_clients` DROP `client_last_name`;
		-- column prefix changed from "ccy" to "currency"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `ccy_code` TO `currency_code`;
		-- column prefix changed from "ccy" to "currency"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `ccy_name` TO `currency_name`;
		-- column prefix changed from "ccy" to "currency"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `ccy_symbol` TO `currency_symbol`;
		-- column prefix changed from "ccy" to "currency"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `ccy_data` TO `currency_data`;
		-- column prefix changed from "ccy" to "currency"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `ccy_created_at` TO `currency_created_at`;
		-- column prefix changed from "ccy" to "currency"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `ccy_updated_at` TO `currency_updated_at`;
		-- column prefix changed from "ccy" to "currency"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `ccy_valid` TO `currency_valid`;
		-- column type changed
		ALTER TABLE `gObL_clients` CHANGE `client_id` `client_id` int(11) unsigned NOT NULL AUTO_INCREMENT;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_id` `account_id` int(11) unsigned NOT NULL AUTO_INCREMENT;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_client_id` `account_client_id` int(11) unsigned NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `currency_code` `currency_code` varchar(30) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `currency_name` `currency_name` varchar(60) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `currency_symbol` `currency_symbol` varchar(6) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `currency_data` `currency_data` text NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `currency_created_at` `currency_created_at` bigint(20) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `currency_updated_at` `currency_updated_at` bigint(20) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `currency_valid` `currency_valid` tinyint(1) NOT NULL DEFAULT '1';
		-- column added
		ALTER TABLE `gObL_clients` ADD `client_name` varchar(60) NOT NULL;
		-- table added
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



		-- constraint deleted.
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT uc_gObL_accounts_0 UNIQUE (`account_client_id` , `account_currency_code`);
		-- constraint deleted.
		ALTER TABLE `gObL_currencies` ADD CONSTRAINT uc_gObL_currencies_0 UNIQUE (`currency_code`);
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_clients FOREIGN KEY (`account_client_id`) REFERENCES gObL_clients (`client_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		-- constraints column mapping changed.
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY (`account_currency_code`) REFERENCES gObL_currencies (`currency_code`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		DIFF_SQL;
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): string
	{
		return <<<'DIFF_SQL'
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_clients;
		-- constraints column mapping changed.
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_currencies;
		-- constraint deleted.
		ALTER TABLE `gObL_accounts` DROP INDEX uc_gObL_accounts_0;
		-- constraint deleted.
		ALTER TABLE `gObL_currencies` DROP INDEX uc_gObL_currencies_0;
		-- table deleted
		DROP TABLE `gObL_orders`;
		-- column deleted
		ALTER TABLE `gObL_clients` DROP `client_name`;
		-- column prefix changed from "currency" to "ccy"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_code` TO `ccy_code`;
		-- column prefix changed from "currency" to "ccy"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_name` TO `ccy_name`;
		-- column prefix changed from "currency" to "ccy"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_symbol` TO `ccy_symbol`;
		-- column prefix changed from "currency" to "ccy"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_data` TO `ccy_data`;
		-- column prefix changed from "currency" to "ccy"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_created_at` TO `ccy_created_at`;
		-- column prefix changed from "currency" to "ccy"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_updated_at` TO `ccy_updated_at`;
		-- column prefix changed from "currency" to "ccy"
		ALTER TABLE `gObL_currencies` RENAME COLUMN `currency_valid` TO `ccy_valid`;
		-- column type changed
		ALTER TABLE `gObL_clients` CHANGE `client_id` `client_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_id` `account_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_client_id` `account_client_id` bigint(20) unsigned NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `ccy_code` `ccy_code` varchar(30) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `ccy_name` `ccy_name` varchar(60) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `ccy_symbol` `ccy_symbol` varchar(6) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `ccy_data` `ccy_data` text NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `ccy_created_at` `ccy_created_at` bigint(20) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `ccy_updated_at` `ccy_updated_at` bigint(20) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_currencies` CHANGE `ccy_valid` `ccy_valid` tinyint(1) NOT NULL DEFAULT '1';
		-- column added
		ALTER TABLE `gObL_clients` ADD `client_first_name` varchar(60) NOT NULL;
		-- column added
		ALTER TABLE `gObL_clients` ADD `client_last_name` varchar(60) NOT NULL;
		-- table added
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



		-- constraint deleted.
		ALTER TABLE `gObL_currencies` ADD CONSTRAINT pk_gObL_currencies PRIMARY KEY (`ccy_code`);
		-- table "gObL_transactions" was added.
		ALTER TABLE `gObL_transactions` ADD CONSTRAINT uc_gObL_transactions_0 UNIQUE (`transaction_reference`);
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_clients FOREIGN KEY (`account_client_id`) REFERENCES gObL_clients (`client_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		-- constraints column mapping changed.
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY (`account_currency_code`) REFERENCES gObL_currencies (`ccy_code`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		-- table "gObL_transactions" was added.
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
	public function getSchema(): array
	{
		return [
			'clients'    => [
				'diff_key'      => 'ffec81d589152bf22f0ba34eea81abb4',
				'singular_name' => 'client',
				'plural_name'   => 'clients',
				'namespace'     => 'Test',
				'prefix'        => 'gObL',
				'column_prefix' => 'client',
				'columns'       => [
					'id'         => [
						'diff_key'       => '0d3a1f3cc7e34cece016a31b0bad1142',
						'type'           => 'int',
						'prefix'         => 'client',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'given_name' => [
						'diff_key' => 'a44032b46d9ceabe0d8a44c4d978f5aa',
						'type'     => 'string',
						'prefix'   => 'client',
						'min'      => 1,
						'max'      => 60,
					],
					'name'       => [
						'diff_key' => 'f6034c1eb91614a65b9b6acf990389ab',
						'type'     => 'string',
						'prefix'   => 'client',
						'min'      => 1,
						'max'      => 60,
					],
					'gender'     => [
						'diff_key' => '5b2f8b3f8affa8c1ed3c3bfea37e2abc',
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
					'data'       => [
						'diff_key' => '6a2442212899f6c3a550537817d98be0',
						'type'     => 'map',
						'prefix'   => 'client',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '355d870c5eed059ca754588d701e9028',
						'type'     => 'date',
						'prefix'   => 'client',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => 'de9081943e95c1e2aba4c2dda2da93b8',
						'type'     => 'date',
						'prefix'   => 'client',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'      => [
						'diff_key' => '1f7b36079b118f742166aec07effca08',
						'type'     => 'bool',
						'prefix'   => 'client',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints'   => [
					0 => [
						'type'    => 'primary_key',
						'columns' => [
							0 => 'id',
						],
					],
				],
				'relations'     => [
					'accounts' => [
						'type'   => 'one-to-many',
						'target' => 'accounts',
						'link'   => [
							'type' => 'columns',
						],
					],
				],
			],
			'accounts'   => [
				'diff_key'      => '4edc257cc391e356491e1e4277ce561b',
				'singular_name' => 'account',
				'plural_name'   => 'accounts',
				'namespace'     => 'Test',
				'prefix'        => 'gObL',
				'column_prefix' => 'account',
				'columns'       => [
					'id'            => [
						'diff_key'       => '5ae6511663194f9cfcc9aa0674a89fce',
						'type'           => 'int',
						'prefix'         => 'account',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'client_id'     => [
						'diff_key' => 'ddf1cac63bff521b737eafd3b98bea74',
						'type'     => 'ref:clients.id',
						'prefix'   => 'account',
						'unsigned' => true,
					],
					'label'         => [
						'diff_key' => 'bef2324461fcab77376ad05bc103b937',
						'type'     => 'string',
						'prefix'   => 'account',
						'min'      => 1,
						'max'      => 60,
					],
					'balance'       => [
						'diff_key' => '0856de077d36173ddd052f7dfec559c6',
						'type'     => 'decimal',
						'prefix'   => 'account',
						'unsigned' => true,
						'default'  => 0,
					],
					'currency_code' => [
						'diff_key' => '4567d9ab1ad9e25fb7384ca104f88801',
						'type'     => 'ref:currencies.code',
						'prefix'   => 'account',
						'min'      => 1,
						'max'      => 30,
					],
					'data'          => [
						'diff_key' => '2e3d03cc1e991e741b39bebb6608e440',
						'type'     => 'map',
						'prefix'   => 'account',
						'default'  => [
						],
					],
					'created_at'    => [
						'diff_key' => 'dbf674d266a5118fd5a363fa54247d76',
						'type'     => 'date',
						'prefix'   => 'account',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at'    => [
						'diff_key' => '627bd8c8782b623b0d92f5ce1ec37f71',
						'type'     => 'date',
						'prefix'   => 'account',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'         => [
						'diff_key' => '085fadb0d26c76ae0ec00de25156a378',
						'type'     => 'bool',
						'prefix'   => 'account',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints'   => [
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
						'update'    => 'none',
						'delete'    => 'none',
					],
					3 => [
						'type'      => 'foreign_key',
						'reference' => 'currencies',
						'columns'   => [
							'currency_code' => 'code',
						],
						'update'    => 'none',
						'delete'    => 'none',
					],
				],
				'relations'     => [
					'client' => [
						'type'   => 'many-to-one',
						'target' => 'clients',
						'link'   => [
							'type' => 'columns',
						],
					],
				],
			],
			'currencies' => [
				'diff_key'      => 'c83eb26af9cbaaef16fc9d69297e6af0',
				'singular_name' => 'currency',
				'plural_name'   => 'currencies',
				'namespace'     => 'Test',
				'prefix'        => 'gObL',
				'column_prefix' => 'currency',
				'columns'       => [
					'code'       => [
						'diff_key' => '70337316bc348ef591d026b584a23ba2',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 30,
					],
					'name'       => [
						'diff_key' => 'ef233f0f77d6dfaef5971dda5385bd2c',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 60,
					],
					'symbol'     => [
						'diff_key' => '2d97877e95d590eb2c64afdfd6e995dd',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 6,
					],
					'data'       => [
						'diff_key' => 'a87195bf20e193486f086987f3934ac1',
						'type'     => 'map',
						'prefix'   => 'currency',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '584b45e11272aa837bbeae0c2482ac16',
						'type'     => 'date',
						'prefix'   => 'currency',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => '69aa15efd11b7706e32cdea9c92b99b7',
						'type'     => 'date',
						'prefix'   => 'currency',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'      => [
						'diff_key' => '0a57ba7ad224908f713d72ca74ea1b89',
						'type'     => 'bool',
						'prefix'   => 'currency',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints'   => [
					0 => [
						'type'    => 'unique_key',
						'columns' => [
							0 => 'code',
						],
					],
				],
			],
			'orders'     => [
				'diff_key'      => '36872de43bd8e0b99fec591b6bec1d1a',
				'singular_name' => 'order',
				'plural_name'   => 'orders',
				'namespace'     => 'Test',
				'prefix'        => 'gObL',
				'column_prefix' => 'order',
				'columns'       => [
					'id'         => [
						'diff_key'       => 'f713006b12441894ec66cd3966574621',
						'type'           => 'int',
						'prefix'         => 'order',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'data'       => [
						'diff_key' => '50a04d2511e705aaa16efe3148c10edd',
						'type'     => 'map',
						'prefix'   => 'order',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '43dc3df63dfd6cf4ca896c74c3e27baa',
						'type'     => 'date',
						'prefix'   => 'order',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => 'f6380ea6121ca713fb3f779c7db0b9ae',
						'type'     => 'date',
						'prefix'   => 'order',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'      => [
						'diff_key' => '74adea94b0b3ed91f9f2c2154b7aa1b8',
						'type'     => 'bool',
						'prefix'   => 'order',
						'strict'   => true,
						'default'  => 1,
					],
				],
				'constraints'   => [
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
