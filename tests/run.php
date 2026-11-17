<?php
/**
 * Automated tests for the dynamic site. Run from the repo root:
 *
 *   php tests/run.php
 *
 * Each suite (tests/suite-*.php) gets its own throwaway content directory in
 * the system temp folder, so the repo's content/ folder is never touched.
 * Exits non-zero on any failure. (Not deployed: /tests is excluded in
 * .cpanel.yml and denied by .htaccess.)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('TESTS_DIR', __DIR__);
define('REPO_DIR', dirname(__DIR__));

require_once REPO_DIR . '/app/bootstrap.php';

$passed = 0;
$failed = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok   $name\n";
    } else {
        $failed[] = $name;
        echo "  FAIL $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function get(string $uri): array
{
    return front_dispatch('GET', $uri);
}

/** Every JSON-LD block in an HTML document, decoded. */
function ld_blocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
    return array_map(fn ($j) => json_decode($j, true), $m[1]);
}

function ld_types(string $html): array
{
    return array_map(fn ($b) => $b['@type'] ?? '?', ld_blocks($html));
}

function base_page(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'about-us',
        'type' => 'page',
        'status' => 'published',
        'urlPath' => '/about-us/',
        'navActive' => 'about',
        'seo' => [
            'title' => 'About Us & Our <Story> | The Omega Group',
            'description' => 'Who we are.',
        ],
        'h1' => 'About Us',
        'sections' => [
            ['heading' => 'Our story', 'html' => '<p>Founded in Gurgaon.</p>'],
        ],
        'faqs' => [
            ['question' => 'Where are you based?', 'answer' => 'On Dwarka Expressway.'],
        ],
    ], $overrides);
}

/** Fresh, empty content directory (with site.json) for a suite. */
function fresh_content_dir(): string
{
    $dir = sys_get_temp_dir() . '/omega-test-' . bin2hex(random_bytes(4));
    mkdir("$dir/pages", 0755, true);
    copy(REPO_DIR . '/content/site.json', "$dir/site.json");
    config_set(['content_dir' => $dir, 'history_keep' => 30, 'cache_enabled' => true]);
    return $dir;
}

$suites = glob(TESTS_DIR . '/suite-*.php') ?: [];
sort($suites);
foreach ($suites as $suiteFile) {
    $tmp = fresh_content_dir();
    echo "\n=== " . basename($suiteFile, '.php') . " ===\n";
    try {
        (static function (string $tmp, string $suiteFile): void {
            require $suiteFile;
        })($tmp, $suiteFile);
    } catch (Throwable $e) {
        $failed[] = basename($suiteFile) . ': uncaught exception';
        echo "  FAIL uncaught " . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    } finally {
        dir_clear($tmp);
        @rmdir($tmp);
    }
}

echo "\n$passed passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);
