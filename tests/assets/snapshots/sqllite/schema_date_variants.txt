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
"t_created_at" integer NOT NULL,
"t_updated_at" integer NOT NULL,
"t_published_at" integer NOT NULL,
"t_expires_at" integer NULL DEFAULT NULL,
"t_precise_time" numeric(20, 6) NOT NULL,
"t_precise_nullable" numeric(20, 6) NULL DEFAULT NULL
);



