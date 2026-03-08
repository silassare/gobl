-- >>>>@UP>>>>
-- table deleted
DROP TABLE "gObL_temporary";

-- >>>>@DOWN>>>>
-- table added
--
-- Table structure for table "gObL_temporary"
--
DROP TABLE IF EXISTS "gObL_temporary" CASCADE;
CREATE TABLE "gObL_temporary" (
"id" bigserial,
"label" text NOT NULL,

--
-- Primary key constraints definition for table "gObL_temporary"
--
CONSTRAINT pk_gObL_temporary PRIMARY KEY ("id")
);


