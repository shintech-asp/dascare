-- Document management: expiry dates, replacement history, expiry reminders.
ALTER TABLE organization_documents
  ADD COLUMN expires_at DATE NULL AFTER file_size,
  ADD COLUMN replaces_document_id BIGINT UNSIGNED NULL AFTER uploaded_by_user_id,
  ADD COLUMN superseded_at DATETIME NULL AFTER reviewed_at,
  ADD COLUMN expiry_notice_at DATETIME NULL AFTER superseded_at,
  ADD KEY idx_org_docs_current (organization_id, doc_type, superseded_at),
  ADD KEY idx_org_docs_expiry (expires_at, superseded_at);
