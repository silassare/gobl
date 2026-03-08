-- >>>>@UP>>>>
-- constraints column mapping changed.
ALTER TABLE "gObL_accounts" DROP CONSTRAINT fk_accounts_currencies;
-- table "gObL_transactions" was deleted.
ALTER TABLE "gObL_transactions" DROP CONSTRAINT fk_transactions_accounts;
-- primary key constraint deleted
ALTER TABLE "gObL_currencies" DROP CONSTRAINT pk_gObL_currencies;
-- table "gObL_transactions" was deleted.
ALTER TABLE "gObL_transactions" DROP CONSTRAINT uc_gObL_transactions_b8af13ea9c8fe890c9979a1fa8dbde22;
-- table deleted
DROP TABLE "gObL_transactions";
-- column deleted
ALTER TABLE "gObL_clients" DROP "client_first_name";
-- column deleted
ALTER TABLE "gObL_clients" DROP "client_last_name";
-- column prefix changed from "ccy" to "currency"
ALTER TABLE "gObL_currencies" RENAME COLUMN "ccy_code" TO "currency_code";
-- column prefix changed from "ccy" to "currency"
ALTER TABLE "gObL_currencies" RENAME COLUMN "ccy_name" TO "currency_name";
-- column prefix changed from "ccy" to "currency"
ALTER TABLE "gObL_currencies" RENAME COLUMN "ccy_symbol" TO "currency_symbol";
-- column prefix changed from "ccy" to "currency"
ALTER TABLE "gObL_currencies" RENAME COLUMN "ccy_data" TO "currency_data";
-- column prefix changed from "ccy" to "currency"
ALTER TABLE "gObL_currencies" RENAME COLUMN "ccy_created_at" TO "currency_created_at";
-- column prefix changed from "ccy" to "currency"
ALTER TABLE "gObL_currencies" RENAME COLUMN "ccy_updated_at" TO "currency_updated_at";
-- column prefix changed from "ccy" to "currency"
ALTER TABLE "gObL_currencies" RENAME COLUMN "ccy_valid" TO "currency_valid";
-- column type changed
ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" integer NULL DEFAULT NULL;
-- column type changed
ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" integer NULL DEFAULT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_code" varchar(30) NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_name" varchar(60) NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_symbol" varchar(6) NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_data" text NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_created_at" integer NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_updated_at" integer NULL DEFAULT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_valid" integer NOT NULL DEFAULT '1';
-- column added
ALTER TABLE "gObL_clients" ADD "client_name" varchar(60) NOT NULL;
-- table added
--
-- Table structure for table "gObL_orders"
--
DROP TABLE IF EXISTS "gObL_orders";
CREATE TABLE "gObL_orders" (
"order_id" integer PRIMARY KEY AUTOINCREMENT,
"order_data" text NOT NULL,
"order_created_at" integer NOT NULL,
"order_updated_at" integer NULL DEFAULT NULL,
"order_valid" integer NOT NULL DEFAULT '1'
);



ALTER TABLE "gObL_accounts" ADD CONSTRAINT uc_gObL_accounts_f9913917ef012372103dd1a027b2cd6a UNIQUE ("account_client_id" , "account_currency_code");
ALTER TABLE "gObL_currencies" ADD CONSTRAINT uc_gObL_currencies_c13367945d5d4c91047b3b50234aa7ab UNIQUE ("currency_code");
-- constraints column mapping changed.
ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY ("account_currency_code") REFERENCES "gObL_currencies" ("currency_code") ON UPDATE NO ACTION ON DELETE NO ACTION;

-- >>>>@DOWN>>>>
-- constraints column mapping changed.
ALTER TABLE "gObL_accounts" DROP CONSTRAINT fk_accounts_currencies;
-- unique key constraint deleted
ALTER TABLE "gObL_accounts" DROP CONSTRAINT uc_gObL_accounts_f9913917ef012372103dd1a027b2cd6a;
-- unique key constraint deleted
ALTER TABLE "gObL_currencies" DROP CONSTRAINT uc_gObL_currencies_c13367945d5d4c91047b3b50234aa7ab;
-- table deleted
DROP TABLE "gObL_orders";
-- column deleted
ALTER TABLE "gObL_clients" DROP "client_name";
-- column prefix changed from "currency" to "ccy"
ALTER TABLE "gObL_currencies" RENAME COLUMN "currency_code" TO "ccy_code";
-- column prefix changed from "currency" to "ccy"
ALTER TABLE "gObL_currencies" RENAME COLUMN "currency_name" TO "ccy_name";
-- column prefix changed from "currency" to "ccy"
ALTER TABLE "gObL_currencies" RENAME COLUMN "currency_symbol" TO "ccy_symbol";
-- column prefix changed from "currency" to "ccy"
ALTER TABLE "gObL_currencies" RENAME COLUMN "currency_data" TO "ccy_data";
-- column prefix changed from "currency" to "ccy"
ALTER TABLE "gObL_currencies" RENAME COLUMN "currency_created_at" TO "ccy_created_at";
-- column prefix changed from "currency" to "ccy"
ALTER TABLE "gObL_currencies" RENAME COLUMN "currency_updated_at" TO "ccy_updated_at";
-- column prefix changed from "currency" to "ccy"
ALTER TABLE "gObL_currencies" RENAME COLUMN "currency_valid" TO "ccy_valid";
-- column type changed
ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" integer NOT NULL;
-- column type changed
ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" integer NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_code" varchar(30) NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_name" varchar(60) NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_symbol" varchar(6) NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_data" text NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_created_at" integer NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_updated_at" integer NOT NULL;
-- column type changed
ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_valid" integer NOT NULL DEFAULT '1';
-- column added
ALTER TABLE "gObL_clients" ADD "client_first_name" varchar(60) NOT NULL;
-- column added
ALTER TABLE "gObL_clients" ADD "client_last_name" varchar(60) NOT NULL;
-- table added
--
-- Table structure for table "gObL_transactions"
--
DROP TABLE IF EXISTS "gObL_transactions";
CREATE TABLE "gObL_transactions" (
"transaction_id" integer PRIMARY KEY AUTOINCREMENT,
"transaction_account_id" integer NOT NULL,
"transaction_reference" varchar(128) NOT NULL,
"transaction_source" varchar(60) NOT NULL,
"transaction_type" varchar(60) NOT NULL,
"transaction_state" varchar(60) NOT NULL,
"transaction_amount" numeric NOT NULL DEFAULT '0',
"transaction_currency_code" varchar(30) NOT NULL,
"transaction_date" integer NOT NULL,
"transaction_data" text NOT NULL,
"transaction_created_at" integer NOT NULL,
"transaction_updated_at" integer NOT NULL,
"transaction_valid" integer NOT NULL DEFAULT '1'
);



ALTER TABLE "gObL_currencies" ADD CONSTRAINT pk_gObL_currencies PRIMARY KEY ("ccy_code");
-- table "gObL_transactions" was added.
ALTER TABLE "gObL_transactions" ADD CONSTRAINT uc_gObL_transactions_b8af13ea9c8fe890c9979a1fa8dbde22 UNIQUE ("transaction_reference");
-- constraints column mapping changed.
ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY ("account_currency_code") REFERENCES "gObL_currencies" ("ccy_code") ON UPDATE NO ACTION ON DELETE NO ACTION;
-- table "gObL_transactions" was added.
ALTER TABLE "gObL_transactions" ADD CONSTRAINT fk_transactions_accounts FOREIGN KEY ("transaction_account_id") REFERENCES "gObL_accounts" ("account_id") ON UPDATE NO ACTION ON DELETE NO ACTION;