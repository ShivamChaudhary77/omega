<?php
/**
 * Compare every static page with its dynamic version (Phase 3 sign-off and
 * the Phase 6 go-live check). Run from the repo root after
 * `python3 scripts/migrate-to-dynamic.py`:
 *
 *   php tests/compare-site.php            summary + every difference
 *   php tests/compare-site.php -v         also list the pages that match
 *   php tests/compare-site.php /about.html  compare one URL only
 *
 * For each static HTML file it checks, against the dynamic page at the same URL:
 *   - HTTP status, <title>, meta description, canonical, robots, <h1>
 *   - structured data: every type the static page had (in its <head>, or
 *     built in the browser by js/schema.js) must still be there
 *   - visible text, links, images and structure (every element's tag and
 *     CSS classes, in order) of the page body (everything above the footer). Blog posts were filled in by JavaScript, so for them the
 *     check is that the dynamic page contains the article's full text.
 *
 * Exits non-zero if any page differs. Not deployed (/tests is excluded).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
config_set(['cache_enabled' => false]);
page_index_rebuild();

$root = dirname(__DIR__);
$verbose = in_array('-v', $argv, true);
$only = array_values(array_filter(array_slice($argv, 1), fn ($a) => $a !== '-v'));

/* ---------------------------------------------------------------- */

function static_urls(string $root): array
{
    $skip = '#^/(node_modules|scss|blog-cms|tests|app|templates|content|lib|css|js|img|video|data|scripts|php|database)/#';
    $urls = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'html') {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($root));
        if (preg_match($skip, $rel) || $rel === '/single-post.html') {
            continue;
        }
        $urls[] = str_ends_with($rel, '/index.html') && $rel !== '/index.html' ? substr($rel, 0, -strlen('index.html')) : $rel;
    }
    sort($urls);
    return $urls;
}

function resolve_url(string $url, string $base): string
{
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
    if ($url === '' || preg_match('#^([a-z][a-z0-9+.-]*:|/|\#)#i', $url)) {
        return preg_replace('#^https://theomegagroup\.in(?=/)#', '', $url);
    }
    $dir = str_ends_with($base, '/') ? $base : rtrim(dirname($base), '/') . '/';
    $path = $dir . $url;
    $parts = [];
    foreach (explode('/', $path) as $seg) {
        if ($seg === '..') {
            array_pop($parts);
        } elseif ($seg !== '.') {
            $parts[] = $seg;
        }
    }
    return implode('/', $parts);
}

function tag(string $html, string $re): string
{
    return preg_match($re, $html, $m) ? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
}

function norm_text(string $html): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

/** The page body above the footer, without scripts, comments, spinner and TOC. */
function body_region(string $html): string
{
    $body = preg_match('#<body[^>]*>(.*)</body>#s', $html, $m) ? $m[1] : $html;
    foreach (['<!-- Footer Start -->', '<div class="container-fluid bg-dark text-white-50 footer', '<footer class="lp-footer'] as $marker) {
        $pos = strpos($body, $marker);
        if ($pos !== false) {
            $body = substr($body, 0, $pos);
            break;
        }
    }
    $body = preg_replace('#<!--.*?-->#s', '', $body);
    $body = preg_replace('#<(script|noscript)\b.*?</\1>#s', '', $body);
    $body = preg_replace('#<div id="spinner".*?<!-- Spinner End -->#s', '', $body);
    $body = preg_replace('#<div id="spinner"[^>]*>\s*<div[^>]*>\s*<span[^>]*>Loading\.\.\.</span>\s*</div>\s*</div>#s', '', $body);
    $body = preg_replace('#<details class="post-toc".*?</details>#s', '', $body);
    // The old MySQL blog CMS teaser on blog.html (always hidden and empty);
    // deliberately retired by the migration.
    $body = preg_replace('#<div class="container-fluid py-5" id="blog-cms-section".*?<div class="row g-4" id="blog-cms-grid"></div>\s*</div>\s*</div>#s', '', $body);
    return (string) $body;
}

function attr_list(string $html, string $attr, string $base): array
{
    preg_match_all('~\s' . $attr . '="([^"]*)"~', $html, $m);
    $out = [];
    foreach ($m[1] as $v) {
        $v = resolve_url($v, $base);
        if ($v !== '' && $v[0] !== '#') {
            $out[] = $v;
        }
    }
    return $out;
}

/** Every element as "tag.class.class" (classes sorted), in document order. */
function structure(string $html): array
{
    preg_match_all('#<([a-z][a-z0-9]*)\b([^>]*)>#i', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as [, $tag, $attrs]) {
        $classes = preg_match('#\sclass="([^"]*)"#', $attrs, $c) ? preg_split('/\s+/', trim($c[1])) : [];
        sort($classes);
        $out[] = strtolower($tag) . ($classes ? '.' . implode('.', array_filter($classes)) : '');
    }
    return $out;
}

function schema_types(string $html): array
{
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
    $types = [];
    foreach ($m[1] as $json) {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            continue;
        }
        foreach (array_is_list($data) ? $data : [$data] as $item) {
            if (isset($item['@type'])) {
                $types[] = $item['@type'];
            }
        }
    }
    return $types;
}

function first_difference(array $a, array $b): string
{
    $n = max(count($a), count($b));
    for ($i = 0; $i < $n; $i++) {
        if (($a[$i] ?? null) !== ($b[$i] ?? null)) {
            return "#$i static=" . json_encode($a[$i] ?? null) . ' dynamic=' . json_encode($b[$i] ?? null)
                . ' (' . count($a) . ' vs ' . count($b) . ')';
        }
    }
    return '';
}

function text_difference(string $a, string $b): string
{
    $len = min(strlen($a), strlen($b));
    for ($i = 0; $i < $len && $a[$i] === $b[$i]; $i++);
    $from = max(0, $i - 50);
    return "static: «" . mb_strcut($a, $from, 140) . "»\n          dynamic: «" . mb_strcut($b, $from, 140) . "»";
}

/* ---------------------------------------------------------------- */

$urls = $only ?: static_urls($root);
$problems = [];
$okCount = 0;

foreach ($urls as $url) {
    $file = $root . (str_ends_with($url, '/') ? $url . 'index.html' : $url);
    if (!is_file($file)) {
        $problems[$url][] = 'no static file';
        continue;
    }
    $static = (string) file_get_contents($file);
    $res = front_dispatch('GET', $url);
    $dyn = $res['body'];
    $issues = [];

    if ($res['status'] !== 200) {
        $problems[$url] = ["dynamic status {$res['status']}"];
        continue;
    }
    $page = page_find_by_path($url);
    $isPost = ($page['type'] ?? '') === 'blog-post';

    $checks = [
        'title' => '#<title[^>]*>(.*?)</title>#s',
        'description' => '#<meta name="description"[^>]*content="([^"]*)"#',
        'canonical' => '#<link rel="canonical"[^>]*href="([^"]*)"#',
        'h1' => '#<h1[^>]*>(.*?)</h1>#s',
    ];
    foreach ($checks as $name => $re) {
        $a = tag($static, $re);
        $b = tag($dyn, $re);
        if ($name === 'h1') {
            $a = norm_text($a);
            $b = norm_text($b);
            if ($isPost) {
                continue; // static post h1 was filled in by JavaScript
            }
        }
        if ($name === 'canonical') {
            $a = rtrim($a, '/');
            $b = rtrim($b, '/');
        }
        if ($a !== $b) {
            $issues[] = "$name differs: static «{$a}» dynamic «{$b}»";
        }
    }
    $robotsA = tag($static, '#<meta name="robots"[^>]*content="([^"]*)"#') ?: 'index, follow';
    $robotsB = tag($dyn, '#<meta name="robots"[^>]*content="([^"]*)"#');
    if (str_contains($robotsA, 'noindex') !== str_contains($robotsB, 'noindex')) {
        $issues[] = "robots differs: static «{$robotsA}» dynamic «{$robotsB}»";
    }

    $staticTypes = array_diff(schema_types($static), ['Organization', 'LocalBusiness']);
    if (str_contains($static, 'schema.js')) {
        if (str_contains($static, 'aria-label="breadcrumb"')) {
            $staticTypes[] = 'BreadcrumbList';
        }
        if (str_contains($static, 'data-faq-schema')) {
            $staticTypes[] = 'FAQPage';
        }
    }
    $dynTypes = schema_types($dyn);
    $missing = array_diff(array_unique($staticTypes), $dynTypes);
    if ($missing) {
        $issues[] = 'structured data missing: ' . implode(', ', $missing);
    }
    if (!in_array('LocalBusiness', $dynTypes, true)) {
        $issues[] = 'LocalBusiness schema missing';
    }

    $regionA = body_region($static);
    $regionB = body_region($dyn);
    if ($isPost) {
        $articleText = norm_text((string) $page['contentHtml']);
        if (!str_contains(norm_text($regionB), $articleText)) {
            $issues[] = 'article text not fully present in the dynamic page';
        }
    } else {
        $ta = norm_text($regionA);
        $tb = norm_text($regionB);
        if ($ta !== $tb) {
            $issues[] = "visible text differs:\n          " . text_difference($ta, $tb);
        }
        $sa = structure($regionA);
        $sb = structure($regionB);
        if ($sa !== $sb) {
            $issues[] = 'structure/styling differs: ' . first_difference($sa, $sb);
        }
        foreach (['href' => 'links', 'src' => 'images'] as $attr => $label) {
            $la = attr_list($regionA, $attr, $url);
            $lb = attr_list($regionB, $attr, $url);
            if ($la !== $lb) {
                $issues[] = "$label differ: " . first_difference($la, $lb);
            }
        }
    }

    if ($issues) {
        $problems[$url] = $issues;
    } else {
        $okCount++;
        if ($verbose) {
            echo "  ok   $url\n";
        }
    }
}

foreach ($problems as $url => $issues) {
    echo "  DIFF $url\n";
    foreach ($issues as $issue) {
        echo "       - $issue\n";
    }
}

$missingPages = array_diff(array_keys(page_index()), $urls, ['/']);
echo "\n" . count($urls) . " static page(s) compared: $okCount match, " . count($problems) . " differ.\n";
if (!$only && $missingPages) {
    echo 'Dynamic-only URLs (no static file): ' . implode(', ', $missingPages) . "\n";
}
exit($problems ? 1 : 0);
