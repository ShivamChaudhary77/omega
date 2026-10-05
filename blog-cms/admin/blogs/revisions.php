<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = auth_require_login();

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? blog_post_find($id) : null;
if (!$post) {
    http_response_code(404);
    exit('Blog not found.');
}
if (!perm_can_manage_post($user, $post)) {
    http_response_code(403);
    exit('You do not have permission to view this blog\'s revisions.');
}

$viewId = isset($_GET['view']) ? (int) $_GET['view'] : null;
$viewing = $viewId ? revision_find($viewId) : null;
$revisions = revision_list_for_post($id);

admin_layout_start($user, 'blogs', 'Revisions: ' . $post['title']);
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<a href="/blog-cms/admin/blogs/edit.php?id=<?= $id ?>" class="admin-btn admin-btn-outline" style="margin-bottom:16px;">&larr; Back to Editor</a>

<div class="admin-form-grid">
    <div class="admin-card">
        <h3>History</h3>
        <table class="admin-table">
            <thead><tr><th>#</th><th>Changed By</th><th>When</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($revisions as $r): ?>
                <tr>
                    <td>#<?= (int) $r['id'] ?></td>
                    <td><?= $e($r['created_by_name'] ?? 'Unknown') ?></td>
                    <td><?= $e($r['created_at']) ?></td>
                    <td>
                        <a href="?id=<?= $id ?>&view=<?= (int) $r['id'] ?>" class="admin-btn admin-btn-sm admin-btn-outline">View</a>
                        <form method="post" action="/blog-cms/admin/blogs/restore.php" style="display:inline;" onsubmit="return confirm('Restore this revision? The current version will be saved as a new revision first, so nothing is lost.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="revision_id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">Restore</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$revisions): ?><tr><td colspan="4">No revisions yet — revisions are created automatically every time this blog is edited.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="admin-card">
        <h3><?= $viewing ? ('Revision #' . (int) $viewing['id']) : 'Select a revision to view' ?></h3>
        <?php if ($viewing): ?>
            <p><strong>Title:</strong> <?= $e($viewing['title']) ?></p>
            <p><strong>Status at the time:</strong> <?= $e($viewing['status']) ?></p>
            <p><strong>Meta Title:</strong> <?= $e($viewing['meta_title']) ?></p>
            <p><strong>Meta Description:</strong> <?= $e($viewing['meta_description']) ?></p>
            <hr>
            <div style="max-height:400px; overflow:auto; border:1px solid #E8DFD0; padding:12px; border-radius:6px;">
                <?= $viewing['content'] ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php admin_layout_end(); ?>
