<?php
/**
 * Automated tests for the dynamic site. Run from the repo root:
 *
 *   php tests/run.php
 *
 * Uses a throwaway content directory in the system temp folder, so the
 * repo's content/ folder is never touched. Exits non-zero on any failure.
 * (Not deployed: /tests is excluded in .cpanel.yml and denied by .htaccess.)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$tmp = sys_get_temp_dir() . '/omega-test-' . bin2hex(random_bytes(4));
mkdir("$tmp/pages", 0755, true);
copy(dirname(__DIR__) . '/content/site.json', "$tmp/site.json");
putenv("OMEGA_CONTENT_DIR=$tmp");

require dirname(__DIR__) . '/app/bootstrap.php';

$passed = 0;
$failed = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok   $name\n";
    } else {
        $failed[] = $name;
        echo "  FAIL $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function get(string $uri): array
{
    return front_dispatch('GET', $uri);
}

/** Every JSON-LD block in an HTML document, decoded. */
function ld_blocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    return array_map(fn ($j) => json_decode($j, true), $m[1]);
}

function ld_types(string $html): array
{
    return array_map(fn ($b) => $b['@type'] ?? '?', ld_blocks($html));
}

function base_page(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'about-us',
        'type' => 'page',
        'status' => 'published',
        'urlPath' => '/about-us/',
        'navActive' => 'about',
        'seo' => [
            'title' => 'About Us & Our <Story> | The Omega Group',
            'description' => 'Who we are.',
        ],
        'h1' => 'About Us',
        'sections' => [
            ['heading' => 'Our story', 'html' => '<p>Founded in Gurgaon.</p>'],
        ],
        'faqs' => [
            ['question' => 'Where are you based?', 'answer' => 'On Dwarka Expressway.'],
        ],
    ], $overrides);
}

try {
    echo "Content store\n";
    $saved = page_save(base_page());
    check('page_save stamps createdAt/updatedAt', isset($saved['createdAt'], $saved['updatedAt']));
    check('page file written', is_file("$tmp/pages/about-us.json"));
    check('no temp files left behind', glob("$tmp/pages/.*.tmp") === []);
    check('URL index built', (page_index()['/about-us/'] ?? null) === 'about-us');

    $errors = page_validate(base_page(['id' => 'Bad ID']));
    check('invalid id rejected', isset($errors['id']));
    $errors = page_validate(base_page(['id' => 'x', 'urlPath' => '/css/x/']));
    check('reserved URL rejected', isset($errors['urlPath']));
    $errors = page_validate(base_page(['id' => 'other']));
    check('duplicate URL rejected', isset($errors['urlPath']) && str_contains($errors['urlPath'], 'about-us'));
    $errors = page_validate(base_page(['seo' => ['title' => '']]));
    check('missing SEO title rejected', isset($errors['seo.title']));
    try {
        page_save(base_page(['id' => 'Bad ID']));
        check('page_save throws on invalid page', false);
    } catch (PageValidationException $e) {
        check('page_save throws on invalid page', isset($e->errors['id']));
    }
    check('page_load rejects path traversal ids', page_load('../site') === null);

    echo "\nRendering\n";
    $r = get('/about-us/');
    $html = $r['body'];
    check('published page returns 200', $r['status'] === 200, (string) $r['status']);
    check('first render is a cache MISS', ($r['headers']['X-Omega-Cache'] ?? '') === 'MISS');
    check('title escaped', str_contains($html, '<title>About Us &amp; Our &lt;Story&gt; | The Omega Group</title>'));
    check('canonical absolute', str_contains($html, '<link rel="canonical" href="https://theomegagroup.in/about-us/">'));
    check('robots index', str_contains($html, '<meta name="robots" content="index, follow">'));
    check('GA4 tag present', str_contains($html, 'gtag/js?id=G-FSNHC0N7BD'));
    check('Meta Pixel present', str_contains($html, "fbq('init', \"857790543995038\")"));
    check('GSC verification present', str_contains($html, 'google-site-verification'));
    check('schema: LocalBusiness + Breadcrumb + FAQ', ld_types($html) === ['LocalBusiness', 'BreadcrumbList', 'FAQPage'], implode(',', ld_types($html)));
    check('all JSON-LD decodes', !in_array(null, ld_blocks($html), true));
    $lb = ld_blocks($html)[0];
    check('LocalBusiness has address + phone', ($lb['address']['postalCode'] ?? '') === '122006' && ($lb['telephone'] ?? '') !== '');
    check('nav marks About active', str_contains($html, 'href="/about.html" class="nav-item nav-link active">About</a>'));
    check('only one nav item active', substr_count($html, 'nav-link active') === 1);
    check('footer rendered', str_contains($html, '<!-- Footer Start -->') && str_contains($html, '/interior-designers-golf-course-road/'));
    check('CTA rendered by default', str_contains($html, 'Free Luxury Design Consultation'));
    check('section HTML rendered', str_contains($html, '<p>Founded in Gurgaon.</p>'));
    check('FAQ accordion rendered', str_contains($html, 'Where are you based?') && str_contains($html, 'id="faq-about-us"'));
    check('analytics.js included', str_contains($html, '/js/analytics.js'));
    check('schema.js not included (server renders schema)', !str_contains($html, 'schema.js'));

    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $parsed = $dom->loadHTML($html);
    libxml_use_internal_errors($prev);
    check('HTML parses', $parsed && $dom->getElementsByTagName('h1')->length === 1);

    $r2 = get('/about-us/');
    check('second request is a cache HIT', ($r2['headers']['X-Omega-Cache'] ?? '') === 'HIT' && $r2['body'] === $html);
    check('query string ignored for lookup', get('/about-us/?utm_source=x')['status'] === 200);

    page_save(base_page(['h1' => 'About The Omega Group', 'cta' => false]));
    $r3 = get('/about-us/');
    check('saving clears the cache', ($r3['headers']['X-Omega-Cache'] ?? '') === 'MISS' && str_contains($r3['body'], 'About The Omega Group'));
    check('cta:false hides the CTA', !str_contains($r3['body'], 'Free Luxury Design Consultation'));

    echo "\nRouting\n";
    $r = get('/about-us');
    check('missing trailing slash -> 301', $r['status'] === 301 && $r['headers']['Location'] === '/about-us/');
    check('trailing-slash redirect keeps query', get('/about-us?a=1')['headers']['Location'] === '/about-us/?a=1');
    $r = get('/does-not-exist/');
    check('unknown URL -> 404', $r['status'] === 404);
    check('404 uses branded page + noindex', str_contains($r['body'], '<html') && ($r['headers']['X-Robots-Tag'] ?? '') === 'noindex');
    check('POST -> 405', front_dispatch('POST', '/about-us/')['status'] === 405);
    check('path traversal -> 400', get('/../etc/passwd')['status'] === 400);
    check('encoded traversal -> 400', get('/%2e%2e/etc/passwd')['status'] === 400);

    page_save(base_page(['id' => 'secret', 'urlPath' => '/secret/', 'status' => 'draft']));
    check('draft page -> 404', get('/secret/')['status'] === 404);
    check('draft visible to page_find_by_path(includeDrafts)', page_find_by_path('/secret/', true) !== null);

    page_save(base_page([
        'id' => 'home', 'type' => 'page', 'urlPath' => '/index.html', 'aliases' => ['/'],
        'navActive' => 'home', 'seo' => ['title' => 'Home'], 'faqs' => [],
    ]));
    $home = get('/');
    check('alias "/" renders home', $home['status'] === 200 && str_contains($home['body'], '<title>Home</title>'));
    check('home canonical is the root URL', str_contains($home['body'], '<link rel="canonical" href="https://theomegagroup.in/">'));
    check('/index.html renders home too', get('/index.html')['status'] === 200);

    echo "\nRedirects\n";
    redirect_set('/old-about/', '/about-us/');
    $r = get('/old-about/?utm_source=ad');
    check('dashboard redirect -> 301 with query', $r['status'] === 301 && $r['headers']['Location'] === '/about-us/?utm_source=ad');
    redirect_set('/about-us/', '/about/');
    check('chains collapse (old -> new directly)', redirect_find('/old-about/')['to'] === '/about/');
    redirect_set('/about/', '/about-us/');
    check('loop source removed', redirect_find('/about-us/') === null);
    redirect_set('/gone/', '/', 410);
    check('410 Gone', get('/gone/')['status'] === 410);
    redirect_remove('/gone/');
    check('redirect removed', get('/gone/')['status'] === 404);
    try {
        redirect_set('/x/', '/x/');
        check('self-redirect rejected', false);
    } catch (InvalidArgumentException $e) {
        check('self-redirect rejected', true);
    }

    echo "\nHistory and trash\n";
    $history = page_history('about-us');
    check('previous version kept in history', count($history) >= 1);
    $restored = page_restore_version('about-us', end($history)['version']);
    check('restore oldest version', $restored['h1'] === 'About Us');
    check('page_delete moves to trash', page_delete('about-us') && in_array('about-us', pages_in_trash(), true));
    check('deleted page -> 404', get('/about-us/')['status'] === 404);
    page_restore_from_trash('about-us');
    check('restore from trash', get('/about-us/')['status'] === 200 && pages_in_trash() === []);

    config_set(['history_keep' => 3]);
    for ($i = 0; $i < 6; $i++) {
        page_save(base_page(['h1' => "Version $i"]));
    }
    check('history pruned to history_keep', count(page_history('about-us')) === 3);

    echo "\nIndex staleness\n";
    $page = page_load('about-us');
    $page['id'] = 'manual';
    $page['urlPath'] = '/manual/';
    sleep(1); // directory mtime has 1-second resolution
    json_write("$tmp/pages/manual.json", $page);
    check('file added by hand is picked up', get('/manual/')['status'] === 200);
} catch (Throwable $e) {
    $failed[] = 'uncaught exception';
    echo "  FAIL uncaught " . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    dir_clear($tmp);
    @rmdir($tmp);
}

echo "\n$passed passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);
