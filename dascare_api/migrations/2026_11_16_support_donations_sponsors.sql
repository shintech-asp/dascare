-- Voluntary donations (PayMongo) + sponsors. Citizens never pay for emergency features.
CREATE TABLE IF NOT EXISTS support_campaigns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(120) NOT NULL,
  purpose VARCHAR(500) NOT NULL,
  period ENUM('month','year','custom') NOT NULL DEFAULT 'month',
  goal_centavos BIGINT UNSIGNED NOT NULL,
  starts_on DATE NULL,
  ends_on DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS donations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(24) NOT NULL,
  campaign_id INT UNSIGNED NULL,
  amount_centavos BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','paid','failed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending',
  source ENUM('paymongo','manual') NOT NULL DEFAULT 'paymongo',
  method VARCHAR(40) NULL,
  paymongo_checkout_id VARCHAR(80) NULL,
  paymongo_payment_id VARCHAR(80) NULL,
  donor_name VARCHAR(120) NULL,
  donor_email VARCHAR(190) NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  show_on_wall TINYINT(1) NOT NULL DEFAULT 0,
  message VARCHAR(255) NULL,
  admin_note VARCHAR(255) NULL,
  paid_at DATETIME NULL,
  recorded_by_user_id BIGINT UNSIGNED NULL,
  receipt_sent_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_donations_reference (reference),
  UNIQUE KEY uq_donations_checkout (paymongo_checkout_id),
  KEY idx_donations_status (status, paid_at),
  KEY idx_donations_campaign (campaign_id, status, paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sponsors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  tagline VARCHAR(160) NULL,
  website_url VARCHAR(255) NULL,
  category VARCHAR(40) NOT NULL,
  tier ENUM('supporter','partner','presenting') NOT NULL DEFAULT 'supporter',
  logo_file VARCHAR(120) NULL,
  status ENUM('pending','active','paused','ended') NOT NULL DEFAULT 'pending',
  starts_on DATE NULL,
  ends_on DATE NULL,
  sort_order INT NOT NULL DEFAULT 0,
  admin_note VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sponsors_status (status, starts_on, ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sponsor_inquiries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_name VARCHAR(120) NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  category VARCHAR(40) NOT NULL,
  website_url VARCHAR(255) NULL,
  message VARCHAR(1000) NULL,
  status ENUM('new','contacted','accepted','declined') NOT NULL DEFAULT 'new',
  admin_note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inquiries_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Platform RBAC: module "support", granted to the Platform Executive Admin role.
INSERT INTO platform_modules (module_key, module_name, is_active, sort_order)
SELECT 'support', 'Donations & Sponsors', 1, 7 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM platform_modules WHERE module_key = 'support');

INSERT INTO platform_permissions (module_id, resource, action, label)
SELECT m.id, v.resource, v.action, v.label
FROM platform_modules m
JOIN (
  SELECT 'donations' resource, 'read' action, 'View donations' label
  UNION ALL SELECT 'donations', 'create', 'Record a donation received outside PayMongo'
  UNION ALL SELECT 'donations', 'update', 'Mark a donation refunded / cancelled'
  UNION ALL SELECT 'campaigns', 'read', 'View fundraising goals'
  UNION ALL SELECT 'campaigns', 'create', 'Create fundraising goals'
  UNION ALL SELECT 'campaigns', 'update', 'Edit fundraising goals and the support page'
  UNION ALL SELECT 'sponsors', 'read', 'View sponsors and inquiries'
  UNION ALL SELECT 'sponsors', 'create', 'Add sponsors'
  UNION ALL SELECT 'sponsors', 'update', 'Edit sponsors and answer inquiries'
  UNION ALL SELECT 'sponsors', 'delete', 'Remove sponsors'
) v
WHERE m.module_key = 'support'
  AND NOT EXISTS (SELECT 1 FROM platform_permissions p WHERE p.module_id = m.id AND p.resource = v.resource AND p.action = v.action);

INSERT INTO platform_role_permissions (role_id, permission_id)
SELECT 2, p.id FROM platform_permissions p JOIN platform_modules m ON m.id = p.module_id
WHERE m.module_key = 'support'
  AND NOT EXISTS (SELECT 1 FROM platform_role_permissions rp WHERE rp.role_id = 2 AND rp.permission_id = p.id);

INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('support.page', '{"enabled":true,"recipient":"DASCARE project team","intro":"DASCARE is free for everyone who needs an ambulance. Voluntary donations pay for the servers, maps, live updates and SMS that keep it running.","breakdown":[{"label":"Hosting and domain","percent":30},{"label":"Maps and live updates","percent":30},{"label":"SMS and email","percent":15},{"label":"Maintenance and support","percent":25}],"presets":[50,100,200,500],"min_amount":20,"methods":["gcash","paymaya","card","qrph"],"show_wall":true,"thanks":"Thank you for helping keep emergency response free for everyone."}', 'Support page text, amounts and payment methods.');

INSERT INTO support_campaigns (title, purpose, period, goal_centavos, is_active, sort_order)
SELECT 'Keep DASCARE running', 'Hosting, maps, live updates, SMS and maintenance so DASCARE stays free for everyone.', 'month', 1000000, 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM support_campaigns);
