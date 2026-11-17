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

$uploads = array_values(array_filter(media_list(), fn ($i) => $i['uploaded']));
admin_header('Images', 'media');
?>
        <div class="admin-pagehead">
            <h1>Images</h1>
            <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <input class="form-control" type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-dark text-nowrap" type="submit"><i class="bi bi-upload"></i> Upload</button>
            </form>
        </div>
        <p class="text-muted">JPG, PNG or WebP, up to 5 MB. Large photos are resized to 1600 px wide and saved as WebP automatically. To use an image, pick it from a page's image field. The site's original photos (in /img) can be picked too but aren't listed here.</p>
        <div class="media-grid">
<?php foreach ($uploads as $img): ?>
            <figure class="media-item">
                <a href="<?= e($img['url']) ?>" target="_blank" rel="noopener"><img src="<?= e($img['url']) ?>" alt="" loading="lazy"></a>
                <figcaption>
                    <input class="form-control form-control-sm" value="<?= e($img['url']) ?>" readonly onclick="this.select()">
                    <form method="post" data-confirm="Delete this image? Pages that use it will show a broken image."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="url" value="<?= e($img['url']) ?>"><button class="btn btn-sm btn-link text-danger p-0">Delete</button></form>
                </figcaption>
            </figure>
<?php endforeach; ?>
<?php if (!$uploads): ?>
            <p class="text-muted">No uploaded images yet.</p>
<?php endif; ?>
        </div>
<?php
admin_footer();
