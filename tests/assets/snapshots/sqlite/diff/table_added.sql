-- >>>>@UP>>>>
-- table added
--
-- Table structure for table "gObL_products"
--
DROP TABLE IF EXISTS "gObL_products";
CREATE TABLE "gObL_products" (
"id" integer PRIMARY KEY AUTOINCREMENT,
"name" varchar(120) NOT NULL,
"price" numeric NOT NULL
);




-- >>>>@DOWN>>>>
-- table deleted
DROP TABLE "gObL_products";