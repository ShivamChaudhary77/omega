<?php
/**
 * Turns a page array into a complete HTML document.
 *
 * Every page = templates/layout.php wrapped around a type-specific body
 * template (templates/<type>.php). Shared pieces (head, nav, footer, CTA,
 * FAQ accordion, breadcrumb hero) live once in templates/partials/.
 * Templates are plain PHP; always print values with e() unless the value is
 * HTML that was sanitised when it was saved (marked "html" in page data).
 */

declare(strict_types=1);

/** Page type -> body template. Types without a template yet fall back to "page". */
const TYPE_TEMPLATES = [
    'page' => 'page',
];

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

/**
 * Everything the <head> partial needs, with sensible fallbacks so a page
 * with only a title still gets complete SEO tags.
 */
function page_head(array $page, array $site): array
{
    $seo = (array) ($page['seo'] ?? []);
    $canonicalPath = $seo['canonical'] ?? $page['urlPath'];
    $canonical = absolute_url($canonicalPath === '/index.html' ? '/' : $canonicalPath);
    $title = (string) $seo['title'];
    $description = (string) ($seo['description'] ?? ($page['excerpt'] ?? ''));
    $ogImage = $seo['ogImage'] ?? ($page['featuredImage']['src'] ?? ($page['heroImage'] ?? $site['defaultOgImage'] ?? ''));

    $schema = [schema_local_business($site)];
    $crumbs = page_breadcrumb($page);
    if (count($crumbs) > 1) {
        $schema[] = schema_breadcrumb($crumbs, $canonical);
    }
    if (!empty($page['faqs'])) {
        $schema[] = schema_faq($page['faqs']);
    }

    return [
        'title' => $title,
        'description' => $description,
        'keywords' => $seo['keywords'] ?? '',
        'canonical' => $canonical,
        'robots' => !empty($seo['noindex']) ? 'noindex, follow' : 'index, follow',
        'ogType' => $page['type'] === 'blog-post' ? 'article' : 'website',
        'ogImage' => $ogImage ? absolute_url($ogImage) : '',
        'extraCss' => (array) ($page['assets']['css'] ?? []),
        'extraJs' => (array) ($page['assets']['js'] ?? []),
        'owlCarousel' => !empty($page['assets']['owlCarousel']),
        'schema' => $schema,
    ];
}

/**
 * Breadcrumb trail as [[name, path|null], ...]. Uses the page's own
 * "breadcrumb" if set, otherwise Home > page heading.
 */
function page_breadcrumb(array $page): array
{
    if (!empty($page['breadcrumb']) && is_array($page['breadcrumb'])) {
        return array_map(fn ($c) => [(string) ($c['name'] ?? ''), $c['path'] ?? null], $page['breadcrumb']);
    }
    if (in_array($page['type'], ['home', 'landing'], true)) {
        return [];
    }
    return [['Home', '/index.html'], [(string) ($page['h1'] ?? $page['seo']['title'] ?? ''), null]];
}

/** Is a nav item the current section? Pages may set "navActive" to a nav item's key. */
function nav_is_active(array $item, array $page): bool
{
    if (!empty($page['navActive'])) {
        return ($item['key'] ?? null) === $page['navActive'];
    }
    return ($item['href'] ?? null) === ($page['urlPath'] ?? null);
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
            'item' => $path !== null ? absolute_url($path === '/index.html' ? '/' : $path) : $canonical,
        ];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function schema_faq(array $faqs): ?array
{
    $entities = [];
    foreach ($faqs as $faq) {
        $q = trim((string) ($faq['question'] ?? ''));
        $a = trim(strip_tags((string) ($faq['answer'] ?? '')));
        if ($q !== '' && $a !== '') {
            $entities[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
    }
    return $entities ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities] : null;
}
