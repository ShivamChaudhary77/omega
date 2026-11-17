<?php
/**
 * Default, non-secret configuration for the dynamic site.
 *
 * Server-specific values (the real content directory, the dashboard password
 * hash added in Phase 4) go in app/config.local.php, which is gitignored and
 * excluded from deploys. Anything set there overrides the values below.
 *
 * Environment variables (useful for local development and tests):
 *   OMEGA_CONTENT_DIR  absolute path to the content directory
 *   OMEGA_CACHE        "0" disables the page cache
 *   OMEGA_DEBUG        "1" shows error details instead of a generic 500 page
 */

declare(strict_types=1);

return [
    'site_url' => 'https://theomegagroup.in',

    // The on-switch. The dynamic site (and the dashboard) only run on the
    // web server once app/config.local.php says 'enabled' => true, which
    // scripts/go-live.sh writes. Until then index.php keeps serving the
    // static pages, even if the code and content folder are deployed.
    // Local development (php -S / CLI) is enabled by default; set
    // OMEGA_ENABLED=0 or 1 to override.
    'enabled' => getenv('OMEGA_ENABLED') !== false ? getenv('OMEGA_ENABLED') === '1' : in_array(PHP_SAPI, ['cli', 'cli-server'], true),

    // Where page JSON, site.json, redirects, history, trash and the page
    // cache live. Locally this is the repo's content/ folder. In production
    // it must be OUTSIDE public_html (e.g. /home/theomega/omega-content) so
    // a git deploy can never overwrite edits made in the dashboard.
    'content_dir' => getenv('OMEGA_CONTENT_DIR') ?: dirname(__DIR__) . '/content',

    'cache_enabled' => getenv('OMEGA_CACHE') !== '0',

    'debug' => getenv('OMEGA_DEBUG') === '1',

    'timezone' => 'Asia/Kolkata',

    // Where dashboard image uploads are stored (served at /uploads/).
    'uploads_dir' => getenv('OMEGA_UPLOADS_DIR') ?: dirname(__DIR__) . '/uploads',

    // Optional one-time setup link for the dashboard password on a server
    // without a terminal: put a long random string here (in
    // app/config.local.php, never in git) and open /admin/setup.php?token=...
    'admin_setup_token' => null,

    // How many previous versions of each page to keep in _history/.
    'history_keep' => 30,

    // URL prefixes a content page may never use, because real folders or
    // system areas already live there.
    'reserved_prefixes' => [
        '/admin', '/api', '/app', '/templates', '/content', '/tests', '/css', '/js', '/img',
        '/lib', '/php', '/uploads', '/blog-cms', '/data', '/scripts', '/video',
        '/database', '/scss', '/node_modules',
    ],
];
