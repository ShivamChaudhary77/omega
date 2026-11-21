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
 *   php app/cli.php apply-fixes <file.json> [--apply]
 *                                  apply a reviewed list of content fixes. Dry run by
 *                                  default; a fix is only applied when the page still
 *                                  holds the expected old value, and every save goes
 *                                  through page_save() so the page history keeps the
 *                                  previous version.
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

    case 'apply-fixes':
        exit(apply_fixes_command($argv[2] ?? '', in_array('--apply', $argv, true)));

    default:
        echo "Usage: php app/cli.php rebuild|clear-cache|check|render <path>|set-password|static-files [root]|apply-fixes <file> [--apply]\n";
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

/**
 * Applies a fixes file: {"fixes": [{"page", "field" (dot path, e.g. seo.title or
 * internalLinks.1.href), "op" ("set": the field must equal "from", or one of the
 * values in "fromAny"; any JSON value, e.g. a page's whole "blocks" list;
 * "replace": the text field must contain "from", every occurrence is replaced),
 * "from", "to"}]}.
 * Returns the exit code.
 */
function apply_fixes_command(string $file, bool $apply): int
{
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($data) || !is_array($data['fixes'] ?? null)) {
        fwrite(STDERR, "Usage: php app/cli.php apply-fixes <file.json> [--apply] (file missing or not valid JSON)\n");
        return 1;
    }
    echo ($apply ? 'APPLYING' : 'DRY RUN (nothing is saved; add --apply to save)') . ': ' . count($data['fixes']) . " fix(es)\n";
    $pages = [];
    $done = $skipped = $already = 0;
    foreach ($data['fixes'] as $n => $fix) {
        $id = (string) ($fix['page'] ?? '');
        $keys = explode('.', (string) ($fix['field'] ?? ''));
        $op = $fix['op'] ?? 'set';
        // "set" may carry any JSON value (text, a list of blocks…) and may accept
        // several old values ("fromAny"); "replace" works on text only.
        $olds = $op === 'set' && is_array($fix['fromAny'] ?? null) ? $fix['fromAny'] : [$fix['from'] ?? ''];
        $from = $op === 'set' ? ($fix['from'] ?? null) : (string) ($fix['from'] ?? '');
        $to = $op === 'set' ? ($fix['to'] ?? null) : (string) ($fix['to'] ?? '');
        $shown = is_string($to) ? $to : '(' . (is_array($to) ? count($to) . ' item(s)' : json_encode($to)) . ')';
        $label = "#$n $id {$fix['field']}";
        $page = $pages[$id] ?? page_load($id);
        if (!$page) {
            echo "  skip  $label: page not found\n";
            $skipped++;
            continue;
        }
        $ref = &$page;
        foreach ($keys as $k) {
            if (!is_array($ref) || !array_key_exists($k, $ref)) {
                unset($ref);
                $ref = null;
                break;
            }
            $ref = &$ref[$k];
        }
        $current = $ref;
        if ($current === null || ($op === 'replace' && !is_string($current))) {
            echo "  skip  $label: field not found\n";
            $skipped++;
        } elseif ($op === 'set' && $current === $to || $op === 'replace' && !str_contains($current, $from) && str_contains($current, $to)) {
            echo "  ok    $label: already fixed\n";
            $already++;
        } elseif ($op === 'set' && in_array($current, $olds, true)) {
            $ref = $to;
            echo "  fix   $label: \"$shown\"\n";
            $done++;
        } elseif ($op === 'replace' && $from !== '' && str_contains($current, $from)) {
            $ref = str_replace($from, $to, $current);
            echo "  fix   $label: \"$from\" -> \"$to\"\n";
            $done++;
        } else {
            echo "  skip  $label: the page was changed since the audit, so it was left alone\n";
            $skipped++;
        }
        unset($ref);
        $pages[$id] = $page;
    }
    $saved = 0;
    if ($apply) {
        foreach ($pages as $id => $page) {
            if ($page === page_load($id)) {
                continue;
            }
            try {
                page_save($page);
                $saved++;
            } catch (PageValidationException $e) {
                echo "  ERROR saving $id: " . json_encode($e->errors ?? $e->getMessage()) . "\n";
            }
        }
        cache_clear_pages();
    }
    echo "\n$done to fix, $already already fixed, $skipped skipped." . ($apply ? " Saved $saved page(s); page cache cleared.\n" : "\n");
    return 0;
}
