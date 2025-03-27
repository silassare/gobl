<?php
/**
 * Generated on: 27th March 2025, 8:29:27 pm
 */
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface
{
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
		return 1743107367;
	}

	/**
	 * @inheritDoc
	 */
	public function beforeRun(Gobl\DBAL\MigrationMode $mode, string $query): bool|string
	{
		// TODO: implement your custom logic here
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function afterRun(Gobl\DBAL\MigrationMode $mode): void
	{
		// TODO: implement your custom logic here
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
	public function getSchema(): array
	{
		return array (
		  'clients' => 
		  array (
		    'diff_key' => 'f0db55288a085e6a9e8dd11c4cdbb2a8',
		    'singular_name' => 'client',
		    'plural_name' => 'clients',
		    'namespace' => 'Test',
		    'prefix' => 'gObL',
		    'column_prefix' => 'client',
		    'columns' => 
		    array (
		      'id' => 
		      array (
		        'diff_key' => 'a2625aa3c149b4bdfc18a045d33468a5',
		        'type' => 'int',
		        'prefix' => 'client',
		        'unsigned' => true,
		        'auto_increment' => true,
		      ),
		      'given_name' => 
		      array (
		        'diff_key' => 'cd95a612158b3928ec5c5cfec6a811d3',
		        'type' => 'string',
		        'prefix' => 'client',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'name' => 
		      array (
		        'diff_key' => '6ae63de85f0c8bbb599d9f0365324dd6',
		        'type' => 'string',
		        'prefix' => 'client',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'gender' => 
		      array (
		        'diff_key' => '0369a692bdfc44dc6e143af06e44f468',
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
		        'diff_key' => '3fe29a5d3c67800cddcc678125a822d0',
		        'type' => 'map',
		        'prefix' => 'client',
		        'default' => 
		        array (
		        ),
		      ),
		      'created_at' => 
		      array (
		        'diff_key' => 'db5f01d9376f91d0b07715dd59839811',
		        'type' => 'date',
		        'prefix' => 'client',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' => 
		      array (
		        'diff_key' => 'de6d8906aa81bbd7601039136dce7eb7',
		        'type' => 'date',
		        'prefix' => 'client',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' => 
		      array (
		        'diff_key' => '5823f63515abe0edba760819ab32715c',
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
		        'type' => 'one-to-many',
		        'target' => 'accounts',
		        'link' => 
		        array (
		          'type' => 'columns',
		        ),
		      ),
		    ),
		  ),
		  'accounts' => 
		  array (
		    'diff_key' => '8d62653147ceb29030e9fad78aa2bea3',
		    'singular_name' => 'account',
		    'plural_name' => 'accounts',
		    'namespace' => 'Test',
		    'prefix' => 'gObL',
		    'column_prefix' => 'account',
		    'columns' => 
		    array (
		      'id' => 
		      array (
		        'diff_key' => '3355bdbf6e7baf84d6ce33337f71b623',
		        'type' => 'int',
		        'prefix' => 'account',
		        'unsigned' => true,
		        'auto_increment' => true,
		      ),
		      'client_id' => 
		      array (
		        'diff_key' => '20f8b03b8a063580cb1eeb2d047304d8',
		        'type' => 'ref:clients.id',
		        'prefix' => 'account',
		        'unsigned' => true,
		      ),
		      'label' => 
		      array (
		        'diff_key' => 'def7d5ef810ca052e1e79593f75dc09e',
		        'type' => 'string',
		        'prefix' => 'account',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'balance' => 
		      array (
		        'diff_key' => 'b71d05241e4b6b4ba0426aa32a2b4a81',
		        'type' => 'decimal',
		        'prefix' => 'account',
		        'unsigned' => true,
		        'default' => 0,
		      ),
		      'currency_code' => 
		      array (
		        'diff_key' => '55876444c9d6add0c760c7393c01e96c',
		        'type' => 'ref:currencies.code',
		        'prefix' => 'account',
		        'min' => 1,
		        'max' => 30,
		      ),
		      'data' => 
		      array (
		        'diff_key' => '0dc49cd65cfd5ce14d166c05af9934f7',
		        'type' => 'map',
		        'prefix' => 'account',
		        'default' => 
		        array (
		        ),
		      ),
		      'created_at' => 
		      array (
		        'diff_key' => 'b93aaf7dc8fda451b70eb66404601d44',
		        'type' => 'date',
		        'prefix' => 'account',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' => 
		      array (
		        'diff_key' => '0da437c1d60bfdb22a279bf2c9f1d6bf',
		        'type' => 'date',
		        'prefix' => 'account',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' => 
		      array (
		        'diff_key' => '1fbbbefcd63bae47b83483b7438c4039',
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
		        'type' => 'many-to-one',
		        'target' => 'clients',
		        'link' => 
		        array (
		          'type' => 'columns',
		        ),
		      ),
		    ),
		  ),
		  'currencies' => 
		  array (
		    'diff_key' => '01a0afa5ef2680f4b131e777823fd2e5',
		    'singular_name' => 'currency',
		    'plural_name' => 'currencies',
		    'meta' => 
		    array (
		      'api' => 
		      array (
		        'doc' => 
		        array (
		          'singular_name' => 'Currency',
		          'plural_name' => 'Currencies',
		          'use_an' => false,
		          'description' => 'Currency is a system of money in general use in a particular country.',
		        ),
		      ),
		    ),
		    'namespace' => 'Test',
		    'prefix' => 'gObL',
		    'column_prefix' => 'currency',
		    'columns' => 
		    array (
		      'code' => 
		      array (
		        'diff_key' => '162322dbdc486f048e8f08ebfa9f6eba',
		        'type' => 'string',
		        'prefix' => 'currency',
		        'min' => 1,
		        'max' => 30,
		      ),
		      'name' => 
		      array (
		        'diff_key' => '8840d441aaf290033f1181610d17bd03',
		        'type' => 'string',
		        'prefix' => 'currency',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'symbol' => 
		      array (
		        'diff_key' => '7b0a5e4d5f3e97f1606db67c1a26a8b7',
		        'type' => 'string',
		        'prefix' => 'currency',
		        'min' => 1,
		        'max' => 6,
		      ),
		      'data' => 
		      array (
		        'diff_key' => 'ce10f165ac5ae807f600bbf16a7a31c6',
		        'type' => 'map',
		        'prefix' => 'currency',
		        'default' => 
		        array (
		        ),
		      ),
		      'created_at' => 
		      array (
		        'diff_key' => 'f56b22ed9834558416344bada4b77d4f',
		        'type' => 'date',
		        'prefix' => 'currency',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' => 
		      array (
		        'diff_key' => '095784aa754b33489f771bf544c29cdf',
		        'type' => 'date',
		        'prefix' => 'currency',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' => 
		      array (
		        'diff_key' => 'efa48ed8cadee500609dc1f3e73afc0b',
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
		    'diff_key' => '15c13f44bc885822ef54fc5e5c5c472f',
		    'singular_name' => 'order',
		    'plural_name' => 'orders',
		    'namespace' => 'Test',
		    'prefix' => 'gObL',
		    'column_prefix' => 'order',
		    'columns' => 
		    array (
		      'id' => 
		      array (
		        'diff_key' => 'e4282f94f65b6ba7f27d0156410f2d72',
		        'type' => 'int',
		        'prefix' => 'order',
		        'unsigned' => true,
		        'auto_increment' => true,
		      ),
		      'data' => 
		      array (
		        'diff_key' => 'a2d63ace507465247199e0b713ea82b4',
		        'type' => 'map',
		        'prefix' => 'order',
		        'default' => 
		        array (
		        ),
		      ),
		      'created_at' => 
		      array (
		        'diff_key' => 'fe8808a4fe8c512bab25c0204f7506be',
		        'type' => 'date',
		        'prefix' => 'order',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' => 
		      array (
		        'diff_key' => '7a26287330dcb8d4aabdbf985e29f48e',
		        'type' => 'date',
		        'prefix' => 'order',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' => 
		      array (
		        'diff_key' => 'e6f4bcf410d494d4e7f2ac4f2dd36611',
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
