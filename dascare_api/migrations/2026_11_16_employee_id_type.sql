-- Employees carry a government ID: a driver's license for Drivers, any accepted government ID for other roles.
ALTER TABLE organization_invitations ADD COLUMN id_type VARCHAR(30) NOT NULL DEFAULT 'drivers_license' AFTER license_number;
ALTER TABLE personnel_profiles ADD COLUMN id_type VARCHAR(30) NOT NULL DEFAULT 'drivers_license' AFTER license_number;
