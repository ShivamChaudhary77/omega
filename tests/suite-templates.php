<?php
/**
 * Suite: page-type templates and blocks (Phase 2).
 *
 * Uses real site content from tests/fixtures/pages (built by
 * tests/make-fixtures.py) and compares the dynamic output with the current
 * static pages: same visible text, links, SEO tags and structured data.
 */

declare(strict_types=1);

foreach (glob(TESTS_DIR . '/fixtures/pages/*.json') as $fixture) {
    copy($fixture, "$tmp/pages/" . basename($fixture));
}
page_index_rebuild();

/** Visible text between two markers: no tags, scripts or TOC, whitespace collapsed. */
function main_text(string $html, string $from, string $to): string
{
    $start = strpos($html, $from);
    $end = $start === false ? false : strpos($html, $to, $start);
    if ($start === false || $end === false) {
        return '';
    }
    $part = substr($html, $start, $end - $start);
    $part = preg_replace('#<details class="post-toc".*?</details>#s', '', $part);
    $part = preg_replace('#<script\b.*?</script>#s', '', $part);
    $part = html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $part));
}

/** Every href between two markers, in order. */
function main_links(string $html, string $from, string $to): array
{
    $start = strpos($html, $from);
    $end = $start === false ? false : strpos($html, $to, $start);
    if ($start === false || $end === false) {
        return [];
    }
    // In-page anchors (#...) are skipped: the table of contents is now
    // built on the server, where the static pages filled it with JavaScript.
    preg_match_all('~href="([^"#][^"]*)"~', substr($html, $start, $end - $start), $m);
    return array_map(fn ($h) => html_entity_decode($h), $m[1]);
}

function head_tag(string $html, string $pattern): string
{
    return preg_match($pattern, $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : '';
}

function seo_tags(string $html): array
{
    return [
        'title' => head_tag($html, '#<title[^>]*>(.*?)</title>#s'),
        'description' => head_tag($html, '#<meta name="description"[^>]*content="([^"]*)"#'),
        'canonical' => head_tag($html, '#<link rel="canonical"[^>]*href="([^"]*)"#'),
    ];
}

/** JSON-LD types other than the site-wide LocalBusiness, sorted. */
function page_ld_types(string $html): array
{
    // Ignore the business schema (static posts used a basic Organization; every
    // dynamic page carries the fuller LocalBusiness) and empty placeholder tags the
    // static pages filled in with JavaScript.
    $types = array_values(array_filter(ld_types($html), fn ($t) => !in_array($t, ['LocalBusiness', 'Organization', '?'], true)));
    sort($types);
    return $types;
}

function first_diff(string $a, string $b): string
{
    $len = min(strlen($a), strlen($b));
    for ($i = 0; $i < $len && $a[$i] === $b[$i]; $i++);
    return 'static: "…' . substr($a, max(0, $i - 40), 90) . '…"  dynamic: "…' . substr($b, max(0, $i - 40), 90) . '…"';
}

echo "Blog post\n";
$post = page_load('bespoke-interiors-gurgaon');
$r = get($post['urlPath']);
$html = $r['body'];
check('post renders 200', $r['status'] === 200, (string) $r['status']);
$static = static_reference($post['urlPath'] . 'index.html');
check('post SEO tags match static page', seo_tags($html) === seo_tags($static), json_encode([seo_tags($static), seo_tags($html)]));
check('post schema types match static page', page_ld_types($html) === page_ld_types($static), implode(',', page_ld_types($html)));
$staticArticle = array_values(array_filter(ld_blocks($static), fn ($b) => ($b['@type'] ?? '') === 'BlogPosting'))[0] ?? [];
$dynArticle = array_values(array_filter(ld_blocks($html), fn ($b) => ($b['@type'] ?? '') === 'BlogPosting'))[0] ?? [];
check('BlogPosting schema identical to static page', strip_empty($staticArticle) == strip_empty($dynArticle), json_encode(array_diff_assoc(array_map('json_encode', $staticArticle), array_map('json_encode', $dynArticle))));
$staticFaq = array_values(array_filter(ld_blocks($static), fn ($b) => ($b['@type'] ?? '') === 'FAQPage'))[0] ?? [];
$dynFaq = array_values(array_filter(ld_blocks($html), fn ($b) => ($b['@type'] ?? '') === 'FAQPage'))[0] ?? [];
check('FAQPage schema identical to static page', $staticFaq == $dynFaq);
check('article body is in the HTML (no JavaScript needed)', str_contains($html, 'class="post-body"') && !str_contains($html, 'single-post.js'));
preg_match_all('#<h2[^>]*>(.*?)</h2>#s', $post['contentHtml'], $h2s);
$missing = array_filter($h2s[1], fn ($h) => !str_contains($html, '>' . $h . '</h2>'));
check('every article heading present', !$missing && count($h2s[1]) > 0, implode(' | ', $missing));
check('headings get ids for the table of contents', preg_match('#<h2 id="[a-z0-9-]+">#', $html) === 1);
check('table of contents rendered', str_contains($html, '<details class="post-toc">') && substr_count($html, '<li><a href="#') >= 2);
check('key takeaways rendered', str_contains($html, 'Key Takeaways') && str_contains($html, e($post['keyTakeaways'][0])));
check('FAQ accordion rendered', str_contains($html, 'id="post-faq-accordion"') && str_contains($html, e($post['faqs'][0]['question'])));
check('author box links to author page', str_contains($html, 'Written by <a href="/author/shivam-kumar/">Shivam Kumar</a>'));
check('3 related posts', substr_count(main_text($html, 'Related <span', 'Final CTA Start'), 'Read Article') === 3);
check('related posts exclude the post itself', !str_contains(substr($html, (int) strpos($html, 'Related <span')), 'href="' . $post['urlPath'] . '"'));
check('nav marks Blog active', str_contains($html, 'href="/blog.html" class="nav-item nav-link active">Blog</a>'));
check('blog CTA text', str_contains($html, 'Have a question sparked by this article?'));
check('blog.css loaded', str_contains($html, 'href="/css/blog.css"'));
check('share links built', str_contains($html, 'facebook.com/sharer/sharer.php?u=https%3A%2F%2Ftheomegagroup.in%2Fhome-residential-interiors%2Fbespoke-interiors-gurgaon%2F'));

echo "\nLocation page\n";
foreach (['interior-designers-manesar', 'interior-designers-gurgaon'] as $id) {
    $page = page_load($id);
    $r = get($page['urlPath']);
    $html = $r['body'];
    $static = static_reference($page['urlPath'] . 'index.html');
    check("$id renders 200", $r['status'] === 200);
    check("$id SEO tags match static", seo_tags($html) === seo_tags($static), json_encode([seo_tags($static), seo_tags($html)]));
    check("$id schema types match static (+ server-side)", page_ld_types($html) === ['BreadcrumbList', 'FAQPage'], implode(',', page_ld_types($html)));
    $a = main_text($static, '<!-- Hero Start -->', '<!-- Final CTA Start -->');
    $b = main_text($html, '<!-- Hero Start -->', '<!-- Final CTA Start -->');
    check("$id visible text identical to static", $a === $b && $a !== '', first_diff($a, $b));
    check("$id links identical to static", main_links($static, '<!-- Hero Start -->', '<!-- Final CTA Start -->') === main_links($html, '<!-- Hero Start -->', '<!-- Final CTA Start -->'));
    check("$id TOC built on the server", str_contains($html, '<details class="post-toc">'));
    check("$id CTA text uses ctaContext", str_contains($html, 'Talk to our design team about your ' . $page['ctaContext']));
    check("$id hero image preloaded", str_contains($html, '<link rel="preload" as="image" href="' . $page['heroImage'] . '">'));
}

echo "\nProject page\n";
foreach (['joyville-sector-102-gurgaon', 'abhiyan-apartments-sector-12-dwarka', 'm3m-woodhsire-sector-107-gurgaon'] as $id) {
    $page = page_load($id);
    $r = get($page['urlPath']);
    $html = $r['body'];
    $static = static_reference($page['urlPath'] . 'index.html');
    check("$id renders 200", $r['status'] === 200);
    check("$id SEO tags match static", seo_tags($html) === seo_tags($static), json_encode([seo_tags($static), seo_tags($html)]));
    check("$id schema types match static", page_ld_types($html) === page_ld_types($static), implode(',', page_ld_types($html)) . ' vs ' . implode(',', page_ld_types($static)));
    $from = '<div class="container-fluid pb-5 hero-header';
    $to = '<div class="container-fluid bg-primary newsletter';
    $a = main_text($static, $from, $to);
    $b = main_text($html, $from, $to);
    check("$id visible text identical to static", $a === $b && $a !== '', first_diff($a, $b));
    check("$id links identical to static", main_links($static, $from, $to) === main_links($html, $from, $to));
    check("$id gallery + lightbox assets", str_contains($html, 'data-lightbox-gallery') && str_contains($html, '/js/lightbox.js') && str_contains($html, '/css/lightbox.css'));
    check("$id nav marks Projects active", str_contains($html, 'href="/project.html" class="nav-item nav-link active">Projects</a>'));
}

echo "\nBlock pages\n";
page_save([
    'id' => 'home', 'type' => 'home', 'status' => 'published', 'urlPath' => '/index.html', 'aliases' => ['/'],
    'seo' => ['title' => 'Home | The Omega Group'],
    'blocks' => [
        ['type' => 'html', 'html' => '<div class="hero-video"><h1>Luxury Interiors</h1></div>'],
        ['type' => 'project-cards', 'style' => 'carousel', 'headingHtml' => 'Our Latest <span>Projects</span>', 'intro' => 'A glimpse.', 'buttonLabel' => 'View All Projects', 'buttonHref' => '/project.html'],
        ['type' => 'faq', 'headingHtml' => 'FAQ <span>Questions</span>', 'items' => [['question' => 'Q1?', 'answer' => 'A & 1.']]],
        ['type' => 'latest-posts', 'count' => 4, 'intro' => 'Our latest guides.'],
    ],
]);
$html = get('/')['body'];
check('home renders blocks in order', strpos($html, 'hero-video') < strpos($html, 'project-carousel') && strpos($html, 'project-carousel') < strpos($html, 'From The Blog Start'));
check('home has no title band (no breadcrumb schema)', !in_array('BreadcrumbList', ld_types($html), true));
check('carousel lists every project in sortOrder', substr_count($html, 'class="project-card text-decoration-none"') === count(projects_published()) && strpos($html, '/project/abhiyan-apartments-sector-12-dwarka/') < strpos($html, '/project/smart-world-orchard-sector-61-gurgaon/'));
check('carousel loads Owl Carousel assets', str_contains($html, 'owl.carousel.min.css') && str_contains($html, 'owl.carousel.min.js'));
check('latest-posts shows the 4 newest', substr_count(main_text($html, 'From The Blog Start', 'From The Blog End'), 'Read Article') === 4 && str_contains($html, posts_published()[0]['urlPath']));
check('FAQ block feeds FAQPage schema', in_array('FAQPage', ld_types($html), true));
check('FAQ answers are escaped', str_contains($html, 'A &amp; 1.'));
$home = page_load('home');
$home['blocks'][] = ['type' => 'not-a-real-block'];
json_write("$tmp/pages/home.json", $home);
cache_clear_pages();
$prevLog = ini_set('error_log', '/dev/null');
check('unknown block type skipped safely', get('/')['status'] === 200);
ini_set('error_log', (string) $prevLog);
array_pop($home['blocks']);
page_save($home);
check('nav marks Home active', str_contains($html, 'href="/index.html" class="nav-item nav-link active">Home</a>'));

page_save([
    'id' => 'blog', 'type' => 'blog-index', 'status' => 'published', 'urlPath' => '/blog.html',
    'seo' => ['title' => 'Interior Design Blog | The Omega Group'], 'h1' => 'Our Blog',
    'blocks' => [['type' => 'hero'], ['type' => 'blog-listing']],
]);
$html = get('/blog.html')['body'];
$newest = posts_published()[0];
check('blog: pinned card is the newest post', str_contains($html, 'class="blog-pinned-card"') && strpos($html, $newest['urlPath']) < strpos($html, 'blog-filter-bar'));
check('blog: first page of cards rendered on the server', substr_count($html, 'class="blog-card"') === min(9, count(posts_published())));
check('blog: blog.js reads the live feed', str_contains($html, 'window.OMEGA_BLOG_DATA_URL = "/api/posts.json"') && str_contains($html, '/js/blog.js'));
check('blog: CollectionPage schema', in_array('CollectionPage', ld_types($html), true));

$feed = json_decode(get('/api/posts.json')['body'], true);
check('posts feed: newest first, blog.js fields', is_array($feed) && $feed[0]['slug'] === $newest['id'] && isset($feed[0]['tags'], $feed[0]['featuredImage']['src'], $feed[0]['urlPath']));
check('posts feed: JSON content type', str_starts_with(get('/api/posts.json')['headers']['Content-Type'] ?? '', 'application/json'));

$draft = page_load('luxury-tv-unit-design-ideas');
$draft['status'] = 'draft';
page_save($draft);
$feedIds = array_column(json_decode(get('/api/posts.json')['body'], true), 'slug');
check('draft posts hidden from feed', !in_array('luxury-tv-unit-design-ideas', $feedIds, true));
check('draft posts hidden from blog listing', !str_contains(get('/blog.html')['body'], $draft['urlPath']));

page_save([
    'id' => 'projects', 'type' => 'project-index', 'status' => 'published', 'urlPath' => '/project.html',
    'seo' => ['title' => 'Projects | The Omega Group'], 'h1' => 'Our Projects',
    'blocks' => [['type' => 'hero'], ['type' => 'project-cards', 'style' => 'grid', 'intro' => 'Every project.', 'buttonLabel' => 'Start Your Project']],
]);
$html = get('/project.html')['body'];
check('project grid lists every project', substr_count($html, 'data-project-slug=') === count(projects_published()));
$collection = array_values(array_filter(ld_blocks($html), fn ($b) => ($b['@type'] ?? '') === 'CollectionPage'))[0] ?? [];
check('project index ItemList matches the grid', count($collection['mainEntity']['itemListElement'] ?? []) === count(projects_published()));

page_save([
    'id' => 'landing', 'type' => 'landing', 'status' => 'published', 'urlPath' => '/landing-test/', 'layout' => 'bare',
    'seo' => ['title' => 'Free Consultation', 'noindex' => true],
    'assets' => ['css' => ['/css/consultation-landing.css'], 'scripts' => ['/js/analytics.js', '/js/consultation-form.js'], 'animateCss' => false],
    'blocks' => [['type' => 'html', 'html' => '<header class="lp-header">LP</header>']],
]);
$html = get('/landing-test/')['body'];
check('landing: bare layout (no nav, footer or CTA)', !str_contains($html, 'Navbar Start') && !str_contains($html, 'Footer Start') && !str_contains($html, 'Final CTA Start'));
check('landing: page-specific script list only', str_contains($html, '/js/consultation-form.js') && !str_contains($html, 'jquery'));
check('landing: noindex', str_contains($html, '<meta name="robots" content="noindex, follow">'));
check('landing: tracking still present', str_contains($html, 'G-FSNHC0N7BD') && str_contains($html, '857790543995038'));
check('landing: animate.css skipped', !str_contains($html, 'animate.min.css'));

page_save([
    'id' => 'not-found', 'type' => 'not-found', 'status' => 'published', 'urlPath' => '/404.html',
    'seo' => ['title' => 'Page Not Found | The Omega Group'], 'h1' => 'Page Not Found',
]);
$r = get('/no-such-page/');
check('404 uses the content-managed page', $r['status'] === 404 && str_contains($r['body'], 'Page Not Found | The Omega Group'));
check('404 page is noindex', str_contains($r['body'], '<meta name="robots" content="noindex, follow">'));

echo "\nWhole-site sanity\n";
$bad = [];
foreach (page_index() as $path => $id) {
    if ((page_load($id)['status'] ?? '') !== 'published') {
        continue;
    }
    $res = get($path);
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $res['status'] === 200 && $dom->loadHTML($res['body']) && $dom->getElementsByTagName('h1')->length <= 1;
    libxml_use_internal_errors($prev);
    if (!$ok || in_array(null, ld_blocks($res['body']), true)) {
        $bad[] = $path;
    }
}
check('every page renders, parses, has at most one h1 and valid JSON-LD', !$bad, implode(', ', $bad));
