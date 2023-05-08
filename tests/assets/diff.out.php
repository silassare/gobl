<?php
/**
 * Generated on: 7th May 2023, 11:49:17 pm
 */
declare(strict_types=1);

use Gobl\DBAL\Interfaces\MigrationInterface;

return new class implements MigrationInterface {
	/**
	 * @inheritDoc
	 */
	public function getVersion(): int
	{
		return 1;
	}

	/**
	 * @inheritDoc
	 */
	public function getLabel(): string
	{
		return 'Auto generated.';
	}

	/**
	 * @inheritDoc
	 */
	public function getTimestamp(): int
	{
		return 1683503357;
	}

	/**
	 * @inheritDoc
	 */
	public function up(): string
	{
		return <<<DIFF_SQL
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_clients;
		-- constraints column mapping changed.
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_currencies;
		-- table "gObL_transactions" was deleted.
		ALTER TABLE `gObL_transactions` DROP FOREIGN KEY fk_transactions_accounts;
		-- primary key constraint deleted
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
		ALTER TABLE `gObL_clients` CHANGE `client_updated_at` `client_updated_at` bigint(20) NULL DEFAULT NULL;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_id` `account_id` int(11) unsigned NOT NULL AUTO_INCREMENT;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_client_id` `account_client_id` int(11) unsigned NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_updated_at` `account_updated_at` bigint(20) NULL DEFAULT NULL;
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
		ALTER TABLE `gObL_currencies` CHANGE `currency_updated_at` `currency_updated_at` bigint(20) NULL DEFAULT NULL;
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
		`order_updated_at` bigint(20) NULL DEFAULT NULL,
		`order_valid` tinyint(1) NOT NULL DEFAULT '1',

		--
		-- Primary key constraints definition for table `gObL_orders`
		--
		CONSTRAINT pk_gObL_orders PRIMARY KEY (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



		ALTER TABLE `gObL_accounts` ADD CONSTRAINT uc_gObL_accounts_0 UNIQUE (`account_client_id` , `account_currency_code`);
		ALTER TABLE `gObL_currencies` ADD CONSTRAINT uc_gObL_currencies_0 UNIQUE (`currency_code`);
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_clients FOREIGN KEY (`account_client_id`) REFERENCES gObL_clients (`client_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		-- constraints column mapping changed.
		ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY (`account_currency_code`) REFERENCES gObL_currencies (`currency_code`) ON UPDATE NO ACTION ON DELETE NO ACTION;
		DIFF_SQL;
	}

	/**
	 * @inheritDoc
	 */
	public function down(): string
	{
		return <<<DIFF_SQL
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_clients;
		-- constraints column mapping changed.
		ALTER TABLE `gObL_accounts` DROP FOREIGN KEY fk_accounts_currencies;
		-- unique key constraint deleted
		ALTER TABLE `gObL_accounts` DROP INDEX uc_gObL_accounts_0;
		-- unique key constraint deleted
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
		ALTER TABLE `gObL_clients` CHANGE `client_updated_at` `client_updated_at` bigint(20) NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_id` `account_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_client_id` `account_client_id` bigint(20) unsigned NOT NULL;
		-- column type changed
		ALTER TABLE `gObL_accounts` CHANGE `account_updated_at` `account_updated_at` bigint(20) NOT NULL;
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
	 * @inheritDoc
	 */
	public function getSchema(): array
	{
		return [
			'clients'    =>
				[
					'diff_key'      => '1f32f990f23ddd9f30c8e1f9710b1d37',
					'singular_name' => 'client',
					'plural_name'   => 'clients',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'client',
					'columns'       =>
						[
							'id'         =>
								[
									'diff_key'       => 'b570caf0dd9016d19feeb5b73dd4d296',
									'type'           => 'int',
									'prefix'         => 'client',
									'unsigned'       => true,
									'auto_increment' => true,
								],
							'given_name' =>
								[
									'diff_key' => 'fface2b3fc15f2535d379f858dafc067',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
								],
							'name'       =>
								[
									'diff_key' => '12d7e2239500282678cc5ad5fe8ff0f3',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
								],
							'gender'     =>
								[
									'diff_key' => '5fb031e3d9de6a74147f4c1ed3b9dd86',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
									'one_of'   =>
										[
											0 => 'male',
											1 => 'female',
											2 => 'unknown',
										],
								],
							'data'       =>
								[
									'diff_key' => '5c85cb35243fe39f2a1fc78c24d5dca3',
									'type'     => 'map',
									'prefix'   => 'client',
									'default'  =>
										[
										],
								],
							'created_at' =>
								[
									'diff_key' => 'ff97c6db6481feaa6216b26e98deec24',
									'type'     => 'date',
									'prefix'   => 'client',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at' =>
								[
									'diff_key' => '32b8ffe5260e0783dff7bd8a62e9cad1',
									'type'     => 'date',
									'prefix'   => 'client',
									'format'   => 'timestamp',
									'nullable' => true,
								],
							'valid'      =>
								[
									'diff_key' => 'c97ff659160b8d33972d79975eb2e929',
									'type'     => 'bool',
									'prefix'   => 'client',
									'strict'   => true,
									'default'  => 1,
								],
						],
					'constraints'   =>
						[
							0 =>
								[
									'type'    => 'primary_key',
									'columns' =>
										[
											0 => 'id',
										],
								],
						],
					'relations'     =>
						[
							'accounts' =>
								[
									'type'   => 'one-to-many',
									'target' => 'accounts',
									'link'   =>
										[
											'type' => 'columns',
										],
								],
						],
				],
			'accounts'   =>
				[
					'diff_key'      => '201528f1b8cefe9c1ebba831810a3af7',
					'singular_name' => 'account',
					'plural_name'   => 'accounts',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'account',
					'columns'       =>
						[
							'id'            =>
								[
									'diff_key'       => 'f16c81792be920eeddc603f50a908b27',
									'type'           => 'int',
									'prefix'         => 'account',
									'unsigned'       => true,
									'auto_increment' => true,
								],
							'client_id'     =>
								[
									'diff_key' => 'd5349fcccc71fa8104b8186bf4283b71',
									'type'     => 'ref:clients.id',
									'prefix'   => 'account',
									'unsigned' => true,
								],
							'label'         =>
								[
									'diff_key' => '9372f39b892f72078cab0d1162a47ade',
									'type'     => 'string',
									'prefix'   => 'account',
									'min'      => 1,
									'max'      => 60,
								],
							'balance'       =>
								[
									'diff_key' => '3f96f1ef2286c552ab1a276e7f889dbe',
									'type'     => 'decimal',
									'prefix'   => 'account',
									'unsigned' => true,
									'default'  => 0,
								],
							'currency_code' =>
								[
									'diff_key' => '75aaa971f2ac690013793503dece0088',
									'type'     => 'ref:currencies.code',
									'prefix'   => 'account',
									'min'      => 1,
									'max'      => 30,
								],
							'data'          =>
								[
									'diff_key' => 'a8829d3489ee2b4e29427c2e6233427b',
									'type'     => 'map',
									'prefix'   => 'account',
									'default'  =>
										[
										],
								],
							'created_at'    =>
								[
									'diff_key' => '7e427d6b6eb7ef56eb81898086feeeec',
									'type'     => 'date',
									'prefix'   => 'account',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at'    =>
								[
									'diff_key' => '1b42b3dace897c58ab390c2199bdca18',
									'type'     => 'date',
									'prefix'   => 'account',
									'format'   => 'timestamp',
									'nullable' => true,
								],
							'valid'         =>
								[
									'diff_key' => 'a2d79b3ac9698eb5e8de0da51754b569',
									'type'     => 'bool',
									'prefix'   => 'account',
									'strict'   => true,
									'default'  => 1,
								],
						],
					'constraints'   =>
						[
							0 =>
								[
									'type'    => 'primary_key',
									'columns' =>
										[
											0 => 'id',
										],
								],
							1 =>
								[
									'type'    => 'unique_key',
									'columns' =>
										[
											0 => 'client_id',
											1 => 'currency_code',
										],
								],
							2 =>
								[
									'type'      => 'foreign_key',
									'reference' => 'clients',
									'columns'   =>
										[
											'client_id' => 'id',
										],
									'update'    => 'none',
									'delete'    => 'none',
								],
							3 =>
								[
									'type'      => 'foreign_key',
									'reference' => 'currencies',
									'columns'   =>
										[
											'currency_code' => 'code',
										],
									'update'    => 'none',
									'delete'    => 'none',
								],
						],
					'relations'     =>
						[
							'client' =>
								[
									'type'   => 'many-to-one',
									'target' => 'clients',
									'link'   =>
										[
											'type' => 'columns',
										],
								],
						],
				],
			'currencies' =>
				[
					'diff_key'      => 'e758ad7fa1fb421be77e6454f2b8467d',
					'singular_name' => 'currency',
					'plural_name'   => 'currencies',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'currency',
					'columns'       =>
						[
							'code'       =>
								[
									'diff_key' => 'b9af506bc4582aaa5aab4cc9c7ae4851',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 30,
								],
							'name'       =>
								[
									'diff_key' => '446c3561631b6fd97462d5ec76652ff7',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 60,
								],
							'symbol'     =>
								[
									'diff_key' => '3656b3838a7c6389515e552365542323',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 6,
								],
							'data'       =>
								[
									'diff_key' => '7f51d37bedf804b73349cdaa1807792a',
									'type'     => 'map',
									'prefix'   => 'currency',
									'default'  =>
										[
										],
								],
							'created_at' =>
								[
									'diff_key' => 'b4fa36786bbbea87083ef9a111dc7f9a',
									'type'     => 'date',
									'prefix'   => 'currency',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at' =>
								[
									'diff_key' => '5c6808d8c1c7d940cb94525cae54fc96',
									'type'     => 'date',
									'prefix'   => 'currency',
									'format'   => 'timestamp',
									'nullable' => true,
								],
							'valid'      =>
								[
									'diff_key' => '0dfe2698a1e9953319885da7e6657edf',
									'type'     => 'bool',
									'prefix'   => 'currency',
									'strict'   => true,
									'default'  => 1,
								],
						],
					'constraints'   =>
						[
							0 =>
								[
									'type'    => 'unique_key',
									'columns' =>
										[
											0 => 'code',
										],
								],
						],
				],
			'orders'     =>
				[
					'diff_key'      => 'c135c4369298cb53f8b920d19c3af051',
					'singular_name' => 'order',
					'plural_name'   => 'orders',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'order',
					'columns'       =>
						[
							'id'         =>
								[
									'diff_key'       => '317447e806708aed69cd1184797f3ee4',
									'type'           => 'int',
									'prefix'         => 'order',
									'unsigned'       => true,
									'auto_increment' => true,
								],
							'data'       =>
								[
									'diff_key' => 'e8178ca62d2c974c6549db9c9f6c08f8',
									'type'     => 'map',
									'prefix'   => 'order',
									'default'  =>
										[
										],
								],
							'created_at' =>
								[
									'diff_key' => 'ccb1a770be01a7c8de14c006bc8f27df',
									'type'     => 'date',
									'prefix'   => 'order',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at' =>
								[
									'diff_key' => '25372b746d1ded4b100c018c60fac940',
									'type'     => 'date',
									'prefix'   => 'order',
									'format'   => 'timestamp',
									'nullable' => true,
								],
							'valid'      =>
								[
									'diff_key' => '3dccd2bc02f30936ed2c05ff3feb2d31',
									'type'     => 'bool',
									'prefix'   => 'order',
									'strict'   => true,
									'default'  => 1,
								],
						],
					'constraints'   =>
						[
							0 =>
								[
									'type'    => 'primary_key',
									'columns' =>
										[
											0 => 'id',
										],
								],
						],
				],
		];
	}
};
