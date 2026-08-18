<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

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

$task_id = $data['task_id'] ?? null;
$note = $data['notes'] ?? '';
$action = $data['action'] ?? 'note';

if (!$task_id || !$note) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing task_id or note'
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

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return [];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : [];
}

// ==========================
// FETCH TASK
// ==========================
$task = supabaseGet(
    "$supabase_url/rest/v1/tasks?id=eq.$task_id&select=id,title,assigned_to",
    $supabase_key
);

$task = $task[0] ?? null;

if (!$task) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Task not found'
    ]);
    exit;
}

$title = $task['title'];
$assigned_email = $task['assigned_to'];

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
            $user['email'] === $assigned_email
        )
    ) {
        $recipients[] = $user['email'];
    }
}

$recipients = array_unique($recipients);

// ==========================
// EMAIL CONTENT
// ==========================
$subject = "New Task Note: $title";

$body = "
<div style='font-family: Arial, sans-serif; background:#f4f6f9; padding:20px;'>
   <img 
                src='https://www.texolenergies.com/assets/Logo-paGHQfRF.svg'
                alt='Texol Energies'
                style='width:140px; margin-bottom:10px;'
            />
    <div style='max-width:600px; margin:0 auto; background:#ffffff; border-radius:10px;'>

        <div style='background:#1f3c88; color:#fff; padding:20px; text-align:center;'>
            <h2>Task Note Added</h2>
        </div>

        <div style='padding:25px;'>
            <p><strong>Task:</strong> $title</p>

            <div style='background:#f7f9fc; padding:15px; border-radius:8px;'>
                <strong>Note:</strong><br><br>
                $note
            </div>
        </div>

        <div style='padding:15px; text-align:center; font-size:12px; color:#777;'>
            THI Support System
        </div>

    </div>

</div>
";

// ==========================
// SEND EMAIL
// ==========================
$mail = new PHPMailer(true);

try {

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

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Task note notification sent',
        'recipients' => $recipients
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $mail->ErrorInfo,
        'debug' => $e->getMessage()
    ]);
}