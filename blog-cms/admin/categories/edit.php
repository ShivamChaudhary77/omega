<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = perm_require_role(ROLE_EDITOR);

$id = (int) ($_GET['id'] ?? 0);
$category = $id ? blog_category_find($id) : null;
if (!$category) {
    http_response_code(404);
    exit('Category not found.');
}

admin_layout_start($user, 'categories', 'Edit Category');
require __DIR__ . '/_form.php';
admin_layout_end();
