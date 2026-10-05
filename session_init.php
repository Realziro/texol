<?php
// session_init.php - include this instead of calling session_start()

$lifetime = 60 * 60 * 24; // 24 hours of inactivity

if (session_status() === PHP_SESSION_NONE) {
    // Private session folder so other sites on the server can't
    // clean up your sessions with a shorter lifetime.
    // Ideally put this outside your public web root.
    $dir = __DIR__ . '/sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    ini_set('session.save_path', $dir);
    ini_set('session.gc_maxlifetime', (string) $lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

