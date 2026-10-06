<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit;
}

$formId = trim((string) ($payload['form_id'] ?? ''));
if ($formId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Repair form ID is required']);
    exit;
}
if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Supabase service configuration is unavailable']);
    exit;
}

function repair_mail_get(string $resource, array $params = []): array
{
    $path = $resource . ($params ? '?' . http_build_query($params) : '');
    $result = auth_supabase('GET', $path);
    return ($result['code'] >= 200 && $result['code'] < 300 && is_array($result['data'])) ? $result['data'] : [];
}

$rows = repair_mail_get('repair_maintenance_forms', [
    'select' => 'id,form_date,station_name,repair_type,remarks,technician_name,department,requirements,shared_with,status,created_by',
    'id' => 'eq.' . $formId,
    'limit' => 1,
]);
$form = $rows[0] ?? null;
if (!$form) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Repair and maintenance form not found']);
    exit;
}

$createdBy = (string) ($form['created_by'] ?? '');
$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
$sharedIds = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($form['shared_with'] ?? ''))))));
$sharedIds = array_values(array_filter($sharedIds, static fn(string $id): bool => preg_match($uuidPattern, $id) === 1));
$currentUserId = (string) ($_SESSION['user_id'] ?? '');
if (!is_admin() && $createdBy !== $currentUserId && !in_array($currentUserId, $sharedIds, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You are not allowed to send this notification']);
    exit;
}

$recipients = [];
$reporterRows = $createdBy === '' ? [] : repair_mail_get('users', [
    'select' => 'id,email,full_name,department',
    'id' => 'eq.' . $createdBy,
    'limit' => 1,
]);
$reporter = $reporterRows[0] ?? [];
$form['reporter_email'] = $reporter['email'] ?? '';
if (empty($form['reporter_name'])) {
    $form['reporter_name'] = $reporter['full_name'] ?? $reporter['email'] ?? 'Unknown';
}
if (filter_var($form['reporter_email'], FILTER_VALIDATE_EMAIL)) {
    $recipients[] = $form['reporter_email'];
}

$adminRows = repair_mail_get('users', ['select' => 'email', 'role' => 'ilike.admin', 'status' => 'eq.active']);
foreach ($adminRows as $user) {
    if (filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) $recipients[] = $user['email'];
}

$department = trim((string) ($reporter['department'] ?? ''));
if ($department === '') {
    $department = trim((string) ($form['department'] ?? ''));
}
if ($department !== '') {
    $hodRows = repair_mail_get('users', ['select' => 'email,department', 'role' => 'ilike.hod', 'status' => 'eq.active']);
    foreach ($hodRows as $user) {
        if (strcasecmp(trim((string) ($user['department'] ?? '')), $department) === 0
            && filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $user['email'];
        }
    }
}
if ($sharedIds) {
    $sharedRows = repair_mail_get('users', ['select' => 'email', 'id' => 'in.(' . implode(',', $sharedIds) . ')']);
    foreach ($sharedRows as $user) {
        if (filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) $recipients[] = $user['email'];
    }
}

$recipientMap = [];
foreach ($recipients as $email) {
    $email = trim((string) $email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) $recipientMap[strtolower($email)] = $email;
}
$recipients = array_values($recipientMap);
if (!$recipients) {
    echo json_encode(['status' => 'success', 'message' => 'No valid recipients configured']);
    exit;
}

require_once __DIR__ . '/../MailTemplates/repair_maintenance/render.php';
try {
    $email = render_repair_maintenance_email($form);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not render repair and maintenance email']);
    exit;
}

$sendmail = curl_init('http://support.texolenergies.com/sendmail.php');
curl_setopt_array($sendmail, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; THI-Notifier/1.0)',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'to' => implode(',', $recipients),
        'subject' => $email['subject'],
        'body' => $email['body'],
    ]),
]);
$response = curl_exec($sendmail);
$httpCode = (int) curl_getinfo($sendmail, CURLINFO_HTTP_CODE);
$curlError = curl_error($sendmail);
curl_close($sendmail);
$result = is_string($response) ? json_decode($response, true) : null;
if ($response === false || $httpCode < 200 || $httpCode >= 300 || ($result['status'] ?? '') !== 'success') {
    $detail = $curlError ?: ($result['message'] ?? (is_string($response) ? substr(strip_tags($response), 0, 250) : 'Unexpected sendmail response'));
    error_log('Repair and maintenance email upstream failure: HTTP ' . $httpCode . '; ' . $detail);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Repair and maintenance email send failed', 'sendmail_http_status' => $httpCode, 'detail' => $detail]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Repair and maintenance email sent']);
