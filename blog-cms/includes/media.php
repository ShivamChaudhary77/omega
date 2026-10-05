<?php
/**
 * Media library: existing-image reuse + new upload handling.
 *
 * "Existing" images are NEVER copied, moved, renamed, or written to — this
 * file only ever reads img/. A blog_media row for an existing image is
 * created lazily (see media_get_or_create_existing()), storing its real,
 * already-live path untouched. Deleting that blog_media row later only
 * removes the metadata row — it must never delete the underlying file
 * in img/, since other pages on the site may still reference it directly.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MEDIA_ALLOWED_MIME_TO_EXT = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    // SVG is intentionally excluded — an uploaded SVG can carry inline
    // <script>/event-handler XSS payloads that render in-browser exactly
    // like the rest of the page. Per project requirement: "ONLY if safely
    // handled; otherwise disable SVG uploads." Safe SVG sanitization (strip
    // <script>, on*, external refs) is a non-trivial library-grade problem;
    // out of scope for a first version, so disabled rather than half-done.
];

const MEDIA_MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB
const MEDIA_UPLOAD_SUBDIR = '/blog-cms/uploads/';

/** Existing site image directories worth surfacing in the media browser. */
const MEDIA_EXISTING_SCAN_DIRS = ['img/projects', 'img/blog', 'img'];

/**
 * Scans existing image directories on disk (read-only) and returns their
 * root-relative URLs. Does NOT touch the database — this is purely for
 * populating the "Existing Images" tab of the media browser so an admin can
 * pick one; see media_get_or_create_existing() for what happens on select.
 */
function media_scan_existing_images(int $limit = 500): array
{
    $results = [];
    $seen = [];
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

    foreach (MEDIA_EXISTING_SCAN_DIRS as $relDir) {
        $absDir = SITE_ROOT . '/' . $relDir;
        if (!is_dir($absDir)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (count($results) >= $limit) {
                break 2;
            }
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                continue;
            }
            $relPath = '/' . ltrim(str_replace(SITE_ROOT, '', $file->getPathname()), '/');
            if (isset($seen[$relPath])) {
                continue;
            }
            $seen[$relPath] = true;
            $results[] = [
                'file_path' => $relPath,
                'file_name' => $file->getFilename(),
                'file_size_bytes' => $file->getSize(),
            ];
        }
    }

    return $results;
}

/** Look up (or lazily create) the blog_media row for an existing on-disk image path. */
function media_get_or_create_existing(string $relPath, ?int $userId = null): array
{
    $pdo = blog_db();

    $stmt = $pdo->prepare('SELECT * FROM blog_media WHERE file_path = :p LIMIT 1');
    $stmt->execute([':p' => $relPath]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    $absPath = SITE_ROOT . $relPath;
    if (!is_file($absPath)) {
        throw new InvalidArgumentException('That image no longer exists on disk.');
    }

    $size = filesize($absPath) ?: null;
    $dims = @getimagesize($absPath);

    $insert = $pdo->prepare(
        'INSERT INTO blog_media (source, file_path, file_name, mime_type, file_size_bytes, width, height, uploaded_by)
         VALUES (\'existing\', :path, :name, :mime, :size, :w, :h, :uploaded_by)'
    );
    $insert->execute([
        ':path' => $relPath,
        ':name' => basename($relPath),
        ':mime' => $dims['mime'] ?? null,
        ':size' => $size,
        ':w' => $dims[0] ?? null,
        ':h' => $dims[1] ?? null,
        ':uploaded_by' => $userId,
    ]);

    $stmt->execute([':p' => $relPath]);
    return $stmt->fetch();
}

/**
 * @return array{ok: bool, media?: array, error?: string}
 */
function media_handle_upload(array $file, int $userId): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (error code ' . $file['error'] . ').'];
    }
    if ($file['size'] > MEDIA_MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 5MB.'];
    }

    // Validate the REAL MIME type by inspecting file content, never the
    // client-supplied filename/extension or Content-Type header (both are
    // trivially spoofable).
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(MEDIA_ALLOWED_MIME_TO_EXT[$realMime])) {
        return ['ok' => false, 'error' => 'Unsupported file type. Allowed: JPG, PNG, WebP.'];
    }

    // Double-check it's a genuine, decodable image (blocks polyglot files
    // that pass the MIME sniff but aren't valid images).
    $dims = @getimagesize($file['tmp_name']);
    if ($dims === false) {
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }

    $ext = MEDIA_ALLOWED_MIME_TO_EXT[$realMime];
    // Safe, unguessable filename — never trust the client's original filename
    // (path traversal / null-byte / executable-disguised-as-image risk).
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $subdir = date('Y/m');
    $destDir = SITE_ROOT . MEDIA_UPLOAD_SUBDIR . $subdir;
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return ['ok' => false, 'error' => 'Could not create upload directory.'];
    }

    $destPath = $destDir . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
    }
    chmod($destPath, 0644); // never leave an uploaded file executable

    $relPath = MEDIA_UPLOAD_SUBDIR . $subdir . '/' . $safeName;

    $pdo = blog_db();
    $insert = $pdo->prepare(
        'INSERT INTO blog_media (source, file_path, file_name, mime_type, file_size_bytes, width, height, uploaded_by)
         VALUES (\'upload\', :path, :name, :mime, :size, :w, :h, :uploaded_by)'
    );
    $insert->execute([
        ':path' => $relPath,
        ':name' => $safeName,
        ':mime' => $realMime,
        ':size' => $file['size'],
        ':w' => $dims[0],
        ':h' => $dims[1],
        ':uploaded_by' => $userId,
    ]);

    $mediaId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM blog_media WHERE id = :id');
    $stmt->execute([':id' => $mediaId]);

    return ['ok' => true, 'media' => $stmt->fetch()];
}

function media_find(int $id): ?array
{
    $stmt = blog_db()->prepare('SELECT * FROM blog_media WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function media_list(int $page, int $perPage, ?string $search = null): array
{
    $pdo = blog_db();
    $where = '';
    $params = [];
    if ($search) {
        $where = 'WHERE file_name LIKE :q OR alt_text LIKE :q';
        $params[':q'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_media $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $perPage = max(1, min(100, $perPage));
    $offset = max(0, ($page - 1) * $perPage);
    $stmt = $pdo->prepare("SELECT * FROM blog_media $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/**
 * Deletes a blog_media row. Only deletes the underlying FILE when
 * source='upload' (a file this system itself created under
 * blog-cms/uploads/) — an 'existing' row's file is never touched, since it
 * may be referenced directly by other, non-CMS pages on the site.
 */
function media_delete(int $id): bool
{
    $media = media_find($id);
    if (!$media) {
        return false;
    }

    if ($media['source'] === 'upload') {
        $absPath = SITE_ROOT . $media['file_path'];
        // Path-traversal guard: the resolved real path must still be inside
        // the uploads directory before we ever unlink() it.
        $uploadsRoot = realpath(SITE_ROOT . MEDIA_UPLOAD_SUBDIR);
        $real = realpath($absPath);
        if ($real !== false && $uploadsRoot !== false && strpos($real, $uploadsRoot) === 0 && is_file($real)) {
            @unlink($real);
        }
    }

    $stmt = blog_db()->prepare('DELETE FROM blog_media WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}
