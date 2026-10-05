<?php
/**
 * Called when an admin picks an "existing site image" in the media modal —
 * lazily creates (or fetches) its blog_media row so it has a real media ID
 * to attach as a featured image / editor image, without ever copying or
 * modifying the underlying file. See includes/media.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/media.php';

$user = auth_require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_verify($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Security check failed.']);
    exit;
}

$relPath = (string) ($data['file_path'] ?? '');
// Only allow paths inside the site's own image directories — never let this
// endpoint be used to register an arbitrary filesystem path as media.
if ($relPath === '' || !preg_match('#^/img/#', $relPath) || strpos($relPath, '..') !== false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid image path.']);
    exit;
}

try {
    $media = media_get_or_create_existing($relPath, $user['id']);
    echo json_encode(['ok' => true, 'media' => $media]);
} catch (InvalidArgumentException $e) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
