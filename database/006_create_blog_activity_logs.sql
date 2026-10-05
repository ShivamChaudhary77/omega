-- Blog CMS — Activity log (audit trail)
--
-- user_id is nullable and ON DELETE SET NULL rather than CASCADE: if a user
-- account is later deleted, their historical actions must still be visible
-- for audit purposes (description already contains their name as it was at
-- the time — see includes/activity_log.php), not silently erased.

CREATE TABLE IF NOT EXISTS blog_activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'e.g. login, blog_created, blog_published, revision_restored',
    entity_type VARCHAR(50) DEFAULT NULL COMMENT 'e.g. blog_post, blog_category, blog_media, blog_user',
    entity_id INT UNSIGNED DEFAULT NULL,
    description VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_blog_activity_user_id (user_id),
    INDEX idx_blog_activity_action (action),
    INDEX idx_blog_activity_entity (entity_type, entity_id),
    INDEX idx_blog_activity_created_at (created_at),

    CONSTRAINT fk_blog_activity_user FOREIGN KEY (user_id) REFERENCES blog_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
