<?php
// Sponsor inquiries from the public "Become a sponsor" form. GET list; POST {id, status, admin_note}.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/support.php';
header('Content-Type: application/json');

$isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
requirePlatformExecutive($pdo, $isGet ? 'support.sponsors.read' : 'support.sponsors.update');
$uid = (int) ($_SESSION['user_id'] ?? 0);

try {
    if ($isGet) {
        $rows = $pdo->query('SELECT id, organization_name, contact_name, email, phone, category, website_url, message, status, admin_note, created_at FROM sponsor_inquiries ORDER BY FIELD(status, \'new\', \'contacted\', \'accepted\', \'declined\'), created_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['category_label'] = SPONSOR_CATEGORIES[$r['category']] ?? $r['category']; }
        unset($r);
        echo json_encode(['success' => true, 'inquiries' => $rows]);
        exit;
    }
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($in['id'] ?? 0);
    $status = (string) ($in['status'] ?? '');
    $note = mb_substr(trim(strip_tags((string) ($in['admin_note'] ?? ''))), 0, 250);
    if ($id <= 0 || !in_array($status, ['new', 'contacted', 'accepted', 'declined'], true)) platformJsonError(422, 'Choose an inquiry and a status.');
    $u = $pdo->prepare('UPDATE sponsor_inquiries SET status = ?, admin_note = NULLIF(?, \'\') WHERE id = ?');
    $u->execute([$status, $note, $id]);
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$uid, 'platform.sponsor_inquiry_' . $status, 'sponsor_inquiry', $id, json_encode(['note' => $note]), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    echo json_encode(['success' => true, 'message' => 'Inquiry updated.']);
} catch (Throwable $e) {
    error_log('Platform inquiries failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save that.');
}
