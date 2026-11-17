<?php
/**
 * Rendered-page cache. A published page is rendered once and the HTML kept
 * in _cache/pages/ until anything changes: every page save, delete, restore,
 * redirect change or site.json save clears the whole cache. With ~150 pages
 * a full clear is instant and avoids any risk of a stale page (for example
 * the homepage "latest posts" list after a new post).
 */

declare(strict_types=1);

function cache_enabled(): bool
{
    return (bool) config('cache_enabled');
}

function cache_file(string $path): string
{
    return content_path('_cache/pages/' . sha1($path) . '.json');
}

/** Cached response for a path: ['status' => int, 'headers' => [...], 'body' => string] or null. */
function cache_get(string $path): ?array
{
    if (!cache_enabled()) {
        return null;
    }
    $file = cache_file($path);
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || !isset($data['status'], $data['body'])) {
        return null;
    }
    return $data;
}

function cache_put(string $path, array $response): void
{
    if (!cache_enabled()) {
        return;
    }
    try {
        json_write(cache_file($path), [
            'status' => $response['status'],
            'headers' => $response['headers'] ?? [],
            'body' => $response['body'],
            'cachedAt' => date('c'),
        ]);
    } catch (Throwable $e) {
        // A failed cache write must never break the page itself.
        error_log('[omega] cache write failed: ' . $e->getMessage());
    }
}

function cache_clear_pages(): void
{
    dir_clear(content_path('_cache/pages'));
}
