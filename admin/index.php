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
$all = page_summaries();
$countType = fn (string $t) => count(array_filter($all, fn ($p) => ($p['type'] ?? '') === $t));
$chips = [
    ['All pages', 'bi-collection', [], count($all)],
    ['Blog posts', 'bi-journal-text', ['type' => 'blog-post'], $countType('blog-post')],
    ['Location pages', 'bi-geo-alt', ['type' => 'location'], $countType('location')],
    ['Projects', 'bi-building', ['type' => 'project'], $countType('project')],
    ['Drafts', 'bi-pencil-square', ['status' => 'draft'], count(array_filter($all, fn ($p) => ($p['status'] ?? '') !== 'published'))],
];
$isActiveChip = fn (array $params) => ($params['type'] ?? '') === $typeFilter && ($params['status'] ?? '') === $statusFilter && ($params === [] ? $query === '' : true);
?>
        <div class="admin-pagehead">
            <div>
                <h1>Pages</h1>
                <p class="admin-lead">Everything on your website. Click a title to edit it, or <strong>View</strong> to see it live.</p>
            </div>
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> New</button>
                <ul class="dropdown-menu dropdown-menu-end">
<?php foreach (CREATABLE_TYPES as $type => $label): ?>
                    <li><a class="dropdown-item" href="/admin/edit.php?new=<?= e($type) ?>"><i class="bi <?= e(type_icon($type)) ?> me-2"></i><?= e($label) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="admin-chips" role="navigation" aria-label="Quick filters">
<?php foreach ($chips as [$label, $icon, $params, $n]): ?>
            <a class="admin-chip<?= $isActiveChip($params) ? ' active' : '' ?>" href="/admin/<?= $params ? '?' . e(http_build_query($params)) : '' ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?> <span><?= (int) $n ?></span></a>
<?php endforeach; ?>
        </div>

        <form class="admin-filters" method="get" role="search">
            <div class="admin-search">
                <i class="bi bi-search"></i>
                <input class="form-control" type="search" name="q" value="<?= e($query) ?>" placeholder="Search by title or address…" data-live-search="#pages-table" autocomplete="off">
            </div>
            <select class="form-select" name="type" onchange="this.form.submit()" aria-label="Page type">
                <option value="">All types</option>
<?php foreach ($types as $type): ?>
                <option value="<?= e($type) ?>"<?= $type === $typeFilter ? ' selected' : '' ?>><?= e(type_label($type)) ?></option>
<?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()" aria-label="Status">
                <option value="">Published &amp; drafts</option>
                <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Published</option>
                <option value="draft"<?= $statusFilter === 'draft' ? ' selected' : '' ?>>Drafts</option>
            </select>
            <button class="btn btn-outline-dark" type="submit">Search</button>
        </form>

        <div class="admin-results-line"><span data-live-count><?= count($rows) ?></span> of <?= count($rows) ?> shown<?= ($query !== '' || $typeFilter !== '' || $statusFilter !== '') ? ' · <a href="/admin/">clear filters</a>' : '' ?></div>

        <div class="admin-table-wrap">
            <table class="table admin-table align-middle" id="pages-table">
                <thead><tr><th>Title</th><th class="d-none d-md-table-cell">Type</th><th class="d-none d-sm-table-cell">Status</th><th class="d-none d-lg-table-cell">Date</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
<?php foreach ($rows as $p): $live = ($p['status'] ?? '') === 'published'; ?>
                    <tr data-search="<?= e(mb_strtolower($p['displayTitle'] . ' ' . $p['urlPath'] . ' ' . ($p['category'] ?? '') . ' ' . type_label($p['type']))) ?>">
                        <td>
                            <div class="admin-row-title">
                                <span class="admin-type-icon" title="<?= e(type_label($p['type'])) ?>"><i class="bi <?= e(type_icon($p['type'])) ?>"></i></span>
                                <div>
                                    <a class="admin-title" href="/admin/edit.php?id=<?= e($p['id']) ?>"><?= e($p['displayTitle']) ?></a>
                                    <div class="admin-url"><?= e($p['urlPath']) ?></div>
                                    <span class="admin-status d-sm-none mt-1 <?= $live ? 'is-live' : 'is-draft' ?>"><?= $live ? 'Published' : 'Draft' ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell"><?= e(type_label($p['type'])) ?><?= !empty($p['category']) ? '<div class="admin-url admin-category">' . e($p['category']) . '</div>' : '' ?></td>
                        <td class="d-none d-sm-table-cell"><span class="admin-status <?= $live ? 'is-live' : 'is-draft' ?>"><?= $live ? 'Published' : 'Draft' ?></span></td>
                        <td class="text-nowrap small d-none d-lg-table-cell"><?= e($p['type'] === 'blog-post' ? format_date($p['publishDate'] ?? null) : '') ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-dark admin-action" href="/admin/edit.php?id=<?= e($p['id']) ?>"><i class="bi bi-pencil"></i><span> Edit</span></a>
                            <a class="btn btn-sm btn-outline-secondary admin-action" href="<?= $live ? e($p['urlPath']) : '/admin/preview.php?id=' . e($p['id']) ?>" target="_blank" rel="noopener" title="<?= $live ? 'Open the live page' : 'Preview this draft' ?>"><i class="bi bi-eye"></i><span> View</span></a>
                        </td>
                    </tr>
<?php endforeach; ?>
                    <tr class="admin-empty-row"<?= $rows ? ' hidden' : '' ?>><td colspan="5"><i class="bi bi-search"></i><br>No pages match. Try another word or <a href="/admin/">show all pages</a>.</td></tr>
                </tbody>
            </table>
        </div>
<?php
admin_footer();
