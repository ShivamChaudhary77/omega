<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/post_renderer.php';

$user = auth_require_login();

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? blog_post_find($id) : null;
if (!$post) {
    http_response_code(404);
    exit('Blog not found.');
}
if (!perm_can_manage_post($user, $post)) {
    http_response_code(403);
    exit('You do not have permission to preview this blog.');
}

$category = blog_category_find((int) $post['category_id']);
$authorStmt = blog_db()->prepare('SELECT name FROM blog_users WHERE id = :id');
$authorStmt->execute([':id' => $post['author_id']]);
$author = ['name' => $authorStmt->fetchColumn() ?: 'Shivam Kumar'];
$related = $category ? blog_post_related($post['id'], $category['id']) : [];

render_public_post_page($post, $category ?: ['name' => 'Blog', 'slug' => 'blog'], $author, $related, true);
