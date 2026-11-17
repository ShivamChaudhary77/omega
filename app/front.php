<?php
/**
 * Front controller: maps a request to a response.
 *
 * Order of checks for GET/HEAD requests:
 *   1. bad/suspicious path           -> 400
 *   2. page cache hit                -> cached response
 *   3. generated files               -> /sitemap.xml, /sitemap-blog.xml, /llms.txt, /api/posts.json
 *   3b. dashboard redirect           -> 301/302 (or 410 Gone)
 *   4. published page at this URL    -> rendered page
 *   5. same URL with a trailing "/"  -> 301 to it (matches Apache's old behaviour)
 *   6. otherwise                     -> 404 with the site's branded 404 page
 *
 * front_dispatch() is pure (returns an array) so tests can call it directly;
 * front_handle_request() sends the result to the browser.
 *
 * Phase 6 (go-live) creates the root index.php that calls
 * front_handle_request(). Until then this code only runs locally through
 * router.php, so the live static site is unaffected.
 */

declare(strict_types=1);

/** @return array{status:int, headers:array<string,string>, body:string} */
function front_dispatch(string $method, string $uri): array
{
    $method = strtoupper($method);
    if ($method !== 'GET' && $method !== 'HEAD') {
        return response(405, 'Method not allowed', ['Allow' => 'GET, HEAD', 'Content-Type' => 'text/plain; charset=utf-8']);
    }

    $path = path_normalize($uri);
    if ($path === null) {
        return response(400, 'Bad request', ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    $cached = cache_get($path);
    if ($cached) {
        $cached['headers']['X-Omega-Cache'] = 'HIT';
        return $cached;
    }

    $special = special_route($path);
    if ($special !== null) {
        $result = response(200, $special[1], ['Content-Type' => $special[0], 'X-Omega-Cache' => 'MISS']);
        cache_put($path, $result);
        return $result;
    }

    $redirect = redirect_find($path);
    if ($redirect) {
        $code = (int) ($redirect['code'] ?? 301);
        if ($code === 410) {
            return not_found_response(410);
        }
        $location = (string) $redirect['to'];
        $query = parse_url($uri, PHP_URL_QUERY);
        if ($query) {
            $location .= (str_contains($location, '?') ? '&' : '?') . $query;
        }
        return response($code, '', ['Location' => $location]);
    }

    $page = page_find_by_path($path);
    if ($page) {
        $result = response(200, render_page($page), [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Omega-Cache' => 'MISS',
        ]);
        cache_put($path, $result);
        return $result;
    }

    if (!str_ends_with($path, '/') && !preg_match('/\.[a-z0-9]+$/i', $path) && page_find_by_path($path . '/')) {
        $query = parse_url($uri, PHP_URL_QUERY);
        return response(301, '', ['Location' => $path . '/' . ($query ? "?$query" : '')]);
    }

    return not_found_response(404);
}

function response(int $status, string $body, array $headers = []): array
{
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

/** Generated files served by the site: path => content type. */
const SPECIAL_ROUTES = [
    '/api/posts.json' => 'application/json; charset=utf-8',
    '/sitemap.xml' => 'application/xml; charset=utf-8',
    '/sitemap-blog.xml' => 'application/xml; charset=utf-8',
    '/llms.txt' => 'text/plain; charset=utf-8',
];

/** [content type, body] for a generated file, or null. */
function special_route(string $path): ?array
{
    if (!isset(SPECIAL_ROUTES[$path])) {
        return null;
    }
    $body = match ($path) {
        '/api/posts.json' => posts_feed_json(),
        '/sitemap.xml' => sitemap_xml('pages'),
        '/sitemap-blog.xml' => sitemap_xml('blog'),
        '/llms.txt' => llms_txt(),
    };
    return [SPECIAL_ROUTES[$path], $body];
}

/**
 * Published blog posts in the shape js/blog.js expects (the same fields as
 * the old data/blog-data.json), newest first.
 */
function posts_feed_json(): string
{
    $feed = array_map(fn ($p) => [
        'slug' => $p['id'],
        'title' => $p['title'] ?? '',
        'category' => $p['category'] ?? '',
        'tags' => array_values((array) ($p['tags'] ?? [])),
        'publishDate' => $p['publishDate'] ?? '',
        'readingTimeMinutes' => (int) ($p['readingTimeMinutes'] ?? 0),
        'featuredImage' => $p['featuredImage'] ?? ['src' => '', 'alt' => '', 'width' => 1200, 'height' => 800],
        'excerpt' => $p['excerpt'] ?? '',
        'urlPath' => $p['urlPath'],
    ], posts_published());
    return (string) json_encode($feed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * The branded 404 page: the published content page with id "not-found" if
 * there is one, otherwise the static 404.html (which carries its own noindex).
 */
function not_found_response(int $status = 404): array
{
    $page = page_load('not-found');
    if ($page && ($page['status'] ?? '') === 'published') {
        return response($status, render_page($page), ['Content-Type' => 'text/html; charset=utf-8', 'X-Robots-Tag' => 'noindex']);
    }
    $file = OMEGA_ROOT . '/404.html';
    if (is_file($file)) {
        return response($status, (string) file_get_contents($file), ['Content-Type' => 'text/html; charset=utf-8', 'X-Robots-Tag' => 'noindex']);
    }
    // Last resort: a simple branded page in the site layout.
    $fallback = [
        'id' => 'not-found', 'type' => 'not-found', 'status' => 'published', 'urlPath' => '/404.html',
        'seo' => ['title' => 'Page Not Found | ' . (site('business')['name'] ?? ''), 'noindex' => true],
        'h1' => 'Page Not Found',
        'intro' => '<p>The page you are looking for may have moved or no longer exists. Try the <a href="/index.html">home page</a> or the <a href="/blog.html">blog</a>.</p>',
    ];
    return response($status, render_page($fallback), ['Content-Type' => 'text/html; charset=utf-8', 'X-Robots-Tag' => 'noindex']);
}

/** Run the current request and send the response. */
function front_handle_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    try {
        $result = front_dispatch($method, $_SERVER['REQUEST_URI'] ?? '/');
    } catch (Throwable $e) {
        error_log('[omega] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $detail = config('debug') ? "\n\n" . $e : '';
        $result = response(500, 'Something went wrong. Please try again shortly.' . $detail, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
    http_response_code($result['status']);
    foreach ($result['headers'] as $name => $value) {
        header("$name: $value");
    }
    if (strtoupper($method) !== 'HEAD') {
        echo $result['body'];
    }
}
