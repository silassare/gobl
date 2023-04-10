<?php
/**
 * Generated on: 10th April 2023, 11:08:36 am
 */
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface
{
	/**
	 * @inheritDoc
	 */
	public function getVersion(): int
	{
		return 1681124916;
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
		return 1681124916;
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
					'diff_key' => 'f9ac3c66693e089ead79f76cd08769da',
					'singular_name' => 'client',
					'plural_name' => 'clients',
					'prefix' => 'gObL',
					'column_prefix' => 'client',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '406095feef90ae87f20ec834f9c45340',
									'type' => 'int',
									'prefix' => 'client',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'given_name' =>
								array (
									'diff_key' => '982273b26836974f721bec9577e9e466',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'name' =>
								array (
									'diff_key' => '317158235ae05055b1e7895eb4d06060',
									'type' => 'string',
									'prefix' => 'client',
									'min' => 1,
									'max' => 60,
								),
							'gender' =>
								array (
									'diff_key' => '16f020d5b523c766bd20ca9c8a534ac3',
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
									'diff_key' => '372633b1f1b48951160cd95403313805',
									'type' => 'map',
									'prefix' => 'client',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '6ab15cb56f0b4a191ae90e8952c50fb9',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'e3ddccedf92d882cdfaf32fff1beac45',
									'type' => 'date',
									'prefix' => 'client',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'fea4d3bd3eb4a23a08d8b2a2a1e96a57',
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
					'diff_key' => '5fe2720f0dbd34d215e6a9abd662ddb4',
					'singular_name' => 'account',
					'plural_name' => 'accounts',
					'prefix' => 'gObL',
					'column_prefix' => 'account',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => '4b35e46a51ac8a696b32fc313a9e884c',
									'type' => 'int',
									'prefix' => 'account',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'client_id' =>
								array (
									'diff_key' => '156ac60a1a1e70f9d3667cbc860d8955',
									'type' => 'ref:clients.id',
									'prefix' => 'account',
								),
							'label' =>
								array (
									'diff_key' => 'bfde6da34ac3c466997604aede1889bf',
									'type' => 'string',
									'prefix' => 'account',
									'min' => 1,
									'max' => 60,
								),
							'balance' =>
								array (
									'diff_key' => 'd87a50a229ad92c3594bd79c9fc0593f',
									'type' => 'decimal',
									'prefix' => 'account',
									'unsigned' => true,
									'default' => 0,
								),
							'currency_code' =>
								array (
									'diff_key' => '864e7ba72579c57b3f77d907867227ba',
									'type' => 'ref:currencies.code',
									'prefix' => 'account',
								),
							'data' =>
								array (
									'diff_key' => 'c6adf1e591f2b68caaf126c9a64e385f',
									'type' => 'map',
									'prefix' => 'account',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => 'ffeffc6c9a79d4c8e950b52a10cf50a1',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '85292ecb988147719954f76e92ddfd7c',
									'type' => 'date',
									'prefix' => 'account',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => '2211241c5ec9aad9195996228a7aa9c4',
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
					'diff_key' => '55c7a625c994da01272583128953654f',
					'singular_name' => 'currency',
					'plural_name' => 'currencies',
					'prefix' => 'gObL',
					'column_prefix' => 'currency',
					'columns' =>
						array (
							'code' =>
								array (
									'diff_key' => '64a7259a57d4bcaef9feca07952ad76f',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 30,
								),
							'name' =>
								array (
									'diff_key' => '386e1f48453df714042ee430cabff29b',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 60,
								),
							'symbol' =>
								array (
									'diff_key' => '0f12e395f7c5d8d692630bcd70c680b3',
									'type' => 'string',
									'prefix' => 'currency',
									'min' => 1,
									'max' => 6,
								),
							'data' =>
								array (
									'diff_key' => '3396ecd4bc2f8e588c03777d96a5df4b',
									'type' => 'map',
									'prefix' => 'currency',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '2bb35f8914768ca49454b4eae806fe8c',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => '8879d17dfd34de09f169a2cb165e830f',
									'type' => 'date',
									'prefix' => 'currency',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => '5ca2c4e6f1d74923a0f2e761003e4193',
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
					'diff_key' => 'c82ef07e4349131684ca543c90ac22df',
					'singular_name' => 'order',
					'plural_name' => 'orders',
					'prefix' => 'gObL',
					'column_prefix' => 'order',
					'columns' =>
						array (
							'id' =>
								array (
									'diff_key' => 'b35773c53ca608e059c0110911a6fddf',
									'type' => 'int',
									'prefix' => 'order',
									'unsigned' => true,
									'auto_increment' => true,
								),
							'data' =>
								array (
									'diff_key' => 'e754c52fab7f11187ba3d04214c8c4e8',
									'type' => 'map',
									'prefix' => 'order',
									'default' =>
										array (
										),
								),
							'created_at' =>
								array (
									'diff_key' => '9283102a119fa2491e32fb2cd54497bd',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'updated_at' =>
								array (
									'diff_key' => 'd4168955a59af09e95cf0ef5d84a13db',
									'type' => 'date',
									'prefix' => 'order',
									'auto' => true,
									'format' => 'timestamp',
								),
							'valid' =>
								array (
									'diff_key' => 'f87b1d80280ec7dc4d686988d8404e82',
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
