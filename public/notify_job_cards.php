<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

require_once __DIR__ . '/../auth.php';

// Enable PHP error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$job_card_id = $data['job_card_id'] ?? null;
$action = $data['action'] ?? 'created';
$added_by = $data['added_by'] ?? 'Unknown User';

if (!$job_card_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing job_card_id'
    ]);
    exit;
}

if (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Supabase configuration not found'
    ]);
    exit;
}

$supabase_url = rtrim(SUPABASE_URL, '/');
$supabase_key = SUPABASE_ANON_KEY;

// ==========================
// SAFE CURL FUNCTION
// ==========================
function supabaseGet($url, $key) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

// ==========================
// FETCH JOB CARD
// ==========================
$job_card = supabaseGet(
    "$supabase_url/rest/v1/job_cards?id=eq.$job_card_id&select=id,job_card_number,title,description,created_by,department",
    $supabase_key
);

$job_card = $job_card[0] ?? null;

if (!$job_card) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Job card not found'
    ]);
    exit;
}

$job_card_number = $job_card['job_card_number'];
$title = $job_card['title'];
$description = $job_card['description'] ?? 'No description';
$created_by_id = $job_card['created_by'];
$department = $job_card['department'] ?? '';

// ==========================
// FETCH CREATOR DETAILS
// ==========================
$creator = supabaseGet(
    "$supabase_url/rest/v1/users?id=eq.$created_by_id&select=email,full_name",
    $supabase_key
);

$creator = $creator[0] ?? null;
$creator_email = $creator['email'] ?? null;
$creator_name = $creator['full_name'] ?? $creator_email;

// ==========================
// BUILD RECIPIENTS USING ROLE NOTIFICATIONS
// ==========================
$recipients = [];

// Determine event key based on action
$event_key = '';
if ($action === 'created') {
    $event_key = 'job_card_created';
} elseif ($action === 'updated') {
    $event_key = 'job_card_updated';
}

// Get notification recipients based on role notifications
if (!empty($event_key)) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$supabase_url/rest/v1/rpc/get_notification_recipients",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabase_key",
            "Authorization: Bearer $supabase_key",
            "Content-Type: application/json",
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'p_event' => $event_key,
            'p_department' => $department,
            'p_requester_id' => $created_by_id
        ]),
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $notification_recipients = json_decode($response, true);
        if (is_array($notification_recipients)) {
            foreach ($notification_recipients as $recipient) {
                $email = $recipient['email'] ?? '';
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
        }
    }
}

// Always add the creator
if ($creator_email) {
    $recipients[] = $creator_email;
}

$recipients = array_unique($recipients);

if (empty($recipients)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'No recipients configured for this event'
    ]);
    exit;
}

// ==========================
// EMAIL CONTENT
// ==========================
$subject = "Job Card $action: $title";

$body = "
<div style='font-family: Arial, sans-serif; background:#f4f6f9; padding:20px;'>
   <img
        src='https://texolenergies.com/assets/Logo-paGHQfRF.svg'
        alt='Texol Energies'
        style='width:140px; margin-bottom:10px; display:block; margin-left:auto; margin-right:auto;'
    />
    <div style='max-width:650px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);'>

        <!-- HEADER -->
        <div style='background:#1f3c88; color:#ffffff; padding:25px; text-align:center;'>
            <h2 style='margin:0; font-size:20px;'>Job Card $action</h2>
        </div>

        <!-- BODY -->
        <div style='padding:25px;'>

            <!-- JOB CARD NUMBER -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Job Card Number:</strong> $job_card_number
            </p>

            <!-- TITLE -->
            <h3 style='margin:0 0 10px; font-size:18px; color:#333;'>$title</h3>

            <!-- DESCRIPTION -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Description:</strong><br>
                $description
            </p>

            <!-- CREATED BY -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                <strong>Created By:</strong> $creator_name
            </p>

            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                <a href=\"https://support.texolenergies.com/job_cards\" style='color:#1f3c88; text-decoration:none; font-weight:bold;'>View Job Card</a>
            </p>

            <!-- FOOTER TAGS -->
            <div style='margin-top:25px; text-align:center;'>
                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#1f3c88; color:#fff; margin:3px;'>
                    Job Card Notification
                </span>
                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#e9f7ef; color:#1e7e34; margin:3px;'>
                    System Generated
                </span>
            </div>

        </div>

        <!-- FOOTER -->
        <div style='background:#f4f6f9; padding:15px; text-align:center; font-size:12px; color:#777;'>
            <p style='margin:0;'>Texol Energies - THI Support</p>
            <p style='margin:5px 0 0;'>Please do not reply to this email.</p>
        </div>

    </div>

</div>
";

// ==========================
// SEND EMAIL
// ==========================
$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = 'mail.texolenergies.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@texolenergies.com';
    $mail->Password = 'realziro@1997';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('support@texolenergies.com', 'THI Support');

    foreach ($recipients as $email) {
        $mail->addAddress($email);
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = strip_tags($body);

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Job card notification sent',
        'recipients' => $recipients
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $mail->ErrorInfo,
        'debug' => $e->getMessage()
    ]);
}
