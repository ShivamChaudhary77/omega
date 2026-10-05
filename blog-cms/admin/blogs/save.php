<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/blog_functions.php';
require_once __DIR__ . '/../../includes/seo.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/csrf.php';

$user = auth_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require();

$id = (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;
$existing = $isEdit ? blog_post_find($id) : null;
if ($isEdit && !$existing) {
    http_response_code(404);
    exit('Blog not found.');
}
if ($isEdit && !perm_can_manage_post($user, $existing)) {
    http_response_code(403);
    exit('You do not have permission to edit this blog.');
}

$title = v_clean_str($_POST['title'] ?? '', 255);
$slugInput = v_clean_str($_POST['slug'] ?? '', 255);
$excerpt = v_clean_str($_POST['excerpt'] ?? '', 500);
$content = v_sanitize_html((string) ($_POST['content'] ?? ''));
$categoryId = (int) ($_POST['category_id'] ?? 0);
$requestedStatus = v_clean_str($_POST['status'] ?? 'draft', 20);
$scheduledAtInput = v_clean_str($_POST['scheduled_at'] ?? '', 20);

$errors = [];
if ($title === '') {
    $errors['title'] = 'Title is required.';
}
if (!blog_category_find($categoryId)) {
    $errors['category_id'] = 'Please choose a valid category.';
}

// Authors may only save as draft, regardless of what was submitted —
// enforced server-side, not just by hiding the option in the UI.
if (!perm_can_publish($user) && $requestedStatus !== 'draft') {
    $requestedStatus = 'draft';
}
if (!in_array($requestedStatus, ['draft', 'published', 'scheduled', 'archived'], true)) {
    $requestedStatus = 'draft';
}

$authorId = $user['role'] === ROLE_AUTHOR ? $user['id'] : (int) ($_POST['author_id'] ?? $user['id']);

$slugBase = $slugInput !== '' ? v_slugify($slugInput) : v_slugify($title);
if (!v_is_valid_slug($slugBase)) {
    $errors['slug'] = 'Slug could not be generated from the title — try entering one manually.';
}

if (!empty($errors)) {
    // Minimal admin error surface — a full re-render-with-errors flow would
    // duplicate the whole form; for an internal tool, redirecting back with
    // a query-string error summary is an acceptable, honest trade-off.
    $back = $isEdit ? "/blog-cms/admin/blogs/edit.php?id=$id" : '/blog-cms/admin/blogs/create.php';
    header('Location: ' . $back . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

$slug = blog_post_unique_slug($slugBase, $isEdit ? $id : null);

$publishedAt = $existing['published_at'] ?? null;
$scheduledAt = null;
if ($requestedStatus === 'published' && $publishedAt === null) {
    $publishedAt = date('Y-m-d H:i:s');
} elseif ($requestedStatus === 'scheduled') {
    $scheduledAt = $scheduledAtInput !== '' ? date('Y-m-d H:i:s', strtotime($scheduledAtInput)) : null;
    if (!$scheduledAt) {
        $requestedStatus = 'draft'; // no valid date given — don't silently publish
    }
}

$data = [
    'title' => $title,
    'slug' => $slug,
    'excerpt' => $excerpt,
    'content' => $content,
    'category_id' => $categoryId,
    'author_id' => $authorId,
    'status' => $requestedStatus,
    'published_at' => $publishedAt,
    'scheduled_at' => $scheduledAt,
    'meta_title' => v_clean_str($_POST['meta_title'] ?? '', 255),
    'meta_description' => v_clean_str($_POST['meta_description'] ?? '', 500),
    'focus_keyword' => v_clean_str($_POST['focus_keyword'] ?? '', 150),
    'canonical_url' => v_clean_str($_POST['canonical_url'] ?? '', 500),
    'robots_index' => !empty($_POST['robots_noindex']) ? 'noindex' : 'index',
    'robots_follow' => !empty($_POST['robots_nofollow']) ? 'nofollow' : 'follow',
    'og_title' => v_clean_str($_POST['og_title'] ?? '', 255),
    'og_description' => v_clean_str($_POST['og_description'] ?? '', 500),
    'og_image' => v_clean_str($_POST['og_image'] ?? '', 500),
    'twitter_title' => v_clean_str($_POST['twitter_title'] ?? '', 255),
    'twitter_description' => v_clean_str($_POST['twitter_description'] ?? '', 500),
    'twitter_image' => v_clean_str($_POST['twitter_image'] ?? '', 500),
    'reading_time' => max(1, (int) round(str_word_count(strip_tags($content)) / 200)),
];
$featuredImageId = (int) ($_POST['featured_image_id'] ?? 0);
if ($featuredImageId > 0) {
    $data['featured_image_id'] = $featuredImageId;
}

if ($isEdit) {
    blog_post_update($id, $data, $user['id']);
    $postId = $id;
} else {
    $postId = blog_post_create($data, $user['id']);
}

header('Location: /blog-cms/admin/blogs/edit.php?id=' . $postId . '&saved=1');
exit;
