<?php
/**
 * What the dashboard's edit form shows for each page type, and how a
 * submitted form becomes page JSON again.
 *
 * A field: ['key' => 'seo.title', 'label' => ..., 'type' => ..., 'help' => ...,
 *           'required' => bool, 'max' => int (character guide), 'options' => [...],
 *           'fields' => [...] (repeater rows)]
 * Types: text, textarea, richtext, number, datetime, select, checkbox, image,
 *        images (gallery), lines (one item per line), repeater, blocks, cta
 *
 * Fields not listed for a type are never touched by the form, so advanced
 * data (structured data, assets, aliases...) survives every save.
 */

declare(strict_types=1);

const CREATABLE_TYPES = [
    'blog-post' => 'Blog post',
    'location' => 'Location page',
    'project' => 'Project',
    'page' => 'Simple page',
];

const TYPE_LABELS = [
    'home' => 'Home page', 'page' => 'Simple page', 'about' => 'About page', 'blog-post' => 'Blog post',
    'location' => 'Location page', 'project' => 'Project', 'blog-index' => 'Blog page',
    'project-index' => 'Projects page', 'service' => 'Services page', 'contact' => 'Contact page',
    'faqs' => 'FAQ page', 'landing' => 'Landing page', 'author' => 'Author page', 'not-found' => '404 page',
];

const BLOCK_LABELS = [
    'html' => 'Designed section',
    'hero' => 'Title band',
    'rich-text' => 'Text section',
    'faq' => 'FAQ section',
    'latest-posts' => 'Latest blog posts',
    'project-cards' => 'Project cards',
    'blog-listing' => 'Blog listing',
];

function type_label(string $type): string
{
    return TYPE_LABELS[$type] ?? ucfirst($type);
}

/* ------------------------------------------------------------------ */
/* Field definitions                                                   */
/* ------------------------------------------------------------------ */

function f(string $key, string $label, string $type = 'text', array $extra = []): array
{
    return array_merge(['key' => $key, 'label' => $label, 'type' => $type], $extra);
}

function faq_fields(): array
{
    return [
        f('question', 'Question'),
        f('answer', 'Answer', 'textarea', ['rows' => 3]),
        f('answerHtml', 'Answer with links (optional, used instead of the plain answer)', 'richtext', ['small' => true]),
    ];
}

function link_fields(): array
{
    return [f('text', 'Link text'), f('href', 'Link URL', 'text', ['placeholder' => '/blog.html'])];
}

function section_fields(): array
{
    return [f('heading', 'Heading'), f('html', 'Text', 'richtext')];
}

/** Every editable field of a block type. */
function block_fields(string $type): array
{
    switch ($type) {
        case 'hero':
            return [f('h1', 'Heading (leave empty to use the page heading)'), f('variant', 'Layout', 'select', ['options' => ['' => 'Standard', 'wide' => 'Wide']])];
        case 'rich-text':
            return section_fields();
        case 'faq':
            return [f('headingHtml', 'Section heading (HTML allowed)'), f('items', 'Questions', 'repeater', ['fields' => faq_fields(), 'itemLabel' => 'Question'])];
        case 'latest-posts':
            return [f('headingHtml', 'Section heading (HTML allowed)'), f('intro', 'Intro text', 'textarea', ['rows' => 2]),
                f('count', 'How many posts', 'number'), f('buttonLabel', 'Button text'), f('buttonHref', 'Button link')];
        case 'project-cards':
            return [f('style', 'Style', 'select', ['options' => ['grid' => 'Grid', 'carousel' => 'Carousel']]),
                f('headingHtml', 'Section heading (HTML allowed)'), f('intro', 'Intro text', 'textarea', ['rows' => 2]),
                f('buttonLabel', 'Button text'), f('buttonHref', 'Button link'),
                f('exclude', 'Projects to hide (one project id per line)', 'lines', ['rows' => 2])];
        default:
            return [];
    }
}

/** Form groups for a page, by type. */
function admin_form_groups(array $page): array
{
    $type = (string) $page['type'];
    $content = [];

    switch ($type) {
        case 'blog-post':
            $content = [
                f('title', 'Post title (the H1 on the page)', 'text', ['required' => true]),
                f('category', 'Category', 'select', ['options' => array_combine(blog_categories(), blog_categories()), 'allowNew' => true,
                    'help' => 'The category is part of the post URL.']),
                f('featuredImage.src', 'Featured image', 'image', ['required' => true]),
                f('featuredImage.alt', 'Featured image description (alt text)', 'text', ['required' => true, 'help' => 'Describe the photo for Google and screen readers.']),
                f('excerpt', 'Short summary (shown on blog cards)', 'textarea', ['rows' => 2, 'max' => 200]),
                f('keyTakeaways', 'Key takeaways (one per line)', 'lines', ['rows' => 4]),
                f('contentHtml', 'Article', 'richtext', ['required' => true, 'tall' => true]),
                f('faqs', 'Frequently asked questions', 'repeater', ['fields' => faq_fields(), 'itemLabel' => 'Question']),
                f('internalLinks', '"Continue reading" links', 'repeater', ['fields' => link_fields(), 'itemLabel' => 'Link']),
                f('tags', 'Tags (one per line)', 'lines', ['rows' => 3]),
                f('author', 'Author', 'select', ['options' => array_combine(array_keys((array) site('authors')), array_keys((array) site('authors')))]),
                f('reviewedBy', 'Reviewed by'),
                f('publishDate', 'Publish date', 'datetime', ['required' => true]),
                f('readingTimeMinutes', 'Reading time in minutes (leave empty to calculate)', 'number'),
            ];
            break;
        case 'location':
            $content = [
                f('h1', 'Page heading (H1)', 'text', ['required' => true]),
                f('heroImage', 'Main image', 'image'),
                f('directAnswer', 'Opening answer (first paragraph)', 'textarea', ['rows' => 3]),
                f('facts', 'Fact pills', 'repeater', ['fields' => [f('value', 'Value', 'text', ['placeholder' => '90+']), f('label', 'Label', 'text', ['placeholder' => 'Projects Delivered'])], 'itemLabel' => 'Fact']),
                f('keyTakeaways', 'Key takeaways (one per line)', 'lines', ['rows' => 4]),
                f('sections', 'Sections', 'repeater', ['fields' => section_fields(), 'itemLabel' => 'Section']),
                f('faqs', 'Frequently asked questions', 'repeater', ['fields' => faq_fields(), 'itemLabel' => 'Question']),
                f('internalLinks', '"Explore more" links', 'repeater', ['fields' => link_fields(), 'itemLabel' => 'Link']),
                f('ctaContext', 'Consultation box wording', 'text', ['help' => 'Fills "Talk to our design team about your ___".']),
            ];
            break;
        case 'project':
            $content = [
                f('name', 'Project name', 'text', ['required' => true]),
                f('projectType', 'Project type', 'select', ['options' => ['Apartment Interior' => 'Apartment Interior', 'Villa Interior' => 'Villa Interior', 'Modular Kitchen' => 'Modular Kitchen'], 'allowNew' => true]),
                f('location', 'Location', 'text', ['placeholder' => 'Sector 102, Gurgaon']),
                f('h1', 'Page heading (H1)'),
                f('image', 'Main photo', 'image', ['required' => true]),
                f('imageAlt', 'Main photo description (alt text)'),
                f('gallery', 'Gallery photos (one per line, in order)', 'images'),
                f('leadAnswer', 'Opening paragraph (leave empty for a gallery-only page)', 'textarea', ['rows' => 3]),
                f('sections', 'Sections', 'repeater', ['fields' => section_fields(), 'itemLabel' => 'Section']),
                f('configuration', 'Configuration (e.g. 3 BHK)', 'text', ['help' => 'Only real figures: leave empty if unknown.']),
                f('sizeSqFt', 'Size in sq. ft.', 'text'),
                f('budgetBand', 'Budget band', 'text'),
                f('timeline', 'Timeline', 'text'),
                f('relatedBlogPosts', 'Related blog posts', 'repeater', ['fields' => link_fields(), 'itemLabel' => 'Link']),
                f('card.title', 'Card title on the projects page'),
                f('card.alt', 'Card photo description'),
                f('carouselCard.title', 'Card title in the homepage carousel (if different)'),
                f('sortOrder', 'Display order (1 = first)', 'number'),
            ];
            break;
        default:
            $content = [f('h1', 'Page heading (H1)', 'text', ['help' => 'Used in the breadcrumb and search results.'])];
            if (!empty($page['blocks'])) {
                $content[] = f('blocks', 'Page sections', 'blocks');
            } else {
                $content[] = f('intro', 'Introduction', 'richtext');
                $content[] = f('sections', 'Sections', 'repeater', ['fields' => section_fields(), 'itemLabel' => 'Section']);
                $content[] = f('faqs', 'Frequently asked questions', 'repeater', ['fields' => faq_fields(), 'itemLabel' => 'Question']);
            }
    }

    $seo = [
        f('seo.title', 'SEO title', 'text', ['required' => true, 'max' => 60, 'help' => 'Shown as the blue link in Google. About 50–60 characters.']),
        f('seo.description', 'Meta description', 'textarea', ['rows' => 3, 'max' => 160, 'help' => 'Shown under the link in Google. About 150–160 characters.']),
    ];
    if ($type === 'blog-post') {
        $seo[] = f('seo.focusKeyword', 'Focus keyword', 'text', ['help' => 'The main search phrase this post should rank for. Check keyword-map.md so two pages don\'t target the same one.']);
    } else {
        $seo[] = f('seo.keywords', 'Keywords (optional)');
    }
    $seo[] = f('seo.ogImage', 'Social sharing image (optional)', 'image');
    $seo[] = f('seo.noindex', 'Hide this page from Google (noindex)', 'checkbox');

    $groups = [
        ['title' => 'Content', 'fields' => $content],
        ['title' => 'Search engines (SEO)', 'fields' => $seo],
    ];
    if (($page['layout'] ?? 'default') !== 'bare') {
        $groups[] = ['title' => 'Consultation box', 'fields' => [f('cta', 'Consultation box', 'cta')]];
    }
    return $groups;
}

/** Existing blog categories, alphabetical. */
function blog_categories(): array
{
    $cats = array_unique(array_filter(array_map(fn ($p) => $p['category'] ?? '', array_filter(page_summaries(), fn ($p) => ($p['type'] ?? '') === 'blog-post'))));
    sort($cats);
    return array_values($cats);
}

/* ------------------------------------------------------------------ */
/* Dot-path helpers                                                    */
/* ------------------------------------------------------------------ */

function arr_get(array $data, string $path)
{
    foreach (explode('.', $path) as $key) {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return null;
        }
        $data = $data[$key];
    }
    return $data;
}

function arr_set(array &$data, string $path, $value): void
{
    $keys = explode('.', $path);
    $last = array_pop($keys);
    $ref = &$data;
    foreach ($keys as $key) {
        if (!isset($ref[$key]) || !is_array($ref[$key])) {
            $ref[$key] = [];
        }
        $ref = &$ref[$key];
    }
    if ($value === null) {
        unset($ref[$last]);
    } else {
        $ref[$last] = $value;
    }
}

/** HTML input name for a dot path: "seo.title" -> f[seo][title] */
function field_name(string $path, string $prefix = 'f'): string
{
    return $prefix . '[' . implode('][', explode('.', $path)) . ']';
}

/* ------------------------------------------------------------------ */
/* Form -> page                                                        */
/* ------------------------------------------------------------------ */

/** Convert one submitted value according to its field definition. */
function field_value(array $field, $raw)
{
    switch ($field['type']) {
        case 'checkbox':
            return !empty($raw) ? true : null;
        case 'number':
            return ($raw === null || trim((string) $raw) === '') ? null : (int) $raw;
        case 'datetime':
            $raw = trim((string) $raw);
            return $raw === '' ? null : (strlen($raw) === 16 ? $raw . ':00' : $raw);
        case 'richtext':
            $clean = sanitize_rich_html((string) $raw);
            return trim(strip_tags($clean, '<img>')) === '' ? null : $clean;
        case 'lines':
        case 'images':
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $raw) ?: [])));
            return $lines ?: null;
        case 'repeater':
            $rows = [];
            foreach ((array) $raw as $rowRaw) {
                $row = [];
                foreach ($field['fields'] as $sub) {
                    $v = field_value($sub, $rowRaw[$sub['key']] ?? null);
                    if ($v !== null) {
                        $row[$sub['key']] = $v;
                    }
                }
                if (isset($row['answerHtml'])) {
                    unset($row['answer']);
                }
                if ($row) {
                    $rows[] = $row;
                }
            }
            return $rows ?: null;
        case 'blocks':
            return blocks_from_form((array) $raw);
        case 'cta':
            if (empty($raw['show'])) {
                return false;
            }
            $cta = [];
            foreach (['eyebrow', 'heading', 'text', 'buttonLabel', 'buttonHref'] as $k) {
                $v = trim((string) ($raw[$k] ?? ''));
                if ($v !== '') {
                    $cta[$k] = $v;
                }
            }
            return $cta ?: null;
        default:
            $v = trim(str_replace(["\r\n", "\r"], "\n", (string) $raw));
            return $v === '' ? null : $v;
    }
}

/** Submitted blocks (in their new order) -> block arrays. */
function blocks_from_form(array $rows): array
{
    $blocks = [];
    foreach ($rows as $row) {
        $original = json_decode((string) ($row['_json'] ?? ''), true);
        $block = is_array($original) ? $original : [];
        $type = (string) ($row['type'] ?? ($block['type'] ?? ''));
        if (!in_array($type, BLOCK_TYPES, true)) {
            continue;
        }
        $block['type'] = $type;
        if ($type === 'html') {
            $html = sanitize_raw_html((string) ($row['html'] ?? ($block['html'] ?? '')));
            if (isset($row['text']) || isset($row['alt'])) {
                $html = html_apply_text_edits($html, (array) ($row['text'] ?? []), (array) ($row['alt'] ?? []));
            }
            $block['html'] = $html;
        }
        foreach (block_fields($type) as $field) {
            $value = field_value($field, $row[$field['key']] ?? null);
            if ($value === null) {
                unset($block[$field['key']]);
            } else {
                $block[$field['key']] = $value;
            }
        }
        $blocks[] = $block;
    }
    return $blocks;
}

/**
 * Apply a submitted form to a page: start from the stored page so fields the
 * form doesn't show are kept, then overwrite every field the form does show.
 */
function page_from_form(array $existing, array $form): array
{
    $page = $existing;
    foreach (admin_form_groups($existing) as $group) {
        foreach ($group['fields'] as $field) {
            $raw = arr_get($form, $field['key']);
            if ($field['type'] === 'select' && !empty($field['allowNew']) && trim((string) arr_get($form, $field['key'] . '__new')) !== '') {
                $raw = arr_get($form, $field['key'] . '__new');
            }
            $value = field_value($field, $raw);
            // The date picker has minute precision: keep the stored seconds
            // when the minute wasn't changed.
            if ($field['type'] === 'datetime' && is_string($value) && substr((string) arr_get($existing, $field['key']), 0, 16) === substr($value, 0, 16)) {
                $value = arr_get($existing, $field['key']);
            }
            if ($field['type'] === 'cta') {
                // Keep any non-text overrides (image, alt text) the form doesn't show.
                if ($value !== false && is_array($existing['cta'] ?? null)) {
                    $kept = array_diff_key($existing['cta'], array_flip(['eyebrow', 'heading', 'text', 'buttonLabel', 'buttonHref']));
                    $value = array_merge($kept, (array) $value) ?: null;
                }
                $page['cta'] = $value;
                if ($value === null) {
                    unset($page['cta']);
                }
                continue;
            }
            arr_set($page, $field['key'], $value);
        }
    }
    foreach (['card', 'carouselCard', 'featuredImage', 'seo'] as $key) {
        if (isset($page[$key]) && $page[$key] === []) {
            unset($page[$key]);
        }
    }
    return $page;
}

/** Required-field errors for a submitted page (keyed by field key). */
function form_required_errors(array $page): array
{
    $errors = [];
    foreach (admin_form_groups($page) as $group) {
        foreach ($group['fields'] as $field) {
            if (!empty($field['required']) && in_array(arr_get($page, $field['key']), [null, '', []], true)) {
                $errors[$field['key']] = $field['label'] . ' is required.';
            }
        }
    }
    return $errors;
}

/** Fill in automatic values before saving (reading time, image size, dates...). */
function page_autofill(array $page, ?array $before): array
{
    if ($page['type'] === 'blog-post') {
        if (empty($page['readingTimeMinutes']) && !empty($page['contentHtml'])) {
            $words = str_word_count(strip_tags((string) $page['contentHtml']));
            $page['readingTimeMinutes'] = max(1, (int) round($words / 200));
        }
        $src = $page['featuredImage']['src'] ?? null;
        if ($src && ($src !== ($before['featuredImage']['src'] ?? null) || empty($page['featuredImage']['width']))) {
            $dims = media_dimensions($src);
            if ($dims) {
                $page['featuredImage']['width'] = $dims['width'];
                $page['featuredImage']['height'] = $dims['height'];
            }
        }
        if ($before !== null && ($before['contentHtml'] ?? null) !== ($page['contentHtml'] ?? null)) {
            $page['modifiedDate'] = date('Y-m-d\TH:i:s');
        }
        $page['modifiedDate'] = $page['modifiedDate'] ?? ($page['publishDate'] ?? date('Y-m-d\TH:i:s'));
    }
    return $page;
}

/** The URL a new page of this type gets from its slug (and category). */
function default_url_path(string $type, string $slug, array $page = []): string
{
    switch ($type) {
        case 'blog-post':
            return '/' . slugify((string) ($page['category'] ?? 'blog')) . "/$slug/";
        case 'project':
            return "/project/$slug/";
        default:
            return "/$slug/";
    }
}

/** A brand-new page of a creatable type. */
function new_page(string $type): array
{
    $page = ['id' => '', 'type' => $type, 'status' => 'draft', 'urlPath' => '', 'seo' => ['title' => '']];
    if ($type === 'blog-post') {
        $authors = array_keys((array) site('authors'));
        $page += ['title' => '', 'category' => blog_categories()[0] ?? 'Blog', 'author' => $authors[0] ?? '',
            'reviewedBy' => 'The Omega Group design team', 'publishDate' => date('Y-m-d\TH:i:00'), 'contentHtml' => ''];
    }
    if ($type === 'project') {
        $page['sortOrder'] = count(projects_published()) + 1;
    }
    return $page;
}
