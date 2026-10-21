<?php
define('AUTH_AUTO_REQUIRE_LOGIN', false);
require_once __DIR__ . '/../auth.php';

$message = '';
$error = '';
date_default_timezone_set('Africa/Nairobi');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = "Please enter your email.";
    } else {

        $supabaseUrl = rtrim(SUPABASE_URL, '/');
        $supabaseKey = SUPABASE_ANON_KEY;

        // check user exists
        $query = http_build_query([
            'select' => 'id,email',
            'email' => 'eq.' . $email,
            'limit' => 1
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . "/rest/v1/users?$query",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $users = json_decode($response, true);

        if (!is_array($users) || count($users) === 0) {
            $error = "Email not found.";
        } else {

            // create reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // store token in DB (create columns: reset_token, reset_expires)
            $updateData = [
                'reset_token' => $token,
                'reset_expires' => $expires
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . "/rest/v1/users?email=eq.$email",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "PATCH",
                CURLOPT_POSTFIELDS => json_encode($updateData),
                CURLOPT_HTTPHEADER => [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey",
                    "Content-Type: application/json",
                    "Prefer: return=representation"
                ]
            ]);

            curl_exec($ch);
            curl_close($ch);


try {


$resetUrl = "https://support.texolenergies.com/reset_password?token=" . $token;   

$email = $email ?? '';
    
    $subject = 'Password Reset Request - Support Portal';

    $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    require __DIR__ . '/../MailTemplates/auth/reset_password.php';
    $body = ob_get_clean();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://support.texolenergies.com/sendmail.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; THI-Notifier/1.0)');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'to' => $email,
        'subject' => $subject,
        'body' => $body
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

} catch (Exception $e) {
    error_log("Error sending reset password email: " . $e->getMessage());
}


            $message = "Password reset link has been sent to your email.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
        <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">

</head>
<body style="font-family: Arial; background:#f4f6f8; display:flex; justify-content:center; align-items:center; height:100vh;">

<div style="background:#fff; padding:25px; border-radius:10px; width:350px;">

    <h3>Forgot Password</h3>

    <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>
    <?php if ($message) echo "<p style='color:green;'>$message</p>"; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Enter email"
               style="width:100%; padding:10px; margin:10px 0;" required>

        <button style="width:100%; padding:10px; background:#0d6efd; color:#fff; border:none;">
            Send Reset Link
        </button>
    </form>

</div>

</body>
</html>