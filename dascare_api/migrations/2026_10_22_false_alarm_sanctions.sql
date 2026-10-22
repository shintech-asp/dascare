-- False-alarm sanctions (reusables/false_alarm_sanctions.php).
-- A request closed as false_alarm by an organization / platform admin gives
-- its requester a strike: account (citizen) or phone number / temporary
-- account (guest). Strikes drive the configurable ladder in system_settings
-- 'sanctions.false_alarm' (warning → timed suspension → suspension until
-- review; guest numbers blocked for a while). Citizens can appeal a strike.

CREATE TABLE IF NOT EXISTS false_alarm_strikes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  emergency_request_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,                 -- citizen account (NULL for guests)
  phone VARCHAR(30) NULL,                       -- guest's normalized number
  temporary_account_id BIGINT UNSIGNED NULL,    -- website guest's temporary account
  organization_id BIGINT UNSIGNED NULL,         -- who closed it (NULL = platform)
  issued_by_user_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  sanction_applied VARCHAR(30) NOT NULL DEFAULT 'none', -- none|warning|suspended|review|guest_block
  status ENUM('active','removed') NOT NULL DEFAULT 'active',
  removed_by_user_id BIGINT UNSIGNED NULL,
  removed_at DATETIME NULL,
  removed_reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_false_alarm_strikes_request (emergency_request_id),
  KEY idx_false_alarm_strikes_user (user_id, status, created_at),
  KEY idx_false_alarm_strikes_phone (phone, status, created_at),
  KEY idx_false_alarm_strikes_temp (temporary_account_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS false_alarm_appeals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  strike_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_false_alarm_appeals_status (status, created_at),
  KEY idx_false_alarm_appeals_strike (strike_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Short-lived key that lets a citizen suspended for false alarms (who can't
-- log in) see their strikes and appeal, after proving their password.
CREATE TABLE IF NOT EXISTS sanction_access_tokens (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY idx_sanction_access_tokens_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Suspension bookkeeping: until when, and whether it came from false alarms
-- (only those auto-lift / are lifted when a strike is removed).
ALTER TABLE users
  ADD COLUMN suspended_until DATETIME NULL AFTER account_status,
  ADD COLUMN suspension_source VARCHAR(20) NULL AFTER suspended_until;

INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('sanctions.false_alarm',
  '{"enabled":true,"window_days":90,"warn_at":1,"suspend_at":2,"suspend_days":7,"review_at":3,"guest_enabled":true,"guest_window_days":30,"guest_block_at":2,"guest_block_days":7}',
  'False-alarm sanctions: strike window, warning / timed suspension / review thresholds, and guest phone-number blocking.');
