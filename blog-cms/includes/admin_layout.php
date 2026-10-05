<?php
/**
 * Shared admin chrome (sidebar + topbar). Every admin/*.php page (except
 * login/setup) calls admin_layout_start($user, 'active-nav-key') right
 * after its own PHP logic, then admin_layout_end() at the very bottom.
 */

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

function admin_layout_start(array $user, string $active, string $title): void
{
    $navItems = [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/blog-cms/admin/index.php'],
        'blogs' => ['label' => 'Blogs', 'href' => '/blog-cms/admin/blogs/index.php'],
        'categories' => ['label' => 'Categories', 'href' => '/blog-cms/admin/categories/index.php'],
        'media' => ['label' => 'Media', 'href' => '/blog-cms/admin/media/index.php'],
        'users' => ['label' => 'Users', 'href' => '/blog-cms/admin/users/index.php'],
        'activity' => ['label' => 'Activity Logs', 'href' => '/blog-cms/admin/activity/index.php'],
        'settings' => ['label' => 'Settings', 'href' => '/blog-cms/admin/settings/index.php'],
    ];
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($title) ?> — Blog CMS</title>
<meta name="robots" content="noindex, nofollow">
<link href="/blog-cms/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-logo">The Omega Group<br>Blog CMS</div>
        <nav>
            <?php foreach ($navItems as $key => $item): ?>
                <?php if ($key === 'users' && !perm_can_manage_users($user)) continue; ?>
                <?php if ($key === 'activity' && !perm_can_view_activity_logs($user)) continue; ?>
                <a href="<?= $e($item['href']) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= $e($item['label']) ?></a>
            <?php endforeach; ?>
            <a href="/blog-cms/admin/logout.php">Logout</a>
        </nav>
    </aside>
    <div class="admin-main">
        <div class="admin-topbar">
            <strong><?= $e($title) ?></strong>
            <span class="admin-user"><?= $e($user['name']) ?> · <?= $e(ucwords(str_replace('_', ' ', $user['role']))) ?></span>
        </div>
        <div class="admin-content">
    <?php
}

function admin_layout_end(): void
{
    ?>
        </div>
    </div>
</div>
</body>
</html>
    <?php
}
