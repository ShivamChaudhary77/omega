<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = perm_require_role(ROLE_EDITOR);

$search = trim((string) ($_GET['q'] ?? ''));
$categories = blog_categories_all(false);
if ($search !== '') {
    $categories = array_values(array_filter($categories, fn($c) => stripos($c['name'], $search) !== false));
}

// Post counts, for the orphan-prevention warning shown before delete.
$pdo = blog_db();
$counts = [];
foreach ($pdo->query('SELECT category_id, COUNT(*) c, SUM(status = "published") pub FROM blog_posts GROUP BY category_id') as $row) {
    $counts[(int) $row['category_id']] = ['total' => (int) $row['c'], 'published' => (int) $row['pub']];
}

admin_layout_start($user, 'categories', 'Categories');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="admin-card" style="margin-bottom:20px;">
    <form method="get" style="display:flex; gap:10px;">
        <input type="text" name="q" value="<?= $e($search) ?>" placeholder="Search categories…" style="flex:1; padding:8px; border:1px solid #E8DFD0; border-radius:6px;">
        <button type="submit" class="admin-btn admin-btn-outline">Search</button>
        <a href="/blog-cms/admin/categories/create.php" class="admin-btn admin-btn-primary" style="margin-top:0;">+ New Category</a>
    </form>
</div>

<div class="admin-card">
<table class="admin-table">
    <thead><tr><th>Name</th><th>Slug</th><th>Status</th><th>Posts</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): $count = $counts[(int) $c['id']] ?? ['total' => 0, 'published' => 0]; ?>
        <tr>
            <td><?= $e($c['name']) ?></td>
            <td>/<?= $e($c['slug']) ?>/</td>
            <td><?= $e(ucfirst($c['status'])) ?></td>
            <td><?= $count['total'] ?> (<?= $count['published'] ?> published)</td>
            <td>
                <a href="/blog-cms/admin/categories/edit.php?id=<?= (int) $c['id'] ?>" class="admin-btn admin-btn-sm admin-btn-outline">Edit</a>
                <form method="post" action="/blog-cms/admin/categories/delete.php" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" <?= $count['published'] > 0 ? 'disabled title="Cannot delete: has published posts"' : '' ?>>Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$categories): ?><tr><td colspan="5">No categories found.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php admin_layout_end(); ?>
