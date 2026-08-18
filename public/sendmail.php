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

// Get POST data
$to_email = $_POST['to'] ?? '';
$cc_emails = $_POST['cc'] ?? '';
$email_subject = $_POST['subject'] ?? '';
$email_body = $_POST['body'] ?? 'This is a notification email.';

if (!$to_email || !$email_subject) {
    echo json_encode(['status' => 'error', 'message' => 'Missing to or subject']);
    exit;
}

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host       = 'mail.texolenergies.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'support@texolenergies.com';
    $mail->Password   = 'realziro@1997';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('support@texolenergies.com', 'THI Support');

    // Main recipient
    $mail->addAddress($to_email);

    // Handle CC emails
    if (!empty($cc_emails)) {
        $cc_list = explode(',', $cc_emails);
        foreach ($cc_list as $cc) {
            $cc = trim($cc);
            if (!empty($cc)) {
                $mail->addCC($cc);
            }
        }
    }

    $mail->isHTML(true);
    $mail->Subject = $email_subject;
    $mail->Body    = $email_body;
    $mail->AltBody = strip_tags($email_body);

    $mail->send();

    echo json_encode(['status' => 'success', 'message' => 'Email sent successfully']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $mail->ErrorInfo]);
}