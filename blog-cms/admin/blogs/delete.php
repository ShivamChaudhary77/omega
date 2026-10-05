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

$id = (int) ($_POST['id'] ?? 0);
$post = blog_post_find($id);
if (!$post) {
    http_response_code(404);
    exit('Blog not found.');
}
if (!perm_can_manage_post($user, $post)) {
    http_response_code(403);
    exit('You do not have permission to delete this blog.');
}

blog_post_delete($id, $user['id']);

header('Location: /blog-cms/admin/blogs/index.php?deleted=1');
exit;
