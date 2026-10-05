<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/seo.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/admin_blog_form.php';

$user = auth_require_login();

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? blog_post_find($id) : null;
if (!$post) {
    http_response_code(404);
    exit('Blog not found.');
}
if (!perm_can_manage_post($user, $post)) {
    http_response_code(403);
    exit('You do not have permission to edit this blog.');
}

$categories = blog_categories_all(false);
$authors = $user['role'] === ROLE_AUTHOR
    ? [['id' => $user['id'], 'name' => $user['name']]]
    : blog_db()->query('SELECT id, name FROM blog_users ORDER BY name')->fetchAll();

admin_layout_start($user, 'blogs', 'Edit: ' . $post['title']);
render_blog_form($user, $post, $categories, $authors);
admin_layout_end();
