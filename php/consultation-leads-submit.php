<?php
/**
 * free-design-consultation/ lead form submission handler. Accepts POST
 * (JSON or form-encoded), validates server-side, and inserts into
 * consultation_leads via a prepared statement. Always responds with JSON.
 *
 * Deliberately mirrors php/contact-submit.php's structure (same repo, same
 * host, same DB) rather than introducing a second pattern for form-to-DB
 * logic — see README-CONSULTATION-LANDING.md for why.
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
    $data = $_POST;
}

// Honeypot: a field named "website", hidden from real users via CSS.
if (!empty($data['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

function clean_str($value, int $maxLen): string
{
    $value = is_string($value) ? trim($value) : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    return mb_substr($value, 0, $maxLen);
}

$projectType = clean_str($data['project_type'] ?? '', 50);
$configuration = clean_str($data['configuration'] ?? '', 50);
$budgetRange = clean_str($data['budget_range'] ?? '', 50);
$timeline = clean_str($data['timeline'] ?? '', 50);
$fullName = clean_str($data['full_name'] ?? '', 150);
$phoneNumber = clean_str($data['phone_number'] ?? '', 20);
$city = clean_str($data['city'] ?? '', 100);
$pageUrl = clean_str($data['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''), 500);
$referrerUrl = clean_str($data['referrer_url'] ?? '', 500);

// is_qualified is sent by the client (js/consultation-form.js) as a UX
// convenience for firing the lead_qualified GA4 event immediately, but the
// value actually stored is always recomputed here server-side — never
// trust a client-supplied boolean for a field used in lead-routing/reporting.
$belowStartingPrice = $budgetRange === 'Under ₹15 Lakhs';
$justExploring = $timeline === 'Just Exploring';
$isQualified = (!$belowStartingPrice && !$justExploring) ? 1 : 0;

$errors = [];
if ($projectType === '') {
    $errors['project_type'] = 'Please select a project type.';
}
if ($configuration === '') {
    $errors['configuration'] = 'Please select a configuration.';
}
if ($budgetRange === '') {
    $errors['budget_range'] = 'Please select a budget range.';
}
if ($timeline === '') {
    $errors['timeline'] = 'Please select a timeline.';
}
if ($fullName === '') {
    $errors['full_name'] = 'Full name is required.';
}
if ($phoneNumber === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phoneNumber)) {
    $errors['phone_number'] = 'Enter a valid phone number.';
}
if ($city === '') {
    $errors['city'] = 'City is required.';
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
        'INSERT INTO consultation_leads
            (project_type, configuration, budget_range, timeline, full_name, phone_number, city,
             is_qualified, page_url, referrer_url, ip_address, user_agent)
         VALUES (:project_type, :configuration, :budget_range, :timeline, :full_name, :phone_number, :city,
             :is_qualified, :page_url, :referrer_url, :ip_address, :user_agent)'
    );
    $stmt->execute([
        ':project_type' => $projectType,
        ':configuration' => $configuration,
        ':budget_range' => $budgetRange,
        ':timeline' => $timeline,
        ':full_name' => $fullName,
        ':phone_number' => $phoneNumber,
        ':city' => $city,
        ':is_qualified' => $isQualified,
        ':page_url' => $pageUrl,
        ':referrer_url' => $referrerUrl !== '' ? $referrerUrl : null,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent,
    ]);

    echo json_encode(['success' => true, 'is_qualified' => (bool) $isQualified]);
} catch (PDOException $e) {
    error_log('[consultation-leads-submit] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Something went wrong on our end. Please call or WhatsApp us instead.',
    ]);
}
