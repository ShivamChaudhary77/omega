<?php
/**
 * Centralized, non-secret Blog CMS configuration. Deliberately does NOT
 * duplicate DB credentials — those stay in the site's existing
 * php/config.php (see includes/db.php), reused as-is. This file only holds
 * values that are safe to read from any admin page and that a developer
 * might reasonably want to tweak without touching PHP logic.
 */

declare(strict_types=1);

return [
    'site_url' => 'https://theomegagroup.in',
    'blog_url_note' => 'CMS posts use the same /<category-slug>/<post-slug>/ URL shape as the '
        . 'existing 79 static posts — there is no separate /blog/ URL prefix. See BLOG-README.md.',
    'upload_dir' => '/blog-cms/uploads/',
    'max_upload_bytes' => 5 * 1024 * 1024,
    'allowed_image_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'posts_per_page' => 12,
    'default_author_email' => null, // set to an existing blog_users.email to preselect a default author in the editor
    'timezone' => 'Asia/Kolkata',
    'seo_defaults' => [
        'title_suffix' => ' | The Omega Group Blog',
        'site_name' => 'The Omega Group',
    ],
];
