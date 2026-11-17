<?php
/**
 * One-time password setup. Works only while no password exists, and only
 * on a local computer or with the setup token from app/config.local.php
 * ('admin_setup_token' => 'long-random-string'), opened as
 * /admin/setup.php?token=... . Alternatively run: php app/cli.php set-password
 */

declare(strict_types=1);

$ADMIN_PUBLIC = true;
require __DIR__ . '/_init.php';

if (admin_password_is_set()) {
    redirect_to('/admin/login.php');
}
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$configured = (string) (config('admin_setup_token') ?? '');
$allowed = request_is_local() || ($configured !== '' && strlen($configured) >= 20 && hash_equals($configured, $token));

$error = null;
if ($allowed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'The form expired. Please try again.';
    } elseif ($password !== (string) ($_POST['confirm'] ?? '')) {
        $error = 'The two passwords do not match.';
    } else {
        try {
            admin_password_set($password);
            admin_login($password);
            flash('success', 'Password saved. Welcome to the dashboard.');
            redirect_to('/admin/');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

admin_header('Set up');
?>
        <div class="admin-login card">
            <img src="/img/logo.png" alt="The Omega Group" height="30" class="mb-4">
            <h1 class="h4 mb-3">Set the dashboard password</h1>
<?php if (!$allowed): ?>
            <p>The dashboard password has not been set yet. Ask your developer to run <code>php app/cli.php set-password</code> on the server, or to give you the setup link.</p>
<?php else: ?>
<?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
            <p class="text-muted small">Choose a password of at least <?= PASSWORD_MIN_LENGTH ?> characters and keep it in a password manager. There is only one password: anyone who has it can edit the website.</p>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label class="form-label" for="password">New password</label>
                <input class="form-control mb-3" type="password" id="password" name="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password">
                <label class="form-label" for="confirm">Repeat the password</label>
                <input class="form-control mb-3" type="password" id="confirm" name="confirm" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password">
                <button class="btn btn-dark w-100" type="submit">Save password</button>
            </form>
<?php endif; ?>
        </div>
<?php
admin_footer();
