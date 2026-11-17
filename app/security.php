<?php
/**
 * Dashboard security: one shared password, sessions, CSRF tokens and login
 * rate limiting. There are no user accounts.
 *
 * The password is stored only as a password_hash() value in
 * <content dir>/_security/admin.json (outside public_html in production).
 * Set or reset it from the server with:  php app/cli.php set-password
 */

declare(strict_types=1);

const ADMIN_SESSION_NAME = 'OMEGA_ADMIN';
const ADMIN_IDLE_SECONDS = 2 * 60 * 60;   // log out after 2 hours without activity
const LOGIN_MAX_FAILS = 5;                 // failed attempts allowed ...
const LOGIN_WINDOW_SECONDS = 15 * 60;      // ... per IP address in this window
const PASSWORD_MIN_LENGTH = 10;

/* ------------------------------------------------------------------ */
/* Password                                                            */
/* ------------------------------------------------------------------ */

function admin_password_file(): string
{
    return content_path('_security/admin.json');
}

function admin_password_is_set(): bool
{
    $data = is_file(admin_password_file()) ? json_read(admin_password_file()) : null;
    return !empty($data['passwordHash']);
}

/** Store a new dashboard password. Throws if it is too short. */
function admin_password_set(string $password): void
{
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new InvalidArgumentException('The password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
    }
    json_write(admin_password_file(), [
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'updatedAt' => date('c'),
    ]);
    @chmod(admin_password_file(), 0600);
}

function admin_password_verify(string $password): bool
{
    $data = is_file(admin_password_file()) ? json_read(admin_password_file()) : null;
    $hash = (string) ($data['passwordHash'] ?? '');
    return $hash !== '' && password_verify($password, $hash);
}

/* ------------------------------------------------------------------ */
/* Login rate limiting                                                 */
/* ------------------------------------------------------------------ */

function login_attempts_file(): string
{
    return content_path('_security/login-attempts.json');
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/** Failed attempts for this IP inside the window. */
function login_recent_failures(string $ip): array
{
    $all = is_file(login_attempts_file()) ? (json_read(login_attempts_file()) ?? []) : [];
    $cutoff = time() - LOGIN_WINDOW_SECONDS;
    return array_values(array_filter((array) ($all[$ip] ?? []), fn ($t) => (int) $t > $cutoff));
}

function login_is_locked(string $ip): bool
{
    return count(login_recent_failures($ip)) >= LOGIN_MAX_FAILS;
}

function login_record_failure(string $ip): void
{
    $all = is_file(login_attempts_file()) ? (json_read(login_attempts_file()) ?? []) : [];
    $cutoff = time() - LOGIN_WINDOW_SECONDS;
    foreach ($all as $key => $times) {
        $all[$key] = array_values(array_filter((array) $times, fn ($t) => (int) $t > $cutoff));
        if (!$all[$key]) {
            unset($all[$key]);
        }
    }
    $all[$ip][] = time();
    json_write(login_attempts_file(), $all);
}

function login_clear_failures(string $ip): void
{
    $all = is_file(login_attempts_file()) ? (json_read(login_attempts_file()) ?? []) : [];
    if (isset($all[$ip])) {
        unset($all[$ip]);
        json_write(login_attempts_file(), $all);
    }
}

/* ------------------------------------------------------------------ */
/* Sessions                                                            */
/* ------------------------------------------------------------------ */

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

/**
 * True only on the local development server (php -S). Never based on the
 * Host header or client address, which a visitor can influence.
 */
function request_is_local(): bool
{
    // OMEGA_SIMULATE_REMOTE=1 lets local rehearsals exercise the live-server path.
    return PHP_SAPI === 'cli-server' && getenv('OMEGA_SIMULATE_REMOTE') !== '1';
}

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(ADMIN_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    if (!empty($_SESSION['admin']) && (time() - (int) ($_SESSION['lastSeen'] ?? 0)) > ADMIN_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = ['type' => 'info', 'text' => 'You were logged out after 2 hours without activity.'];
    }
    if (!empty($_SESSION['admin'])) {
        $_SESSION['lastSeen'] = time();
    }
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin']);
}

/** Try the password. Returns null on success, or an error message. */
function admin_login(string $password): ?string
{
    $ip = client_ip();
    if (login_is_locked($ip)) {
        return 'Too many wrong passwords. Please wait 15 minutes and try again.';
    }
    if (!admin_password_verify($password)) {
        login_record_failure($ip);
        $left = LOGIN_MAX_FAILS - count(login_recent_failures($ip));
        return $left > 0 ? "Wrong password. $left attempt(s) left before a 15-minute lock." : 'Too many wrong passwords. Please wait 15 minutes and try again.';
    }
    login_clear_failures($ip);
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['lastSeen'] = time();
    return null;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        session_destroy();
    }
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
