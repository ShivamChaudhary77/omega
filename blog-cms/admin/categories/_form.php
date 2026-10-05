<?php
/** Shared form partial, included by create.php and edit.php (both already
 *  set $category to null or an array before requiring this file). */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/csrf.php';
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="admin-card" style="max-width:600px;">
<form method="post" action="/blog-cms/admin/categories/save.php">
    <?= csrf_field() ?>
    <?php if ($category): ?><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><?php endif; ?>
    <div class="admin-form-row">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= $e($category['name'] ?? '') ?>" required>
    </div>
    <div class="admin-form-row">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" value="<?= $e($category['slug'] ?? '') ?>" placeholder="auto-generated from name if left blank">
        <div class="admin-hint">Changing the slug of a category with published posts changes those posts' live URLs.</div>
    </div>
    <div class="admin-form-row">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= $e($category['description'] ?? '') ?></textarea>
    </div>
    <div class="admin-form-row">
        <label for="seo_title">SEO Title</label>
        <input type="text" id="seo_title" name="seo_title" value="<?= $e($category['seo_title'] ?? '') ?>">
    </div>
    <div class="admin-form-row">
        <label for="seo_description">SEO Description</label>
        <textarea id="seo_description" name="seo_description" rows="2"><?= $e($category['seo_description'] ?? '') ?></textarea>
    </div>
    <div class="admin-form-row">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="active" <?= ($category['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($category['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <button type="submit" class="admin-btn admin-btn-primary" style="width:auto;">Save Category</button>
</form>
</div>
