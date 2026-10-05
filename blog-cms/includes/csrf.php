<?php
/**
 * CSRF token helpers. One token per session, regenerated on login (see
 * auth.php's login()) — not per-form — which is enough protection for an
 * internal admin tool without forcing every open tab to re-render on submit.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('csrf_token() called before session started.');
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(?string $submittedToken): bool
{
    if (empty($_SESSION['csrf_token']) || $submittedToken === null || $submittedToken === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/** Call at the top of every state-changing admin POST handler. Exits on failure. */
function csrf_require(): void
{
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_verify($token)) {
        http_response_code(403);
        exit('Security check failed. Please go back, refresh the page, and try again.');
    }
}
