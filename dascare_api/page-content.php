<?php
/**
 * GET /page-content.php?slug=about
 * GET /page-content.php?slug=terms-of-service
 * GET /page-content.php?slug=privacy-policy
 * GET /page-content.php?slug=about&with_team=1
 *
 * Public, unauthenticated endpoint — serves the editable text content
 * for the About / Terms of Service / Privacy Policy pages
 * (site_page_sections), plus, only for slug=about, the team credit
 * cards (site_team_members: name + role only, no photo yet) and the
 * version badge (system_settings.platform.app_version).
 *
 * No session/role check on purpose: this is public marketing/legal
 * content, same trust level as the pages themselves.
 *
 * Sits alongside cors.php/session.php (adjust the require paths below
 * if this ends up nested in a subfolder, e.g. public/page-content.php
 * -> __DIR__ . '/../cors.php').
 */

require __DIR__ . '/cors.php';
require_once __DIR__ . '/db/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

const ALLOWED_SLUGS = ['about', 'terms-of-service', 'privacy-policy'];

$slug = $_GET['slug'] ?? '';

if (!in_array($slug, ALLOWED_SLUGS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown or missing slug', 'allowed' => ALLOWED_SLUGS]);
    exit;
}

$response = ['slug' => $slug, 'sections' => []];

try {
    $stmt = $pdo->prepare("
        SELECT section_key, section_num, title, body, list_items
        FROM site_page_sections
        WHERE page_slug = ? AND is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$slug]);

    $response['sections'] = array_map(function ($row) {
        return [
            'id'         => $row['section_key'],
            'num'        => $row['section_num'],
            'title'      => $row['title'],
            'paragraphs' => $row['body'] !== null ? json_decode($row['body']) : [],
            'list'       => $row['list_items'] !== null ? json_decode($row['list_items']) : null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    error_log("Page content fetch error ($slug): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load page content']);
    exit;
}

// Team + version only travel with the About page — Terms/Privacy have no
// use for them, so skip the extra queries entirely for those slugs.
if ($slug === 'about') {

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, role_title
            FROM site_team_members
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute();

        // Photos are intentionally left out for now — the frontend falls
        // back to a placeholder icon for every card.
        $response['team'] = array_map(function ($row) {
            return [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
                'role' => $row['role_title'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    } catch (PDOException $e) {
        error_log('About team fetch error: ' . $e->getMessage());
        $response['team'] = [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT setting_value
            FROM system_settings
            WHERE setting_key = 'platform.app_version'
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // setting_value is stored as JSON (see system_settings in
        // dascare.sql), so a plain string version like "0.1.1" is itself
        // JSON-encoded.
        $response['version'] = $row ? json_decode($row['setting_value']) : null;

    } catch (PDOException $e) {
        error_log('About version fetch error: ' . $e->getMessage());
        // Non-fatal — degrade gracefully instead of failing the whole
        // request over a badge.
        $response['version'] = null;
    }
}

echo json_encode($response);
