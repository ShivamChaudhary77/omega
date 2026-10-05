<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

auth_start_session();
$user = auth_current_user();
if ($user !== null) {
    log_activity($user['id'], 'logout', 'blog_user', $user['id'], $user['name'] . ' logged out');
}
auth_logout();

header('Location: /blog-cms/admin/login.php');
exit;
