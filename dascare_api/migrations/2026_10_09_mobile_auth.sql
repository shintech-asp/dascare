-- DASCARE mobile app (dascare_mobile/) — Phase 1: token auth.
-- Additive only: two NEW tables, no existing table is altered.
--
-- Apply:
--   /Applications/XAMPP/xamppfiles/bin/mariadb -u root dascare < dascare_api/migrations/2026_10_09_mobile_auth.sql
-- Undo:
--   DROP TABLE guest_request_tokens; DROP TABLE api_tokens;

-- Login tokens for the Android app. The web keeps using cookie sessions.
-- Only a SHA-256 hash of each token is stored; the plain token lives only on
-- the phone. token_type:
--   access    = a logged-in citizen's app session (long-lived, revocable)
--   login_2fa = short-lived challenge between password and the emailed
--               2FA code (replaces the web's $_SESSION['pending_2fa_*'])
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `token_type` enum('access','login_2fa') NOT NULL DEFAULT 'access',
  `device_name` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_hash` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`, `token_type`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Private access keys for emergency requests a GUEST sent from the app, so
-- the same phone can track the request and say "not my emergency" (dedup
-- unmerge) without an account. One key per request; hash only.
CREATE TABLE IF NOT EXISTS `guest_request_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guest_request_tokens_hash` (`token_hash`),
  UNIQUE KEY `uq_guest_request_tokens_request` (`emergency_request_id`),
  CONSTRAINT `fk_guest_request_tokens_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
