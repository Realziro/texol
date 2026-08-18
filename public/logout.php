<?php
session_start();

// Clear all session data
$_SESSION = [];

// Destroy the session cookie if it exists
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Clear remember me cookie
if (isset($_COOKIE['texol_remember'])) {
    setcookie('texol_remember', '', time() - 3600, '  ');
}

// Finally destroy the session
session_destroy();

// Redirect back to login page (clean URL)
header('Location:   login');
exit;

