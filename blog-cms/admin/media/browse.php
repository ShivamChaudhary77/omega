<?php
/**
 * JSON endpoint backing the media-picker modal (see assets/js/admin-editor.js).
 * Returns existing on-disk images (scanned read-only, not yet necessarily in
 * blog_media) merged with already-uploaded blog_media rows, so the modal has
 * one flat list to render regardless of source.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/media.php';

$user = auth_require_login();
header('Content-Type: application/json');

$search = trim((string) ($_GET['q'] ?? ''));

$existing = media_scan_existing_images();
if ($search !== '') {
    $existing = array_values(array_filter($existing, fn($f) => stripos($f['file_name'], $search) !== false));
}

$uploaded = media_list(1, 100, $search !== '' ? $search : null)['rows'];
$uploadedOnly = array_values(array_filter($uploaded, fn($m) => $m['source'] === 'upload'));

echo json_encode([
    'existing' => array_slice($existing, 0, 60),
    'uploaded' => $uploadedOnly,
]);
