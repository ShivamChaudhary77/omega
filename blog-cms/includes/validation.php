<?php
/**
 * Small, dependency-free validation + sanitization helpers shared across the
 * admin CRUD screens. Nothing here talks to the database — pure functions
 * only, so they're trivially testable and reusable from both admin/blogs/save.php
 * and admin/categories/*, admin/users/*, etc.
 */

declare(strict_types=1);

function v_clean_str($value, int $maxLen = 255): string
{
    $value = is_string($value) ? trim($value) : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    return mb_substr($value, 0, $maxLen);
}

function v_is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($email) <= 190;
}

/**
 * Slugify a string the same way the existing static blog pipeline does
 * (lowercase, hyphen-separated, no unsafe characters) — see
 * BLOG-README.md's urlPath convention. Keeping the two slug formats
 * identical means a CMS post and a legacy static post are indistinguishable
 * by URL shape.
 */
function v_slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text ?? '');
    return trim($text ?? '', '-');
}

function v_is_valid_slug(string $slug): bool
{
    return $slug !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1 && mb_strlen($slug) <= 255;
}

/** Very small allowlist-based sanitizer for editor HTML — see media.php/seo.php docblocks
 *  for why we don't accept arbitrary HTML. Strips everything not on the allowlist,
 *  strips all `on*` event attributes and javascript: URLs regardless of tag. */
function v_sanitize_html(string $html): string
{
    $allowedTags = '<h2><h3><h4><p><br><strong><b><em><i><ul><ol><li><a><img><blockquote>'
        . '<table><thead><tbody><tr><th><td><code><pre><figure><figcaption><div><span>';
    $clean = strip_tags($html, $allowedTags);

    // Strip on* event handler attributes (onclick="", onerror='', etc.)
    $clean = (string) preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);

    // Strip javascript:/data: URLs in href/src — allow only http(s), mailto, tel, and
    // root-relative paths.
    $clean = (string) preg_replace_callback(
        '/\b(href|src)\s*=\s*(["\'])(.*?)\2/i',
        function (array $m): string {
            $url = trim($m[3]);
            $isSafe = $url === ''
                || substr($url, 0, 1) === '/'
                || substr($url, 0, 1) === '#'
                || preg_match('#^(https?://|mailto:|tel:)#i', $url) === 1;
            return $isSafe ? $m[0] : $m[1] . '=' . $m[2] . $m[2];
        },
        $clean
    );

    return $clean;
}

function v_errors_add(array &$errors, string $field, string $message): void
{
    $errors[$field] = $message;
}
