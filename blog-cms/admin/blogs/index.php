<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/csrf.php';

$user = auth_require_login();

$filters = [
    'search' => trim((string) ($_GET['q'] ?? '')),
    'status' => $_GET['status'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'author_id' => $_GET['author_id'] ?? '',
    'sort' => $_GET['sort'] ?? 'updated_at',
    'dir' => $_GET['dir'] ?? 'desc',
];
if ($user['role'] === ROLE_AUTHOR) {
    $filters['restrict_author_id'] = $user['id'];
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$result = blog_post_admin_list($filters, $page, $perPage);
$totalPages = (int) ceil($result['total'] / $perPage);

$categories = blog_categories_all(false);
$authors = blog_db()->query('SELECT id, name FROM blog_users ORDER BY name')->fetchAll();

admin_layout_start($user, 'blogs', 'Blogs');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qs = fn(array $overrides) => '?' . http_build_query(array_merge($_GET, $overrides));
?>

<div class="admin-card" style="margin-bottom:20px;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
        <div class="admin-form-row" style="margin:0; min-width:200px;">
            <label>Search</label>
            <input type="text" name="q" value="<?= $e($filters['search']) ?>" placeholder="Search by title…">
        </div>
        <div class="admin-form-row" style="margin:0;">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <?php foreach (['draft', 'published', 'scheduled', 'archived'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-row" style="margin:0;">
            <label>Category</label>
            <select name="category_id">
                <option value="">All</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($user['role'] !== ROLE_AUTHOR): ?>
        <div class="admin-form-row" style="margin:0;">
            <label>Author</label>
            <select name="author_id">
                <option value="">All</option>
                <?php foreach ($authors as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= (string) $filters['author_id'] === (string) $a['id'] ? 'selected' : '' ?>><?= $e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="admin-btn admin-btn-outline">Filter</button>
        <a href="/blog-cms/admin/blogs/create.php" class="admin-btn admin-btn-primary" style="margin-top:0;">+ New Blog</a>
    </form>
</div>

<div class="admin-card">
<table class="admin-table">
    <thead><tr>
        <th>Title</th><th>Status</th><th>Author</th><th>Category</th>
        <th>Created</th><th>Updated</th><th>Published</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($result['rows'] as $p): ?>
        <tr>
            <td><?= $e($p['title']) ?></td>
            <td><span class="admin-badge admin-badge-<?= $e($p['status']) ?>"><?= $e(ucfirst($p['status'])) ?></span></td>
            <td><?= $e($p['author_name'] ?? '—') ?></td>
            <td><?= $e($p['category_name'] ?? '—') ?></td>
            <td><?= $e(substr($p['created_at'], 0, 16)) ?></td>
            <td><?= $e(substr($p['updated_at'], 0, 16)) ?></td>
            <td><?= $p['published_at'] ? $e(substr($p['published_at'], 0, 16)) : '—' ?></td>
            <td style="white-space:nowrap;">
                <a href="/blog-cms/admin/blogs/edit.php?id=<?= (int) $p['id'] ?>" class="admin-btn admin-btn-sm admin-btn-outline">Edit</a>
                <a href="/blog-cms/admin/blogs/preview.php?id=<?= (int) $p['id'] ?>" class="admin-btn admin-btn-sm admin-btn-outline" target="_blank">Preview</a>
                <?php if ($p['status'] === 'published'): ?>
                    <a href="https://theomegagroup.in/<?= $e($p['category_slug']) ?>/<?= $e($p['slug']) ?>/" class="admin-btn admin-btn-sm admin-btn-outline" target="_blank">View</a>
                <?php endif; ?>
                <a href="/blog-cms/admin/blogs/revisions.php?id=<?= (int) $p['id'] ?>" class="admin-btn admin-btn-sm admin-btn-outline">Revisions</a>
                <?php if (perm_can_manage_post($user, $p)): ?>
                <form method="post" action="/blog-cms/admin/blogs/duplicate.php" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-outline">Duplicate</button>
                </form>
                <form method="post" action="/blog-cms/admin/blogs/delete.php" style="display:inline;" onsubmit="return confirm('Delete this blog permanently? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$result['rows']): ?><tr><td colspan="8">No blogs found.</td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<div style="margin-top:16px; display:flex; gap:6px;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="<?= $e($qs(['page' => $i])) ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-outline' ?>" style="margin-top:0;"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
</div>

<?php admin_layout_end(); ?>
