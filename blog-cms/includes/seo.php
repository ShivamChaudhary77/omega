<?php
/**
 * SEO field fallback logic + structured data generation for CMS-managed
 * posts. Mirrors the fallback chain already used by the existing static
 * blog pipeline (scripts/build-post-pages.py) so a CMS post and a legacy
 * static post behave identically to a crawler:
 *   meta title       -> custom meta_title -> post title -> site default
 *   meta description -> custom meta_description -> excerpt -> generated fallback
 *   og_image         -> custom og_image -> featured image
 *   canonical        -> custom canonical_url -> the post's real live URL
 */

declare(strict_types=1);

const SEO_SITE_NAME = 'The Omega Group';
const SEO_SITE_URL = 'https://theomegagroup.in';
const SEO_DEFAULT_TITLE_SUFFIX = ' | The Omega Group Blog';

function seo_post_url(array $post, string $categorySlug): string
{
    return SEO_SITE_URL . '/' . $categorySlug . '/' . $post['slug'] . '/';
}

function seo_resolve_meta_title(array $post): string
{
    if (!empty($post['meta_title'])) {
        return $post['meta_title'];
    }
    return $post['title'] . SEO_DEFAULT_TITLE_SUFFIX;
}

function seo_resolve_meta_description(array $post): string
{
    if (!empty($post['meta_description'])) {
        return $post['meta_description'];
    }
    if (!empty($post['excerpt'])) {
        return $post['excerpt'];
    }
    $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($post['content'] ?? '')));
    return mb_substr($text, 0, 160);
}

function seo_resolve_canonical(array $post, string $categorySlug): string
{
    return !empty($post['canonical_url']) ? $post['canonical_url'] : seo_post_url($post, $categorySlug);
}

function seo_resolve_og_image(array $post, ?string $featuredImageUrl): ?string
{
    if (!empty($post['og_image'])) {
        return $post['og_image'];
    }
    return $featuredImageUrl;
}

function seo_resolve_twitter_image(array $post, ?string $ogImage): ?string
{
    return !empty($post['twitter_image']) ? $post['twitter_image'] : $ogImage;
}

/**
 * Renders <title>, meta description, canonical, robots, OG + Twitter tags.
 * All values are passed through htmlspecialchars — nothing here trusts
 * admin-entered SEO fields to be safe to print raw, since they end up in
 * attribute values a browser/crawler parses.
 */
function seo_render_head_tags(array $post, string $categorySlug, ?string $featuredImageUrl): string
{
    $title = seo_resolve_meta_title($post);
    $description = seo_resolve_meta_description($post);
    $canonical = seo_resolve_canonical($post, $categorySlug);
    $ogImage = seo_resolve_og_image($post, $featuredImageUrl);
    $twitterImage = seo_resolve_twitter_image($post, $ogImage);
    $ogTitle = !empty($post['og_title']) ? $post['og_title'] : $title;
    $ogDescription = !empty($post['og_description']) ? $post['og_description'] : $description;
    $twitterTitle = !empty($post['twitter_title']) ? $post['twitter_title'] : $ogTitle;
    $twitterDescription = !empty($post['twitter_description']) ? $post['twitter_description'] : $ogDescription;
    $robots = ($post['robots_index'] ?? 'index') . ', ' . ($post['robots_follow'] ?? 'follow');

    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    $html = '';
    $html .= "<title>{$e($title)}</title>\n";
    $html .= "    <meta name=\"description\" content=\"{$e($description)}\">\n";
    $html .= "    <meta name=\"robots\" content=\"{$e($robots)}\">\n";
    $html .= "    <link rel=\"canonical\" href=\"{$e($canonical)}\">\n";
    $html .= "    <meta property=\"og:type\" content=\"article\">\n";
    $html .= "    <meta property=\"og:site_name\" content=\"" . SEO_SITE_NAME . "\">\n";
    $html .= "    <meta property=\"og:title\" content=\"{$e($ogTitle)}\">\n";
    $html .= "    <meta property=\"og:description\" content=\"{$e($ogDescription)}\">\n";
    $html .= "    <meta property=\"og:url\" content=\"{$e($canonical)}\">\n";
    if ($ogImage) {
        $html .= "    <meta property=\"og:image\" content=\"{$e($ogImage)}\">\n";
    }
    $html .= "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    $html .= "    <meta name=\"twitter:title\" content=\"{$e($twitterTitle)}\">\n";
    $html .= "    <meta name=\"twitter:description\" content=\"{$e($twitterDescription)}\">\n";
    if ($twitterImage) {
        $html .= "    <meta name=\"twitter:image\" content=\"{$e($twitterImage)}\">\n";
    }

    return $html;
}

/**
 * Known named authors, keyed by exact display name — mirrors AUTHORS in
 * scripts/build-post-pages.py (the static pipeline's equivalent). Extend
 * both together if a second named author is ever added.
 */
const SEO_AUTHORS = [
    'Shivam Kumar' => [
        'url' => SEO_SITE_URL . '/author/shivam-kumar/',
        'sameAs' => ['https://www.linkedin.com/in/shivam-growth-engineer/'],
    ],
];

function seo_build_author_ld(string $authorName): array
{
    $ld = ['@type' => 'Person', 'name' => $authorName];
    if (isset(SEO_AUTHORS[$authorName])) {
        $ld += SEO_AUTHORS[$authorName];
    }
    return $ld;
}

/**
 * Builds one BlogPosting JSON-LD block server-side from trusted, already-
 * validated post fields (never from raw unsanitized admin JSON input — the
 * project requirement is explicit that arbitrary JSON-LD injection from
 * untrusted users must not be allowed, so there is no "paste your own
 * schema" field anywhere in the editor).
 */
function seo_build_article_ld(array $post, string $categorySlug, ?string $featuredImageUrl, string $authorName): string
{
    $url = seo_resolve_canonical($post, $categorySlug);
    $image = seo_resolve_og_image($post, $featuredImageUrl);

    $data = [
        '@context' => 'https://schema.org',
        '@type' => $post['schema_type'] ?: 'BlogPosting',
        'headline' => mb_substr($post['title'], 0, 110),
        'description' => seo_resolve_meta_description($post),
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'author' => seo_build_author_ld($authorName),
        'publisher' => [
            '@type' => 'Organization',
            'name' => SEO_SITE_NAME,
            'logo' => ['@type' => 'ImageObject', 'url' => SEO_SITE_URL . '/img/logo.png'],
        ],
        'datePublished' => $post['published_at'] ? date(DATE_ATOM, strtotime($post['published_at'])) : null,
        'dateModified' => date(DATE_ATOM, strtotime($post['updated_at'] ?? 'now')),
    ];
    if ($image) {
        $data['image'] = [$image];
    }
    $data = array_filter($data, fn($v) => $v !== null);

    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

function seo_build_breadcrumb_ld(string $categoryName, string $categorySlug, string $postTitle, string $postUrl): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SEO_SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => SEO_SITE_URL . '/blog.html'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $categoryName, 'item' => SEO_SITE_URL . '/' . $categorySlug . '/'],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $postTitle, 'item' => $postUrl],
        ],
    ];
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * A simple, transparent CONTENT CHECKLIST score (0-100) based on whether
 * this post's own fields are filled in sensibly — title length, meta
 * description length, featured image present, content length, focus
 * keyword present in title. This is NOT a Google ranking score, is not
 * derived from any Google API or data, and must always be labeled in the
 * admin UI as "Content checklist score" — never "SEO score" or anything
 * implying it reflects real search performance. See admin/blogs/edit.php
 * for the label.
 */
function seo_calculate_content_checklist_score(array $post): int
{
    $score = 0;
    $titleLen = mb_strlen($post['title'] ?? '');
    if ($titleLen >= 30 && $titleLen <= 65) {
        $score += 20;
    } elseif ($titleLen > 0) {
        $score += 10;
    }

    $metaDesc = seo_resolve_meta_description($post);
    $descLen = mb_strlen($metaDesc);
    if ($descLen >= 120 && $descLen <= 160) {
        $score += 20;
    } elseif ($descLen > 0) {
        $score += 10;
    }

    if (!empty($post['featured_image_id'])) {
        $score += 20;
    }

    $wordCount = str_word_count(strip_tags($post['content'] ?? ''));
    if ($wordCount >= 600) {
        $score += 20;
    } elseif ($wordCount >= 300) {
        $score += 10;
    }

    if (!empty($post['focus_keyword']) && stripos($post['title'] ?? '', $post['focus_keyword']) !== false) {
        $score += 20;
    }

    return min(100, $score);
}
