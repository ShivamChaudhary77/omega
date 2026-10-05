<?php
/**
 * Shared PDO connection for the Blog CMS.
 *
 * Deliberately reuses the SAME credentials the existing site already uses
 * (php/contact-submit.php, php/consultation-leads-submit.php) via
 * php/config.php, rather than duplicating DB_HOST/DB_NAME/DB_USER/
 * DB_PASSWORD in a second config file. There is exactly one MySQL database
 * for this whole site; the blog_* tables just live in it alongside
 * contact_form_submissions and consultation_leads.
 */

declare(strict_types=1);

if (!defined('BLOG_CMS_ROOT')) {
    define('BLOG_CMS_ROOT', dirname(__DIR__));
}
// Site root is one level above blog-cms/
if (!defined('SITE_ROOT')) {
    define('SITE_ROOT', dirname(BLOG_CMS_ROOT));
}

require_once SITE_ROOT . '/php/config.php';

function blog_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
