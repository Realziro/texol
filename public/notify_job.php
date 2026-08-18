<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

// ==========================
// INPUT
// ==========================
$data = json_decode(file_get_contents("php://input"), true);

$job_card_id = $data['job_card_id'] ?? null;
$action = $data['action'] ?? 'update';

if (!$job_card_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing job card ID']);
    exit;
}

// ==========================
// SUPABASE CONFIG
// ==========================
$supabase_url = "https://pjwvfuyzbzayxqxisysi.supabase.co";
$supabase_key = "sb_publishable_0irc-UsepAkekLihnXz8Mw_ouNmhqld"; // IMPORTANT: server only

// ==========================
// HELPER: FETCH FROM SUPABASE
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

// ==========================
// FETCH JOB CARD
// ==========================
$job = supabaseGet(
    "$supabase_url/rest/v1/job_cards?id=eq.$job_card_id&select=*",
    $supabase_key
);

$job = $job[0] ?? null;

if (!$job) {
    echo json_encode(['status' => 'error', 'message' => 'Job card not found']);
    exit;
}

// ==========================
// FETCH USERS (ADMIN + TECHNICIAN)
// ==========================
$technician_email = $job['technician_email'];
$technician_email = $job['technician_email'];
$technician_name = $technician_email; // fallback if not found

$tech_user = supabaseGet(
    "$supabase_url/rest/v1/users?select=fullname,email&email=eq.$technician_email",
    $supabase_key
);

if (!empty($tech_user) && isset($tech_user[0]['fullname'])) {
    $technician_name = $tech_user[0]['fullname'];
}
$users = supabaseGet(
    "$supabase_url/rest/v1/users?select=email,role",
    $supabase_key
);

$recipients = [];

foreach ($users as $user) {

    if (
        strtolower($user['role']) === 'admin' ||
        $user['email'] === $technician_email
    ) {
        $recipients[] = $user['email'];
    }
}

$recipients = array_unique($recipients);

// ==========================
// JOB CARD FIELDS
// ==========================
$title = $job['title'];
$description = $job['description'];
$status = $job['status'];
$priority = $job['priority'];
$urgency = $job['urgency'];
$impact = $job['impact'];
$category = $job['category'];
$department_id = $job['department_id'];
$due_date = $job['due_date'];
$planned_start = $job['planned_start_date'];

// ==========================
// EMAIL TEMPLATE
// ==========================
switch ($action) {

    case 'update':
        $subject = "Task Updated: $title";
        $heading = "Task Update";
        break;

    case 'assigned':
        $subject = "Job Card Assigned: $title";
        $heading = "Job Assignment";
        break;

    case 'completed':
        $subject = "Job Card Completed: $title";
        $heading = "Job Completed";
        break;

    default:
        $subject = "Job Card Notification: $title";
        $heading = "Job Update";
}

// ==========================
// EMAIL BODY
// ==========================
$body = "
<div style='font-family: Arial, sans-serif; background:#f4f6f9; padding:20px;'>
   <img 
                src='https://texolenergies.com/assets/Logo-paGHQfRF.svg' 
                alt='Texol Energies'
                style='width:140px; margin-bottom:10px; display:block; margin-left:auto; margin-right:auto;'
            />
    <div style='max-width:650px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);'>

        <!-- HEADER -->
        <div style='background:#0f2d52; color:#ffffff; padding:22px; text-align:center;'>
            <h2 style='margin:0; font-size:20px;'>$heading</h2>
        </div>

        <!-- BODY -->
        <div style='padding:25px;'>

            <h3 style='margin:0 0 10px; font-size:18px; color:#333;'>$title</h3>

            <p style='font-size:14px; color:#555; margin-bottom:20px;'>
                $description
            </p>

            <!-- BADGES -->
            <div style='margin-bottom:20px;'>

                <!-- Status -->
                <span style='display:inline-block; padding:5px 10px; border-radius:15px; font-size:12px; background:#e8f0ff; color:#0f2d52; margin:3px;'>
                    Status: $status
                </span>

                <!-- Priority -->
                <span style='display:inline-block; padding:5px 10px; border-radius:15px; font-size:12px; background:#fff4e5; color:#b26a00; margin:3px;'>
                    Priority: $priority
                </span>

                <!-- Urgency -->
                <span style='display:inline-block; padding:5px 10px; border-radius:15px; font-size:12px; background:#ffe5e5; color:#b30000; margin:3px;'>
                    Urgency: $urgency
                </span>

                <!-- Impact -->
                <span style='display:inline-block; padding:5px 10px; border-radius:15px; font-size:12px; background:#e7f7ef; color:#1e7e34; margin:3px;'>
                    Impact: $impact
                </span>

                <!-- Category -->
                <span style='display:inline-block; padding:5px 10px; border-radius:15px; font-size:12px; background:#f0f0f0; color:#333; margin:3px;'>
                    Category: $category
                </span>

            </div>

            <!-- DETAILS (SHORT FORM) -->
            <div style='font-size:13px; color:#444; line-height:1.6;'>

                <p style='margin:5px 0;'><strong>Due Date:</strong> $due_date</p>
                <p style='margin:5px 0;'><strong>Planned Start:</strong> $planned_start</p>
                <p style='margin:5px 0;'><strong>Technician:</strong> $technician_email</p>

            </div>

            <!-- FOOTER TAGS -->
            <div style='margin-top:25px; text-align:center;'>

                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#0f2d52; color:#fff;'>
                    Task Update
                </span>

                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#e9f7ef; color:#1e7e34;'>
                    System Generated
                </span>

            </div>

        </div>

        <!-- FOOTER -->
        <div style='background:#f4f6f9; padding:15px; text-align:center; font-size:12px; color:#777;'>
            <p style='margin:0;'>THI Support</p>
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
        'message' => 'Job card notification sent',
        'recipients' => $recipients
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $mail->ErrorInfo
    ]);
}