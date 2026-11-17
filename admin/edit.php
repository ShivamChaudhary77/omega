<?php
/**
 * Create or edit a page.
 *   /admin/edit.php?id=<page id>     edit
 *   /admin/edit.php?new=<type>       create (blog-post, location, project, page)
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_form.php';

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$newType = (string) ($_GET['new'] ?? $_POST['new'] ?? '');
$isNew = $id === '';

if ($isNew) {
    if (!isset(CREATABLE_TYPES[$newType])) {
        flash('danger', 'Unknown page type.');
        redirect_to('/admin/');
    }
    $stored = null;
    $page = new_page($newType);
} else {
    $stored = page_load($id);
    if (!$stored) {
        flash('danger', 'That page does not exist (it may have been deleted).');
        redirect_to('/admin/');
    }
    $page = $stored;
}

$errors = [];
$meta = ['slug' => '', 'urlPath' => $page['urlPath'] ?? '', 'status' => $page['status'] ?? 'draft'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors['_form'] = 'The form expired (you may have been logged out). Your changes are below; please save again.';
    }
    $meta = array_merge($meta, array_map(fn ($v) => trim((string) $v), (array) ($_POST['meta'] ?? [])));
    $page = page_from_form($page, (array) ($_POST['f'] ?? []));
    $page['status'] = in_array($meta['status'], PAGE_STATUSES, true) ? $meta['status'] : 'draft';

    if ($isNew) {
        $base = $meta['slug'] !== '' ? $meta['slug'] : (string) ($page['title'] ?? $page['name'] ?? $page['h1'] ?? $page['seo']['title'] ?? '');
        $page['id'] = slugify($base);
        if ($page['id'] === '') {
            $errors['meta.slug'] = 'Give the page a title or a page address.';
        } elseif (page_load($page['id']) || in_array($page['id'], pages_in_trash(), true)) {
            $errors['meta.slug'] = 'A page with the address "' . $page['id'] . '" already exists (or is in the trash). Choose another.';
        }
    }
    $page['urlPath'] = $meta['urlPath'] !== '' ? $meta['urlPath'] : default_url_path($page['type'], (string) $page['id'], $page);
    if ($page['urlPath'] !== '' && !str_starts_with($page['urlPath'], '/')) {
        $page['urlPath'] = '/' . $page['urlPath'];
    }
    if (!isset($page['seo']['title']) && ($page['title'] ?? $page['h1'] ?? $page['name'] ?? '') !== '') {
        $page['seo']['title'] = ($page['title'] ?? $page['h1'] ?? $page['name']) . ' | ' . (site('business')['name'] ?? '');
    }

    $errors += form_required_errors($page);
    if (!$errors) {
        $page = page_autofill($page, $stored);
        try {
            $saved = page_save($page);
            $verb = $saved['status'] === 'published' ? 'Saved and published' : 'Saved as draft';
            $note = '.';
            // A published page moved: send its old address to the new one
            // (301), so links and Google rankings carry over.
            if ($stored && ($stored['status'] ?? '') === 'published' && $stored['urlPath'] !== $saved['urlPath']) {
                redirect_set($stored['urlPath'], $saved['urlPath']);
                $note = '. Its address changed, so the old address ' . $stored['urlPath'] . ' now redirects here automatically.';
            }
            flash('success', $verb . ': ' . ($saved['title'] ?? $saved['name'] ?? $saved['h1'] ?? $saved['seo']['title']) . $note);
            redirect_to('/admin/edit.php?id=' . rawurlencode($saved['id']));
        } catch (PageValidationException $e) {
            foreach ($e->errors as $k => $msg) {
                $errors[$k === 'urlPath' || $k === 'id' ? 'meta.' . ($k === 'id' ? 'slug' : 'urlPath') : $k] = $msg;
            }
        }
    }
}

$site = site();
$displayTitle = (string) ($page['title'] ?? $page['name'] ?? $page['h1'] ?? $page['seo']['title'] ?? '');
admin_header($isNew ? 'New ' . strtolower(CREATABLE_TYPES[$newType]) : 'Edit ' . $displayTitle, 'pages');
$scripts = ['https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js'];
?>
        <div class="admin-pagehead">
            <div>
                <a href="/admin/" class="small text-muted"><i class="bi bi-arrow-left"></i> All pages</a>
                <h1><?= $isNew ? 'New ' . e(strtolower(CREATABLE_TYPES[$newType])) : e($displayTitle ?: 'Untitled') ?></h1>
                <div class="text-muted small"><?= e(type_label($page['type'])) ?><?= !$isNew ? (!empty($stored['updatedAt']) ? ' · last saved ' . e(admin_date($stored['updatedAt'])) : ' · not edited in the dashboard yet') : '' ?></div>
            </div>
        </div>

<?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following, then save again:</strong>
            <ul class="mb-0"><?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
        </div>
<?php endif; ?>

        <form method="post" class="admin-edit" id="edit-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($isNew ? '' : $page['id']) ?>">
            <input type="hidden" name="new" value="<?= e($newType) ?>">

            <div class="admin-edit-main">
<?php foreach (admin_form_groups($page) as $gi => $group): ?>
                <section class="card admin-card">
                    <h2 class="admin-card-title"><?= e($group['title']) ?></h2>
<?php foreach ($group['fields'] as $field): ?>
<?php
    if ($field['type'] === 'blocks') {
        echo '<label class="form-label">' . e($field['label']) . '</label>';
        render_blocks_editor((array) ($page['blocks'] ?? []), 'f[blocks]');
    } elseif ($field['type'] === 'cta') {
        render_cta_field($page, $site);
    } else {
        render_field($field, arr_get($page, $field['key']), field_name($field['key']), $errors);
    }
?>
<?php endforeach; ?>
                </section>
<?php endforeach; ?>
            </div>

            <aside class="admin-edit-side">
                <section class="card admin-card admin-sticky">
                    <h2 class="admin-card-title">Publish</h2>
                    <label class="form-label" for="meta_status">Status</label>
                    <select class="form-select mb-3" id="meta_status" name="meta[status]">
                        <option value="draft"<?= ($page['status'] ?? '') !== 'published' ? ' selected' : '' ?>>Draft (not visible)</option>
                        <option value="published"<?= ($page['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published (live)</option>
                    </select>
<?php if ($isNew): ?>
                    <label class="form-label" for="meta_slug">Page address</label>
                    <input class="form-control mb-1<?= isset($errors['meta.slug']) ? ' is-invalid' : '' ?>" id="meta_slug" name="meta[slug]" value="<?= e($meta['slug']) ?>" placeholder="made from the title" data-slug-source>
                    <div class="form-text mb-3">Lowercase words joined by hyphens, e.g. <code>modular-kitchen-ideas</code>.</div>
<?php endif; ?>
                    <label class="form-label" for="meta_url">URL</label>
                    <input class="form-control mb-1<?= isset($errors['meta.urlPath']) ? ' is-invalid' : '' ?>" id="meta_url" name="meta[urlPath]" value="<?= e($isNew ? $meta['urlPath'] : ($page['urlPath'] ?? '')) ?>" placeholder="<?= $isNew ? 'set automatically' : '' ?>">
                    <div class="form-text mb-3"><?= $isNew ? 'Leave empty to set it automatically.' : 'Changing the URL breaks existing links and search rankings. Avoid it unless necessary.' ?></div>
                    <button class="btn btn-dark w-100 mb-2" type="submit">Save</button>
<?php if (!$isNew): ?>
                    <a class="btn btn-outline-dark w-100 mb-2" href="/admin/preview.php?id=<?= e($page['id']) ?>" target="_blank" rel="noopener">Preview saved version</a>
<?php if (($stored['status'] ?? '') === 'published'): ?>
                    <a class="btn btn-outline-secondary w-100 mb-2" href="<?= e($stored['urlPath']) ?>" target="_blank" rel="noopener">View live page <i class="bi bi-box-arrow-up-right"></i></a>
<?php endif; ?>
                    <a class="btn btn-link w-100" href="/admin/history.php?id=<?= e($page['id']) ?>">Earlier versions</a>
<?php endif; ?>
                </section>

                <section class="card admin-card">
                    <h2 class="admin-card-title">SEO check</h2>
                    <div class="serp-preview" data-serp>
                        <div class="serp-url" data-serp-url><?= e(preg_replace('#^https?://#', '', absolute_url($page['urlPath'] ?: '/'))) ?></div>
                        <div class="serp-title" data-serp-title><?= e($page['seo']['title'] ?? '') ?></div>
                        <div class="serp-desc" data-serp-desc><?= e($page['seo']['description'] ?? '') ?></div>
                    </div>
                    <div class="form-text mb-3">How the page may look in Google. Updates as you type.</div>
                    <ul class="seo-checks">
<?php foreach (seo_checks($page) as $c): ?>
                        <li class="seo-<?= e($c['level']) ?>"><i class="bi <?= $c['level'] === 'good' ? 'bi-check-circle-fill' : ($c['level'] === 'warn' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle') ?>"></i> <?= e($c['text']) ?></li>
<?php endforeach; ?>
                    </ul>
                    <div class="form-text"><?= $isNew ? 'Checks run on the saved page; save to update them.' : 'Checked when the page was opened or last saved.' ?></div>
                </section>
            </aside>
        </form>

<?php if (!$isNew && $page['type'] !== 'home'): ?>
        <form method="post" action="/admin/delete.php" class="admin-delete card admin-card" data-confirm="Move “<?= e($displayTitle) ?>” to the trash? It will disappear from the website. You can restore it from the trash.">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($page['id']) ?>">
<?php if (($stored['status'] ?? '') === 'published'): $suggested = suggested_redirect_target($stored); ?>
            <label class="form-label" for="redirect_choice">After deleting, send visitors of <code><?= e($stored['urlPath']) ?></code> to:</label>
            <select class="form-select mb-2" id="redirect_choice" name="redirect" data-redirect-choice>
                <option value="<?= e($suggested) ?>" selected><?= e($suggested) ?> (recommended)</option>
<?php if ($suggested !== '/index.html'): ?>
                <option value="/index.html">The home page</option>
<?php endif; ?>
                <option value="custom">Another address…</option>
                <option value="none">Nowhere: show "page not found"</option>
            </select>
            <input class="form-control mb-2" type="text" name="redirect_custom" placeholder="/blog.html" hidden data-redirect-custom>
            <div class="form-text mb-3">A redirect keeps visitors from old links and Google rankings instead of losing them.</div>
<?php endif; ?>
            <div><button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Move to trash</button></div>
        </form>
<?php endif; ?>

        <!-- Image picker -->
        <div class="modal fade" id="imagePicker" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">Choose an image</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <input type="search" class="form-control" style="max-width:320px" placeholder="Search by file name…" data-picker-search>
                            <label class="btn btn-dark mb-0"><i class="bi bi-upload"></i> Upload new<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-picker-upload multiple></label>
                            <span class="small text-muted align-self-center" data-picker-status></span>
                        </div>
                        <div class="picker-grid" data-picker-grid></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-dark" data-picker-done hidden>Add selected</button></div>
                </div>
            </div>
        </div>
<?php
admin_footer($scripts);
