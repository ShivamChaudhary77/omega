<?php
/** Dashboard home: every page, with search and type filter. */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$query = trim((string) ($_GET['q'] ?? ''));
$typeFilter = (string) ($_GET['type'] ?? '');
$statusFilter = (string) ($_GET['status'] ?? '');

$rows = [];
foreach (page_summaries() as $id => $p) {
    $title = (string) ($p['title'] ?? $p['name'] ?? $p['h1'] ?? '') ?: (string) $p['seoTitle'];
    if ($typeFilter !== '' && ($p['type'] ?? '') !== $typeFilter) {
        continue;
    }
    if ($statusFilter !== '' && ($p['status'] ?? '') !== $statusFilter) {
        continue;
    }
    if ($query !== '' && stripos($title . ' ' . $p['urlPath'] . ' ' . $id, $query) === false) {
        continue;
    }
    $rows[] = $p + ['displayTitle' => $title];
}
usort($rows, function ($a, $b) {
    $order = ['home' => 0, 'about' => 1, 'service' => 2, 'project-index' => 3, 'blog-index' => 4, 'contact' => 5];
    $oa = $order[$a['type']] ?? ($a['type'] === 'blog-post' ? 50 : 20);
    $ob = $order[$b['type']] ?? ($b['type'] === 'blog-post' ? 50 : 20);
    if ($oa !== $ob) {
        return $oa <=> $ob;
    }
    if ($a['type'] === 'blog-post') {
        return strcmp((string) ($b['publishDate'] ?? ''), (string) ($a['publishDate'] ?? ''));
    }
    return strcmp($a['displayTitle'], $b['displayTitle']);
});
$types = array_unique(array_map(fn ($p) => $p['type'], page_summaries()));
sort($types);

admin_header('Pages', 'pages');
?>
        <div class="admin-pagehead">
            <h1>Pages <span class="text-muted fs-6"><?= count($rows) ?></span></h1>
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> New</button>
                <ul class="dropdown-menu dropdown-menu-end">
<?php foreach (CREATABLE_TYPES as $type => $label): ?>
                    <li><a class="dropdown-item" href="/admin/edit.php?new=<?= e($type) ?>"><?= e($label) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
        </div>

        <form class="admin-filters" method="get">
            <input class="form-control" type="search" name="q" value="<?= e($query) ?>" placeholder="Search title or URL…">
            <select class="form-select" name="type" onchange="this.form.submit()">
                <option value="">All types</option>
<?php foreach ($types as $type): ?>
                <option value="<?= e($type) ?>"<?= $type === $typeFilter ? ' selected' : '' ?>><?= e(type_label($type)) ?></option>
<?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">Published &amp; drafts</option>
                <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Published</option>
                <option value="draft"<?= $statusFilter === 'draft' ? ' selected' : '' ?>>Drafts</option>
            </select>
            <button class="btn btn-outline-dark" type="submit">Search</button>
        </form>

        <div class="admin-table-wrap">
            <table class="table admin-table align-middle">
                <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
<?php foreach ($rows as $p): ?>
                    <tr>
                        <td>
                            <a class="admin-title" href="/admin/edit.php?id=<?= e($p['id']) ?>"><?= e($p['displayTitle']) ?></a>
                            <div class="admin-url"><?= e($p['urlPath']) ?></div>
                        </td>
                        <td><?= e(type_label($p['type'])) ?><?= !empty($p['category']) ? '<div class="admin-url">' . e($p['category']) . '</div>' : '' ?></td>
                        <td><span class="badge <?= ($p['status'] ?? '') === 'published' ? 'bg-success' : 'bg-secondary' ?>"><?= ($p['status'] ?? '') === 'published' ? 'Published' : 'Draft' ?></span></td>
                        <td class="text-nowrap small"><?= e($p['type'] === 'blog-post' ? format_date($p['publishDate'] ?? null) : '') ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-dark" href="/admin/edit.php?id=<?= e($p['id']) ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= ($p['status'] ?? '') === 'published' ? e($p['urlPath']) : '/admin/preview.php?id=' . e($p['id']) ?>" target="_blank" rel="noopener">View</a>
                        </td>
                    </tr>
<?php endforeach; ?>
<?php if (!$rows): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No pages match.</td></tr>
<?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
admin_footer();
