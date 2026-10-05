<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/validation.php';
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
$isEdit = $id > 0;
$name = v_clean_str($_POST['name'] ?? '', 150);
$email = v_clean_str($_POST['email'] ?? '', 190);
$role = in_array($_POST['role'] ?? '', [ROLE_AUTHOR, ROLE_EDITOR, ROLE_SUPER_ADMIN], true) ? $_POST['role'] : ROLE_AUTHOR;
$status = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
$password = (string) ($_POST['password'] ?? '');
$mustChange = !empty($_POST['must_change_password']) ? 1 : 0;

$errors = [];
if ($name === '') $errors[] = 'Name is required.';
if (!v_is_valid_email($email)) $errors[] = 'Enter a valid email.';
if (!$isEdit && mb_strlen($password) < 10) $errors[] = 'Password must be at least 10 characters.';
if ($password !== '' && mb_strlen($password) < 10) $errors[] = 'Password must be at least 10 characters.';

// Prevent a super admin from locking themselves out by demoting/disabling
// their own only-super-admin account.
$pdo = blog_db();
if ($isEdit && (int) $id === (int) $user['id'] && $role !== ROLE_SUPER_ADMIN) {
    $remaining = (int) $pdo->query("SELECT COUNT(*) FROM blog_users WHERE role = 'super_admin' AND id != " . (int) $id)->fetchColumn();
    if ($remaining === 0) {
        $errors[] = 'You cannot remove super admin from your own account — you are the only super admin.';
    }
}

if (!empty($errors)) {
    $back = $isEdit ? "/blog-cms/admin/users/edit.php?id=$id" : '/blog-cms/admin/users/create.php';
    header('Location: ' . $back . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

if ($isEdit) {
    if ($password !== '') {
        $stmt = $pdo->prepare('UPDATE blog_users SET name=:name, email=:email, role=:role, status=:status, password_hash=:hash, must_change_password=:mcp WHERE id=:id');
        $stmt->execute([':name' => $name, ':email' => $email, ':role' => $role, ':status' => $status, ':hash' => password_hash($password, PASSWORD_DEFAULT), ':mcp' => $mustChange, ':id' => $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE blog_users SET name=:name, email=:email, role=:role, status=:status, must_change_password=:mcp WHERE id=:id');
        $stmt->execute([':name' => $name, ':email' => $email, ':role' => $role, ':status' => $status, ':mcp' => $mustChange, ':id' => $id]);
    }
    log_activity($user['id'], 'user_updated', 'blog_user', $id, "Updated user \"$name\"");
} else {
    $stmt = $pdo->prepare('INSERT INTO blog_users (name, email, password_hash, role, status, must_change_password) VALUES (:name, :email, :hash, :role, :status, :mcp)');
    $stmt->execute([':name' => $name, ':email' => $email, ':hash' => password_hash($password, PASSWORD_DEFAULT), ':role' => $role, ':status' => $status, ':mcp' => $mustChange]);
    $id = (int) $pdo->lastInsertId();
    log_activity($user['id'], 'user_created', 'blog_user', $id, "Created user \"$name\" ($role)");
}

header('Location: /blog-cms/admin/users/index.php?saved=1');
exit;
