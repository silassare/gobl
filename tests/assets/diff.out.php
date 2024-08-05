<?php
/**
 * Generated on: 5th August 2024, 4:23:36 pm
 */
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface {
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
		return <<<DIFF_LABEL
		Auto generated.
		DIFF_LABEL;

	}

	/**
	 * @inheritDoc
	 */
	public function getTimestamp(): int
	{
		return 1722875016;
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
		ALTER TABLE `gObL_transactions` DROP INDEX uc_gObL_transactions_b8af13ea9c8fe890c9979a1fa8dbde22;
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



		ALTER TABLE `gObL_accounts` ADD CONSTRAINT uc_gObL_accounts_f9913917ef012372103dd1a027b2cd6a UNIQUE (`account_client_id` , `account_currency_code`);
		ALTER TABLE `gObL_currencies` ADD CONSTRAINT uc_gObL_currencies_c13367945d5d4c91047b3b50234aa7ab UNIQUE (`currency_code`);
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
		ALTER TABLE `gObL_accounts` DROP INDEX uc_gObL_accounts_f9913917ef012372103dd1a027b2cd6a;
		-- unique key constraint deleted
		ALTER TABLE `gObL_currencies` DROP INDEX uc_gObL_currencies_c13367945d5d4c91047b3b50234aa7ab;
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
		ALTER TABLE `gObL_transactions` ADD CONSTRAINT uc_gObL_transactions_b8af13ea9c8fe890c9979a1fa8dbde22 UNIQUE (`transaction_reference`);
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
		return array(
			'db_table_prefix' => 'gObL',
			'db_host'         => '***',
			'db_name'         => '***',
			'db_user'         => '***',
			'db_pass'         => '***',
			'db_charset'      => 'utf8mb4',
			'db_collate'      => 'utf8mb4_unicode_ci',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSchema(): array
	{
		return array(
			'clients'    =>
				array(
					'diff_key'      => 'f0db55288a085e6a9e8dd11c4cdbb2a8',
					'singular_name' => 'client',
					'plural_name'   => 'clients',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'client',
					'columns'       =>
						array(
							'id'         =>
								array(
									'diff_key'       => 'f6bee1acf32fcf1b3b9107c2c2a58b7a',
									'type'           => 'int',
									'prefix'         => 'client',
									'unsigned'       => true,
									'auto_increment' => true,
								),
							'given_name' =>
								array(
									'diff_key' => 'c179b3e8f5a2df22a61f763d9fca6db6',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
								),
							'name'       =>
								array(
									'diff_key' => '432186508f260c0d702ee3556f6392a6',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
								),
							'gender'     =>
								array(
									'diff_key' => 'bcd2020e9ae327b3f047b6597dd2e013',
									'type'     => 'string',
									'prefix'   => 'client',
									'min'      => 1,
									'max'      => 60,
									'one_of'   =>
										array(
											0 => 'male',
											1 => 'female',
											2 => 'unknown',
										),
								),
							'data'       =>
								array(
									'diff_key' => '1307ad18bdac5eb7f6ccc97f5698c538',
									'type'     => 'map',
									'prefix'   => 'client',
									'default'  =>
										array(),
								),
							'created_at' =>
								array(
									'diff_key' => 'f429942cb32a017dce36a141585d1a82',
									'type'     => 'date',
									'prefix'   => 'client',
									'auto'     => true,
									'format'   => 'timestamp',
								),
							'updated_at' =>
								array(
									'diff_key' => '8aa56f04c175ca5aebbb971d97a04a39',
									'type'     => 'date',
									'prefix'   => 'client',
									'format'   => 'timestamp',
									'nullable' => true,
								),
							'valid'      =>
								array(
									'diff_key' => 'a4a0975d84293e65be83e9f0fc597b36',
									'type'     => 'bool',
									'prefix'   => 'client',
									'strict'   => true,
									'default'  => 1,
								),
						),
					'constraints'   =>
						array(
							0 =>
								array(
									'type'    => 'primary_key',
									'columns' =>
										array(
											0 => 'id',
										),
								),
						),
					'relations'     =>
						array(
							'accounts' =>
								array(
									'type'   => 'one-to-many',
									'target' => 'accounts',
									'link'   =>
										array(
											'type' => 'columns',
										),
								),
						),
				),
			'accounts'   =>
				array(
					'diff_key'      => '8d62653147ceb29030e9fad78aa2bea3',
					'singular_name' => 'account',
					'plural_name'   => 'accounts',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'account',
					'columns'       =>
						array(
							'id'            =>
								array(
									'diff_key'       => 'f1559ac3960e234ac2c4d0b21a4928aa',
									'type'           => 'int',
									'prefix'         => 'account',
									'unsigned'       => true,
									'auto_increment' => true,
								),
							'client_id'     =>
								array(
									'diff_key' => '63dd885880c41fc95e36f1ae15cc62b7',
									'type'     => 'ref:clients.id',
									'prefix'   => 'account',
									'unsigned' => true,
								),
							'label'         =>
								array(
									'diff_key' => '85c575d81a6ce046158852eeeeae7bad',
									'type'     => 'string',
									'prefix'   => 'account',
									'min'      => 1,
									'max'      => 60,
								),
							'balance'       =>
								array(
									'diff_key' => 'bc2dbb578c54759c452a7a6d103dc1f5',
									'type'     => 'decimal',
									'prefix'   => 'account',
									'unsigned' => true,
									'default'  => 0,
								),
							'currency_code' =>
								array(
									'diff_key' => 'bc6f2f93fa97a804998cb496ae428e7f',
									'type'     => 'ref:currencies.code',
									'prefix'   => 'account',
									'min'      => 1,
									'max'      => 30,
								),
							'data'          =>
								array(
									'diff_key' => 'dbd49fbba56784b3ef6d8c8529563a64',
									'type'     => 'map',
									'prefix'   => 'account',
									'default'  =>
										array(),
								),
							'created_at'    =>
								array(
									'diff_key' => '66d75847131514840d942ad663d2204b',
									'type'     => 'date',
									'prefix'   => 'account',
									'auto'     => true,
									'format'   => 'timestamp',
								),
							'updated_at'    =>
								array(
									'diff_key' => '68e8acc0d1ba559dca353b76582c8cec',
									'type'     => 'date',
									'prefix'   => 'account',
									'format'   => 'timestamp',
									'nullable' => true,
								),
							'valid'         =>
								array(
									'diff_key' => 'de03abdc6c4efd74ace2bfded813e461',
									'type'     => 'bool',
									'prefix'   => 'account',
									'strict'   => true,
									'default'  => 1,
								),
						),
					'constraints'   =>
						array(
							0 =>
								array(
									'type'    => 'primary_key',
									'columns' =>
										array(
											0 => 'id',
										),
								),
							1 =>
								array(
									'type'    => 'unique_key',
									'columns' =>
										array(
											0 => 'client_id',
											1 => 'currency_code',
										),
								),
							2 =>
								array(
									'type'      => 'foreign_key',
									'reference' => 'clients',
									'columns'   =>
										array(
											'client_id' => 'id',
										),
									'update'    => 'none',
									'delete'    => 'none',
								),
							3 =>
								array(
									'type'      => 'foreign_key',
									'reference' => 'currencies',
									'columns'   =>
										array(
											'currency_code' => 'code',
										),
									'update'    => 'none',
									'delete'    => 'none',
								),
						),
					'relations'     =>
						array(
							'client' =>
								array(
									'type'   => 'many-to-one',
									'target' => 'clients',
									'link'   =>
										array(
											'type' => 'columns',
										),
								),
						),
				),
			'currencies' =>
				array(
					'diff_key'      => '01a0afa5ef2680f4b131e777823fd2e5',
					'singular_name' => 'currency',
					'plural_name'   => 'currencies',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'currency',
					'columns'       =>
						array(
							'code'       =>
								array(
									'diff_key' => 'd1ba58eb2d593c13376bc5d30bd1f8b0',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 30,
								),
							'name'       =>
								array(
									'diff_key' => '321d13b0ab56fff94988d3df0e28a8db',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 60,
								),
							'symbol'     =>
								array(
									'diff_key' => 'bbd6fa54beb7504e511897e805b255df',
									'type'     => 'string',
									'prefix'   => 'currency',
									'min'      => 1,
									'max'      => 6,
								),
							'data'       =>
								array(
									'diff_key' => '5bce1e5bbd3835222adb7aca89af8ea6',
									'type'     => 'map',
									'prefix'   => 'currency',
									'default'  =>
										array(),
								),
							'created_at' =>
								array(
									'diff_key' => '5de8543d9efa289e328ffc59f63928f4',
									'type'     => 'date',
									'prefix'   => 'currency',
									'auto'     => true,
									'format'   => 'timestamp',
								),
							'updated_at' =>
								array(
									'diff_key' => 'bae763827fa1592b554dfbc04e4625fe',
									'type'     => 'date',
									'prefix'   => 'currency',
									'format'   => 'timestamp',
									'nullable' => true,
								),
							'valid'      =>
								array(
									'diff_key' => 'd1eb588d180839b58892fbbd3c84ca97',
									'type'     => 'bool',
									'prefix'   => 'currency',
									'strict'   => true,
									'default'  => 1,
								),
						),
					'constraints'   =>
						array(
							0 =>
								array(
									'type'    => 'unique_key',
									'columns' =>
										array(
											0 => 'code',
										),
								),
						),
				),
			'orders'     =>
				array(
					'diff_key'      => '15c13f44bc885822ef54fc5e5c5c472f',
					'singular_name' => 'order',
					'plural_name'   => 'orders',
					'namespace'     => 'Test',
					'prefix'        => 'gObL',
					'column_prefix' => 'order',
					'columns'       =>
						array(
							'id'         =>
								array(
									'diff_key'       => '4fac3b44c60461ca566ec78787fba9d9',
									'type'           => 'int',
									'prefix'         => 'order',
									'unsigned'       => true,
									'auto_increment' => true,
								),
							'data'       =>
								array(
									'diff_key' => 'de1d1c4d9b379f08523a67e99c7497fd',
									'type'     => 'map',
									'prefix'   => 'order',
									'default'  =>
										array(),
								),
							'created_at' =>
								array(
									'diff_key' => '4c957f902267077da7209ec61871743b',
									'type'     => 'date',
									'prefix'   => 'order',
									'auto'     => true,
									'format'   => 'timestamp',
								),
							'updated_at' =>
								array(
									'diff_key' => '9442e929759fc9bd3a1a65d48c7ab97d',
									'type'     => 'date',
									'prefix'   => 'order',
									'format'   => 'timestamp',
									'nullable' => true,
								),
							'valid'      =>
								array(
									'diff_key' => 'a142883c23ec9ae858db01444643ee52',
									'type'     => 'bool',
									'prefix'   => 'order',
									'strict'   => true,
									'default'  => 1,
								),
						),
					'constraints'   =>
						array(
							0 =>
								array(
									'type'    => 'primary_key',
									'columns' =>
										array(
											0 => 'id',
										),
								),
						),
				),
		);
	}
};
