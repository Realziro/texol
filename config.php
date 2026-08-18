<?php
// config.php
// Simple .env loader for local development (no external dependencies).

// Path to .env at the project root (next to this file)
$envPath = __DIR__ . '/.env';

if (file_exists($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        // Split KEY=VALUE
        [$name, $value] = array_map('trim', explode('=', $line, 2));

        // Remove surrounding quotes if any
        $value = trim($value, "\"'");

        if ($name !== '') {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Helper constants for Supabase (adjust variable names if needed)
if (! defined('SUPABASE_URL') && isset($_ENV['SUPABASE_URL'])) {
    define('SUPABASE_URL', $_ENV['SUPABASE_URL']);
}

if (! defined('SUPABASE_ANON_KEY') && isset($_ENV['SUPABASE_ANON_KEY'])) {
    define('SUPABASE_ANON_KEY', $_ENV['SUPABASE_ANON_KEY']);
}

// Permission checking function
function check_permission($module, $action = 'view') {
    // Admins have all permissions
    if (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin') {
        return true;
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_email'])) {
        return false;
    }

    // Fetch user permissions from database
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY')) {
        return false;
    }

    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;
    $userEmail = $_SESSION['user_email'];

    $query = http_build_query([
        'select' => 'action',
        'user_email' => 'eq.' . $userEmail,
        'module' => 'eq.' . $module
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/user_permissions?' . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $permissions = json_decode($response, true) ?: [];
        
        // Check if user has the specific action or 'all' permission
        foreach ($permissions as $perm) {
            if ($perm['action'] === 'all' || $perm['action'] === $action) {
                return true;
            }
        }
    }

    return false;
}

