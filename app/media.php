<?php
/**
 * Image uploads for the dashboard.
 *
 * Uploads go to /uploads/YYYY/MM/ inside the website folder (excluded from
 * deploys, so a git deploy never removes them). Every upload is checked by
 * its real content (not its name), resized to at most 1600px wide and saved
 * as WebP when the server supports it.
 */

declare(strict_types=1);

const MEDIA_MAX_BYTES = 5 * 1024 * 1024;
const MEDIA_MAX_WIDTH = 1600;
const MEDIA_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function uploads_dir(): string
{
    return rtrim((string) (config('uploads_dir') ?: OMEGA_ROOT . '/uploads'), '/');
}

/**
 * Save an uploaded image ($_FILES entry). Returns
 * ['ok' => true, 'url' => '/uploads/...', 'width' => , 'height' => ] or ['ok' => false, 'error' => ...].
 */
function media_upload(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        return ['ok' => false, 'error' => $tooBig ? 'The image is too large (maximum 5 MB).' : 'The upload failed. Please try again.'];
    }
    if ($file['size'] > MEDIA_MAX_BYTES) {
        return ['ok' => false, 'error' => 'The image is too large (maximum 5 MB).'];
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(MEDIA_TYPES[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG and WebP images can be uploaded.'];
    }
    $size = @getimagesize($file['tmp_name']);
    if ($size === false) {
        return ['ok' => false, 'error' => 'That file is not a valid image.'];
    }

    $base = slugify(pathinfo((string) ($file['name'] ?? 'image'), PATHINFO_FILENAME)) ?: 'image';
    $base = substr($base, 0, 60) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    media_protect_uploads_dir();
    $subdir = date('Y/m');
    $dir = uploads_dir() . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create the uploads folder.'];
    }

    [$width, $height] = $size;
    $image = media_load($file['tmp_name'], $mime);
    $canWebp = $image && function_exists('imagewebp');
    if ($image && ($width > MEDIA_MAX_WIDTH || $canWebp)) {
        if ($width > MEDIA_MAX_WIDTH) {
            $newHeight = (int) round($height * MEDIA_MAX_WIDTH / $width);
            $resized = imagecreatetruecolor(MEDIA_MAX_WIDTH, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, MEDIA_MAX_WIDTH, $newHeight, $width, $height);
            $image = $resized;
            [$width, $height] = [MEDIA_MAX_WIDTH, $newHeight];
        }
        $ext = $canWebp ? 'webp' : MEDIA_TYPES[$mime];
        $path = "$dir/$base.$ext";
        $saved = match ($ext) {
            'webp' => imagewebp($image, $path, 82),
            'png' => imagepng($image, $path, 6),
            default => imagejpeg($image, $path, 85),
        };
        if (!$saved) {
            return ['ok' => false, 'error' => 'Could not save the image.'];
        }
    } else {
        $ext = MEDIA_TYPES[$mime];
        $path = "$dir/$base.$ext";
        if (!move_uploaded_file($file['tmp_name'], $path) && !rename($file['tmp_name'], $path)) {
            return ['ok' => false, 'error' => 'Could not save the image.'];
        }
    }
    @chmod($path, 0644);
    return ['ok' => true, 'url' => "/uploads/$subdir/$base.$ext", 'width' => $width, 'height' => $height];
}

/** Make sure nothing in the uploads folder can ever run as a script. */
function media_protect_uploads_dir(): void
{
    $dir = uploads_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $htaccess = "$dir/.htaccess";
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "# Uploaded images only: never execute anything here.\nOptions -Indexes -ExecCGI\n"
            . "<FilesMatch \"\\.(php\\d?|phtml|phar|pl|py|cgi|sh|shtml|html?|svg)$\">\n    Require all denied\n</FilesMatch>\n");
    }
}

/** GD image from a file, or null if GD can't read it. */
function media_load(string $file, string $mime)
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file),
        'image/png' => @imagecreatefrompng($file),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
        default => false,
    };
    return $image ?: null;
}

/**
 * Images the picker offers: uploads first (newest first), then the site's
 * existing image folders. Each: ['url' => , 'name' => , 'uploaded' => bool].
 */
function media_list(): array
{
    $list = [];
    $collect = function (string $dir, string $urlBase, bool $uploaded) use (&$list) {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $found = [];
        foreach ($it as $f) {
            if (preg_match('/\.(webp|jpe?g|png|svg)$/i', $f->getFilename())) {
                $found[] = [$f->getMTime(), $urlBase . str_replace('\\', '/', substr($f->getPathname(), strlen($dir)))];
            }
        }
        usort($found, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($found as [, $url]) {
            $list[] = ['url' => $url, 'name' => basename($url), 'uploaded' => $uploaded];
        }
    };
    $collect(uploads_dir(), '/uploads', true);
    $collect(OMEGA_ROOT . '/img', '/img', false);
    return $list;
}

/** Width/height of a site image by URL path, or null. */
function media_dimensions(string $url): ?array
{
    if (!preg_match('#^/(uploads|img)/[A-Za-z0-9_./ -]+$#', $url) || str_contains($url, '..')) {
        return null;
    }
    $file = str_starts_with($url, '/uploads/') ? uploads_dir() . substr($url, 8) : OMEGA_ROOT . $url;
    $size = is_file($file) ? @getimagesize($file) : false;
    return $size ? ['width' => $size[0], 'height' => $size[1]] : null;
}

/** Delete an uploaded image (only files inside /uploads). */
function media_delete(string $url): bool
{
    if (!preg_match('#^/uploads/[A-Za-z0-9_./-]+$#', $url) || str_contains($url, '..')) {
        return false;
    }
    $file = uploads_dir() . substr($url, 8);
    return is_file($file) && unlink($file);
}
