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

$id = (int) ($_GET['id'] ?? 0);
$stmt = blog_db()->prepare('SELECT * FROM blog_users WHERE id = :id');
$stmt->execute([':id' => $id]);
$editUser = $stmt->fetch();
if (!$editUser) {
    http_response_code(404);
    exit('User not found.');
}

admin_layout_start($user, 'users', 'Edit User');
require __DIR__ . '/_form.php';
admin_layout_end();
