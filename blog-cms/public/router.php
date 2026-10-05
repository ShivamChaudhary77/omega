<?php
/**
 * Public router for CMS-managed posts ONLY.
 *
 * Apache only ever forwards a request here (see the .htaccess block added
 * by scripts/build-redirects.py / documented in DEPLOYMENT.md) when NO
 * static file or directory already exists at the requested path — so this
 * script never sees a request for one of the 79 existing static posts,
 * never competes with them, and a bug here cannot break them.
 *
 * Expected inbound query params (set by the .htaccess RewriteRule):
 *   category_slug, post_slug
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/../includes/post_renderer.php';

// Promote any due scheduled posts before querying — see blog_functions.php's
// docblock on blog_promote_due_scheduled_posts() for why this replaces a cron.
blog_promote_due_scheduled_posts();

$categorySlug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['category_slug'] ?? '')));
$postSlug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['post_slug'] ?? '')));

$post = ($categorySlug && $postSlug) ? blog_post_public_find($categorySlug, $postSlug) : null;

if (!$post) {
    http_response_code(404);
    // Reuse the site's real 404 page rather than a bare PHP error page, so
    // a mistyped/removed CMS post URL looks identical to any other 404.
    $notFoundPath = dirname(__DIR__, 2) . '/404.html';
    if (is_file($notFoundPath)) {
        readfile($notFoundPath);
    } else {
        echo '<h1>404 — Page Not Found</h1>';
    }
    exit;
}

$category = blog_category_find((int) $post['category_id']);
$author = ['name' => $post['author_name'] ?? 'The Omega Group Design Team'];
$related = blog_post_related((int) $post['id'], (int) $post['category_id']);

render_public_post_page($post, $category, $author, $related, false);
