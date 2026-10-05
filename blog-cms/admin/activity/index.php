<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = auth_require_login();
if (!perm_can_view_activity_logs($user)) {
    http_response_code(403);
    exit('You do not have permission to view activity logs.');
}

$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'entity_type' => $_GET['entity_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = activity_log_list($filters, $page, 40);
$totalPages = (int) ceil($result['total'] / 40);

$users = blog_db()->query('SELECT id, name FROM blog_users ORDER BY name')->fetchAll();
$actions = blog_db()->query('SELECT DISTINCT action FROM blog_activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

admin_layout_start($user, 'activity', 'Activity Logs');
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="admin-card" style="margin-bottom:20px;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
        <div class="admin-form-row" style="margin:0;">
            <label>User</label>
            <select name="user_id"><option value="">All</option>
                <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (string) $filters['user_id'] === (string) $u['id'] ? 'selected' : '' ?>><?= $e($u['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-row" style="margin:0;">
            <label>Action</label>
            <select name="action"><option value="">All</option>
                <?php foreach ($actions as $a): ?><option value="<?= $e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= $e(ucwords(str_replace('_', ' ', $a))) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-row" style="margin:0;">
            <label>From</label>
            <input type="date" name="date_from" value="<?= $e($filters['date_from']) ?>">
        </div>
        <div class="admin-form-row" style="margin:0;">
            <label>To</label>
            <input type="date" name="date_to" value="<?= $e($filters['date_to']) ?>">
        </div>
        <button type="submit" class="admin-btn admin-btn-outline">Filter</button>
    </form>
</div>

<div class="admin-card">
<table class="admin-table">
    <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($result['rows'] as $a): ?>
        <tr>
            <td><?= $e($a['created_at']) ?></td>
            <td><?= $e($a['user_name'] ?? 'System') ?></td>
            <td><?= $e(ucwords(str_replace('_', ' ', $a['action']))) ?></td>
            <td><?= $e($a['description']) ?></td>
            <td><?= $e($a['ip_address'] ?? '—') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$result['rows']): ?><tr><td colspan="5">No activity found.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php if ($totalPages > 1): ?>
<div style="margin-top:16px; display:flex; gap:6px;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-outline' ?>" style="margin-top:0;"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
</div>
<?php admin_layout_end(); ?>
