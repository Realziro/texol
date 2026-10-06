<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit;
}

$checklistId = trim((string) ($payload['checklist_id'] ?? ''));
if ($checklistId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Checklist ID is required']);
    exit;
}
if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Supabase service configuration is unavailable']);
    exit;
}

function cctv_mail_get(string $resource, array $params = []): array
{
    $path = $resource . ($params ? '?' . http_build_query($params) : '');
    $result = auth_supabase('GET', $path);
    return ($result['code'] >= 200 && $result['code'] < 300 && is_array($result['data'])) ? $result['data'] : [];
}

$rows = cctv_mail_get('cctv_checklists', [
    'select' => 'id,branch_type,checklist_date,station_data,station_count,total_cameras,functioning_cameras,created_by,reporter_name,shared_with,status',
    'id' => 'eq.' . $checklistId,
    'limit' => 1,
]);
$checklist = $rows[0] ?? null;
if (!$checklist) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'CCTV checklist not found']);
    exit;
}

$branchType = strtolower((string) ($checklist['branch_type'] ?? ''));
if (!in_array($branchType, ['texol', 'sta'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unsupported CCTV checklist type']);
    exit;
}

$createdBy = (string) ($checklist['created_by'] ?? '');
$sharedIds = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($checklist['shared_with'] ?? ''))))));
$currentUserId = (string) ($_SESSION['user_id'] ?? '');
if (!is_admin() && $createdBy !== $currentUserId && !in_array($currentUserId, $sharedIds, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You are not allowed to send this checklist notification']);
    exit;
}

$recipients = [];
$reporterRows = $createdBy === '' ? [] : cctv_mail_get('users', [
    'select' => 'id,email,full_name,department',
    'id' => 'eq.' . $createdBy,
    'limit' => 1,
]);
$reporter = $reporterRows[0] ?? [];
$reporterEmail = $reporter['email'] ?? '';
if (filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) {
    $recipients[] = $reporterEmail;
}
if (empty($checklist['reporter_name']) && !empty($reporter['full_name'])) {
    $checklist['reporter_name'] = $reporter['full_name'];
}
$reporterDepartment = trim((string) ($reporter['department'] ?? ''));

$adminRows = cctv_mail_get('users', ['select' => 'email', 'role' => 'ilike.admin', 'status' => 'eq.active']);
foreach ($adminRows as $user) {
    if (filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) $recipients[] = $user['email'];
}
if ($reporterDepartment !== '') {
    $hodRows = cctv_mail_get('users', ['select' => 'email,department', 'role' => 'ilike.hod', 'status' => 'eq.active']);
    foreach ($hodRows as $user) {
        if (strcasecmp(trim((string) ($user['department'] ?? '')), $reporterDepartment) === 0
            && filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $user['email'];
        }
    }
}

$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
$sharedIds = array_values(array_filter($sharedIds, static fn(string $id): bool => preg_match($uuidPattern, $id) === 1));
if ($sharedIds) {
    $sharedRows = cctv_mail_get('users', ['select' => 'email', 'id' => 'in.(' . implode(',', $sharedIds) . ')']);
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

require_once __DIR__ . '/../MailTemplates/cctv_checklists/render.php';
try {
    $email = render_cctv_checklist_email($checklist);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not render CCTV checklist email']);
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
    error_log('CCTV sendmail upstream failure: HTTP ' . $httpCode . '; ' . $detail);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'CCTV email send failed', 'sendmail_http_status' => $httpCode, 'detail' => $detail]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'CCTV checklist email sent']);
