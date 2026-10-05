<?php
session_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has permission to manage permissions
if (!check_permission('permissions', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';

if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) {
    echo json_encode(['success' => false, 'message' => 'Supabase configuration not found']);
    exit;
}

$supabaseUrl = rtrim(SUPABASE_URL, '/');
$supabaseKey = SUPABASE_SERVICE_ROLE_KEY; // Use service role key for admin operations

function supabaseRequest($url, $method, $data = null, $key) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'response' => json_decode($response, true),
        'httpCode' => $httpCode
    ];
}

try {
    switch ($action) {
        case 'save_role':
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $roleId = $_POST['role_id'] ?? '';

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Role name is required']);
                exit;
            }

            if ($roleId) {
                // Update existing role
                $result = supabaseRequest(
                    "$supabaseUrl/rest/v1/roles?id=eq.$roleId",
                    'PATCH',
                    ['name' => $name, 'description' => $description],
                    $supabaseKey
                );
            } else {
                // Create new role
                $result = supabaseRequest(
                    "$supabaseUrl/rest/v1/roles",
                    'POST',
                    ['name' => $name, 'description' => $description],
                    $supabaseKey
                );
            }

            if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
                echo json_encode(['success' => true, 'message' => 'Role saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save role']);
            }
            break;

        case 'save_role_permissions':
            $roleId = $_POST['role_id'] ?? '';
            $permissionsJson = $_POST['permissions'] ?? '';
            $permissions = json_decode($permissionsJson, true);

            if (empty($roleId) || !is_array($permissions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }

            // Delete existing permissions for this role
            supabaseRequest(
                "$supabaseUrl/rest/v1/role_permissions?role_id=eq.$roleId",
                'DELETE',
                null,
                $supabaseKey
            );

            // Insert new permissions
            if (!empty($permissions)) {
                $result = supabaseRequest(
                    "$supabaseUrl/rest/v1/role_permissions",
                    'POST',
                    $permissions,
                    $supabaseKey
                );

                if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
                    echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save permissions']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
            }
            break;

        case 'save_role_notifications':
            $roleId = $_POST['role_id'] ?? '';
            $notificationsJson = $_POST['notifications'] ?? '';
            $notifications = json_decode($notificationsJson, true);

            if (empty($roleId) || !is_array($notifications)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }

            // Delete existing notifications for this role
            supabaseRequest(
                "$supabaseUrl/rest/v1/role_notifications?role_id=eq.$roleId",
                'DELETE',
                null,
                $supabaseKey
            );

            // Insert new notifications
            if (!empty($notifications)) {
                $result = supabaseRequest(
                    "$supabaseUrl/rest/v1/role_notifications",
                    'POST',
                    $notifications,
                    $supabaseKey
                );

                if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
                    echo json_encode(['success' => true, 'message' => 'Notifications saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save notifications']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'Notifications saved successfully']);
            }
            break;

        case 'save_user_role':
            $userId = $_POST['user_id'] ?? '';
            $roleId = $_POST['role_id'] ?? '';

            if (empty($userId)) {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit;
            }

            $result = supabaseRequest(
                "$supabaseUrl/rest/v1/users?id=eq.$userId",
                'PATCH',
                ['role_id' => $roleId ?: null],
                $supabaseKey
            );

            if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
                echo json_encode(['success' => true, 'message' => 'Role assigned successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign role']);
            }
            break;

        case 'save_user_overrides':
            $userEmail = $_POST['user_email'] ?? '';
            $permissionsJson = $_POST['permissions'] ?? '';
            $permissions = json_decode($permissionsJson, true);

            if (empty($userEmail) || !is_array($permissions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }

            // Delete existing overrides for this user
            supabaseRequest(
                "$supabaseUrl/rest/v1/user_permissions?user_email=eq.$userEmail",
                'DELETE',
                null,
                $supabaseKey
            );

            // Insert new overrides
            if (!empty($permissions)) {
                $result = supabaseRequest(
                    "$supabaseUrl/rest/v1/user_permissions",
                    'POST',
                    $permissions,
                    $supabaseKey
                );

                if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
                    echo json_encode(['success' => true, 'message' => 'Overrides saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save overrides']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'Overrides saved successfully']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
