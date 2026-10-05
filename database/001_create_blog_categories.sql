-- Blog CMS — Categories
-- Independent of every existing table (contact_form_submissions,
-- consultation_leads). No FOREIGN KEY to anything outside blog_* tables.
--
-- Seeded with the site's REAL existing category taxonomy (see BLOG-README.md)
-- so a post created in the CMS under "Kitchen Design" lands at the same
-- /kitchen-design/<slug>/ URL shape visitors and Google already know, not a
-- new one. Two legacy one-off directories from the original WordPress import
-- (builder-floor-interior-design-gurgaon-2026, dlf-the-arbour-interior-design-
-- cost-guide-2026 — each holds exactly one mis-categorized post, not an
-- ongoing category) are intentionally NOT seeded here; new posts shouldn't be
-- filed under them going forward.

CREATE TABLE IF NOT EXISTS blog_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    seo_title VARCHAR(255) DEFAULT NULL,
    seo_description VARCHAR(500) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blog_categories_slug (slug),
    INDEX idx_blog_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO blog_categories (name, slug, description, status) VALUES
    ('Home & Residential Interiors', 'home-residential-interiors', 'Home, apartment and villa interior design content.', 'active'),
    ('Kitchen Design', 'kitchen-design', 'Modular kitchen design, cost and planning content.', 'active'),
    ('Cost & Planning Guides', 'cost-planning-guides', 'Budgeting, cost breakdowns and project-planning content.', 'active'),
    ('Office & Commercial Interiors', 'office-commercial-interiors', 'Office and commercial interior design content.', 'active'),
    ('Design Trends & Inspiration', 'design-trends-inspiration', 'Design trend and inspiration content.', 'active'),
    ('Interior Design Insights', 'interior-design-insights', 'General interior design insight and advice content.', 'active'),
    ('Blog', 'blog', 'Fallback/general category for posts that do not fit a more specific one.', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);
