<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = perm_require_role(ROLE_EDITOR);
$category = null;

admin_layout_start($user, 'categories', 'New Category');
require __DIR__ . '/_form.php';
admin_layout_end();
