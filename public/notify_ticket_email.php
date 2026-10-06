<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit;
}

$ticketId = trim((string) ($payload['ticket_id'] ?? ''));
$action = trim((string) ($payload['action'] ?? ''));
$sourceModule = trim((string) ($payload['source_module'] ?? 'tickets'));
$allowedActions = ['created', 'updated', 'assigned', 'note', 'closed'];
if ($ticketId === '' || !in_array($action, $allowedActions, true) || !in_array($sourceModule, ['tickets', 'mytickets', 'shared', 'admintickets'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ticket ID, source module, and a supported event are required']);
    exit;
}

$permissionAction = $action === 'created'
    || ($action === 'assigned' && ($payload['permission_action'] ?? '') === 'create')
    ? 'create'
    : 'edit';
$ticketFilters = [];
if ($sourceModule === 'mytickets') {
    if (!in_array($action, ['created', 'updated', 'note'], true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'This event is not allowed from My Tickets']);
        exit;
    }

    $ownerId = trim((string) ($_SESSION['user_id'] ?? ''));
    if ($ownerId === '') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'A user ID is required for My Tickets notifications']);
        exit;
    }
    $ticketFilters = ['requested_by' => 'eq.' . $ownerId];
} elseif (in_array($sourceModule, ['shared', 'admintickets'], true)) {
    if ($action !== 'note') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Only note notifications are allowed from this page']);
        exit;
    }
    if ($sourceModule === 'admintickets' && !is_admin()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Admin access is required']);
        exit;
    }
} else {
    require_permission('tickets', $permissionAction);
    $ticketFilters = scope_filter_params('tickets', $permissionAction, 'department', ['requested_by']);
}

if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Supabase service configuration is unavailable']);
    exit;
}

function ticket_mail_get(string $resource, array $params = []): array
{
    $path = $resource;
    if ($params) {
        $path .= '?' . http_build_query($params);
    }
    $result = auth_supabase('GET', $path);
    return ($result['code'] >= 200 && $result['code'] < 300 && is_array($result['data'])) ? $result['data'] : [];
}

$ticketRows = ticket_mail_get('tickets', array_merge([
    'select' => 'id,ticket_id,title,description,requested_by,department,status,priority,branch,category',
    'id' => 'eq.' . $ticketId,
    'limit' => 1,
], $ticketFilters));
$ticket = $ticketRows[0] ?? null;
if (!$ticket) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Ticket not found']);
    exit;
}

if ($sourceModule === 'shared' && !is_admin()) {
    $assigneeRows = ticket_mail_get('ticket_assignees', [
        'select' => 'technician_email',
        'ticket_id' => 'eq.' . $ticket['id'],
        'technician_email' => 'eq.' . current_user_email(),
        'limit' => 1,
    ]);
    if (!$assigneeRows) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Only assigned technicians can send this note notification']);
        exit;
    }
}

$requesterValue = trim((string) ($ticket['requested_by'] ?? ''));
$requesterFilter = filter_var($requesterValue, FILTER_VALIDATE_EMAIL)
    ? ['email' => 'eq.' . $requesterValue]
    : ['id' => 'eq.' . $requesterValue];
$requesterRows = $requesterValue === '' ? [] : ticket_mail_get('users', array_merge(
    ['select' => 'id,email,full_name', 'limit' => 1],
    $requesterFilter
));
$requester = $requesterRows[0] ?? [];
$ticket['requester_email'] = $requester['email'] ?? (filter_var($requesterValue, FILTER_VALIDATE_EMAIL) ? $requesterValue : '');
$ticket['requester_name'] = $requester['full_name'] ?? $ticket['requester_email'];

$eventTemplates = [
    'created' => 'created.php',
    'updated' => 'updated.php',
    'assigned' => 'assigned.php',
    'note' => 'note.php',
    'closed' => 'closed.php',
];
$template = require __DIR__ . '/../MailTemplates/tickets/' . $eventTemplates[$action];
$rpc = auth_supabase('POST', 'rpc/get_notification_recipients', [
    'p_event' => $template['event_key'],
    'p_department' => $ticket['department'] ?? '',
    'p_requester_id' => $requester['id'] ?? null,
]);

$recipients = [];
if ($rpc['code'] === 200 && is_array($rpc['data'])) {
    foreach ($rpc['data'] as $recipient) {
        $email = $recipient['email'] ?? '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $email;
        }
    }
}

$adminRows = ticket_mail_get('users', [
    'select' => 'email',
    'role' => 'ilike.admin',
    'status' => 'eq.active',
]);
foreach ($adminRows as $admin) {
    $email = $admin['email'] ?? '';
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = $email;
    }
}

$ticketDepartment = trim((string) ($ticket['department'] ?? ''));
if ($ticketDepartment !== '') {
    $hodRows = ticket_mail_get('users', [
        'select' => 'email,department',
        'role' => 'ilike.hod',
        'status' => 'eq.active',
    ]);
    foreach ($hodRows as $hod) {
        if (strcasecmp(trim((string) ($hod['department'] ?? '')), $ticketDepartment) !== 0) {
            continue;
        }

        $email = $hod['email'] ?? '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $email;
        }
    }
}

if (filter_var($ticket['requester_email'], FILTER_VALIDATE_EMAIL)) {
    $recipients[] = $ticket['requester_email'];
}

$assignees = [];
if ($action === 'assigned') {
    $assigneeRows = ticket_mail_get('ticket_assignees', [
        'select' => 'technician_email',
        'ticket_id' => 'eq.' . $ticket['id'],
    ]);
    foreach ($assigneeRows as $assignee) {
        $email = trim((string) ($assignee['technician_email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $assignees[] = $email;
            $recipients[] = $email;
        }
    }
}

$recipients = array_values(array_unique($recipients));
if (!$recipients) {
    echo json_encode(['status' => 'success', 'message' => 'No recipients configured for this event']);
    exit;
}

require_once __DIR__ . '/../MailTemplates/tickets/render.php';
try {
    $email = render_ticket_email($action, $ticket, [
        'note' => $payload['notes'] ?? '',
        'added_by' => $payload['added_by'] ?? 'Unknown User',
        'updated_by' => $payload['updated_by'] ?? $payload['added_by'] ?? '',
        'closed_by' => $payload['closed_by'] ?? $payload['added_by'] ?? '',
        'assignees' => $assignees,
    ]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not render ticket email']);
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
    $detail = $curlError ?: ($result['message'] ?? '');
    if ($detail === '' && is_string($response)) {
        $detail = trim(preg_replace('/\s+/', ' ', strip_tags($response)));
    }
    if ($detail === '') {
        $detail = 'Unexpected sendmail response';
    }
    $detail = function_exists('mb_substr') ? mb_substr($detail, 0, 300) : substr($detail, 0, 300);
    error_log('Ticket sendmail upstream failure: HTTP ' . $httpCode . '; ' . $detail);

    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'The sendmail service failed to send the ticket notification',
        'sendmail_http_status' => $httpCode,
        'detail' => $detail,
    ]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Ticket notification sent']);
