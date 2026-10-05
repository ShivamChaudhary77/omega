<?php
/**
 * Deletes a category — but ONLY if it has zero published posts, per project
 * requirement: "Prevent deleting a category if it would create orphaned
 * published posts unless a safe reassignment process exists." No
 * reassignment flow exists yet, so this is a hard block, not a soft
 * warning — draft/scheduled/archived posts in the category do not block
 * deletion (blog_posts.category_id has ON DELETE RESTRICT as the DB-level
 * backstop regardless).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/activity_log.php';

$user = perm_require_role(ROLE_EDITOR);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require();

$id = (int) ($_POST['id'] ?? 0);
$category = blog_category_find($id);
if (!$category) {
    http_response_code(404);
    exit('Category not found.');
}

$pdo = blog_db();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE category_id = :id AND status = 'published'");
$stmt->execute([':id' => $id]);
if ((int) $stmt->fetchColumn() > 0) {
    header('Location: /blog-cms/admin/categories/index.php?error=' . urlencode('Cannot delete "' . $category['name'] . '" — it still has published posts. Move or unpublish them first.'));
    exit;
}

$del = $pdo->prepare('DELETE FROM blog_categories WHERE id = :id');
$del->execute([':id' => $id]);
log_activity($user['id'], 'category_deleted', 'blog_category', $id, 'Deleted category "' . $category['name'] . '"');

header('Location: /blog-cms/admin/categories/index.php?deleted=1');
exit;
