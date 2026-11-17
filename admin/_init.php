<?php
/**
 * Shared start-up for every dashboard page: loads the app, sends security
 * headers, enforces HTTPS on the live site, starts the session and (unless
 * the page is public, like login) requires the dashboard password.
 *
 * The dashboard stays switched off until the site is set up: the content
 * folder must exist and a password must be set (php app/cli.php set-password,
 * or the one-time setup page with the setup token).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once OMEGA_APP . '/security.php';
require_once OMEGA_APP . '/sanitize.php';
require_once OMEGA_APP . '/media.php';
require_once OMEGA_APP . '/admin_fields.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

if (!request_is_https() && !request_is_local()) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'theomegagroup.in') . ($_SERVER['REQUEST_URI'] ?? '/admin/'), true, 301);
    exit;
}

/** True once the content folder exists (Phase 6 creates it on the live server). */
function dashboard_available(): bool
{
    return is_file(content_path('site.json'));
}

if (!dashboard_available()) {
    http_response_code(503);
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Dashboard</title>'
        . '<p style="font-family:sans-serif;padding:2rem">The dashboard is not set up on this server yet.</p>';
    exit;
}

admin_session_start();

/** Send the browser elsewhere and stop. */
function redirect_to(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** One-time message shown on the next page. */
function flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Reject a POST without a valid CSRF token. */
function require_post_with_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        flash('danger', 'That action could not be completed (the form expired). Please try again.');
        redirect_to('/admin/');
    }
}

if (empty($ADMIN_PUBLIC)) {
    if (!admin_password_is_set()) {
        redirect_to('/admin/setup.php');
    }
    if (!admin_is_logged_in()) {
        redirect_to('/admin/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    }
}

require __DIR__ . '/_layout.php';
