<?php
/**
 * Dashboard page frame: admin_header() before the page content,
 * admin_footer() after it.
 */

declare(strict_types=1);

function admin_header(string $title, string $active = ''): void
{
    $flash = take_flash();
    $nav = [
        'pages' => ['/admin/', 'Pages'],
        'media' => ['/admin/media.php', 'Images'],
        'trash' => ['/admin/trash.php', 'Trash'],
        'settings' => ['/admin/settings.php', 'Settings'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · Omega Dashboard</title>
    <link href="/img/favicon.svg" rel="icon" type="image/svg+xml">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/admin/assets/admin.css" rel="stylesheet">
</head>
<body class="admin">
<?php if (admin_is_logged_in()): ?>
    <nav class="admin-topbar">
        <a class="admin-brand" href="/admin/"><img src="/img/logo.png" alt="The Omega Group" height="22"> <span>Dashboard</span></a>
        <div class="admin-nav">
<?php foreach ($nav as $key => [$href, $label]): ?>
            <a href="<?= e($href) ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= e($label) ?></a>
<?php endforeach; ?>
            <a href="/" target="_blank" rel="noopener">View site <i class="bi bi-box-arrow-up-right"></i></a>
            <form method="post" action="/admin/logout.php" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-link admin-logout">Log out</button></form>
        </div>
    </nav>
<?php endif; ?>
    <main class="admin-main">
<?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['text']) ?></div>
<?php endif; ?>
<?php
}

function admin_footer(array $scripts = []): void
{
    ?>
    </main>
    <script>window.OMEGA_CSRF = <?= json_encode(csrf_token()) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($scripts as $src): ?>
    <script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
    <script src="/admin/assets/admin.js"></script>
</body>
</html>
<?php
}

/** "17 Nov 2026, 14:05" from an ISO date. */
function admin_date(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($iso))->format('j M Y, H:i');
    } catch (Exception $e) {
        return '—';
    }
}
