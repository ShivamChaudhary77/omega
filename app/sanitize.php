<?php
/**
 * Cleaning HTML that comes from the dashboard, and "text-only" editing of
 * hand-designed HTML sections.
 *
 * sanitize_rich_html()  for rich-text fields (blog body, sections, intro):
 *                       allow-list of tags and attributes, everything else
 *                       removed. Safe for any input.
 * sanitize_raw_html()   for "html" blocks (hand-designed sections): keeps
 *                       the markup exactly, only removing scripts, event
 *                       handlers and javascript: links.
 * html_text_parts() / html_apply_text_edits()
 *                       list the visible text and image alt texts of an HTML
 *                       section and write edited text back, without changing
 *                       a single character of the surrounding markup.
 */

declare(strict_types=1);

const RICH_ALLOWED = [
    'p' => ['class'], 'br' => [], 'h2' => ['id', 'class'], 'h3' => ['id', 'class'], 'h4' => ['id', 'class'], 'h5' => ['id', 'class'], 'strong' => [], 'b' => [],
    'em' => [], 'i' => ['class'], 'u' => [], 'ul' => ['class'], 'ol' => ['class', 'start'], 'li' => [], 'blockquote' => [], 'hr' => [],
    'code' => [], 'pre' => [], 'div' => ['class'], 'span' => ['class'], 'sup' => [], 'sub' => [],
    'a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading', 'title', 'class'],
    'figure' => [], 'figcaption' => [], 'table' => ['class'], 'thead' => [], 'tbody' => [], 'tr' => [],
    'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
];
const RICH_DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'noscript', 'svg', 'math'];

function url_is_safe(string $url): bool
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
    return $url === '' || $url[0] === '/' || $url[0] === '#'
        || (bool) preg_match('#^(https?://|mailto:|tel:)#i', $url)
        || !preg_match('#^[a-z][a-z0-9+.-]*:#i', $url); // relative path
}

/** Allow-list sanitizer for rich-text editor output. */
function sanitize_rich_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if (!$root) {
        return '';
    }
    sanitize_node_children($root);
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function sanitize_node_children(DOMNode $parent): void
{
    foreach (iterator_to_array($parent->childNodes) as $node) {
        if ($node instanceof DOMComment) {
            $parent->removeChild($node);
            continue;
        }
        if (!$node instanceof DOMElement) {
            continue;
        }
        $tag = strtolower($node->nodeName);
        if (in_array($tag, RICH_DROP_WITH_CONTENT, true)) {
            $parent->removeChild($node);
            continue;
        }
        sanitize_node_children($node);
        if (!array_key_exists($tag, RICH_ALLOWED)) {
            // Unknown wrapper (div, font, ...): keep its content, drop the tag.
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            continue;
        }
        foreach (iterator_to_array($node->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $keep = in_array($name, RICH_ALLOWED[$tag], true);
            if ($keep && in_array($name, ['href', 'src'], true) && !url_is_safe($attr->nodeValue)) {
                $keep = false;
            }
            if ($keep && $name === 'class' && !preg_match('/^[A-Za-z0-9_\- ]*$/', $attr->nodeValue)) {
                $keep = false;
            }
            if (!$keep) {
                $node->removeAttribute($attr->nodeName);
            }
        }
        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener');
        }
    }
}

/**
 * Light cleaning for hand-designed HTML sections: removes <script> elements,
 * on*="" event handlers and javascript:/vbscript:/data: links, and leaves
 * everything else byte-for-byte unchanged.
 */
function sanitize_raw_html(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $html) ?? '';
    $html = preg_replace('#<script\b[^>]*/?>#i', '', $html) ?? '';
    $html = preg_replace('#(<[a-z][^>]*?)\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '$1', $html) ?? '';
    $html = preg_replace_callback('#\b(href|src|action|formaction|poster)\s*=\s*(["\'])(.*?)\2#is', function ($m) {
        return preg_match('#^\s*(javascript|vbscript|data):#i', html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5)) ? $m[1] . '=' . $m[2] . '#' . $m[2] : $m[0];
    }, $html) ?? '';
    return $html;
}

/* ------------------------------------------------------------------ */
/* Text-only editing of HTML sections                                  */
/* ------------------------------------------------------------------ */

/** Split HTML into alternating [text, tag, text, tag, ...] pieces. */
function html_split(string $html): array
{
    return preg_split('#(<!--.*?-->|<script\b.*?</script\s*>|<style\b.*?</style\s*>|<[^>]+>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
}

/**
 * Editable parts of an HTML section, in order:
 *   ['kind' => 'text', 'key' => piece index, 'value' => decoded text]
 *   ['kind' => 'alt',  'key' => piece index, 'value' => decoded alt text]
 */
function html_text_parts(string $html): array
{
    $parts = [];
    foreach (html_split($html) as $i => $piece) {
        if ($i % 2 === 0) {
            $text = trim(html_entity_decode($piece, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text !== '') {
                $parts[] = ['kind' => 'text', 'key' => $i, 'value' => preg_replace('/\s+/u', ' ', $text)];
            }
        } elseif (preg_match('#^<img\b#i', $piece) && preg_match('#\salt="([^"]*)"#i', $piece, $m)) {
            $parts[] = ['kind' => 'alt', 'key' => $i, 'value' => html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')];
        }
    }
    return $parts;
}

/**
 * Write edited text back. $texts / $alts map piece index => new value.
 * Unchanged values leave the original characters (and entities) untouched;
 * changed text keeps the original surrounding whitespace.
 */
function html_apply_text_edits(string $html, array $texts, array $alts): string
{
    $pieces = html_split($html);
    $current = [];
    foreach (html_text_parts($html) as $part) {
        $current[$part['kind'] . ':' . $part['key']] = $part['value'];
    }
    foreach ($texts as $key => $value) {
        $key = (int) $key;
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        if (!isset($pieces[$key]) || $key % 2 !== 0 || !isset($current["text:$key"]) || $value === $current["text:$key"]) {
            continue;
        }
        preg_match('/^(\s*).*?(\s*)$/su', $pieces[$key], $ws);
        $pieces[$key] = ($ws[1] ?? '') . htmlspecialchars($value, ENT_NOQUOTES | ENT_HTML5, 'UTF-8') . ($ws[2] ?? '');
    }
    foreach ($alts as $key => $value) {
        $key = (int) $key;
        $value = trim((string) $value);
        if (!isset($pieces[$key]) || !isset($current["alt:$key"]) || $value === $current["alt:$key"]) {
            continue;
        }
        $pieces[$key] = preg_replace('#(\salt=)"[^"]*"#i', '$1"' . str_replace('$', '\$', e($value)) . '"', $pieces[$key], 1) ?? $pieces[$key];
    }
    return implode('', $pieces);
}
