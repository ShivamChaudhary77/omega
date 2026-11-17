<?php
/** Earlier versions of a page, with one-click restore. */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$id = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$page = page_load($id);
if (!$page) {
    flash('danger', 'That page does not exist.');
    redirect_to('/admin/');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_with_csrf();
    try {
        page_restore_version($id, (string) ($_POST['version'] ?? ''));
        flash('success', 'The earlier version was restored. (The version you replaced is kept in the history too.)');
        redirect_to('/admin/edit.php?id=' . rawurlencode($id));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect_to('/admin/history.php?id=' . rawurlencode($id));
    }
}

$versions = page_history($id);
$title = (string) ($page['title'] ?? $page['name'] ?? $page['h1'] ?? $page['seo']['title'] ?? $id);
admin_header('History: ' . $title, 'pages');
?>
        <div class="admin-pagehead">
            <div>
                <a href="/admin/edit.php?id=<?= e($id) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Back to the editor</a>
                <h1>Earlier versions</h1>
                <div class="text-muted small"><?= e($title) ?> · current version saved <?= e(admin_date($page['updatedAt'] ?? null)) ?></div>
            </div>
        </div>
        <p class="text-muted">Every save keeps the previous version (the last <?= (int) config('history_keep') ?> are kept).</p>
        <div class="admin-table-wrap">
            <table class="table admin-table align-middle">
                <thead><tr><th>Version saved</th><th>SEO title at that time</th><th class="text-end"></th></tr></thead>
                <tbody>
<?php foreach ($versions as $v): ?>
                    <tr>
                        <td><?= e(admin_date($v['updatedAt'] ?? null)) ?></td>
                        <td class="small"><?= e($v['title'] ?? '') ?></td>
                        <td class="text-end">
                            <form method="post" data-confirm="Replace the current page with this earlier version?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="version" value="<?= e($v['version']) ?>"><button class="btn btn-sm btn-outline-dark">Restore this version</button></form>
                        </td>
                    </tr>
<?php endforeach; ?>
<?php if (!$versions): ?>
                    <tr><td colspan="3" class="text-center text-muted py-5">No earlier versions yet. They appear after the first time you save this page.</td></tr>
<?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
admin_footer();
