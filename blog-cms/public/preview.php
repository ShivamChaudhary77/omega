<?php
/**
 * Token-based preview — for sharing an unpublished draft with someone who
 * doesn't have (and shouldn't need) an admin login, e.g. a client. Requires
 * knowing the post's own unpublished_token, a 48-char random value never
 * exposed anywhere public (only visible to logged-in admins on the edit
 * screen) — this is the "secure preview token" the project brief asks for,
 * distinct from the "authenticated preview access" admin/blogs/preview.php
 * provides.
 *
 * Never indexable (forced noindex in post_renderer.php whenever $isPreview
 * is true) and never linked from anywhere crawlable.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/../includes/post_renderer.php';

$token = (string) ($_GET['token'] ?? '');
if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    exit('Not found.');
}

$post = blog_post_find_by_preview_token($token);
if (!$post) {
    http_response_code(404);
    exit('Not found.');
}

$category = blog_category_find((int) $post['category_id']) ?: ['id' => 0, 'name' => 'Blog', 'slug' => 'blog'];
$authorStmt = blog_db()->prepare('SELECT name FROM blog_users WHERE id = :id');
$authorStmt->execute([':id' => $post['author_id']]);
$author = ['name' => $authorStmt->fetchColumn() ?: 'Shivam Kumar'];
$related = $category['id'] ? blog_post_related((int) $post['id'], (int) $category['id']) : [];

render_public_post_page($post, $category, $author, $related, true);
