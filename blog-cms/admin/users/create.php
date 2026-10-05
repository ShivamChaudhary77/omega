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
$editUser = null;

admin_layout_start($user, 'users', 'New User');
require __DIR__ . '/_form.php';
admin_layout_end();
