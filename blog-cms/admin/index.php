<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/blog_functions.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$user = auth_require_login();
$stats = blog_dashboard_stats();

$recentActivity = activity_log_list([], 1, 8)['rows'];
$recentPosts = blog_post_admin_list(
    array_filter(['restrict_author_id' => $user['role'] === ROLE_AUTHOR ? $user['id'] : null]),
    1,
    8
)['rows'];

admin_layout_start($user, 'dashboard', 'Dashboard');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="admin-stat-grid">
    <div class="admin-stat-card"><div class="admin-stat-value"><?= $stats['total_posts'] ?></div><div class="admin-stat-label">Total Blogs</div></div>
    <div class="admin-stat-card"><div class="admin-stat-value"><?= $stats['published'] ?></div><div class="admin-stat-label">Published</div></div>
    <div class="admin-stat-card"><div class="admin-stat-value"><?= $stats['drafts'] ?></div><div class="admin-stat-label">Drafts</div></div>
    <div class="admin-stat-card"><div class="admin-stat-value"><?= $stats['scheduled'] ?></div><div class="admin-stat-label">Scheduled</div></div>
    <div class="admin-stat-card"><div class="admin-stat-value"><?= $stats['categories'] ?></div><div class="admin-stat-label">Categories</div></div>
    <div class="admin-stat-card"><div class="admin-stat-value"><?= $stats['media'] ?></div><div class="admin-stat-label">Media Files</div></div>
    <?php if (perm_can_manage_users($user)): ?>
    <div class="admin-stat-card"><div class="admin-stat-value"><?= $stats['users'] ?></div><div class="admin-stat-label">Users</div></div>
    <?php endif; ?>
</div>

<div class="admin-form-grid">
    <div class="admin-card">
        <h3>Recently Updated Blogs</h3>
        <table class="admin-table">
            <thead><tr><th>Title</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            <?php foreach ($recentPosts as $p): ?>
                <tr>
                    <td><a href="/blog-cms/admin/blogs/edit.php?id=<?= (int) $p['id'] ?>"><?= $e($p['title']) ?></a></td>
                    <td><span class="admin-badge admin-badge-<?= $e($p['status']) ?>"><?= $e(ucfirst($p['status'])) ?></span></td>
                    <td><?= $e($p['updated_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentPosts): ?><tr><td colspan="3">No blogs yet. <a href="/blog-cms/admin/blogs/create.php">Create your first one</a>.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="admin-card">
        <h3>Recent Activity</h3>
        <table class="admin-table">
            <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead>
            <tbody>
            <?php foreach ($recentActivity as $a): ?>
                <tr>
                    <td><?= $e($a['created_at']) ?></td>
                    <td><?= $e($a['user_name'] ?? 'System') ?></td>
                    <td><?= $e($a['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentActivity): ?><tr><td colspan="3">No activity yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php admin_layout_end(); ?>
