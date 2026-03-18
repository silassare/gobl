-- >>>>@UP>>>>
-- column type changed
ALTER TABLE "gObL_docs" ALTER COLUMN "doc_payload" TYPE jsonb USING "doc_payload"::jsonb;

-- >>>>@DOWN>>>>
-- column type changed
ALTER TABLE "gObL_docs" ALTER COLUMN "doc_payload" TYPE varchar(65535);