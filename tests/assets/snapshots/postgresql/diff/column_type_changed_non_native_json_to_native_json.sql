-- >>>>@UP>>>>
-- column type changed
ALTER TABLE "gObL_docs" ALTER COLUMN "doc_payload" jsonb NOT NULL USING to_jsonb("doc_payload"::text);

-- >>>>@DOWN>>>>
-- column type changed
ALTER TABLE "gObL_docs" ALTER COLUMN "doc_payload" text NOT NULL;