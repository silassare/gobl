--
-- Auto generated file, please don't edit.
-- With: gobl [GOBL_VERSION]
-- Time: [GENERATED_AT]
--

--
-- Table structure for table "gObL_authors"
--
DROP TABLE IF EXISTS "gObL_authors";
CREATE TABLE "gObL_authors" (
"author_id" integer PRIMARY KEY AUTOINCREMENT,
"author_email" varchar(255) NOT NULL,
"author_name" varchar(100) NOT NULL,
CONSTRAINT uc_gObL_authors_0c83f57c786a0b4a39efab23731c7ebc UNIQUE ("author_email")
);




--
-- Table structure for table "gObL_posts"
--
DROP TABLE IF EXISTS "gObL_posts";
CREATE TABLE "gObL_posts" (
"post_id" integer PRIMARY KEY AUTOINCREMENT,
"post_author_id" integer NOT NULL,
"post_title" varchar(200) NOT NULL,
"post_slug" varchar(200) NOT NULL,
"post_published" integer NOT NULL DEFAULT '0',
CONSTRAINT fk_posts_authors FOREIGN KEY ("post_author_id") REFERENCES "gObL_authors" ("author_id") ON UPDATE NO ACTION ON DELETE NO ACTION,
CONSTRAINT uc_gObL_posts_2dbcba41b9ac4c5d22886ba672463cb4 UNIQUE ("post_slug")
);



