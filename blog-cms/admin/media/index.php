<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = auth_require_login();

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = media_list($page, 24, $search !== '' ? $search : null);
$totalPages = (int) ceil($result['total'] / 24);

admin_layout_start($user, 'media', 'Media Library');
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="admin-card" style="margin-bottom:20px;">
    <form method="get" style="display:flex; gap:10px;">
        <input type="text" name="q" value="<?= $e($search) ?>" placeholder="Search uploaded/registered media…" style="flex:1; padding:8px; border:1px solid #E8DFD0; border-radius:6px;">
        <button type="submit" class="admin-btn admin-btn-outline">Search</button>
    </form>
    <p class="admin-hint" style="margin-top:10px; margin-bottom:0;">This grid shows media already registered here (uploaded, or an existing site image you've previously selected in a blog editor). To browse and pick from ALL existing site images (including ones never used in a blog yet), use "Choose Featured Image" / the image button inside a blog's editor.</p>
</div>

<div class="admin-card">
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:16px;">
<?php foreach ($result['rows'] as $m): ?>
    <div>
        <img src="<?= $e($m['file_path']) ?>" style="width:100%; height:120px; object-fit:cover; border-radius:6px; border:1px solid #E8DFD0;">
        <div style="font-size:.75rem; margin-top:6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= $e($m['file_name']) ?>"><?= $e($m['file_name']) ?></div>
        <div style="font-size:.7rem; color:#8a8271;"><?= $e(ucfirst($m['source'])) ?> · <?= $m['file_size_bytes'] ? round($m['file_size_bytes'] / 1024) . 'KB' : '—' ?></div>
        <form method="post" action="/blog-cms/admin/media/delete.php" onsubmit="return confirm('Remove this from the media library? <?= $m['source'] === 'upload' ? 'This deletes the uploaded file permanently.' : 'The original site image file is NOT deleted, only this library entry.' ?>');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" style="width:100%; margin-top:4px;">Remove</button>
        </form>
    </div>
<?php endforeach; ?>
<?php if (!$result['rows']): ?><p>No media registered yet.</p><?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div style="margin-top:16px; display:flex; gap:6px;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-outline' ?>" style="margin-top:0;"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
</div>
<?php admin_layout_end(); ?>
