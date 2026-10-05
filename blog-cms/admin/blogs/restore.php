<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

$user = auth_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require();

$revisionId = (int) ($_POST['revision_id'] ?? 0);
$revision = revision_find($revisionId);
if (!$revision) {
    http_response_code(404);
    exit('Revision not found.');
}

$post = blog_post_find((int) $revision['post_id']);
if (!$post || !perm_can_restore_revisions($user, $post)) {
    http_response_code(403);
    exit('You do not have permission to restore this revision.');
}

$result = revision_restore($revisionId, $user['id']);
if (!$result['ok']) {
    http_response_code(500);
    exit($result['error']);
}

header('Location: /blog-cms/admin/blogs/edit.php?id=' . $result['post_id'] . '&restored=1');
exit;
