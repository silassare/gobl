-- >>>>@UP>>>>
ALTER TABLE "gObL_products" ADD CONSTRAINT fk_products_categories FOREIGN KEY ("product_category_id") REFERENCES "gObL_categories" ("category_id") ON UPDATE NO ACTION ON DELETE NO ACTION;

-- >>>>@DOWN>>>>
-- foreign key constraint deleted
ALTER TABLE "gObL_products" DROP CONSTRAINT fk_products_categories;