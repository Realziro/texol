<?php
session_start();

// Load env/config (Supabase keys, etc.)
require_once __DIR__ . '/../config.php';

// Protect page: only allow access when logged in
if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Restrict access to users with customer_feedback permission or Call Center Agent role
if (!check_permission('customer_feedback', 'view') && (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'call center agent')) {
    header('Location:   dashboard');
    exit;
}

// Handle email notification action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_feedback_email') {
    header('Content-Type: application/json');

    $cfid = $_POST['cfid'] ?? '';
    $clientName = $_POST['client_name'] ?? '';
    $station = $_POST['station'] ?? '';
    $sharedEmails = $_POST['shared_emails'] ?? '';

    if (empty($sharedEmails)) {
        echo json_encode(['success' => false, 'message' => 'No recipients provided.']);
        exit;
    }

    // Send email using PHPMailer
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'mail.texolenergies.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'support@texolenergies.com';
        $mail->Password = 'realziro@1997';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('support@texolenergies.com', 'THI Support');
        $emailArray = explode(',', $sharedEmails);
        foreach ($emailArray as $email) {
            $mail->addAddress(trim($email));
        }

        $mail->isHTML(true);
        $mail->Subject = 'New Customer Feedback: ' . $cfid;

        $mailBody = "
        <div style='font-family: Arial, sans-serif; background:#f4f6f9; padding:20px;'>
            <img src='https://texolenergies.com/assets/Logo-paGHQfRF.svg' alt='Texol Energies' style='width:140px; margin:0 auto 15px; display:block;' />
            <div style='max-width:650px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);'>
                <div style='background:#1f3c88; color:#ffffff; padding:25px; text-align:center;'>
                    <h2 style='margin:0;'>New Customer Feedback</h2>
                </div>
                <div style='padding:25px;'>
                    <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                        <strong>Feedback ID:</strong> $cfid
                    </p>
                    <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                        <strong>Client Name:</strong> $clientName
                    </p>
                    <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:10px;'>
                        <strong>Station:</strong> $station
                    </p>
                    <div style='margin-bottom:20px;'>
                        <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#e8f0ff; color:#1f3c88; margin:3px;'>
                            Status: New
                        </span>
                        <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#f0f0f0; color:#555; margin:3px;'>
                            Station: $station
                        </span>
                    </div>
                    <p style='font-size:14px; color:#555; line-height:1.6; margin-bottom:20px;'>
                        <a href='https://support.texolenergies.com/customer_feedback' style='color:#1f3c88; text-decoration:none; font-weight:bold;'>View Feedback</a>
                    </p>
                    <div style='margin-top:25px; text-align:center;'>
                        <span style='display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; background:#1f3c88; color:#fff; margin:3px;'>
                            Customer Feedback Notification
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
        </div>";

        $mail->Body = $mailBody;
        $mail->send();

        echo json_encode(['success' => true, 'message' => 'Email sent successfully.']);
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $mail->ErrorInfo);
        echo json_encode(['success' => false, 'message' => 'Failed to send email.']);
    }
    exit;
}

$isCallCenterAgent = isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'call center agent';
$hasFeedbackPermission = check_permission('customer_feedback', 'view');

// Set active menu for sidebar
$activeMenu = 'customer_feedback';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Work Card System - Customer Feedback</title>

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
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">

    <!-- Reuse layout styles -->
    <link rel="stylesheet" href="sidebar.css" />

    <style>
        .signature-canvas {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            background-color: #f8f9fa;
            cursor: crosshair;
        }
        .signature-canvas:hover {
            border-color: #0d6efd;
        }
        .section-title {
            background-color: #1f3c88;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content flex-grow-1 d-flex flex-column">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
            <!-- Sidebar toggle (hamburger) on small screens -->
            <button
                class="btn btn-outline-secondary d-lg-none me-2"
                type="button"
                id="sidebarToggle"
                aria-label="Toggle sidebar"
            >
                <i class="bi bi-list"></i>
            </button>

            <a class="navbar-brand fw-semibold d-none d-sm-inline d-flex align-items-center gap-2" href="#">
                <span id="pageTitle">Customer Feedback</span>
            </a>

            <div class="ms-auto d-flex align-items-center gap-3">
                <?php include __DIR__ . '/partials/notifications.php'; ?>
                <?php include __DIR__ . '/partials/navbar_user.php'; ?>
            </div>
        </nav>
        <!-- /Top Navbar -->

        <!-- Main Content Area -->
        <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <ul class="nav nav-tabs card-header-tabs" id="feedbackTabs" role="tablist">
                                    <?php if ($isCallCenterAgent) : ?>
                                    <li class="nav-item">
                                        <button class="nav-link active" id="form-tab" data-bs-toggle="tab" data-bs-target="#form-tab-pane" type="button" role="tab">
                                            <i class="bi bi-plus-circle me-2"></i>New Feedback
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($hasFeedbackPermission) : ?>
                                    <li class="nav-item">
                                        <button class="nav-link <?php echo !$isCallCenterAgent ? 'active' : ''; ?>" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-tab-pane" type="button" role="tab">
                                            <i class="bi bi-clock-history me-2"></i>History
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($hasFeedbackPermission && !$isCallCenterAgent) : ?>
                                    <li class="nav-item d-none" id="edit-tab-wrapper">
                                        <button class="nav-link" id="edit-form-tab" data-bs-toggle="tab" data-bs-target="#form-tab-pane" type="button" role="tab">
                                            <i class="bi bi-pencil me-2"></i>Edit Feedback
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="feedbackTabsContent">
                                    <!-- Form Tab -->
                                    <div class="tab-pane fade <?php echo $isCallCenterAgent ? 'show active' : ''; ?>" id="form-tab-pane" role="tabpanel">
                                        <!-- Alert -->
                                        <div id="feedbackAlert" class="alert d-none mb-3"></div>

                                        <form id="customerFeedbackForm">
                                    <!-- Client Information -->
                                    <div class="section-title">
                                        <i class="bi bi-person me-2"></i>Client Information
                                    </div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Client Name *</label>
                                            <input type="text" class="form-control" id="clientName" required placeholder="Enter client name">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Phone Number *</label>
                                            <input type="tel" class="form-control" id="phoneNumber" required placeholder="Enter phone number">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Station *</label>
                                            <input type="text" class="form-control" id="station" required list="stationList" placeholder="Type to search station...">
                                            <datalist id="stationList">
                                                <?php
                                                // Fetch branches from database
                                                try {
                                                    $supabaseUrl = getenv('SUPABASE_URL') ?: $_ENV['SUPABASE_URL'] ?? 'https://your-project.supabase.co';
                                                    $supabaseKey = getenv('SUPABASE_ANON_KEY') ?: $_ENV['SUPABASE_ANON_KEY'] ?? 'your-anon-key';
                                                    
                                                    $ch = curl_init();
                                                    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . '/rest/v1/branches?select=id,name&order=name');
                                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                                        'apikey: ' . $supabaseKey,
                                                        'Authorization: Bearer ' . $supabaseKey
                                                    ]);
                                                    $response = curl_exec($ch);
                                                    curl_close($ch);
                                                    
                                                    $branches = json_decode($response, true);
                                                    if ($branches) {
                                                        foreach ($branches as $branch) {
                                                            echo '<option value="' . htmlspecialchars($branch['name']) . '">' . htmlspecialchars($branch['name']) . '</option>';
                                                        }
                                                    }
                                                } catch (Exception $e) {
                                                    echo '<option value="">Error loading stations</option>';
                                                }
                                                ?>
                                            </datalist>
                                        </div>
                                    </div>

                                    <!-- Complaint/Feedback Details -->
                                    <div class="section-title">
                                        <i class="bi bi-chat-left-text me-2"></i>Complaint/Feedback Details
                                    </div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Complaint or Feedback Details *</label>
                                            <small class="text-muted d-block mb-2">(Please describe the issue or feedback in detail)</small>
                                            <textarea class="form-control" id="feedbackDetails" rows="5" required placeholder="Enter complaint or feedback details..."></textarea>
                                        </div>
                                    </div>

                                    <!-- Incident Information -->
                                    <div class="section-title">
                                        <i class="bi bi-exclamation-triangle me-2"></i>Incident Information
                                    </div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Date of Incident</label>
                                            <input type="date" class="form-control" id="incidentDate">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Time of Incident</label>
                                            <input type="time" class="form-control" id="incidentTime">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Attendant Name</label>
                                            <input type="text" class="form-control" id="attendantName" placeholder="Enter attendant name">
                                        </div>
                                    </div>

                                    <!-- Call Center Interaction Information -->
                                    <div class="section-title">
                                        <i class="bi bi-telephone me-2"></i>Call Center Interaction Information
                                    </div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Date of Call</label>
                                            <input type="date" class="form-control" id="callDate">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Time of Call</label>
                                            <input type="time" class="form-control" id="callTime">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Agent Name</label>
                                            <input type="text" class="form-control" id="agentName" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? ''); ?>" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Agent Signature</label>
                                            <button type="button" class="btn btn-outline-primary w-100" id="signAgentBtn" data-bs-toggle="modal" data-bs-target="#signatureModal">
                                                <i class="bi bi-pen me-1"></i>Sign
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row g-3 mb-4" id="signaturePreviewRow" style="display: none;">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Signature Preview</label>
                                            <img id="signaturePreview" class="border rounded" style="max-height: 200px; background: white;" alt="Signature">
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="removeSignatureBtn">
                                                <i class="bi bi-x-circle me-1"></i>Remove
                                            </button>
                                            <input type="hidden" id="agentSignatureData" />
                                        </div>
                                    </div>

                                    <!-- Resolution Provided -->
                                    <div class="section-title">
                                        <i class="bi bi-check-circle me-2"></i>Resolution Provided
                                    </div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Resolution Details</label>
                                            <small class="text-muted d-block mb-2">(Please describe how the complaint or feedback was resolved or any corrective/preventive actions taken for non-conformance resolution)</small>
                                            <textarea class="form-control" id="resolutionDetails" rows="4" placeholder="Enter resolution details..."></textarea>
                                        </div>
                                    </div>

                                    <!-- Additional Information -->
                                    <div class="section-title">
                                        <i class="bi bi-info-circle me-2"></i>Additional Information
                                    </div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Additional Notes</label>
                                            <small class="text-muted d-block mb-2">(For any further details or suggestions that may contribute to the continual improvement of service)</small>
                                            <textarea class="form-control" id="additionalNotes" rows="3" placeholder="Enter any additional information..."></textarea>
                                        </div>
                                    </div>

                                    <!-- Share/Forward -->
                                    <div class="section-title">
                                        <i class="bi bi-share me-2"></i>Share / Forward
                                    </div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Share With</label>
                                            <small class="text-muted d-block mb-2">(Type name or email to search users, click to select)</small>
                                            <input type="text" class="form-control" id="sharedWithInput" placeholder="Search users by name or email..." autocomplete="off">
                                            <div id="sharedWithDropdown" class="dropdown-menu w-100" style="position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                                            <div id="selectedUsers" class="mt-2"></div>
                                            <input type="hidden" id="sharedWith" value="">
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="row">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary" id="submitFeedbackBtn">
                                                <i class="bi bi-send me-1"></i>Submit Feedback
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary ms-2" id="resetFeedbackBtn">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                    </div>
                                    <!-- History Tab -->
                                    <div class="tab-pane fade <?php echo !$isCallCenterAgent && $hasFeedbackPermission ? 'show active' : ''; ?>" id="history-tab-pane" role="tabpanel">
                                        <div class="mb-3">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" id="feedbackSearchInput" placeholder="Search by client name, station, or cfid...">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle" id="feedbackHistoryTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>CFID</th>
                                                        <th>Date</th>
                                                        <th>Client Name</th>
                                                        <th>Station</th>
                                                        <th>Feedback Summary</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="feedbackHistoryBody">
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted">Loading...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <nav>
                                            <ul class="pagination justify-content-center" id="feedbackPagination">
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Signature Modal -->
    <div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="signatureModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="signatureModalLabel">
                        <i class="bi bi-pen me-2"></i>Agent Signature
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Draw your signature below:</label>
                        <canvas id="agentSignatureCanvas" class="signature-canvas w-100" style="background: white; border: 2px dashed #dee2e6; border-radius: 8px;" height="100"></canvas>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="clearSignatureBtn">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveSignatureBtn">
                        <i class="bi bi-check me-1"></i>Save Signature
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>

    <!-- Supabase JS -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>

    <script>
        // Initialize Supabase
        const supabaseUrl = '<?php echo defined('SUPABASE_URL') ? SUPABASE_URL : ''; ?>';
        const supabaseKey = '<?php echo defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : ''; ?>';
        const supabaseClient = supabaseUrl && supabaseKey ? supabase.createClient(supabaseUrl, supabaseKey) : null;

        // Current user
        const currentUserEmail = '<?php echo $_SESSION['user_email'] ?? ''; ?>';
        const currentUserId = '<?php echo $_SESSION['user_id'] ?? ''; ?>';
        const hasFeedbackPermission = <?php echo $hasFeedbackPermission ? 'true' : 'false'; ?>;
        const isCallCenterAgent = <?php echo $isCallCenterAgent ? 'true' : 'false'; ?>;
        let isSharedUserEdit = false;

        document.addEventListener('DOMContentLoaded', function() {

            // Load users for shared_with multi-select
            let allUsers = [];
            let selectedUsers = [];

            async function loadUsers() {
                try {
                    const { data, error } = await supabaseClient
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

            // Searchable user selection
            const sharedWithInput = document.getElementById('sharedWithInput');
            const sharedWithDropdown = document.getElementById('sharedWithDropdown');
            const selectedUsersDiv = document.getElementById('selectedUsers');
            const sharedWithHidden = document.getElementById('sharedWith');

            sharedWithInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                sharedWithDropdown.innerHTML = '';

                if (searchTerm.length < 2) {
                    sharedWithDropdown.classList.remove('show');
                    return;
                }

                const filteredUsers = allUsers.filter(user => {
                    const fullName = (user.full_name || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    return fullName.includes(searchTerm) || email.includes(searchTerm);
                });

                if (filteredUsers.length === 0) {
                    const item = document.createElement('div');
                    item.className = 'dropdown-item';
                    item.textContent = 'No users found';
                    item.style.cursor = 'default';
                    sharedWithDropdown.appendChild(item);
                } else {
                    filteredUsers.forEach(user => {
                        const item = document.createElement('div');
                        item.className = 'dropdown-item';
                        item.style.cursor = 'pointer';
                        item.textContent = `${user.full_name || user.email} (${user.email})`;
                        item.addEventListener('click', function() {
                            if (!selectedUsers.find(u => u.id === user.id)) {
                                selectedUsers.push(user);
                                updateSelectedUsersDisplay();
                            }
                            sharedWithInput.value = '';
                            sharedWithDropdown.classList.remove('show');
                        });
                        sharedWithDropdown.appendChild(item);
                    });
                }

                sharedWithDropdown.classList.add('show');
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!sharedWithInput.contains(e.target) && !sharedWithDropdown.contains(e.target)) {
                    sharedWithDropdown.classList.remove('show');
                }
            });

            function updateSelectedUsersDisplay() {
                selectedUsersDiv.innerHTML = selectedUsers.map(user => `
                    <span class="badge bg-primary me-1 mb-1">
                        ${user.full_name || user.email}
                        <span class="ms-1" style="cursor:pointer;" onclick="removeUser('${user.id}')">&times;</span>
                    </span>
                `).join('');

                sharedWithHidden.value = selectedUsers.map(u => u.id).join(',');
            }

            window.removeUser = function(userId) {
                selectedUsers = selectedUsers.filter(u => u.id !== userId);
                updateSelectedUsersDisplay();
            };

            // Signature canvas
            const canvas = document.getElementById('agentSignatureCanvas');
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            console.log('🔍 [DEBUG] Canvas element:', canvas);
            console.log('🔍 [DEBUG] Canvas context:', ctx);

            // Resize canvas to match display size
            function resizeCanvas() {
                if (!canvas) return;
                const rect = canvas.getBoundingClientRect();
                // Use the canvas attribute height if it's larger than the rect height
                // This prevents the canvas from being resized to tiny values when modal is hidden
                const targetHeight = Math.max(canvas.getAttribute('height') || 400, rect.height);
                canvas.width = rect.width;
                canvas.height = targetHeight;
                console.log('[DEBUG] Canvas resized to:', { width: canvas.width, height: canvas.height });
            }
            // Don't resize on page load - only when modal is shown
            window.addEventListener('resize', resizeCanvas);

            // Drawing functions
            if (canvas) {
                canvas.addEventListener('mousedown', startDrawing);
                canvas.addEventListener('mousemove', draw);
                canvas.addEventListener('mouseup', stopDrawing);
                canvas.addEventListener('mouseout', stopDrawing);

                // Touch support
                canvas.addEventListener('touchstart', handleTouchStart);
                canvas.addEventListener('touchmove', handleTouchMove);
                canvas.addEventListener('touchend', stopDrawing);
                console.log('[DEBUG] Canvas event listeners attached');
            } else {
                console.error(' [DEBUG] Canvas element not found!');
            }

            function startDrawing(e) {
                console.log(' [DEBUG] Start drawing at:', { x: e.offsetX, y: e.offsetY });
                isDrawing = true;
                [lastX, lastY] = [e.offsetX, e.offsetY];
            }

            function draw(e) {
                if (!isDrawing) return;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(e.offsetX, e.offsetY);
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.stroke();
                [lastX, lastY] = [e.offsetX, e.offsetY];
            }

            function stopDrawing() {
                if (isDrawing) {
                    console.log('🛑 [DEBUG] Stop drawing');
                }
                isDrawing = false;
            }

            function handleTouchStart(e) {
                e.preventDefault();
                const touch = e.touches[0];
                const rect = canvas.getBoundingClientRect();
                lastX = touch.clientX - rect.left;
                lastY = touch.clientY - rect.top;
                isDrawing = true;
            }

            function handleTouchMove(e) {
                e.preventDefault();
                if (!isDrawing) return;
                const touch = e.touches[0];
                const rect = canvas.getBoundingClientRect();
                const x = touch.clientX - rect.left;
                const y = touch.clientY - rect.top;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.stroke();
                [lastX, lastY] = [x, y];
            }

            // Initialize signature modal
            const signatureModal = document.getElementById('signatureModal');
            signatureModal.addEventListener('shown.bs.modal', function() {
                setTimeout(() => {
                    resizeCanvas();
                }, 100);
            });

            // Clear signature
            document.getElementById('clearSignatureBtn').addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                console.log('🗑️ [DEBUG] Signature cleared');
            });

            // Save signature
            document.getElementById('saveSignatureBtn').addEventListener('click', function() {
                const signatureData = canvas.toDataURL('image/png');
                console.log('💾 [DEBUG] Signature saved:', signatureData ? 'Yes (length: ' + signatureData.length + ')' : 'No');
                
                // Save to hidden input
                document.getElementById('agentSignatureData').value = signatureData;
                
                // Show preview
                const preview = document.getElementById('signaturePreview');
                preview.src = signatureData;
                document.getElementById('signaturePreviewRow').style.display = 'block';
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(signatureModal);
                modal.hide();
            });

            // Remove signature
            document.getElementById('removeSignatureBtn').addEventListener('click', function() {
                document.getElementById('agentSignatureData').value = '';
                document.getElementById('signaturePreviewRow').style.display = 'none';
                document.getElementById('signaturePreview').src = '';
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                console.log('🗑️ [DEBUG] Signature removed');
            });

            // Load feedback history when history tab is clicked
            const historyTab = document.getElementById('history-tab');
            if (historyTab) {
                historyTab.addEventListener('click', function() {
                    loadFeedbackHistory();
                });

                // Auto-load history if tab is active on page load (for users with permission)
                if (historyTab.classList.contains('active')) {
                    loadFeedbackHistory();
                }
            }

            // Pagination and search variables
            let currentPage = 1;
            const itemsPerPage = 10;
            let allFeedbackData = [];

            // Load feedback history function
            async function loadFeedbackHistory() {
                const tbody = document.getElementById('feedbackHistoryBody');
                if (!tbody) {
                    console.error('feedbackHistoryBody element not found');
                    return;
                }
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>';

                try {
                    console.log('Loading feedback history...');
                    // Show all feedback without filtering
                    const { data, error } = await supabaseClient
                        .from('customer_feedback')
                        .select('*')
                        .order('created_at', { ascending: false });

                    console.log('Feedback data:', data);
                    console.log('Feedback error:', error);

                    if (error) throw error;

                    allFeedbackData = data || [];
                    console.log('Total feedback records:', allFeedbackData.length);
                    currentPage = 1;
                    renderFeedbackHistory();
                } catch (err) {
                    console.error('Error loading feedback history:', err);
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load feedback history: ' + err.message + '</td></tr>';
                }
            }

            // Render feedback history with pagination and search
            function renderFeedbackHistory() {
                const tbody = document.getElementById('feedbackHistoryBody');
                const searchTerm = document.getElementById('feedbackSearchInput').value.toLowerCase();

                // Filter data based on search term
                const filteredData = allFeedbackData.filter(feedback => {
                    const clientName = (feedback.client_name || '').toLowerCase();
                    const station = (feedback.station || '').toLowerCase();
                    const cfid = (feedback.cfid || '').toLowerCase();
                    return clientName.includes(searchTerm) || station.includes(searchTerm) || cfid.includes(searchTerm);
                });

                // Calculate pagination
                const totalPages = Math.ceil(filteredData.length / itemsPerPage);
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                const pageData = filteredData.slice(startIndex, endIndex);

                if (!pageData || pageData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No feedback records found</td></tr>';
                    document.getElementById('feedbackPagination').innerHTML = '';
                    return;
                }

                tbody.innerHTML = pageData.map(feedback => {
                    // Check if user can edit (call center agent OR shared with this feedback)
                    const formTabElement = document.getElementById('form-tab');
                    let canEdit = !!formTabElement;

                    if (!canEdit && feedback.shared_with) {
                        console.log('Feedback shared_with:', feedback.shared_with, 'Type:', typeof feedback.shared_with);
                        console.log('Current user ID:', currentUserId);
                        if (Array.isArray(feedback.shared_with)) {
                            canEdit = feedback.shared_with.includes(currentUserId);
                        } else if (typeof feedback.shared_with === 'string') {
                            canEdit = feedback.shared_with.split(',').map(id => id.trim()).includes(currentUserId);
                        }
                        console.log('Can edit:', canEdit);
                    }

                    return `
                        <tr>
                            <td><strong>${feedback.cfid || '-'}</strong></td>
                            <td>${new Date(feedback.created_at).toLocaleDateString()}</td>
                            <td>${feedback.client_name}</td>
                            <td>${feedback.station}</td>
                            <td>${feedback.feedback_details.substring(0, 50)}${feedback.feedback_details.length > 50 ? '...' : ''}</td>
                            <td>${feedback.status === 'resolved' ? '<span class="badge bg-success">Resolved</span>' : '<span class="badge bg-warning">New</span>'}</td>
                            <td>
                                ${canEdit ? `
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editFeedback(${feedback.id})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                ` : ''}
                                <button class="btn btn-sm btn-outline-danger me-1" onclick="deleteFeedback(${feedback.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="printFeedback(${feedback.id})">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');

                // Render pagination
                renderPagination(totalPages);
            }

            // Render pagination controls
            function renderPagination(totalPages) {
                const pagination = document.getElementById('feedbackPagination');
                if (totalPages <= 1) {
                    pagination.innerHTML = '';
                    return;
                }

                let html = '';
                for (let i = 1; i <= totalPages; i++) {
                    html += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                        </li>
                    `;
                }
                pagination.innerHTML = html;
            }

            // Change page
            window.changePage = function(page) {
                currentPage = page;
                renderFeedbackHistory();
            };

            // Search input event listener
            document.getElementById('feedbackSearchInput').addEventListener('input', function() {
                currentPage = 1;
                renderFeedbackHistory();
            });

            // Function to set fields readonly for shared users
            function setFieldsForSharedUser(isShared) {
                isSharedUserEdit = isShared;
                const fieldsToMakeReadonly = [
                    'clientName', 'phoneNumber', 'station', 'feedbackDetails',
                    'incidentDate', 'incidentTime', 'attendantName',
                    'callDate', 'callTime', 'agentName'
                ];

                fieldsToMakeReadonly.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.readOnly = isShared;
                        if (isShared) {
                            field.classList.add('bg-light');
                        } else {
                            field.classList.remove('bg-light');
                        }
                    }
                });

                // Handle signature button
                const signBtn = document.getElementById('signAgentBtn');
                if (signBtn) {
                    signBtn.disabled = isShared;
                }

                // Handle Share/Forward section
                const sharedWithInput = document.getElementById('sharedWithInput');
                const selectedUsersDiv = document.getElementById('selectedUsers');
                if (sharedWithInput) {
                    sharedWithInput.readOnly = isShared;
                    if (isShared) {
                        sharedWithInput.classList.add('bg-light');
                    } else {
                        sharedWithInput.classList.remove('bg-light');
                    }
                }
                if (selectedUsersDiv) {
                    selectedUsersDiv.style.pointerEvents = isShared ? 'none' : 'auto';
                }
            }

            // Edit feedback function
            window.editFeedback = async function(id) {
                try {
                    const { data, error } = await supabaseClient
                        .from('customer_feedback')
                        .select('*')
                        .eq('id', id)
                        .single();

                    if (error) throw error;

                    // Check if user can edit (call center agent OR shared with this feedback)
                    const formTabElement = document.getElementById('form-tab');
                    const editTabWrapper = document.getElementById('edit-tab-wrapper');
                    const editFormTab = document.getElementById('edit-form-tab');

                    // Handle shared_with as either array or comma-separated string
                    let isSharedWithUser = false;
                    if (data.shared_with) {
                        if (Array.isArray(data.shared_with)) {
                            isSharedWithUser = data.shared_with.includes(currentUserId);
                        } else if (typeof data.shared_with === 'string') {
                            isSharedWithUser = data.shared_with.split(',').map(id => id.trim()).includes(currentUserId);
                        }
                    }

                    if (!formTabElement && !isSharedWithUser) {
                        alert('You do not have permission to edit feedback.');
                        return;
                    }

                    // If form tab doesn't exist but user is shared with feedback, show the edit tab
                    if (!formTabElement && isSharedWithUser && editTabWrapper) {
                        editTabWrapper.classList.remove('d-none');
                    }

                    // Set fields readonly for shared users
                    setFieldsForSharedUser(isSharedWithUser && !isCallCenterAgent);

                    // Populate form with existing data
                    document.getElementById('clientName').value = data.client_name;
                    document.getElementById('phoneNumber').value = data.phone_number;
                    document.getElementById('station').value = data.station;
                    document.getElementById('feedbackDetails').value = data.feedback_details;
                    document.getElementById('incidentDate').value = data.incident_date || '';
                    document.getElementById('incidentTime').value = data.incident_time || '';
                    document.getElementById('attendantName').value = data.attendant_name || '';
                    document.getElementById('callDate').value = data.call_date || '';
                    document.getElementById('callTime').value = data.call_time || '';
                    document.getElementById('agentName').value = data.agent_name || '';
                    document.getElementById('resolutionDetails').value = data.resolution_details || '';
                    document.getElementById('additionalNotes').value = data.additional_notes || '';

                    // Populate shared_with
                    selectedUsers = [];
                    if (data.shared_with) {
                        const sharedIds = typeof data.shared_with === 'string' ? data.shared_with.split(',') : data.shared_with;
                        sharedIds.forEach(id => {
                            const user = allUsers.find(u => u.id === id.trim());
                            if (user) {
                                selectedUsers.push(user);
                            }
                        });
                        updateSelectedUsersDisplay();
                    }

                    // Show signature if exists
                    if (data.agent_signature) {
                        document.getElementById('agentSignatureData').value = data.agent_signature;
                        document.getElementById('signaturePreview').src = data.agent_signature;
                        document.getElementById('signaturePreviewRow').style.display = 'block';
                    }

                    // Store ID for update
                    document.getElementById('customerFeedbackForm').dataset.editId = id;

                    // Switch to form tab using appropriate button
                    const tabToUse = formTabElement || editFormTab;
                    if (tabToUse) {
                        const formTab = new bootstrap.Tab(tabToUse);
                        formTab.show();
                    }

                    // Change submit button text
                    document.getElementById('submitFeedbackBtn').innerHTML = '<i class="bi bi-check me-1"></i>Update Feedback';

                } catch (err) {
                    console.error('Error loading feedback for edit:', err);
                    alert('Failed to load feedback for editing');
                }
            };

            // Delete feedback function
            window.deleteFeedback = async function(id) {
                if (!confirm('Are you sure you want to delete this feedback record?')) return;

                try {
                    const { error } = await supabaseClient
                        .from('customer_feedback')
                        .delete()
                        .eq('id', id);

                    if (error) throw error;

                    loadFeedbackHistory();
                } catch (err) {
                    console.error('Error deleting feedback:', err);
                    alert('Failed to delete feedback');
                }
            };

            // Print feedback function
            window.printFeedback = async function(id) {
                try {
                    const { data, error } = await supabaseClient
                        .from('customer_feedback')
                        .select('*')
                        .eq('id', id)
                        .single();

                    if (error) throw error;

                    // Create print content matching sampleform.php structure with database data
                    const printContent = `
                        <html>
                        <head>
                            <title>Customer Feedback - ${data.client_name}</title>
                            <style>
                                :root{
                                    --brand-red:#FF0000;
                                    --line-color:#000;
                                    --font-main:'Century Gothic','Apple Gothic','Avenir Next','Segoe UI',sans-serif;
                                }
                                *{ box-sizing:border-box; }
                                body{
                                    font-family:var(--font-main);
                                    color:#000;
                                    font-size:11pt;
                                    background:#e9e9e9;
                                    margin:0;
                                    padding:24px 0;
                                }
                                .page{
                                    width:8.5in;
                                    min-height:11in;
                                    margin:0 auto 24px;
                                    background:#fff;
                                    padding:0.6in 0.75in 0.9in;
                                    box-shadow:0 0 8px rgba(0,0,0,0.25);
                                    position:relative;
                                }
                                table.doc-header{
                                    width:100%;
                                    border-collapse:collapse;
                                    margin-bottom:22px;
                                    table-layout:fixed;
                                }
                                table.doc-header td{
                                    border:1px solid #000;
                                    padding:4px 8px;
                                    vertical-align:middle;
                                    font-size:10pt;
                                }
                                table.doc-header .company-row td{
                                    text-align:center;
                                    font-weight:bold;
                                    font-size:10pt;
                                    padding:6px 8px;
                                }
                                table.doc-header .logo-cell{
                                    width:20%;
                                    text-align:center;
                                }
                                table.doc-header .logo-cell img{
                                    max-width:95px;
                                    height:auto;
                                    display:block;
                                    margin:0 auto;
                                }
                                table.doc-header .title-cell{
                                    width:50%;
                                    text-align:center;
                                    color:var(--brand-red);
                                    font-weight:bold;
                                    font-size:10pt;
                                }
                                table.doc-header .meta-cell{
                                    width:30%;
                                    text-align:center;
                                    font-weight:bold;
                                    font-size:9.5pt;
                                    line-height:1.5;
                                }
                                h1.form-title{
                                    text-align:center;
                                    font-weight:bold;
                                    font-size:16pt;
                                    margin:0 0 18px;
                                }
                                h2.section-heading{
                                    font-weight:bold;
                                    font-size:11pt;
                                    margin:16px 0 2px;
                                }
                                p.section-note{
                                    font-style:italic;
                                    margin:0 0 6px;
                                    font-size:11pt;
                                }
                                ul.field-list{
                                    list-style:none;
                                    margin:0 0 4px;
                                    padding:0;
                                }
                                ul.field-list li.field-row{
                                    display:flex;
                                    align-items:flex-end;
                                    margin:6px 0;
                                }
                                ul.field-list li.field-row::before{
                                    content:"\\2022";
                                    margin-right:8px;
                                    flex:0 0 auto;
                                    font-weight:bold;
                                }
                                .field-label{
                                    font-weight:bold;
                                    white-space:nowrap;
                                    margin-right:6px;
                                    flex:0 0 auto;
                                }
                                .field-blank{
                                    flex:1 1 auto;
                                    border-bottom:1px dotted #000;
                                    height:1em;
                                }
                                .field-value{
                                    flex:1 1 auto;
                                    border-bottom:1px dotted #000;
                                    height:1em;
                                    padding:0 4px;
                                }
                                .blank-line{
                                    border-bottom:1px dotted #000;
                                    height:1em;
                                    margin:10px 0 10px 22px;
                                }
                                .page-footer{
                                    position:absolute;
                                    bottom:0.4in;
                                    left:0.75in;
                                    right:0.75in;
                                    font-style:italic;
                                    font-size:9pt;
                                    border-top:1px solid #000;
                                    padding-top:4px;
                                }
                                @media print{
                                    body{ background:#fff; padding:0; }
                                    .page{
                                        box-shadow:none;
                                        margin:0;
                                        width:auto;
                                        min-height:auto;
                                        padding:0.6in 0.75in 0.9in;
                                    }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="page">
                                <table class="doc-header">
                                    <tr class="company-row">
                                        <td colspan="3">TEXOL ENERGIES LIMITED</td>
                                    </tr>
                                    <tr>
                                        <td class="logo-cell" rowspan="2">
                                            <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo">
                                            <br>
                                            <small><i>Reliability Redifined</i></small>
                                        </td>
                                        <td class="title-cell" rowspan="2">Customer Feedback Form</td>
                                        <td class="meta-cell">
                                            TEX-MAC-FRM-005, Ver 000<br>
                                            Issue Date: 1<sup>st</sup> Nov 2024
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="meta-cell">Page 1 of 1</td>
                                    </tr>
                                </table>

                                <div style="display:flex; justify-content:center; align-items:center; margin-bottom:18px; position:relative;">
                                    <div style="position:absolute; left:0; font-weight:bold; font-size:9px;">CFID: ${data.cfid || 'N/A'}</div>
                                    <h1 class="form-title" style="margin:0;">Customer Feedback Form</h1>
                                </div>

                                <h2 class="section-heading">Client's Information</h2>
                                <ul class="field-list">
                                    <li class="field-row"><span class="field-label">Client's Name:</span><span class="field-value">${data.client_name}</span></li>
                                    <li class="field-row"><span class="field-label">Phone Number:</span><span class="field-value">${data.phone_number}</span></li>
                                    <li class="field-row"><span class="field-label">Station:</span><span class="field-value">${data.station}</span></li>
                                </ul>

                                <h2 class="section-heading">Complaint or Feedback Details</h2>
                                <p class="section-note">(Please describe the issue or feedback in detail)</p>
                                <ul class="field-list">
                                    <li class="field-row"><span class="field-label">Complaint Details:</span><span class="field-value">${data.feedback_details}</span></li>
                                </ul>
                                <div class="blank-line"></div>
                                <div class="blank-line"></div>

                                <h2 class="section-heading">Incident Information</h2>
                                <ul class="field-list">
                                    <li class="field-row"><span class="field-label">Date of Incident:</span><span class="field-value">${data.incident_date || 'N/A'}</span></li>
                                    <li class="field-row"><span class="field-label">Time of Incident:</span><span class="field-value">${data.incident_time || 'N/A'}</span></li>
                                    <li class="field-row"><span class="field-label">Attendant Name/ID (if applicable):</span><span class="field-value">${data.attendant_name || 'N/A'}</span></li>
                                </ul>

                                <h2 class="section-heading">Call Center Interaction Information</h2>
                                <ul class="field-list">
                                    <li class="field-row"><span class="field-label">Date of Call:</span><span class="field-value">${data.call_date || 'N/A'}</span></li>
                                    <li class="field-row"><span class="field-label">Time of Call:</span><span class="field-value">${data.call_time || 'N/A'}</span></li>
                                    <li class="field-row"><span class="field-label">Agent's Name:</span><span class="field-value">${data.agent_name || 'N/A'}</span></li>
                                    <li class="field-row"><span class="field-label">Agent's Signature:</span>
                                        ${data.agent_signature ? `<span class=""><img src="${data.agent_signature}" style="max-height:50px;vertical-align:middle;" /></span>` : '<span class="field-value">No signature</span>'}
                                    </li>
                                </ul>

                                <h2 class="section-heading">Resolution (For Internal Use Only)</h2>
                                <p class="section-note">(Please describe how the complaint or feedback was resolved or any corrective/preventive actions taken for non-conformance resolution)</p>
                                <ul class="field-list">
                                    <li class="field-row"><span class="field-label">Resolution Provided:</span><span class="field-value">${data.resolution_details || 'N/A'}</span></li>
                                </ul>
                                <div class="blank-line"></div>

                                <h2 class="section-heading">Additional Comments (Optional)</h2>
                                <p class="section-note">(For any further details or suggestions that may contribute to the continual improvement of services)</p>
                                <ul class="field-list">
                                    <li class="field-row"><span class="field-label">Additional Comments:</span><span class="field-value">${data.additional_notes || 'N/A'}</span></li>
                                </ul>

                                <div class="page-footer">Texol Energies Ltd Customer Feedback Form</div>
                            </div>
                        </body>
                        </html>
                    `;

                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(printContent);
                    printWindow.document.close();
                    printWindow.print();

                } catch (err) {
                    console.error('Error printing feedback:', err);
                    alert('Failed to print feedback');
                }
            };

            // Form submission
            document.getElementById('customerFeedbackForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                hideAlert();

                const clientName = document.getElementById('clientName').value.trim();
                const phoneNumber = document.getElementById('phoneNumber').value.trim();
                const station = document.getElementById('station').value.trim();
                const feedbackDetails = document.getElementById('feedbackDetails').value.trim();
                const incidentDate = document.getElementById('incidentDate').value;
                const incidentTime = document.getElementById('incidentTime').value;
                const attendantName = document.getElementById('attendantName').value.trim();
                const callDate = document.getElementById('callDate').value;
                const callTime = document.getElementById('callTime').value;
                const agentName = document.getElementById('agentName').value.trim();
                const resolutionDetails = document.getElementById('resolutionDetails').value.trim();
                const additionalNotes = document.getElementById('additionalNotes').value.trim();

                // Get shared_with selected users (comma-separated IDs from hidden input)
                const sharedWith = document.getElementById('sharedWith').value;

                // Get signature from hidden input
                const signatureData = document.getElementById('agentSignatureData').value;
                console.log('🔍 [DEBUG] Signature data captured:', signatureData ? 'Yes (length: ' + signatureData.length + ')' : 'No');

                if (!clientName || !phoneNumber || !station || !feedbackDetails) {
                    showAlert('danger', 'Please fill in all required fields.');
                    return;
                }

                const submitBtn = document.getElementById('submitFeedbackBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Submitting...';

                try {
                    const editId = document.getElementById('customerFeedbackForm').dataset.editId;
                    let result;

                    if (editId) {
                        // Update existing record
                        const updateData = {
                            resolution_details: resolutionDetails,
                            additional_notes: additionalNotes || null
                        };

                        // Only call center agents can update all fields
                        if (!isSharedUserEdit) {
                            updateData.client_name = clientName;
                            updateData.phone_number = phoneNumber;
                            updateData.station = station;
                            updateData.feedback_details = feedbackDetails;
                            updateData.incident_date = incidentDate || null;
                            updateData.incident_time = incidentTime || null;
                            updateData.attendant_name = attendantName || null;
                            updateData.call_date = callDate || null;
                            updateData.call_time = callTime || null;
                            updateData.agent_name = agentName || null;
                            updateData.agent_signature = signatureData;
                            updateData.shared_with = sharedWith || null;
                            updateData.submitted_by = currentUserEmail;
                        } else {
                            // Shared user updating with resolution - change status to resolved
                            if (resolutionDetails && resolutionDetails.trim() !== '') {
                                updateData.status = 'resolved';
                            }
                        }

                        result = await supabaseClient
                            .from('customer_feedback')
                            .update(updateData)
                            .eq('id', editId)
                            .select();
                    } else {
                        // Generate cfid: YYYYMMDD + increment number
                        const today = new Date();
                        const dateStr = today.getFullYear() +
                            String(today.getMonth() + 1).padStart(2, '0') +
                            String(today.getDate()).padStart(2, '0');

                        // Get the highest cfid for today to determine next increment
                        const { data: lastCfidData } = await supabaseClient
                            .from('customer_feedback')
                            .select('cfid')
                            .like('cfid', `${dateStr}%`)
                            .order('cfid', { ascending: false })
                            .limit(1);

                        let increment = 1;
                        if (lastCfidData && lastCfidData.length > 0) {
                            const lastCfid = lastCfidData[0].cfid;
                            const lastIncrement = parseInt(lastCfid.slice(-3));
                            increment = lastIncrement + 1;
                        }

                        const cfid = dateStr + String(increment).padStart(3, '0');

                        // Insert new record
                        result = await supabaseClient
                            .from('customer_feedback')
                            .insert([{
                                cfid: cfid,
                                client_name: clientName,
                                phone_number: phoneNumber,
                                station: station,
                                feedback_details: feedbackDetails,
                                incident_date: incidentDate || null,
                                incident_time: incidentTime || null,
                                attendant_name: attendantName || null,
                                call_date: callDate || null,
                                call_time: callTime || null,
                                agent_name: agentName || null,
                                agent_signature: signatureData,
                                resolution_details: resolutionDetails,
                                additional_notes: additionalNotes || null,
                                shared_with: sharedWith || null,
                                submitted_by: currentUserEmail,
                                status: 'new',
                                created_at: new Date().toISOString()
                            }])
                            .select();
                    }

                    const { data, error } = result;
                    if (error) throw error;

                    // Send email notification to shared_with users
                    if (sharedWith && !editId) {
                        const sharedWithIds = sharedWith.split(',').map(id => id.trim());
                        if (sharedWithIds.length > 0) {
                            // Fetch email addresses of shared_with users
                            const { data: sharedWithUsers, error: usersError } = await supabaseClient
                                .from('users')
                                .select('email, full_name')
                                .in('id', sharedWithIds);

                            if (!usersError && sharedWithUsers && sharedWithUsers.length > 0) {
                                const sharedWithEmails = sharedWithUsers.map(u => u.email).join(',');

                                // Send email notification via PHP
                                const emailFormData = new FormData();
                                emailFormData.append('action', 'send_feedback_email');
                                emailFormData.append('cfid', cfid);
                                emailFormData.append('client_name', clientName);
                                emailFormData.append('station', station);
                                emailFormData.append('shared_emails', sharedWithEmails);

                                try {
                                    await fetch('customer_feedback.php', {
                                        method: 'POST',
                                        body: emailFormData
                                    });
                                } catch (emailErr) {
                                    console.error('Failed to send email notification:', emailErr);
                                }
                            }
                        }
                    }

                    showAlert('success', editId ? 'Feedback updated successfully!' : 'Feedback submitted successfully!');
                    document.getElementById('customerFeedbackForm').reset();
                    delete document.getElementById('customerFeedbackForm').dataset.editId;
                    document.getElementById('submitFeedbackBtn').innerHTML = '<i class="bi bi-send me-1"></i>Submit Feedback';
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    document.getElementById('agentSignatureData').value = '';
                    document.getElementById('signaturePreviewRow').style.display = 'none';
                    document.getElementById('signaturePreview').src = '';

                } catch (err) {
                    console.error('Error submitting feedback:', err);
                    showAlert('danger', 'Failed to submit feedback. Please try again.');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Feedback';
                }
            });

            // Reset form
            document.getElementById('resetFeedbackBtn').addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                document.getElementById('agentSignatureData').value = '';
                document.getElementById('signaturePreviewRow').style.display = 'none';
                setFieldsForSharedUser(false);
                delete document.getElementById('customerFeedbackForm').dataset.editId;
                document.getElementById('submitFeedbackBtn').innerHTML = '<i class="bi bi-send me-1"></i>Submit Feedback';
            });

            // Alert functions
            function showAlert(type, message) {
                const alert = document.getElementById('feedbackAlert');
                alert.className = `alert alert-${type} mb-3`;
                alert.textContent = message;
                alert.classList.remove('d-none');
            }

            function hideAlert() {
                const alert = document.getElementById('feedbackAlert');
                alert.classList.add('d-none');
            }

        // Sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    });
    </script>
</body>
</html>
