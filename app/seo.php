<?php
/**
 * SEO automation (Phase 5):
 *   - live sitemaps (/sitemap.xml for pages, /sitemap-blog.xml for posts)
 *   - live /llms.txt (brand fact sheet for AI assistants) from site.json
 *   - link checking and the dashboard's SEO checks
 */

declare(strict_types=1);

const SITEMAP_DEFAULTS = [
    'home' => ['weekly', '1.0'],
    'blog-index' => ['weekly', '0.8'],
    'project-index' => ['monthly', '0.8'],
    'service' => ['monthly', '0.9'],
    'location' => ['monthly', '0.8'],
    'project' => ['yearly', '0.7'],
    'blog-post' => ['monthly', '0.6'],
    'contact' => ['yearly', '0.6'],
];

/** Pages that belong in a sitemap: published, indexable, not the 404 page. */
function sitemap_pages(): array
{
    $out = [];
    foreach (pages_all() as $page) {
        if (($page['status'] ?? '') !== 'published' || !empty($page['seo']['noindex']) || $page['type'] === 'not-found') {
            continue;
        }
        $out[] = $page;
    }
    return $out;
}

/** ISO date (Y-m-d) a page last changed. */
function sitemap_lastmod(array $page): string
{
    $candidates = [];
    if ($page['type'] === 'blog-post') {
        $candidates[] = $page['modifiedDate'] ?? $page['publishDate'] ?? null;
    } else {
        $candidates[] = $page['sitemap']['lastmod'] ?? null;
    }
    $candidates[] = $page['updatedAt'] ?? null;
    $dates = array_filter(array_map(fn ($d) => $d ? substr((string) $d, 0, 10) : null, $candidates));
    return $dates ? max($dates) : date('Y-m-d');
}

/**
 * Sitemap XML. $kind "blog" = blog posts only (newest-added last, like the
 * old generated file); "pages" = everything else.
 */
function sitemap_xml(string $kind): string
{
    $pages = array_values(array_filter(sitemap_pages(), fn ($p) => ($p['type'] === 'blog-post') === ($kind === 'blog')));
    if ($kind === 'blog') {
        usort($pages, fn ($a, $b) => ((int) ($a['seq'] ?? PHP_INT_MAX) <=> (int) ($b['seq'] ?? PHP_INT_MAX)) ?: strcmp((string) ($a['publishDate'] ?? ''), (string) ($b['publishDate'] ?? '')));
    } else {
        usort($pages, fn ($a, $b) => ((int) ($a['sitemap']['order'] ?? 9999) <=> (int) ($b['sitemap']['order'] ?? 9999)) ?: strcmp($a['urlPath'], $b['urlPath']));
    }
    $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
    foreach ($pages as $page) {
        [$freq, $priority] = SITEMAP_DEFAULTS[$page['type']] ?? ['monthly', '0.6'];
        $freq = $page['sitemap']['changefreq'] ?? $freq;
        $priority = $page['sitemap']['priority'] ?? $priority;
        $loc = htmlspecialchars(absolute_url($page['urlPath']), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lines[] = "  <url><loc>$loc</loc><lastmod>" . sitemap_lastmod($page) . "</lastmod><changefreq>$freq</changefreq><priority>$priority</priority></url>";
    }
    $lines[] = '</urlset>';
    return implode("\n", $lines) . "\n";
}

/** /llms.txt built from site.json, so business details stay in one place. */
function llms_txt(): string
{
    $site = site();
    $b = (array) ($site['business'] ?? []);
    $llms = (array) ($site['llms'] ?? []);
    $stats = (array) ($site['stats'] ?? []);
    $addr = (array) ($b['address'] ?? []);
    $founded = '';
    if (!empty($b['foundingDate'])) {
        $d = DateTimeImmutable::createFromFormat('!Y-m', substr((string) $b['foundingDate'], 0, 7));
        $founded = $d ? $d->format('F Y') : (string) $b['foundingDate'];
    }
    $out = [];
    $out[] = '# ' . ($b['name'] ?? '');
    $out[] = '';
    if (!empty($llms['summary'])) {
        $out[] = '> ' . $llms['summary'];
        $out[] = '';
    }
    if (!empty($llms['whatWeDo'])) {
        $out[] = '## What we do';
        $out[] = '';
        $out[] = $llms['whatWeDo'];
        $out[] = '';
    }
    $facts = array_filter([
        $founded ? "Founded: $founded" : null,
        !empty($stats['yearsExperience']) ? "Experience: {$stats['yearsExperience']} years (combined team experience)" : null,
        !empty($stats['projectsDelivered']) ? "Projects delivered: {$stats['projectsDelivered']}" : null,
        !empty($stats['onTimeDeliveryRate']) ? "On-time delivery rate: {$stats['onTimeDeliveryRate']}" : null,
        !empty($stats['qcChecksPerProject']) ? "Quality control checks per project: {$stats['qcChecksPerProject']}" : null,
        !empty($stats['customFurnitureDesigns']) ? "Custom furniture designs completed: {$stats['customFurnitureDesigns']}" : null,
        !empty($site['startingPrice']['display']) ? 'Starting price, full-home turnkey interior design: ' . $site['startingPrice']['display'] : null,
        !empty($b['priceRange']) ? "Price range: {$b['priceRange']} (premium/luxury)" : null,
    ]);
    if ($facts) {
        $out[] = '## Facts';
        $out[] = '';
        foreach ($facts as $fact) {
            $out[] = "- $fact";
        }
        $out[] = '';
    }
    $out[] = '## Location & service area';
    $out[] = '';
    if (!empty($llms['locationText'])) {
        $out[] = $llms['locationText'];
        $out[] = '';
    }
    $address = implode(', ', array_filter([$addr['streetAddress'] ?? null, $addr['addressLocality'] ?? null,
        trim(($addr['addressRegion'] ?? '') . ' ' . ($addr['postalCode'] ?? '')), ($addr['addressCountry'] ?? '') === 'IN' ? 'India' : ($addr['addressCountry'] ?? null)]));
    $out[] = "Address: $address";
    $out[] = 'Phone: ' . ($site['phoneDisplay'] ?? ($b['telephone'] ?? ''));
    if (!empty($b['email'])) {
        $out[] = 'Email: ' . $b['email'];
    }
    $out[] = '';
    if (!empty($llms['keyPages'])) {
        $out[] = '## Key pages';
        $out[] = '';
        foreach ($llms['keyPages'] as $link) {
            $out[] = '- ' . ($link['label'] ?? '') . ': ' . absolute_url((string) ($link['href'] ?? '/'));
        }
        $out[] = '';
    }
    if (!empty($llms['differentiators'])) {
        $out[] = '## Differentiators';
        $out[] = '';
        foreach ($llms['differentiators'] as $item) {
            $out[] = "- $item";
        }
        $out[] = '';
    }
    $out[] = '## Sitemap';
    $out[] = '';
    $out[] = absolute_url('/sitemap.xml');
    return implode("\n", $out) . "\n";
}

/* ------------------------------------------------------------------ */
/* Link checking                                                       */
/* ------------------------------------------------------------------ */

/** Source paths of the historic redirects kept in .htaccess (redirects.json). */
function static_redirect_sources(): array
{
    static $sources = null;
    if ($sources === null) {
        $data = json_read(OMEGA_ROOT . '/redirects.json') ?? [];
        $sources = array_map(fn ($r) => (string) ($r['from'] ?? ''), (array) ($data['redirects'] ?? []));
    }
    return $sources;
}

/** Does an internal link go somewhere real? (page, redirect, or real file) */
function internal_link_ok(string $href): bool
{
    $path = (string) parse_url($href, PHP_URL_PATH);
    if ($path === '' || $path === '/') {
        return true;
    }
    if (isset(page_index()[$path]) || redirect_find($path) || in_array($path, static_redirect_sources(), true)
        || in_array(rtrim($path, '/') . '/', static_redirect_sources(), true)) {
        return true;
    }
    if (in_array($path, ['/sitemap.xml', '/sitemap-blog.xml', '/llms.txt', '/api/posts.json'], true)) {
        return true;
    }
    $file = OMEGA_ROOT . $path;
    if (str_contains($path, '..')) {
        return false;
    }
    if (str_starts_with($path, '/uploads/')) {
        require_once OMEGA_APP . '/media.php';
        return is_file(uploads_dir() . substr($path, 8));
    }
    return is_file($file) || (is_dir($file) && is_file(rtrim($file, '/') . '/index.html'));
}

/** Every internal (root-relative) href and image src used in a page's own content. */
function page_internal_links(array $page): array
{
    $html = (string) ($page['contentHtml'] ?? '') . ($page['intro'] ?? '');
    foreach ((array) ($page['sections'] ?? []) as $s) {
        $html .= $s['html'] ?? '';
    }
    foreach ((array) ($page['blocks'] ?? []) as $b) {
        $html .= ($b['html'] ?? '');
        foreach ((array) ($b['items'] ?? []) as $item) {
            $html .= $item['answerHtml'] ?? '';
        }
    }
    foreach ((array) ($page['faqs'] ?? []) as $item) {
        $html .= $item['answerHtml'] ?? '';
    }
    preg_match_all('#\s(?:href|src)="(/[^"/][^"]*|/)"#', $html, $m);
    $links = $m[1];
    foreach (['internalLinks', 'relatedBlogPosts'] as $key) {
        foreach ((array) ($page[$key] ?? []) as $l) {
            $links[] = (string) ($l['href'] ?? '');
        }
    }
    foreach (['featuredImage.src', 'heroImage', 'image', 'seo.ogImage'] as $key) {
        $v = $key === 'featuredImage.src' ? ($page['featuredImage']['src'] ?? null) : ($key === 'seo.ogImage' ? ($page['seo']['ogImage'] ?? null) : ($page[$key] ?? null));
        if (is_string($v) && str_starts_with($v, '/')) {
            $links[] = $v;
        }
    }
    foreach ((array) ($page['gallery'] ?? []) as $g) {
        $links[] = (string) $g;
    }
    return array_values(array_unique(array_filter($links, fn ($l) => str_starts_with($l, '/') && !str_starts_with($l, '//'))));
}

/* ------------------------------------------------------------------ */
/* SEO checks for the dashboard                                        */
/* ------------------------------------------------------------------ */

/**
 * Checks shown in the editor's SEO panel:
 * [['level' => 'good'|'warn'|'info', 'text' => ...], ...] (warnings first).
 */
function seo_checks(array $page): array
{
    $checks = [];
    $add = function (string $level, string $text) use (&$checks) {
        $checks[] = ['level' => $level, 'text' => $text];
    };
    $id = (string) ($page['id'] ?? '');
    $seo = (array) ($page['seo'] ?? []);
    $title = trim((string) ($seo['title'] ?? ''));
    $desc = trim((string) ($seo['description'] ?? ''));
    $tLen = mb_strlen($title);
    $dLen = mb_strlen($desc);

    if (($page['status'] ?? '') !== 'published') {
        $add('info', 'This page is a draft: Google and visitors can\'t see it yet.');
    }
    if (!empty($seo['noindex'])) {
        $add('info', 'This page is hidden from Google (noindex).');
    }

    if ($tLen === 0) {
        $add('warn', 'Add an SEO title.');
    } elseif ($tLen > 60) {
        $add('warn', "The SEO title is $tLen characters. Google usually cuts it off after about 60.");
    } elseif ($tLen < 30) {
        $add('warn', "The SEO title is short ($tLen characters). 50–60 characters with the main search phrase works best.");
    } else {
        $add('good', "SEO title length is good ($tLen characters).");
    }
    if ($dLen === 0) {
        $add('warn', 'Add a meta description: Google may otherwise pick random text from the page.');
    } elseif ($dLen > 160) {
        $add('warn', "The meta description is $dLen characters. Google usually cuts it off after about 160.");
    } elseif ($dLen < 70) {
        $add('warn', "The meta description is short ($dLen characters). Aim for 120–160.");
    } else {
        $add('good', "Meta description length is good ($dLen characters).");
    }

    $others = array_filter(pages_all(), fn ($p) => $p['id'] !== $id && ($p['status'] ?? '') === 'published');
    $sameTitle = array_filter($others, fn ($p) => $title !== '' && mb_strtolower(trim((string) ($p['seo']['title'] ?? ''))) === mb_strtolower($title));
    if ($sameTitle) {
        $add('warn', 'Another page has the same SEO title: ' . page_label(reset($sameTitle)) . '. Each page needs its own title.');
    }
    $sameDesc = array_filter($others, fn ($p) => $desc !== '' && trim((string) ($p['seo']['description'] ?? '')) === $desc);
    if ($sameDesc) {
        $add('warn', 'Another page has the same meta description: ' . page_label(reset($sameDesc)) . '.');
    }

    if (($page['type'] ?? '') === 'blog-post') {
        $kw = mb_strtolower(trim((string) ($seo['focusKeyword'] ?? '')));
        if ($kw === '') {
            $add('info', 'Add a focus keyword (the main search phrase this post should rank for).');
        } else {
            $sameKw = array_filter($others, fn ($p) => ($p['type'] ?? '') === 'blog-post' && mb_strtolower(trim((string) ($p['seo']['focusKeyword'] ?? ''))) === $kw);
            if ($sameKw) {
                $add('warn', 'Another post already targets "' . $seo['focusKeyword'] . '": ' . page_label(reset($sameKw)) . '. Two pages competing for one phrase usually both rank worse.');
            }
            if (!str_contains(mb_strtolower($title), $kw)) {
                $add('info', 'The focus keyword is not in the SEO title.');
            }
            $firstPara = mb_strtolower(strip_tags((string) (preg_match('#<p[^>]*>(.*?)</p>#s', (string) ($page['contentHtml'] ?? ''), $m) ? $m[1] : '')));
            if (!str_contains($firstPara, $kw)) {
                $add('info', 'The focus keyword is not in the first paragraph.');
            }
        }
        if (empty($page['featuredImage']['alt'])) {
            $add('warn', 'Describe the featured image (alt text).');
        }
        $links = count(array_filter(page_internal_links($page), fn ($l) => !preg_match('#\.(webp|jpe?g|png|svg)$#i', $l)));
        if ($links < 2) {
            $add('info', 'Add 2–4 links to related posts or pages: it helps Google and readers.');
        }
        if (empty($page['faqs'])) {
            $add('info', 'An FAQ section (3–6 short questions) helps the post appear in Google\'s "People also ask" and AI answers.');
        }
    }

    if (in_array($page['type'] ?? '', ['location', 'page'], true) && empty($page['h1'])) {
        $add('warn', 'Add a page heading (H1).');
    }

    $html = (string) ($page['contentHtml'] ?? '');
    foreach ((array) ($page['sections'] ?? []) as $s) {
        $html .= $s['html'] ?? '';
    }
    $noAlt = preg_match_all('#<img\b(?![^>]*\salt="[^"]+")[^>]*>#i', $html);
    if ($noAlt) {
        $add('warn', "$noAlt image(s) in the text have no description (alt text).");
    }

    $broken = array_values(array_filter(page_internal_links($page), fn ($l) => !internal_link_ok($l)));
    if ($broken) {
        $add('warn', 'Broken link(s) or image(s): ' . implode(', ', array_slice($broken, 0, 5)) . (count($broken) > 5 ? ' and ' . (count($broken) - 5) . ' more' : '') . '.');
    } else {
        $add('good', 'All internal links and images work.');
    }

    usort($checks, fn ($a, $b) => ['warn' => 0, 'info' => 1, 'good' => 2][$a['level']] <=> ['warn' => 0, 'info' => 1, 'good' => 2][$b['level']]);
    return $checks;
}

/** "Title" (/url/) for messages. */
function page_label(array $page): string
{
    $t = (string) ($page['title'] ?? $page['name'] ?? $page['h1'] ?? $page['seo']['title'] ?? $page['id']);
    return '“' . $t . '” (' . ($page['urlPath'] ?? '') . ')';
}

/** Where visitors of a deleted page should go by default. */
function suggested_redirect_target(array $page): string
{
    switch ($page['type'] ?? '') {
        case 'blog-post':
            return '/blog.html';
        case 'project':
            return '/project.html';
        case 'location':
            return '/interior-designers-gurgaon/';
        default:
            return '/index.html';
    }
}
