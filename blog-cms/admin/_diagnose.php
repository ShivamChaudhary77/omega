<?php
/**
 * TEMPORARY diagnostic page — delete this file once the CMS is working.
 * Shows PHP errors directly in the browser (overriding production's
 * display_errors=off just for this one request) and checks each dependency
 * step by step, so a fatal error shows exactly which step failed instead of
 * a blank 500.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: text/plain; charset=utf-8');

echo "=== Blog CMS Diagnostic ===\n\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Current file: " . __FILE__ . "\n";
echo "SAPI: " . php_sapi_name() . "\n\n";

echo "--- Step 1: locate php/config.php ---\n";
$configPath = dirname(__DIR__, 2) . '/php/config.php';
echo "Looking for: $configPath\n";
if (!is_file($configPath)) {
    echo "RESULT: FAIL — config.php does not exist at that path.\n";
    exit;
}
echo "RESULT: OK — file exists.\n\n";

echo "--- Step 2: load config.php ---\n";
try {
    require_once $configPath;
    echo "RESULT: OK — loaded. DB_HOST=" . (defined('DB_HOST') ? DB_HOST : 'UNDEFINED')
        . ", DB_NAME=" . (defined('DB_NAME') ? DB_NAME : 'UNDEFINED') . "\n\n";
} catch (Throwable $e) {
    echo "RESULT: FAIL — " . get_class($e) . ": " . $e->getMessage() . "\n";
    exit;
}

echo "--- Step 3: connect to MySQL via PDO ---\n";
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "RESULT: OK — connected.\n\n";
} catch (Throwable $e) {
    echo "RESULT: FAIL — " . get_class($e) . ": " . $e->getMessage() . "\n";
    exit;
}

echo "--- Step 4: check each blog_* table exists ---\n";
foreach (['blog_categories', 'blog_users', 'blog_posts', 'blog_media', 'blog_revisions', 'blog_activity_logs'] as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  $table: OK ($count rows)\n";
    } catch (Throwable $e) {
        echo "  $table: FAIL — " . $e->getMessage() . "\n";
    }
}
echo "\n";

echo "--- Step 5: load the full includes/ chain used by setup.php and login.php ---\n";
try {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/csrf.php';
    require_once __DIR__ . '/../includes/validation.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/activity_log.php';
    require_once __DIR__ . '/../includes/permissions.php';
    echo "RESULT: OK — all includes loaded without error.\n\n";
} catch (Throwable $e) {
    echo "RESULT: FAIL — " . get_class($e) . ": " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit;
}

echo "--- Step 6: start a session (as auth.php's auth_start_session() does) ---\n";
try {
    auth_start_session();
    echo "RESULT: OK — session started. Session save path: " . session_save_path() . " (writable: " . (is_writable(session_save_path() ?: sys_get_temp_dir()) ? 'yes' : 'no') . ")\n\n";
} catch (Throwable $e) {
    echo "RESULT: FAIL — " . get_class($e) . ": " . $e->getMessage() . "\n";
    exit;
}

echo "=== All checks passed. If setup.php/login.php still fail, the issue is likely\n";
echo "    Apache-level (.htaccess / mod_rewrite / mod_security), not PHP-level. ===\n";
