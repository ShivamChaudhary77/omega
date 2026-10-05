<?php
/**
 * Session-based auth for the Blog CMS admin. Completely separate session
 * namespace/cookie from anything the public site uses (the public site has
 * no login of its own — this is the first authenticated area on the site).
 *
 * Brute-force protection: blog_users.failed_login_count / locked_until.
 * After 5 consecutive failures, the account is locked for 15 minutes.
 * Counter resets to 0 on a successful login.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

const AUTH_MAX_FAILED_ATTEMPTS = 5;
const AUTH_LOCKOUT_MINUTES = 15;

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('omega_blog_admin_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/blog-cms/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function auth_current_user(): ?array
{
    auth_start_session();
    return $_SESSION['blog_admin_user'] ?? null;
}

function auth_require_login(): array
{
    $user = auth_current_user();
    if ($user === null) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: /blog-cms/admin/login.php?redirect=' . $redirect);
        exit;
    }

    // Enforce "must change password" server-side on every request, not just
    // at login — a super admin can flip this flag on an existing session at
    // any time (e.g. after resetting someone's temporary password) and it
    // must take effect immediately, not just on their next fresh login.
    $onChangePasswordPage = ($_SERVER['SCRIPT_NAME'] ?? '') === '/blog-cms/admin/change-password.php';
    if ($user['must_change_password'] && !$onChangePasswordPage) {
        header('Location: /blog-cms/admin/change-password.php');
        exit;
    }

    return $user;
}

/**
 * @return array{ok: bool, error?: string}
 */
function auth_attempt_login(string $email, string $password): array
{
    $pdo = blog_db();

    $stmt = $pdo->prepare('SELECT * FROM blog_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Same generic message as a wrong password — never reveal whether
        // the email exists.
        return ['ok' => false, 'error' => 'Incorrect email or password.'];
    }

    if ($user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'This account has been disabled. Contact your administrator.'];
    }

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Try again in a few minutes.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $failedCount = (int) $user['failed_login_count'] + 1;
        $lockedUntil = null;
        if ($failedCount >= AUTH_MAX_FAILED_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + AUTH_LOCKOUT_MINUTES * 60);
            $failedCount = 0; // reset counter once locked, so the lock has a clean window
        }
        $upd = $pdo->prepare('UPDATE blog_users SET failed_login_count = :c, locked_until = :l WHERE id = :id');
        $upd->execute([':c' => $failedCount, ':l' => $lockedUntil, ':id' => $user['id']]);

        return ['ok' => false, 'error' => 'Incorrect email or password.'];
    }

    // Success: reset failure counter, record login, regenerate session ID
    // (prevents session fixation), and store only the fields the app needs
    // in $_SESSION — never the password hash.
    $upd = $pdo->prepare(
        'UPDATE blog_users
            SET failed_login_count = 0, locked_until = NULL,
                last_login_at = NOW(), last_login_ip = :ip
          WHERE id = :id'
    );
    $upd->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? null, ':id' => $user['id']]);

    auth_start_session();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']); // force a fresh CSRF token for the new session

    $_SESSION['blog_admin_user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'must_change_password' => (bool) $user['must_change_password'],
    ];

    log_activity((int) $user['id'], 'login', 'blog_user', (int) $user['id'], $user['name'] . ' logged in');

    return ['ok' => true];
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
