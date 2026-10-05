<?php
/**
 * Core blog_posts CRUD + query helpers, shared by admin/blogs/*.php and
 * blog-cms/public/router.php. Every write here goes through a prepared
 * statement; nothing here trusts caller input to already be safe.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/revision.php';
require_once __DIR__ . '/activity_log.php';

function blog_categories_all(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM blog_categories';
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY name ASC';
    return blog_db()->query($sql)->fetchAll();
}

function blog_category_find(int $id): ?array
{
    $stmt = blog_db()->prepare('SELECT * FROM blog_categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function blog_category_find_by_slug(string $slug): ?array
{
    $stmt = blog_db()->prepare('SELECT * FROM blog_categories WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

/** Ensures a slug is unique in blog_posts, appending -2, -3, ... if needed. Excludes $excludePostId when editing. */
function blog_post_unique_slug(string $baseSlug, ?int $excludePostId = null): string
{
    $pdo = blog_db();
    $slug = $baseSlug;
    $suffix = 1;
    while (true) {
        $sql = 'SELECT COUNT(*) FROM blog_posts WHERE slug = :slug';
        $params = [':slug' => $slug];
        if ($excludePostId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludePostId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $suffix++;
        $slug = $baseSlug . '-' . $suffix;
    }
}

function blog_post_find(int $id): ?array
{
    $stmt = blog_db()->prepare('SELECT * FROM blog_posts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function blog_post_find_by_slug(string $slug): ?array
{
    $stmt = blog_db()->prepare('SELECT * FROM blog_posts WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function blog_post_find_by_preview_token(string $token): ?array
{
    $stmt = blog_db()->prepare('SELECT * FROM blog_posts WHERE preview_token = :t');
    $stmt->execute([':t' => $token]);
    return $stmt->fetch() ?: null;
}

const BLOG_POST_WRITABLE_FIELDS = [
    'title', 'slug', 'excerpt', 'content', 'featured_image_id', 'category_id', 'author_id',
    'status', 'published_at', 'scheduled_at',
    'meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'robots_index', 'robots_follow',
    'og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description', 'twitter_image',
    'reading_time', 'allow_comments', 'schema_type',
];

/**
 * Create a new post. $data keys should already be validated by the caller
 * (admin/blogs/save.php) — this function's job is the DB write + activity
 * log, not form validation.
 */
function blog_post_create(array $data, int $authorUserId): int
{
    $pdo = blog_db();
    $cols = array_intersect_key($data, array_flip(BLOG_POST_WRITABLE_FIELDS));
    $cols['preview_token'] = bin2hex(random_bytes(24));

    $fields = array_keys($cols);
    $placeholders = array_map(fn($f) => ':' . $f, $fields);

    $stmt = $pdo->prepare(
        'INSERT INTO blog_posts (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')'
    );
    $params = [];
    foreach ($cols as $f => $v) {
        $params[':' . $f] = $v;
    }
    $stmt->execute($params);
    $postId = (int) $pdo->lastInsertId();

    log_activity($authorUserId, 'blog_created', 'blog_post', $postId, 'Created blog "' . $data['title'] . '"');

    return $postId;
}

/**
 * Update an existing post. Snapshots the PRE-update state into
 * blog_revisions first (see revision.php), then writes the new data.
 */
function blog_post_update(int $postId, array $data, int $editorUserId): bool
{
    $pdo = blog_db();
    $current = blog_post_find($postId);
    if (!$current) {
        return false;
    }

    // Only create a revision if something meaningful actually changed —
    // avoids a new revision row for e.g. a no-op re-save.
    $meaningfullyChanged = false;
    foreach (REVISION_COLUMNS as $col) {
        if (array_key_exists($col, $data) && (string) ($data[$col] ?? '') !== (string) ($current[$col] ?? '')) {
            $meaningfullyChanged = true;
            break;
        }
    }
    if ($meaningfullyChanged) {
        revision_create($postId, $current, $editorUserId);
    }

    $cols = array_intersect_key($data, array_flip(BLOG_POST_WRITABLE_FIELDS));
    if (empty($cols)) {
        return true;
    }
    $setSql = implode(', ', array_map(fn($f) => "$f = :$f", array_keys($cols)));
    $stmt = $pdo->prepare("UPDATE blog_posts SET $setSql WHERE id = :id");
    $params = [':id' => $postId];
    foreach ($cols as $f => $v) {
        $params[':' . $f] = $v;
    }
    $stmt->execute($params);

    $statusChanged = isset($data['status']) && $data['status'] !== $current['status'];
    if ($statusChanged && $data['status'] === 'published') {
        log_activity($editorUserId, 'blog_published', 'blog_post', $postId, 'Published blog "' . $current['title'] . '"');
    } elseif ($statusChanged && $current['status'] === 'published') {
        log_activity($editorUserId, 'blog_unpublished', 'blog_post', $postId, 'Unpublished blog "' . $current['title'] . '"');
    } else {
        log_activity($editorUserId, 'blog_updated', 'blog_post', $postId, 'Updated blog "' . $current['title'] . '"');
    }

    return true;
}

function blog_post_delete(int $postId, int $userId): bool
{
    $post = blog_post_find($postId);
    if (!$post) {
        return false;
    }
    // blog_revisions has ON DELETE CASCADE for post_id, so revision history
    // for a deleted post is removed with it — acceptable, since the post
    // itself no longer exists to have a history of.
    $stmt = blog_db()->prepare('DELETE FROM blog_posts WHERE id = :id');
    $ok = $stmt->execute([':id' => $postId]);
    if ($ok) {
        log_activity($userId, 'blog_deleted', 'blog_post', $postId, 'Deleted blog "' . $post['title'] . '"');
    }
    return $ok;
}

/**
 * Duplicate a post: new row, new unique slug, status forced to draft, a
 * fresh preview token, NO revision history copied (per project requirement).
 */
function blog_post_duplicate(int $postId, int $userId): ?int
{
    $original = blog_post_find($postId);
    if (!$original) {
        return null;
    }

    $newSlug = blog_post_unique_slug($original['slug'] . '-copy');
    $data = array_intersect_key($original, array_flip(BLOG_POST_WRITABLE_FIELDS));
    $data['slug'] = $newSlug;
    $data['title'] = $original['title'] . ' (Copy)';
    $data['status'] = 'draft';
    $data['published_at'] = null;
    $data['scheduled_at'] = null;

    $newId = blog_post_create($data, $userId);
    log_activity($userId, 'blog_created', 'blog_post', $newId, 'Duplicated blog "' . $original['title'] . '" as "' . $data['title'] . '"');

    return $newId;
}

/**
 * @return array{rows: array, total: int}
 */
function blog_post_admin_list(array $filters, int $page, int $perPage): array
{
    $pdo = blog_db();
    $where = [];
    $params = [];

    if (!empty($filters['search'])) {
        $where[] = 'p.title LIKE :search';
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['status'])) {
        $where[] = 'p.status = :status';
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = (int) $filters['category_id'];
    }
    if (!empty($filters['author_id'])) {
        $where[] = 'p.author_id = :author_id';
        $params[':author_id'] = (int) $filters['author_id'];
    }
    // Author-role restriction: authors only ever see their own posts in the list.
    if (!empty($filters['restrict_author_id'])) {
        $where[] = 'p.author_id = :restrict_author_id';
        $params[':restrict_author_id'] = (int) $filters['restrict_author_id'];
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sortCol = in_array($filters['sort'] ?? '', ['created_at', 'updated_at', 'published_at', 'title'], true) ? $filters['sort'] : 'updated_at';
    $sortDir = ($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $perPage = max(1, min(100, $perPage));
    $offset = max(0, ($page - 1) * $perPage);

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
           FROM blog_posts p
           LEFT JOIN blog_categories c ON c.id = p.category_id
           LEFT JOIN blog_users u ON u.id = p.author_id
           $whereSql
          ORDER BY p.$sortCol $sortDir
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Publicly visible posts only: published AND (published_at is in the past). */
function blog_post_public_list(?int $categoryId, int $page, int $perPage): array
{
    $pdo = blog_db();
    $where = "WHERE p.status = 'published' AND p.published_at <= NOW()";
    $params = [];
    if ($categoryId) {
        $where .= ' AND p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $perPage = max(1, min(50, $perPage));
    $offset = max(0, ($page - 1) * $perPage);

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
           FROM blog_posts p
           LEFT JOIN blog_categories c ON c.id = p.category_id
           LEFT JOIN blog_users u ON u.id = p.author_id
           $where
          ORDER BY p.published_at DESC
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** A published post, fetched by category+slug — this is what the public router calls. */
function blog_post_public_find(string $categorySlug, string $slug): ?array
{
    $stmt = blog_db()->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
           FROM blog_posts p
           JOIN blog_categories c ON c.id = p.category_id
           LEFT JOIN blog_users u ON u.id = p.author_id
          WHERE c.slug = :cat AND p.slug = :slug
            AND p.status = 'published' AND p.published_at <= NOW()
          LIMIT 1"
    );
    $stmt->execute([':cat' => $categorySlug, ':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

/** Related posts: same category, excluding the current post, most recent first. */
function blog_post_related(int $postId, int $categoryId, int $limit = 3): array
{
    $stmt = blog_db()->prepare(
        "SELECT p.*, c.slug AS category_slug
           FROM blog_posts p
           JOIN blog_categories c ON c.id = p.category_id
          WHERE p.category_id = :cat AND p.id != :id
            AND p.status = 'published' AND p.published_at <= NOW()
          ORDER BY p.published_at DESC
          LIMIT $limit"
    );
    $stmt->execute([':cat' => $categoryId, ':id' => $postId]);
    return $stmt->fetchAll();
}

/** Dashboard counts — see admin/index.php. */
function blog_dashboard_stats(): array
{
    $pdo = blog_db();
    $stats = [];
    $stats['total_posts'] = (int) $pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
    $stats['published'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();
    $stats['drafts'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn();
    $stats['scheduled'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'scheduled'")->fetchColumn();
    $stats['categories'] = (int) $pdo->query('SELECT COUNT(*) FROM blog_categories')->fetchColumn();
    $stats['media'] = (int) $pdo->query('SELECT COUNT(*) FROM blog_media')->fetchColumn();
    $stats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM blog_users')->fetchColumn();
    return $stats;
}

/**
 * Promotes any 'scheduled' post whose scheduled_at has arrived to
 * 'published'. Called cheaply at the top of the public router and the
 * public listing page — no cron is required on Hostinger shared hosting,
 * at the cost of a scheduled post going live on the next real visitor hit
 * rather than the exact second (acceptable for a blog; documented in
 * DEPLOYMENT.md as optionally upgradeable to a real Hostinger cron job).
 */
function blog_promote_due_scheduled_posts(): void
{
    $pdo = blog_db();
    $stmt = $pdo->prepare(
        "UPDATE blog_posts SET status = 'published', published_at = scheduled_at
          WHERE status = 'scheduled' AND scheduled_at <= NOW()"
    );
    $stmt->execute();
}
