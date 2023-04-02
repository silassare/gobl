<?php
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface
{
	/**
	 * @inheritDoc
	 */
	public function getVersion(): int
	{
		// Created at: 2023-04-02T20:31:53+00:00
		return 1680467513;
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
					'diff_key' => '727829a2e9dcb8dbaaea147f73eb736c',
					'singular_name' => 'client',
					'plural_name' => 'clients',
					'prefix' => 'gObL',
					'column_prefix' => 'client',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '953c321be2e06962b1a14731a55f1ba9',
									'type' => 'int',
									'prefix' => 'client',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'given_name' =>
								array (
									'diff_key' => 'bd267838929305dda33e2d0653b239e6',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'name' =>
								array (
									'diff_key' => '51f03d9cc3fb3b03ca6107b489e84826',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'gender' =>
								array (
									'diff_key' => 'df5fc0b64125ff422b8209ec806025c3',
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
									'diff_key' => 'ec5c64e8a9ad2cd1627fdf0bfbc52be1',
									'type' => 'map',
									'prefix' => 'client',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'f6c4a5355a40fc02043e5804d7bab418',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '1e5da9097b3e862d69b1e53ae847aad1',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'd7526f1a19d330b633f25fce9bd2f353',
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
					'diff_key' => 'd361d23bd7714661277c61cc8668116c',
					'singular_name' => 'account',
					'plural_name' => 'accounts',
					'prefix' => 'gObL',
					'column_prefix' => 'account',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '34233b4dd8c404bdb916a15c9246c484',
									'type' => 'int',
									'prefix' => 'account',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'client_id' =>
								array (
									'diff_key' => 'c4d89eee74b9b79f92305a3c114e26d7',
									'type' => 'ref:clients.id',
									'prefix' => 'account',
								),
							'label' =>
								array (
									'diff_key' => '548b36d692aeb4970b3a80ed4efe0720',
									'type' => 'string',
									'prefix' => 'account',
									'min' => 1,
									'max' => 60,
								),
							'balance' =>
								array (
									'diff_key' => '2b80eec55538fe7e014ea42797715eca',
									'type' => 'decimal',
									'prefix' => 'account',
									'unsigned' => true,
									'default' => 0,
								),
							'currency_code' =>
								array (
									'diff_key' => '6d6e6dc31bfa190d99d13b4497ce20bd',
									'type' => 'ref:currencies.code',
									'prefix' => 'account',
								),
							'data' =>
								array (
									'diff_key' => 'a8d3643fe74b1d151798bb4036b977a4',
									'type' => 'map',
									'prefix' => 'account',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '1bfcde45d334fd044a3731182f9e7179',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '8737ad3a6f6b2656701bf666ecc69e96',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'b32da3f082f31fd3613ff0af4e13e70d',
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
					'diff_key' => '20ebe59765bc6cfe63874ffc13648ea8',
					'singular_name' => 'currency',
					'plural_name' => 'currencies',
					'prefix' => 'gObL',
					'column_prefix' => 'currency',
					'columns' =>
						array (
							'code' =>
								array (
									'diff_key' => '77c62776012b89388fd58b05d5e9925f',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 30,
								),
							'name' =>
								array (
									'diff_key' => '1778be0d9ba53ebf7ae33fa6ac1ab010',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 60,
								),
							'symbol' =>
								array (
									'diff_key' => '4459045fb1ebb1ecdc17a8a12a17c3b3',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 6,
								),
							'data' =>
								array (
									'diff_key' => 'ff88db8a7a3a388d3ac6baaff93586d9',
									'type' => 'map',
									'prefix' => 'currency',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '62bc0446878571a6e9460711e1037baa',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '26b55bd0d417a88690853806f78f20e1',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => '63107240f52a8bee89f38d229ce113db',
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
					'diff_key' => 'cabd1fef72c71f92a183b94e4d1cb7b7',
					'singular_name' => 'order',
					'plural_name' => 'orders',
					'prefix' => 'gObL',
					'column_prefix' => 'order',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '104fd192146c756088d453cbb5d6bfca',
									'type' => 'int',
									'prefix' => 'order',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'data' =>
								array (
									'diff_key' => '3305e467bc7b6bc10512fbc3d57f166f',
									'type' => 'map',
									'prefix' => 'order',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '32c80f52a6c35c857b089f0ea23c2fc1',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '697894dce99b19545fb7d7f5a3ab59df',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'e0f370d24b8e72f15447b28cc8c6a568',
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
