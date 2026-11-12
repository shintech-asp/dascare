-- Emergency request limit, editable on Platform → DSS & Operations.
INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('emergency.request_limit', '{"max":10,"window_minutes":30}', 'Emergency requests allowed per account/network address within the time window.');
