<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return new class() implements \Gobl\DBAL\Interfaces\MigrationInterface {
	/**
	 * {@inheritDoc}
	 */
	public function getTimestamp(): int
	{
		// Created at: 2022-05-05T22:38:40+00:00
		return 1651790320;
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): string
	{
		return <<<'DIFF_SQL'
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
	 * {@inheritDoc}
	 */
	public function down(): string
	{
		return <<<'DIFF_SQL'
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
};
