<?php
declare(strict_types=1);
require_once __DIR__ . '/../reusables/bootstrap.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/auth.php';
require_once __DIR__ . '/../reusables/response.php';
requireAuth($pdo);
jsonResponse(['success' => true, 'message' => 'Dispatch module API placeholder.']);
