--
-- Auto generated file, please don't edit.
-- With: gobl [GOBL_VERSION]
-- Time: [GENERATED_AT]
--

--
-- Table structure for table "gObL_t"
--
DROP TABLE IF EXISTS "gObL_t" CASCADE;
CREATE TABLE "gObL_t" (
"t_id" bigserial,
"t_active" boolean NOT NULL DEFAULT '1',
"t_archived" boolean NOT NULL DEFAULT '0',
"t_optional_flag" boolean NULL DEFAULT NULL,
"t_score" double precision NOT NULL,
"t_unsigned_score" double precision NOT NULL,
"t_nullable_score" double precision NULL DEFAULT NULL,
"t_amount" numeric NOT NULL,
"t_unsigned_amount" numeric NOT NULL,
"t_amount_default" numeric NOT NULL DEFAULT '0.00',
"t_nullable_amount" numeric NULL DEFAULT NULL,

--
-- Primary key constraints definition for table "gObL_t"
--
CONSTRAINT pk_gObL_t PRIMARY KEY ("t_id")
);




