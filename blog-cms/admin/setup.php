<?php
/**
 * One-time first-admin creation. Refuses to run if ANY row already exists
 * in blog_users — not just "have I run before", checked fresh on every
 * request, so this file can be safely left on the server afterward without
 * becoming a standing way to create a second super admin. See
 * database/README.md "Creating the first admin user".
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

auth_start_session();

$pdo = blog_db();
$existingCount = (int) $pdo->query('SELECT COUNT(*) FROM blog_users')->fetchColumn();
if ($existingCount > 0) {
    http_response_code(403);
    exit('Setup has already been completed. Delete or rename blog-cms/admin/setup.php if you no longer need it.');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $name = v_clean_str($_POST['name'] ?? '', 150);
    $email = v_clean_str($_POST['email'] ?? '', 190);
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }
    if (!v_is_valid_email($email)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (mb_strlen($password) < 10) {
        $errors['password'] = 'Password must be at least 10 characters.';
    }
    if ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // must_change_password is always forced to 1 here, regardless of
        // how this password was chosen — this form has no reliable way to
        // know whether the password was typed fresh and privately or
        // pasted/shared somewhere first (e.g. in a chat, a ticket, over
        // Slack), so treating every setup password as temporary is the
        // only safe default. Matches the project requirement: "The first
        // login should require changing the temporary password if a
        // temporary password is used."
        $stmt = $pdo->prepare(
            'INSERT INTO blog_users (name, email, password_hash, role, status, must_change_password)
             VALUES (:name, :email, :hash, \'super_admin\', \'active\', 1)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        log_activity($userId, 'user_created', 'blog_user', $userId, "First admin account created ($email)");

        header('Location: /blog-cms/admin/login.php?setup=done');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Blog CMS — First-Time Setup</title>
<meta name="robots" content="noindex, nofollow">
<link href="/blog-cms/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-auth-body">
<div class="admin-auth-card">
    <h1>Create the First Admin Account</h1>
    <p class="admin-auth-hint">This page only works once — it refuses to run once any admin account exists. You'll be asked to set a new password immediately after your first login.</p>
    <?php foreach ($errors as $err): ?>
        <div class="admin-alert admin-alert-error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>

        <label for="password">Password (min. 10 characters)</label>
        <input type="password" id="password" name="password" minlength="10" required>

        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="10" required>

        <button type="submit" class="admin-btn admin-btn-primary">Create Admin Account</button>
    </form>
</div>
</body>
</html>
