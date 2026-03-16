-- >>>>@UP>>>>
-- column type changed
ALTER TABLE "gObL_docs" ALTER COLUMN "doc_payload" text NOT NULL;

-- >>>>@DOWN>>>>
-- column type changed
ALTER TABLE "gObL_docs" ALTER COLUMN "doc_payload" varchar(65535) NOT NULL;