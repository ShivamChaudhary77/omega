<?php
/**
 * Shared blog editor form, used by both admin/blogs/create.php and edit.php.
 * $post is null for create, or an existing blog_posts row (array) for edit.
 *
 * Content editor: Quill.js (MIT, CDN-hosted, no build step, no framework —
 * satisfies "lightweight editor compatible with the hosting environment").
 * Quill's own HTML output is NOT trusted as-is: admin/blogs/save.php runs it
 * through v_sanitize_html() before it ever reaches the database, and the
 * public router sanitizes again at render time as defense in depth.
 *
 * YouTube embeds: rather than allow raw <iframe> HTML (which would mean
 * accepting arbitrary iframe src from an editor — exactly the "arbitrary
 * unsafe HTML" the project brief warns against), the toolbar's embed button
 * extracts just the YouTube video ID from a pasted URL and inserts a plain
 * text token "[[youtube:VIDEO_ID]]". blog_functions.php's content renderer
 * converts that token to a real iframe with a fixed, safe src template at
 * render time — the ID is validated (`^[a-zA-Z0-9_-]{11}$`) before it's ever
 * interpolated into that src.
 */

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/media.php';

function render_blog_form(array $user, ?array $post, array $categories, array $authors): void
{
    $e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    $isEdit = $post !== null;
    $canPublish = perm_can_publish($user);
    ?>
<form method="post" action="/blog-cms/admin/blogs/save.php" id="blog-form">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $post['id'] ?>"><?php endif; ?>

    <div class="admin-tabs">
        <a href="#content" class="tab-link active" data-tab="content">Content</a>
        <a href="#seo" class="tab-link" data-tab="seo">SEO</a>
    </div>

    <div class="admin-form-grid">
        <div>
            <div id="tab-content" class="tab-pane">
                <div class="admin-form-row">
                    <label for="title">Blog Title</label>
                    <input type="text" id="title" name="title" value="<?= $e($post['title'] ?? '') ?>" required>
                </div>
                <div class="admin-form-row">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="<?= $e($post['slug'] ?? '') ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="auto-generated from title if left blank">
                    <div class="admin-hint">Final URL: <span id="url-preview">https://theomegagroup.in/&lt;category&gt;/&lt;slug&gt;/</span>. Changing the slug of an already-published post breaks its existing URL for visitors/Google unless you add a redirect afterward — prefer leaving it as-is once published.</div>
                </div>
                <div class="admin-form-row">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="2" maxlength="500"><?= $e($post['excerpt'] ?? '') ?></textarea>
                </div>
                <div class="admin-form-row">
                    <label for="content_html">Content</label>
                    <div id="editor" style="background:#fff; min-height:400px;"><?= $post['content'] ?? '' ?></div>
                    <textarea name="content" id="content_html" style="display:none;"></textarea>
                </div>
            </div>

            <div id="tab-seo" class="tab-pane" style="display:none;">
                <div class="admin-form-row">
                    <label for="meta_title">Meta Title</label>
                    <input type="text" id="meta_title" name="meta_title" value="<?= $e($post['meta_title'] ?? '') ?>" maxlength="255">
                    <div class="admin-hint">Falls back to "Blog Title | The Omega Group Blog" if left blank.</div>
                </div>
                <div class="admin-form-row">
                    <label for="meta_description">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" rows="2" maxlength="500"><?= $e($post['meta_description'] ?? '') ?></textarea>
                    <div class="admin-hint">Falls back to the excerpt, then an auto-generated snippet from the content.</div>
                </div>
                <div class="admin-form-row">
                    <label for="focus_keyword">Focus Keyword</label>
                    <input type="text" id="focus_keyword" name="focus_keyword" value="<?= $e($post['focus_keyword'] ?? '') ?>">
                </div>
                <div class="admin-form-row">
                    <label for="canonical_url">Canonical URL</label>
                    <input type="url" id="canonical_url" name="canonical_url" value="<?= $e($post['canonical_url'] ?? '') ?>" placeholder="Falls back to this post's own URL">
                </div>
                <div class="admin-form-row" style="display:flex; gap:20px;">
                    <label><input type="checkbox" name="robots_noindex" value="1" <?= (($post['robots_index'] ?? 'index') === 'noindex') ? 'checked' : '' ?>> Noindex</label>
                    <label><input type="checkbox" name="robots_nofollow" value="1" <?= (($post['robots_follow'] ?? 'follow') === 'nofollow') ? 'checked' : '' ?>> Nofollow</label>
                </div>
                <hr>
                <div class="admin-form-row">
                    <label for="og_title">OG Title</label>
                    <input type="text" id="og_title" name="og_title" value="<?= $e($post['og_title'] ?? '') ?>">
                </div>
                <div class="admin-form-row">
                    <label for="og_description">OG Description</label>
                    <textarea id="og_description" name="og_description" rows="2"><?= $e($post['og_description'] ?? '') ?></textarea>
                </div>
                <div class="admin-form-row">
                    <label for="og_image">OG Image URL</label>
                    <input type="text" id="og_image" name="og_image" value="<?= $e($post['og_image'] ?? '') ?>" placeholder="Falls back to the featured image">
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-outline js-pick-media" data-target="og_image">Choose from Media Library</button>
                </div>
                <div class="admin-form-row">
                    <label for="twitter_title">Twitter Title</label>
                    <input type="text" id="twitter_title" name="twitter_title" value="<?= $e($post['twitter_title'] ?? '') ?>">
                </div>
                <div class="admin-form-row">
                    <label for="twitter_description">Twitter Description</label>
                    <textarea id="twitter_description" name="twitter_description" rows="2"><?= $e($post['twitter_description'] ?? '') ?></textarea>
                </div>
                <div class="admin-form-row">
                    <label for="twitter_image">Twitter Image URL</label>
                    <input type="text" id="twitter_image" name="twitter_image" value="<?= $e($post['twitter_image'] ?? '') ?>" placeholder="Falls back to OG image">
                </div>
                <?php if ($isEdit): ?>
                <div class="admin-hint">Content checklist score (not a Google ranking score — just a checklist of this post's own fields): <strong><?= (int) seo_calculate_content_checklist_score($post) ?>/100</strong></div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="admin-card" style="margin-bottom:16px;">
                <h4>Publish</h4>
                <div class="admin-form-row">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <?php if ($canPublish): ?>
                        <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="scheduled" <?= ($post['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="archived" <?= ($post['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$canPublish): ?><div class="admin-hint">Only editors and super admins can publish, schedule, or archive. Authors can save drafts.</div><?php endif; ?>
                </div>
                <div class="admin-form-row" id="scheduled-row" style="display:none;">
                    <label for="scheduled_at">Publish On</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?= $e(isset($post['scheduled_at']) ? str_replace(' ', 'T', substr($post['scheduled_at'], 0, 16)) : '') ?>">
                </div>
                <div class="admin-form-row">
                    <button type="submit" name="action" value="save" class="admin-btn admin-btn-primary">Save</button>
                </div>
                <?php if ($isEdit): ?>
                <div class="admin-form-row">
                    <a href="/blog-cms/admin/blogs/preview.php?id=<?= (int) $post['id'] ?>" target="_blank" class="admin-btn admin-btn-outline" style="width:100%; display:block;">Preview</a>
                </div>
                <?php if (!empty($post['preview_token'])): ?>
                <div class="admin-form-row">
                    <label>Shareable Preview Link</label>
                    <input type="text" readonly value="https://theomegagroup.in/blog-cms/public/preview.php?token=<?= $e($post['preview_token']) ?>" onclick="this.select()">
                    <div class="admin-hint">Anyone with this link can view this post without logging in — it's never indexed and never linked from anywhere public. Share it only with people who need to see this draft.</div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="admin-card" style="margin-bottom:16px;">
                <h4>Organize</h4>
                <div class="admin-form-row">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-slug="<?= $e($c['slug']) ?>" <?= (int) ($post['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-row">
                    <label for="author_id">Author</label>
                    <select id="author_id" name="author_id" <?= $user['role'] === ROLE_AUTHOR ? 'disabled' : '' ?>>
                        <?php foreach ($authors as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) ($post['author_id'] ?? $user['id']) === (int) $a['id'] ? 'selected' : '' ?>><?= $e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($user['role'] === ROLE_AUTHOR): ?>
                        <input type="hidden" name="author_id" value="<?= (int) $user['id'] ?>">
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-card">
                <h4>Featured Image</h4>
                <div id="featured-image-preview">
                    <?php if (!empty($post['featured_image_id'])): $fm = media_find((int) $post['featured_image_id']); ?>
                        <?php if ($fm): ?><img src="<?= $e($fm['file_path']) ?>" style="width:100%; border-radius:6px;"><?php endif; ?>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="featured_image_id" id="featured_image_id" value="<?= $e($post['featured_image_id'] ?? '') ?>">
                <button type="button" class="admin-btn admin-btn-outline js-pick-media" data-target="featured_image_id" data-preview="featured-image-preview" style="width:100%; margin-top:10px;">Choose Featured Image</button>
            </div>
        </div>
    </div>
</form>

<div id="media-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999;">
    <div style="background:#fff; max-width:800px; margin:40px auto; padding:24px; border-radius:10px; max-height:80vh; overflow:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0;">Media Library</h3>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" id="media-modal-close">Close</button>
        </div>
        <div class="admin-tabs">
            <a href="#" class="tab-link active" data-media-tab="existing">Existing Site Images</a>
            <a href="#" class="tab-link" data-media-tab="upload">Upload New</a>
        </div>
        <div id="media-tab-existing">
            <input type="text" id="media-search-existing" placeholder="Search existing images…" style="width:100%; padding:8px; margin-bottom:12px; border:1px solid #E8DFD0; border-radius:6px;">
            <div id="media-existing-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px;"></div>
        </div>
        <div id="media-tab-upload" style="display:none;">
            <input type="file" id="media-upload-input" accept="image/jpeg,image/png,image/webp">
            <div class="admin-hint">JPG, PNG, or WebP. Max 5MB.</div>
            <div id="media-upload-status"></div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="/blog-cms/assets/js/admin-editor.js"></script>
    <?php
}
