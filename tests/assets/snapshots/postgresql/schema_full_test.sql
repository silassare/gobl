--
-- Auto generated file, please don't edit.
-- With: gobl [GOBL_VERSION]
-- Time: [GENERATED_AT]
--

--
-- Table structure for table "gObL_clients"
--
DROP TABLE IF EXISTS "gObL_clients" CASCADE;
CREATE TABLE "gObL_clients" (
"client_id" bigserial,
"client_first_name" varchar(60) NOT NULL,
"client_last_name" varchar(60) NOT NULL,
"client_given_name" varchar(60) NOT NULL,
"client_gender" varchar(60) NOT NULL,
"client_data" jsonb NOT NULL DEFAULT '{}',
"client_created_at" bigint NOT NULL,
"client_updated_at" bigint NOT NULL,
"client_valid" boolean NOT NULL DEFAULT '1',

--
-- Primary key constraints definition for table "gObL_clients"
--
CONSTRAINT pk_gObL_clients PRIMARY KEY ("client_id")
);




--
-- Table structure for table "gObL_accounts"
--
DROP TABLE IF EXISTS "gObL_accounts" CASCADE;
CREATE TABLE "gObL_accounts" (
"account_id" bigserial,
"account_client_id" bigint NOT NULL,
"account_label" varchar(60) NOT NULL,
"account_balance" numeric NOT NULL DEFAULT '0',
"account_currency_code" varchar(30) NOT NULL,
"account_data" jsonb NOT NULL DEFAULT '{}',
"account_created_at" bigint NOT NULL,
"account_updated_at" bigint NOT NULL,
"account_valid" boolean NOT NULL DEFAULT '1',

--
-- Primary key constraints definition for table "gObL_accounts"
--
CONSTRAINT pk_gObL_accounts PRIMARY KEY ("account_id")
);




--
-- Table structure for table "gObL_transactions"
--
DROP TABLE IF EXISTS "gObL_transactions" CASCADE;
CREATE TABLE "gObL_transactions" (
"transaction_id" bigserial,
"transaction_account_id" bigint NOT NULL,
"transaction_reference" varchar(128) NOT NULL,
"transaction_source" varchar(60) NOT NULL,
"transaction_type" varchar(60) NOT NULL,
"transaction_state" varchar(60) NOT NULL,
"transaction_amount" numeric NOT NULL DEFAULT '0',
"transaction_currency_code" varchar(30) NOT NULL,
"transaction_date" bigint NOT NULL,
"transaction_data" jsonb NOT NULL DEFAULT '{}',
"transaction_created_at" bigint NOT NULL,
"transaction_updated_at" bigint NOT NULL,
"transaction_valid" boolean NOT NULL DEFAULT '1',

--
-- Primary key constraints definition for table "gObL_transactions"
--
CONSTRAINT pk_gObL_transactions PRIMARY KEY ("transaction_id")
);




--
-- Table structure for table "gObL_currencies"
--
DROP TABLE IF EXISTS "gObL_currencies" CASCADE;
CREATE TABLE "gObL_currencies" (
"ccy_code" varchar(30) NOT NULL,
"ccy_name" varchar(60) NOT NULL,
"ccy_symbol" varchar(6) NOT NULL,
"ccy_data" jsonb NOT NULL DEFAULT '{}',
"ccy_created_at" bigint NOT NULL,
"ccy_updated_at" bigint NOT NULL,
"ccy_valid" boolean NOT NULL DEFAULT '1',

--
-- Primary key constraints definition for table "gObL_currencies"
--
CONSTRAINT pk_gObL_currencies PRIMARY KEY ("ccy_code")
);




--
-- Foreign keys constraints definition for table "gObL_accounts"
--
ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_clients FOREIGN KEY ("account_client_id") REFERENCES "gObL_clients" ("client_id") ON UPDATE NO ACTION ON DELETE NO ACTION;
ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY ("account_currency_code") REFERENCES "gObL_currencies" ("ccy_code") ON UPDATE NO ACTION ON DELETE NO ACTION;


--
-- Foreign keys constraints definition for table "gObL_transactions"
--
ALTER TABLE "gObL_transactions" ADD CONSTRAINT fk_transactions_accounts FOREIGN KEY ("transaction_account_id") REFERENCES "gObL_accounts" ("account_id") ON UPDATE NO ACTION ON DELETE NO ACTION;


--
-- Unique constraints definition for table "gObL_transactions"
--
ALTER TABLE "gObL_transactions" ADD CONSTRAINT uc_gObL_transactions_b8af13ea9c8fe890c9979a1fa8dbde22 UNIQUE ("transaction_reference");
