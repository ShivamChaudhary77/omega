-- Blog CMS — Admin users (completely separate from any customer/lead table;
-- these are internal team accounts for the admin dashboard only).
--
-- No default row is inserted here on purpose — see database/README.md
-- "Creating the first admin" for why a hardcoded password hash is not
-- shipped in a SQL file that could end up in version control.

CREATE TABLE IF NOT EXISTS blog_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'editor', 'author') NOT NULL DEFAULT 'author',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blog_users_email (email),
    INDEX idx_blog_users_role (role),
    INDEX idx_blog_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
