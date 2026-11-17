<?php
/** Dashboard login: one shared password. */

declare(strict_types=1);

$ADMIN_PUBLIC = true;
require __DIR__ . '/_init.php';

if (!admin_password_is_set()) {
    redirect_to('/admin/setup.php');
}
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/admin/');
if (!str_starts_with($next, '/admin/') || str_contains($next, '//')) {
    $next = '/admin/';
}
if (admin_is_logged_in()) {
    redirect_to($next);
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'The form expired. Please try again.';
    } else {
        $error = admin_login((string) ($_POST['password'] ?? ''));
        if ($error === null) {
            redirect_to($next);
        }
    }
}

admin_header('Log in');
?>
        <div class="admin-login card">
            <img src="/img/logo.png" alt="The Omega Group" height="30" class="mb-4">
            <h1 class="h4 mb-3">Website dashboard</h1>
<?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <label class="form-label" for="password">Password</label>
                <input class="form-control mb-3" type="password" id="password" name="password" required autofocus autocomplete="current-password">
                <button class="btn btn-dark w-100" type="submit">Log in</button>
            </form>
        </div>
<?php
admin_footer();
