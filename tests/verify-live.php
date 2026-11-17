<?php
/**
 * Check a running website against the content: every published page, the
 * generated files, the historic redirects and a few assets. Use it right
 * after go-live (and any time later):
 *
 *   php tests/verify-live.php https://theomegagroup.in
 *   php tests/verify-live.php http://127.0.0.1:8000      (local rehearsal)
 *
 * Expected values come from this repository's content/ folder (the content
 * copied to the server at go-live), so run it before editing in the dashboard.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^https?://#', $base)) {
    fwrite(STDERR, "Usage: php tests/verify-live.php https://theomegagroup.in\n");
    exit(2);
}

/** @return array{status:int, body:string, location:string, type:string} */
function fetch(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'omega-verify/1.0',
    ]);
    $raw = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $size);
    preg_match('/^Location:\s*(\S+)/mi', $headers, $loc);
    preg_match('/^Content-Type:\s*([^\r\n]+)/mi', $headers, $type);
    return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => substr($raw, $size), 'location' => $loc[1] ?? '', 'type' => $type[1] ?? ''];
}

$problems = [];
$ok = 0;
function result(string $what, bool $good, string $detail = ''): void
{
    global $problems, $ok;
    if ($good) {
        $ok++;
    } else {
        $problems[] = "$what" . ($detail !== '' ? " — $detail" : '');
    }
}

echo "Checking $base …\n";

$pages = array_filter(pages_all(), fn ($p) => ($p['status'] ?? '') === 'published');
foreach ($pages as $page) {
    $url = $page['urlPath'];
    $r = fetch($base . $url);
    $expectStatus = $page['type'] === 'not-found' ? [200, 404] : [200];
    if (!in_array($r['status'], $expectStatus, true)) {
        result($url, false, "HTTP {$r['status']}" . ($r['location'] ? " → {$r['location']}" : ''));
        continue;
    }
    preg_match('#<title[^>]*>(.*?)</title>#s', $r['body'], $t);
    $title = html_entity_decode(trim($t[1] ?? ''), ENT_QUOTES | ENT_HTML5);
    if ($title !== $page['seo']['title']) {
        result($url, false, "title is «{$title}», expected «{$page['seo']['title']}»");
        continue;
    }
    if (!str_contains($r['body'], 'G-FSNHC0N7BD')) {
        result($url, false, 'Google Analytics tag missing');
        continue;
    }
    result($url, true);
}
echo '  pages: ' . count($pages) . " checked\n";

foreach (['/sitemap.xml' => '<urlset', '/sitemap-blog.xml' => '<urlset', '/llms.txt' => '# ', '/api/posts.json' => '"urlPath"'] as $path => $needle) {
    $r = fetch($base . $path);
    result($path, $r['status'] === 200 && str_contains($r['body'], $needle), "HTTP {$r['status']}");
}
$sm = fetch($base . '/sitemap.xml')['body'] . fetch($base . '/sitemap-blog.xml')['body'];
preg_match_all('#<loc>https?://[^/]+([^<]*)</loc>#', $sm, $locs);
$missing = array_filter($locs[1], fn ($p) => !isset(page_index()[$p]));
result('sitemaps list only real pages', !$missing, implode(', ', array_slice($missing, 0, 5)));

$redirects = (json_read(OMEGA_ROOT . '/redirects.json') ?? [])['redirects'] ?? [];
$checked = 0;
foreach (array_slice($redirects, 0, 25) as $rule) {
    $r = fetch($base . $rule['from']);
    $checked++;
    result('redirect ' . $rule['from'], $r['status'] === 301 && str_ends_with($r['location'], $rule['to']), "HTTP {$r['status']} → {$r['location']}");
}
echo "  historic redirects: $checked checked\n";

$r = fetch($base . '/no-such-page-' . bin2hex(random_bytes(3)) . '/');
result('unknown URL gives 404', $r['status'] === 404, "HTTP {$r['status']}");
foreach (['/css/style.css', '/js/main.js', '/img/logo.png'] as $asset) {
    result($asset, fetch($base . $asset)['status'] === 200);
}
foreach (['/app/config.php', '/content/site.json', '/templates/layout.php', '/admin/_init.php'] as $private) {
    $r = fetch($base . $private);
    result("$private is not public", $r['status'] !== 200 || !str_contains($r['body'], '<?php'), "HTTP {$r['status']}");
}
$r = fetch($base . '/admin/');
result('/admin/ asks for login', in_array($r['status'], [302, 303], true) && str_contains($r['location'], 'login'), "HTTP {$r['status']}");

echo "\n$ok checks passed, " . count($problems) . " problem(s)\n";
foreach ($problems as $p) {
    echo "  ✗ $p\n";
}
exit($problems ? 1 : 0);
