<?php
session_start();

// Protect profile page: only allow access when logged in
if (!isset($_SESSION['user_email'])) {
    header('Location: login');
    exit;
}

require_once __DIR__ . '/../config.php';

$currentEmail = $_SESSION['user_email'] ?? '';
$currentName = $_SESSION['user_name'] ?? '';
$currentDepartment = $_SESSION['user_department'] ?? '';
$currentRole = $_SESSION['user_role'] ?? '';
$userId = null;

$successMessage = '';
$errorMessage = '';
$departments = [];

// Fetch departments from database
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY') && SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    $query = http_build_query([
        'select' => 'id,name',
        'order' => 'name.asc'
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/departments?' . $query,
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
        $departments = json_decode($response, true);
        if (!is_array($departments)) {
            $departments = [];
        }
    }
}

// Create uploads directories if they don't exist
$uploadsDir = __DIR__ . '/uploads/profile/';
$signatureUploadsDir = __DIR__ . '/uploads/signatures/';

if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

if (!file_exists($signatureUploadsDir)) {
    mkdir($signatureUploadsDir, 0755, true);
}

// Get user data from Supabase
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY') && SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;
    
    $query = http_build_query([
        'select' => 'id,full_name,email,department,role,profile_picture,signature',
        'email' => 'eq.' . urlencode($currentEmail),
        'limit' => 1,
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $query,
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
        $rows = json_decode($response, true);
        if (is_array($rows) && count($rows) > 0) {
            $userData = $rows[0];
            $userId = $userData['id'] ?? null;
            $currentName = $userData['full_name'] ?? $currentName;
            $currentDepartment = $userData['department'] ?? $currentDepartment;
            $currentRole = $userData['role'] ?? $currentRole;
            $profilePicture = $userData['profile_picture'] ?? '';
            $signature = $userData['signature'] ?? '';
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = $_POST['department'] ?? '';
        
        if (empty($fullName) || empty($email) || empty($department)) {
            $errorMessage = 'Please fill in all required fields.';
        } elseif (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY') || SUPABASE_URL === '' || SUPABASE_ANON_KEY === '') {
            $errorMessage = 'Service is not configured. Please contact the administrator.';
        } else {
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;
            
            $updateData = [
                'full_name' => $fullName,
                'email' => $email,
                'department' => $department
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/users?email=eq.' . urlencode($currentEmail),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => json_encode($updateData),
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $supabaseKey,
                    'Authorization: Bearer ' . $supabaseKey,
                    'Content-Type: application/json',
                    'Prefer: return=representation',
                ],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
                        if ($httpCode >= 200 && $httpCode < 300) {
                            $_SESSION['user_name'] = $fullName;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_department'] = $department;
                            $currentName = $fullName;
                            $currentEmail = $email;
                            $currentDepartment = $department;
                            
                            // Reload profile picture after update
                            $query = http_build_query([
                                'select' => 'profile_picture',
                                'email' => 'eq.' . urlencode($email),
                                'limit' => 1,
                            ]);
                            
                            $ch2 = curl_init();
                            curl_setopt_array($ch2, [
                                CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $query,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER => [
                                    'apikey: ' . $supabaseKey,
                                    'Authorization: Bearer ' . $supabaseKey,
                                    'Accept: application/json',
                                ],
                            ]);
                            
                            $response2 = curl_exec($ch2);
                            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                            curl_close($ch2);
                            
                            if ($httpCode2 === 200 && $response2) {
                                $rows2 = json_decode($response2, true);
                                if (is_array($rows2) && count($rows2) > 0) {
                                    $profilePicture = $rows2[0]['profile_picture'] ?? '';
                                    $_SESSION['user_profile_picture'] = $profilePicture;
                                }
                            }
                            
                            $successMessage = 'Profile updated successfully!';
                        } else {
                            $errorMessage = 'Failed to update profile. Please try again.';
                        }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = 'Please fill in all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'New password must be at least 6 characters long.';
        } elseif (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY') || SUPABASE_URL === '' || SUPABASE_ANON_KEY === '') {
            $errorMessage = 'Service is not configured. Please contact the administrator.';
        } else {
            // Verify current password
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;
            
            $query = http_build_query([
                'select' => 'temp_password',
                'email' => 'eq.' . urlencode($currentEmail),
                'limit' => 1,
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $query,
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
                $rows = json_decode($response, true);
                if (is_array($rows) && count($rows) > 0) {
                    $storedPassword = $rows[0]['temp_password'] ?? '';
                    
                    if (hash_equals($storedPassword, $currentPassword)) {
                        // Update password
                        $updateData = ['temp_password' => $newPassword];
                        
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $supabaseUrl . '/rest/v1/users?email=eq.' . urlencode($currentEmail),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => 'PATCH',
                            CURLOPT_POSTFIELDS => json_encode($updateData),
                            CURLOPT_HTTPHEADER => [
                                'apikey: ' . $supabaseKey,
                                'Authorization: Bearer ' . $supabaseKey,
                                'Content-Type: application/json',
                                'Prefer: return=representation',
                            ],
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode >= 200 && $httpCode < 300) {
                            $successMessage = 'Password changed successfully!';
                        } else {
                            $errorMessage = 'Failed to change password. Please try again.';
                        }
                    } else {
                        $errorMessage = 'Current password is incorrect.';
                    }
                } else {
                    $errorMessage = 'User not found.';
                }
            } else {
                $errorMessage = 'Failed to verify current password.';
            }
        }
    } elseif ($action === 'upload_picture') {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                $errorMessage = 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.';
            } elseif ($file['size'] > $maxSize) {
                $errorMessage = 'File size exceeds 5MB limit.';
            } else {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . md5($currentEmail . time()) . '.' . $extension;
                $filepath = $uploadsDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $relativePath = 'uploads/profile/' . $filename;
                    
                    // Delete old profile picture if exists
                    if (!empty($profilePicture) && file_exists(__DIR__ . '/uploads/profile/' . basename($profilePicture))) {
                        @unlink(__DIR__ . '/uploads/profile/' . basename($profilePicture));
                    }
                    
                    // Update in Supabase
                    if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY') && SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
                        $supabaseUrl = rtrim(SUPABASE_URL, '/');
                        $supabaseKey = SUPABASE_ANON_KEY;
                        
                        $updateData = ['profile_picture' => $relativePath];
                        
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $supabaseUrl . '/rest/v1/users?email=eq.' . urlencode($currentEmail),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => 'PATCH',
                            CURLOPT_POSTFIELDS => json_encode($updateData),
                            CURLOPT_HTTPHEADER => [
                                'apikey: ' . $supabaseKey,
                                'Authorization: Bearer ' . $supabaseKey,
                                'Content-Type: application/json',
                                'Prefer: return=representation',
                            ],
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode >= 200 && $httpCode < 300) {
                            $profilePicture = $relativePath;
                            $_SESSION['user_profile_picture'] = $relativePath;
                            $successMessage = 'Profile picture updated successfully!';
                        } else {
                            $errorMessage = 'Failed to update profile picture in database.';
                        }
                    } else {
                        $profilePicture = $relativePath;
                        $successMessage = 'Profile picture uploaded successfully!';
                    }
                } else {
                    $errorMessage = 'Failed to upload file. Please try again.';
                }
            }
        } else {
            $errorMessage = 'No file uploaded or upload error occurred.';
        }
    } elseif ($action === 'upload_signature') {
        if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['signature_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowedTypes)) {
                $errorMessage = 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.';
            } elseif ($file['size'] > $maxSize) {
                $errorMessage = 'File size exceeds 2MB limit.';
            } else {
                // Save as JPEG since background whitening outputs JPEG
                $extension = 'jpg';
                $filename = 'signature_' . md5($currentEmail . time()) . '.' . $extension;
                $filepath = $signatureUploadsDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Since background removal is done client-side, file is already processed
                    $relativePath = 'uploads/signatures/' . $filename;

                    // Delete old signature image if exists
                    if (!empty($signature) && file_exists(__DIR__ . '/uploads/signatures/' . basename($signature))) {
                        @unlink(__DIR__ . '/uploads/signatures/' . basename($signature));
                    }

                    // Update in Supabase
                    if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY') && SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
                        $supabaseUrl = rtrim(SUPABASE_URL, '/');
                        $supabaseKey = SUPABASE_ANON_KEY;

                        $updateData = ['signature' => $relativePath];

                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $supabaseUrl . '/rest/v1/users?email=eq.' . urlencode($currentEmail),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => 'PATCH',
                            CURLOPT_POSTFIELDS => json_encode($updateData),
                            CURLOPT_HTTPHEADER => [
                                'apikey: ' . $supabaseKey,
                                'Authorization: Bearer ' . $supabaseKey,
                                'Content-Type: application/json',
                                'Prefer: return=representation',
                            ],
                        ]);

                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode >= 200 && $httpCode < 300) {
                            $signature = $relativePath;
                            $_SESSION['user_signature'] = $relativePath;
                            $successMessage = 'Signature uploaded successfully!';
                        } else {
                            $errorMessage = 'Failed to update signature in database.';
                        }
                    } else {
                        $signature = $relativePath;
                        $successMessage = 'Signature uploaded successfully!';
                    }
                } else {
                    $errorMessage = 'Failed to upload signature file. Please try again.';
                }
            }
        } else {
            $errorMessage = 'No file uploaded or upload error occurred.';
        }
    } elseif ($action === 'delete_signature') {
        // Delete old signature image if exists
        if (!empty($signature) && file_exists(__DIR__ . '/uploads/signatures/' . basename($signature))) {
            @unlink(__DIR__ . '/uploads/signatures/' . basename($signature));
        }

        // Update in Supabase
        if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY') && SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;

            $updateData = ['signature' => null];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/users?email=eq.' . urlencode($currentEmail),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => json_encode($updateData),
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $supabaseKey,
                    'Authorization: Bearer ' . $supabaseKey,
                    'Content-Type: application/json',
                    'Prefer: return=representation',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $signature = '';
                unset($_SESSION['user_signature']);
                $successMessage = 'Signature deleted successfully!';
            } else {
                $errorMessage = 'Failed to delete signature from database.';
            }
        } else {
            $signature = '';
            $successMessage = 'Signature deleted successfully!';
        }
    }
}

// Get updated profile picture if needed
if (!isset($profilePicture)) {
    $profilePicture = '';
}

// Get updated signature if needed
if (!isset($signature)) {
    $signature = '';
}

// Store profile picture in session for navbar use
if (!empty($profilePicture)) {
    $_SESSION['user_profile_picture'] = $profilePicture;
} elseif (isset($_SESSION['user_profile_picture'])) {
    $profilePicture = $_SESSION['user_profile_picture'];
}

// Store signature in session
if (!empty($signature)) {
    $_SESSION['user_signature'] = $signature;
} elseif (isset($_SESSION['user_signature'])) {
    $signature = $_SESSION['user_signature'];
}

// Generate initials for profile picture placeholder
$initials = '';
if (!empty($currentName)) {
    $nameParts = explode(' ', trim($currentName));
    if (count($nameParts) >= 2) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
    } else {
        $initials = strtoupper(substr($currentName, 0, 2));
    }
} else {
    $initials = strtoupper(substr($currentEmail, 0, 2));
}

// Verify profile picture file exists
if (!empty($profilePicture)) {
    $filename = basename($profilePicture);
    $picturePath = __DIR__ . '/uploads/profile/' . $filename;
    if (!file_exists($picturePath)) {
        $profilePicture = '';
        unset($_SESSION['user_profile_picture']);
    }
}

// Verify signature file exists
if (!empty($signature)) {
    $filename = basename($signature);
    $signaturePath = __DIR__ . '/uploads/signatures/' . $filename;
    if (!file_exists($signaturePath)) {
        $signature = '';
        unset($_SESSION['user_signature']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - Profile</title>
    
    <!-- Bootstrap 5 CSS CDN -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    />
    
    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" />
    
    <style>
        .profile-picture-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .profile-picture:hover {
            border-color: #4f46e5;
        }
        .profile-picture-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            border: 4px solid #e5e7eb;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .profile-picture-placeholder:hover {
            border-color: #4f46e5;
        }
        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #4f46e5;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        #profilePictureInput {
            display: none;
        }
        .signature-upload-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
            width: 100%;
        }
        .signature-preview {
            max-width: 100%;
            max-height: 150px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            object-fit: contain;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .signature-placeholder {
            width: 100%;
            height: 150px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 14px;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .signature-placeholder::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s ease;
        }
        .signature-placeholder:hover::before {
            left: 100%;
        }
        .signature-placeholder:hover {
            border-color: #6366f1;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }
        .signature-placeholder i {
            font-size: 32px;
            margin-bottom: 8px;
            color: #9ca3af;
            transition: color 0.3s ease;
        }
        .signature-placeholder:hover i {
            color: #6366f1;
        }
        #signatureInput {
            display: none;
        }
        .upload-progress {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            z-index: 10;
        }
        .upload-progress .spinner-border {
            width: 40px;
            height: 40px;
            color: #6366f1;
        }
        .upload-progress p {
            margin-top: 12px;
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        .signature-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 12px;
        }
        .btn-upload {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }
        .btn-upload:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        .btn-upload:active {
            transform: translateY(0);
        }
        .signature-info {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            color: #92400e;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .signature-info i {
            font-size: 14px;
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'profile';
        include __DIR__ . '/partials/sidebar.php';
        ?>
        
        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <button
                    class="btn btn-outline-secondary d-lg-none me-2"
                    id="sidebarToggleBtn"
                    type="button"
                    aria-label="Toggle sidebar"
                >
                    <i class="bi bi-list"></i>
                </button>
                
                <a class="navbar-brand fw-semibold d-none d-sm-inline d-flex align-items-center gap-2" href="#">
                  
                    <span id="pageTitle">Profile</span>
                </a>
                
                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>
            
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">My Profile</h1>
                    <p class="text-muted small mb-4">Manage your account settings and preferences.</p>
                    
                    <?php if (!empty($successMessage)) : ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errorMessage)) : ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row g-4">
                        <!-- Left Column: Profile Picture and Signature -->
                        <div class="col-12 col-md-4">
                            <!-- Profile Picture Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body text-center">
                                    <div class="profile-picture-container">
                                        <?php if (!empty($profilePicture)) : ?>
                                            <img src="<?php echo htmlspecialchars($profilePicture, ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="Profile Picture"
                                                 class="profile-picture"
                                                 id="profilePictureDisplay">
                                        <?php else : ?>
                                            <div class="profile-picture-placeholder" id="profilePicturePlaceholder">
                                                <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="upload-overlay" onclick="document.getElementById('profilePictureInput').click()">
                                            <i class="bi bi-camera-fill"></i>
                                        </div>
                                    </div>
                                    <input type="file"
                                           id="profilePictureInput"
                                           name="profile_picture"
                                           accept="image/jpeg,image/png,image/gif,image/webp"
                                           form="profilePictureForm">
                                    <form id="profilePictureForm" method="post" enctype="multipart/form-data" style="display: none;">
                                        <input type="hidden" name="action" value="upload_picture">
                                    </form>
                                    <p class="small text-muted mt-2">Click to upload a new profile picture</p>
                                    <p class="small text-muted">Max size: 5MB (JPEG, PNG, GIF, WebP)</p>
                                </div>
                            </div>

                            <!-- Signature Section -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-0 fw-semibold">
                                        <i class="bi bi-pen-fill me-2 text-primary"></i>Signature
                                    </h2>
                                </div>
                                <div class="card-body text-center">
                                    <div class="signature-upload-container mb-3">
                                        <?php if (!empty($signature)) : ?>
                                            <div class="position-relative">
                                                <img id="signaturePreview"
                                                     src="<?php echo htmlspecialchars($signature, ENT_QUOTES, 'UTF-8'); ?>"
                                                     alt="Signature"
                                                     class="signature-preview">
                                                <div id="uploadProgress" class="upload-progress d-none">
                                                    <div class="spinner-border" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <p>Uploading signature...</p>
                                                </div>
                                            </div>
                                        <?php else : ?>
                                            <div class="position-relative">
                                                <div id="signaturePlaceholder" class="signature-placeholder" onclick="document.getElementById('signatureInput').click()">
                                                    <i class="bi bi-cloud-arrow-up"></i>
                                                    <span class="mt-2">Click to upload signature</span>
                                                </div>
                                                <div id="uploadProgress" class="upload-progress d-none">
                                                    <div class="spinner-border" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <p>Uploading signature...</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file"
                                           id="signatureInput"
                                           name="signature_image"
                                           accept="image/jpeg,image/png,image/gif,image/webp"
                                           form="signatureForm">
                                    <form id="signatureForm" method="post" enctype="multipart/form-data" style="display: none;">
                                        <input type="hidden" name="action" value="upload_signature">
                                    </form>
                                    <div class="signature-actions">
                                        <button type="button" class="btn-upload" onclick="document.getElementById('signatureInput').click()">
                                            <i class="bi bi-upload me-1"></i> Upload Signature
                                        </button>
                                        <?php if (!empty($signature)) : ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" id="removeSignatureBtn">
                                                <i class="bi bi-trash me-1"></i> Remove
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="signature-info">
                                        <i class="bi bi-info-circle"></i>
                                        <span>Max size: 2MB (JPEG, PNG, GIF, WebP) - Background will be whitened automatically</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Profile Details and Change Password -->
                        <div class="col-12 col-md-8">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-0 fw-semibold">Personal Information</h2>
                                </div>
                                <div class="card-body px-3 px-md-4">
                                    <form method="post" action="profile">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold" for="full_name">Full Name</label>
                                                <input type="text" 
                                                       class="form-control form-control-sm" 
                                                       id="full_name" 
                                                       name="full_name" 
                                                       value="<?php echo htmlspecialchars($currentName, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       required>
                                            </div>
                                            
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small fw-semibold" for="email">Email</label>
                                                <input type="email" 
                                                       class="form-control form-control-sm" 
                                                       id="email" 
                                                       name="email" 
                                                       value="<?php echo htmlspecialchars($currentEmail, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       required>
                                            </div>
                                            
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small fw-semibold" for="department">Department</label>
                                                <select class="form-select form-select-sm" id="department" name="department" required>
                                                    <option value="">Select department</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <option value="<?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentDepartment === $dept['name'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold">Role</label>
                                                <input type="text" 
                                                       class="form-control form-control-sm" 
                                                       value="<?php echo htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       disabled>
                                                <small class="text-muted">Role cannot be changed from profile page</small>
                                            </div>
                                            
                                            <div class="col-12 d-flex justify-content-end mt-3">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-save me-1"></i> Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Change Password Section -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-0 fw-semibold">Change Password</h2>
                                </div>
                                <div class="card-body px-3 px-md-4">
                                    <form method="post" action="profile" id="changePasswordForm">
                                        <input type="hidden" name="action" value="change_password">
                                        
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold" for="current_password">Current Password</label>
                                                <input type="password" 
                                                       class="form-control form-control-sm" 
                                                       id="current_password" 
                                                       name="current_password" 
                                                       required>
                                            </div>
                                            
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small fw-semibold" for="new_password">New Password</label>
                                                <input type="password" 
                                                       class="form-control form-control-sm" 
                                                       id="new_password" 
                                                       name="new_password" 
                                                       minlength="6"
                                                       required>
                                                <small class="text-muted">Minimum 6 characters</small>
                                            </div>
                                            
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small fw-semibold" for="confirm_password">Confirm New Password</label>
                                                <input type="password" 
                                                       class="form-control form-control-sm" 
                                                       id="confirm_password" 
                                                       name="confirm_password" 
                                                       minlength="6"
                                                       required>
                                            </div>
                                            
                                            <div class="col-12 d-flex justify-content-end mt-3">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-key me-1"></i> Change Password
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>


    
    <!-- Bootstrap JS Bundle CDN -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>
    
    <!-- App JS -->
    <script src="app.js"></script>
    
    <script>
        // Handle profile picture upload
        const profilePictureInput = document.getElementById('profilePictureInput');
        const profilePictureForm = document.getElementById('profilePictureForm');

        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    // Add file input to form
                    const existingInput = profilePictureForm.querySelector('input[type="file"]');
                    if (existingInput) {
                        existingInput.remove();
                    }
                    profilePictureForm.appendChild(this.cloneNode(true));

                    // Submit form
                    profilePictureForm.submit();
                }
            });
        }

        // Handle signature upload
        const signatureInput = document.getElementById('signatureInput');
        const signatureForm = document.getElementById('signatureForm');
        const uploadProgress = document.getElementById('uploadProgress');
        const signaturePlaceholder = document.getElementById('signaturePlaceholder');
        const signaturePreview = document.getElementById('signaturePreview');

        if (signatureInput) {
            signatureInput.addEventListener('change', async function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    
                    // Validate file size
                    const maxSize = 2 * 1024 * 1024; // 2MB
                    if (file.size > maxSize) {
                        alert('File size exceeds 2MB limit. Please choose a smaller file.');
                        this.value = '';
                        return;
                    }
                    
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.');
                        this.value = '';
                        return;
                    }

                    // Show progress spinner
                    if (uploadProgress) {
                        uploadProgress.classList.remove('d-none');
                        uploadProgress.querySelector('p').textContent = 'Whitening background...';
                    }
                    
                    // Disable buttons during upload
                    const uploadBtn = document.querySelector('.btn-upload');
                    if (uploadBtn) {
                        uploadBtn.disabled = true;
                        uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                    }

                    try {
                        // Use canvas-based background whitening
                        const processedBlob = await whitenSignatureBackground(file);
                        
                        // Create a new file from the processed blob
                        const processedFile = new File([processedBlob], 'signature_processed.jpg', { type: 'image/jpeg' });
                        
                        // Update progress message
                        if (uploadProgress) {
                            uploadProgress.querySelector('p').textContent = 'Uploading signature...';
                        }
                        
                        // Create FormData with the processed file
                        const formData = new FormData();
                        formData.append('action', 'upload_signature');
                        formData.append('signature_image', processedFile);
                        
                        // Upload the processed file
                        const response = await fetch('profile', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            // Reload the page to show the updated signature
                            window.location.reload();
                        } else {
                            throw new Error('Upload failed');
                        }
                        
                    } catch (error) {
                        console.error('Background whitening error:', error);
                        alert('Failed to process signature. Please try again or upload a different image.');
                        
                        // Hide progress and reset UI
                        if (uploadProgress) {
                            uploadProgress.classList.add('d-none');
                        }
                        if (uploadBtn) {
                            uploadBtn.disabled = false;
                            uploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Upload Signature';
                        }
                        this.value = '';
                    }
                }
            });
        }

        // Whitening signature background function
        function whitenSignatureBackground(imageFile, threshold = 200) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                const reader = new FileReader();

                reader.onload = e => { img.src = e.target.result; };
                reader.onerror = reject;
                img.onload = () => {
                    const w = img.width, h = img.height;

                    // 1. Full-res original
                    const canvas = document.createElement('canvas');
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    const original = ctx.getImageData(0, 0, w, h).data;

                    // 2. Cheap blur estimate of local lighting (downscale -> upscale)
                    const small = document.createElement('canvas');
                    const smallW = Math.max(1, Math.round(w / 24));
                    const smallH = Math.max(1, Math.round(h / 24));
                    small.width = smallW;
                    small.height = smallH;
                    small.getContext('2d').drawImage(img, 0, 0, smallW, smallH);

                    const blurCanvas = document.createElement('canvas');
                    blurCanvas.width = w;
                    blurCanvas.height = h;
                    const blurCtx = blurCanvas.getContext('2d');
                    blurCtx.imageSmoothingEnabled = true;
                    blurCtx.drawImage(small, 0, 0, w, h);
                    const blurred = blurCtx.getImageData(0, 0, w, h).data;

                    // 3. Normalize lighting, then force background to pure white
                    const output = ctx.createImageData(w, h);
                    const outData = output.data;

                    for (let i = 0; i < original.length; i += 4) {
                        const origGray = (original[i] + original[i + 1] + original[i + 2]) / 3;
                        const bgGray = (blurred[i] + blurred[i + 1] + blurred[i + 2]) / 3 || 1;
                        const corrected = Math.min(255, (origGray / bgGray) * 255);

                        if (corrected >= threshold) {
                            // background -> pure white
                            outData[i] = 255;
                            outData[i + 1] = 255;
                            outData[i + 2] = 255;
                        } else {
                            // ink -> keep original color (usually dark blue/black)
                            outData[i] = original[i];
                            outData[i + 1] = original[i + 1];
                            outData[i + 2] = original[i + 2];
                        }
                        outData[i + 3] = 255; // fully opaque, no transparency needed
                    }

                    ctx.putImageData(output, 0, 0);
                    canvas.toBlob(blob => resolve(blob), 'image/jpeg', 0.92); // JPEG fine now — no transparency to preserve
                };
                img.onerror = reject;
                reader.readAsDataURL(imageFile);
            });
        }

        // Remove signature
        const removeSignatureBtn = document.getElementById('removeSignatureBtn');
        if (removeSignatureBtn) {
            removeSignatureBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete your signature?')) {
                    // Create a form to delete signature
                    const deleteForm = document.createElement('form');
                    deleteForm.method = 'post';
                    deleteForm.action = 'profile';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_signature';

                    deleteForm.appendChild(actionInput);
                    document.body.appendChild(deleteForm);
                    deleteForm.submit();
                }
            });
        }

        // Password confirmation validation
        const changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>
