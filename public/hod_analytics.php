<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
require_once __DIR__ . '/../auth.php';

// Protect page: only allow access when logged in
if (!isset($_SESSION['user_email'])) {
    header('Location: login');
    exit;
}

// Restrict to HOD role only
if (!is_hod()) {
    header('Location: index');
    exit;
}

$analyticsData = [];
$errorMessage = '';
$department = trim($_SESSION['user_department'] ?? $_SESSION['department'] ?? '');
$userId = $_SESSION['user_id'] ?? '';

// Fetch analytics data from Supabase
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    try {
        // Fetch user's department from database to ensure accuracy
        $email = $_SESSION['user_email'] ?? '';
        if ($email) {
            $query = http_build_query(['select' => 'department', 'email' => 'eq.' . $email, 'limit' => 1]);
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
            $userData = json_decode($response, true) ?: [];
            curl_close($ch);
            if (!empty($userData[0]['department'])) {
                $department = trim($userData[0]['department']);
            }
        }

        // Fetch tickets data for the department
        $query = http_build_query([
            'select' => '*',
            'order' => 'created_at.desc'
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/tickets?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $allTickets = json_decode($response, true) ?: [];
        curl_close($ch);
        // Filter by department and re-index array
        $analyticsData['tickets'] = array_values(array_filter($allTickets, function($t) use ($department) {
            $ticketDept = trim($t['department'] ?? '');
            return strcasecmp($ticketDept, $department) === 0;
        }));

        // Fetch requisitions data for the department
        $query = http_build_query([
            'select' => '*',
            'order' => 'created_at.desc'
        ]);
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
        $allRequisitions = json_decode($response, true) ?: [];
        curl_close($ch);
        // Filter by department and re-index array
        $analyticsData['requisitions'] = array_values(array_filter($allRequisitions, function($r) use ($department) {
            $reqDept = trim($r['department'] ?? '');
            return strcasecmp($reqDept, $department) === 0;
        }));

        // Fetch incidents data for the department
        $query = http_build_query(['select' => '*', 'order' => 'created_at.desc']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/incidents?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $allIncidents = json_decode($response, true) ?: [];
        curl_close($ch);
        // Filter by department and re-index array
        $analyticsData['incidents'] = array_values(array_filter($allIncidents, function($i) use ($department) {
            return strcasecmp(trim($i['department'] ?? ''), $department) === 0;
        }));

        // Fetch job cards data for the department
        $query = http_build_query(['select' => '*', 'order' => 'created_at.desc']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/job_cards?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $allJobCards = json_decode($response, true) ?: [];
        curl_close($ch);
        // Filter by department - skip for now as we need owner info
        $analyticsData['job_cards'] = array_values($allJobCards);

        // Fetch repair maintenance forms for the department
        $query = http_build_query(['select' => '*', 'order' => 'form_date.desc,created_at.desc']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/repair_maintenance_forms?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $allRepairForms = json_decode($response, true) ?: [];
        curl_close($ch);
        // Filter by department and re-index array
        $analyticsData['repair_maintenance'] = array_values(array_filter($allRepairForms, function($r) use ($department) {
            return strcasecmp(trim($r['department'] ?? ''), $department) === 0;
        }));

        // Fetch CCTV checklists for the department
        $query = http_build_query(['select' => '*', 'order' => 'created_at.desc']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/cctv_checklist?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $allCCTV = json_decode($response, true) ?: [];
        curl_close($ch);
        // Filter by department and re-index array
        $analyticsData['cctv_checklist'] = array_values(array_filter($allCCTV, function($c) use ($department) {
            return strcasecmp(trim($c['department'] ?? ''), $department) === 0;
        }));

        // Fetch users in the department
        $query = http_build_query(['select' => 'id,full_name,role,department,created_at', 'order' => 'created_at.desc']);
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
        $allUsers = json_decode($response, true) ?: [];
        curl_close($ch);
        // Filter by department and re-index array
        $analyticsData['users'] = array_values(array_filter($allUsers, function($u) use ($department) {
            return strcasecmp(trim($u['department'] ?? ''), $department) === 0;
        }));

    } catch (Exception $e) {
        $errorMessage = 'Error fetching analytics data: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HOD Analytics - Work Card System</title>

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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Reuse layout styles -->
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">

    <style>
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .chart-container-lg {
            position: relative;
            height: 400px;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        .table thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .period-selector {
            max-width: 200px;
        }
        .priority-high { color: #dc3545; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #198754; }
    </style>
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'hod_analytics';
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
                    <span id="pageTitle">HOD Analytics</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <!-- Analytics Content -->
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h4 fw-semibold mb-1">Department Analytics Dashboard</h1>
                        <p class="text-muted small mb-0">
                            Overview for: <strong><?php echo htmlspecialchars($department); ?></strong>
                        </p>
                    </div>
                    <div class="period-selector">
                        <select class="form-select form-select-sm" id="periodSelector">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                </div>

                <!-- Overview Statistics Cards -->
                <div class="row g-3 g-lg-4 mb-4">
                    <!-- Tickets -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-ticket-detailed text-primary fs-4"></i>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">Tickets</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['tickets'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Total Tickets</p>
                                <div class="mt-2">
                                    <small class="text-warning">
                                        <i class="bi bi-clock"></i>
                                        <?php echo count(array_filter($analyticsData['tickets'] ?? [], fn($t) => strtolower($t['status'] ?? '') === 'open')); ?> Open
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Requisitions -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-file-earmark-plus text-success fs-4"></i>
                                    </div>
                                    <span class="badge bg-success rounded-pill">Requisitions</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['requisitions'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Total Requisitions</p>
                                <div class="mt-2">
                                    <small class="text-warning">
                                        <i class="bi bi-hourglass"></i>
                                        <?php echo count(array_filter($analyticsData['requisitions'] ?? [], fn($r) => strtolower($r['status'] ?? '') === 'pending')); ?> Pending Approval
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Incidents -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                                    </div>
                                    <span class="badge bg-danger rounded-pill">Incidents</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['incidents'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Total Incidents</p>
                                <div class="mt-2">
                                    <small class="text-warning">
                                        <i class="bi bi-clock"></i>
                                        <?php echo count(array_filter($analyticsData['incidents'] ?? [], fn($i) => strtolower($i['status'] ?? '') === 'pending')); ?> Pending
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Members -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-people text-info fs-4"></i>
                                    </div>
                                    <span class="badge bg-info rounded-pill">Team</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['users'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Team Members</p>
                                <div class="mt-2">
                                    <small class="text-info">
                                        <i class="bi bi-person-workspace"></i>
                                        <?php echo count(array_filter($analyticsData['users'] ?? [], fn($u) => strtolower($u['role'] ?? '') === 'technician')); ?> Technicians
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Statistics Cards -->
                <div class="row g-3 g-lg-4 mb-4">
                    <!-- Job Cards -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-clipboard-check text-warning fs-4"></i>
                                    </div>
                                    <span class="badge bg-warning rounded-pill">Job Cards</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['job_cards'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Total Job Cards</p>
                                <div class="mt-2">
                                    <small class="text-warning">
                                        <i class="bi bi-hourglass"></i>
                                        <?php echo count(array_filter($analyticsData['job_cards'] ?? [], fn($j) => strtolower($j['status'] ?? '') === 'in progress')); ?> In Progress
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Repair Maintenance -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-secondary bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-tools text-secondary fs-4"></i>
                                    </div>
                                    <span class="badge bg-secondary rounded-pill">Repairs</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['repair_maintenance'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Repair Forms</p>
                                <div class="mt-2">
                                    <small class="text-warning">
                                        <i class="bi bi-hourglass"></i>
                                        <?php echo count(array_filter($analyticsData['repair_maintenance'] ?? [], fn($r) => strtolower($r['status'] ?? '') === 'pending')); ?> Pending
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CCTV Checklists -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-purple bg-opacity-10 p-3 rounded-circle" style="background-color: rgba(111, 66, 193, 0.1);">
                                        <i class="bi bi-camera-video text-purple fs-4" style="color: #6f42c1;"></i>
                                    </div>
                                    <span class="badge rounded-pill" style="background-color: #6f42c1;">CCTV</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['cctv_checklist'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">CCTV Checklists</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Approvals -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-orange bg-opacity-10 p-3 rounded-circle" style="background-color: rgba(253, 126, 20, 0.1);">
                                        <i class="bi bi-check-circle text-orange fs-4" style="color: #fd7e14;"></i>
                                    </div>
                                    <span class="badge rounded-pill" style="background-color: #fd7e14;">Approvals</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1">
                                    <?php
                                    $pendingApprovals = count(array_filter($analyticsData['requisitions'] ?? [], fn($r) => strtolower($r['status'] ?? '') === 'pending'))
                                        + count(array_filter($analyticsData['repair_maintenance'] ?? [], fn($r) => strtolower($r['status'] ?? '') === 'pending'));
                                    echo $pendingApprovals;
                                    ?>
                                </h3>
                                <p class="text-muted small mb-0">Pending Approvals</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-3 g-lg-4 mb-4">
                    <!-- Tickets by Status -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="h6 mb-0">Tickets by Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="ticketsStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Requisitions by Status -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="h6 mb-0">Requisitions by Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="requisitionsStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="row g-3 g-lg-4 mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="h6 mb-0">Department Activity Timeline (Last 30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container-lg">
                                    <canvas id="activityTimelineChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Items Table -->
                <div class="row g-3 g-lg-4 mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="h6 mb-0">Pending Items Requiring Attention</h5>
                                <span class="badge bg-warning text-dark">
                                    <?php
                                    $pendingCount = count(array_filter($analyticsData['tickets'] ?? [], fn($t) => strtolower($t['status'] ?? '') === 'open'))
                                        + count(array_filter($analyticsData['requisitions'] ?? [], fn($r) => strtolower($r['status'] ?? '') === 'pending'))
                                        + count(array_filter($analyticsData['incidents'] ?? [], fn($i) => strtolower($i['status'] ?? '') === 'pending'))
                                        + count(array_filter($analyticsData['repair_maintenance'] ?? [], fn($r) => strtolower($r['status'] ?? '') === 'pending'));
                                    echo $pendingCount;
                                    ?> Items
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Title/Description</th>
                                                <th>Status</th>
                                                <th>Priority</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pendingItemsTable">
                                            <!-- Populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Performance -->
                <div class="row g-3 g-lg-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="h6 mb-0">Team Members</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Role</th>
                                                <th>Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analyticsData['users'] ?? [] as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($user['role'] ?? '-'); ?></span></td>
                                                <td><?php echo htmlspecialchars(substr($user['created_at'] ?? '-', 0, 10)); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>

    <script>
        // Analytics data from PHP
        const analyticsData = <?php echo json_encode($analyticsData); ?>;
        const department = <?php echo json_encode($department); ?>;

        // Store original data for filtering
        const originalData = JSON.parse(JSON.stringify(analyticsData));

        // Function to filter data by date period
        function filterDataByPeriod(data, days) {
            if (days === 'all') return data;

            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - parseInt(days));

            return data.filter(item => {
                const itemDate = new Date(item.created_at || item.form_date);
                return itemDate >= cutoffDate;
            });
        }

        // Function to update statistics
        function updateStatistics(period) {
            const filteredData = {
                tickets: filterDataByPeriod(originalData.tickets || [], period),
                requisitions: filterDataByPeriod(originalData.requisitions || [], period),
                incidents: filterDataByPeriod(originalData.incidents || [], period),
                job_cards: filterDataByPeriod(originalData.job_cards || [], period),
                repair_maintenance: filterDataByPeriod(originalData.repair_maintenance || [], period),
                cctv_checklist: filterDataByPeriod(originalData.cctv_checklist || [], period),
                users: originalData.users || []
            };

            // Update ticket count
            const ticketCount = filteredData.tickets.length;
            const openTicketCount = filteredData.tickets.filter(t => (t.status || '').toLowerCase() === 'open').length;
            document.querySelector('.stat-card:nth-child(1) h3').textContent = ticketCount;
            document.querySelector('.stat-card:nth-child(1) .text-success').innerHTML = `<i class="bi bi-clock"></i> ${openTicketCount} Open`;

            // Update requisition count
            const reqCount = filteredData.requisitions.length;
            const pendingReqCount = filteredData.requisitions.filter(r => (r.status || '').toLowerCase() === 'pending').length;
            document.querySelector('.stat-card:nth-child(2) h3').textContent = reqCount;
            document.querySelector('.stat-card:nth-child(2) .text-warning').innerHTML = `<i class="bi bi-hourglass"></i> ${pendingReqCount} Pending Approval`;

            // Update incident count
            const incCount = filteredData.incidents.length;
            const pendingIncCount = filteredData.incidents.filter(i => (i.status || '').toLowerCase() === 'pending').length;
            document.querySelector('.stat-card:nth-child(3) h3').textContent = incCount;
            document.querySelector('.stat-card:nth-child(3) .text-warning').innerHTML = `<i class="bi bi-clock"></i> ${pendingIncCount} Pending`;

            // Update repair count
            const repairCount = filteredData.repair_maintenance.length;
            const pendingRepairCount = filteredData.repair_maintenance.filter(r => (r.status || '').toLowerCase() === 'pending').length;
            document.querySelector('.stat-card:nth-child(6) h3').textContent = repairCount;
            document.querySelector('.stat-card:nth-child(6) .text-warning').innerHTML = `<i class="bi bi-hourglass"></i> ${pendingRepairCount} Pending`;

            // Update pending approvals
            const pendingApprovals = pendingReqCount + pendingRepairCount;
            document.querySelector('.stat-card:nth-child(8) h3').textContent = pendingApprovals;

            // Update pending items table
            updatePendingItemsTable(filteredData);

            // Update charts
            updateCharts(filteredData);
        }

        // Function to update pending items table
        function updatePendingItemsTable(data) {
            const pendingItemsTable = document.getElementById('pendingItemsTable');
            if (!pendingItemsTable) return;

            const pendingItems = [];

            // Add pending tickets
            if (Array.isArray(data.tickets)) {
                data.tickets.filter(t => (t.status || '').toLowerCase() === 'open').forEach(ticket => {
                    pendingItems.push({
                        type: 'Ticket',
                        title: ticket.title || ticket.description || '-',
                        status: ticket.status,
                        priority: ticket.priority?.name || ticket.priority || '-',
                        created: ticket.created_at ? ticket.created_at.split('T')[0] : '-',
                        link: 'tickets'
                    });
                });
            }

            // Add pending requisitions
            if (Array.isArray(data.requisitions)) {
                data.requisitions.filter(r => (r.status || '').toLowerCase() === 'pending').forEach(req => {
                    pendingItems.push({
                        type: 'Requisition',
                        title: req.item_name || req.description || '-',
                        status: req.status,
                        priority: 'Medium',
                        created: req.created_at ? req.created_at.split('T')[0] : '-',
                        link: 'requisitions_view_all'
                    });
                });
            }

            // Add pending incidents
            if (Array.isArray(data.incidents)) {
                data.incidents.filter(i => (i.status || '').toLowerCase() === 'pending').forEach(incident => {
                    pendingItems.push({
                        type: 'Incident',
                        title: incident.incident_type || incident.description || '-',
                        status: incident.status,
                        priority: 'High',
                        created: incident.created_at ? incident.created_at.split('T')[0] : '-',
                        link: 'incident'
                    });
                });
            }

            // Add pending repairs
            if (Array.isArray(data.repair_maintenance)) {
                data.repair_maintenance.filter(r => (r.status || '').toLowerCase() === 'pending').forEach(repair => {
                    pendingItems.push({
                        type: 'Repair',
                        title: repair.repair_type || '-',
                        status: repair.status,
                        priority: 'Medium',
                        created: repair.created_at ? repair.created_at.split('T')[0] : '-',
                        link: 'repair_maintenance'
                    });
                });
            }

            // Sort by created date (newest first)
            pendingItems.sort((a, b) => new Date(b.created) - new Date(a.created));

            // Render table rows (limit to 10)
            pendingItemsTable.innerHTML = '';
            if (pendingItems.length === 0) {
                pendingItemsTable.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No pending items</td></tr>';
            } else {
                pendingItems.slice(0, 10).forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><span class="badge bg-secondary">${item.type}</span></td>
                        <td>${item.title}</td>
                        <td><span class="badge bg-warning text-dark">${item.status}</span></td>
                        <td><span class="priority-${item.priority.toLowerCase()}">${item.priority}</span></td>
                        <td>${item.created}</td>
                        <td>
                            <a href="${item.link}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    `;
                    pendingItemsTable.appendChild(row);
                });
            }

            // Update pending count badge
            const pendingCountBadge = document.querySelector('.card-header .badge');
            if (pendingCountBadge) {
                pendingCountBadge.textContent = pendingItems.length + ' Items';
            }
        }

        // Store chart instances for updating
        let ticketsStatusChart = null;
        let requisitionsStatusChart = null;
        let activityTimelineChart = null;

        // Function to update charts
        function updateCharts(data) {
            // Update tickets status chart
            if (ticketsStatusChart) {
                const ticketStatusCounts = {};
                if (Array.isArray(data.tickets)) {
                    data.tickets.forEach(ticket => {
                        const status = (ticket.status || 'unknown').toLowerCase();
                        ticketStatusCounts[status] = (ticketStatusCounts[status] || 0) + 1;
                    });
                }
                ticketsStatusChart.data.labels = Object.keys(ticketStatusCounts);
                ticketsStatusChart.data.datasets[0].data = Object.values(ticketStatusCounts);
                ticketsStatusChart.update();
            }

            // Update requisitions status chart
            if (requisitionsStatusChart) {
                const reqStatusCounts = {};
                if (Array.isArray(data.requisitions)) {
                    data.requisitions.forEach(req => {
                        const status = (req.status || 'unknown').toLowerCase();
                        reqStatusCounts[status] = (reqStatusCounts[status] || 0) + 1;
                    });
                }
                requisitionsStatusChart.data.labels = Object.keys(reqStatusCounts);
                requisitionsStatusChart.data.datasets[0].data = Object.values(reqStatusCounts);
                requisitionsStatusChart.update();
            }

            // Update activity timeline chart
            if (activityTimelineChart) {
                const timeline = {};
                const days = document.getElementById('periodSelector').value;
                const dayCount = days === 'all' ? 30 : parseInt(days);

                // Initialize timeline
                for (let i = 0; i < dayCount; i++) {
                    const date = new Date();
                    date.setDate(date.getDate() - i);
                    const dateStr = date.toISOString().split('T')[0];
                    timeline[dateStr] = { tickets: 0, requisitions: 0, incidents: 0, repairs: 0 };
                }

                // Count activities by date
                if (Array.isArray(data.tickets)) {
                    data.tickets.forEach(ticket => {
                        if (ticket.created_at) {
                            const dateStr = ticket.created_at.split('T')[0];
                            if (timeline[dateStr]) timeline[dateStr].tickets++;
                        }
                    });
                }

                if (Array.isArray(data.requisitions)) {
                    data.requisitions.forEach(req => {
                        if (req.created_at) {
                            const dateStr = req.created_at.split('T')[0];
                            if (timeline[dateStr]) timeline[dateStr].requisitions++;
                        }
                    });
                }

                if (Array.isArray(data.incidents)) {
                    data.incidents.forEach(incident => {
                        if (incident.created_at) {
                            const dateStr = incident.created_at.split('T')[0];
                            if (timeline[dateStr]) timeline[dateStr].incidents++;
                        }
                    });
                }

                if (Array.isArray(data.repair_maintenance)) {
                    data.repair_maintenance.forEach(repair => {
                        if (repair.created_at) {
                            const dateStr = repair.created_at.split('T')[0];
                            if (timeline[dateStr]) timeline[dateStr].repairs++;
                        }
                    });
                }

                const labels = Object.keys(timeline).reverse();
                activityTimelineChart.data.labels = labels;
                activityTimelineChart.data.datasets[0].data = labels.map(d => timeline[d].tickets);
                activityTimelineChart.data.datasets[1].data = labels.map(d => timeline[d].requisitions);
                activityTimelineChart.data.datasets[2].data = labels.map(d => timeline[d].incidents);
                activityTimelineChart.data.datasets[3].data = labels.map(d => timeline[d].repairs);
                activityTimelineChart.update();
            }
        }

        // Period selector change event
        document.getElementById('periodSelector').addEventListener('change', function() {
            updateStatistics(this.value);
        });

        // Chart.js configuration
        Chart.defaults.font.family = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        Chart.defaults.color = '#6c757d';

        // Tickets Status Chart
        const ticketsStatusCtx = document.getElementById('ticketsStatusChart');
        if (ticketsStatusCtx) {
            const ticketStatusCounts = {};
            if (Array.isArray(analyticsData.tickets)) {
                analyticsData.tickets.forEach(ticket => {
                    const status = (ticket.status || 'unknown').toLowerCase();
                    ticketStatusCounts[status] = (ticketStatusCounts[status] || 0) + 1;
                });
            }

            ticketsStatusChart = new Chart(ticketsStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(ticketStatusCounts),
                    datasets: [{
                        data: Object.values(ticketStatusCounts),
                        backgroundColor: [
                            '#0d6efd',
                            '#198754',
                            '#ffc107',
                            '#dc3545',
                            '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Requisitions Status Chart
        const requisitionsStatusCtx = document.getElementById('requisitionsStatusChart');
        if (requisitionsStatusCtx) {
            const reqStatusCounts = {};
            if (Array.isArray(analyticsData.requisitions)) {
                analyticsData.requisitions.forEach(req => {
                    const status = (req.status || 'unknown').toLowerCase();
                    reqStatusCounts[status] = (reqStatusCounts[status] || 0) + 1;
                });
            }

            requisitionsStatusChart = new Chart(requisitionsStatusCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(reqStatusCounts),
                    datasets: [{
                        label: 'Requisitions',
                        data: Object.values(reqStatusCounts),
                        backgroundColor: [
                            '#198754',
                            '#ffc107',
                            '#dc3545',
                            '#0d6efd'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Activity Timeline Chart
        const activityTimelineCtx = document.getElementById('activityTimelineChart');
        if (activityTimelineCtx) {
            const timeline = {};
            const days = document.getElementById('periodSelector').value;
            const dayCount = days === 'all' ? 30 : parseInt(days);

            // Initialize timeline
            for (let i = 0; i < dayCount; i++) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateStr = date.toISOString().split('T')[0];
                timeline[dateStr] = { tickets: 0, requisitions: 0, incidents: 0, repairs: 0 };
            }

            // Count activities by date
            if (Array.isArray(analyticsData.tickets)) {
                analyticsData.tickets.forEach(ticket => {
                    if (ticket.created_at) {
                        const dateStr = ticket.created_at.split('T')[0];
                        if (timeline[dateStr]) timeline[dateStr].tickets++;
                    }
                });
            }

            if (Array.isArray(analyticsData.requisitions)) {
                analyticsData.requisitions.forEach(req => {
                    if (req.created_at) {
                        const dateStr = req.created_at.split('T')[0];
                        if (timeline[dateStr]) timeline[dateStr].requisitions++;
                    }
                });
            }

            if (Array.isArray(analyticsData.incidents)) {
                analyticsData.incidents.forEach(incident => {
                    if (incident.created_at) {
                        const dateStr = incident.created_at.split('T')[0];
                        if (timeline[dateStr]) timeline[dateStr].incidents++;
                    }
                });
            }

            if (Array.isArray(analyticsData.repair_maintenance)) {
                analyticsData.repair_maintenance.forEach(repair => {
                    if (repair.created_at) {
                        const dateStr = repair.created_at.split('T')[0];
                        if (timeline[dateStr]) timeline[dateStr].repairs++;
                    }
                });
            }

            const labels = Object.keys(timeline).reverse();
            activityTimelineChart = new Chart(activityTimelineCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Tickets',
                            data: labels.map(d => timeline[d].tickets),
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Requisitions',
                            data: labels.map(d => timeline[d].requisitions),
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Incidents',
                            data: labels.map(d => timeline[d].incidents),
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Repairs',
                            data: labels.map(d => timeline[d].repairs),
                            borderColor: '#6c757d',
                            backgroundColor: 'rgba(108, 117, 125, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Initialize pending items table with current data
        updatePendingItemsTable(analyticsData);

        // Initialize charts with current data
        updateCharts(analyticsData);
    </script>
</body>
</html>
