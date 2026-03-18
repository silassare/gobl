-- >>>>@UP>>>>
-- column type changed
ALTER TABLE "gObL_items" ALTER COLUMN "status" TYPE varchar(20);
ALTER TABLE "gObL_items" ALTER COLUMN "status" SET DEFAULT 'active';

-- >>>>@DOWN>>>>
-- column type changed
ALTER TABLE "gObL_items" ALTER COLUMN "status" TYPE varchar(20);
ALTER TABLE "gObL_items" ALTER COLUMN "status" DROP DEFAULT;