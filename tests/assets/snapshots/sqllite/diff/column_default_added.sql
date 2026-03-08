-- >>>>@UP>>>>
-- column type changed
ALTER TABLE "gObL_items" ALTER COLUMN "status" varchar(20) NOT NULL DEFAULT 'active';

-- >>>>@DOWN>>>>
-- column type changed
ALTER TABLE "gObL_items" ALTER COLUMN "status" varchar(20) NOT NULL;