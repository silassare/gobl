-- >>>>@UP>>>>
-- column type changed
ALTER TABLE "gObL_profiles" ALTER COLUMN "bio" TYPE varchar(500);
ALTER TABLE "gObL_profiles" ALTER COLUMN "bio" DROP NOT NULL;
ALTER TABLE "gObL_profiles" ALTER COLUMN "bio" SET DEFAULT NULL;

-- >>>>@DOWN>>>>
-- column type changed
ALTER TABLE "gObL_profiles" ALTER COLUMN "bio" TYPE varchar(500);
ALTER TABLE "gObL_profiles" ALTER COLUMN "bio" SET NOT NULL;
ALTER TABLE "gObL_profiles" ALTER COLUMN "bio" DROP DEFAULT;