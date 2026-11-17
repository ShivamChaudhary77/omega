<?php
/**
 * Turns a page array into a complete HTML document.
 *
 * Every page = templates/layout.php wrapped around a type-specific body
 * template. Shared pieces live once in templates/partials/; reusable page
 * sections ("blocks") live in templates/blocks/.
 *
 * Escaping convention for page data: a field named "html" or ending in
 * "Html" (contentHtml, headingHtml, intro html...) is trusted HTML that the
 * dashboard sanitises when it is saved, and is printed as-is. Every other
 * field is plain text and must be printed with e().
 */

declare(strict_types=1);

/** Page type -> body template (templates/<name>.php). */
const TYPE_TEMPLATES = [
    'blog-post' => 'blog-post',
    'location' => 'location',
    'project' => 'project',
];

/** Stylesheets / scripts each page type needs on top of the site defaults. */
const TYPE_ASSETS = [
    'blog-post' => ['css' => ['/css/blog.css']],
    'location' => ['css' => ['/css/blog.css']],
    'project' => ['css' => ['/css/blog.css', '/css/lightbox.css'], 'js' => ['/js/lightbox.js']],
    'blog-index' => ['css' => ['/css/blog.css'], 'js' => ['/js/blog.js']],
    'author' => ['css' => ['/css/blog.css']],
    'faqs' => ['css' => ['/css/blog.css']],
];

/** Block types a page's "blocks" list may use (templates/blocks/<type>.php). */
const BLOCK_TYPES = ['html', 'hero', 'rich-text', 'faq', 'latest-posts', 'project-cards', 'blog-listing'];

/** Render templates/<name>.php with the given variables and return the HTML. */
function render_template(string $name, array $vars = []): string
{
    if (!preg_match('#^[a-z0-9][a-z0-9\-/]*$#', $name) || str_contains($name, '..')) {
        throw new InvalidArgumentException("Invalid template name: $name");
    }
    $file = OMEGA_TEMPLATES . "/$name.php";
    if (!is_file($file)) {
        throw new RuntimeException("Template not found: $name");
    }
    return (static function (string $__file, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    })($file, $vars);
}

/** Shortcut for templates: echo a partial. */
function partial(string $name, array $vars = []): void
{
    echo render_template("partials/$name", $vars);
}

function template_for_type(string $type): string
{
    return TYPE_TEMPLATES[$type] ?? 'page';
}

/** Full HTML document for a page. */
function render_page(array $page): string
{
    $site = site();
    $body = render_template(template_for_type((string) $page['type']), ['page' => $page, 'site' => $site]);
    return render_template('layout', [
        'site' => $site,
        'page' => $page,
        'head' => page_head($page, $site),
        'content' => $body,
    ]);
}

/** Render a page's "blocks" list. Unknown block types are skipped (and logged). */
function render_blocks(array $page, array $site): string
{
    $out = '';
    foreach ((array) ($page['blocks'] ?? []) as $i => $block) {
        $type = (string) ($block['type'] ?? '');
        if (!in_array($type, BLOCK_TYPES, true)) {
            error_log("[omega] page {$page['id']}: unknown block type \"$type\" skipped");
            continue;
        }
        $out .= render_template("blocks/$type", ['block' => $block, 'page' => $page, 'site' => $site, 'index' => $i]);
    }
    return $out;
}

/**
 * Everything the <head> partial needs, with sensible fallbacks so a page
 * with only a title still gets complete SEO tags.
 */
function page_head(array $page, array $site): array
{
    $type = (string) $page['type'];
    $seo = (array) ($page['seo'] ?? []);
    $canonicalPath = $seo['canonical'] ?? $page['urlPath'];
    $canonical = absolute_url($canonicalPath === '/index.html' ? '/' : $canonicalPath);
    $title = (string) $seo['title'];
    $description = (string) ($seo['description'] ?? ($page['excerpt'] ?? ''));
    $ogImage = $seo['ogImage']
        ?? ($page['featuredImage']['src'] ?? ($page['heroImage'] ?? ($page['image'] ?? ($site['defaultOgImage'] ?? ''))));

    $typeAssets = TYPE_ASSETS[$type] ?? [];
    $assets = (array) ($page['assets'] ?? []);

    return [
        'title' => $title,
        'description' => $description,
        'keywords' => (string) ($seo['keywords'] ?? ''),
        'canonical' => $canonical,
        'robots' => (!empty($seo['noindex']) || $type === 'not-found') ? 'noindex, follow' : 'index, follow',
        'ogType' => $type === 'blog-post' ? 'article' : 'website',
        'ogImage' => $ogImage ? absolute_url($ogImage) : '',
        'preload' => (string) ($seo['preloadImage'] ?? ($page['heroImage'] ?? ($type === 'project' ? ($page['image'] ?? '') : ''))),
        'extraCss' => array_values(array_unique(array_merge($typeAssets['css'] ?? [], (array) ($assets['css'] ?? [])))),
        'extraJs' => array_values(array_unique(array_merge($typeAssets['js'] ?? [], (array) ($assets['js'] ?? [])))),
        'scripts' => isset($assets['scripts']) ? (array) $assets['scripts'] : null,
        'animateCss' => ($assets['animateCss'] ?? true) !== false,
        'owlCarousel' => !empty($assets['owlCarousel']) || page_has_block($page, 'project-cards', ['style' => 'carousel']),
        'headHtml' => (string) ($page['headHtml'] ?? ''),
        'schema' => array_values(array_filter(page_schema($page, $site, $canonical))),
    ];
}

/** True if the page has a block of this type (optionally matching some fields). */
function page_has_block(array $page, string $type, array $match = []): bool
{
    foreach ((array) ($page['blocks'] ?? []) as $block) {
        if (($block['type'] ?? '') !== $type) {
            continue;
        }
        $ok = true;
        foreach ($match as $key => $value) {
            if (($block[$key] ?? null) !== $value) {
                $ok = false;
            }
        }
        if ($ok) {
            return true;
        }
    }
    return false;
}

/** JSON-LD blocks for a page, by type. */
function page_schema(array $page, array $site, string $canonical): array
{
    $type = (string) $page['type'];
    $schema = [schema_local_business($site)];

    if ($type === 'blog-post') {
        $schema[] = schema_blog_posting($page, $site, $canonical);
        $schema[] = schema_breadcrumb([['Home', '/index.html'], ['Blog', '/blog.html'], [(string) $page['title'], null]], $canonical);
    } else {
        $crumbs = page_breadcrumb($page);
        if (count($crumbs) > 1) {
            $schema[] = schema_breadcrumb($crumbs, $canonical);
        }
    }
    if ($type === 'project') {
        $schema[] = schema_creative_work($page, $canonical);
    }
    if ($type === 'project-index') {
        $schema[] = schema_collection($page, $canonical, array_map(fn ($p) => ['url' => $p['urlPath'], 'name' => $p['name'] ?? $p['seoTitle']], projects_published()));
    }
    if ($type === 'blog-index') {
        $schema[] = schema_collection($page, $canonical, array_map(fn ($p) => ['url' => $p['urlPath'], 'name' => $p['title'] ?? $p['seoTitle']], array_slice(posts_published(), 0, 30)));
    }
    $schema[] = schema_faq(page_faqs($page));
    return $schema;
}

/** Every FAQ on the page: the page-level "faqs" plus any FAQ blocks. */
function page_faqs(array $page): array
{
    $faqs = (array) ($page['faqs'] ?? []);
    foreach ((array) ($page['blocks'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'faq') {
            $faqs = array_merge($faqs, (array) ($block['items'] ?? []));
        }
    }
    return $faqs;
}

/**
 * Breadcrumb trail as [[name, path|null], ...]. Uses the page's own
 * "breadcrumb" if set, otherwise a sensible default for the page type.
 */
function page_breadcrumb(array $page): array
{
    if (!empty($page['breadcrumb']) && is_array($page['breadcrumb'])) {
        return array_map(fn ($c) => [(string) ($c['name'] ?? ''), $c['path'] ?? null], $page['breadcrumb']);
    }
    switch ($page['type']) {
        case 'home':
        case 'landing':
            return [];
        case 'blog-post':
            return [['Home', '/index.html'], ['Blog', '/blog.html'], [(string) ($page['category'] ?? ''), null]];
        case 'project':
            return [['Home', '/index.html'], ['Projects', '/project.html'], [(string) ($page['name'] ?? ''), null]];
        default:
            return [['Home', '/index.html'], [(string) ($page['h1'] ?? $page['seo']['title'] ?? ''), null]];
    }
}

/** Is a nav item the current section? Pages may set "navActive" to a nav item's key. */
function nav_is_active(array $item, array $page): bool
{
    $active = $page['navActive'] ?? ([
        'blog-post' => 'blog', 'blog-index' => 'blog', 'project' => 'projects', 'project-index' => 'projects',
        'home' => 'home', 'contact' => 'contact', 'service' => 'services', 'about' => 'about',
    ][$page['type']] ?? null);
    if ($active !== null) {
        return ($item['key'] ?? null) === $active;
    }
    return ($item['href'] ?? null) === ($page['urlPath'] ?? null);
}

/** CTA settings for a page: site default, then the page type's text, then the page's own overrides. */
function page_cta(array $page, array $site): array
{
    $cta = (array) ($site['cta'] ?? []);
    $typeText = $site['ctaTextByType'][$page['type']] ?? null;
    if ($typeText) {
        $cta['text'] = str_replace('{ctaContext}', (string) ($page['ctaContext'] ?? 'project'), $typeText);
    }
    return array_merge($cta, is_array($page['cta'] ?? null) ? $page['cta'] : []);
}

/* ------------------------------------------------------------------ */
/* Content helpers used by templates                                   */
/* ------------------------------------------------------------------ */

/** "2026-11-17T12:00:00" -> "17 Nov 2026" (same format as the static site). */
function format_date(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    try {
        return (new DateTimeImmutable($iso))->format('j M Y');
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Give every <h2>/<h3> in an HTML fragment a unique id (slug of its text)
 * and return [html, toc] where toc lists the h2s as [['id' => , 'text' => ]].
 * Mirrors what js/single-post.js used to do in the browser.
 */
function html_heading_ids(string $html): array
{
    $used = [];
    $toc = [];
    $html = preg_replace_callback('#<(h[23])((?:\s[^>]*)?)>(.*?)</\1>#is', function ($m) use (&$used, &$toc) {
        [$all, $tag, $attrs, $inner] = $m;
        $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/\sid\s*=\s*"([^"]*)"/i', $attrs, $idm)) {
            $id = $idm[1];
        } else {
            $base = slugify_like_js($text);
            $id = $base;
            $n = 1;
            while ($id === '' || isset($used[$id])) {
                $id = $base . '-' . $n++;
            }
            $attrs .= ' id="' . e($id) . '"';
        }
        $used[$id] = true;
        if (strtolower($tag) === 'h2') {
            $toc[] = ['id' => $id, 'text' => $text];
        }
        return "<$tag$attrs>$inner</$tag>";
    }, $html) ?? $html;
    return [$html, $toc];
}

/** Same slug rule as the site's JavaScript (lowercase, non-alphanumerics -> "-"). */
function slugify_like_js(string $text): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text)) ?? '', '-');
}

/** Named-author details from site.json "authors", or null. */
function author_info(?string $name, array $site): ?array
{
    return $name !== null ? ($site['authors'][$name] ?? null) : null;
}

/** Related posts: same category first, then the newest others, 3 in total. */
function related_posts(array $post, int $count = 3): array
{
    $others = array_values(array_filter(posts_published(), fn ($p) => $p['id'] !== $post['id']));
    $same = array_values(array_filter($others, fn ($p) => ($p['category'] ?? '') === ($post['category'] ?? '')));
    $rest = array_values(array_filter($others, fn ($p) => ($p['category'] ?? '') !== ($post['category'] ?? '')));
    return array_slice(array_merge($same, $rest), 0, $count);
}

/** De-duplicated [{text, href}] links (first occurrence of each href wins). */
function unique_links(array $links): array
{
    $seen = [];
    $out = [];
    foreach ($links as $link) {
        $href = (string) ($link['href'] ?? '');
        if ($href === '' || isset($seen[$href])) {
            continue;
        }
        $seen[$href] = true;
        $out[] = ['text' => (string) ($link['text'] ?? $href), 'href' => $href];
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Schema.org builders                                                 */
/* ------------------------------------------------------------------ */

function schema_local_business(array $site): array
{
    $business = (array) ($site['business'] ?? []);
    $address = (array) ($business['address'] ?? []);
    $geo = (array) ($business['geo'] ?? []);
    return [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        '@id' => absolute_url('/') . '#organization',
        'name' => $business['name'] ?? null,
        'alternateName' => $business['alternateName'] ?? null,
        'url' => absolute_url('/'),
        'logo' => isset($business['logo']) ? absolute_url($business['logo']) : null,
        'image' => isset($business['image']) ? absolute_url($business['image']) : null,
        'telephone' => $business['telephone'] ?? null,
        'email' => $business['email'] ?? null,
        'priceRange' => $business['priceRange'] ?? null,
        'foundingDate' => $business['foundingDate'] ?? null,
        'sameAs' => $business['sameAs'] ?? null,
        'address' => $address ? array_merge(['@type' => 'PostalAddress'], $address) : null,
        'geo' => $geo ? ['@type' => 'GeoCoordinates', 'latitude' => $geo['latitude'] ?? null, 'longitude' => $geo['longitude'] ?? null] : null,
        'areaServed' => array_map(fn ($a) => ['@type' => 'AdministrativeArea', 'name' => $a], (array) ($business['areaServed'] ?? [])),
    ];
}

function schema_breadcrumb(array $crumbs, string $canonical): array
{
    $items = [];
    foreach ($crumbs as $i => [$name, $path]) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $name,
            'item' => $path !== null ? absolute_url($path) : $canonical,
        ];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function schema_faq(array $faqs): ?array
{
    $entities = [];
    foreach ($faqs as $faq) {
        $q = trim((string) ($faq['question'] ?? ''));
        $a = trim(html_entity_decode(strip_tags((string) ($faq['answer'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($q !== '' && $a !== '') {
            $entities[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
    }
    return $entities ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities] : null;
}

function schema_blog_posting(array $post, array $site, string $canonical): array
{
    $authorName = (string) ($post['author'] ?? '');
    $author = ['@type' => 'Person', 'name' => $authorName];
    $info = author_info($authorName, $site);
    if ($info) {
        $author['url'] = absolute_url((string) $info['url']);
        $author['sameAs'] = $info['sameAs'] ?? null;
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post['title'] ?? '',
        'description' => $post['seo']['description'] ?? '',
        'image' => isset($post['featuredImage']['src']) ? absolute_url($post['featuredImage']['src']) : null,
        'datePublished' => $post['publishDate'] ?? null,
        'dateModified' => $post['modifiedDate'] ?? ($post['publishDate'] ?? null),
        'author' => $author,
        'reviewedBy' => !empty($post['reviewedBy']) ? ['@type' => 'Organization', 'name' => $post['reviewedBy']] : null,
        'publisher' => [
            '@type' => 'Organization',
            'name' => $site['business']['name'] ?? '',
            'logo' => ['@type' => 'ImageObject', 'url' => absolute_url('/img/logo.png')],
        ],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
        'keywords' => implode(', ', array_filter(array_merge([$post['seo']['focusKeyword'] ?? ''], (array) ($post['tags'] ?? [])))),
    ];
}

function schema_creative_work(array $project, string $canonical): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWork',
        'name' => $project['name'] ?? '',
        'about' => $project['projectType'] ?? null,
        'url' => $canonical,
        'image' => isset($project['image']) ? absolute_url($project['image']) : null,
        'creator' => ['@type' => 'LocalBusiness', '@id' => absolute_url('/') . '#organization'],
        'locationCreated' => $project['location'] ?? null,
        'size' => !empty($project['sizeSqFt']) ? $project['sizeSqFt'] . ' sq. ft.' : null,
    ];
}

/** CollectionPage + ItemList for listing pages. $items: [['url' => path, 'name' => ...], ...] */
function schema_collection(array $page, string $canonical, array $items): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $page['seo']['title'] ?? '',
        'url' => $canonical,
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => absolute_url($item['url']),
                'name' => $item['name'],
            ], $items, array_keys($items)),
        ],
    ];
}
