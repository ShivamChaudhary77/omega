<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/activity_log.php';

$user = auth_require_login();
if (!perm_can_manage_users($user)) {
    http_response_code(403);
    exit('You do not have permission to manage users.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require();

$id = (int) ($_POST['id'] ?? 0);
if ($id === (int) $user['id']) {
    header('Location: /blog-cms/admin/users/index.php?error=' . urlencode('You cannot delete your own account.'));
    exit;
}

$pdo = blog_db();
$stmt = $pdo->prepare('SELECT name FROM blog_users WHERE id = :id');
$stmt->execute([':id' => $id]);
$name = $stmt->fetchColumn();
if (!$name) {
    http_response_code(404);
    exit('User not found.');
}

// blog_posts.author_id has ON DELETE RESTRICT, so deleting a user who still
// authors posts fails at the DB level rather than silently orphaning them —
// surface that clearly instead of a raw SQL error.
try {
    $del = $pdo->prepare('DELETE FROM blog_users WHERE id = :id');
    $del->execute([':id' => $id]);
} catch (PDOException $e) {
    header('Location: /blog-cms/admin/users/index.php?error=' . urlencode("Cannot delete \"$name\" — they still have blogs assigned to them. Reassign those blogs to another author first."));
    exit;
}

log_activity($user['id'], 'user_deleted', 'blog_user', $id, "Deleted user \"$name\"");
header('Location: /blog-cms/admin/users/index.php?deleted=1');
exit;
