<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/activity_log.php';

$user = auth_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require();

$id = (int) ($_POST['id'] ?? 0);
$media = media_find($id);
if (!$media) {
    http_response_code(404);
    exit('Media not found.');
}

// blog_posts.featured_image_id has ON DELETE SET NULL, so deleting a media
// row in use as a featured image just clears that reference rather than
// failing — acceptable for media (unlike categories, an image isn't the
// primary content of a post).
media_delete($id);
log_activity($user['id'], 'media_deleted', 'blog_media', $id, 'Removed media "' . $media['file_name'] . '"');

header('Location: /blog-cms/admin/media/index.php?deleted=1');
exit;
