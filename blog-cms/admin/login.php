<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';

auth_start_session();

if (auth_current_user() !== null) {
    header('Location: /blog-cms/admin/index.php');
    exit;
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = v_clean_str($_POST['email'] ?? '', 190);
    $password = (string) ($_POST['password'] ?? '');

    $result = auth_attempt_login($email, $password);
    if ($result['ok']) {
        $redirect = $_GET['redirect'] ?? '/blog-cms/admin/index.php';
        // Only ever redirect back into our own admin area — never an
        // open redirect to an arbitrary external URL from the query string.
        if (!is_string($redirect) || strpos($redirect, '/blog-cms/admin/') !== 0) {
            $redirect = '/blog-cms/admin/index.php';
        }
        header('Location: ' . $redirect);
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Login — The Omega Group Blog CMS</title>
<meta name="robots" content="noindex, nofollow">
<link href="/blog-cms/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-auth-body">
<div class="admin-auth-card">
    <h1>Blog CMS Login</h1>
    <?php if (isset($_GET['setup']) && $_GET['setup'] === 'done'): ?>
        <div class="admin-alert admin-alert-success">Admin account created. Sign in below.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="admin-btn admin-btn-primary">Log In</button>
    </form>
</div>
</body>
</html>
