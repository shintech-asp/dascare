<?php
// temporary/me.php
// The signed-in temporary (guest) account: its requests, when it will be
// deleted, whether it can be completed now. 401 when not signed in to one.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/temporary_accounts.php';
header('Content-Type: application/json');

$account = tempCurrentAccount($pdo);
if (!$account) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not signed in to a temporary account.']);
    exit;
}
// Keep the session's guest requests in step (e.g. a report linked meanwhile).
$_SESSION['guest_request_ids'] = array_values(array_unique(array_merge(
    array_map('intval', (array) ($_SESSION['guest_request_ids'] ?? [])), tempRequestIds($pdo, (int) $account['id'])
)));
echo json_encode(['success' => true, 'temporary' => tempSessionInfo($pdo, $account)]);
