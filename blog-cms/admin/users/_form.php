<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/csrf.php';
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$isEdit = $editUser !== null;
?>
<div class="admin-card" style="max-width:520px;">
<form method="post" action="/blog-cms/admin/users/save.php">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>
    <div class="admin-form-row">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" value="<?= $e($editUser['name'] ?? '') ?>" required>
    </div>
    <div class="admin-form-row">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $e($editUser['email'] ?? '') ?>" required>
    </div>
    <div class="admin-form-row">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="author" <?= ($editUser['role'] ?? '') === 'author' ? 'selected' : '' ?>>Author</option>
            <option value="editor" <?= ($editUser['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor</option>
            <option value="super_admin" <?= ($editUser['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
        </select>
    </div>
    <div class="admin-form-row">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="active" <?= ($editUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="disabled" <?= ($editUser['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
    </div>
    <div class="admin-form-row">
        <label for="password"><?= $isEdit ? 'New Password (leave blank to keep current)' : 'Password' ?></label>
        <input type="password" id="password" name="password" minlength="10" <?= $isEdit ? '' : 'required' ?>>
    </div>
    <?php if ($isEdit): ?>
    <div class="admin-form-row">
        <label><input type="checkbox" name="must_change_password" value="1" <?= !empty($editUser['must_change_password']) ? 'checked' : '' ?>> Require password change at next login</label>
    </div>
    <?php else: ?>
    <div class="admin-form-row">
        <label><input type="checkbox" name="must_change_password" value="1" checked> Require password change at first login</label>
    </div>
    <?php endif; ?>
    <button type="submit" class="admin-btn admin-btn-primary" style="width:auto;">Save User</button>
</form>
</div>
