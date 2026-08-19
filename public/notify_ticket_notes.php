<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

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

$ticket_id = $data['ticket_id'] ?? null;
$note = $data['notes'] ?? '';
$action = $data['action'] ?? 'note';
$added_by = $data['added_by'] ?? 'Unknown User';

if (!$ticket_id || !$note) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing ticket_id or note'
    ]);
    exit;
}

// ==========================
// SUPABASE CONFIG
// ==========================
$supabase_url = "https://pjwvfuyzbzayxqxisysi.supabase.co";
$supabase_key = "sb_publishable_0irc-UsepAkekLihnXz8Mw_ouNmhqld"; // IMPORTANT: server only

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
// FETCH TICKET
// ==========================
$ticket = supabaseGet(
    "$supabase_url/rest/v1/tickets?id=eq.$ticket_id&select=ticket_id,title,description,requested_by",
    $supabase_key
);

$ticket = $ticket[0] ?? null;

if (!$ticket) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Ticket not found'
    ]);
    exit;
}

$ticket_id_display = $ticket['ticket_id'];
$title = $ticket['title'];
$description = $ticket['description'] ?? 'No description';
$requested_by_id = $ticket['requested_by'];

// ==========================
// FETCH REQUESTER DETAILS
// ==========================
$requester = supabaseGet(
    "$supabase_url/rest/v1/users?id=eq.$requested_by_id&select=email,full_name",
    $supabase_key
);

$requester = $requester[0] ?? null;
$requester_email = $requester['email'] ?? null;
$requester_name = $requester['full_name'] ?? $requester_email;

// ==========================
// FETCH USERS
// ==========================
$users = supabaseGet(
    "$supabase_url/rest/v1/users?select=email,role",
    $supabase_key
);

$recipients = [];

foreach ($users as $user) {

    if (
        isset($user['email']) &&
        (
            strtolower($user['role']) === 'admin' ||
            $user['email'] === $requester_email
        )
    ) {
        $recipients[] = $user['email'];
    }
}

$recipients = array_unique($recipients);

// ==========================
// EMAIL CONTENT
// ==========================
$subject = "New Note Added to Ticket: $title";

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

            <h2 style='margin:0; font-size:20px;'>New Note Added to Ticket</h2>

        </div>

        <!-- BODY -->
        <div style='padding:25px;'>

            <!-- TICKET ID -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Ticket ID:</strong> $ticket_id_display
            </p>

            <!-- TITLE -->
            <h3 style='margin:0 0 10px; font-size:18px; color:#333;'>$title</h3>

            <!-- DESCRIPTION -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Description:</strong><br>
                $description
            </p>

            <!-- REQUESTED BY -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                <strong>Requested By:</strong> $requester_name
            </p>

            <!-- NOTE -->
            <div style='background:#f7f9fc; padding:15px; border-radius:8px; margin-bottom:20px;'>
                <strong style='color:#1f3c88; font-size:16px;'>New Note:</strong><br><br>
                <p style='font-size:14px; color:#555; line-height:1.6; margin:0;'>$note</p>
                <p style='font-size:12px; color:#777; line-height:1.6; margin:10px 0 0 0;'>
                    <em>Added by: $added_by</em>
                </p>
            </div>

            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                <a href=\"https://support.texolenergies.com/tickets?ticket_id=$ticket_id_display\" style='color:#1f3c88; text-decoration:none; font-weight:bold;'>View Ticket</a>
            </p>

            <!-- FOOTER TAGS -->
            <div style='margin-top:25px; text-align:center;'>

                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#1f3c88; color:#fff; margin:3px;'>
                    Ticket Note Notification
                </span>

                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#e9f7ef; color:#1e7e34; margin:3px;'>
                    System Generated
                </span>

            </div>

        </div>

        <!-- FOOTER -->
        <div style='background:#f4f6f9; padding:15px; text-align:center; font-size:12px; color:#777;'>

            <p style='margin:0;'>Texol Energies - THI Support </p>
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

    if (empty($recipients)) {
        throw new Exception("No recipients found");
    }

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
        'message' => 'Ticket note notification sent',
        'recipients' => $recipients
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $mail->ErrorInfo,
        'debug' => $e->getMessage()
    ]);
}
