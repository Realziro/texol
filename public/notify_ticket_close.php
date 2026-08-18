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

$ticket_id = $data['ticket_id'] ?? null;
$action = $data['action'] ?? 'update';

if (!$ticket_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ticket ID']);
    exit;
}

// ==========================
// SUPABASE CONFIG
// ==========================
$supabase_url = "https://pjwvfuyzbzayxqxisysi.supabase.co";
$supabase_key = "sb_publishable_0irc-UsepAkekLihnXz8Mw_ouNmhqld";

// ==========================
// HELPER
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
// FETCH TICKET
// ==========================
$ticket = supabaseGet(
    "$supabase_url/rest/v1/tickets?id=eq.$ticket_id&select=*",
    $supabase_key
);

$ticket = $ticket[0] ?? null;

if (!$ticket) {
    echo json_encode(['status' => 'error', 'message' => 'Ticket not found']);
    exit;
}

// ==========================
// FIELDS
// ==========================
$title = $ticket['title'];
$requester = $ticket['requester'];
$source = $ticket['source'];
$description = $ticket['description'];
$department = $ticket['department'];
$category = $ticket['category'];
$status = $ticket['status'];
$urgency = $ticket['urgency'];
$impact = $ticket['impact'];
$priority = $ticket['priority'];
$planned_start = $ticket['planned_start_date'];
$planned_end = $ticket['planned_end_date'];
$cc_emails = $ticket['cc_emails'] ?? '';

// ==========================
// ASSIGNEES (comma emails)
// ==========================
$assignees = supabaseGet(
    "$supabase_url/rest/v1/ticket_assignees?select=technician_email&ticket_id=eq.$ticket_id",
    $supabase_key
);

// collect technician emails
$techEmails = [];

if (!empty($assignees)) {
    foreach ($assignees as $a) {
        if (!empty($a['technician_email'])) {
            $parts = explode(',', $a['technician_email']);
            foreach ($parts as $p) {
                $techEmails[] = trim($p);
            }
        }
    }
}

$techEmails = array_unique($techEmails);

// ==========================
// GET TECHNICIAN NAMES
// ==========================
$techNames = [];

if (!empty($techEmails)) {

    foreach ($techEmails as $email) {

        $user = supabaseGet(
            "$supabase_url/rest/v1/users?select=fullname,email&email=eq.$email",
            $supabase_key
        );

        if (!empty($user[0]['fullname'])) {
            $techNames[] = $user[0]['full_name'];
        } else {
            $techNames[] = $email;
        }
    }
}

// ==========================
// RECIPIENTS
// ==========================
$recipients = [];

// requester
if (!empty($requester)) {
    $recipients[] = $requester;
}

$requester_email = $ticket['requester'] ?? null;
$requester_name = $requester_email; // fallback

if (!empty($requester_email)) {

    $requester_user = supabaseGet(
        "$supabase_url/rest/v1/users?select=fullname,email&email=eq.$requester_email",
        $supabase_key
    );

    if (!empty($requester_user) && isset($requester_user[0]['full_name'])) {
        $requester_name = $requester_user[0]['full_name'];
    }
}
// CC emails
if (!empty($cc_emails)) {
    foreach (explode(',', $cc_emails) as $cc) {
        $cc = trim($cc);
        if ($cc) $recipients[] = $cc;
    }
}

// technicians
foreach ($techEmails as $email) {
    $recipients[] = $email;
}

// admins
$users = supabaseGet(
    "$supabase_url/rest/v1/users?select=email,role",
    $supabase_key
);

foreach ($users as $user) {
    if (strtolower($user['role']) === 'admin') {
        $recipients[] = $user['email'];
    }
}

$recipients = array_unique($recipients);

// ==========================
// SUBJECT
// ==========================
$subject = "Ticket Update: $title";

// ==========================
// BODY
// ==========================
$body = "
<div style='font-family: Arial, sans-serif; background:#f4f6f9; padding:20px;'>
   <img 
                src='https://texolenergies.com/assets/Logo-paGHQfRF.svg' 
                alt='Texol Energies'
                style='width:140px; margin-bottom:10px; display:block; margin-left:auto; margin-right:auto;'
            />
    <div style='max-width:700px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);'>

        <!-- HEADER -->
        <div style='background:#0d6efd; color:#fff; padding:22px; text-align:center;'>
            <h2 style='margin:0;'>Ticket Update</h2>
        </div>

        <!-- BODY -->
        <div style='padding:25px;'>

            <h3 style='margin:0 0 10px;'>$title</h3>

            <div style='margin-bottom:15px;'>

                <span style='padding:6px 10px; background:#e8f0ff; border-radius:20px; font-size:12px; margin:3px;'>Status: $status</span>
                <span style='padding:6px 10px; background:#fff4e5; border-radius:20px; font-size:12px; margin:3px;'>Priority: $priority</span>
                <span style='padding:6px 10px; background:#e7f7ef; border-radius:20px; font-size:12px; margin:3px;'>Urgency: $urgency</span>
                <span style='padding:6px 10px; background:#f3e8ff; border-radius:20px; font-size:12px; margin:3px;'>Impact: $impact</span>

            </div>

            <table style='width:100%; font-size:13px; border-collapse:collapse;'>

                <tr><td><b>Requester</b></td><td>$requester</td></tr>
                <tr><td><b>Source</b></td><td>$source</td></tr>
                <tr><td><b>Department</b></td><td>$department</td></tr>
                <tr><td><b>Category</b></td><td>$category</td></tr>
                <tr><td><b>Planned Start</b></td><td>$planned_start</td></tr>
                <tr><td><b>Planned End</b></td><td>$planned_end</td></tr>

            </table>

            <hr>

            <p style='font-size:13px;'><b>Description</b></p>
            <div style='background:#f7f9fc; padding:12px; border-radius:8px;'>
                $description
            </div>

            <p style='margin-top:15px; font-size:13px;'>
                <b>Technicians:</b> " . implode(', ', $techNames) . "
            </p>

        </div>

        <!-- FOOTER -->
        <div style='background:#f4f6f9; padding:15px; text-align:center; font-size:12px; color:#777;'>
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

    foreach ($recipients as $email) {
        $mail->addAddress($email);
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Ticket update sent successfully',
        'recipients' => $recipients
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $mail->ErrorInfo
    ]);
}