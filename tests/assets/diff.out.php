<?php
/**
 * Generated on: 27th March 2025, 8:53:56 pm
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
		return 1743108836;
	}

	/**
	 * @inheritDoc
	 */
	public function beforeRun(\Gobl\DBAL\MigrationMode $mode, string $query): bool|string
	{
		// TODO: implement your custom logic here
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function afterRun(\Gobl\DBAL\MigrationMode $mode): void
	{
		// TODO: implement your custom logic here
	}

	/**
	 * @inheritDoc
	 */
	public function up(): string
	{
		return <<<DIFF_SQL
		/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
		/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
		/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
		/*!40101 SET NAMES utf8mb4 */;
		/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
		/*!40103 SET TIME_ZONE='+00:00' */;
		/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
		/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
		/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
		/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

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

		/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
		/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
		/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
		/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
		/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
		/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
		/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
		/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
		DIFF_SQL;

	}

	/**
	 * @inheritDoc
	 */
	public function down(): string
	{

		return <<<DIFF_SQL
		/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
		/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
		/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
		/*!40101 SET NAMES utf8mb4 */;
		/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
		/*!40103 SET TIME_ZONE='+00:00' */;
		/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
		/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
		/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
		/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

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

		/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
		/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
		/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
		/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
		/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
		/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
		/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
		/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
		DIFF_SQL;

	}

	public function getConfigs(): array
	{
		return array (
		  'db_table_prefix' => 'gObL',
		  'db_host' => '***',
		  'db_port' => '***',
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
		        'diff_key' => '8a3fa5c5ecf4de66ba45318910dfd4c2',
		        'type' => 'int',
		        'prefix' => 'client',
		        'unsigned' => true,
		        'auto_increment' => true,
		      ),
		      'given_name' =>
		      array (
		        'diff_key' => 'd9d22201a2faa0d079d1c8d558077c77',
		        'type' => 'string',
		        'prefix' => 'client',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'name' =>
		      array (
		        'diff_key' => 'b6102666e48fc306f005670d237476b3',
		        'type' => 'string',
		        'prefix' => 'client',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'gender' =>
		      array (
		        'diff_key' => 'eea28337b476ba89c52b71dac3396993',
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
		        'diff_key' => '332ce863978d8ff5bf34e91c7b31198e',
		        'type' => 'map',
		        'prefix' => 'client',
		        'default' =>
		        array (
		        ),
		      ),
		      'created_at' =>
		      array (
		        'diff_key' => '64e95ee82db45e34a6c94bb5677be795',
		        'type' => 'date',
		        'prefix' => 'client',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' =>
		      array (
		        'diff_key' => '650df0130fbdfd254bd531306d54fc48',
		        'type' => 'date',
		        'prefix' => 'client',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' =>
		      array (
		        'diff_key' => '7ccd35ad12c78d6fbdac3f3771f4a1ae',
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
		        'diff_key' => '928b82919baa6be1e95acfc6a1e45c27',
		        'type' => 'int',
		        'prefix' => 'account',
		        'unsigned' => true,
		        'auto_increment' => true,
		      ),
		      'client_id' =>
		      array (
		        'diff_key' => '471cc190c00e52b5a8cd348afc543960',
		        'type' => 'ref:clients.id',
		        'prefix' => 'account',
		        'unsigned' => true,
		      ),
		      'label' =>
		      array (
		        'diff_key' => 'bbac6bef1a874e62afda12a1aa778f3d',
		        'type' => 'string',
		        'prefix' => 'account',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'balance' =>
		      array (
		        'diff_key' => '871170dfc8df1d93e44ea59a15838fd6',
		        'type' => 'decimal',
		        'prefix' => 'account',
		        'unsigned' => true,
		        'default' => 0,
		      ),
		      'currency_code' =>
		      array (
		        'diff_key' => 'abef8b546f6ba90bcc3142ad5322a69a',
		        'type' => 'ref:currencies.code',
		        'prefix' => 'account',
		        'min' => 1,
		        'max' => 30,
		      ),
		      'data' =>
		      array (
		        'diff_key' => '445538a0da046d517d00ceb84dab1128',
		        'type' => 'map',
		        'prefix' => 'account',
		        'default' =>
		        array (
		        ),
		      ),
		      'created_at' =>
		      array (
		        'diff_key' => '441033768f6dea44814e7f58680f9de5',
		        'type' => 'date',
		        'prefix' => 'account',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' =>
		      array (
		        'diff_key' => 'f0d8e7c4d25631225680fc05cf08ff83',
		        'type' => 'date',
		        'prefix' => 'account',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' =>
		      array (
		        'diff_key' => '6789f0fe54e0db688b2345c256bf516c',
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
		        'diff_key' => '631ee781dbf7808983f848e41bed0caf',
		        'type' => 'string',
		        'prefix' => 'currency',
		        'min' => 1,
		        'max' => 30,
		      ),
		      'name' =>
		      array (
		        'diff_key' => 'b7bba2cd374dd74750d88807d93d3d39',
		        'type' => 'string',
		        'prefix' => 'currency',
		        'min' => 1,
		        'max' => 60,
		      ),
		      'symbol' =>
		      array (
		        'diff_key' => '90a33b66db88118579e365cc04f78c49',
		        'type' => 'string',
		        'prefix' => 'currency',
		        'min' => 1,
		        'max' => 6,
		      ),
		      'data' =>
		      array (
		        'diff_key' => '4a8dd829c11043ddc916703713ca15c7',
		        'type' => 'map',
		        'prefix' => 'currency',
		        'default' =>
		        array (
		        ),
		      ),
		      'created_at' =>
		      array (
		        'diff_key' => '78506f1b3050aaef226f4d80bfc05827',
		        'type' => 'date',
		        'prefix' => 'currency',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' =>
		      array (
		        'diff_key' => '50d227dd537920df0d6b6c152fd0761c',
		        'type' => 'date',
		        'prefix' => 'currency',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' =>
		      array (
		        'diff_key' => 'f109d5a3e587224baf23dbc7a17beff4',
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
		        'diff_key' => 'ccc833d6add056b7824285d9ccc07f3a',
		        'type' => 'int',
		        'prefix' => 'order',
		        'unsigned' => true,
		        'auto_increment' => true,
		      ),
		      'data' =>
		      array (
		        'diff_key' => 'c2caeedc10f06f5ccdcecb75b45adcfe',
		        'type' => 'map',
		        'prefix' => 'order',
		        'default' =>
		        array (
		        ),
		      ),
		      'created_at' =>
		      array (
		        'diff_key' => '0fc04af592fdbb725925fc986cc69cc7',
		        'type' => 'date',
		        'prefix' => 'order',
		        'auto' => true,
		        'format' => 'timestamp',
		      ),
		      'updated_at' =>
		      array (
		        'diff_key' => '908620f8677e420330537455e5b8852d',
		        'type' => 'date',
		        'prefix' => 'order',
		        'format' => 'timestamp',
		        'nullable' => true,
		      ),
		      'valid' =>
		      array (
		        'diff_key' => '52342c9d133ad7efa2a16ba81b5666cd',
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
