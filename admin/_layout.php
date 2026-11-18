<?php
/**
 * Dashboard page frame: admin_header() before the page content,
 * admin_footer() after it. Logged-in pages get the app shell: a left
 * sidebar (navigation, "Create new", counts) and a top bar; on phones the
 * sidebar slides in from a menu button.
 */

declare(strict_types=1);

/** Cache-busting URL for a dashboard asset. */
function admin_asset(string $path): string
{
    $file = OMEGA_ROOT . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function admin_header(string $title, string $active = ''): void
{
    $flash = take_flash();
    $loggedIn = admin_is_logged_in();
    $nav = [];
    $drafts = 0;
    if ($loggedIn) {
        $summaries = page_summaries();
        $drafts = count(array_filter($summaries, fn ($p) => ($p['status'] ?? '') !== 'published'));
        $nav = [
            'pages' => ['/admin/', 'bi-file-earmark-text', 'Pages', 'Edit every page and post', count($summaries)],
            'media' => ['/admin/media.php', 'bi-images', 'Images', 'Upload and manage photos', null],
            'redirects' => ['/admin/redirects.php', 'bi-signpost-split', 'Redirects', 'Old addresses → new ones', count(redirects_all()) ?: null],
            'trash' => ['/admin/trash.php', 'bi-trash', 'Trash', 'Restore deleted pages', count(pages_in_trash()) ?: null],
            'settings' => ['/admin/settings.php', 'bi-sliders', 'Settings', 'Business details, menu, footer', null],
        ];
    }
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
    <link href="<?= e(admin_asset('/admin/assets/admin.css')) ?>" rel="stylesheet">
</head>
<body class="admin<?= $loggedIn ? ' admin-app' : ' admin-guest' ?>">
<?php if ($loggedIn): ?>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Dashboard menu">
        <a class="admin-brand" href="/admin/"><img src="/img/logo.png" alt="The Omega Group" height="22"><span>Dashboard</span></a>

        <div class="dropdown admin-create">
            <button class="btn admin-create-btn dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-plus-lg"></i> Create new
            </button>
            <ul class="dropdown-menu w-100">
<?php foreach (CREATABLE_TYPES as $type => $label): ?>
                <li><a class="dropdown-item" href="/admin/edit.php?new=<?= e($type) ?>"><i class="bi <?= e(type_icon($type)) ?> me-2"></i><?= e($label) ?></a></li>
<?php endforeach; ?>
            </ul>
        </div>

        <nav class="admin-sidenav">
            <div class="admin-sidenav-label">Manage</div>
<?php foreach ($nav as $key => [$href, $icon, $label, $hint, $count]): ?>
            <a href="<?= e($href) ?>" class="admin-sidenav-link<?= $key === $active ? ' active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= e($icon) ?>"></i>
                <span class="admin-sidenav-text"><span class="admin-sidenav-title"><?= e($label) ?></span><span class="admin-sidenav-hint"><?= e($hint) ?></span></span>
<?php if ($count !== null): ?>
                <span class="admin-sidenav-count"><?= (int) $count ?></span>
<?php endif; ?>
            </a>
<?php endforeach; ?>
<?php if ($drafts): ?>
            <a href="/admin/?status=draft" class="admin-sidenav-sub"><i class="bi bi-pencil-square"></i> <?= $drafts ?> draft<?= $drafts === 1 ? '' : 's' ?> waiting</a>
<?php endif; ?>
        </nav>

        <div class="admin-sidebar-foot">
            <a href="/" target="_blank" rel="noopener" class="admin-sidenav-link small-link"><i class="bi bi-box-arrow-up-right"></i><span class="admin-sidenav-title">View website</span></a>
            <a href="/admin/settings.php#password" class="admin-sidenav-link small-link"><i class="bi bi-key"></i><span class="admin-sidenav-title">Change password</span></a>
            <form method="post" action="/admin/logout.php"><?= csrf_field() ?><button type="submit" class="admin-sidenav-link small-link admin-logout"><i class="bi bi-box-arrow-left"></i><span class="admin-sidenav-title">Log out</span></button></form>
        </div>
    </aside>
    <div class="admin-backdrop" data-sidebar-close></div>

    <div class="admin-shell">
        <header class="admin-topbar">
            <button class="admin-menu-btn" type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open menu"><i class="bi bi-list"></i></button>
            <div class="admin-topbar-title"><?= e($title) ?></div>
            <a href="/" target="_blank" rel="noopener" class="admin-topbar-link"><i class="bi bi-globe2"></i><span> View website</span></a>
        </header>
<?php endif; ?>
    <main class="admin-main">
<?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> admin-flash alert-dismissible" role="alert">
            <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill') ?>"></i>
            <span><?= e($flash['text']) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
<?php endif; ?>
<?php
}

function admin_footer(array $scripts = []): void
{
    ?>
    </main>
<?php if (admin_is_logged_in()): ?>
    </div>
<?php endif; ?>
    <script>window.OMEGA_CSRF = <?= json_encode(csrf_token()) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($scripts as $src): ?>
    <script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
    <script src="<?= e(admin_asset('/admin/assets/admin.js')) ?>"></script>
</body>
</html>
<?php
}

/** Bootstrap icon for a page type. */
function type_icon(string $type): string
{
    return [
        'home' => 'bi-house-door', 'blog-post' => 'bi-journal-text', 'blog-index' => 'bi-journals',
        'location' => 'bi-geo-alt', 'project' => 'bi-building', 'project-index' => 'bi-grid-3x3-gap',
        'page' => 'bi-file-earmark', 'about' => 'bi-people', 'service' => 'bi-briefcase', 'contact' => 'bi-telephone',
        'faqs' => 'bi-question-circle', 'landing' => 'bi-megaphone', 'author' => 'bi-person-badge', 'not-found' => 'bi-exclamation-triangle',
    ][$type] ?? 'bi-file-earmark';
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
