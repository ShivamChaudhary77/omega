<?php
/**
 * Maintenance commands. Run from the repo root:
 *
 *   php app/cli.php rebuild        rebuild the URL index and clear the page cache
 *                                  (needed only after editing content files by hand)
 *   php app/cli.php clear-cache    clear the rendered-page cache
 *   php app/cli.php check          validate every page file and report problems
 *   php app/cli.php render /path/  print the HTML the site would send for a URL
 *   php app/cli.php set-password   set or reset the dashboard password
 *   php app/cli.php static-files [root]
 *                                  list the static files the dynamic site replaces
 *                                  (deleted on the server at go-live), relative to root
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'rebuild':
        $index = page_index_rebuild();
        cache_clear_pages();
        echo 'Indexed ' . count($index['paths']) . " URL(s); page cache cleared.\n";
        break;

    case 'clear-cache':
        cache_clear_pages();
        echo "Page cache cleared.\n";
        break;

    case 'check':
        $pages = pages_all();
        $problems = 0;
        foreach ($pages as $id => $page) {
            foreach (page_validate($page) as $field => $message) {
                echo "$id: [$field] $message\n";
                $problems++;
            }
        }
        site();
        echo count($pages) . " page(s) checked, $problems problem(s); site.json OK.\n";
        exit($problems ? 1 : 0);

    case 'render':
        config_set(['cache_enabled' => false]);
        $result = front_dispatch('GET', $argv[2] ?? '/');
        fwrite(STDERR, "HTTP {$result['status']}\n");
        foreach ($result['headers'] as $k => $v) {
            fwrite(STDERR, "$k: $v\n");
        }
        echo $result['body'];
        break;

    case 'set-password':
        require_once OMEGA_APP . '/security.php';
        $ask = function (string $prompt): string {
            fwrite(STDOUT, $prompt);
            $silent = DIRECTORY_SEPARATOR === '/' && posix_isatty_safe();
            if ($silent) {
                shell_exec('stty -echo');
            }
            $line = trim((string) fgets(STDIN));
            if ($silent) {
                shell_exec('stty echo');
                fwrite(STDOUT, "\n");
            }
            return $line;
        };
        $password = $ask('New dashboard password (at least ' . PASSWORD_MIN_LENGTH . ' characters): ');
        if ($password !== $ask('Repeat it: ')) {
            fwrite(STDERR, "The passwords do not match. Nothing changed.\n");
            exit(1);
        }
        try {
            admin_password_set($password);
        } catch (InvalidArgumentException $e) {
            fwrite(STDERR, $e->getMessage() . " Nothing changed.\n");
            exit(1);
        }
        echo "Dashboard password saved (" . admin_password_file() . ").\n";
        break;

    case 'static-files':
        foreach (static_files_replaced($argv[2] ?? OMEGA_ROOT) as $file) {
            echo $file . "\n";
        }
        break;

    default:
        echo "Usage: php app/cli.php rebuild|clear-cache|check|render <path>|set-password|static-files [root]\n";
}

/** True when STDIN is an interactive terminal (so typing can be hidden). */
function posix_isatty_safe(): bool
{
    return function_exists('posix_isatty') ? @posix_isatty(STDIN) : (function_exists('stream_isatty') && @stream_isatty(STDIN));
}

/**
 * Static files that would shadow a dynamic page or generated file (Apache
 * serves a real file before the front controller), relative to $root.
 * Only files that actually exist are listed.
 */
function static_files_replaced(string $root): array
{
    $root = rtrim($root, '/');
    $files = ['sitemap.xml', 'sitemap-blog.xml', 'llms.txt', 'single-post.html', '404.html', 'index.html'];
    foreach (pages_all() as $page) {
        foreach (array_merge([$page['urlPath']], (array) ($page['aliases'] ?? [])) as $path) {
            $path = ltrim((string) $path, '/');
            if ($path === '') {
                continue;
            }
            $files[] = str_ends_with($path, '/') ? $path . 'index.html' : $path;
        }
    }
    $files = array_values(array_unique(array_filter($files, fn ($f) => is_file("$root/$f") && !str_contains($f, '..'))));
    sort($files);
    return $files;
}
