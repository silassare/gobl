<?php
/**
 * Generated on: 20th April 2023, 4:35:02 pm
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
		return 1682008502;
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
	 * @inheritDoc
	 */
	public function down(): string
	{
		return <<<DIFF_SQL
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
	 * @inheritDoc
	 */
	public function getTables(): array
	{
		return [
			'clients'    =>
				[
					'diff_key'      => 'a62f471a2cf722b02163d4e17baac2df',
					'singular_name' => 'client',
					'plural_name'   => 'clients',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'client',
					'columns'       =>
						[
							'id'         =>
								[
									'diff_key'       => '7e3fcd9ea31e5a29842ad16890210296',
									'type'           => 'int',
									'prefix'         => 'client',
									'unsigned'       => true,
									'auto_increment' => true,
								],
							'given_name' =>
								[
									'diff_key' => '772898b6a3f24fcdef13a0e7668d6b43',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
								],
							'name'       =>
								[
									'diff_key' => '0ead17a73c1f1b641434b5cac3380e9d',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
								],
							'gender'     =>
								[
									'diff_key' => 'd25adb5233b554402c5b3575b0327a5d',
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
									'diff_key' => '08e958cbfb421daa1b3e5152a2aa92bc',
									'type'     => 'map',
									'prefix'   => 'client',
									'default'  =>
										[
										],
								],
							'created_at' =>
								[
									'diff_key' => 'abfddb6b13b13864afaf3e97a248504f',
									'type'     => 'date',
									'prefix'   => 'client',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at' =>
								[
									'diff_key' => 'a87cdad32a42bc2697c16e3589462c39',
									'type'     => 'date',
									'prefix'   => 'client',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'valid'      =>
								[
									'diff_key' => '00b0937ea473f0a3acb9b9f2acee3fcd',
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
					'diff_key'      => '09a323cbbc430408970bbb896aa7191c',
					'singular_name' => 'account',
					'plural_name'   => 'accounts',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'account',
					'columns'       =>
						[
							'id'            =>
								[
									'diff_key'       => 'daf27c855ed5aedadba59b0128d954be',
									'type'           => 'int',
									'prefix'         => 'account',
									'unsigned'       => true,
									'auto_increment' => true,
								],
							'client_id'     =>
								[
									'diff_key' => '69e3e14cd53987898a206f82402e9605',
									'type'     => 'ref:clients.id',
									'prefix'   => 'account',
									'unsigned' => true,
								],
							'label'         =>
								[
									'diff_key' => 'efd23c342ac8773a8817e437ca6261a7',
									'type'     => 'string',
									'prefix'   => 'account',
									'min'      => 1,
									'max'      => 60,
								],
							'balance'       =>
								[
									'diff_key' => '935be222e43d5c95adaec7a1e9764648',
									'type'     => 'decimal',
									'prefix'   => 'account',
									'unsigned' => true,
									'default'  => 0,
								],
							'currency_code' =>
								[
									'diff_key' => 'bb1e01e7b6718f51235ea27a802a4acb',
									'type'     => 'ref:currencies.code',
									'prefix'   => 'account',
									'min'      => 1,
									'max'      => 30,
								],
							'data'          =>
								[
									'diff_key' => 'bf4bf7eefedd1185f7aa3ad4f885a6b8',
									'type'     => 'map',
									'prefix'   => 'account',
									'default'  =>
										[
										],
								],
							'created_at'    =>
								[
									'diff_key' => 'ce03550a05d8224d064834b7576da310',
									'type'     => 'date',
									'prefix'   => 'account',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at'    =>
								[
									'diff_key' => 'f868d99157b9ca799d1bdc7224e4bbba',
									'type'     => 'date',
									'prefix'   => 'account',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'valid'         =>
								[
									'diff_key' => '92301d92f025f2f3d233de6b739e7c7d',
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
					'diff_key'      => 'f404b744b11b451b477981f7c0d7c0c2',
					'singular_name' => 'currency',
					'plural_name'   => 'currencies',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'currency',
					'columns'       =>
						[
							'code'       =>
								[
									'diff_key' => '7d36def82d069c158916cfb9d0f70b5d',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 30,
								],
							'name'       =>
								[
									'diff_key' => '2c7b6e6a566ec177c7e8ac5248f5e0ad',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 60,
								],
							'symbol'     =>
								[
									'diff_key' => '6f6a14db6cfbf3d5946fdd314804816a',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 6,
								],
							'data'       =>
								[
									'diff_key' => '859e89c2f2c75866a198bbc433fd563b',
									'type'     => 'map',
									'prefix'   => 'currency',
									'default'  =>
										[
										],
								],
							'created_at' =>
								[
									'diff_key' => '836da1fab2249fcd2855128af8eec7f5',
									'type'     => 'date',
									'prefix'   => 'currency',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at' =>
								[
									'diff_key' => '6b8b5a47eb6036808fe1d16eb7d8c001',
									'type'     => 'date',
									'prefix'   => 'currency',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'valid'      =>
								[
									'diff_key' => '471c3e2a8551dab69d5b6bd72b9f1fb8',
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
					'diff_key'      => '738b6f125e5f092dc63f11de2ae4aca1',
					'singular_name' => 'order',
					'plural_name'   => 'orders',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'order',
					'columns'       =>
						[
							'id'         =>
								[
									'diff_key'       => '155fd8b66d899b13fb025a66aa2d9837',
									'type'           => 'int',
									'prefix'         => 'order',
									'unsigned'       => true,
									'auto_increment' => true,
								],
							'data'       =>
								[
									'diff_key' => '7a2942820af98b3526c48db3c46ec314',
									'type'     => 'map',
									'prefix'   => 'order',
									'default'  =>
										[
										],
								],
							'created_at' =>
								[
									'diff_key' => 'de0c35d2b124c6d5f3a4a178c611bd98',
									'type'     => 'date',
									'prefix'   => 'order',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'updated_at' =>
								[
									'diff_key' => '7fd079e85bbb1749951694084f754427',
									'type'     => 'date',
									'prefix'   => 'order',
									'auto'     => true,
									'format'   => 'timestamp',
								],
							'valid'      =>
								[
									'diff_key' => 'eb5617476da3d41b3c5a9a526d9d4651',
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
