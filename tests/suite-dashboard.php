<?php
/**
 * Suite: dashboard logic (Phase 4): password and login limits, HTML
 * cleaning, text-only editing, and the edit form, including a round trip
 * of every real migrated page (open in the editor, save unchanged, page must
 * render exactly as before).
 */

declare(strict_types=1);

require_once OMEGA_APP . '/security.php';
require_once OMEGA_APP . '/sanitize.php';
require_once OMEGA_APP . '/media.php';
require_once OMEGA_APP . '/admin_fields.php';

foreach (glob(REPO_DIR . '/content/pages/*.json') as $file) {
    copy($file, "$tmp/pages/" . basename($file));
}
page_index_rebuild();

echo "Password and login limits\n";
check('no password initially', !admin_password_is_set());
try {
    admin_password_set('short');
    check('short password rejected', false);
} catch (InvalidArgumentException $e) {
    check('short password rejected', true);
}
admin_password_set('correct horse battery');
check('password stored as a hash only', admin_password_is_set() && !str_contains((string) file_get_contents(admin_password_file()), 'correct horse'));
check('right password accepted', admin_password_verify('correct horse battery'));
check('wrong password rejected', !admin_password_verify('wrong password!!'));
$ip = '203.0.113.9';
for ($i = 0; $i < LOGIN_MAX_FAILS; $i++) {
    login_record_failure($ip);
}
check('IP locked after ' . LOGIN_MAX_FAILS . ' failures', login_is_locked($ip));
check('other IPs unaffected', !login_is_locked('198.51.100.1'));
login_clear_failures($ip);
check('lock cleared after a successful login', !login_is_locked($ip));

echo "\nHTML cleaning\n";
$dirty = '<p onclick="steal()">Hi<script>alert(1)</script> <a href="javascript:alert(1)">x</a> <a href="/ok/" target="_blank">ok</a></p><iframe src="//evil"></iframe><img src="/a.webp" onerror="x" alt="A"><h2 style="color:red" data-start="1">Head</h2>';
$clean = sanitize_rich_html($dirty);
check('rich: scripts, iframes and handlers removed', !preg_match('/script|iframe|onclick|onerror|javascript:/i', $clean), $clean);
check('rich: safe links, images and headings kept', str_contains($clean, '<a href="/ok/" target="_blank" rel="noopener">ok</a>') && str_contains($clean, '<img src="/a.webp" alt="A">') && str_contains($clean, '<h2>Head</h2>'), $clean);
$raw = '<div class="x" onmouseover="bad()"><a href="javascript:bad()">a</a><script>bad()</script><p style="color:red">Keep</p></div>';
check('raw: only dangerous parts removed', sanitize_raw_html($raw) === '<div class="x"><a href="#">a</a><p style="color:red">Keep</p></div>', sanitize_raw_html($raw));

$migratedHtml = 0;
$untouched = 0;
foreach (pages_all() as $page) {
    foreach ((array) ($page['blocks'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'html') {
            $migratedHtml++;
            $untouched += (int) (sanitize_raw_html($block['html']) === $block['html'] && html_apply_text_edits($block['html'], [], []) === $block['html']);
        }
    }
}
check("all $migratedHtml designed sections survive cleaning unchanged", $migratedHtml > 0 && $untouched === $migratedHtml);

echo "\nText-only editing\n";
$html = "<div class=\"card\">\n  <h3>Old &amp; tired</h3>\n  <p>Keep me</p>\n  <img class=\"x\" src=\"/a.webp\" alt=\"Old alt\">\n</div>";
$parts = html_text_parts($html);
check('finds texts and image descriptions', array_column($parts, 'value') === ['Old & tired', 'Keep me', 'Old alt']);
$texts = [];
$alts = [];
foreach ($parts as $part) {
    if ($part['value'] === 'Old & tired') {
        $texts[$part['key']] = 'New <heading> & fresh';
    } elseif ($part['kind'] === 'alt') {
        $alts[$part['key']] = 'New "alt"';
    } else {
        $texts[$part['key']] = $part['value'];
    }
}
$edited = html_apply_text_edits($html, $texts, $alts);
check('edited text escaped, markup untouched', $edited === "<div class=\"card\">\n  <h3>New &lt;heading&gt; &amp; fresh</h3>\n  <p>Keep me</p>\n  <img class=\"x\" src=\"/a.webp\" alt=\"New &quot;alt&quot;\">\n</div>", $edited);

echo "\nEdit form round trip (all real pages)\n";
/** What the browser would submit for a page, built from the field definitions. */
function form_value(array $field, $value)
{
    switch ($field['type']) {
        case 'checkbox':
            return !empty($value) ? '1' : '';
        case 'lines':
        case 'images':
            return implode("\n", (array) $value);
        case 'datetime':
            return substr((string) $value, 0, 16);
        case 'repeater':
            $rows = [];
            foreach ((array) $value as $row) {
                $r = [];
                foreach ($field['fields'] as $sub) {
                    $r[$sub['key']] = form_value($sub, $row[$sub['key']] ?? null);
                }
                $rows[] = $r;
            }
            return $rows;
        default:
            return $value === null ? '' : (string) $value;
    }
}

function form_for(array $page): array
{
    $form = [];
    foreach (admin_form_groups($page) as $group) {
        foreach ($group['fields'] as $field) {
            $value = arr_get($page, $field['key']);
            if ($field['type'] === 'blocks') {
                $rows = [];
                foreach ((array) $value as $block) {
                    $json = $block;
                    unset($json['html']);
                    $row = ['type' => $block['type'], '_json' => json_encode($json)];
                    if ($block['type'] === 'html') {
                        $row['html'] = $block['html'];
                        foreach (html_text_parts($block['html']) as $part) {
                            $row[$part['kind']][$part['key']] = $part['value'];
                        }
                    }
                    foreach (block_fields($block['type']) as $bf) {
                        $row[$bf['key']] = form_value($bf, $block[$bf['key']] ?? null);
                    }
                    $rows[] = $row;
                }
                $form['blocks'] = $rows;
            } elseif ($field['type'] === 'cta') {
                $cta = $page['cta'] ?? null;
                $form['cta'] = ['show' => $cta === false ? '' : '1'] + (is_array($cta) ? array_map('strval', array_intersect_key($cta, array_flip(['eyebrow', 'heading', 'text', 'buttonLabel', 'buttonHref']))) : []);
            } else {
                arr_set($form, $field['key'], form_value($field, $value));
            }
        }
    }
    return $form;
}

function render_fingerprint(array $page): array
{
    $html = render_page($page);
    preg_match_all('#<([a-z][a-z0-9]*)\b[^>]*?(?:\sclass="([^"]*)")?[^>]*>#i', $html, $m);
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#<script\b.*?</script>#s', '', $html)), ENT_QUOTES | ENT_HTML5)));
    return [$text, implode(' ', $m[1]), implode(' ', $m[2])];
}

$changed = [];
$count = 0;
foreach (pages_all() as $id => $page) {
    $count++;
    $after = page_from_form($page, form_for($page));
    if (render_fingerprint($page) !== render_fingerprint($after)) {
        $changed[] = $id;
    }
}
check("open + save unchanged keeps all $count pages identical when rendered", $count >= 148 && !$changed, implode(', ', array_slice($changed, 0, 8)));

$post = page_load('bespoke-interiors-gurgaon');
$after = page_from_form($post, form_for($post));
check('fields the form does not show are kept (seq, image size, modifiedDate)', $after['seq'] === $post['seq'] && $after['featuredImage']['width'] === $post['featuredImage']['width'] && $after['modifiedDate'] === $post['modifiedDate']);
check('unchanged publish date keeps its seconds', $after['publishDate'] === $post['publishDate']);

echo "\nEditing\n";
$form = form_for($post);
$form['seo']['title'] = 'New SEO Title';
$form['faqs'][] = ['question' => 'A new question?', 'answer' => 'A new answer.', 'answerHtml' => ''];
array_shift($form['faqs']);
$form['contentHtml'] = '<p>Short new body</p><script>x()</script>';
$after = page_from_form($post, $form);
check('text field change applied', $after['seo']['title'] === 'New SEO Title');
check('repeater rows added and removed', end($after['faqs'])['question'] === 'A new question?' && count($after['faqs']) === count($post['faqs']));
check('rich text cleaned on save', $after['contentHtml'] === '<p>Short new body</p>');
$auto = page_autofill(array_merge($after, ['readingTimeMinutes' => null]), $post);
check('reading time and modified date filled automatically', $auto['readingTimeMinutes'] === 1 && $auto['modifiedDate'] !== $post['modifiedDate']);

$home = page_load('home');
$form = form_for($home);
foreach ($form['blocks'] as $i => $row) {
    if ($row['type'] === 'html' && !empty($row['text'])) {
        $firstKey = array_key_first($row['text']);
        $oldText = $row['text'][$firstKey];
        $form['blocks'][$i]['text'][$firstKey] = 'Brand new headline';
        $target = $i;
        break;
    }
}
$after = page_from_form($home, $form);
check('designed section: text edit changes only that text', str_contains($after['blocks'][$target]['html'], 'Brand new headline')
    && str_replace('Brand new headline', '', $after['blocks'][$target]['html']) === str_replace(htmlspecialchars($oldText, ENT_NOQUOTES | ENT_HTML5), '', $home['blocks'][$target]['html']));
$form = form_for($home);
$moved = array_pop($form['blocks']);
array_unshift($form['blocks'], $moved);
$after = page_from_form($home, $form);
check('sections can be reordered (extra block data kept)', $after['blocks'][0]['type'] === end($home['blocks'])['type'] && ($after['blocks'][0]['count'] ?? null) === (end($home['blocks'])['count'] ?? null));
$form = form_for($home);
$form['cta'] = ['show' => ''];
check('consultation box can be hidden', page_from_form($home, $form)['cta'] === false);

echo "\nNew pages\n";
$new = new_page('blog-post');
check('new blog post starts as a draft with today\'s date', $new['status'] === 'draft' && str_starts_with($new['publishDate'], date('Y-m-d')));
check('default URL from category + slug', default_url_path('blog-post', 'my-post', ['category' => 'Kitchen Design']) === '/kitchen-design/my-post/');
check('default URL for projects', default_url_path('project', 'x', []) === '/project/x/');
$new['id'] = 'test-new-post';
$new['urlPath'] = '/kitchen-design/test-new-post/';
$new['title'] = 'Test';
$new['seo']['title'] = 'Test';
$new['contentHtml'] = '<p>Body</p>';
$saved = page_save(page_autofill($new, null));
check('new post gets the next seq', $saved['seq'] === max(array_map(fn ($p) => (int) ($p['seq'] ?? 0), page_summaries())));
check('draft not public until published', get('/kitchen-design/test-new-post/')['status'] === 404);
$saved['status'] = 'published';
page_save($saved);
check('published post is live', get('/kitchen-design/test-new-post/')['status'] === 200);
check('required fields enforced', isset(form_required_errors(new_page('blog-post'))['title']));
