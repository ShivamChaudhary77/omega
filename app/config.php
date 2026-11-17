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

    // Where page JSON, site.json, redirects, history, trash and the page
    // cache live. Locally this is the repo's content/ folder. In production
    // it must be OUTSIDE public_html (e.g. /home/theomega/omega-content) so
    // a git deploy can never overwrite edits made in the dashboard.
    'content_dir' => getenv('OMEGA_CONTENT_DIR') ?: dirname(__DIR__) . '/content',

    'cache_enabled' => getenv('OMEGA_CACHE') !== '0',

    'debug' => getenv('OMEGA_DEBUG') === '1',

    'timezone' => 'Asia/Kolkata',

    // How many previous versions of each page to keep in _history/.
    'history_keep' => 30,

    // URL prefixes a content page may never use, because real folders or
    // system areas already live there.
    'reserved_prefixes' => [
        '/admin', '/app', '/templates', '/content', '/tests', '/css', '/js', '/img',
        '/lib', '/php', '/uploads', '/blog-cms', '/data', '/scripts', '/video',
        '/database', '/scss', '/node_modules',
    ],
];
