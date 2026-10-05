<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/activity_log.php';

$user = perm_require_role(ROLE_EDITOR);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require();

$id = (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;
$name = v_clean_str($_POST['name'] ?? '', 150);
$slugInput = v_clean_str($_POST['slug'] ?? '', 150);
$description = v_clean_str($_POST['description'] ?? '', 2000);
$seoTitle = v_clean_str($_POST['seo_title'] ?? '', 255);
$seoDescription = v_clean_str($_POST['seo_description'] ?? '', 500);
$status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

if ($name === '') {
    header('Location: /blog-cms/admin/categories/' . ($isEdit ? "edit.php?id=$id" : 'create.php') . '&error=' . urlencode('Name is required.'));
    exit;
}

$pdo = blog_db();
$baseSlug = $slugInput !== '' ? v_slugify($slugInput) : v_slugify($name);
$slug = $baseSlug;
$suffix = 1;
while (true) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM blog_categories WHERE slug = :slug' . ($isEdit ? ' AND id != :id' : ''));
    $params = [':slug' => $slug];
    if ($isEdit) $params[':id'] = $id;
    $stmt->execute($params);
    if ((int) $stmt->fetchColumn() === 0) break;
    $suffix++;
    $slug = $baseSlug . '-' . $suffix;
}

if ($isEdit) {
    $stmt = $pdo->prepare(
        'UPDATE blog_categories SET name=:name, slug=:slug, description=:description, seo_title=:seo_title, seo_description=:seo_description, status=:status WHERE id=:id'
    );
    $stmt->execute([':name' => $name, ':slug' => $slug, ':description' => $description, ':seo_title' => $seoTitle, ':seo_description' => $seoDescription, ':status' => $status, ':id' => $id]);
    log_activity($user['id'], 'category_updated', 'blog_category', $id, "Updated category \"$name\"");
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO blog_categories (name, slug, description, seo_title, seo_description, status) VALUES (:name, :slug, :description, :seo_title, :seo_description, :status)'
    );
    $stmt->execute([':name' => $name, ':slug' => $slug, ':description' => $description, ':seo_title' => $seoTitle, ':seo_description' => $seoDescription, ':status' => $status]);
    $id = (int) $pdo->lastInsertId();
    log_activity($user['id'], 'category_created', 'blog_category', $id, "Created category \"$name\"");
}

header('Location: /blog-cms/admin/categories/index.php?saved=1');
exit;
