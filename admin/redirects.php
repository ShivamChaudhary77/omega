<?php
/** Redirects created by the dashboard: list, add, remove. */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$errors = [];
$form = ['from' => '', 'to' => '', 'code' => '301'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_with_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        redirect_remove((string) ($_POST['from'] ?? ''));
        flash('success', 'Redirect removed.');
        redirect_to('/admin/redirects.php');
    }
    $form = ['from' => trim((string) ($_POST['from'] ?? '')), 'to' => trim((string) ($_POST['to'] ?? '')), 'code' => (string) ($_POST['code'] ?? '301')];
    $from = path_normalize($form['from']);
    $code = $form['code'] === '410' ? 410 : 301;
    if ($from === null || !str_starts_with($form['from'], '/')) {
        $errors[] = 'The old address must start with "/", e.g. /old-page/.';
    } elseif ($from === '/' || page_find_by_path($from, true)) {
        $errors[] = 'A page exists at ' . $from . ', so it cannot redirect. Change or delete that page instead.';
    }
    if ($code === 301 && !preg_match('#^(/|https://)#', $form['to'])) {
        $errors[] = 'The new address must start with "/" (a page on this site) or "https://".';
    }
    if ($code === 301 && $from !== null && $form['to'] === $from) {
        $errors[] = 'An address cannot redirect to itself.';
    }
    if (!$errors && $from !== null) {
        redirect_set($from, $code === 410 ? '/' : $form['to'], $code);
        flash('success', $code === 410 ? "$from now tells Google the page is gone for good." : "$from now redirects to {$form['to']}.");
        redirect_to('/admin/redirects.php');
    }
}

$redirects = redirects_all();
$static = count(static_redirect_sources());
admin_header('Redirects', 'redirects');
?>
        <div class="admin-pagehead"><h1>Redirects</h1></div>
        <p class="text-muted">A redirect sends visitors (and Google) from an old address to a new one, so old links keep working. The dashboard adds one automatically when you change a published page's URL or delete a published page. The site also has <?= $static ?> older redirects built in, which aren't listed here.</p>
<?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
        <section class="card admin-card">
            <h2 class="admin-card-title">Add a redirect</h2>
            <form method="post" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-md-4"><label class="form-label" for="r_from">Old address</label><input class="form-control" id="r_from" name="from" value="<?= e($form['from']) ?>" placeholder="/old-page/" required></div>
                <div class="col-md-4"><label class="form-label" for="r_to">New address</label><input class="form-control" id="r_to" name="to" value="<?= e($form['to']) ?>" placeholder="/new-page/"></div>
                <div class="col-md-2"><label class="form-label" for="r_code">Type</label>
                    <select class="form-select" id="r_code" name="code">
                        <option value="301"<?= $form['code'] !== '410' ? ' selected' : '' ?>>Moved (301)</option>
                        <option value="410"<?= $form['code'] === '410' ? ' selected' : '' ?>>Gone for good (410)</option>
                    </select></div>
                <div class="col-md-2"><button class="btn btn-dark w-100" type="submit">Add</button></div>
            </form>
        </section>
        <div class="admin-table-wrap">
            <table class="table admin-table align-middle">
                <thead><tr><th>Old address</th><th>Goes to</th><th>Added</th><th></th></tr></thead>
                <tbody>
<?php foreach ($redirects as $from => $rule): ?>
                    <tr>
                        <td><code><?= e($from) ?></code></td>
                        <td><?= (int) ($rule['code'] ?? 301) === 410 ? '<span class="badge bg-secondary">Gone (410)</span>' : '<code>' . e($rule['to'] ?? '') . '</code>' ?></td>
                        <td class="small"><?= e(admin_date($rule['createdAt'] ?? null)) ?></td>
                        <td class="text-end"><form method="post" data-confirm="Remove this redirect? Visitors of <?= e($from) ?> will see 'page not found'."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="from" value="<?= e($from) ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td>
                    </tr>
<?php endforeach; ?>
<?php if (!$redirects): ?>
                    <tr><td colspan="4" class="text-center text-muted py-5">No dashboard redirects yet.</td></tr>
<?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
admin_footer();
