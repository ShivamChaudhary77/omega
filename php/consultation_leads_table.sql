-- Run this once against the production database (hPanel → phpMyAdmin, or
-- your MySQL client of choice) before the free-design-consultation/ form
-- goes live. No table prefix — matches the existing contact_form_submissions
-- table's convention; there is no WordPress/wp_ install on this site.
CREATE TABLE consultation_leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_type VARCHAR(50) NOT NULL,
    configuration VARCHAR(50) NOT NULL,
    budget_range VARCHAR(50) NOT NULL,
    timeline VARCHAR(50) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    is_qualified TINYINT(1) NOT NULL DEFAULT 0,
    page_url VARCHAR(500) NOT NULL,
    referrer_url VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_number (phone_number),
    INDEX idx_is_qualified (is_qualified),
    INDEX idx_submitted_at (submitted_at)
);
