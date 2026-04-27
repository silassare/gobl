-- >>>>@UP>>>>
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
ALTER TABLE `gObL_currencies` CHANGE `currency_data` `currency_data` json NOT NULL DEFAULT ('{}');
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
`order_data` json NOT NULL DEFAULT ('{}'),
`order_created_at` bigint(20) NOT NULL,
`order_updated_at` bigint(20) NULL DEFAULT NULL,
`order_valid` tinyint(1) NOT NULL DEFAULT '1',

--
-- Primary key constraints definition for table `gObL_orders`
--
CONSTRAINT pk_gObL_orders PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- unique key constraint added
ALTER TABLE `gObL_accounts` ADD CONSTRAINT uc_gObL_accounts_f9913917ef012372103dd1a027b2cd6a UNIQUE (`account_client_id` , `account_currency_code`);
-- unique key constraint added
ALTER TABLE `gObL_currencies` ADD CONSTRAINT uc_gObL_currencies_c13367945d5d4c91047b3b50234aa7ab UNIQUE (`currency_code`);
-- constraints column "account_client_id" type changed in host table "gObL_accounts".
ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_clients FOREIGN KEY (`account_client_id`) REFERENCES `gObL_clients` (`client_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
-- constraints column mapping changed.
ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY (`account_currency_code`) REFERENCES `gObL_currencies` (`currency_code`) ON UPDATE NO ACTION ON DELETE NO ACTION;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- >>>>@DOWN>>>>
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
ALTER TABLE `gObL_currencies` CHANGE `ccy_data` `ccy_data` json NOT NULL DEFAULT ('{}');
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
`transaction_data` json NOT NULL DEFAULT ('{}'),
`transaction_created_at` bigint(20) NOT NULL,
`transaction_updated_at` bigint(20) NOT NULL,
`transaction_valid` tinyint(1) NOT NULL DEFAULT '1',

--
-- Primary key constraints definition for table `gObL_transactions`
--
CONSTRAINT pk_gObL_transactions PRIMARY KEY (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- primary key constraint added
ALTER TABLE `gObL_currencies` ADD CONSTRAINT pk_gObL_currencies PRIMARY KEY (`ccy_code`);
-- table "gObL_transactions" was added.
ALTER TABLE `gObL_transactions` ADD CONSTRAINT uc_gObL_transactions_b8af13ea9c8fe890c9979a1fa8dbde22 UNIQUE (`transaction_reference`);
-- constraints column "account_client_id" type changed in host table "gObL_accounts".
ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_clients FOREIGN KEY (`account_client_id`) REFERENCES `gObL_clients` (`client_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
-- constraints column mapping changed.
ALTER TABLE `gObL_accounts` ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY (`account_currency_code`) REFERENCES `gObL_currencies` (`ccy_code`) ON UPDATE NO ACTION ON DELETE NO ACTION;
-- table "gObL_transactions" was added.
ALTER TABLE `gObL_transactions` ADD CONSTRAINT fk_transactions_accounts FOREIGN KEY (`transaction_account_id`) REFERENCES `gObL_accounts` (`account_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;