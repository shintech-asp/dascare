-- DASCARE mobile app — Phase 7: push notifications (Firebase Cloud Messaging).
-- Additive only: two NEW tables, no existing table is altered.
--
-- Apply:
--   /Applications/XAMPP/xamppfiles/bin/mariadb -u root dascare < dascare_api/migrations/2026_10_09_mobile_push.sql
-- Undo:
--   DROP TABLE push_request_watch; DROP TABLE push_devices;

-- One row per installed app (its FCM registration token). user_id is set
-- while a citizen is logged in on that phone, NULL for a guest phone.
CREATE TABLE IF NOT EXISTS `push_devices` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fcm_token` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `platform` varchar(20) NOT NULL DEFAULT 'android',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_devices_token` (`fcm_token`),
  KEY `idx_push_devices_user` (`user_id`),
  CONSTRAINT `fk_push_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Emergency requests a phone follows without an account (guest SOS sent from
-- that phone — proven by its guest key at registration time).
CREATE TABLE IF NOT EXISTS `push_request_watch` (
  `push_device_id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`push_device_id`, `emergency_request_id`),
  KEY `idx_push_watch_request` (`emergency_request_id`),
  CONSTRAINT `fk_push_watch_device` FOREIGN KEY (`push_device_id`) REFERENCES `push_devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_push_watch_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
