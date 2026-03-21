<?php
/**
 * Generated on: [GENERATED_AT]
 */
declare(strict_types=1);

return new class implements \Gobl\DBAL\Interfaces\MigrationInterface
{
	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getVersion(): int
	{
		return 1;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getLabel(): string
	{
		
		return <<<DIFF_LABEL
		Auto generated.
		DIFF_LABEL;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getTimestamp(): int
	{
		return 0;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function beforeRun(\Gobl\DBAL\MigrationMode $mode, string $query): bool|string
	{
		// TODO: implement your custom logic here
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function afterRun(\Gobl\DBAL\MigrationMode $mode): void
	{
		// TODO: implement your custom logic here
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function up(): string
	{
		
		return <<<DIFF_SQL
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE "gObL_accounts" DROP CONSTRAINT fk_accounts_clients;
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
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_id" TYPE integer;
		-- column type changed
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" TYPE bigint;
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" DROP NOT NULL;
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" SET DEFAULT NULL;
		-- column type changed
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_id" TYPE integer;
		-- column type changed
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_client_id" TYPE integer;
		-- column type changed
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" TYPE bigint;
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" DROP NOT NULL;
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" SET DEFAULT NULL;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_code" TYPE varchar(30);
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_name" TYPE varchar(60);
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_symbol" TYPE varchar(6);
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_data" TYPE jsonb;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_created_at" TYPE bigint;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_updated_at" TYPE bigint;
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_updated_at" DROP NOT NULL;
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_updated_at" SET DEFAULT NULL;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "currency_valid" TYPE boolean;
		-- column added
		ALTER TABLE "gObL_clients" ADD "client_name" varchar(60) NOT NULL;
		-- table added
		--
		-- Table structure for table "gObL_orders"
		--
		DROP TABLE IF EXISTS "gObL_orders" CASCADE;
		CREATE TABLE "gObL_orders" (
		"order_id" serial,
		"order_data" jsonb NOT NULL DEFAULT '{}',
		"order_created_at" bigint NOT NULL,
		"order_updated_at" bigint NULL DEFAULT NULL,
		"order_valid" boolean NOT NULL DEFAULT '1',

		--
		-- Primary key constraints definition for table "gObL_orders"
		--
		CONSTRAINT pk_gObL_orders PRIMARY KEY ("order_id")
		);



		ALTER TABLE "gObL_accounts" ADD CONSTRAINT uc_gObL_accounts_f9913917ef012372103dd1a027b2cd6a UNIQUE ("account_client_id" , "account_currency_code");
		ALTER TABLE "gObL_currencies" ADD CONSTRAINT uc_gObL_currencies_c13367945d5d4c91047b3b50234aa7ab UNIQUE ("currency_code");
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_clients FOREIGN KEY ("account_client_id") REFERENCES "gObL_clients" ("client_id") ON UPDATE NO ACTION ON DELETE NO ACTION;
		-- constraints column mapping changed.
		ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY ("account_currency_code") REFERENCES "gObL_currencies" ("currency_code") ON UPDATE NO ACTION ON DELETE NO ACTION;
		DIFF_SQL;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function down(): string
	{
		
		return <<<DIFF_SQL
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE "gObL_accounts" DROP CONSTRAINT fk_accounts_clients;
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
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_id" TYPE bigint;
		-- column type changed
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" TYPE bigint;
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" SET NOT NULL;
		ALTER TABLE "gObL_clients" ALTER COLUMN "client_updated_at" DROP DEFAULT;
		-- column type changed
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_id" TYPE bigint;
		-- column type changed
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_client_id" TYPE bigint;
		-- column type changed
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" TYPE bigint;
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" SET NOT NULL;
		ALTER TABLE "gObL_accounts" ALTER COLUMN "account_updated_at" DROP DEFAULT;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_code" TYPE varchar(30);
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_name" TYPE varchar(60);
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_symbol" TYPE varchar(6);
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_data" TYPE jsonb;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_created_at" TYPE bigint;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_updated_at" TYPE bigint;
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_updated_at" SET NOT NULL;
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_updated_at" DROP DEFAULT;
		-- column type changed
		ALTER TABLE "gObL_currencies" ALTER COLUMN "ccy_valid" TYPE boolean;
		-- column added
		ALTER TABLE "gObL_clients" ADD "client_first_name" varchar(60) NOT NULL;
		-- column added
		ALTER TABLE "gObL_clients" ADD "client_last_name" varchar(60) NOT NULL;
		-- table added
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



		ALTER TABLE "gObL_currencies" ADD CONSTRAINT pk_gObL_currencies PRIMARY KEY ("ccy_code");
		-- table "gObL_transactions" was added.
		ALTER TABLE "gObL_transactions" ADD CONSTRAINT uc_gObL_transactions_b8af13ea9c8fe890c9979a1fa8dbde22 UNIQUE ("transaction_reference");
		-- constraints column "account_client_id" type changed in host table "gObL_accounts".
		ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_clients FOREIGN KEY ("account_client_id") REFERENCES "gObL_clients" ("client_id") ON UPDATE NO ACTION ON DELETE NO ACTION;
		-- constraints column mapping changed.
		ALTER TABLE "gObL_accounts" ADD CONSTRAINT fk_accounts_currencies FOREIGN KEY ("account_currency_code") REFERENCES "gObL_currencies" ("ccy_code") ON UPDATE NO ACTION ON DELETE NO ACTION;
		-- table "gObL_transactions" was added.
		ALTER TABLE "gObL_transactions" ADD CONSTRAINT fk_transactions_accounts FOREIGN KEY ("transaction_account_id") REFERENCES "gObL_accounts" ("account_id") ON UPDATE NO ACTION ON DELETE NO ACTION;
		DIFF_SQL;
	}

	#[\Override]
	public function getConfigs(): array
	{
		return array (
		  'db_table_prefix' => 'gObL',
		  'db_host' => '***',
		  'db_port' => '***',
		  'db_name' => '***',
		  'db_user' => '***',
		  'db_pass' => '***',
		  'db_charset' => 'utf8',
		  'db_collate' => 'utf8',
		  'db_server_version' => '',
		);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
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
		        'type' => 'int',
		        'unsigned' => true,
		        'auto_increment' => true,
		        'prefix' => 'client',
		        'diff_key' => '14ba9335bd3a89381f85c488b7d7479e',
		      ),
		      'given_name' => 
		      array (
		        'type' => 'string',
		        'min' => 1,
		        'max' => 60,
		        'prefix' => 'client',
		        'diff_key' => 'a1e3fa792eb396a3a4ae0ef306064ae8',
		      ),
		      'name' => 
		      array (
		        'type' => 'string',
		        'min' => 1,
		        'max' => 60,
		        'prefix' => 'client',
		        'diff_key' => 'da51f0ded18c7975ec12253ff962bc83',
		      ),
		      'gender' => 
		      array (
		        'type' => 'string',
		        'min' => 1,
		        'max' => 60,
		        'one_of' => 
		        array (
		          0 => 'male',
		          1 => 'female',
		          2 => 'unknown',
		        ),
		        'prefix' => 'client',
		        'diff_key' => 'f70b2162baadaabd1efa847313df2fce',
		      ),
		      'data' => 
		      array (
		        'type' => 'map',
		        'default' => 
		        array (
		        ),
		        'prefix' => 'client',
		        'diff_key' => 'b9ed9db22490366b3ff4744e55185c30',
		      ),
		      'created_at' => 
		      array (
		        'type' => 'date',
		        'auto' => true,
		        'format' => 'timestamp',
		        'prefix' => 'client',
		        'diff_key' => '5c5a33754d19cc4b585e5a07de808952',
		      ),
		      'updated_at' => 
		      array (
		        'type' => 'date',
		        'format' => 'timestamp',
		        'nullable' => true,
		        'prefix' => 'client',
		        'diff_key' => 'e76ec32965bb5a35c8fbb947aba5379f',
		      ),
		      'valid' => 
		      array (
		        'strict' => true,
		        'type' => 'bool',
		        'default' => 1,
		        'prefix' => 'client',
		        'diff_key' => 'a7c31f9a83842303a68c6b80b2ed138d',
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
		        'type' => 'int',
		        'unsigned' => true,
		        'auto_increment' => true,
		        'prefix' => 'account',
		        'diff_key' => 'e9c239e4ac994e5924f7fffd8a6526ef',
		      ),
		      'client_id' => 
		      array (
		        'type' => 'ref:clients.id',
		        'unsigned' => true,
		        'prefix' => 'account',
		        'diff_key' => 'd169fe50dca36707ef3e2741c5872dd3',
		      ),
		      'label' => 
		      array (
		        'type' => 'string',
		        'min' => 1,
		        'max' => 60,
		        'prefix' => 'account',
		        'diff_key' => '4cf6f776b8bee602c30d7bb2bc749956',
		      ),
		      'balance' => 
		      array (
		        'type' => 'decimal',
		        'unsigned' => true,
		        'default' => 0,
		        'prefix' => 'account',
		        'diff_key' => '7dd636cfa8fd942d5438066bbd3aae9d',
		      ),
		      'currency_code' => 
		      array (
		        'type' => 'ref:currencies.code',
		        'min' => 1,
		        'max' => 30,
		        'prefix' => 'account',
		        'diff_key' => '7e1a8d774f4086cfa7eb724980f1b071',
		      ),
		      'data' => 
		      array (
		        'type' => 'map',
		        'default' => 
		        array (
		        ),
		        'prefix' => 'account',
		        'diff_key' => '84cd50284582d095c3513256deb3b7e3',
		      ),
		      'created_at' => 
		      array (
		        'type' => 'date',
		        'auto' => true,
		        'format' => 'timestamp',
		        'prefix' => 'account',
		        'diff_key' => '31492f2773a8aa390f2961bb2f705a36',
		      ),
		      'updated_at' => 
		      array (
		        'type' => 'date',
		        'format' => 'timestamp',
		        'nullable' => true,
		        'prefix' => 'account',
		        'diff_key' => '83c3b0a2e3191dcdf4ab4cbccbefb437',
		      ),
		      'valid' => 
		      array (
		        'strict' => true,
		        'type' => 'bool',
		        'default' => 1,
		        'prefix' => 'account',
		        'diff_key' => '90016cc6efa18ca15429fde9c79819ff',
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
		        'type' => 'string',
		        'min' => 1,
		        'max' => 30,
		        'prefix' => 'currency',
		        'diff_key' => '093257e5638bbb0165c1de5f546f3d19',
		      ),
		      'name' => 
		      array (
		        'type' => 'string',
		        'min' => 1,
		        'max' => 60,
		        'prefix' => 'currency',
		        'diff_key' => '59718a4adcdcb6218269ca1d7581080f',
		      ),
		      'symbol' => 
		      array (
		        'type' => 'string',
		        'min' => 1,
		        'max' => 6,
		        'prefix' => 'currency',
		        'diff_key' => '58bbb5c54cab9aabac17da9270c1cbd5',
		      ),
		      'data' => 
		      array (
		        'type' => 'map',
		        'default' => 
		        array (
		        ),
		        'prefix' => 'currency',
		        'diff_key' => 'eea3e8b0fcda9eb516dfb8c00a4a2f0d',
		      ),
		      'created_at' => 
		      array (
		        'type' => 'date',
		        'auto' => true,
		        'format' => 'timestamp',
		        'prefix' => 'currency',
		        'diff_key' => '836473be576e95678a9bbd87d06a5db5',
		      ),
		      'updated_at' => 
		      array (
		        'type' => 'date',
		        'format' => 'timestamp',
		        'nullable' => true,
		        'prefix' => 'currency',
		        'diff_key' => '91b988f23026c1aed8994c64376a7535',
		      ),
		      'valid' => 
		      array (
		        'strict' => true,
		        'type' => 'bool',
		        'default' => 1,
		        'prefix' => 'currency',
		        'diff_key' => 'c4b8a3e8f1f2e21532bed2415bc45f4d',
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
		        'type' => 'int',
		        'unsigned' => true,
		        'auto_increment' => true,
		        'prefix' => 'order',
		        'diff_key' => 'd976a6fdef239f273bac3d553a507461',
		      ),
		      'data' => 
		      array (
		        'type' => 'map',
		        'default' => 
		        array (
		        ),
		        'prefix' => 'order',
		        'diff_key' => 'f798e510a23ec12dfe64d98c14ae779c',
		      ),
		      'created_at' => 
		      array (
		        'type' => 'date',
		        'auto' => true,
		        'format' => 'timestamp',
		        'prefix' => 'order',
		        'diff_key' => '52f902926023046ba28f6ff24baa8c91',
		      ),
		      'updated_at' => 
		      array (
		        'type' => 'date',
		        'format' => 'timestamp',
		        'nullable' => true,
		        'prefix' => 'order',
		        'diff_key' => '2e9608c55d234f0a89d1a9f9e210e709',
		      ),
		      'valid' => 
		      array (
		        'strict' => true,
		        'type' => 'bool',
		        'default' => 1,
		        'prefix' => 'order',
		        'diff_key' => '94f9ff63a8b94307812b0cfa3ab8e08d',
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
