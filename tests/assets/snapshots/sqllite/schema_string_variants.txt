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
"t_name" varchar(60) NOT NULL,
"t_code" varchar(3) NOT NULL,
"t_title" varchar(255) NOT NULL,
"t_optional_note" varchar(200) NULL DEFAULT NULL,
"t_with_default" varchar(50) NOT NULL DEFAULT 'N/A',
"t_body" text NOT NULL,
"t_nullable_body" text NULL
);



