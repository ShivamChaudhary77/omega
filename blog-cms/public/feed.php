<?php
/**
 * RSS feed for CMS-managed published posts. Reachable at
 * /blog-cms/public/feed.php — link it from wherever the site's real
 * templates want an RSS <link> (see DEPLOYMENT.md), not added automatically
 * to any existing page by this change.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/../includes/seo.php';

header('Content-Type: application/rss+xml; charset=utf-8');

$posts = blog_post_public_list(null, 1, 20)['rows'];

$e = fn($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0"><channel>' . "\n";
echo '<title>The Omega Group Blog</title>' . "\n";
echo '<link>https://theomegagroup.in/blog.html</link>' . "\n";
echo '<description>Interior design insights from The Omega Group, Gurgaon.</description>' . "\n";
echo '<language>en-in</language>' . "\n";

foreach ($posts as $p) {
    $url = seo_post_url($p, $p['category_slug']);
    echo '<item>' . "\n";
    echo '<title>' . $e($p['title']) . '</title>' . "\n";
    echo '<link>' . $e($url) . '</link>' . "\n";
    echo '<guid>' . $e($url) . '</guid>' . "\n";
    echo '<pubDate>' . date(DATE_RSS, strtotime($p['published_at'])) . '</pubDate>' . "\n";
    echo '<description>' . $e(seo_resolve_meta_description($p)) . '</description>' . "\n";
    echo '</item>' . "\n";
}

echo '</channel></rss>' . "\n";
