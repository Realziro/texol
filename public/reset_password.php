<?php
session_start();
require_once __DIR__ . '/../config.php';
date_default_timezone_set('Africa/Nairobi');

$token = $_GET['token'] ?? '';
$message = '';
$error = '';
$success = false;

if ($token === '') {
    die("Invalid reset link.");
}

$supabaseUrl = rtrim(SUPABASE_URL, '/');
$supabaseKey = SUPABASE_ANON_KEY;

/**
 * 1. Get user by token
 */
$queryUrl = $supabaseUrl . "/rest/v1/users?"
    . "select=email,reset_expires"
    . "&reset_token=eq." . urlencode($token)
    . "&limit=1";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $queryUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$user = json_decode($response, true);

if (!is_array($user) || count($user) === 0) {
    die("Invalid or expired token.");
}

$record = $user[0];
$email = $record['email'] ?? '';

if (!empty($record['reset_expires']) && strtotime($record['reset_expires']) < time()) {
    die("Token has expired.");
}

/**
 * 2. Handle reset
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $passwordPattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$/";

    if ($password === '' || $confirmPassword === '') {
        $error = "All fields are required.";
    }
    elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    }
    elseif (!preg_match($passwordPattern, $password)) {
        $error = "Password must be 8+ chars with uppercase, lowercase, number & symbol.";
    }
    else {

        $updateData = [
            'temp_password' => $password,
            'reset_token' => null,
            'reset_expires' => null
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . "/rest/v1/users?email=eq." . urlencode($email),
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

        $success = true;
        $message = "Password updated successfully. You can now login.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .box {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        h3 {
            margin-bottom: 15px;
            text-align: center;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            outline: none;
            font-size: 14px;
        }

        input:focus {
            border-color: #0d6efd;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #198754;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #157347;
        }

        .error {
            color: red;
            font-size: 13px;
            text-align: center;
        }

        .success {
            color: green;
            font-size: 14px;
            text-align: center;
        }

        .login-btn {
            display: block;
            text-align: center;
            margin-top: 15px;
            padding: 12px;
            background: #0d6efd;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
        }

        .login-btn:hover {
            background: #0b5ed7;
        }

        @media (max-width: 480px) {
            .box {
                margin: 15px;
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="box">

    <h3>Reset Password</h3>

    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if ($message): ?>
        <p class="success"><?php echo $message; ?></p>
    <?php endif; ?>

    <?php if (!$success): ?>
        <form method="POST">

            <input type="password" name="password" placeholder="New password" required>

            <input type="password" name="confirm_password" placeholder="Confirm password" required>

            <button class="btn" type="submit">Reset Password</button>

        </form>
    <?php else: ?>
        <a href="login.php" class="login-btn">Go to Login</a>
    <?php endif; ?>

</div>

</body>
</html>