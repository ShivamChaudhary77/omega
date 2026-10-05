<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity_log.php';

$user = auth_require_login(); // safe: this IS change-password.php, so the redirect check above doesn't loop

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    $pdo = blog_db();
    $stmt = $pdo->prepare('SELECT password_hash FROM blog_users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, (string) $hash)) {
        $errors[] = 'Current password is incorrect.';
    }
    if (mb_strlen($new) < 10) {
        $errors[] = 'New password must be at least 10 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        $upd = $pdo->prepare('UPDATE blog_users SET password_hash = :hash, must_change_password = 0 WHERE id = :id');
        $upd->execute([':hash' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['id']]);
        log_activity($user['id'], 'password_changed', 'blog_user', $user['id'], $user['name'] . ' changed their password');

        // Refresh the session copy so the redirect loop in auth_require_login() stops immediately.
        $_SESSION['blog_admin_user']['must_change_password'] = false;
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Change Password — Blog CMS</title>
<meta name="robots" content="noindex, nofollow">
<link href="/blog-cms/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-auth-body">
<div class="admin-auth-card">
    <h1>Change Your Password</h1>
    <p class="admin-auth-hint">You must set a new password before continuing.</p>
    <?php foreach ($errors as $err): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">Password updated. <a href="/blog-cms/admin/index.php">Continue to the dashboard</a>.</div>
    <?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>
        <label for="new_password">New Password (min. 10 characters)</label>
        <input type="password" id="new_password" name="new_password" minlength="10" required>
        <label for="new_password_confirm">Confirm New Password</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" minlength="10" required>
        <button type="submit" class="admin-btn admin-btn-primary">Update Password</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
