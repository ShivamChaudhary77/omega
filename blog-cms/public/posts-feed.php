<?php
/**
 * JSON feed of published CMS posts, consumed by js/blog-cms-grid.js on
 * blog.html. Kept completely separate from data/blog-data.json (the 79
 * existing static posts' source) — this only ever returns CMS-managed
 * posts, so the existing blog.js / blog-data.json pipeline is untouched.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/../includes/seo.php';
require_once __DIR__ . '/../includes/media.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 min — this is a public listing, safe to cache briefly

$limit = min(24, max(1, (int) ($_GET['limit'] ?? 12)));
$rows = blog_post_public_list(null, 1, $limit)['rows'];

$posts = array_map(function (array $p): array {
    $featuredImageUrl = null;
    if (!empty($p['featured_image_id'])) {
        $media = media_find((int) $p['featured_image_id']);
        $featuredImageUrl = $media ? $media['file_path'] : null;
    }
    return [
        'title' => $p['title'],
        'excerpt' => seo_resolve_meta_description($p),
        'url' => '/' . $p['category_slug'] . '/' . $p['slug'] . '/',
        'category' => $p['category_name'],
        'author' => $p['author_name'] ?? 'The Omega Group Design Team',
        'publishDate' => $p['published_at'],
        'featuredImage' => $featuredImageUrl,
        'readingTimeMinutes' => $p['reading_time'] ? (int) $p['reading_time'] : null,
    ];
}, $rows);

echo json_encode(['posts' => $posts], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
