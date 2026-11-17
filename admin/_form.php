<?php
/**
 * Renders dashboard form fields from the definitions in app/admin_fields.php.
 */

declare(strict_types=1);

/** One field. $name is the input name for this field ("f[seo][title]"). */
function render_field(array $field, $value, string $name, array $errors = [], string $errorKey = ''): void
{
    $id = 'fld_' . substr(md5($name), 0, 10);
    $type = $field['type'];
    $error = $errors[$errorKey ?: $field['key']] ?? null;
    $required = !empty($field['required']);
    $label = e($field['label']) . ($required ? ' <span class="text-danger">*</span>' : '');
    $help = !empty($field['help']) ? '<div class="form-text">' . e($field['help']) . '</div>' : '';
    $max = !empty($field['max']) ? ' data-max="' . (int) $field['max'] . '"' : '';
    $invalid = $error ? ' is-invalid' : '';

    echo '<div class="admin-field admin-field-' . e($type) . '">';
    switch ($type) {
        case 'checkbox':
            echo '<input type="hidden" name="' . e($name) . '" value="">';
            echo '<div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="' . $id . '" name="' . e($name) . '"' . (!empty($value) ? ' checked' : '') . '>';
            echo '<label class="form-check-label" for="' . $id . '">' . $label . '</label></div>';
            break;

        case 'textarea':
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<textarea class="form-control' . $invalid . '" id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($field['rows'] ?? 4) . '"' . $max . '>' . e($value) . '</textarea>';
            break;

        case 'richtext':
            $cls = !empty($field['tall']) ? 'richtext richtext-tall' : (!empty($field['small']) ? 'richtext richtext-small' : 'richtext');
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<textarea class="form-control ' . $cls . $invalid . '" id="' . $id . '" name="' . e($name) . '" rows="8">' . e($value) . '</textarea>';
            break;

        case 'lines':
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<textarea class="form-control' . $invalid . '" id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($field['rows'] ?? 4) . '">' . e(implode("\n", (array) $value)) . '</textarea>';
            break;

        case 'images':
            $list = (array) $value;
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<div class="gallery-field" data-gallery>';
            echo '<div class="gallery-thumbs">';
            foreach ($list as $src) {
                echo '<img src="' . e($src) . '" alt="" loading="lazy">';
            }
            echo '</div>';
            echo '<textarea class="form-control font-monospace small' . $invalid . '" id="' . $id . '" name="' . e($name) . '" rows="5">' . e(implode("\n", $list)) . '</textarea>';
            echo '<button type="button" class="btn btn-sm btn-outline-dark mt-2" data-pick-image="multi">Add photos…</button>';
            echo '</div>';
            break;

        case 'image':
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<div class="image-field" data-image-field>';
            echo '<img class="image-preview" src="' . e($value ?: '') . '" alt=""' . ($value ? '' : ' hidden') . '>';
            echo '<div class="input-group"><input class="form-control' . $invalid . '" type="text" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="/uploads/… or /img/…">';
            echo '<button type="button" class="btn btn-outline-dark" data-pick-image="single">Choose…</button></div></div>';
            break;

        case 'number':
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<input class="form-control' . $invalid . '" type="number" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '">';
            break;

        case 'datetime':
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<input class="form-control' . $invalid . '" type="datetime-local" id="' . $id . '" name="' . e($name) . '" value="' . e(substr((string) $value, 0, 16)) . '">';
            break;

        case 'select':
            $options = (array) ($field['options'] ?? []);
            $known = array_key_exists((string) $value, $options) || $value === null || $value === '';
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<select class="form-select' . $invalid . '" id="' . $id . '" name="' . e($name) . '">';
            if (!$known) {
                echo '<option value="' . e($value) . '" selected>' . e($value) . '</option>';
            }
            foreach ($options as $optValue => $optLabel) {
                echo '<option value="' . e($optValue) . '"' . ((string) $optValue === (string) $value ? ' selected' : '') . '>' . e($optLabel) . '</option>';
            }
            echo '</select>';
            if (!empty($field['allowNew'])) {
                $newName = preg_replace('/\]$/', '__new]', $name);
                echo '<input class="form-control form-control-sm mt-1" type="text" name="' . e($newName) . '" placeholder="…or type a new one">';
            }
            break;

        case 'repeater':
            render_repeater($field, (array) $value, $name, $errors);
            break;

        default:
            echo '<label class="form-label" for="' . $id . '">' . $label . '</label>';
            echo '<input class="form-control' . $invalid . '" type="text" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . $max
                . (!empty($field['placeholder']) ? ' placeholder="' . e($field['placeholder']) . '"' : '') . '>';
    }
    if ($error) {
        echo '<div class="invalid-feedback d-block">' . e($error) . '</div>';
    }
    echo $help . '</div>';
}

/** Repeating rows (FAQs, links, sections...). */
function render_repeater(array $field, array $rows, string $name, array $errors = []): void
{
    $itemLabel = $field['itemLabel'] ?? 'Item';
    echo '<label class="form-label">' . e($field['label']) . '</label>';
    echo '<div class="repeater" data-repeater>';
    echo '<div class="repeater-items">';
    foreach (array_values($rows) as $i => $row) {
        render_repeater_row($field, (array) $row, $name . "[$i]", $itemLabel);
    }
    echo '</div><template>';
    render_repeater_row($field, [], $name . '[__INDEX__]', $itemLabel);
    echo '</template>';
    echo '<button type="button" class="btn btn-sm btn-outline-dark" data-repeater-add><i class="bi bi-plus-lg"></i> Add ' . e(strtolower($itemLabel)) . '</button>';
    echo '</div>';
}

function render_repeater_row(array $field, array $row, string $rowName, string $itemLabel): void
{
    echo '<div class="repeater-item"><div class="item-tools"><span class="item-label">' . e($itemLabel) . '</span>';
    echo item_tool_buttons();
    echo '</div>';
    foreach ($field['fields'] as $sub) {
        if ($sub['key'] === 'answerHtml' && empty($row['answerHtml'])) {
            echo '<details class="small mb-2"><summary>Answer needs a link?</summary>';
            render_field($sub, null, $rowName . '[' . $sub['key'] . ']');
            echo '</details>';
            continue;
        }
        render_field($sub, $row[$sub['key']] ?? null, $rowName . '[' . $sub['key'] . ']');
    }
    echo '</div>';
}

function item_tool_buttons(): string
{
    return '<span class="btn-group btn-group-sm">'
        . '<button type="button" class="btn btn-outline-secondary" data-move="up" title="Move up"><i class="bi bi-arrow-up"></i></button>'
        . '<button type="button" class="btn btn-outline-secondary" data-move="down" title="Move down"><i class="bi bi-arrow-down"></i></button>'
        . '<button type="button" class="btn btn-outline-danger" data-remove title="Remove"><i class="bi bi-trash"></i></button></span>';
}

/** The page-sections editor for block pages. */
function render_blocks_editor(array $blocks, string $name): void
{
    echo '<div class="blocks-editor" data-repeater>';
    echo '<div class="repeater-items">';
    foreach (array_values($blocks) as $i => $block) {
        render_block($block, $name . "[$i]");
    }
    echo '</div>';
    foreach (BLOCK_LABELS as $type => $label) {
        echo '<template data-block-type="' . e($type) . '">';
        render_block(['type' => $type] + ($type === 'project-cards' ? ['style' => 'grid'] : []), $name . '[__BLOCK__]', true);
        echo '</template>';
    }
    echo '<div class="dropdown"><button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> Add section</button><ul class="dropdown-menu">';
    foreach (BLOCK_LABELS as $type => $label) {
        echo '<li><button type="button" class="dropdown-item" data-add-block="' . e($type) . '">' . e($label) . '</button></li>';
    }
    echo '</ul></div></div>';
}

function render_block(array $block, string $name, bool $isNew = false): void
{
    $type = (string) $block['type'];
    $json = $block;
    unset($json['html']);
    echo '<div class="repeater-item block-item" data-block="' . e($type) . '">';
    echo '<div class="item-tools"><span class="item-label">' . e(BLOCK_LABELS[$type] ?? $type) . block_summary($block) . '</span>' . item_tool_buttons() . '</div>';
    echo '<input type="hidden" name="' . e($name) . '[type]" value="' . e($type) . '">';
    echo '<input type="hidden" name="' . e($name) . '[_json]" value="' . e(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '">';

    if ($type === 'html') {
        $html = (string) ($block['html'] ?? '');
        $parts = html_text_parts($html);
        $textMode = !$isNew && $parts;
        echo '<div class="html-block" data-html-block data-mode="' . ($textMode ? 'text' : 'html') . '">';
        echo '<div class="btn-group btn-group-sm mb-2" role="group">'
            . '<button type="button" class="btn btn-outline-dark" data-mode-btn="text"' . ($parts || !$isNew ? '' : ' disabled') . '>Edit text</button>'
            . '<button type="button" class="btn btn-outline-dark" data-mode-btn="html">Edit HTML (advanced)</button></div>';
        echo '<div class="html-text-fields">';
        echo '<p class="form-text mt-0">Change the words of this designed section. The design stays exactly as it is.</p>';
        foreach ($parts as $part) {
            $fieldName = $name . '[' . $part['kind'] . '][' . $part['key'] . ']';
            $label = $part['kind'] === 'alt' ? 'Image description' : '';
            if ($label) {
                echo '<label class="form-label small text-muted mb-0">' . $label . '</label>';
            }
            if (mb_strlen($part['value']) > 90) {
                echo '<textarea class="form-control form-control-sm mb-2" rows="' . min(6, (int) ceil(mb_strlen($part['value']) / 90)) . '" name="' . e($fieldName) . '">' . e($part['value']) . '</textarea>';
            } else {
                echo '<input class="form-control form-control-sm mb-2" type="text" name="' . e($fieldName) . '" value="' . e($part['value']) . '">';
            }
        }
        echo '</div>';
        echo '<div class="html-raw-field"><textarea class="form-control font-monospace small" rows="14" name="' . e($name) . '[html]" spellcheck="false">' . e($html) . '</textarea>'
            . '<div class="form-text">Advanced: changing the HTML can break the layout. Use “Edit text” for wording changes.</div></div>';
        echo '</div>';
    } elseif ($type === 'blog-listing') {
        echo '<p class="form-text mt-0">Shows the newest post, the category filter, search and every published blog post automatically.</p>';
    } else {
        if ($type === 'latest-posts') {
            echo '<p class="form-text mt-0">Shows the newest published blog posts automatically.</p>';
        } elseif ($type === 'project-cards') {
            echo '<p class="form-text mt-0">Shows every published project automatically, in their display order.</p>';
        }
        foreach (block_fields($type) as $field) {
            $value = $block[$field['key']] ?? null;
            if ($field['type'] === 'repeater') {
                render_repeater($field, (array) $value, $name . '[' . $field['key'] . ']');
            } else {
                render_field($field, $value, $name . '[' . $field['key'] . ']');
            }
        }
    }
    echo '</div>';
}

/** A short hint of what a section contains, for the collapsed header. */
function block_summary(array $block): string
{
    $text = '';
    if (($block['type'] ?? '') === 'html') {
        if (preg_match('#<h[1-3][^>]*>(.*?)</h[1-3]>#s', (string) ($block['html'] ?? ''), $m)) {
            $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
        }
    } else {
        $text = trim(strip_tags((string) ($block['heading'] ?? $block['headingHtml'] ?? $block['h1'] ?? '')));
    }
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return $text !== '' ? ' <span class="text-muted fw-normal">— ' . e(mb_strimwidth($text, 0, 60, '…')) . '</span>' : '';
}

/** Consultation box settings. */
function render_cta_field(array $page, array $site): void
{
    $cta = $page['cta'] ?? null;
    $show = $cta !== false;
    $defaults = page_cta(array_merge($page, ['cta' => null]), $site);
    $values = is_array($cta) ? $cta : [];
    echo '<input type="hidden" name="f[cta][show]" value="">';
    echo '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" id="cta_show" name="f[cta][show]"' . ($show ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="cta_show">Show the consultation box above the footer</label></div>';
    echo '<p class="form-text">Leave a box empty to use the standard wording shown in grey.</p>';
    $labels = ['eyebrow' => 'Small heading', 'heading' => 'Heading', 'text' => 'Text', 'buttonLabel' => 'Button text', 'buttonHref' => 'Button link'];
    foreach ($labels as $key => $label) {
        echo '<div class="admin-field"><label class="form-label" for="cta_' . $key . '">' . e($label) . '</label>';
        $attrs = ' id="cta_' . $key . '" name="f[cta][' . $key . ']" placeholder="' . e($defaults[$key] ?? '') . '"';
        if ($key === 'text') {
            echo '<textarea class="form-control" rows="2"' . $attrs . '>' . e($values[$key] ?? '') . '</textarea>';
        } else {
            echo '<input class="form-control" type="text"' . $attrs . ' value="' . e($values[$key] ?? '') . '">';
        }
        echo '</div>';
    }
}
