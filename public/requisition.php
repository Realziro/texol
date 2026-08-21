<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
session_start();

// Load env/config (Supabase keys, etc.)
require_once __DIR__ . '/../config.php';

// Protect page: only allow access when logged in
if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Redirect non-admins and users without requisition_approval permission to mytickets page
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    if (!check_permission('requisition_approval', 'view')) {
        header('Location:   mytickets');
        exit;
    }
}

$message = '';
$messageType = '';
$requisitions = [];
$items = [];
$suppliers = [];
$userEmail = $_SESSION['user_email'] ?? '';
$ticketId = $_GET['ticket_id'] ?? '';



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($action === 'upload_attachment') {
        // Handle file upload - always return JSON
        header('Content-Type: application/json');

        error_log('upload_attachment action triggered');
        $requisitionId = $_POST['requisition_id'] ?? '';
        $originalName = $_POST['original_name'] ?? '';
        $fileSize = $_POST['file_size'] ?? '';
        $mimeType = $_POST['mime_type'] ?? '';

        error_log('upload_attachment - requisition_id: ' . $requisitionId);
        error_log('upload_attachment - FILES: ' . json_encode($_FILES));

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
            error_log('upload_attachment - userId: ' . $userId);
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            error_log('upload_attachment - File upload failed, error code: ' . ($_FILES['file']['error'] ?? 'no file'));
            echo json_encode(['success' => false, 'message' => 'File upload failed']);
            exit;
        }

        // Create upload directory
        $uploadDir = __DIR__ . '/uploads/requisitions/' . $requisitionId . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileName = time() . '_' . basename($_FILES['file']['name']);
        $filePath = $uploadDir . $fileName;
        $relativePath = 'uploads/requisitions/' . $requisitionId . '/' . $fileName;

        error_log('upload_attachment - Moving file to: ' . $filePath);

        // Move uploaded file
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
            error_log('upload_attachment - File moved successfully');
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

                error_log('upload_attachment - Inserting into database: ' . json_encode($data));

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
                $response = curl_exec($ch);
                error_log('upload_attachment - Database response: ' . $response);
                curl_close($ch);
            }

            echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
            exit;
        } else {
            error_log('upload_attachment - Failed to move file');
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            exit;
        }
    } elseif ($action === 'approve') {
        // Approve requisition
        $reqId = $_POST['req_id'] ?? '';
        $password = $_POST['password'] ?? '';

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        if (empty($password)) {
            $response = ['success' => false, 'message' => 'Password is required to approve requisition.'];
        } elseif ($reqId && defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;

            // Get current user ID and verify password
            $query = http_build_query(['select' => 'id,temp_password,signature', 'email' => 'eq.' . $userEmail]);
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
            $storedPassword = $userData[0]['temp_password'] ?? '';
            $userSignature = $userData[0]['signature'] ?? '';
            curl_close($ch);

            if (!$userId) {
                $response = ['success' => false, 'message' => 'User not found.'];
            } elseif (empty($userSignature)) {
                $response = ['success' => false, 'message' => 'You must set your signature before approving requisitions.'];
            } elseif (!hash_equals($storedPassword, $password)) {
                $response = ['success' => false, 'message' => 'Incorrect password.'];
            } else {
                // Fetch current requisition to check existing approvals
                $query = http_build_query(['select' => '*', 'id' => 'eq.' . $reqId]);
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $supabaseUrl . '/rest/v1/requisitions?' . $query,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . $supabaseKey,
                        'Authorization: Bearer ' . $supabaseKey,
                        'Accept: application/json',
                    ],
                ]);
                $reqResponse = curl_exec($ch);
                $reqData = json_decode($reqResponse, true);
                curl_close($ch);

                if (empty($reqData) || !is_array($reqData) || empty($reqData[0])) {
                    $response = ['success' => false, 'message' => 'Requisition not found.'];
                } else {
                    $requisition = $reqData[0];
                    $sharedWith = $requisition['shared_with'] ?? '';
                    $approvedByUsers = $requisition['approved_by_users'] ?? [];

                    // Parse shared_with (comma-separated)
                    $sharedWithArray = !empty($sharedWith) ? explode(',', $sharedWith) : [];
                    $approvedByArray = is_array($approvedByUsers) ? $approvedByUsers : (!empty($approvedByUsers) ? json_decode($approvedByUsers, true) : []);

                    // Check if user already approved (check user_id in objects)
                    $alreadyApproved = false;
                    foreach ($approvedByArray as $approval) {
                        if (is_array($approval) && isset($approval['user_id']) && $approval['user_id'] === $userId) {
                            $alreadyApproved = true;
                            break;
                        } elseif (is_string($approval) && $approval === $userId) {
                            // Handle old format (just user IDs)
                            $alreadyApproved = true;
                            break;
                        }
                    }

                    if ($alreadyApproved) {
                        $response = ['success' => false, 'message' => 'You have already approved this requisition.'];
                    } else {
                        // Add user to approved list with timestamp
                        $approvedByArray[] = [
                            'user_id' => $userId,
                            'approved_at' => date('c')
                        ];

                        // Calculate approval progress
                        $totalApprovers = count($sharedWithArray);
                        $approvedCount = count($approvedByArray);
                        $progress = $totalApprovers > 0 ? ($approvedCount / $totalApprovers) * 100 : 0;

                        // Determine status based on progress
                        $status = 'pending';
                        if ($progress >= 100) {
                            $status = 'approved';
                        } elseif ($progress > 0) {
                            $status = 'partially_approved';
                        }

                        $data = [
                            'approved_by_users' => json_encode($approvedByArray),
                            'approved_by' => $userId, // Keep single approved_by for backward compatibility
                            'status' => $status,
                            'approval_progress' => $progress
                        ];

                        // If fully approved, set approved_at
                        if ($status === 'approved') {
                            $data['approved_at'] = date('c');
                        }

                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $supabaseUrl . '/rest/v1/requisitions?id=eq.' . $reqId,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => 'PATCH',
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

                        $response = ['success' => true, 'message' => 'Requisition approved!'];
                    }
                }
            }
        } else {
            $response = ['success' => false, 'message' => 'Invalid request.'];
        }

        // Return JSON for AJAX requests
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            $message = $response['message'];
            $messageType = $response['success'] ? 'success' : 'error';
        }
    } elseif ($action === 'reject') {
        // Reject requisition
        $reqId = $_POST['req_id'] ?? '';
        if ($reqId && defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;

            $data = ['status' => 'rejected'];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/requisitions?id=eq.' . $reqId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $supabaseKey,
                    'Authorization: Bearer ' . $supabaseKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($data),
            ]);
            curl_exec($ch);
            curl_close($ch);

            $response = ['success' => true, 'message' => 'Requisition rejected!'];
        } else {
            $response = ['success' => false, 'message' => 'Invalid request.'];
        }

        // Return JSON for AJAX requests
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            $message = $response['message'];
            $messageType = $response['success'] ? 'success' : 'error';
        }
    } elseif ($action === 'restore') {
        // Restore rejected requisition
        $reqId = $_POST['req_id'] ?? '';
        if ($reqId && defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;

            $data = ['status' => 'pending'];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/requisitions?id=eq.' . $reqId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $supabaseKey,
                    'Authorization: Bearer ' . $supabaseKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($data),
            ]);
            curl_exec($ch);
            curl_close($ch);

            $response = ['success' => true, 'message' => 'Requisition restored!'];
        } else {
            $response = ['success' => false, 'message' => 'Invalid request.'];
        }

        // Return JSON for AJAX requests
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            $message = $response['message'];
            $messageType = $response['success'] ? 'success' : 'error';
        }
    } elseif ($action === 'create') {
        // Create new requisition (standalone, without ticket ID)
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');

        $department = $_POST['department'] ?? '';
        $requiredDate = $_POST['required_date'] ?? '';
        $supplierId = $_POST['supplier_id'] ?? '';
        $sharedWith = $_POST['shared_with'] ?? '';
        $ticketId = $_POST['ticket_id'] ?? '';

        $itemIds = $_POST['item_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $units = $_POST['unit'] ?? [];
        $unitPrices = $_POST['unit_price'] ?? [];

        if (empty($department) || empty($requiredDate) || empty($itemIds) || empty($quantities)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }

        if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;

            // Get current user ID
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

            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
                exit;
            }

            // Calculate approval progress
            $sharedWithArray = $sharedWith ? explode(',', $sharedWith) : [];
            $totalApprovers = count($sharedWithArray);
            $progress = 0;

            // Generate requisition number
            $requisitionNumber = 'RQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            // Create requisition
            $requisitionData = [
                'requisition_number' => $requisitionNumber,
                'requested_by' => $userId,
                'department' => $department,
                'required_date' => $requiredDate,
                'supplier_id' => $supplierId ?: null,
                'shared_with' => $sharedWith ?: null,
                'ticket_id' => $ticketId ?: null,
                'status' => 'pending',
                'approval_progress' => $progress,
                'approved_by_users' => json_encode([]),
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/requisitions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $supabaseKey,
                    'Authorization: Bearer ' . $supabaseKey,
                    'Content-Type: application/json',
                    'Prefer: return=representation',
                ],
                CURLOPT_POSTFIELDS => json_encode($requisitionData),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $requisitionResult = json_decode($response, true);
            curl_close($ch);

            error_log('Create requisition - HTTP Code: ' . $httpCode);
            error_log('Create requisition - Response: ' . $response);

            if (isset($requisitionResult[0]['id'])) {
                $requisitionId = $requisitionResult[0]['id'];

                // Create requisition items
                foreach ($itemIds as $index => $itemId) {
                    if (empty($itemId) || empty($quantities[$index])) continue;

                    $itemData = [
                        'requisition_id' => $requisitionId,
                        'item_id' => $itemId,
                        'quantity' => $quantities[$index],
                        'unit' => $units[$index] ?? 'pieces',
                        'unit_price' => $unitPrices[$index] ?? 0,
                    ];

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $supabaseUrl . '/rest/v1/requisition_items',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . $supabaseKey,
                            'Authorization: Bearer ' . $supabaseKey,
                            'Content-Type: application/json',
                        ],
                        CURLOPT_POSTFIELDS => json_encode($itemData),
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }

                // Send email notification to shared_with users
                if (!empty($sharedWithArray)) {
                    // Fetch email addresses of shared_with users
                    $sharedWithIds = implode(',', array_map(function($id) {
                        return 'eq.' . trim($id);
                    }, $sharedWithArray));
                    $query = http_build_query(['select' => 'email,full_name', 'id' => "in.($sharedWithIds)"]);
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
                    $sharedWithUsers = json_decode($response, true);
                    curl_close($ch);
                }

                echo json_encode(['success' => true, 'message' => 'Requisition created successfully!']);
            } else {
                $errorMessage = 'Failed to create requisition.';
                if (isset($requisitionResult['message'])) {
                    $errorMessage .= ' ' . $requisitionResult['message'];
                }
                if (isset($requisitionResult['details'])) {
                    $errorMessage .= ' Details: ' . $requisitionResult['details'];
                }
                error_log('Create requisition - Error: ' . $errorMessage);
                echo json_encode(['success' => false, 'message' => $errorMessage]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Supabase configuration not found.']);
        }
        exit;
    }
}

// Fetch data from Supabase
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;
    
    // Fetch items
    $query = http_build_query(['select' => '*', 'order' => 'name.asc']);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/items?' . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $items = json_decode($response, true);
    if (!is_array($items)) {
        error_log('Items fetch error: ' . $response);
        $items = [];
    }
    curl_close($ch);
    
    // Fetch suppliers
    $query = http_build_query(['select' => '*', 'order' => 'name.asc']);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/suppliers?' . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $suppliers = json_decode($response, true);
    if (!is_array($suppliers)) {
        error_log('Suppliers fetch error: ' . $response);
        $suppliers = [];
    }
    curl_close($ch);
    
    // Fetch requisitions with related data (with pagination)
    $queryParams = [
        'select' => '*',
        'order' => 'created_at.desc',
        'limit' => 100
    ];

    // Get current user ID
    $currentUserId = null;
    if (isset($_SESSION['user_id'])) {
        $currentUserId = $_SESSION['user_id'];
    } elseif (!empty($userEmail)) {
        // Fallback: fetch user ID from email
        $userQuery = http_build_query(['select' => 'id', 'email' => 'eq.' . $userEmail]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $userQuery,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $userResponse = curl_exec($ch);
        $userData = json_decode($userResponse, true);
        $currentUserId = is_array($userData) && !empty($userData[0]) ? $userData[0]['id'] : null;
        curl_close($ch);
    }

    // Filter by ticket_id if provided
    if ($ticketId) {
        $queryParams['ticket_id'] = 'eq.' . $ticketId;
    } else {
        // Show requisitions where user is requester OR in shared_with
        // Since Supabase doesn't support OR in simple queries, we need to fetch all and filter in PHP
        // or use a more complex query. For now, let's remove the shared_with filter
        // and filter in PHP to show user's own requisitions + shared ones
        if ($currentUserId) {
            // Don't filter in query - will filter in PHP
        }
    }

    $query = http_build_query($queryParams);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/requisitions?' . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $requisitions = json_decode($response, true);
    curl_close($ch);

    // Handle error response
    if (!is_array($requisitions)) {
        error_log('Requisitions fetch error: ' . $response);
        $requisitions = [];
    }

    // Filter requisitions based on permissions
    // If user has 'requisitions_view_all' permission, show all requisitions
    // Otherwise, show where user is requester OR in shared_with
    if ($currentUserId && !$ticketId) {
        $canViewAllRequisitions = check_permission('requisitions_view_all', 'view');

        if ($canViewAllRequisitions) {
            // Show all requisitions
        } else {
            // Filter to show only user's requisitions
            $filteredRequisitions = [];
            foreach ($requisitions as $req) {
                if (!is_array($req)) continue;
                $isRequester = ($req['requested_by'] ?? '') === $currentUserId;
                $sharedWith = $req['shared_with'] ?? '';
                $sharedWithArray = !empty($sharedWith) ? explode(',', $sharedWith) : [];
                $isInSharedWith = in_array($currentUserId, $sharedWithArray);

                if ($isRequester || $isInSharedWith) {
                    $filteredRequisitions[] = $req;
                }
            }
            $requisitions = $filteredRequisitions;
        }
    }

    // Fetch all requisition items and attachments in single queries (N+1 optimization)
    $reqIds = array_filter(array_column($requisitions, 'id'));
    $allItems = [];
    $allAttachments = [];

    if (!empty($reqIds)) {
        // Fetch all items for these requisitions
        $reqIdsString = implode(',', $reqIds);
        $query = http_build_query([
            'select' => '*,item:items(id,name,unit)',
            'requisition_id' => 'in.(' . $reqIdsString . ')'
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/requisition_items?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $allItems = json_decode($response, true) ?: [];
        curl_close($ch);

        // Fetch all attachments for these requisitions
        $query = http_build_query([
            'select' => '*',
            'requisition_id' => 'in.(' . $reqIdsString . ')',
            'order' => 'created_at.desc'
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/requisition_attachments?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $allAttachments = json_decode($response, true) ?: [];
        curl_close($ch);
    }

    // Group items and attachments by requisition_id
    $itemsByReq = [];
    foreach ($allItems as $item) {
        $reqId = $item['requisition_id'] ?? null;
        if ($reqId) {
            $itemsByReq[$reqId][] = $item;
        }
    }

    $attachmentsByReq = [];
    foreach ($allAttachments as $attachment) {
        $reqId = $attachment['requisition_id'] ?? null;
        if ($reqId) {
            $attachmentsByReq[$reqId][] = $attachment;
        }
    }

    // Assign items and attachments to requisitions
    foreach ($requisitions as &$req) {
        if (!is_array($req)) {
            continue;
        }

        $req['items'] = $itemsByReq[$req['id']] ?? [];
        $req['attachments'] = $attachmentsByReq[$req['id']] ?? [];
        $req['supplier'] = null;
        $req['requested_by_user'] = null;
        $req['approved_by_user'] = null;
    }

    // Fetch all suppliers and users in single queries (N+1 optimization)
    $supplierIds = array_filter(array_unique(array_column($requisitions, 'supplier_id')));
    $userIds = array_filter(array_unique(array_column($requisitions, 'requested_by')));
    $approvedByIds = array_filter(array_unique(array_column($requisitions, 'approved_by')));
    $allUserIds = array_unique(array_merge($userIds, $approvedByIds));

    $suppliersById = [];
    if (!empty($supplierIds)) {
        $supplierIdsString = implode(',', $supplierIds);
        $query = http_build_query([
            'select' => 'id,name',
            'id' => 'in.(' . $supplierIdsString . ')'
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/suppliers?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $suppliersData = json_decode($response, true) ?: [];
        curl_close($ch);

        foreach ($suppliersData as $supplier) {
            $suppliersById[$supplier['id']] = $supplier;
        }
    }

    $usersById = [];
    if (!empty($allUserIds)) {
        $userIdsString = implode(',', $allUserIds);
        $query = http_build_query([
            'select' => 'id,full_name,email,signature',
            'id' => 'in.(' . $userIdsString . ')'
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
        $usersData = json_decode($response, true) ?: [];
        curl_close($ch);

        foreach ($usersData as $user) {
            $usersById[$user['id']] = $user;
        }
    }

    // Assign suppliers and users to requisitions
    foreach ($requisitions as &$req) {
        if (!is_array($req)) {
            continue;
        }

        $req['supplier'] = $suppliersById[$req['supplier_id']] ?? null;
        $req['requested_by_user'] = $usersById[$req['requested_by']] ?? null;
        $req['approved_by_user'] = $usersById[$req['approved_by']] ?? null;
    }
    // Unset the reference to avoid issues
    unset($req);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Requisitions - Work Card System</title>

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

    <!-- DataTables CSS -->
    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css"
    />

    <!-- Reuse layout styles -->
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">

    <style>
        .print-only {
            display: none;
        }

        @media print {
            .print-only {
                display: block;
            }
            .modal {
                position: static;
                display: block;
                overflow: visible;
            }
            .modal-dialog {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            .modal-content {
                border: none;
                box-shadow: none;
            }
            .modal-header .btn-close,
            .modal-footer {
                display: none !important;
            }
            body > *:not(.modal) {
                display: none;
            }
            .modal {
                display: block !important;
            }
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'requisitions';
        include __DIR__ . '/partials/sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1 d-flex flex-column">
            <!-- Top Navbar -->
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
                    <span id="pageTitle">Requisitions</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <!-- Requisitions Content -->
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <?php if ($message): ?>
                    <div class="mb-4 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info d-none" id="setupAlert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Requisition Module</strong> - This module is a placeholder. To enable full functionality, you need to create the following Supabase tables: <code>requisitions</code>, <code>requisition_items</code>, <code>items</code>, <code>suppliers</code>, and <code>requisition_attachments</code>.
                </div>

                <!-- Add Requisition Form -->
                <?php if (isset($_GET['ticket_id'])): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-file-earmark-plus me-2"></i>Create New Requisition
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="requisitionForm" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Department / Branch</label>
                                <input type="text" class="form-control form-control-sm" name="department" placeholder="e.g., Production, Warehouse">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Required Date</label>
                                <input type="date" class="form-control form-control-sm" name="required_date">
                            </div>
                            <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($_GET['ticket_id']); ?>">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Supplier</label>
                                <div class="input-group">
                                    <select class="form-select form-select-sm" name="supplier_id" id="supplierSelect">
                                        <option value="">Select Supplier</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo htmlspecialchars($supplier['id']); ?>">
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openAddSupplierModal()">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Share With (For Approval)</label>
                                <small class="text-muted d-block mb-1">Search users by name or email to share for approval (multiple users allowed)</small>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm" id="sharedWithInput" placeholder="Search users..." autocomplete="off">
                                    <div id="sharedWithDropdown" class="dropdown-menu w-100" style="position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                                </div>
                                <div id="selectedUsers" class="mt-1"></div>
                                <input type="hidden" name="shared_with" id="sharedWith" value="">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Items</label>
                                <div id="itemsContainer">
                                    <div class="item-row row g-2 mb-2">
                                        <div class="col-md-3">
                                            <div class="input-group">
                                                <select class="form-select form-select-sm item-select" name="item_id[]" onchange="window.updateUnitFromItem(this)">
                                                    <option value="">Select Item</option>
                                                    <?php foreach ($items as $item): ?>
                                                        <option value="<?php echo htmlspecialchars($item['id']); ?>" data-unit="<?php echo htmlspecialchars($item['unit']); ?>" data-price="<?php echo htmlspecialchars($item['unit_price'] ?? 0); ?>">
                                                            <?php echo htmlspecialchars($item['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.openAddItemModal(this)">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" step="0.01" class="form-control form-control-sm" name="quantity[]" placeholder="Quantity">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select form-select-sm unit-select" name="unit[]">
                                                <option value="kg">kg</option>
                                                <option value="liters">liters</option>
                                                <option value="pieces">pieces</option>
                                                <option value="bags">bags</option>
                                                <option value="boxes">boxes</option>
                                                <option value="tons">tons</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" step="0.01" class="form-control form-control-sm price-input" name="unit_price[]" placeholder="Price">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.removeItem(this)">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="window.addItem()">
                                    <i class="bi bi-plus-circle"></i> Add Item
                                </button>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Attachments</label>
                                <input type="file" class="form-control form-control-sm" name="attachments[]" multiple>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check-circle"></i> Create Requisition
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Requisitions Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>All Requisitions
                        </h5>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openCreateRequisitionModal()">
                            <i class="bi bi-plus-circle me-1"></i> New Requisition
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="requisitionsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small text-uppercase text-muted">RQ #</th>
                                        <th class="small text-uppercase text-muted">Ticket ID</th>
                                        <th class="small text-uppercase text-muted">Department</th>
                                        <th class="small text-uppercase text-muted">Required Date</th>
                                        <th class="small text-uppercase text-muted">Status</th>
                                        <th class="small text-uppercase text-muted">Approval Progress</th>
                                        <th class="small text-uppercase text-muted">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($requisitions)): ?>
                                        <tr>
                                            <td class="text-center text-muted small py-3">-</td>
                                            <td class="text-center text-muted small py-3">-</td>
                                            <td class="text-center text-muted small py-3">-</td>
                                            <td class="text-center text-muted small py-3">-</td>
                                            <td class="text-center text-muted small py-3">-</td>
                                            <td class="text-center text-muted small py-3">-</td>
                                            <td class="text-center text-muted small py-3">-</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($requisitions as $req): ?>
                                            <?php if (!is_array($req)) continue; ?>
                                            <tr>
                                                <td class="small"><?php echo htmlspecialchars($req['requisition_number'] ?? '-'); ?></td>
                                                <td class="small">
                                                    <?php if (!empty($req['ticket_id'])): ?>
                                                        <a href="tickets.php?ticket_id=<?php echo htmlspecialchars($req['ticket_id']); ?>" class="text-primary text-decoration-none"><?php echo htmlspecialchars($req['ticket_id']); ?></a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small"><?php echo htmlspecialchars($req['department'] ?? '-'); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($req['required_date'] ?? '-'); ?></td>
                                                <td class="small">
                                                    <span class="badge rounded-pill <?php
                                                        $status = strtolower($req['status'] ?? '');
                                                        $badgeClass = 'bg-secondary';
                                                        if ($status === 'pending') $badgeClass = 'bg-warning text-dark';
                                                        elseif ($status === 'approved') $badgeClass = 'bg-success';
                                                        elseif ($status === 'partially_approved') $badgeClass = 'bg-info';
                                                        elseif ($status === 'rejected') $badgeClass = 'bg-danger';
                                                        echo $badgeClass;
                                                    ?> small">
                                                        <?php
                                                        if ($status === 'partially_approved') echo 'Partially Approved';
                                                        else echo ucfirst($req['status'] ?? '-');
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="small">
                                                    <?php
                                                    $sharedWith = $req['shared_with'] ?? '';
                                                    $approvedByUsers = $req['approved_by_users'] ?? [];
                                                    $sharedWithArray = !empty($sharedWith) ? explode(',', $sharedWith) : [];
                                                    $approvedByArray = is_array($approvedByUsers) ? $approvedByUsers : (!empty($approvedByUsers) ? json_decode($approvedByUsers, true) : []);
                                                    $totalApprovers = count($sharedWithArray);
                                                    $approvedCount = count($approvedByArray);
                                                    $progress = $totalApprovers > 0 ? ($approvedCount / $totalApprovers) * 100 : 0;
                                                    ?>
                                                    <?php if ($totalApprovers > 0): ?>
                                                        <div class="progress" style="height: 20px; width: 100px;">
                                                            <div class="progress-bar <?php echo $progress >= 100 ? 'bg-success' : ($progress > 0 ? 'bg-info' : 'bg-secondary'); ?>"
                                                                 role="progressbar"
                                                                 style="width: <?php echo $progress; ?>%"
                                                                 aria-valuenow="<?php echo $progress; ?>"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                <?php echo $approvedCount; ?>/<?php echo $totalApprovers; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small">
                                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewRequisition(<?php echo htmlspecialchars(json_encode($req)); ?>)">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="openUploadModal('<?php echo htmlspecialchars($req['id']); ?>')">
                                                        <i class="bi bi-upload"></i> Upload
                                                    </button>
                                                    <?php
                                                    // Show approve button if user is in shared_with and hasn't approved yet
                                                    // Requester cannot approve their own requisition unless explicitly in approvers list
                                                    $isRequester = ($req['requested_by'] ?? '') === $currentUserId;
                                                    $currentUserInShared = in_array($currentUserId, $sharedWithArray);
                                                    $currentUserApproved = false;
                                                    foreach ($approvedByArray as $approval) {
                                                        if (is_array($approval) && isset($approval['user_id']) && $approval['user_id'] === $currentUserId) {
                                                            $currentUserApproved = true;
                                                            break;
                                                        } elseif (is_string($approval) && $approval === $currentUserId) {
                                                            // Handle old format (just user IDs)
                                                            $currentUserApproved = true;
                                                            break;
                                                        }
                                                    }
                                                    // Check if current user is one of the first 2 in shared_with (only they can approve/reject)
                                                    $currentUserIndex = array_search($currentUserId, $sharedWithArray);
                                                    $isFirstOrSecondApprover = $currentUserIndex !== false && $currentUserIndex < 2;
                                                    // User can approve if: in shared_with AND hasn't approved AND status not approved or rejected AND is first or second approver
                                                    // Requester can only approve if they are explicitly in the shared_with list
                                                    $canApprove = $currentUserInShared && !$currentUserApproved && !in_array(($req['status'] ?? ''), ['approved', 'rejected']) && $isFirstOrSecondApprover;
                                                    // User can reject if: in shared_with AND status is pending or partially_approved AND is first or second approver
                                                    $canReject = $currentUserInShared && in_array(($req['status'] ?? ''), ['pending', 'partially_approved']) && $isFirstOrSecondApprover;
                                                    if ($canApprove): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success me-1" onclick="openApproveModal('<?php echo htmlspecialchars($req['id']); ?>')">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($canReject): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger me-1" onclick="openRejectModal('<?php echo htmlspecialchars($req['id']); ?>')">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="app.js"></script>

    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#requisitionsTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                order: [], // Disable initial sorting to preserve server-side order (created_at desc)
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search requisitions..."
                },
                columnDefs: [
                    { orderable: false, targets: 6 } // Disable sorting on Actions column
                ]
            });
        });
    </script>
    <script>
        // Global functions for HTML event handlers
        window.updateUnitFromItem = function(select) {
            const selectedOption = select.options[select.selectedIndex];
            const unit = selectedOption.dataset.unit;
            const price = selectedOption.dataset.price;
            
            const row = select.closest('.item-row');
            const unitSelect = row.querySelector('.unit-select');
            const priceInput = row.querySelector('.price-input');
            
            if (unit) unitSelect.value = unit;
            if (price) priceInput.value = price;
        };

        window.addItem = function(containerId = 'itemsContainer') {
            const container = document.getElementById(containerId);
            const newRow = document.createElement('div');
            newRow.className = 'item-row row g-2 mb-2';

            let itemOptions = '<option value="">Select Item</option>';
            window.itemsData.forEach(item => {
                itemOptions += `<option value="${item.id}" data-unit="${item.unit}" data-price="${item.unit_price || 0}">${item.name}</option>`;
            });

            newRow.innerHTML = `
                <div class="col-md-3">
                    <div class="input-group">
                        <select class="form-select form-select-sm item-select" name="item_id[]" onchange="window.updateUnitFromItem(this)">
                            ${itemOptions}
                        </select>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.openAddItemModal(this)">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <input type="number" step="0.01" class="form-control form-control-sm" name="quantity[]" placeholder="Quantity">
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm unit-select" name="unit[]">
                        <option value="kg">kg</option>
                        <option value="liters">liters</option>
                        <option value="pieces">pieces</option>
                        <option value="bags">bags</option>
                        <option value="boxes">boxes</option>
                        <option value="tons">tons</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" step="0.01" class="form-control form-control-sm price-input" name="unit_price[]" placeholder="Price">
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.removeItem(this)">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        };

        window.removeItem = function(button) {
            const container = button.closest('[id$="ItemsContainer"]');
            if (container && container.children.length > 1) {
                button.closest('.item-row').remove();
            } else {
                alert('At least one item is required');
            }
        };
    </script>
    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = supabaseUrl && supabaseKey ? createClient(supabaseUrl, supabaseKey) : null;
        const userEmail = '<?php echo $userEmail; ?>';
        const ticketIdParam = '<?php echo $ticketId; ?>';
        const currentUserId = '<?php echo $_SESSION['user_id'] ?? ''; ?>';

       
        // Get items data from PHP for JavaScript
        window.itemsData = <?php echo json_encode($items); ?>;

        // Load users for shared_with selection
        let allUsers = [];
        let selectedUsers = [];

        async function loadUsers() {
            try {
                const { data, error } = await supabase
                    .from('users')
                    .select('id, email, full_name')
                    .order('full_name', { ascending: true });

                if (error) throw error;
                allUsers = data || [];
            } catch (err) {
                console.error('Error loading users:', err);
            }
        }

        loadUsers();

        // Reusable function to initialize user search
        window.initUserSearch = function(inputId, dropdownId, selectedDivId, hiddenInputId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            const selectedDiv = document.getElementById(selectedDivId);
            const hiddenInput = document.getElementById(hiddenInputId);

            if (!input || !dropdown || !selectedDiv || !hiddenInput) {
                console.error('User search elements not found');
                return;
            }

            let localSelectedUsers = [];

            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                dropdown.innerHTML = '';

                if (searchTerm.length < 2) {
                    dropdown.classList.remove('show');
                    return;
                }

                const filteredUsers = allUsers.filter(user => {
                    const fullName = (user.full_name || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    return fullName.includes(searchTerm) || email.includes(searchTerm);
                });

                if (filteredUsers.length > 0) {
                    filteredUsers.forEach(user => {
                        const option = document.createElement('a');
                        option.className = 'dropdown-item';
                        option.href = '#';
                        option.textContent = `${user.full_name || user.email} (${user.email})`;
                        option.addEventListener('click', function(e) {
                            e.preventDefault();
                            addUserToSelection(user);
                        });
                        dropdown.appendChild(option);
                    });
                    dropdown.classList.add('show');
                } else {
                    dropdown.classList.remove('show');
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });

            function addUserToSelection(user) {
                // Check if user already selected
                if (localSelectedUsers.find(u => u.id === user.id)) {
                    return;
                }

                localSelectedUsers.push(user);
                input.value = '';
                dropdown.classList.remove('show');

                updateSelectedUsersDisplay();
            }

            function removeUserFromSelection(userId) {
                localSelectedUsers = localSelectedUsers.filter(u => u.id !== userId);
                updateSelectedUsersDisplay();
            }

            function updateSelectedUsersDisplay() {
                selectedDiv.innerHTML = '';
                const userIds = [];

                localSelectedUsers.forEach(user => {
                    userIds.push(user.id);
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-primary me-1 mb-1';
                    badge.innerHTML = `
                        ${user.full_name || user.email}
                        <button type="button" class="btn-close btn-close-white ms-1" data-user-id="${user.id}"></button>
                    `;
                    selectedDiv.appendChild(badge);
                });

                hiddenInput.value = userIds.join(',');

                // Add event listeners to remove buttons
                selectedDiv.querySelectorAll('.btn-close').forEach(btn => {
                    btn.addEventListener('click', function() {
                        removeUserFromSelection(this.dataset.userId);
                    });
                });
            }
        };

        // Searchable user selection (multiple users) - original form
        const sharedWithInput = document.getElementById('sharedWithInput');
        const sharedWithDropdown = document.getElementById('sharedWithDropdown');
        const selectedUsersDiv = document.getElementById('selectedUsers');
        const sharedWithHidden = document.getElementById('sharedWith');

        if (sharedWithInput) {
            window.initUserSearch('sharedWithInput', 'sharedWithDropdown', 'selectedUsers', 'sharedWith');
        }

        function addUser(user) {
            // Check if user already selected
            if (selectedUsers.find(u => u.id === user.id)) {
                return;
            }

            selectedUsers.push(user);
            sharedWithInput.value = '';
            sharedWithDropdown.classList.remove('show');

            updateSelectedUsersDisplay();
        }

        function removeUser(userId) {
            selectedUsers = selectedUsers.filter(u => u.id !== userId);
            updateSelectedUsersDisplay();
        }

        function updateSelectedUsersDisplay() {
            selectedUsersDiv.innerHTML = '';
            const userIds = [];

            selectedUsers.forEach(user => {
                userIds.push(user.id);
                const badge = document.createElement('span');
                badge.className = 'badge bg-primary me-1 mb-1';
                badge.innerHTML = `
                    ${user.full_name || user.email}
                    <button type="button" class="btn-close btn-close-white ms-1" onclick="removeUser('${user.id}')"></button>
                `;
                selectedUsersDiv.appendChild(badge);
            });

            sharedWithHidden.value = userIds.join(',');
        }

        window.removeUser = removeUser;

        // Handle form submission
        const requisitionForm = document.getElementById('requisitionForm');
        if (requisitionForm) {
            requisitionForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const submitBtn = requisitionForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';

                if (!supabase) {
                    alert('Supabase connection not available');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    return;
                }

                // Get current user
                const { data: userData } = await supabase
                    .from('users')
                    .select('id')
                    .eq('email', userEmail)
                    .single();
                
                if (!userData) {
                    alert('User not found');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    return;
                }

                // Collect form data
                const formData = new FormData(requisitionForm);
                const department = formData.get('department');
                const requiredDate = formData.get('required_date');
                const supplierId = formData.get('supplier_id');
                const ticketIdFromForm = formData.get('ticket_id');
                const sharedWith = formData.get('shared_with');
                
                const itemIds = formData.getAll('item_id[]');
                const quantities = formData.getAll('quantity[]');
                const units = formData.getAll('unit[]');
                const unitPrices = formData.getAll('unit_price[]');
                
                // Validate
                if (!itemIds[0] || !quantities[0]) {
                    alert('Please add at least one item');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    return;
                }

                try {
                    // Generate requisition number
                    const requisitionNumber = 'RQ-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + Math.floor(1000 + Math.random() * 9000);
                    
                    // Create requisition
                    const requisitionData = {
                        requisition_number: requisitionNumber,
                        requested_by: userData.id,
                        department: department || null,
                        required_date: requiredDate || null,
                        supplier_id: supplierId || null,
                        shared_with: sharedWith || null,
                        status: 'pending'
                    };

                    // Add ticket_id if provided (prefer form value over URL param)
                    console.log('ticketIdFromForm:', ticketIdFromForm);
                    console.log('ticketIdParam:', ticketIdParam);

                    if (ticketIdFromForm) {
                        requisitionData.ticket_id = ticketIdFromForm;
                    } else if (ticketIdParam) {
                        requisitionData.ticket_id = ticketIdParam;
                    }
                    
                    console.log('Inserting requisition with ticket_id:', requisitionData.ticket_id);
                    
                    const { data: requisition, error: reqError } = await supabase
                        .from('requisitions')
                        .insert(requisitionData)
                        .select()
                        .single();
                    
                    if (reqError) throw reqError;
                    
                    // Add requisition items
                    for (let i = 0; i < itemIds.length; i++) {
                        if (itemIds[i] && quantities[i]) {
                            await supabase.from('requisition_items').insert({
                                requisition_id: requisition.id,
                                item_id: itemIds[i],
                                quantity: parseFloat(quantities[i]),
                                unit: units[i],
                                unit_price: parseFloat(unitPrices[i]) || 0
                            });
                        }
                    }
                    
                    // Handle file attachments
                    const fileInput = requisitionForm.querySelector('input[type="file"]');
                    console.log('File input:', fileInput);
                    console.log('Files selected:', fileInput ? fileInput.files.length : 0);

                    if (fileInput && fileInput.files.length > 0) {
                        for (const file of fileInput.files) {
                            console.log('Uploading file:', file.name);
                            const formData = new FormData();
                            formData.append('action', 'upload_attachment');
                            formData.append('requisition_id', requisition.id);
                            formData.append('file', file);
                            formData.append('original_name', file.name);
                            formData.append('file_size', file.size);
                            formData.append('mime_type', file.type);

                            const uploadResponse = await fetch('upload_attachment.php', {
                                method: 'POST',
                                body: formData
                            });

                            console.log('Upload response status:', uploadResponse.status);
                            const uploadResult = await uploadResponse.json();
                            console.log('Upload response:', uploadResult);

                            if (!uploadResult.success) {
                                throw new Error('Failed to upload attachment: ' + uploadResult.message);
                            }
                        }
                    }

                    // Send email notification to shared_with users
                    if (sharedWith) {
                        const sharedWithIds = sharedWith.split(',').map(id => id.trim());
                        if (sharedWithIds.length > 0) {
                            // Fetch email addresses of shared_with users
                            const { data: sharedWithUsers, error: usersError } = await supabase
                                .from('users')
                                .select('email, full_name')
                                .in('id', sharedWithIds);

                            if (!usersError && sharedWithUsers && sharedWithUsers.length > 0) {
                                const sharedWithEmails = sharedWithUsers.map(u => u.email);

                                // Send email notification via notify_requisition.php
                                const mailBody = `
                                <div style='font-family: Arial, sans-serif; background:#f4f6f9; padding:20px;'>
                                    <img src='https://texolenergies.com/assets/Logo-paGHQfRF.svg' alt='Texol Energies' style='width:140px; margin:0 auto 15px; display:block;' />
                                    <div style='max-width:650px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);'>
                                        <div style='background:#1f3c88; color:#ffffff; padding:25px; text-align:center;'>
                                            <h2 style='margin:0;'>New Requisition Created</h2>
                                        </div>
                                        <div style='padding:25px;'>
                                            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                                                <strong>Requisition Number:</strong> ${requisitionNumber}
                                            </p>
                                            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                                                <strong>Department:</strong> ${department}
                                            </p>
                                            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                                                <strong>Required Date:</strong> ${requiredDate}
                                            </p>
                                            <div style='margin-bottom:20px;'>
                                                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#e8f0ff; color:#1f3c88; margin:3px;'>
                                                    Status: Pending
                                                </span>
                                                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#f0f0f0; color:#555; margin:3px;'>
                                                    Department: ${department}
                                                </span>
                                            </div>
                                            <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                                                <a href='https://support.texolenergies.com/requisition' style='color:#1f3c88; text-decoration:none; font-weight:bold;'>View Requisition</a>
                                            </p>
                                            <div style='margin-top:25px; text-align:center;'>
                                                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#1f3c88; color:#fff; margin:3px;'>
                                                    Requisition Notification
                                                </span>
                                                <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#e9f7ef; color:#1e7e34; margin:3px;'>
                                                    System Generated
                                                </span>
                                            </div>
                                        </div>
                                        <div style='background:#f4f6f9; padding:15px; text-align:center; font-size:12px; color:#777;'>
                                            <p style='margin:0;'>Texol Energies - THI Support</p>
                                            <p style='margin:5px 0 0;'>Please do not reply to this email.</p>
                                        </div>
                                    </div>
                                </div>`;

                                const toEmail = sharedWithEmails[0] || '';
                                const ccEmails = sharedWithEmails.length > 1 ? sharedWithEmails.slice(1).join(',') : '';

                                console.log('Shared with emails:', sharedWithEmails);
                                console.log('To email:', toEmail);
                                console.log('CC emails:', ccEmails);

                                const emailData = {
                                    type: 'requisition',
                                    to: toEmail,
                                    cc: ccEmails,
                                    subject: 'New Requisition Created: ' + requisitionNumber,
                                    body: mailBody
                                };

                                console.log('Email data being sent:', emailData);

                                try {
                                    const emailResponse = await fetch('notify_requisition.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json'
                                        },
                                        body: JSON.stringify(emailData)
                                    });
                                    const responseText = await emailResponse.text();
                                    console.log('Email notification response:', responseText);
                                    if (responseText) {
                                        try {
                                            const emailResult = JSON.parse(responseText);
                                            console.log('Email notification result:', emailResult);
                                        } catch (jsonErr) {
                                            console.error('Failed to parse email response:', responseText);
                                        }
                                    }
                                } catch (emailErr) {
                                    console.error('Failed to send email notification:', emailErr);
                                }
                            }
                        }
                    }

                    alert('Requisition created successfully!');
                    window.location.reload();
                } catch (error) {
                    console.error('Error creating requisition:', error);
                    alert('Error creating requisition: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });
        }

        // View requisition modal
        window.viewRequisition = function(req) {
            const modal = document.getElementById('viewRequisitionModal');
            const details = document.getElementById('requisitionDetails');

            if (!modal || !details) {
                // Create modal if it doesn't exist
                createViewModal();
                return viewRequisition(req);
            }

            // Store current requisition for printing
            window.currentRequisition = req;
            
            // Calculate total
            let total = 0;
            if (req.items && req.items.length > 0) {
                req.items.forEach(item => {
                    total += (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
                });
            }
            
            let itemsHtml = '';
            if (req.items && req.items.length > 0) {
                itemsHtml = `
                    <table class="table table-sm mt-2">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${req.items.map(item => {
                                const itemTotal = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
                                const itemName = item.item ? item.item.name : 'Unknown';
                                return `
                                    <tr>
                                        <td>${itemName}</td>
                                        <td>${item.quantity}</td>
                                        <td>${item.unit}</td>
                                        <td>${item.unit_price || 0}</td>
                                        <td>${itemTotal.toFixed(2)}</td>
                                    </tr>
                                `;
                            }).join('')}
                            <tr class="table-light fw-semibold">
                                <td colspan="4" class="text-end">Grand Total:</td>
                                <td>${total.toFixed(2)}</td>
                            </tr>
                        </tbody>
                    </table>
                `;
            } else {
                itemsHtml = '<p class="text-muted mt-2">No items</p>';
            }
            
            let attachmentsHtml = '';
            if (req.attachments && req.attachments.length > 0) {
                attachmentsHtml = `
                    <div class="mt-3">
                        <h6 class="fw-semibold">Attachments</h6>
                        <ul class="list-unstyled">
                            ${req.attachments.map(attach => `
                                <li><a href="${attach.file_path}" download="${attach.original_name}" class="text-primary text-decoration-none">
                                    <i class="bi bi-download me-1"></i>${attach.original_name}
                                </a></li>
                            `).join('')}
                        </ul>
                    </div>
                `;
            }
            
            const requestedByName = req.requested_by_user ? (req.requested_by_user.full_name || req.requested_by_user.email) : '-';
            const supplierName = req.supplier ? req.supplier.name : '-';

            // Parse shared_with to get first and second users for review/approval
            const sharedWith = req.shared_with || '';
            const sharedWithArray = sharedWith ? sharedWith.split(',').map(id => id.trim()) : [];
            const firstSharedUserId = sharedWithArray.length > 0 ? sharedWithArray[0] : null;
            const secondSharedUserId = sharedWithArray.length > 1 ? sharedWithArray[1] : null;

            // Signature section for print
            let signatureHtml = '';
            if (req.requested_by_user && req.requested_by_user.signature) {
                signatureHtml = `
                    <div class="mt-3 print-only">
                        <h6 class="fw-semibold">Requested By Signature</h6>
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <small class="text-muted">Name:</small>
                                <div class="fw-semibold">${requestedByName}</div>
                            </div>
                            <div>
                                <img src="${req.requested_by_user.signature}" alt="Signature" style="max-height: 60px; max-width: 150px;">
                            </div>
                        </div>
                    </div>
                `;
            }

            // Reviewed By and Approved By sections
            let reviewApprovalHtml = '';
            if (firstSharedUserId || secondSharedUserId) {
                reviewApprovalHtml = `
                    <div class="mt-3 print-only">
                        <h6 class="fw-semibold">Approval Chain</h6>
                        <div class="row g-3">
                            ${firstSharedUserId ? `
                                <div class="col-6">
                                    <small class="text-muted">Reviewed By (1st Approver):</small>
                                    <div class="fw-semibold" id="reviewedBy-${firstSharedUserId}">Loading...</div>
                                    <div id="reviewedBySig-${firstSharedUserId}"></div>
                                </div>
                            ` : ''}
                            ${secondSharedUserId ? `
                                <div class="col-6">
                                    <small class="text-muted">Approved By (2nd Approver):</small>
                                    <div class="fw-semibold" id="approvedBy-${secondSharedUserId}">Loading...</div>
                                    <div id="approvedBySig-${secondSharedUserId}"></div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }

            details.innerHTML = `
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <small class="text-muted">Requisition #:</small>
                        <div class="fw-semibold">${req.requisition_number || '-'}</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Status:</small>
                        <div class="fw-semibold">${req.status || '-'}</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Department:</small>
                        <div>${req.department || '-'}</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Required Date:</small>
                        <div>${req.required_date || '-'}</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Supplier:</small>
                        <div>${supplierName}</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Requested By:</small>
                        <div>${requestedByName}</div>
                    </div>
                </div>
                <h6 class="fw-semibold mt-3">Items</h6>
                ${itemsHtml}
                ${attachmentsHtml}
                ${signatureHtml}
                ${reviewApprovalHtml}
            `;

            // Fetch user details for approval chain
            if (firstSharedUserId) {
                fetchUserDetails(firstSharedUserId, 'reviewedBy');
            }
            if (secondSharedUserId) {
                fetchUserDetails(secondSharedUserId, 'approvedBy');
            }

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        };

        async function fetchUserDetails(userId, prefix) {
            if (!supabase) return;

            try {
                const { data, error } = await supabase
                    .from('users')
                    .select('id, full_name, email, signature')
                    .eq('id', userId)
                    .single();

                if (error) throw error;

                const nameElement = document.getElementById(`${prefix}-${userId}`);
                const sigElement = document.getElementById(`${prefix}Sig-${userId}`);

                if (nameElement) {
                    nameElement.textContent = data.full_name || data.email || '-';
                }

                if (sigElement && data.signature) {
                    sigElement.innerHTML = `<img src="${data.signature}" alt="Signature" style="max-height: 60px; max-width: 150px; margin-top: 5px;">`;
                }
            } catch (err) {
                console.error('Error fetching user details:', err);
                const nameElement = document.getElementById(`${prefix}-${userId}`);
                if (nameElement) {
                    nameElement.textContent = 'Error loading user';
                }
            }
        }

        function createViewModal() {
            const modalHtml = `
                <div class="modal fade" id="viewRequisitionModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Requisition Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="requisitionDetails"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="printRequisition()">
                                    <i class="bi bi-printer me-1"></i> Print
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        // Print requisition in new window
        window.printRequisition = function() {
            const req = window.currentRequisition;
            if (!req) return;

            // Helper function to format date as day/month/year (dd/mm/yy)
            const formatDateDMY = (dateString) => {
                if (!dateString) return '-';
                const date = new Date(dateString);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = String(date.getFullYear()).slice(-2); // Get last 2 digits
                return `${day}/${month}/${year}`;
            };

            // Get requester info
            const requestedByName = req.requested_by_user ? (req.requested_by_user.full_name || req.requested_by_user.email) : '-';
            const requesterDepartment = req.department || '-';
            const createdDate = formatDateDMY(req.created_at);
            const requiredDate = req.required_date ? formatDateDMY(req.required_date) : '-';

            // Calculate grand total
            let grandTotal = 0;
            if (req.items && req.items.length > 0) {
                req.items.forEach(item => {
                    grandTotal += (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
                });
            }

            // Get approver info
            const approverName = req.approved_by_user ? (req.approved_by_user.full_name || req.approved_by_user.email) : '';
            const approvedDate = formatDateDMY(req.approved_at);
            const approverSignature = (req.approved_by_user && req.approved_by_user.signature) ? req.approved_by_user.signature : '';

            // Parse approved_by_users to get actual approvers (not just shared_with)
            const approvedByUsers = req.approved_by_users || [];
            const approvedByArray = Array.isArray(approvedByUsers) ? approvedByUsers : (typeof approvedByUsers === 'string' ? JSON.parse(approvedByUsers) : []);

            // Extract user IDs and dates from approved_by array (handle both old format and new object format)
            const actualApprovers = approvedByArray.map(approval => {
                if (typeof approval === 'object' && approval.user_id) {
                    return {
                        user_id: approval.user_id,
                        approved_at: approval.approved_at
                    };
                }
                return { user_id: approval, approved_at: null }; // old format (just user ID string)
            });

            // Get first and second actual approvers for review/approval in print
            const firstApprover = actualApprovers.length > 0 ? actualApprovers[0] : null;
            const secondApprover = actualApprovers.length > 1 ? actualApprovers[1] : null;

            // Format approval dates
            const firstApprovalDate = firstApprover && firstApprover.approved_at ? formatDateDMY(firstApprover.approved_at) : '';
            const secondApprovalDate = secondApprover && secondApprover.approved_at ? formatDateDMY(secondApprover.approved_at) : '';

            // Get requester signature
            const requesterSignature = (req.requested_by_user && req.requested_by_user.signature) ? req.requested_by_user.signature : '';

            // Generate items rows
            let itemsRows = '';
            if (req.items && req.items.length > 0) {
                itemsRows = req.items.map((item, index) => {
                    const itemName = item.item ? item.item.name : 'Unknown';
                    const quantity = item.quantity || 0;
                    const unit = item.unit || '';
                    return `
                        <tr>
                            <td class="no-col">${index + 1}</td>
                            <td class="item-col">${itemName}</td>
                            <td class="desc-col"></td>
                            <td class="qty-col">${quantity} ${unit}</td>
                        </tr>
                    `;
                }).join('');
            }

            // Add empty rows to fill the table
            const emptyRowsNeeded = 8 - (req.items ? req.items.length : 0);
            for (let i = 0; i < emptyRowsNeeded; i++) {
                itemsRows += `
                    <tr>
                        <td class="no-col">&nbsp;</td>
                        <td class="item-col"></td>
                        <td class="desc-col"></td>
                        <td class="qty-col"></td>
                    </tr>
                `;
            }

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html lang="en">
                <head>
                <meta charset="UTF-8">
                <title>TEX-ADM-FRM-003 Requisition to Order Form</title>
                <style>
                  * { box-sizing: border-box; }
                  body {
                    font-family: "Century Gothic", "CenturyGothic", "Apple Gothic", Arial, sans-serif;
                    background: #e8e8e8;
                    margin: 0;
                    padding: 30px 0;
                  }
                  .page {
                    width: 850px;
                    background: #fff;
                    margin: 0 auto;
                    padding: 30px 35px 50px 35px;
                    position: relative;
                  }

                  /* Header table */
                  .header-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 2px solid #000;
                    margin-bottom: 22px;
                  }
                  .header-table td, .header-table th {
                    border: 1px solid #000;
                    padding: 4px 8px;
                    vertical-align: middle;
                  }
                  .header-title {
                    text-align: center;
                    font-weight: bold;
                    font-size: 10px;
                    letter-spacing: 0.5px;
                  }
                  .logo-cell {
                    width: 150px;
                    text-align: center;
                    padding: 6px;
                  }
                  .logo-main {
                    font-size: 26px;
                    font-weight: 800;
                    letter-spacing: 1px;
                    color: #444;
                    font-family: 'Trebuchet MS', Arial, sans-serif;
                  }
                  .logo-main .x { color: #b0b0b0; }
                  .logo-sub {
                    font-size: 6px;
                    letter-spacing: 2px;
                    color: #888;
                    margin-top: -2px;
                  }
                  .logo-tag {
                    font-size: 9px;
                    font-style: italic;
                    color: #333;
                    margin-top: 4px;
                  }
                  .form-name-cell {
                    text-align: center;
                    font-size: 10px;
                    font-weight: normal;
                  }
                  .meta-cell {
                    font-size: 10px;
                    text-align: left;
                    line-height: 1.6;
                  }
                  .meta-cell b { font-weight: bold; }
                  .page-cell {
                    text-align: center;
                    font-size: 10px;
                  }

                  /* Main title */
                  .main-title {
                    text-align: center;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.5px;
                    margin: 18px 0 20px 0;
                  }

                  /* From/Date table */
                  .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid #000;
                    margin-bottom: 14px;
                    font-size: 10px;
                  }
                  .info-table td {
                    border: 1px solid #000;
                    padding: 10px 8px;
                    width: 50%;
                  }

                  .request-line {
                    font-size: 11px;
                    margin: 10px 0 6px 0;
                  }

                  /* Items table */
                  .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid #000;
                    font-size: 10px;
                    margin-bottom: 20px;
                  }
                  .items-table th {
                    border: 1px solid #000;
                    background: #a6a6a6;
                    padding: 6px 4px;
                    font-size: 10px;
                    text-align: left;
                  }
                  .items-table td {
                    border: 1px solid #000;
                    padding: 12px 4px;
                  }
                  .items-table th.no-col, .items-table td.no-col { width: 4%; text-align: center; }
                  .items-table th.item-col, .items-table td.item-col { width: 15%; }
                  .items-table th.desc-col, .items-table td.desc-col { width: 61%; }
                  .items-table th.qty-col, .items-table td.qty-col { width: 20%; }

                  /* Bottom section: mandatory / budget / store */
                  .bottom-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid #000;
                    font-size: 10px;
                    margin-bottom: 18px;
                  }
                  .bottom-table th {
                    background: #a6a6a6;
                    border: 1px solid #000;
                    padding: 5px 6px;
                    text-align: left;
                    font-size: 10px;
                  }
                  .bottom-table td {
                    border: 1px solid #000;
                    padding: 8px 6px;
                    vertical-align: top;
                  }
                  .mandatory-desc {
                    font-size: 10px;
                    font-weight: normal;
                    font-style: normal;
                  }
                  .num-col { width: 4%; text-align: center; }
                  .mand-col { width: 46%; }
                  .budget-avail-col { width: 12%; text-align: center; }
                  .budget-amt-col { width: 13%; text-align: center; }
                  .store-item-col { width: 13%; text-align: center; }
                  .store-qty-col { width: 12%; text-align: center; }

                  /* Reason for procurement */
                  .reason-block {
                    font-size: 11px;
                    margin-bottom: 18px;
                  }
                  .dotted-line {
                    border-bottom: 1px dotted #000;
                    height: 20px;
                  }

                  /* Signature table */
                  .sig-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid #000;
                    font-size: 10px;
                    margin-bottom: 40px;
                  }
                  .sig-table th {
                    background: #a6a6a6;
                    border: 1px solid #000;
                    padding: 5px 8px;
                    text-align: left;
                  }
                  .sig-table td {
                    border: 1px solid #000;
                    padding: 16px 8px;
                    width: 33.33%;
                    font-weight: bold;
                  }

                  .footer-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    margin-top: 20px;
                  }
                  .footer-caption {
                    font-size: 11px;
                    font-style: italic;
                  }
                  .page-number {
                    font-size: 11px;
                  }

                  @media print {
                    body { background: #fff; padding: 0; }
                    .page { margin: 0; padding: 20px; width: 100%; }
                  }
                </style>
                </head>
                <body>

                <div class="page">

                  <!-- Header -->
                  <table class="header-table">
                    <tr>
                      <td colspan="3" class="header-title">TEXOL ENERGIES LIMITED</td>
                    </tr>
                    <tr>
                      <td class="logo-cell">
                        <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo" style="max-width: 120px; max-height: 60px;">
                        <br>
                        <small><i>Reliability Redefined</i></small>
                      </td>
                      <td class="form-name-cell">Requisition to Order Form</td>
                      <td class="meta-cell">
                        <b>TEX-ADM-FRM-003, Ver 000</b><br>
                        Issue Date: 1<sup>st</sup> Nov 2024
                      </td>
                    </tr>
                    <tr>
                      <td colspan="2"></td>
                      <td class="page-cell">Page 1 of 1</td>
                    </tr>
                  </table>

                  <div class="main-title">REQUISITION TO ORDER (RO) FORM</div>

                  <!-- From / Date -->
                  <table class="info-table">
                    <tr>
                      <td>From: ${requestedByName}</td>
                      <td>Date: ${createdDate}</td>
                    </tr>
                    <tr>
                      <td>Department: ${requesterDepartment}</td>
                      <td>Items Required by when: ${requiredDate}</td>
                    </tr>
                  </table>

                  <div class="request-line">Request: Please arrange for the procurement of the following items</div>

                  <!-- Items table -->
                  <table class="items-table">
                    <tr>
                      <th class="no-col">No.</th>
                      <th class="item-col">ITEM</th>
                      <th class="desc-col">DETAILED SPECIFIC DESCRIPTION<br>(Attachments, Drawings, BOQs)</th>
                      <th class="qty-col">ORDER QTY</th>
                    </tr>
                    ${itemsRows}
                  </table>

                  <!-- Mandatory requirements / budget / store check -->
                  <table class="bottom-table">
                    <tr>
                      <th colspan="2">MANDATORY REQUIREMENTS</th>
                      <th colspan="2">BUDGET CHECK</th>
                      <th colspan="2">STORE CHECK</th>
                    </tr>
                    <tr>
                      <td colspan="2" class="mandatory-desc">e.g. (warranty, sample required, country of origin, after sale support, delivery date, training, standardization, site visit etc.)</td>
                      <td class="budget-avail-col" rowspan="3"><b>Budget<br>Available (Y/N)</b></td>
                      <td class="budget-amt-col" rowspan="3"><b>Amount<br>Budgeted</b><p></p><br>${grandTotal.toFixed(2)}</td>
                      <td class="store-item-col" rowspan="3"><b>Item<br>Available in<br>store (Y/N)</b></td>
                      <td class="store-qty-col" rowspan="3"><b>Qty<br>Available</b></td>
                    </tr>
                    <tr>
                      <td class="num-col">1</td><td class="mand-col"></td>
                    </tr>
                    <tr>
                      <td class="num-col">2</td><td class="mand-col"></td>
                    </tr>
                  
                  </table>

                  <!-- Reason for procurement -->
                  <div class="reason-block">
                    Required for (Reason for procurement):<br><br>
                    <div class="dotted-line"></div>
                    <div class="dotted-line"></div>
                  </div>

                  <!-- Signature table -->
                  <table class="sig-table">
                    <tr>
                      <th>Requested by</th>
                      <th>Review by (Supervisor/HOD)</th>
                      <th>Approved by</th>
                    </tr>
                    <tr>
                      <td>Name: ${requestedByName}</td><td>Name: <span id="printReviewedByName"></span></td><td>Name: <span id="printApprovedByName"></span></td>
                    </tr>
                    <tr>
                      <td>Signature: ${requesterSignature ? '<img src="' + requesterSignature + '" alt="Signature" style="max-height: 40px; max-width: 100px;">' : ''}</td><td>Signature: <span id="printReviewedBySig"></span></td><td>Signature: <span id="printApprovedBySig"></span></td>
                    </tr>
                    <tr>
                      <td>Date: ${createdDate}</td><td>Date: <span id="printReviewedByDate">${firstApprovalDate}</span></td><td>Date: <span id="printApprovedByDate">${secondApprovalDate}</span></td>
                    </tr>
                  </table>

                  <div class="footer-row">
                    <div class="footer-caption"><em>Texol Energies Limited Requisition to Order Form</em></div>
                    <div class="page-number">1</div>
                  </div>

                </div>

                </body>
                </html>
            `);
            printWindow.document.close();

            // Fetch user details for print approval chain
            if (firstApprover) {
                fetchPrintUserDetails(printWindow, firstApprover, 'printReviewedBy');
            }
            if (secondApprover) {
                fetchPrintUserDetails(printWindow, secondApprover, 'printApprovedBy');
            }

            printWindow.focus();
        };

        async function fetchPrintUserDetails(printWindow, approver, prefix) {
            if (!supabase || !approver) return;

            try {
                const { data, error } = await supabase
                    .from('users')
                    .select('id, full_name, email, signature')
                    .eq('id', approver.user_id)
                    .single();

                if (error) throw error;

                const nameElement = printWindow.document.getElementById(`${prefix}Name`);
                const sigElement = printWindow.document.getElementById(`${prefix}Sig`);
                const dateElement = printWindow.document.getElementById(`${prefix}Date`);

                if (nameElement) {
                    nameElement.textContent = data.full_name || data.email || '-';
                }

                if (sigElement && data.signature) {
                    sigElement.innerHTML = `<img src="${data.signature}" alt="Signature" style="max-height: 40px; max-width: 100px;">`;
                }

                if (dateElement && approver.approved_at) {
                    const date = new Date(approver.approved_at);
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = String(date.getFullYear()).slice(-2);
                    dateElement.textContent = `${day}/${month}/${year}`;
                }
            } catch (err) {
                console.error('Error fetching user details for print:', err);
                const nameElement = printWindow.document.getElementById(`${prefix}Name`);
                if (nameElement) {
                    nameElement.textContent = 'Error loading user';
                }
            }
        }

        // Restore requisition
        window.restoreRequisition = function(reqId) {
            if (!confirm('Are you sure you want to restore this requisition?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'restore');
            formData.append('req_id', reqId);

            fetch('requisition.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const responseText = await response.text();
                console.log('Restore requisition response:', responseText);
                if (!responseText) {
                    throw new Error('Empty response from server');
                }
                return JSON.parse(responseText);
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while restoring the requisition.');
            });
        };

        // Create Requisition Modal (without ticket ID)
        window.openCreateRequisitionModal = function() {
            const modalHtml = `
                <div class="modal fade" id="createRequisitionModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Create New Requisition</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="standaloneRequisitionForm" class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Department / Branch</label>
                                        <input type="text" class="form-control form-control-sm" name="department" placeholder="e.g., Production, Warehouse" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Required Date</label>
                                        <input type="date" class="form-control form-control-sm" name="required_date" required>
                                    </div>
                                    <input type="hidden" name="ticket_id" value="">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Supplier</label>
                                        <div class="input-group">
                                            <select class="form-select form-select-sm" name="supplier_id" id="standaloneSupplierSelect">
                                                <option value="">Select Supplier</option>
                                                <?php foreach ($suppliers as $supplier): ?>
                                                    <option value="<?php echo htmlspecialchars($supplier['id']); ?>">
                                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openAddSupplierModal()">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Share With (For Approval)</label>
                                        <small class="text-muted d-block mb-1">Search users by name or email to share for approval (multiple users allowed)</small>
                                        <div class="position-relative">
                                            <input type="text" class="form-control form-control-sm" id="standaloneSharedWithInput" placeholder="Search users..." autocomplete="off">
                                            <div id="standaloneSharedWithDropdown" class="dropdown-menu w-100" style="position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                                        </div>
                                        <div id="standaloneSelectedUsers" class="mt-1"></div>
                                        <input type="hidden" name="shared_with" id="standaloneSharedWith" value="">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Items</label>
                                        <div id="standaloneItemsContainer">
                                            <div class="item-row row g-2 mb-2">
                                                <div class="col-md-3">
                                                    <div class="input-group">
                                                        <select class="form-select form-select-sm item-select" name="item_id[]" onchange="window.updateUnitFromItem(this)">
                                                            <option value="">Select Item</option>
                                                            <?php foreach ($items as $item): ?>
                                                                <option value="<?php echo htmlspecialchars($item['id']); ?>" data-unit="<?php echo htmlspecialchars($item['unit']); ?>" data-price="<?php echo htmlspecialchars($item['unit_price'] ?? 0); ?>">
                                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.openAddItemModal(this)">
                                                            <i class="bi bi-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" step="0.01" class="form-control form-control-sm" name="quantity[]" placeholder="Quantity" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <select class="form-select form-select-sm unit-select" name="unit[]">
                                                        <option value="kg">kg</option>
                                                        <option value="liters">liters</option>
                                                        <option value="pieces">pieces</option>
                                                        <option value="meters">meters</option>
                                                        <option value="units">units</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" step="0.01" class="form-control form-control-sm price-input" name="unit_price[]" placeholder="Unit Price">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="window.removeItem(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="window.addItem('standaloneItemsContainer')">
                                            <i class="bi bi-plus"></i> Add Item
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Attachments</label>
                                        <input type="file" class="form-control form-control-sm" name="attachments[]" multiple>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-check-circle"></i> Create Requisition
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('createRequisitionModal'));
            modal.show();
            document.getElementById('createRequisitionModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });

            // Initialize user search for standalone modal
            initUserSearch('standaloneSharedWithInput', 'standaloneSharedWithDropdown', 'standaloneSelectedUsers', 'standaloneSharedWith');

            // Handle form submission
            document.getElementById('standaloneRequisitionForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'create');

                fetch('requisition.php', {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const responseText = await response.text();
                    console.log('Create requisition modal response:', responseText);
                    if (!responseText) {
                        throw new Error('Empty response from server');
                    }
                    return JSON.parse(responseText);
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        bootstrap.Modal.getInstance(document.getElementById('createRequisitionModal')).hide();
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while creating the requisition.');
                });
            });
        };

        // Add Supplier Modal
        window.openAddSupplierModal = function() {
            const modalHtml = `
                <div class="modal fade" id="addSupplierModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add New Supplier</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addSupplierForm" class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Supplier Name *</label>
                                        <input type="text" class="form-control" id="supplierName" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Contact Person</label>
                                        <input type="text" class="form-control" id="supplierContact">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="supplierPhone">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" id="supplierEmail">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" id="supplierAddress" rows="2"></textarea>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveSupplier()">Save Supplier</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
            modal.show();
            document.getElementById('addSupplierModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        };

        window.saveSupplier = async function() {
            const name = document.getElementById('supplierName').value.trim();
            if (!name) {
                alert('Please enter supplier name');
                return;
            }

            const supplierData = {
                name: name,
                contact_person: document.getElementById('supplierContact').value || null,
                phone: document.getElementById('supplierPhone').value || null,
                email: document.getElementById('supplierEmail').value || null,
                address: document.getElementById('supplierAddress').value || null
            };

            try {
                const { data, error } = await supabase
                    .from('suppliers')
                    .insert(supplierData)
                    .select()
                    .single();

                if (error) throw error;

                // Add to supplier select
                const supplierSelect = document.getElementById('supplierSelect');
                const option = document.createElement('option');
                option.value = data.id;
                option.textContent = data.name;
                supplierSelect.appendChild(option);
                supplierSelect.value = data.id;

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addSupplierModal'));
                modal.hide();

                alert('Supplier added successfully');
            } catch (err) {
                console.error('Error adding supplier:', err);
                alert('Failed to add supplier: ' + err.message);
            }
        };

        // Add Item Modal
        let currentItemSelect = null;
        window.openAddItemModal = function(button) {
            currentItemSelect = button.closest('.input-group').querySelector('.item-select');
            
            const modalHtml = `
                <div class="modal fade" id="addItemModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add New Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addItemForm" class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Item Name *</label>
                                        <input type="text" class="form-control" id="itemName" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Unit *</label>
                                        <select class="form-select" id="itemUnit" required>
                                            <option value="kg">kg</option>
                                            <option value="liters">liters</option>
                                            <option value="pieces">pieces</option>
                                            <option value="bags">bags</option>
                                            <option value="boxes">boxes</option>
                                            <option value="tons">tons</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Unit Price</label>
                                        <input type="number" step="0.01" class="form-control" id="itemUnitPrice">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" id="itemDescription" rows="2"></textarea>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveItem()">Save Item</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('addItemModal'));
            modal.show();
            document.getElementById('addItemModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
                currentItemSelect = null;
            });
        };

        window.saveItem = async function() {
            const name = document.getElementById('itemName').value.trim();
            const unit = document.getElementById('itemUnit').value;
            if (!name || !unit) {
                alert('Please enter item name and unit');
                return;
            }

            const itemData = {
                name: name,
                unit: unit,
                unit_price: parseFloat(document.getElementById('itemUnitPrice').value) || null,
                description: document.getElementById('itemDescription').value || null
            };

            try {
                const { data, error } = await supabase
                    .from('items')
                    .insert(itemData)
                    .select()
                    .single();

                if (error) throw error;

                // Add to all item selects
                const itemSelects = document.querySelectorAll('.item-select');
                itemSelects.forEach(select => {
                    const option = document.createElement('option');
                    option.value = data.id;
                    option.textContent = data.name;
                    option.dataset.unit = data.unit;
                    option.dataset.price = data.unit_price || 0;
                    select.appendChild(option);
                });

                // Select the new item in the current select
                if (currentItemSelect) {
                    currentItemSelect.value = data.id;
                    updateUnitFromItem(currentItemSelect);
                }

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addItemModal'));
                modal.hide();

                alert('Item added successfully');
            } catch (err) {
                console.error('Error adding item:', err);
                alert('Failed to add item: ' + err.message);
            }
        };

        // Open approve modal
        window.openApproveModal = function(reqId) {
            const modal = document.getElementById('approveModal');
            const reqIdInput = document.getElementById('approveReqId');
            reqIdInput.value = reqId;
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        };

        // Open reject modal
        window.openRejectModal = function(reqId) {
            const modal = document.getElementById('rejectModal');
            const reqIdInput = document.getElementById('rejectReqId');
            reqIdInput.value = reqId;
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        };

        // Open upload modal
        window.openUploadModal = function(reqId) {
            const modal = document.getElementById('uploadModal');
            const reqIdInput = document.getElementById('uploadReqId');
            const errorDiv = document.getElementById('uploadError');
            const fileInput = document.getElementById('uploadFile');
            const fileList = document.getElementById('fileList');
            reqIdInput.value = reqId;
            errorDiv.classList.add('d-none');
            fileInput.value = '';
            fileList.innerHTML = '';
            // Clear stored files
            window.selectedFiles = [];
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        };

        // Store selected files
        window.selectedFiles = [];

        // Drag and drop functionality
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('uploadFile');
        const fileList = document.getElementById('fileList');

        // Click to select files
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        // Handle file selection
        fileInput.addEventListener('change', (e) => {
            addFiles(e.target.files);
        });

        // Drag events
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            addFiles(files);
        });

        // Add files to selection
        function addFiles(files) {
            Array.from(files).forEach(file => {
                // Check for duplicates
                if (!window.selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                    window.selectedFiles.push(file);
                }
            });
            displayFiles();
        }

        // Remove file from selection
        window.removeFile = function(index) {
            window.selectedFiles.splice(index, 1);
            displayFiles();
        }

        // Display selected files
        function displayFiles() {
            fileList.innerHTML = '';
            if (window.selectedFiles.length > 0) {
                const list = document.createElement('ul');
                list.className = 'list-group';
                window.selectedFiles.forEach((file, index) => {
                    const item = document.createElement('li');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center';
                    item.innerHTML = `
                        <span><i class="bi bi-file-earmark me-2"></i>${file.name}</span>
                        <div>
                            <span class="badge bg-secondary me-2">${formatFileSize(file.size)}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `;
                    list.appendChild(item);
                });
                fileList.appendChild(list);
            }
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Submit upload form
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const reqId = document.getElementById('uploadReqId').value;
            const errorDiv = document.getElementById('uploadError');
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            errorDiv.classList.add('d-none');

            if (!window.selectedFiles || window.selectedFiles.length === 0) {
                errorDiv.textContent = 'Please select files to upload';
                errorDiv.classList.remove('d-none');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';

            // Upload all files in parallel
            const uploadPromises = window.selectedFiles.map(file => {
                const formData = new FormData();
                formData.append('action', 'upload_attachment');
                formData.append('requisition_id', reqId);
                formData.append('file', file);
                formData.append('original_name', file.name);
                formData.append('file_size', file.size);
                formData.append('mime_type', file.type);

                return fetch('upload_attachment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const responseText = await response.text();
                    console.log('Upload attachment response for', file.name, ':', responseText);
                    if (!responseText) {
                        throw new Error('Empty response from server');
                    }
                    return JSON.parse(responseText);
                })
                .then(result => ({ success: result.success, file: file.name }))
                .catch(error => {
                    console.error('Error uploading file:', error);
                    return { success: false, file: file.name };
                });
            });

            const results = await Promise.all(uploadPromises);

            const successCount = results.filter(r => r.success).length;
            const failCount = results.filter(r => !r.success).length;

            if (successCount > 0 && failCount === 0) {
                // All files uploaded successfully
                const modal = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));
                modal.hide();
                location.reload();
            } else if (successCount > 0) {
                // Some files uploaded
                errorDiv.textContent = `${successCount} file(s) uploaded successfully, ${failCount} failed`;
                errorDiv.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            } else {
                // All files failed
                errorDiv.textContent = 'Failed to upload files';
                errorDiv.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        });

        // Submit approval with password
        document.getElementById('approveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const password = document.getElementById('approvePassword').value;
            const reqId = document.getElementById('approveReqId').value;
            const errorDiv = document.getElementById('approveError');
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            errorDiv.classList.add('d-none');

            if (!password) {
                errorDiv.textContent = 'Please enter your password';
                errorDiv.classList.remove('d-none');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Approving...';

            const formData = new FormData();
            formData.append('action', 'approve');
            formData.append('req_id', reqId);
            formData.append('password', password);

            fetch('requisition.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(async response => {
                const responseText = await response.text();
                console.log('Approve requisition response:', responseText);
                if (!responseText) {
                    throw new Error('Empty response from server');
                }
                return JSON.parse(responseText);
            })
            .then(data => {
                if (data.success) {
                    // Success - reload page
                    location.reload();
                } else {
                    // Show error
                    errorDiv.textContent = data.message;
                    errorDiv.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.textContent = 'Failed to approve requisition';
                errorDiv.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            });
        });

        // Submit reject form
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const reqId = document.getElementById('rejectReqId').value;
            const errorDiv = document.getElementById('rejectError');
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            errorDiv.classList.add('d-none');

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Rejecting...';

            const formData = new FormData();
            formData.append('action', 'reject');
            formData.append('req_id', reqId);

            fetch('requisition.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(async response => {
                const responseText = await response.text();
                console.log('Reject requisition response:', responseText);
                if (!responseText) {
                    throw new Error('Empty response from server');
                }
                return JSON.parse(responseText);
            })
            .then(data => {
                if (data.success) {
                    // Success - reload page
                    location.reload();
                } else {
                    // Show error
                    errorDiv.textContent = data.message;
                    errorDiv.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.textContent = 'Failed to reject requisition';
                errorDiv.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            });
        });
    </script>
</body>
</html>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Requisition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="approveError" class="alert alert-danger d-none mb-3"></div>
                <form id="approveForm">
                    <input type="hidden" id="approveReqId" name="req_id">
                    <div class="mb-3">
                        <label for="approvePassword" class="form-label">Enter your password to approve</label>
                        <input type="password" class="form-control" id="approvePassword" name="password" required>
                    </div>
                    <div class="modal-footer px-0 pb-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Requisition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="rejectError" class="alert alert-danger d-none mb-3"></div>
                <p>Are you sure you want to reject this requisition? This action cannot be undone.</p>
                <form id="rejectForm">
                    <input type="hidden" id="rejectReqId" name="req_id">
                    <div class="modal-footer px-0 pb-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Attachments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="uploadError" class="alert alert-danger d-none mb-3"></div>
                <form id="uploadForm">
                    <input type="hidden" id="uploadReqId" name="req_id">
                    <div class="mb-3">
                        <div id="dropZone" class="border border-2 border-dashed rounded p-4 text-center" style="cursor: pointer; transition: all 0.3s;">
                            <i class="bi bi-cloud-upload fs-1 text-muted"></i>
                            <p class="mb-2 text-muted">Drag & drop files here or click to select</p>
                            <input type="file" class="form-control d-none" id="uploadFile" name="file" multiple>
                        </div>
                        <div id="fileList" class="mt-3"></div>
                    </div>
                    <div class="modal-footer px-0 pb-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    #dropZone.dragover {
        border-color: #0d6efd;
        background-color: #f8f9fa;
    }
</style>
