<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/activity_log.php';

$user = auth_require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Security check failed.']);
    exit;
}
if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file received.']);
    exit;
}

$result = media_handle_upload($_FILES['file'], $user['id']);
if ($result['ok']) {
    log_activity($user['id'], 'media_uploaded', 'blog_media', (int) $result['media']['id'], 'Uploaded image "' . $result['media']['file_name'] . '"');
}

echo json_encode($result);
