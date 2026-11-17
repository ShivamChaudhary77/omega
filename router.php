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

$preferDynamic = getenv('OMEGA_MODE') === 'dynamic';
$hasDynamicPage = $preferDynamic && page_find_by_path($path) !== null;

if (!$hasDynamicPage) {
    $file = __DIR__ . $path;
    if (is_file($file) || (is_dir($file) && (is_file("$file/index.html") || is_file("$file/index.php")))) {
        return false; // let the built-in server serve the real file
    }
}

front_handle_request();
return true;
