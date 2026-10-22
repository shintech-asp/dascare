-- Dasmariñas has ONE LGU organization (reusables/organization_requirements.php).
-- Owner's decision (2026-10-22): "DASCARE Demo Rescue Organization" is the
-- LGU; every other City Rescue / LGU organization (incl. Cdrrmo, which stays
-- as it is otherwise) becomes a Private Ambulance provider — accounts, crews,
-- units and history unchanged, but the DSS now only sends them to their
-- listed service-area barangays. Rejected LGU applications are left alone.
-- Safe to run twice.

UPDATE organizations
SET organization_type = 'private_ambulance'
WHERE organization_type = 'city_rescue' AND deleted_at IS NULL
  AND name <> 'DASCARE Demo Rescue Organization'
  AND application_status <> 'rejected';
