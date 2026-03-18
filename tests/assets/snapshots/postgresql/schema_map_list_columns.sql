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
"t_data" jsonb NOT NULL DEFAULT '{}',
"t_optional_extras" jsonb NULL DEFAULT NULL,
"t_data_big" jsonb NOT NULL,
"t_list_big" jsonb NOT NULL,
"t_tags" jsonb NOT NULL DEFAULT '[]',
"t_optional_items" jsonb NULL DEFAULT NULL,

--
-- Primary key constraints definition for table "gObL_t"
--
CONSTRAINT pk_gObL_t PRIMARY KEY ("t_id")
);




