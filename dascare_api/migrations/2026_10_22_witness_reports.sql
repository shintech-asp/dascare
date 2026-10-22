-- Witness (bystander) reports: the SOS sender saw the emergency but isn't
-- the person who needs help. They must describe what they saw (photos
-- optional); responders see the report (organizations/incidents/report.php).
-- Their own medical record is NOT attached to a witness report.
ALTER TABLE emergency_requests
  ADD COLUMN reporter_type ENUM('self','witness') NOT NULL DEFAULT 'self' AFTER source;
