-- Blog CMS — Revisions
--
-- One row is inserted every time a post is updated (see
-- includes/revision.php's create_revision(), called from
-- admin/blogs/save.php before the blog_posts row itself is updated — so a
-- revision always captures the state JUST BEFORE the change that triggered
-- it, matching WordPress's own revision semantics).
--
-- Restoring a revision (admin/blogs/restore.php) NEVER deletes rows from
-- this table — it snapshots the CURRENT state as one more revision, then
-- overwrites blog_posts with the selected revision's data, then logs the
-- restore in blog_activity_logs. Full history is always preserved.

CREATE TABLE IF NOT EXISTS blog_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    excerpt VARCHAR(500) DEFAULT NULL,
    content MEDIUMTEXT NOT NULL,
    featured_image_id INT UNSIGNED DEFAULT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    author_id INT UNSIGNED DEFAULT NULL,
    status ENUM('draft', 'published', 'scheduled', 'archived') DEFAULT NULL,

    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    focus_keyword VARCHAR(150) DEFAULT NULL,
    canonical_url VARCHAR(500) DEFAULT NULL,
    robots_index ENUM('index', 'noindex') DEFAULT NULL,
    robots_follow ENUM('follow', 'nofollow') DEFAULT NULL,
    og_title VARCHAR(255) DEFAULT NULL,
    og_description VARCHAR(500) DEFAULT NULL,
    og_image VARCHAR(500) DEFAULT NULL,
    twitter_title VARCHAR(255) DEFAULT NULL,
    twitter_description VARCHAR(500) DEFAULT NULL,
    twitter_image VARCHAR(500) DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL COMMENT 'blog_users.id of whoever made the edit that triggered this snapshot',

    INDEX idx_blog_revisions_post_id (post_id),
    INDEX idx_blog_revisions_created_by (created_by),
    INDEX idx_blog_revisions_created_at (created_at),

    CONSTRAINT fk_blog_revisions_post FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_revisions_created_by FOREIGN KEY (created_by) REFERENCES blog_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
