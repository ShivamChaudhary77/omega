<?php
/** Log out (POST only, with CSRF token). */

declare(strict_types=1);

$ADMIN_PUBLIC = true;
require __DIR__ . '/_init.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    admin_logout();
    admin_session_start();
    flash('success', 'You have been logged out.');
}
redirect_to('/admin/login.php');
