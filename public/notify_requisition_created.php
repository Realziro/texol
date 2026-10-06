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

$requisition_id = $data['requisition_id'] ?? null;
$action = $data['action'] ?? 'created'; // 'created', 'approved', 'updated'
$added_by = $data['added_by'] ?? 'Unknown User';

if (!$requisition_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing requisition_id'
    ]);
    exit;
}

// ==========================
// SUPABASE CONFIG
// ==========================
require_once __DIR__ . '/../auth.php';

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
// FETCH REQUISITION
// ==========================
$requisition = supabaseGet(
    "$supabase_url/rest/v1/requisitions?id=eq.$requisition_id&select=*,items:requisition_items(id,item:items(id,name,unit),quantity,unit_price)",
    $supabase_key
);

$requisition = $requisition[0] ?? null;

if (!$requisition) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Requisition not found'
    ]);
    exit;
}

$requisition_number = $requisition['requisition_number'];
$description = $requisition['description'] ?? 'No description';
$requested_by_id = $requisition['requested_by'];
$department = $requisition['department'] ?? '';
$required_date = $requisition['required_date'] ?? '';
$shared_with = $requisition['shared_with'] ?? '';
$status = $requisition['status'] ?? '';
$approval_progress = $requisition['approval_progress'] ?? 0;

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
// BUILD RECIPIENTS LIST USING ROLE NOTIFICATIONS
// ==========================
$recipients = [];

// Determine event key
$event_key = '';
if ($action === 'created') {
    $event_key = 'requisition_submitted';
} elseif ($action === 'approved') {
    $event_key = 'requisition_approved';
} elseif ($action === 'updated') {
    $event_key = 'requisition_updated';
} elseif ($action === 'rejected') {
    $event_key = 'requisition_rejected';
}

// Get notification recipients based on role notifications
if (!empty($event_key)) {
    // Call get_notification_recipients RPC function
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
            'p_requester_id' => $requested_by_id
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

// Always add the requester as well (they should always know about their own requisitions)
if ($requester_email) {
    $recipients[] = $requester_email;
}

$recipients = array_unique($recipients);

if (empty($recipients)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No valid recipients found'
    ]);
    exit;
}

// ==========================
// BUILD ITEMS TABLE
// ==========================
$items_html = '';
if (isset($requisition['items']) && is_array($requisition['items'])) {
    $items_html = "<table style='width:100%; border-collapse:collapse; margin-bottom:20px;'>";
    $items_html .= "<thead><tr style='background:#f7f9fc;'>";
    $items_html .= "<th style='padding:10px; text-align:left; border-bottom:2px solid #1f3c88; font-size:12px; color:#333;'>Item</th>";
    $items_html .= "<th style='padding:10px; text-align:center; border-bottom:2px solid #1f3c88; font-size:12px; color:#333;'>Quantity</th>";
    $items_html .= "<th style='padding:10px; text-align:center; border-bottom:2px solid #1f3c88; font-size:12px; color:#333;'>Unit</th>";
    $items_html .= "<th style='padding:10px; text-align:right; border-bottom:2px solid #1f3c88; font-size:12px; color:#333;'>Unit Price</th>";
    $items_html .= "</tr></thead><tbody>";

    foreach ($requisition['items'] as $item) {
        $item_name = $item['item']['name'] ?? 'Unknown';
        $quantity = $item['quantity'] ?? 0;
        $unit = $item['item']['unit'] ?? '';
        $unit_price = $item['unit_price'] ?? 0;

        $items_html .= "<tr style='border-bottom:1px solid #e9ecef;'>";
        $items_html .= "<td style='padding:10px; font-size:13px; color:#555;'>$item_name</td>";
        $items_html .= "<td style='padding:10px; text-align:center; font-size:13px; color:#555;'>$quantity</td>";
        $items_html .= "<td style='padding:10px; text-align:center; font-size:13px; color:#555;'>$unit</td>";
        $items_html .= "<td style='padding:10px; text-align:right; font-size:13px; color:#555;'>" . number_format($unit_price, 2) . "</td>";
        $items_html .= "</tr>";
    }

    $items_html .= "</tbody></table>";
} else {
    $items_html = "<p style='font-size:14px; color:#777; font-style:italic;'>No items in this requisition</p>";
}

// ==========================
// EMAIL CONTENT
// ==========================
$action_title = '';
if ($action === 'created') {
    $action_title = 'New Requisition Created';
} elseif ($action === 'approved') {
    $action_title = 'Requisition Approved';
} elseif ($action === 'updated') {
    $action_title = 'Requisition Updated';
} else {
    $action_title = 'Requisition Notification';
}

$subject = "$action_title: $requisition_number";

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
            <h2 style='margin:0; font-size:20px;'>$action_title</h2>
        </div>

        <!-- BODY -->
        <div style='padding:25px;'>

            <!-- REQUISITION NUMBER -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Requisition Number:</strong> $requisition_number
            </p>

            <!-- STATUS -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Status:</strong> " . ucfirst($status) . "
            </p>

            <!-- DEPARTMENT -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Department:</strong> $department
            </p>

            <!-- REQUIRED DATE -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                <strong>Required Date:</strong> $required_date
            </p>

            <!-- REQUESTED BY -->
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                <strong>Requested By:</strong> $requester_name
            </p>

            <!-- DESCRIPTION -->
            <div style='background:#f7f9fc; padding:15px; border-radius:8px; margin-bottom:20px;'>
                <strong style='color:#1f3c88; font-size:16px;'>Description:</strong><br><br>
                <p style='font-size:14px; color:#555; line-height:1.6; margin:0;'>$description</p>
            </div>

            <!-- ITEMS -->
            <div style='margin-bottom:20px;'>
                <strong style='color:#1f3c88; font-size:16px;'>Items:</strong><br><br>
                $items_html
            </div>";

// Add approval progress if applicable
if ($action === 'approved') {
    $body .= "
            <!-- APPROVAL PROGRESS -->
            <div style='background:#e9f7ef; padding:15px; border-radius:8px; margin-bottom:20px;'>
                <strong style='color:#1e7e34; font-size:16px;'>Approval Progress:</strong>
                <p style='font-size:14px; color:#555; line-height:1.6; margin:10px 0 0 0;'>
                    This requisition has been approved.
                </p>
            </div>";
}

$body .= "
            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                <a href=\"https://support.texolenergies.com/requisition\" style='color:#1f3c88; text-decoration:none; font-weight:bold;'>View Requisition</a>
            </p>

            <!-- FOOTER TAGS -->
            <div style='margin-top:25px; text-align:center;'>
                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#1f3c88; color:#fff; margin:3px;'>
                    Requisition Notification
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
        'message' => 'Requisition notification sent',
        'recipients' => $recipients
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $mail->ErrorInfo,
        'debug' => $e->getMessage()
    ]);
}
