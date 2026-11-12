-- Dispatch waves: several organizations can hold an open offer for the same incident.
ALTER TABLE incident_offers
  ADD COLUMN wave TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER organization_id;
INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('dispatch.waves', '{"enabled":true,"wave_seconds":120,"wave2_size":2}', 'Dispatch waves: seconds between waves and how many organizations wave 2 adds.');
