<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

// ==========================
// GET INPUT
// ==========================
$data = json_decode(file_get_contents("php://input"), true);

$task_id = $data['task_id'] ?? null;
$action = $data['action'] ?? 'update';

if (!$task_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing task ID']);
    exit;
}

// ==========================
// SUPABASE REST API CONFIG
// ==========================
$supabase_url = "https://pjwvfuyzbzayxqxisysi.supabase.co";
$supabase_key = "sb_publishable_0irc-UsepAkekLihnXz8Mw_ouNmhqld"; // IMPORTANT: server only

// ==========================
// FETCH TASK
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
    curl_close($ch);

    return json_decode($response, true);
}

$task = supabaseGet(
    "$supabase_url/rest/v1/tasks?id=eq.$task_id&select=*",
    $supabase_key
);

$task = $task[0] ?? null;

if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found']);
    exit;
}

// ==========================
// FETCH USERS (ASSIGNED + ADMINS)
// ==========================
$assigned_email = $task['assigned_to'];
$job_card_id = $task['job_card_id'];

$users = supabaseGet(
    "$supabase_url/rest/v1/users?select=email,role",
    $supabase_key
);

$recipients = [];

foreach ($users as $user) {

    if (
        $user['role'] === 'admin' ||
        $user['role'] === 'Admin' ||
        $user['email'] === $assigned_email
    ) {
        $recipients[] = $user['email'];
    }
}

$recipients = array_unique($recipients);

// ==========================
// EMAIL TEMPLATE
// ==========================
$title = $task['title'];
$status = $task['status'];
$priority = $task['priority'];
$description = $task['description'];

switch ($action) {

    case 'update':
        $subject = "Task Updated: $title";
        $heading = "Task Updated";
        break;

    case 'assigned':
        $subject = "Task Assigned: $title";
        $heading = "Task Assignment";
        break;

    default:
        $subject = "Task Notification: $title";
        $heading = "Task Update";
}

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

         

            <h2 style='margin:0; font-size:20px;'>$heading</h2>

        </div>

        <!-- BODY -->
        <div style='padding:25px;'>

            <!-- TITLE -->
            <h3 style='margin:0 0 10px; font-size:18px; color:#333;'>$title</h3>

            <!-- DESCRIPTION -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                $description
            </p>

            <!-- BADGES -->
            <div style='margin-bottom:20px;'>

                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#e8f0ff; color:#1f3c88; margin:3px;'>
                    Status: $status
                </span>

                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#fff4e5; color:#b26a00; margin:3px;'>
                    Priority: $priority
                </span>

            </div>



            <!-- FOOTER TAGS -->
            <div style='margin-top:25px; text-align:center;'>

                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#1f3c88; color:#fff; margin:3px;'>
                    Task Notification
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

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Notification sent',
        'recipients' => $recipients
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $mail->ErrorInfo
    ]);
}