<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

require_once __DIR__ . '/../config.php';

// Protect page: only allow access when logged in
if (!isset($_SESSION['user_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'upload_attachment') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$requisitionId = $_POST['requisition_id'] ?? '';
$originalName = $_POST['original_name'] ?? '';
$fileSize = $_POST['file_size'] ?? '';
$mimeType = $_POST['mime_type'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

// Get user ID
$userId = null;
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;
    $query = http_build_query(['select' => 'id', 'email' => 'eq.' . $userEmail]);
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
    $userData = json_decode($response, true);
    $userId = $userData[0]['id'] ?? null;
    curl_close($ch);
}

// Upload directory - all files go directly in requisitions folder
$uploadDir = __DIR__ . '/uploads/requisitions/';

// Check if directory exists and is writable
if (!file_exists($uploadDir)) {
    echo json_encode(['success' => false, 'message' => 'Upload directory does not exist: ' . $uploadDir]);
    exit;
}

if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'message' => 'Upload directory is not writable: ' . $uploadDir]);
    exit;
}

// Generate unique filename with requisition ID prefix
$fileName = $requisitionId . '_' . time() . '_' . basename($_FILES['file']['name']);
$filePath = $uploadDir . $fileName;
$relativePath = 'uploads/requisitions/' . $fileName;

// Move uploaded file
$moveResult = move_uploaded_file($_FILES['file']['tmp_name'], $filePath);

if (!$moveResult) {
    echo json_encode(['success' => false, 'message' => 'Failed to move file. Target: ' . $filePath . ', Source: ' . $_FILES['file']['tmp_name']]);
    exit;
}

// Store in database
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    $data = [
        'requisition_id' => $requisitionId,
        'original_name' => $originalName,
        'file_name' => $fileName,
        'file_path' => $relativePath,
        'file_size' => $fileSize,
        'mime_type' => $mimeType,
        'uploaded_by' => $userId
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/requisition_attachments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    curl_exec($ch);
    curl_close($ch);
}

echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
