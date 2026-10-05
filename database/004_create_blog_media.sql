-- Blog CMS — Media library
--
-- Supports BOTH newly uploaded files AND references to images that already
-- exist in the site's own img/ tree (existing site images are never copied,
-- moved, or renamed — see includes/media.php's scan_existing_images()).
--
-- `source` distinguishes the two: 'existing' rows are created lazily (only
-- once an admin actually attaches alt/title/caption text to that image, or
-- selects it in the editor) and store the image's real, already-live path
-- (e.g. /img/projects/krisumi-sector-36a-gurgaon/krisumi-...-02.webp)
-- untouched. 'upload' rows are new files saved under blog-cms/uploads/.

CREATE TABLE IF NOT EXISTS blog_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source ENUM('upload', 'existing') NOT NULL,
    file_path VARCHAR(500) NOT NULL COMMENT 'Root-relative path, e.g. /blog-cms/uploads/2026/09/abc123.webp or /img/projects/.../x.webp',
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size_bytes INT UNSIGNED DEFAULT NULL,
    width SMALLINT UNSIGNED DEFAULT NULL,
    height SMALLINT UNSIGNED DEFAULT NULL,
    alt_text VARCHAR(255) DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    caption VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_blog_media_file_path (file_path(255)),
    INDEX idx_blog_media_source (source),
    INDEX idx_blog_media_uploaded_by (uploaded_by),

    CONSTRAINT fk_blog_media_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES blog_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Now that blog_media exists, wire blog_posts.featured_image_id to it.
ALTER TABLE blog_posts
    ADD CONSTRAINT fk_blog_posts_featured_image FOREIGN KEY (featured_image_id) REFERENCES blog_media(id) ON DELETE SET NULL;
