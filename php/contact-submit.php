<?php
/**
 * Contact form submission handler. Accepts POST (JSON or form-encoded),
 * validates server-side, and inserts into contact_form_submissions via a
 * prepared statement. Always responds with JSON.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    // Fall back to standard form-encoded POST in case the client sends that instead.
    $data = $_POST;
}

// Honeypot: a field named "website" that's hidden from real users via CSS.
// Bots that fill every field trip this; report success without touching the DB.
if (!empty($data['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

function clean_str($value, int $maxLen): string
{
    $value = is_string($value) ? trim($value) : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value); // strip control chars
    return mb_substr($value, 0, $maxLen);
}

$fullName = clean_str($data['full_name'] ?? '', 150);
$phoneNumber = clean_str($data['phone_number'] ?? '', 20);
$city = clean_str($data['city'] ?? '', 100);
$projectDetails = clean_str($data['project_details'] ?? '', 65000);
$pageUrl = clean_str($data['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''), 500);
$referrerUrl = clean_str($data['referrer_url'] ?? '', 500);

// Server-side validation — the real gate; client-side validation is UX only
// and can always be bypassed.
$errors = [];
if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
}
if ($phoneNumber === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phoneNumber)) {
    $errors['phone_number'] = 'Enter a valid phone number.';
}
if ($city === '') {
    $errors['city'] = 'City is required.';
}
if ($projectDetails === '') {
    $errors['project_details'] = 'Please tell us a bit about your project.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
if ($userAgent !== null) {
    $userAgent = mb_substr($userAgent, 0, 1000);
}

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO contact_form_submissions
            (full_name, phone_number, city, project_details, page_url, referrer_url, ip_address, user_agent)
         VALUES (:full_name, :phone_number, :city, :project_details, :page_url, :referrer_url, :ip_address, :user_agent)'
    );
    $stmt->execute([
        ':full_name' => $fullName,
        ':phone_number' => $phoneNumber,
        ':city' => $city,
        ':project_details' => $projectDetails,
        ':page_url' => $pageUrl,
        ':referrer_url' => $referrerUrl !== '' ? $referrerUrl : null,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent,
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Never echo DB error details to the client — log server-side only.
    error_log('[contact-submit] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Something went wrong on our end. Please call or WhatsApp us instead.',
    ]);
}
