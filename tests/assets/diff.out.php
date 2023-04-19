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
		return 1681895695;
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
	public function getTables(): array
	{
		return [
			'clients'    => [
				'diff_key'      => '883c6aebe983e837e6e5f4f7354e3421',
				'singular_name' => 'client',
				'plural_name'   => 'clients',
				'prefix'        => 'gObL',
				'column_prefix' => 'client',
				'columns'       => [
					'id'         => [
						'diff_key'       => 'a0dcd03ceb5cfa2a30fd7c0660bcaafa',
						'type'           => 'int',
						'prefix'         => 'client',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'given_name' => [
						'diff_key' => 'e236f941b4d87cff23ead867a6fc6670',
						'type'     => 'string',
						'prefix'   => 'client',
						'min'      => 1,
						'max'      => 60,
					],
					'name'       => [
						'diff_key' => 'aa9314f961beaec12c9df34c06e34b3d',
						'type'     => 'string',
						'prefix'   => 'client',
						'min'      => 1,
						'max'      => 60,
					],
					'gender'     => [
						'diff_key' => 'c3dba82d9fa022eb15907da6e5b7174d',
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
						'diff_key' => '76c70516fd3dcd6d06c3a6839814292c',
						'type'     => 'map',
						'prefix'   => 'client',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '389578bbf38f5ffcd53411a323c2279a',
						'type'     => 'date',
						'prefix'   => 'client',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => '70e4a639c58bc1f6313ed30160d18d2e',
						'type'     => 'date',
						'prefix'   => 'client',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'      => [
						'diff_key' => 'cb3abf14c7f311bbaf40268402833fdf',
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
				'diff_key'      => '4f0d78fca994ac1004a840c24a9530f3',
				'singular_name' => 'account',
				'plural_name'   => 'accounts',
				'prefix'        => 'gObL',
				'column_prefix' => 'account',
				'columns'       => [
					'id'            => [
						'diff_key'       => '29f994a8622404fdbdebdfceda591e4c',
						'type'           => 'int',
						'prefix'         => 'account',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'client_id'     => [
						'diff_key' => 'de4111fc9e2611bd1d1504873e241181',
						'type'     => 'ref:clients.id',
						'prefix'   => 'account',
						'unsigned' => true,
					],
					'label'         => [
						'diff_key' => 'c74f52c9220375eb6959dc2136fa2489',
						'type'     => 'string',
						'prefix'   => 'account',
						'min'      => 1,
						'max'      => 60,
					],
					'balance'       => [
						'diff_key' => 'a3a541ac1e51696c92779970e3076347',
						'type'     => 'decimal',
						'prefix'   => 'account',
						'unsigned' => true,
						'default'  => 0,
					],
					'currency_code' => [
						'diff_key' => '3f3683dab974487372be524e543fdf9c',
						'type'     => 'ref:currencies.code',
						'prefix'   => 'account',
						'min'      => 1,
						'max'      => 30,
					],
					'data'          => [
						'diff_key' => 'a0597b6ca978aad5b05815bf6c91c213',
						'type'     => 'map',
						'prefix'   => 'account',
						'default'  => [
						],
					],
					'created_at'    => [
						'diff_key' => '70aa29a6bbfa4809e66d635682ccb95f',
						'type'     => 'date',
						'prefix'   => 'account',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at'    => [
						'diff_key' => '2122788d663b6a949c7011a247b3018e',
						'type'     => 'date',
						'prefix'   => 'account',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'         => [
						'diff_key' => 'cf447305a32f67516bc57272de98dcf3',
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
				'diff_key'      => '2b8451927979146e80ad5ed3ea204637',
				'singular_name' => 'currency',
				'plural_name'   => 'currencies',
				'prefix'        => 'gObL',
				'column_prefix' => 'currency',
				'columns'       => [
					'code'       => [
						'diff_key' => '2f9f047ca7e4a3963f3353a5fe8ab36e',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 30,
					],
					'name'       => [
						'diff_key' => '06fd9d4fd6b982a6686b036e6e140f72',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 60,
					],
					'symbol'     => [
						'diff_key' => '356fbb2cef0acf0fb359018e71aa3c35',
						'type'     => 'string',
						'prefix'   => 'currency',
						'min'      => 1,
						'max'      => 6,
					],
					'data'       => [
						'diff_key' => '113933a8c9aa3437d95b16059fcd3a93',
						'type'     => 'map',
						'prefix'   => 'currency',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => 'f598ad30531798d413149bd423e6ada0',
						'type'     => 'date',
						'prefix'   => 'currency',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => 'cf2376730971b2e465a04be87ea5f19f',
						'type'     => 'date',
						'prefix'   => 'currency',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'      => [
						'diff_key' => '6a3d37f2f718feff1215b3c4140f6021',
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
				'diff_key'      => 'cc85f1f63f7a97f5bdd041a8372de4d5',
				'singular_name' => 'order',
				'plural_name'   => 'orders',
				'prefix'        => 'gObL',
				'column_prefix' => 'order',
				'columns'       => [
					'id'         => [
						'diff_key'       => '6d8afd261a86c7d59023ad52503be7db',
						'type'           => 'int',
						'prefix'         => 'order',
						'unsigned'       => true,
						'auto_increment' => true,
					],
					'data'       => [
						'diff_key' => '7cb4bd559ef6eb75c5db106a98d0634c',
						'type'     => 'map',
						'prefix'   => 'order',
						'default'  => [
						],
					],
					'created_at' => [
						'diff_key' => '2c4d2f16ff098e1257899d50726103d4',
						'type'     => 'date',
						'prefix'   => 'order',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'updated_at' => [
						'diff_key' => 'a154f26ce343877b1d33e7e9a7c6ed9a',
						'type'     => 'date',
						'prefix'   => 'order',
						'auto'     => true,
						'format'   => 'timestamp',
					],
					'valid'      => [
						'diff_key' => 'e932032de35079863d20dee4f3f59a9e',
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
