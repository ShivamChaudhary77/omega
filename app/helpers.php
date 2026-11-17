<?php
/**
 * Small shared helpers: escaping, URLs, JSON file I/O.
 */

declare(strict_types=1);

/** Escape a value for HTML text or a double-quoted attribute. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Absolute URL on the live site for a root-relative path. */
function absolute_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim((string) config('site_url'), '/') . '/' . ltrim($path, '/');
}

/** "My Page Title!" -> "my-page-title" */
function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = str_replace('&', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

/**
 * <script type="application/ld+json"> tag for a schema array, or "" when
 * empty. JSON_HEX_TAG turns "<" and ">" into < / > so content can
 * never close the script tag early.
 */
function json_ld(?array $data): string
{
    if (!$data) {
        return '';
    }
    $json = json_encode(
        strip_empty($data),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    );
    return '<script type="application/ld+json">' . $json . '</script>';
}

/** Recursively drop null, "" and empty-array values (schema hygiene). */
function strip_empty(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $value = strip_empty($value);
            $data[$key] = $value;
        }
        if ($value === null || $value === '' || $value === []) {
            unset($data[$key]);
        }
    }
    return $data;
}

/** Read and decode a JSON file. Returns null if missing; throws if corrupt. */
function json_read(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException("Cannot read $file");
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON in $file: " . json_last_error_msg());
    }
    return $data;
}

/**
 * Write JSON atomically: write a temp file in the same folder, then rename
 * it over the target. A crash mid-write leaves the old file intact instead
 * of a half-written one.
 */
function json_write(string $file, array $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory $dir");
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Cannot encode JSON: ' . json_last_error_msg());
    }
    $tmp = $dir . '/.' . basename($file) . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException("Cannot write $tmp");
    }
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException("Cannot move $tmp to $file");
    }
}

/** Delete every file inside a directory (not the directory itself). */
function dir_clear(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_dir($path)) {
            dir_clear($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}
