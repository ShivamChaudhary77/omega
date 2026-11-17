<?php
/**
 * Maintenance commands. Run from the repo root:
 *
 *   php app/cli.php rebuild        rebuild the URL index and clear the page cache
 *                                  (needed only after editing content files by hand)
 *   php app/cli.php clear-cache    clear the rendered-page cache
 *   php app/cli.php check          validate every page file and report problems
 *   php app/cli.php render /path/  print the HTML the site would send for a URL
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

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

    default:
        echo "Usage: php app/cli.php rebuild|clear-cache|check|render <path>\n";
}
