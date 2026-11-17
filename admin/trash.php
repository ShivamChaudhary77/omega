<?php
/** Deleted pages: restore them, or delete them for good. */

declare(strict_types=1);

require __DIR__ . '/_init.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_with_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'restore') {
            $page = page_restore_from_trash($id);
            flash('success', 'Restored "' . ($page['title'] ?? $page['name'] ?? $page['h1'] ?? $id) . '". It is back at ' . $page['urlPath'] . ' with its previous status (any redirect from that address was removed).');
            redirect_to('/admin/edit.php?id=' . rawurlencode($id));
        } elseif ($action === 'purge' && page_id_is_valid($id) && is_file(content_path("_trash/$id.json"))) {
            unlink(content_path("_trash/$id.json"));
            flash('success', 'Deleted for good: ' . $id . '.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect_to('/admin/trash.php');
}

$items = [];
foreach (pages_in_trash() as $id) {
    $p = json_read(content_path("_trash/$id.json")) ?? [];
    $items[] = $p + ['id' => $id];
}
usort($items, fn ($a, $b) => strcmp((string) ($b['deletedAt'] ?? ''), (string) ($a['deletedAt'] ?? '')));

admin_header('Trash', 'trash');
?>
        <div class="admin-pagehead"><h1>Trash</h1></div>
        <p class="text-muted">Deleted pages stay here until you delete them for good.</p>
        <div class="admin-table-wrap">
            <table class="table admin-table align-middle">
                <thead><tr><th>Title</th><th>Type</th><th>Deleted</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
<?php foreach ($items as $p): ?>
                    <tr>
                        <td><strong><?= e($p['title'] ?? $p['name'] ?? $p['h1'] ?? $p['seo']['title'] ?? $p['id']) ?></strong><div class="admin-url"><?= e($p['urlPath'] ?? '') ?></div></td>
                        <td><?= e(type_label((string) ($p['type'] ?? ''))) ?></td>
                        <td class="small"><?= e(admin_date($p['deletedAt'] ?? null)) ?></td>
                        <td class="text-end text-nowrap">
                            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['id']) ?>"><input type="hidden" name="action" value="restore"><button class="btn btn-sm btn-dark">Restore</button></form>
                            <form method="post" class="d-inline" data-confirm="Delete “<?= e($p['title'] ?? $p['id']) ?>” for good? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['id']) ?>"><input type="hidden" name="action" value="purge"><button class="btn btn-sm btn-outline-danger">Delete for good</button></form>
                        </td>
                    </tr>
<?php endforeach; ?>
<?php if (!$items): ?>
                    <tr><td colspan="4" class="text-center text-muted py-5">The trash is empty.</td></tr>
<?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
admin_footer();
