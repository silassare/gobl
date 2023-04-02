<?php
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface
{
	/**
	 * @inheritDoc
	 */
	public function getTimestamp(): int
	{
		// Created at: 2023-04-02T19:52:46+00:00
		return 1680465166;
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
			'db_host' => 'localhost',
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
					'diff_key' => '867c1f476cd8f98af8763ccacd0c8b66',
					'singular_name' => 'client',
					'plural_name' => 'clients',
					'prefix' => 'gObL',
					'column_prefix' => 'client',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => 'fc49aaa405c71750e9239645001f6624',
									'type' => 'int',
									'prefix' => 'client',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'given_name' =>
								array (
									'diff_key' => '9d558df4603193df1c73c1557da0e182',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'name' =>
								array (
									'diff_key' => 'd66a9d07d5ed22b6318a240ca5347ac0',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'gender' =>
								array (
									'diff_key' => 'cc66bd97250c43b4853fce3fbe758e26',
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
									'diff_key' => 'bad41dc84cd5429b53d9b0745bf7a94f',
									'type' => 'map',
									'prefix' => 'client',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'cc84a93471af9d8eae8e8f12255235e2',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '16b362a0c732745107e64e9b61edc6c6',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'cb6185575202aa3fc86cf71d6f7960c2',
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
					'diff_key' => '2bab5352abf912a392b5c069cb652194',
					'singular_name' => 'account',
					'plural_name' => 'accounts',
					'prefix' => 'gObL',
					'column_prefix' => 'account',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => 'd640a655003c02efb7523dc0e25e4695',
									'type' => 'int',
									'prefix' => 'account',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'client_id' =>
								array (
									'diff_key' => '31bcd812130e0080def5d78b7636228f',
									'type' => 'ref:clients.id',
									'prefix' => 'account',
								),
							'label' =>
								array (
									'diff_key' => '963d21d19c5cedb359d3b35cde249ec9',
									'type' => 'string',
									'prefix' => 'account',
									'min' => 1,
									'max' => 60,
								),
							'balance' =>
								array (
									'diff_key' => '541c3e92a2c24c3c49e7440c75386ed8',
									'type' => 'decimal',
									'prefix' => 'account',
									'unsigned' => true,
									'default' => 0,
								),
							'currency_code' =>
								array (
									'diff_key' => 'aa403def1093f0c977a1c5e821f932f2',
									'type' => 'ref:currencies.code',
									'prefix' => 'account',
								),
							'data' =>
								array (
									'diff_key' => '80fee3746e36c11a9760171e64c04dd9',
									'type' => 'map',
									'prefix' => 'account',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '13509fed73b4f40ea5e1d33fe0f55375',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '2bd340fac91c4ed444410aad5f5f542e',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'beb8ab3625d40c737f22c105c39ff1c0',
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
					'diff_key' => '81d9dca2e23358c5fdbd6b421040d6fb',
					'singular_name' => 'currency',
					'plural_name' => 'currencies',
					'prefix' => 'gObL',
					'column_prefix' => 'currency',
					'columns' =>
						array (
							'code' =>
								array (
									'diff_key' => 'f670c0466b5a1723809528364c7406eb',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 30,
								),
							'name' =>
								array (
									'diff_key' => '02e83edd532e11c2f4efa137979e9bcc',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 60,
								),
							'symbol' =>
								array (
									'diff_key' => 'd62f275bc11e8f3973a7018975af23e9',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 6,
								),
							'data' =>
								array (
									'diff_key' => '38f247696e7ca9420ae97bb720d8ae1e',
									'type' => 'map',
									'prefix' => 'currency',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'f83dbba54b8fbf6131d8a310a6efabc3',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '5bece15d5b4e0ec83b89124435a96c78',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'bc9b5a58a5a7b113cce7513cc3cd4cbb',
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
					'diff_key' => '1f747b469bbee8e083bcb12e846c7a7e',
					'singular_name' => 'order',
					'plural_name' => 'orders',
					'prefix' => 'gObL',
					'column_prefix' => 'order',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '055562694964ccf3845a48fcbbdb8689',
									'type' => 'int',
									'prefix' => 'order',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'data' =>
								array (
									'diff_key' => '3485d84fd075d33a0f3ff3665868dec7',
									'type' => 'map',
									'prefix' => 'order',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'a2915cddadacdb0000d8d955b5087fb5',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'b4d400de9fc0e6e6dbb634bb4d3aad94',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => '8aa4548f18d0d0ad20101817384068c2',
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
