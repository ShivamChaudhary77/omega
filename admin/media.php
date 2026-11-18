<?php
/**
 * Images.
 *   GET                 page listing uploaded images (upload / delete)
 *   GET  ?format=json   every image the picker can offer
 *   POST action=upload  upload (form, or JSON response with ?format=json;
 *                       ?format=tinymce answers in the editor's format)
 *   POST action=delete  delete an uploaded image
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$format = (string) ($_GET['format'] ?? '');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrf_valid(is_string($token) ? $token : null)) {
        $format !== '' ? json_out(['error' => 'Your session expired. Reload the page and try again.'], 403) : require_post_with_csrf();
    }
    $action = (string) ($_POST['action'] ?? 'upload');
    if ($action === 'delete') {
        $ok = media_delete((string) ($_POST['url'] ?? ''));
        flash($ok ? 'success' : 'danger', $ok ? 'Image deleted.' : 'That image could not be deleted.');
        redirect_to('/admin/media.php');
    }
    $file = $_FILES['file'] ?? null;
    $result = is_array($file) ? media_upload($file) : ['ok' => false, 'error' => 'No file was received.'];
    if ($format === 'tinymce') {
        $result['ok'] ? json_out(['location' => $result['url']]) : json_out(['error' => ['message' => $result['error']]], 400);
    }
    if ($format === 'json') {
        $result['ok'] ? json_out($result) : json_out($result, 400);
    }
    flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Uploaded: ' . $result['url'] : $result['error']);
    redirect_to('/admin/media.php');
}

if ($format === 'json') {
    json_out(['images' => media_list()]);
}

/** Readable group name for an image path: "Blog: featured images", "Project: Joyville"... */
function image_group(string $url): string
{
    if (str_starts_with($url, '/uploads/')) {
        return 'Uploaded in the dashboard';
    }
    $parts = explode('/', trim($url, '/'));
    if (($parts[1] ?? '') === 'blog') {
        return ($parts[2] ?? '') === 'featured' ? 'Blog: featured images' : 'Blog: images inside articles';
    }
    if (($parts[1] ?? '') === 'projects' && count($parts) > 3) {
        return 'Project: ' . ucwords(str_replace('-', ' ', $parts[2]));
    }
    return 'General site images';
}

/** Every /img or /uploads path mentioned by a page, the settings, the templates or the stylesheets. */
function images_in_use(): array
{
    $text = json_encode(array_values(pages_all()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . json_encode(site(), JSON_UNESCAPED_SLASHES);
    $templates = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(OMEGA_TEMPLATES, FilesystemIterator::SKIP_DOTS));
    foreach ($templates as $tpl) {
        $text .= (string) file_get_contents($tpl->getPathname());
    }
    foreach (glob(OMEGA_ROOT . '/css/*.css') ?: [] as $css) {
        $text .= str_replace(['../img/', '"img/', "'img/", '(img/'], ['/img/', '"/img/', "'/img/", '(/img/'], (string) file_get_contents($css));
    }
    preg_match_all('#/(?:img|uploads)/[A-Za-z0-9_./%() -]+?\.(?:webp|jpe?g|png|svg|gif)#i', $text, $m);
    return array_fill_keys(array_map('rawurldecode', $m[0]), true);
}

$all = media_list();
$used = images_in_use();
$uploads = array_values(array_filter($all, fn ($i) => $i['uploaded']));
$siteImages = array_values(array_filter($all, fn ($i) => !$i['uploaded']));
$view = (string) ($_GET['view'] ?? ($uploads ? 'uploads' : 'site'));
$view = in_array($view, ['uploads', 'site'], true) ? $view : 'site';
$list = $view === 'uploads' ? $uploads : $siteImages;
usort($list, fn ($a, $b) => strcmp(image_group($a['url']), image_group($b['url'])) ?: strnatcasecmp($a['name'], $b['name']));
$groups = array_count_values(array_map(fn ($i) => image_group($i['url']), $list));
ksort($groups);
$unused = count(array_filter($list, fn ($i) => !isset($used[$i['url']])));

admin_header('Images', 'media');
?>
        <div class="admin-pagehead">
            <div>
                <h1>Images</h1>
                <p class="admin-lead">Every photo on the website. Click a photo to open it full size, or <strong>Copy</strong> its address to use it anywhere.</p>
            </div>
            <form method="post" enctype="multipart/form-data" class="admin-upload">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <input class="form-control" type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-dark text-nowrap" type="submit"><i class="bi bi-upload"></i> Upload</button>
            </form>
        </div>

        <div class="admin-tabs admin-tabs-links">
            <a class="admin-tab<?= $view === 'site' ? ' active' : '' ?>" href="/admin/media.php?view=site"><i class="bi bi-images"></i> Website images <span class="admin-tab-count"><?= count($siteImages) ?></span></a>
            <a class="admin-tab<?= $view === 'uploads' ? ' active' : '' ?>" href="/admin/media.php?view=uploads"><i class="bi bi-cloud-arrow-up"></i> Uploaded in the dashboard <span class="admin-tab-count"><?= count($uploads) ?></span></a>
        </div>

<?php if ($view === 'site'): ?>
        <p class="form-text mt-0 mb-3"><i class="bi bi-info-circle"></i> These photos came with the website. They can be used on any page (pick them with <strong>Choose…</strong> in an image field) but not deleted here, because they're part of the website's files. JPG, PNG and WebP files you upload go to the other tab.</p>
<?php else: ?>
        <p class="form-text mt-0 mb-3"><i class="bi bi-info-circle"></i> JPG, PNG or WebP, up to 5 MB. Large photos are resized to 1600 px wide and saved as WebP automatically.</p>
<?php endif; ?>

<?php if ($list): ?>
        <div class="admin-filters">
            <div class="admin-search">
                <i class="bi bi-search"></i>
                <input class="form-control" type="search" placeholder="Search by file name…" data-media-search autocomplete="off">
            </div>
            <select class="form-select" data-media-group aria-label="Folder">
                <option value="">All folders (<?= count($list) ?>)</option>
<?php foreach ($groups as $group => $n): ?>
                <option value="<?= e($group) ?>"><?= e($group) ?> (<?= $n ?>)</option>
<?php endforeach; ?>
            </select>
            <select class="form-select" data-media-usage aria-label="Usage">
                <option value="">In use and not in use</option>
                <option value="used">In use on the website</option>
                <option value="unused">Not used anywhere (<?= $unused ?>)</option>
            </select>
        </div>
        <div class="admin-results-line"><span data-media-count><?= count($list) ?></span> of <?= count($list) ?> images</div>
<?php endif; ?>

        <div class="media-grid" data-media-grid>
<?php foreach ($list as $img):
    $file = $img['uploaded'] ? uploads_dir() . substr($img['url'], 8) : OMEGA_ROOT . $img['url'];
    $size = @getimagesize($file);
    $kb = is_file($file) ? (int) round(filesize($file) / 1024) : 0;
    $inUse = isset($used[$img['url']]);
    $group = image_group($img['url']);
?>
            <figure class="media-item" data-name="<?= e(mb_strtolower($img['name'] . ' ' . $group)) ?>" data-group="<?= e($group) ?>" data-used="<?= $inUse ? 'used' : 'unused' ?>">
                <a href="<?= e($img['url']) ?>" target="_blank" rel="noopener" class="media-thumb" title="Open full size"><img src="<?= e($img['url']) ?>" alt="" loading="lazy"></a>
                <figcaption>
                    <div class="media-name" title="<?= e($img['url']) ?>"><?= e($img['name']) ?></div>
                    <div class="media-meta"><?= $size ? $size[0] . ' × ' . $size[1] . ' · ' : '' ?><?= $kb ?> KB</div>
                    <div class="media-group"><?= e($group) ?></div>
                    <div class="media-actions">
                        <span class="media-badge <?= $inUse ? 'is-used' : 'is-unused' ?>"><?= $inUse ? 'In use' : 'Not used' ?></span>
                        <button type="button" class="btn btn-sm btn-outline-dark" data-copy="<?= e($img['url']) ?>"><i class="bi bi-clipboard"></i> Copy</button>
<?php if ($img['uploaded']): ?>
                        <form method="post" data-confirm="Delete this image?<?= $inUse ? ' It is used on the website: those pages will show a broken image.' : '' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="url" value="<?= e($img['url']) ?>"><button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
<?php endif; ?>
                    </div>
                </figcaption>
            </figure>
<?php endforeach; ?>
        </div>
<?php if (!$list): ?>
        <div class="admin-empty-box"><i class="bi bi-cloud-arrow-up"></i><p>No images uploaded in the dashboard yet. Use <strong>Upload</strong> above, or <strong>Choose… → Upload new</strong> inside any image field.</p></div>
<?php endif; ?>
        <div class="admin-empty-box" data-media-empty hidden><i class="bi bi-search"></i><p>No images match.</p></div>
<?php
admin_footer();
