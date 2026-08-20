<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header('Content-Type: application/json');

// ==========================
// SAFE INPUT PARSE
// ==========================
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON input'
    ]);
    exit;
}

$type = $data['type'] ?? ''; // 'requisition' or 'feedback'
$to_email = $data['to'] ?? '';
$cc_emails = $data['cc'] ?? '';
$email_subject = $data['subject'] ?? '';
$email_body = $data['body'] ?? '';

if (!$to_email || !$email_subject || !$email_body) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields: to, subject, or body'
    ]);
    exit;
}

// ==========================
// SEND EMAIL VIA SENDMAIL.PHP
// ==========================
$postData = [
    'to' => $to_email,
    'cc' => $cc_emails,
    'subject' => $email_subject,
    'body' => $email_body
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'sendmail.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('CURL Error in notify_requisition.php: ' . $curlError);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to sendmail.php'
    ]);
    exit;
}

$result = json_decode($response, true);

if ($result && isset($result['status']) && $result['status'] === 'success') {
    echo json_encode([
        'status' => 'success',
        'message' => 'Email sent successfully'
    ]);
} else {
    error_log('Sendmail.php error: ' . $response);
    echo json_encode([
        'status' => 'error',
        'message' => $result['message'] ?? 'Failed to send email'
    ]);
}
