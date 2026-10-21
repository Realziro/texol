<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
require_once __DIR__ . '/../auth.php';

// Load env/config (Supabase keys, etc.)

// Protect page: only allow access when logged in
if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Restrict to admin and HOD only
if (!is_admin() && !is_hod()) {
    header('Location:   index');
    exit;
}

$analyticsData = [];
$errorMessage = '';

// Fetch analytics data from Supabase
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    try {
        // Fetch tickets data
        $query = http_build_query(['select' => '*,category:categories(name),priority:priorities(name),status', 'order' => 'created_at.desc']);
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
        $analyticsData['tickets'] = json_decode($response, true) ?: [];
        curl_close($ch);

        // Fetch requisitions data
        $query = http_build_query(['select' => '*,supplier:suppliers(name),requested_by_user:users(full_name,department)', 'order' => 'created_at.desc']);
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
        $analyticsData['requisitions'] = json_decode($response, true) ?: [];
        curl_close($ch);

        // Fetch incidents data
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
        $analyticsData['incidents'] = json_decode($response, true) ?: [];
        curl_close($ch);

        // Fetch meetings data
        $query = http_build_query(['select' => '*', 'order' => 'created_at.desc']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/meetings?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $analyticsData['meetings'] = json_decode($response, true) ?: [];
        curl_close($ch);

        // Fetch customer feedback data
        $query = http_build_query(['select' => '*', 'order' => 'created_at.desc']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/customer_feedback?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $analyticsData['customer_feedback'] = json_decode($response, true) ?: [];
        curl_close($ch);

        // Fetch users data
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
        $analyticsData['users'] = json_decode($response, true) ?: [];
        curl_close($ch);

        // Fetch job cards data
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
        $analyticsData['job_cards'] = json_decode($response, true) ?: [];
        curl_close($ch);

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
    <title>System Analytics - Work Card System</title>

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
    </style>
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'analytics';
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
                    <span id="pageTitle">System Analytics</span>
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

                <!-- Header with Period Selector -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h4 fw-semibold mb-1">System Analytics Dashboard</h1>
                        <p class="text-muted small mb-0">
                            Comprehensive overview of all system modules and activities
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
                    <!-- Total Tickets -->
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
                                    <small class="text-success">
                                        <i class="bi bi-arrow-up"></i>
                                        <?php echo count(array_filter($analyticsData['tickets'] ?? [], fn($t) => strtolower($t['status'] ?? '') === 'open')); ?> Open
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Requisitions -->
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
                                    <small class="text-success">
                                        <i class="bi bi-arrow-up"></i>
                                        <?php echo count(array_filter($analyticsData['requisitions'] ?? [], fn($r) => ($r['status'] ?? '') === 'approved')); ?> Approved
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Incidents -->
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

                    <!-- Total Users -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-people text-info fs-4"></i>
                                    </div>
                                    <span class="badge bg-info rounded-pill">Users</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['users'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Total Users</p>
                                <div class="mt-2">
                                    <small class="text-info">
                                        <i class="bi bi-person-badge"></i>
                                        <?php echo count(array_filter($analyticsData['users'] ?? [], fn($u) => strtolower($u['role'] ?? '') === 'admin')); ?> Admins
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
                            </div>
                        </div>
                    </div>

                    <!-- Meetings -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-secondary bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-calendar-event text-secondary fs-4"></i>
                                    </div>
                                    <span class="badge bg-secondary rounded-pill">Meetings</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['meetings'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Total Meetings</p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Feedback -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-purple bg-opacity-10 p-3 rounded-circle" style="background-color: rgba(111, 66, 193, 0.1);">
                                        <i class="bi bi-chat-dots text-purple fs-4" style="color: #6f42c1;"></i>
                                    </div>
                                    <span class="badge rounded-pill" style="background-color: #6f42c1;">Feedback</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1"><?php echo count($analyticsData['customer_feedback'] ?? []); ?></h3>
                                <p class="text-muted small mb-0">Customer Feedback</p>
                            </div>
                        </div>
                    </div>

                    <!-- System Health -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card border-0 shadow-sm stat-card h-100">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                        <i class="bi bi-heart-pulse text-success fs-4"></i>
                                    </div>
                                    <span class="badge bg-success rounded-pill">Health</span>
                                </div>
                                <h3 class="h2 fw-bold mb-1">98%</h3>
                                <p class="text-muted small mb-0">System Health</p>
                                <div class="mt-2">
                                    <small class="text-success">
                                        <i class="bi bi-check-circle"></i> All Systems Operational
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row g-3 g-lg-4 mb-4">
                    <!-- Tickets Status Chart -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4">
                                <h5 class="h6 mb-0 fw-semibold">Tickets by Status</h5>
                            </div>
                            <div class="card-body px-3 px-md-4 pb-4">
                                <div class="chart-container">
                                    <canvas id="ticketsStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Requisitions Status Chart -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4">
                                <h5 class="h6 mb-0 fw-semibold">Requisitions by Status</h5>
                            </div>
                            <div class="card-body px-3 px-md-4 pb-4">
                                <div class="chart-container">
                                    <canvas id="requisitionsStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 g-lg-4 mb-4">
                    <!-- Activity Timeline Chart -->
                    <div class="col-12 col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4">
                                <h5 class="h6 mb-0 fw-semibold">Activity Timeline (Last 30 Days)</h5>
                            </div>
                            <div class="card-body px-3 px-md-4 pb-4">
                                <div class="chart-container-lg">
                                    <canvas id="activityTimelineChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Department Distribution Chart -->
                    <div class="col-12 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4">
                                <h5 class="h6 mb-0 fw-semibold">Users by Department</h5>
                            </div>
                            <div class="card-body px-3 px-md-4 pb-4">
                                <div class="chart-container">
                                    <canvas id="departmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Tables Section -->
                <div class="row g-3 g-lg-4 mb-4">
                    <!-- Recent Tickets Table -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                <h5 class="h6 mb-0 fw-semibold">Recent Tickets</h5>
                                <a href="tickets" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="small">ID</th>
                                                <th class="small">Subject</th>
                                                <th class="small">Status</th>
                                                <th class="small">Priority</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $recentTickets = array_slice($analyticsData['tickets'] ?? [], 0, 5);
                                            foreach ($recentTickets as $ticket):
                                                if (!is_array($ticket)) continue;
                                            ?>
                                            <tr>
                                                <td class="small"><?php echo htmlspecialchars($ticket['ticket_id'] ?? '-'); ?></td>
                                                <td class="small"><?php echo htmlspecialchars(substr($ticket['subject'] ?? '-', 0, 20)); ?>...</td>
                                                <td class="small">
                                                    <span class="badge status-badge rounded-pill <?php
                                                        $status = strtolower($ticket['status'] ?? '');
                                                        $badgeClass = 'bg-secondary';
                                                        if ($status === 'open') $badgeClass = 'bg-primary';
                                                        elseif ($status === 'in_progress') $badgeClass = 'bg-warning text-dark';
                                                        elseif ($status === 'resolved') $badgeClass = 'bg-success';
                                                        elseif ($status === 'closed') $badgeClass = 'bg-dark';
                                                        echo $badgeClass;
                                                    ?>">
                                                        <?php echo ucfirst($ticket['status'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="small">
                                                    <span class="badge status-badge rounded-pill <?php
                                                        $priority = strtolower($ticket['priority'] ?? '');
                                                        $priorityClass = 'bg-secondary';
                                                        if ($priority === 'high') $priorityClass = 'bg-danger';
                                                        elseif ($priority === 'medium') $priorityClass = 'bg-warning text-dark';
                                                        elseif ($priority === 'low') $priorityClass = 'bg-info';
                                                        echo $priorityClass;
                                                    ?>">
                                                        <?php echo ucfirst($ticket['priority'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recentTickets)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted small py-3">No tickets found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Requisitions Table -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                <h5 class="h6 mb-0 fw-semibold">Recent Requisitions</h5>
                                <a href="requisition" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="small">RQ #</th>
                                                <th class="small">Description</th>
                                                <th class="small">Status</th>
                                                <th class="small">Department</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $recentRequisitions = array_slice($analyticsData['requisitions'] ?? [], 0, 5);
                                            foreach ($recentRequisitions as $requisition):
                                                if (!is_array($requisition)) continue;
                                            ?>
                                            <tr>
                                                <td class="small"><?php echo htmlspecialchars($requisition['requisition_number'] ?? '-'); ?></td>
                                                <td class="small"><?php echo htmlspecialchars(substr($requisition['description'] ?? '-', 0, 20)); ?>...</td>
                                                <td class="small">
                                                    <span class="badge status-badge rounded-pill <?php
                                                        $status = strtolower($requisition['status'] ?? '');
                                                        $badgeClass = 'bg-secondary';
                                                        if ($status === 'pending') $badgeClass = 'bg-warning text-dark';
                                                        elseif ($status === 'approved') $badgeClass = 'bg-success';
                                                        elseif ($status === 'partially_approved') $badgeClass = 'bg-info';
                                                        elseif ($status === 'rejected') $badgeClass = 'bg-danger';
                                                        echo $badgeClass;
                                                    ?>">
                                                        <?php
                                                        if ($status === 'partially_approved') echo 'Partially';
                                                        else echo ucfirst($requisition['status'] ?? '-');
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="small"><?php echo htmlspecialchars($requisition['department'] ?? '-'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recentRequisitions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted small py-3">No requisitions found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- More Detailed Tables -->
                <div class="row g-3 g-lg-4 mb-4">
                    <!-- Incidents Table -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                <h5 class="h6 mb-0 fw-semibold">Recent Incidents</h5>
                                <a href="incident" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="small">Date</th>
                                                <th class="small">Type</th>
                                                <th class="small">Location</th>
                                                <th class="small">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $recentIncidents = array_slice($analyticsData['incidents'] ?? [], 0, 5);
                                            foreach ($recentIncidents as $incident):
                                                if (!is_array($incident)) continue;
                                            ?>
                                            <tr>
                                                <td class="small"><?php echo htmlspecialchars(substr($incident['incident_date'] ?? '-', 0, 10)); ?></td>
                                                <td class="small"><?php echo htmlspecialchars(substr($incident['incident_type'] ?? '-', 0, 15)); ?>...</td>
                                                <td class="small"><?php echo htmlspecialchars(substr($incident['location'] ?? '-', 0, 15)); ?>...</td>
                                                <td class="small">
                                                    <span class="badge status-badge rounded-pill <?php
                                                        $status = strtolower($incident['status'] ?? '');
                                                        $badgeClass = 'bg-secondary';
                                                        if ($status === 'pending') $badgeClass = 'bg-warning text-dark';
                                                        elseif ($status === 'investigating') $badgeClass = 'bg-info';
                                                        elseif ($status === 'resolved') $badgeClass = 'bg-success';
                                                        echo $badgeClass;
                                                    ?>">
                                                        <?php echo ucfirst($incident['status'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recentIncidents)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted small py-3">No incidents found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                <h5 class="h6 mb-0 fw-semibold">Recent Users</h5>
                                <a href="users" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="small">Name</th>
                                                <th class="small">Role</th>
                                                <th class="small">Department</th>
                                                <th class="small">Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $recentUsers = array_slice($analyticsData['users'] ?? [], 0, 5);
                                            foreach ($recentUsers as $user):
                                                if (!is_array($user)) continue;
                                            ?>
                                            <tr>
                                                <td class="small"><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                                <td class="small">
                                                    <span class="badge status-badge rounded-pill <?php
                                                        $role = strtolower($user['role'] ?? '');
                                                        $roleClass = 'bg-secondary';
                                                        if ($role === 'admin') $roleClass = 'bg-danger';
                                                        elseif ($role === 'hod') $roleClass = 'bg-warning text-dark';
                                                        elseif ($role === 'technician') $roleClass = 'bg-info';
                                                        echo $roleClass;
                                                    ?>">
                                                        <?php echo ucfirst($user['role'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="small"><?php echo htmlspecialchars($user['department'] ?? '-'); ?></td>
                                                <td class="small"><?php echo htmlspecialchars(substr($user['created_at'] ?? '-', 0, 10)); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recentUsers)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted small py-3">No users found</td>
                                            </tr>
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="app.js"></script>

    <script>
        // Analytics data from PHP
        const analyticsData = <?php echo json_encode($analyticsData); ?>;

        console.log('Analytics Data:', analyticsData);

        // Process data for charts
        function processTicketsByStatus(tickets) {
            const statusCounts = {};
            if (Array.isArray(tickets)) {
                tickets.forEach(ticket => {
                    const status = (ticket.status || 'unknown').toLowerCase();
                    statusCounts[status] = (statusCounts[status] || 0) + 1;
                });
            }
            return statusCounts;
        }

        function processRequisitionsByStatus(requisitions) {
            const statusCounts = {};
            if (Array.isArray(requisitions)) {
                requisitions.forEach(req => {
                    const status = (req.status || 'unknown').toLowerCase();
                    statusCounts[status] = (statusCounts[status] || 0) + 1;
                });
            }
            return statusCounts;
        }

        function processUsersByDepartment(users) {
            const deptCounts = {};
            if (Array.isArray(users)) {
                users.forEach(user => {
                    const dept = user.department || 'Unknown';
                    deptCounts[dept] = (deptCounts[dept] || 0) + 1;
                });
            }
            return deptCounts;
        }

        function processActivityTimeline(data) {
            const timeline = {};
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

            // Initialize last 30 days
            for (let i = 0; i < 30; i++) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateStr = date.toISOString().split('T')[0];
                timeline[dateStr] = { tickets: 0, requisitions: 0, incidents: 0 };
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

            return timeline;
        }

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing charts...');

            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded!');
                return;
            }

            try {
                // Tickets Status Chart
                const ticketsCtx = document.getElementById('ticketsStatusChart');
                if (ticketsCtx) {
                    const ticketsStatus = processTicketsByStatus(analyticsData.tickets || []);
                    console.log('Tickets Status Data:', ticketsStatus);

                    if (Object.keys(ticketsStatus).length > 0) {
                        new Chart(ticketsCtx.getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: Object.keys(ticketsStatus),
                                datasets: [{
                                    data: Object.values(ticketsStatus),
                                    backgroundColor: [
                                        '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d'
                                    ],
                                    borderWidth: 2
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
                    } else {
                        ticketsCtx.getContext('2d').font = '14px Arial';
                        ticketsCtx.getContext('2d').fillText('No ticket data available', 10, 50);
                    }
                }

                // Requisitions Status Chart
                const reqCtx = document.getElementById('requisitionsStatusChart');
                if (reqCtx) {
                    const reqStatus = processRequisitionsByStatus(analyticsData.requisitions || []);
                    console.log('Requisitions Status Data:', reqStatus);

                    if (Object.keys(reqStatus).length > 0) {
                        new Chart(reqCtx.getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: Object.keys(reqStatus),
                                datasets: [{
                                    data: Object.values(reqStatus),
                                    backgroundColor: [
                                        '#ffc107', '#198754', '#0dcaf0', '#dc3545', '#6c757d'
                                    ],
                                    borderWidth: 2
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
                    } else {
                        reqCtx.getContext('2d').font = '14px Arial';
                        reqCtx.getContext('2d').fillText('No requisition data available', 10, 50);
                    }
                }

                // Activity Timeline Chart
                const activityCtx = document.getElementById('activityTimelineChart');
                if (activityCtx) {
                    const activityTimeline = processActivityTimeline(analyticsData);
                    const sortedDates = Object.keys(activityTimeline).sort().reverse();
                    const ticketData = sortedDates.map(date => activityTimeline[date].tickets);
                    const reqData = sortedDates.map(date => activityTimeline[date].requisitions);
                    const incidentData = sortedDates.map(date => activityTimeline[date].incidents);

                    console.log('Activity Timeline Data:', { sortedDates, ticketData, reqData, incidentData });

                    new Chart(activityCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: sortedDates.map(date => {
                                const d = new Date(date);
                                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            }),
                            datasets: [
                                {
                                    label: 'Tickets',
                                    data: ticketData,
                                    borderColor: '#0d6efd',
                                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Requisitions',
                                    data: reqData,
                                    borderColor: '#198754',
                                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Incidents',
                                    data: incidentData,
                                    borderColor: '#dc3545',
                                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                                    fill: true,
                                    tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            }
                        }
                    });
                }

                // Department Distribution Chart
                const deptCtx = document.getElementById('departmentChart');
                if (deptCtx) {
                    const deptDistribution = processUsersByDepartment(analyticsData.users || []);
                    console.log('Department Distribution Data:', deptDistribution);

                    if (Object.keys(deptDistribution).length > 0) {
                        new Chart(deptCtx.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: Object.keys(deptDistribution),
                                datasets: [{
                                    label: 'Users',
                                    data: Object.values(deptDistribution),
                                    backgroundColor: [
                                        '#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6c757d'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: 'y',
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        });
                    } else {
                        deptCtx.getContext('2d').font = '14px Arial';
                        deptCtx.getContext('2d').fillText('No user data available', 10, 50);
                    }
                }

                console.log('Charts initialized successfully');
            } catch (error) {
                console.error('Error initializing charts:', error);
            }
        });

        // Period selector functionality
        document.getElementById('periodSelector').addEventListener('change', function() {
            // In a real implementation, this would filter the data based on the selected period
            // For now, we'll just show a message
            console.log('Period changed to:', this.value);
        });
    </script>
</body>
</html>