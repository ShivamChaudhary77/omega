<?php
/**
 * Read-only settings view. Deliberately not an editable-settings UI backed
 * by the database for v1 — editable settings would mean either a 7th DB
 * table for a handful of rarely-changed values, or writing PHP config from
 * PHP (a second attack surface for arbitrary file writes if ever done
 * carelessly). Displaying blog_config.php's real values with a pointer to
 * the file is a simpler, equally functional, lower-risk v1. See
 * DEPLOYMENT.md / the implementation report for this trade-off.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

$user = perm_require_role(ROLE_SUPER_ADMIN);
$config = require __DIR__ . '/../../config/blog_config.php';

admin_layout_start($user, 'settings', 'Settings');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="admin-card">
    <h3>Blog CMS Configuration</h3>
    <p class="admin-hint">These values live in <code>blog-cms/config/blog_config.php</code>. Edit that file directly to change them — there's no write-UI for settings in this version (see file docblock for why).</p>
    <table class="admin-table">
        <tbody>
        <tr><th>Site URL</th><td><?= $e($config['site_url']) ?></td></tr>
        <tr><th>Upload Directory</th><td><?= $e($config['upload_dir']) ?></td></tr>
        <tr><th>Max Upload Size</th><td><?= $e(round($config['max_upload_bytes'] / 1024 / 1024, 1)) ?> MB</td></tr>
        <tr><th>Allowed Image Types</th><td><?= $e(implode(', ', $config['allowed_image_mime_types'])) ?></td></tr>
        <tr><th>Posts Per Page</th><td><?= $e($config['posts_per_page']) ?></td></tr>
        <tr><th>Timezone</th><td><?= $e($config['timezone']) ?></td></tr>
        <tr><th>SEO Title Suffix</th><td><?= $e($config['seo_defaults']['title_suffix']) ?></td></tr>
        </tbody>
    </table>
</div>
<?php admin_layout_end(); ?>
