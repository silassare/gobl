<?php
/**
 * Generated on: 10th April 2023, 11:00 am
 */
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface
{
	/**
	 * @inheritDoc
	 */
	public function getVersion(): int
	{
		return 1681124420;
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
		return 1681124420;
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
					'diff_key' => '17cb9313deb9b410961c537437c489df',
					'singular_name' => 'client',
					'plural_name' => 'clients',
					'prefix' => 'gObL',
					'column_prefix' => 'client',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '68d2be055ad01c6ad06874c2aa9d4bd9',
									'type' => 'int',
									'prefix' => 'client',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'given_name' =>
								array (
									'diff_key' => 'c561510e9ee848ebf8ed0008d823a606',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'name' =>
								array (
									'diff_key' => '1949f10b32c7b1bcf32e5785b65db05f',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'gender' =>
								array (
									'diff_key' => '73c7b65c35b1c257963ba38a8dfde6c9',
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
									'diff_key' => 'c5f9744e76212a1e895b715c466f3bae',
									'type' => 'map',
									'prefix' => 'client',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '86f215a5f9800724fe638b0567224340',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'a88a44a03d1d14b1adff1a34201422f5',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => '2558e4283a5054fd46d49372bcd10f0d',
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
					'diff_key' => 'bb4daf4481b064ec2260443a455a0ff1',
					'singular_name' => 'account',
					'plural_name' => 'accounts',
					'prefix' => 'gObL',
					'column_prefix' => 'account',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '1b6d18f0cd56305bd177f5199838247b',
									'type' => 'int',
									'prefix' => 'account',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'client_id' =>
								array (
									'diff_key' => '6985c64403c59660f5a58989c653d247',
									'type' => 'ref:clients.id',
									'prefix' => 'account',
								),
							'label' =>
								array (
									'diff_key' => '2dab32f95bf60497d4a6e8de1970306c',
									'type' => 'string',
									'prefix' => 'account',
									'min' => 1,
									'max' => 60,
								),
							'balance' =>
								array (
									'diff_key' => '1b6feb5faaced97eb368271ff6bcd5a7',
									'type' => 'decimal',
									'prefix' => 'account',
									'unsigned' => true,
									'default' => 0,
								),
							'currency_code' =>
								array (
									'diff_key' => '5ab7b31890b892d5cd39e7911bd665e9',
									'type' => 'ref:currencies.code',
									'prefix' => 'account',
								),
							'data' =>
								array (
									'diff_key' => '9e8b06734c7b1ea106274a8ad0faeb05',
									'type' => 'map',
									'prefix' => 'account',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '393d8083d006e223a0eb852c409e839f',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'b11eaafacb255bc229a7f7adf22df547',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'db3b7218e9d307fae3f3215dbb8f8000',
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
					'diff_key' => '38fd4efa15b344a546e0143e21436e50',
					'singular_name' => 'currency',
					'plural_name' => 'currencies',
					'prefix' => 'gObL',
					'column_prefix' => 'currency',
					'columns' =>
						array (
							'code' =>
								array (
									'diff_key' => '6cebd6c70a3cdc2624dd8905b9036ee9',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 30,
								),
							'name' =>
								array (
									'diff_key' => '547b94f37222c02aa9960d2793d0eb02',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 60,
								),
							'symbol' =>
								array (
									'diff_key' => '17cb9ae36ec67ce792ebba2dc4fd0cc8',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 6,
								),
							'data' =>
								array (
									'diff_key' => 'b35ce338df03ff595b2d8fb408a199fb',
									'type' => 'map',
									'prefix' => 'currency',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'c40b839db4a7aba28b03d1af7c1278d0',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'e29526ff1c041751a3a8ba3c1cc01988',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => '67765edbc147bc5460740b7702d25337',
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
					'diff_key' => 'f8e402de7459d7e7a19441087052e7fa',
					'singular_name' => 'order',
					'plural_name' => 'orders',
					'prefix' => 'gObL',
					'column_prefix' => 'order',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => 'f231ca611ba32ca527c7fc94d8a6c46e',
									'type' => 'int',
									'prefix' => 'order',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'data' =>
								array (
									'diff_key' => 'ff6b327b928724f582a0b1059deeaa80',
									'type' => 'map',
									'prefix' => 'order',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'defbf5d1ccd36c7d9550c24aa8242888',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '919ad2de5acbc38297376f44e169b102',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'db229bf31e0e347e7b10eb3ab1df0416',
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
