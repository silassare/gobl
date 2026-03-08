--
-- Auto generated file, please don't edit.
-- With: gobl [GOBL_VERSION]
-- Time: [GENERATED_AT]
--

--
-- Table structure for table "gObL_authors"
--
DROP TABLE IF EXISTS "gObL_authors" CASCADE;
CREATE TABLE "gObL_authors" (
"author_id" bigserial,
"author_email" varchar(255) NOT NULL,
"author_name" varchar(100) NOT NULL,

--
-- Primary key constraints definition for table "gObL_authors"
--
CONSTRAINT pk_gObL_authors PRIMARY KEY ("author_id")
);




--
-- Table structure for table "gObL_posts"
--
DROP TABLE IF EXISTS "gObL_posts" CASCADE;
CREATE TABLE "gObL_posts" (
"post_id" bigserial,
"post_author_id" bigint NOT NULL,
"post_title" varchar(200) NOT NULL,
"post_slug" varchar(200) NOT NULL,
"post_published" boolean NOT NULL DEFAULT '0',

--
-- Primary key constraints definition for table "gObL_posts"
--
CONSTRAINT pk_gObL_posts PRIMARY KEY ("post_id")
);




--
-- Unique constraints definition for table "gObL_authors"
--
ALTER TABLE "gObL_authors" ADD CONSTRAINT uc_gObL_authors_0c83f57c786a0b4a39efab23731c7ebc UNIQUE ("author_email");


--
-- Foreign keys constraints definition for table "gObL_posts"
--
ALTER TABLE "gObL_posts" ADD CONSTRAINT fk_posts_authors FOREIGN KEY ("post_author_id") REFERENCES "gObL_authors" ("author_id") ON UPDATE NO ACTION ON DELETE NO ACTION;


--
-- Unique constraints definition for table "gObL_posts"
--
ALTER TABLE "gObL_posts" ADD CONSTRAINT uc_gObL_posts_2dbcba41b9ac4c5d22886ba672463cb4 UNIQUE ("post_slug");
