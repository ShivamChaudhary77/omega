<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = auth_require_login();
if (!perm_can_manage_users($user)) {
    http_response_code(403);
    exit('You do not have permission to manage users.');
}

$users = blog_db()->query('SELECT * FROM blog_users ORDER BY created_at DESC')->fetchAll();

admin_layout_start($user, 'users', 'Users');
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div style="margin-bottom:20px;"><a href="/blog-cms/admin/users/create.php" class="admin-btn admin-btn-primary" style="width:auto;">+ New User</a></div>

<div class="admin-card">
<table class="admin-table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $e($u['name']) ?></td>
            <td><?= $e($u['email']) ?></td>
            <td><?= $e(ucwords(str_replace('_', ' ', $u['role']))) ?></td>
            <td><?= $e(ucfirst($u['status'])) ?></td>
            <td><?= $e($u['last_login_at'] ?? 'Never') ?></td>
            <td>
                <a href="/blog-cms/admin/users/edit.php?id=<?= (int) $u['id'] ?>" class="admin-btn admin-btn-sm admin-btn-outline">Edit</a>
                <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                <form method="post" action="/blog-cms/admin/users/delete.php" style="display:inline;" onsubmit="return confirm('Delete this user? Their past activity/revisions remain on record.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php admin_layout_end(); ?>
