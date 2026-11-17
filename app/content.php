<?php
/**
 * Content storage: one JSON file per page, plus site-wide settings and
 * dashboard-managed redirects. No database.
 *
 * Layout of the content directory (see config 'content_dir'):
 *   site.json                     business info, nav, footer, CTA, tracking IDs
 *   pages/<id>.json               one file per page
 *   redirects.json                {"/old/": {"to": "/new/", "code": 301}, ...}
 *   _history/<id>/<stamp>.json    previous versions of each page
 *   _trash/<id>.json              deleted pages (restorable)
 *   _cache/                       URL index + rendered page cache (safe to delete)
 *
 * Every page has at least: id, type, status ("draft"|"published"), urlPath,
 * seo{title, description, ...}. Optional "aliases": extra paths that render
 * the same page (e.g. "/" for the home page whose urlPath is "/index.html").
 */

declare(strict_types=1);

const PAGE_TYPES = ['home', 'page', 'blog-post', 'location', 'project', 'blog-index',
    'project-index', 'service', 'contact', 'landing', 'author'];
const PAGE_STATUSES = ['draft', 'published'];

function content_dir(): string
{
    return rtrim((string) config('content_dir'), '/');
}

function content_path(string $relative = ''): string
{
    return content_dir() . ($relative === '' ? '' : '/' . ltrim($relative, '/'));
}

/* ------------------------------------------------------------------ */
/* Site settings                                                       */
/* ------------------------------------------------------------------ */

/** site.json, loaded once per request. */
function site(?string $key = null)
{
    static $cache = [];
    $dir = content_dir();
    if (!array_key_exists($dir, $cache)) {
        $site = json_read(content_path('site.json'));
        if ($site === null) {
            throw new RuntimeException('Missing site.json in content directory ' . $dir);
        }
        $cache[$dir] = $site;
    }
    $site = $cache[$dir];
    return $key === null ? $site : ($site[$key] ?? null);
}

function site_save(array $site): void
{
    json_write(content_path('site.json'), $site);
    cache_clear_pages();
}

/* ------------------------------------------------------------------ */
/* Paths                                                               */
/* ------------------------------------------------------------------ */

/**
 * Normalise a request URI to a clean path: no query string, decoded,
 * single slashes, leading slash. Returns null for anything suspicious
 * (path traversal, control characters).
 */
function path_normalize(string $uri): ?string
{
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }
    $path = rawurldecode($path);
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $path) || preg_match('#(^|/)\.\.?(/|$)#', $path)) {
        return null;
    }
    $path = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? '', '/');
    return $path;
}

/** True if a URL path is allowed for a content page. */
function path_is_valid_page_url(string $path): bool
{
    if (!preg_match('#^/([a-z0-9][a-z0-9.\-]*(/[a-z0-9][a-z0-9.\-]*)*/?)?$#', $path)) {
        return false;
    }
    foreach ((array) config('reserved_prefixes') as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/') || str_starts_with($path, $prefix . '.')) {
            return false;
        }
    }
    return true;
}

/* ------------------------------------------------------------------ */
/* Pages                                                               */
/* ------------------------------------------------------------------ */

function page_id_is_valid(string $id): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,150}$/', $id);
}

function page_file(string $id): string
{
    if (!page_id_is_valid($id)) {
        throw new InvalidArgumentException("Invalid page id: $id");
    }
    return content_path("pages/$id.json");
}

function page_load(string $id): ?array
{
    return page_id_is_valid($id) ? json_read(page_file($id)) : null;
}

/** Every page (drafts included), keyed by id, sorted by id. */
function pages_all(): array
{
    $pages = [];
    foreach (glob(content_path('pages/*.json')) ?: [] as $file) {
        $page = json_read($file);
        if ($page && isset($page['id'])) {
            $pages[$page['id']] = $page;
        }
    }
    ksort($pages);
    return $pages;
}

/**
 * URL -> page id lookup table, cached in _cache/index.json. Rebuilt
 * automatically when the pages folder changes (a page added, deleted or
 * saved through page_save()). After editing page files by hand, run
 * `php app/cli.php rebuild`.
 */
function page_index(): array
{
    $memo = &page_index_memo();
    $dir = content_dir();
    clearstatcache(true, content_path('pages'));
    $pagesDir = content_path('pages');
    $pagesMtime = is_dir($pagesDir) ? (int) filemtime($pagesDir) : 0;

    if (isset($memo[$dir]) && $memo[$dir]['pagesMtime'] === $pagesMtime) {
        return $memo[$dir]['paths'];
    }
    $indexFile = content_path('_cache/index.json');
    $index = is_file($indexFile) ? json_read($indexFile) : null;
    if (!$index || ($index['pagesMtime'] ?? -1) !== $pagesMtime) {
        $index = page_index_rebuild();
    }
    $memo[$dir] = $index;
    return $index['paths'];
}

/** Per-request copy of the index, shared by page_index() and page_index_rebuild(). */
function &page_index_memo(): array
{
    static $memo = [];
    return $memo;
}

function page_index_rebuild(): array
{
    clearstatcache();
    $paths = [];
    foreach (pages_all() as $id => $page) {
        foreach (array_merge([$page['urlPath'] ?? ''], (array) ($page['aliases'] ?? [])) as $path) {
            if (is_string($path) && $path !== '') {
                $paths[$path] = $id;
            }
        }
    }
    $pagesDir = content_path('pages');
    $index = [
        'pagesMtime' => is_dir($pagesDir) ? (int) filemtime($pagesDir) : 0,
        'builtAt' => date('c'),
        'paths' => $paths,
    ];
    json_write(content_path('_cache/index.json'), $index);
    $memo = &page_index_memo();
    $memo[content_dir()] = $index;
    return $index;
}

/** The page published at a URL path (or alias), or null. Drafts excluded unless asked. */
function page_find_by_path(string $path, bool $includeDrafts = false): ?array
{
    $id = page_index()[$path] ?? null;
    if ($id === null) {
        return null;
    }
    $page = page_load($id);
    if (!$page) {
        return null;
    }
    if (!$includeDrafts && ($page['status'] ?? '') !== 'published') {
        return null;
    }
    return $page;
}

/** Validation errors for a page, keyed by field. Empty array = valid. */
function page_validate(array $page): array
{
    $errors = [];
    $id = (string) ($page['id'] ?? '');
    if (!page_id_is_valid($id)) {
        $errors['id'] = 'Use lowercase letters, numbers and hyphens only.';
    }
    if (!in_array($page['type'] ?? null, PAGE_TYPES, true)) {
        $errors['type'] = 'Unknown page type.';
    }
    if (!in_array($page['status'] ?? null, PAGE_STATUSES, true)) {
        $errors['status'] = 'Status must be draft or published.';
    }
    $paths = array_merge([(string) ($page['urlPath'] ?? '')], (array) ($page['aliases'] ?? []));
    foreach ($paths as $i => $path) {
        $field = $i === 0 ? 'urlPath' : 'aliases';
        if (!is_string($path) || !path_is_valid_page_url($path)) {
            $errors[$field] = "Invalid or reserved URL: $path";
            continue;
        }
        $owner = page_index()[$path] ?? null;
        if ($owner !== null && $owner !== $id) {
            $errors[$field] = "URL $path is already used by page \"$owner\".";
        }
    }
    if (trim((string) ($page['seo']['title'] ?? '')) === '') {
        $errors['seo.title'] = 'SEO title is required.';
    }
    return $errors;
}

/**
 * Validate and save a page. Keeps the previous version in _history/,
 * stamps createdAt/updatedAt, rebuilds the URL index and clears the page
 * cache. Throws InvalidArgumentException (with ->errors) if invalid.
 */
function page_save(array $page): array
{
    $errors = page_validate($page);
    if ($errors) {
        throw new PageValidationException($errors);
    }
    $id = $page['id'];
    $file = page_file($id);
    $existing = json_read($file);
    $now = date('c');

    if ($existing) {
        page_history_add($id, $existing);
        $page['createdAt'] = $existing['createdAt'] ?? $now;
    } else {
        $page['createdAt'] = $page['createdAt'] ?? $now;
    }
    $page['updatedAt'] = $now;

    json_write($file, $page);
    page_index_rebuild();
    cache_clear_pages();
    return $page;
}

/** Move a page to _trash/. Returns false if it didn't exist. */
function page_delete(string $id): bool
{
    $page = page_load($id);
    if (!$page) {
        return false;
    }
    page_history_add($id, $page);
    $page['deletedAt'] = date('c');
    json_write(content_path("_trash/$id.json"), $page);
    unlink(page_file($id));
    page_index_rebuild();
    cache_clear_pages();
    return true;
}

/** Bring a page back from _trash/. Fails if its URL was taken meanwhile. */
function page_restore_from_trash(string $id): array
{
    if (!page_id_is_valid($id)) {
        throw new InvalidArgumentException("Invalid page id: $id");
    }
    $trashed = json_read(content_path("_trash/$id.json"));
    if (!$trashed) {
        throw new RuntimeException("Page \"$id\" is not in the trash.");
    }
    if (page_load($id)) {
        throw new RuntimeException("A page with id \"$id\" already exists.");
    }
    unset($trashed['deletedAt']);
    $saved = page_save($trashed);
    unlink(content_path("_trash/$id.json"));
    return $saved;
}

/** Ids of pages currently in the trash. */
function pages_in_trash(): array
{
    $ids = array_map(fn ($f) => basename($f, '.json'), glob(content_path('_trash/*.json')) ?: []);
    sort($ids);
    return $ids;
}

/* ------------------------------------------------------------------ */
/* History                                                             */
/* ------------------------------------------------------------------ */

function page_history_add(string $id, array $version): void
{
    $dir = content_path("_history/$id");
    $stamp = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
    json_write("$dir/$stamp.json", $version);

    $files = glob("$dir/*.json") ?: [];
    sort($files);
    $excess = count($files) - (int) config('history_keep');
    for ($i = 0; $i < $excess; $i++) {
        @unlink($files[$i]);
    }
}

/** Saved versions of a page, newest first: [['version' => stamp, 'updatedAt' => ...], ...] */
function page_history(string $id): array
{
    if (!page_id_is_valid($id)) {
        return [];
    }
    $files = glob(content_path("_history/$id/*.json")) ?: [];
    rsort($files);
    return array_map(function ($file) {
        $data = json_read($file) ?? [];
        return [
            'version' => basename($file, '.json'),
            'updatedAt' => $data['updatedAt'] ?? null,
            'title' => $data['seo']['title'] ?? ($data['title'] ?? null),
        ];
    }, $files);
}

/** Restore an older version (the current one goes to history first). */
function page_restore_version(string $id, string $version): array
{
    if (!page_id_is_valid($id) || !preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{4}$/', $version)) {
        throw new InvalidArgumentException('Invalid page id or version.');
    }
    $old = json_read(content_path("_history/$id/$version.json"));
    if (!$old) {
        throw new RuntimeException("Version $version of \"$id\" not found.");
    }
    unset($old['deletedAt']);
    return page_save($old);
}

/* ------------------------------------------------------------------ */
/* Redirects managed from the dashboard                                */
/* (the ~90 historic redirects stay in .htaccess)                      */
/* ------------------------------------------------------------------ */

function redirects_all(): array
{
    return json_read(content_path('redirects.json')) ?? [];
}

/** ['to' => '/new/', 'code' => 301] for a path, or null. */
function redirect_find(string $path): ?array
{
    $redirects = redirects_all();
    return $redirects[$path] ?? null;
}

/**
 * Add or update a redirect. Keeps the table loop-free: anything that used
 * to point at $from now points straight at $to, and a redirect away from
 * $to (which would loop back) is removed.
 */
function redirect_set(string $from, string $to, int $code = 301): void
{
    if ($from === $to) {
        throw new InvalidArgumentException('A redirect cannot point to itself.');
    }
    if (!in_array($code, [301, 302, 410], true)) {
        throw new InvalidArgumentException('Redirect code must be 301, 302 or 410.');
    }
    $redirects = redirects_all();
    unset($redirects[$to]);
    foreach ($redirects as $source => $rule) {
        if (($rule['to'] ?? null) === $from) {
            $redirects[$source]['to'] = $to;
        }
    }
    $redirects[$from] = ['to' => $to, 'code' => $code, 'createdAt' => date('c')];
    ksort($redirects);
    json_write(content_path('redirects.json'), $redirects);
    cache_clear_pages();
}

function redirect_remove(string $from): void
{
    $redirects = redirects_all();
    if (isset($redirects[$from])) {
        unset($redirects[$from]);
        json_write(content_path('redirects.json'), $redirects);
        cache_clear_pages();
    }
}

final class PageValidationException extends InvalidArgumentException
{
    public array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Page is invalid: ' . implode(' ', $errors));
    }
}
