<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/seo.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../../includes/admin_blog_form.php';

$user = auth_require_login();

$categories = blog_categories_all(false);
$authors = $user['role'] === ROLE_AUTHOR
    ? [['id' => $user['id'], 'name' => $user['name']]]
    : blog_db()->query('SELECT id, name FROM blog_users ORDER BY name')->fetchAll();

admin_layout_start($user, 'blogs', 'New Blog');
render_blog_form($user, null, $categories, $authors);
admin_layout_end();
