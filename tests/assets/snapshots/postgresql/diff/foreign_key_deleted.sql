-- >>>>@UP>>>>
-- foreign key constraint deleted
ALTER TABLE "gObL_products" DROP CONSTRAINT fk_gObL_products_gObL_categories;

-- >>>>@DOWN>>>>
-- foreign key constraint added
ALTER TABLE "gObL_products" ADD CONSTRAINT fk_gObL_products_gObL_categories FOREIGN KEY ("product_category_id") REFERENCES "gObL_categories" ("category_id") ON UPDATE NO ACTION ON DELETE NO ACTION;