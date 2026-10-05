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

if (! defined('SUPABASE_SERVICE_ROLE_KEY') && isset($_ENV['SUPABASE_SERVICE_ROLE_KEY'])) {
    define('SUPABASE_SERVICE_ROLE_KEY', $_ENV['SUPABASE_SERVICE_ROLE_KEY']);
}

// Fallback to anon key if service role key is not available
if (! defined('SUPABASE_SERVICE_ROLE_KEY') && defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_SERVICE_ROLE_KEY', SUPABASE_ANON_KEY);
}

// Permission checking function
function check_permission($module, $action = 'view', &$scope = null) {
    // Admins have all permissions with 'all' scope
    if (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin') {
        $scope = 'all';
        return true;
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_email'])) {
        return false;
    }

    // Cache effective permissions in session
    if (!isset($_SESSION['effective_permissions'])) {
        if (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY')) {
            return false;
        }

        $supabaseUrl = rtrim(SUPABASE_URL, '/');
        $supabaseKey = SUPABASE_ANON_KEY;
        $userEmail = $_SESSION['user_email'];

        // Call the get_effective_permissions RPC function
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/rpc/get_effective_permissions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['p_email' => $userEmail]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $permissions = json_decode($response, true) ?: [];
            $_SESSION['effective_permissions'] = $permissions;
        } else {
            $_SESSION['effective_permissions'] = [];
        }
    }

    $permissions = $_SESSION['effective_permissions'];

    // Check if user has the specific action or 'all' permission
    foreach ($permissions as $perm) {
        if ($perm['module'] === $module && ($perm['action'] === 'all' || $perm['action'] === $action)) {
            $scope = $perm['scope'] ?? 'all';
            return true;
        }
    }

    return false;
}

// Helper function to build scope filter for queries
function get_scope_filter($module, $action = 'view', $userEmail = null, $userDepartment = null) {
    $scope = 'all';
    check_permission($module, $action, $scope);

    $filter = '';
    switch ($scope) {
        case 'own':
            // Filter by user's own records
            if ($userEmail) {
                $filter = " AND (created_by = '" . pg_escape_string($userEmail) . "' OR requested_by = '" . pg_escape_string($userEmail) . "')";
            }
            break;
        case 'department':
            // Filter by user's department
            if ($userDepartment) {
                $filter = " AND lower(trim(department)) = lower(trim('" . pg_escape_string($userDepartment) . "'))";
            }
            break;
        case 'all':
        default:
            // No filter - see everything
            break;
    }

    return $filter;
}

