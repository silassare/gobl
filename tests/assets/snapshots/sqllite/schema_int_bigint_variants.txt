--
-- Auto generated file, please don't edit.
-- With: gobl [GOBL_VERSION]
-- Time: [GENERATED_AT]
--

--
-- Table structure for table "gObL_t"
--
DROP TABLE IF EXISTS "gObL_t";
CREATE TABLE "gObL_t" (
"t_id" integer PRIMARY KEY AUTOINCREMENT,
"t_plain_int" integer NOT NULL,
"t_signed_int" integer NOT NULL DEFAULT '42',
"t_nullable_int" integer NULL DEFAULT NULL,
"t_plain_bigint" integer NOT NULL,
"t_unsigned_bigint" integer NOT NULL
);



