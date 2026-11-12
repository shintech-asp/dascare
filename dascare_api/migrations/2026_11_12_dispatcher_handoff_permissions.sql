-- Dispatchers create/record facility handoffs now that the endpoints check
-- hospital.handoffs.* (they used to accept any staff member).
INSERT IGNORE INTO org_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM org_roles r
JOIN rbac_permissions p ON 1=1
JOIN rbac_modules m ON m.id = p.module_id
WHERE r.role_name = 'Dispatcher' AND r.deleted_at IS NULL
  AND m.module_key = 'hospital' AND p.resource = 'handoffs' AND p.action IN ('create','read','update');
