<?php
/**
 * Generated on: 10th April 2023, 12:42:15 pm
 */
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface
{
	/**
	 * @inheritDoc
	 */
	public function getVersion(): int
	{
		return 1681130535;
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
		return 1681130535;
	}

	/**
	 * @inheritDoc
	 */
	public function up(): string
	{
		return <<<DIFF_SQL
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
	 * @inheritDoc
	 */
	public function down(): string
	{

		return <<<DIFF_SQL
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
		return array (
			'db_table_prefix' => 'gObL',
			'db_host' => '***',
			'db_name' => '***',
			'db_user' => '***',
			'db_pass' => '***',
			'db_charset' => 'utf8mb4',
			'db_collate' => 'utf8mb4_unicode_ci',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getTables(): array
	{
		return array (
			'clients' =>
				array (
					'diff_key' => '0041d889610ce664ead30858908520df',
					'singular_name' => 'client',
					'plural_name' => 'clients',
					'prefix' => 'gObL',
					'column_prefix' => 'client',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => 'b2ebac7a193a05432b36893c87fed573',
									'type' => 'int',
									'prefix' => 'client',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'given_name' =>
								array (
									'diff_key' => 'b85ad02461204cec1d6c62b729008b6d',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'name' =>
								array (
									'diff_key' => 'fe13027ab1a96c8ba776cc35d88b8e03',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'gender' =>
								array (
									'diff_key' => 'b1a8f810088415d42c798495d4292261',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
									'one_of' =>
										array (
											0 => 'male',
											1 => 'female',
											2 => 'unknown',
										),
								),
							'data' =>
								array (
									'diff_key' => '489c0b54b6f252611245d68f55586e12',
									'type' => 'map',
									'prefix' => 'client',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'd0af02fc9562f980c82d40908c5f58f3',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '31e7f963177ff83bc1f62a4483cad35b',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'ae1b47df64f4d125aff5af0c93c3c042',
									'type' => 'bool',
									'prefix' => 'client',
									'strict' => true,
									'default' => 1,
								),
						),
					'constraints' =>
						array (
							0 =>
								array (
									'type' => 'primary_key',
									'columns' =>
										array (
											0 => 'id',
										),
								),
						),
					'relations' =>
						array (
							'accounts' =>
								array (
									'target' => 'accounts',
									'type' => 'one-to-many',
								),
						),
				),
			'accounts' =>
				array (
					'diff_key' => 'fc3579b99093aa659711af57241ae044',
					'singular_name' => 'account',
					'plural_name' => 'accounts',
					'prefix' => 'gObL',
					'column_prefix' => 'account',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '4610f74ef99bfb2ffc351f75939db614',
									'type' => 'int',
									'prefix' => 'account',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'client_id' =>
								array (
									'diff_key' => '1530293573610f8a3927f5d7b720fabb',
									'type' => 'ref:clients.id',
									'prefix' => 'account',
									'unsigned' => true,
								),
							'label' =>
								array (
									'diff_key' => 'b540c9489cb52fd887e47588243072ce',
									'type' => 'string',
									'prefix' => 'account',
									'min' => 1,
									'max' => 60,
								),
							'balance' =>
								array (
									'diff_key' => '2a44ededca422e45eb0fbc93b2727154',
									'type' => 'decimal',
									'prefix' => 'account',
									'unsigned' => true,
									'default' => 0,
								),
							'currency_code' =>
								array (
									'diff_key' => '5bfd3f8482555a01fd40e8d21a18132c',
									'type' => 'ref:currencies.code',
									'prefix' => 'account',
									'min' => 1,
									'max' => 30,
								),
							'data' =>
								array (
									'diff_key' => '16d98051b6e4a64bf9824aee00923891',
									'type' => 'map',
									'prefix' => 'account',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'facf0ae7b050f8370e11252caba167d8',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '3f55ff7ea330ad5687f3bf649eb19336',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'a67a48433636ef7b40435afcb68719c0',
									'type' => 'bool',
									'prefix' => 'account',
									'strict' => true,
									'default' => 1,
								),
						),
					'constraints' =>
						array (
							0 =>
								array (
									'type' => 'primary_key',
									'columns' =>
										array (
											0 => 'id',
										),
								),
							1 =>
								array (
									'type' => 'unique_key',
									'columns' =>
										array (
											0 => 'client_id',
											1 => 'currency_code',
										),
								),
							2 =>
								array (
									'type' => 'foreign_key',
									'reference' => 'clients',
									'columns' =>
										array (
											'client_id' => 'id',
										),
									'update' => 'none',
									'delete' => 'none',
								),
							3 =>
								array (
									'type' => 'foreign_key',
									'reference' => 'currencies',
									'columns' =>
										array (
											'currency_code' => 'code',
										),
									'update' => 'none',
									'delete' => 'none',
								),
						),
					'relations' =>
						array (
							'client' =>
								array (
									'target' => 'clients',
									'type' => 'many-to-one',
								),
						),
				),
			'currencies' =>
				array (
					'diff_key' => '255b27061a99e576fc524508ea5fc970',
					'singular_name' => 'currency',
					'plural_name' => 'currencies',
					'prefix' => 'gObL',
					'column_prefix' => 'currency',
					'columns' =>
						array (
							'code' =>
								array (
									'diff_key' => '8911df00f3f8f2f1b8b3d216b5c1dd62',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 30,
								),
							'name' =>
								array (
									'diff_key' => '3b92012ec2c3eb776d03069d1e006e87',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 60,
								),
							'symbol' =>
								array (
									'diff_key' => 'a420c5ba9b7d6adc00dcef61fee0d2af',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 6,
								),
							'data' =>
								array (
									'diff_key' => 'a0a13f82c1f1969b71cd6943ae9aa211',
									'type' => 'map',
									'prefix' => 'currency',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '2e5e6b7ef9480db48f3646d7f3e7473b',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'ebbf25a7d5354f0cdc95871c832490b6',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => '99e236774863584e42ecd9c9c5370085',
									'type' => 'bool',
									'prefix' => 'currency',
									'strict' => true,
									'default' => 1,
								),
						),
					'constraints' =>
						array (
							0 =>
								array (
									'type' => 'unique_key',
									'columns' =>
										array (
											0 => 'code',
										),
								),
						),
				),
			'orders' =>
				array (
					'diff_key' => 'df2b284b606cfd33629b5fd5cd317cb8',
					'singular_name' => 'order',
					'plural_name' => 'orders',
					'prefix' => 'gObL',
					'column_prefix' => 'order',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => 'a44d4a580ee9497fe6ae585d7ce4a59c',
									'type' => 'int',
									'prefix' => 'order',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'data' =>
								array (
									'diff_key' => '5528a95e8240577a04419e6d187f9979',
									'type' => 'map',
									'prefix' => 'order',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '40f641797dba8bfb449bf265090ed5fa',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'd2d8eeb1243cd91f47a8e82940c5f514',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'c7721215b02e4927afacd05d655e2bc0',
									'type' => 'bool',
									'prefix' => 'order',
									'strict' => true,
									'default' => 1,
								),
						),
					'constraints' =>
						array (
							0 =>
								array (
									'type' => 'primary_key',
									'columns' =>
										array (
											0 => 'id',
										),
								),
						),
				),
		);
	}
};
