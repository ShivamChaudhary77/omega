<?php
/**
 * LOCAL DEVELOPMENT ONLY: router script for PHP's built-in web server.
 *
 *   php -S localhost:8000 router.php
 *       Mirrors production after go-live: real files (CSS, images, the
 *       current static pages) are served as-is; any URL without a real file
 *       goes to the dynamic front controller.
 *
 *   OMEGA_MODE=dynamic php -S localhost:8000 router.php
 *       Prefers dynamic pages: if a content page exists for a URL, it is
 *       rendered even when a static file exists at the same URL. Use this to
 *       compare dynamic pages against the static ones during Phases 2–5.
 *
 *   OMEGA_MODE=live php -S localhost:8000 router.php
 *       Behaves like the server after go-live: the static HTML pages are
 *       ignored (they get deleted at go-live) and everything is dynamic.
 *
 * On the live server this file does nothing (404): it only works under the
 * built-in server (PHP_SAPI "cli-server").
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/app/bootstrap.php';

$path = path_normalize($_SERVER['REQUEST_URI'] ?? '/') ?? '/';

// Same protection the .htaccess files give these folders on the real server.
if (preg_match('#^/(app|templates|content|tests)(/|$)#', $path) || basename($path) === 'router.php') {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// The historic redirects live in .htaccess on the real server as exact-match
// rewrite rules (go-live version); emulate them here so local runs match.
$historic = (json_read(__DIR__ . '/redirects.json') ?? [])['redirects'] ?? [];
foreach ($historic as $rule) {
    if (rtrim($path, '/') === rtrim((string) $rule['from'], '/')) {
        header('Location: ' . absolute_url((string) $rule['to']), true, 301);
        return true;
    }
}

// Dashboard uploads may live outside the repo (OMEGA_UPLOADS_DIR); serve them from there.
if (str_starts_with($path, '/uploads/')) {
    require_once OMEGA_APP . '/media.php';
    $file = uploads_dir() . substr($path, strlen('/uploads'));
    if (!str_contains($path, '..') && is_file($file) && preg_match('/\.(webp|jpe?g|png)$/i', $file)) {
        header('Content-Type: ' . (mime_content_type($file) ?: 'application/octet-stream'));
        readfile($file);
        return true;
    }
    http_response_code(404);
    return true;
}

$mode = (string) getenv('OMEGA_MODE');
$preferDynamic = $mode === 'dynamic' || $mode === 'live';
$hasDynamicPage = $preferDynamic && (page_find_by_path($path) !== null || isset(SPECIAL_ROUTES[$path]));

if (!$hasDynamicPage) {
    $file = __DIR__ . $path;
    // "live" mode behaves like the server after go-live, when the static
    // HTML pages are deleted: only real non-HTML files are served directly.
    $isStaticHtml = str_ends_with($path, '.html') || (is_dir($file) && is_file(rtrim($file, '/') . '/index.html'));
    if ($mode === 'live' && $isStaticHtml) {
        dispatch_dynamic();
        return true;
    }
    if (is_file($file) || (is_dir($file) && (is_file("$file/index.html") || is_file("$file/index.php")))) {
        return false; // let the built-in server serve the real file
    }
}

dispatch_dynamic();
return true;

/** Hand the request to index.php when it exists (as Apache does), else straight to the front controller. */
function dispatch_dynamic(): void
{
    if (is_file(__DIR__ . '/index.php')) {
        require __DIR__ . '/index.php';
        return;
    }
    front_handle_request();
}
