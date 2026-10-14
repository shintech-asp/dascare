-- Phase 2: temporary accounts for website guests (reusables/temporary_accounts.php).
-- A guest SOS creates one; the guest is signed in automatically and can come
-- back with a login key. Completing it (normal registration + email check)
-- moves its requests to the new citizen account; unconverted ones are
-- deleted N days after their last request ends (setting below).
CREATE TABLE IF NOT EXISTS temporary_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  login_key_hash CHAR(64) NOT NULL,
  key_hint VARCHAR(4) NOT NULL,                 -- last 4 key characters, shown as "••••-••••-9PHD"
  display_name VARCHAR(160) NULL,
  phone VARCHAR(30) NULL,
  status ENUM('active','converted') NOT NULL DEFAULT 'active',
  converted_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  converted_at DATETIME NULL,
  UNIQUE KEY uq_temporary_accounts_key (login_key_hash),
  KEY idx_temporary_accounts_status (status),
  CONSTRAINT fk_temporary_accounts_user FOREIGN KEY (converted_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE emergency_requests
  ADD COLUMN temporary_account_id BIGINT UNSIGNED NULL AFTER requester_user_id,
  ADD KEY idx_emergency_requests_temporary_account (temporary_account_id);

INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('guest.temporary_account_retention_days', '7', 'Days after a temporary (guest) account''s last request ends before it is deleted, unless the guest completes it.');
