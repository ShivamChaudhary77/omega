<?php
/**
 * Revision history. See database/005_create_blog_revisions.sql for the
 * schema and the "restore never deletes history" guarantee this file
 * implements.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

const REVISION_COLUMNS = [
    'title', 'slug', 'excerpt', 'content', 'featured_image_id', 'category_id', 'author_id', 'status',
    'meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'robots_index', 'robots_follow',
    'og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description', 'twitter_image',
];

/** Snapshot a post's CURRENT row into blog_revisions. Call this BEFORE writing the new data. */
function revision_create(int $postId, array $currentPostRow, int $createdBy): int
{
    $pdo = blog_db();
    $cols = REVISION_COLUMNS;
    $placeholders = array_map(fn($c) => ':' . $c, $cols);

    $stmt = $pdo->prepare(
        'INSERT INTO blog_revisions (post_id, ' . implode(', ', $cols) . ', created_by)
         VALUES (:post_id, ' . implode(', ', $placeholders) . ', :created_by)'
    );

    $params = [':post_id' => $postId, ':created_by' => $createdBy];
    foreach ($cols as $c) {
        $params[':' . $c] = $currentPostRow[$c] ?? null;
    }
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function revision_list_for_post(int $postId): array
{
    $stmt = blog_db()->prepare(
        'SELECT r.id, r.post_id, r.title, r.status, r.created_at, r.created_by, u.name AS created_by_name
           FROM blog_revisions r
           LEFT JOIN blog_users u ON u.id = r.created_by
          WHERE r.post_id = :post_id
          ORDER BY r.created_at DESC'
    );
    $stmt->execute([':post_id' => $postId]);
    return $stmt->fetchAll();
}

function revision_find(int $revisionId): ?array
{
    $stmt = blog_db()->prepare('SELECT * FROM blog_revisions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $revisionId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Restore a revision onto its post: snapshot current state first (so the
 * pre-restore version is never lost), then overwrite the live row, then log
 * it. Runs inside a transaction so a failure partway through can't leave
 * the post half-restored.
 */
function revision_restore(int $revisionId, int $restoredBy): array
{
    $pdo = blog_db();
    $revision = revision_find($revisionId);
    if (!$revision) {
        return ['ok' => false, 'error' => 'Revision not found.'];
    }

    $postId = (int) $revision['post_id'];
    $currentStmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
    $currentStmt->execute([':id' => $postId]);
    $current = $currentStmt->fetch();
    if (!$current) {
        return ['ok' => false, 'error' => 'Post no longer exists.'];
    }

    $pdo->beginTransaction();
    try {
        // 1. Snapshot the CURRENT (pre-restore) state as a new revision.
        revision_create($postId, $current, $restoredBy);

        // 2. Overwrite the live post with the selected revision's data.
        $cols = REVISION_COLUMNS;
        $setSql = implode(', ', array_map(fn($c) => "$c = :$c", $cols));
        $updateStmt = $pdo->prepare("UPDATE blog_posts SET $setSql WHERE id = :id");
        $params = [':id' => $postId];
        foreach ($cols as $c) {
            $params[':' . $c] = $revision[$c] ?? null;
        }
        $updateStmt->execute($params);

        // 3. Log the restore (revision history itself is untouched — both
        //    old and new revisions remain in blog_revisions).
        log_activity($restoredBy, 'revision_restored', 'blog_post', $postId, "Restored revision #$revisionId");

        $pdo->commit();
        return ['ok' => true, 'post_id' => $postId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[revision_restore] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Restore failed. No changes were made.'];
    }
}
