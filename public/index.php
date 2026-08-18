<?php
session_start();

// Protect dashboard: only allow access when logged in
if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Load env/config (Supabase keys, etc.)
require_once __DIR__ . '/../config.php';

// Dashboard metrics sourced from Supabase `tickets`
$email = $_SESSION['user_email'] ?? '';
$userUuid = $_SESSION['user_id'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$isAdmin = (strtolower($userRole) === 'admin');

$supabaseUrl = defined('SUPABASE_URL') ? rtrim(SUPABASE_URL, '/') : '';
$supabaseKey = defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '';

$totalWorkCards = 0;
$thisWeekCount = 0;
$pendingReviews = 0;
$assignedTickets = [];
// New dashboard metrics
$assignedTicketsCount = 0;
$unassignedTicketsCount = 0;
$overdueTicketsCount = 0;
$todayTicketsCount = 0;
$dueTodayTicketsCount = 0;
$openTicketsCount = 0;
$inProgressTicketsCount = 0;
$closedTicketsCount = 0;
$lowPriorityCount = 0;
$mediumPriorityCount = 0;
$highPriorityCount = 0;
$urgentPriorityCount = 0;

if ($supabaseUrl !== '' && $supabaseKey !== '' && $email !== '') {
    // Helper to perform a count query (uses Prefer: count=exact)
    $countQuery = function(array $params) use ($supabaseUrl, $supabaseKey) {
        $ch = curl_init();
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
            'Prefer: count=exact',
            // Ask Supabase/PostgREST to return only the first row but include total
            // via Content-Range to avoid transferring large payloads
            'Range: 0-0'
        ];
        $url = $supabaseUrl . '/rest/v1/tickets?' . http_build_query($params);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headersStr = substr($response, 0, $headerSize);
        curl_close($ch);
        if ($httpCode >= 200 && $httpCode < 300) {
            // Parse Content-Range: 0-24/25 -> total after slash
            $count = 0;
            foreach (explode("\r\n", $headersStr) as $line) {
                if (stripos($line, 'Content-Range:') === 0) {
                    $parts = explode('/', $line);
                    if (count($parts) === 2) {
                        $maybe = trim($parts[1]);
                        if (is_numeric($maybe)) {
                            $count = (int)$maybe;
                        }
                    }
                    break;
                }
            }
            return $count;
        }
        return 0;
    };
    // Generic count helper for a given table/view
    $countQueryOn = function(string $resource, array $params) use ($supabaseUrl, $supabaseKey) {
        $ch = curl_init();
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
            'Prefer: count=exact',
            'Range: 0-0'
        ];
        $url = $supabaseUrl . '/rest/v1/' . rawurlencode($resource) . '?' . http_build_query($params);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headersStr = substr($response, 0, $headerSize);
        curl_close($ch);
        if ($httpCode >= 200 && $httpCode < 300) {
            $count = 0;
            foreach (explode("\r\n", $headersStr) as $line) {
                if (stripos($line, 'Content-Range:') === 0) {
                    $parts = explode('/', $line);
                    if (count($parts) === 2) {
                        $maybe = trim($parts[1]);
                        if (is_numeric($maybe)) {
                            $count = (int)$maybe;
                        }
                    }
                    break;
                }
            }
            return $count;
        }
        return 0;
    };

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $weekStart = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
    $todayStart = new DateTime('today', new DateTimeZone('UTC'));
    $tomorrowStart = (clone $todayStart)->modify('+1 day');
    $nowTs = $now->getTimestamp();

    if ($isAdmin) {
        // Admin sees global stats
        $baseFilter = [];
        $selectForCount = 'id';

        $paramsTotal = array_merge(['select' => $selectForCount], $baseFilter);
        $totalWorkCards = $countQuery($paramsTotal);

        $paramsTotalNonClosed = array_merge(['select' => $selectForCount, 'status' => 'neq.Closed'], $baseFilter);
        $totalNonClosedTickets = $countQuery($paramsTotalNonClosed);

        $paramsWeek = array_merge([
            'select' => $selectForCount,
            'created_at' => 'gte.' . $weekStart->format('Y-m-d\TH:i:s\Z'),
        ], $baseFilter);
        $thisWeekCount = $countQuery($paramsWeek);

        $paramsPending = array_merge([
            'select' => $selectForCount,
            'status' => 'eq.Pending Review',
        ], $baseFilter);
        $pendingReviews = $countQuery($paramsPending);

        $paramsAssignedTickets = [
            'select' => 'id,ticket_assignees!inner(technician_email)',
            'status' => 'neq.Closed',
        ];
        $assignedTicketsCount = $countQuery($paramsAssignedTickets);
        $unassignedTicketsCount = max(0, (int)$totalNonClosedTickets - (int)$assignedTicketsCount);

        $paramsOverdue = array_merge([
            'select'   => $selectForCount,
            'due_date' => 'lt.' . $now->format('Y-m-d\TH:i:s\Z'),
            'status'   => 'neq.Closed',
        ], $baseFilter);
        $overdueTicketsCount = $countQuery($paramsOverdue);

        // Fetch all tickets to filter due today in PHP
        $paramsAllTickets = array_merge([
            'select' => 'id,planned_end_date,status',
            'status' => 'neq.Closed',
        ], $baseFilter);
        $urlAllTickets = $supabaseUrl . '/rest/v1/tickets?' . http_build_query($paramsAllTickets);
        $chAll = curl_init();
        curl_setopt_array($chAll, [
            CURLOPT_URL => $urlAllTickets,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json'
            ]
        ]);
        $allTicketsResponse = curl_exec($chAll);
        curl_close($chAll);
        $allTickets = json_decode($allTicketsResponse, true) ?: [];
        $dueTodayTicketsCount = 0;
        foreach ($allTickets as $ticket) {
            if (!empty($ticket['planned_end_date'])) {
                $dueDate = new DateTime($ticket['planned_end_date']);
                if ($dueDate >= $todayStart && $dueDate < $tomorrowStart) {
                    $dueTodayTicketsCount++;
                }
            }
        }

        $paramsToday = array_merge([
            'select'     => $selectForCount,
            'created_at' => 'gte.' . $todayStart->format('Y-m-d\TH:i:s\Z'),
        ], $baseFilter);
        $todayTicketsCount = $countQuery($paramsToday);

        $openTicketsCount = $countQuery(['select' => $selectForCount, 'status' => 'eq.Open']);
        $inProgressTicketsCount = $countQuery(['select' => $selectForCount, 'status' => 'eq.In Progress']);
        $closedTicketsCount = $countQuery(['select' => $selectForCount, 'status' => 'eq.Closed']);
        $lowPriorityCount = $countQuery(['select' => $selectForCount, 'priority' => 'eq.low']);
        $mediumPriorityCount = $countQuery(['select' => $selectForCount, 'priority' => 'eq.medium']);
        $highPriorityCount = $countQuery(['select' => $selectForCount, 'priority' => 'eq.high']);
        $urgentPriorityCount = $countQuery(['select' => $selectForCount, 'priority' => 'eq.urgent']);
    } else {
        // Non-admin stats: tickets assigned to user OR created by user
        $fetchTickets = function(array $params) use ($supabaseUrl, $supabaseKey) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/tickets?' . http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $supabaseKey,
                    'Authorization: Bearer ' . $supabaseKey,
                    'Accept: application/json',
                ],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && $resp) {
                return json_decode($resp, true) ?: [];
            }
            return [];
        };

        // Build OR filter dynamically based on available values
        // Include: requester (email), requested_by (email or UUID), and assigned tickets
        $orFilter = '(requester.eq.' . $email . ',requested_by.eq.' . $email;
        if ($userUuid) {
            $orFilter .= ',requested_by.eq.' . $userUuid;
        }
        $orFilter .= ')';

        $created = $fetchTickets([
            'select' => 'id,created_at,status,priority,due_date,requester,requested_by,ticket_assignees(technician_email)',
            'or' => $orFilter,
            'limit' => 1000,
        ]);
        $assigned = $fetchTickets([
            'select' => 'id,created_at,status,priority,due_date,requester,requested_by,ticket_assignees!inner(technician_email)',
            'ticket_assignees.technician_email' => 'eq.' . $email,
            'limit' => 1000,
        ]);

        $merged = [];
        foreach (array_merge($created, $assigned) as $t) {
            if (!isset($t['id']) || $t['id'] === '') continue;
            $merged[$t['id']] = $t;
        }
        $scopedTickets = array_values($merged);
        $assignedById = [];
        foreach ($assigned as $t) {
            if (!isset($t['id']) || $t['id'] === '') continue;
            $assignedById[$t['id']] = $t;
        }
        $assignedScopedTickets = array_values($assignedById);

        $totalWorkCards = count($scopedTickets);
        $assignedTicketsCount = count($assignedScopedTickets);

        // For non-admin visible dashboard stats, count both created and assigned tickets
        foreach ($scopedTickets as $t) {
            $createdAt = isset($t['created_at']) ? strtotime((string)$t['created_at']) : false;
            $status = strtolower(trim((string)($t['status'] ?? '')));
            $priority = strtolower(trim((string)($t['priority'] ?? '')));
            $dueAt = isset($t['due_date']) && $t['due_date'] !== null ? strtotime((string)$t['due_date']) : false;
            $assignees = (isset($t['ticket_assignees']) && is_array($t['ticket_assignees'])) ? $t['ticket_assignees'] : [];

            if ($createdAt !== false && $createdAt >= $weekStart->getTimestamp()) $thisWeekCount++;
            if ($createdAt !== false && $createdAt >= $todayStart->getTimestamp()) $todayTicketsCount++;
            if ($status === 'pending review') $pendingReviews++;
            if ($status === 'open') $openTicketsCount++;
            if ($status === 'in progress') $inProgressTicketsCount++;
            if ($status === 'closed') $closedTicketsCount++;
            if ($priority === 'low') $lowPriorityCount++;
            if ($priority === 'medium') $mediumPriorityCount++;
            if ($priority === 'high') $highPriorityCount++;
            if ($priority === 'urgent') $urgentPriorityCount++;
            if ($dueAt !== false && $dueAt < $nowTs && $status !== 'closed') $overdueTicketsCount++;
            if ($dueAt !== false && $dueAt >= $todayStart->getTimestamp() && $dueAt < $tomorrowStart->getTimestamp() && $status !== 'closed') $dueTodayTicketsCount++;
            if (count($assignees) === 0) $unassignedTicketsCount++;
        }
    }

    // 9) Recent activities: all tickets assigned to the logged-in user (created_at only)
    $paramsAssignedList = [
        'select' => 'id,title,status,priority,created_at,requester,requested_by,ticket_assignees!inner(technician_email)',
        'ticket_assignees.technician_email' => 'eq.' . $email,
        'order' => 'created_at.desc',
        'limit' => 50
    ];
    // Fetch list (no count header needed)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/tickets?' . http_build_query($paramsAssignedList),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300 && $resp) {
        $assignedTickets = json_decode($resp, true) ?: [];
        // Sort by coalesce(updated_at, created_at) desc (server ordered by updated_at; add PHP fallback)
        usort($assignedTickets, function($a, $b) {
            $aTime = !empty($a['updated_at']) ? strtotime($a['updated_at']) : strtotime($a['created_at'] ?? '1970-01-01');
            $bTime = !empty($b['updated_at']) ? strtotime($b['updated_at']) : strtotime($b['created_at'] ?? '1970-01-01');
            return $bTime <=> $aTime;
        });
    }
}
date_default_timezone_set('Africa/Nairobi');

// Get current hour in 24h format
$hour = (int) date('H');

// Determine greeting
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 16) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Work Card System - Dashboard</title>

    <!-- Bootstrap 5 CSS CDN -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    />

    <!-- Bootstrap Icons (for sidebar icons) -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    />

    <!-- Custom CSS: Sidebar + Dashboard -->
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">
</head>
<body class="dashboard-body">
    <!--
        Layout structure:
        - Sidebar (fixed on left for md+; collapsible on mobile)
        - Top navbar inside main content with hamburger on mobile
        - Main content area with summary cards and activity table
    -->
    <div class="d-flex" id="layoutWrapper">
        <?php
        // Shared sidebar, mark "dashboard" as active here
        $activeMenu = 'dashboard';
        include __DIR__ . '/partials/sidebar.php';
        ?>

        <!-- Main Content Wrapper -->
        <div class="main-content flex-grow-1 d-flex flex-column">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <!-- Sidebar toggle (hamburger) on small screens -->
                <button
                    class="btn btn-outline-secondary d-lg-none me-2"
                    id="sidebarToggleBtn"
                    type="button"
                    aria-label="Toggle sidebar"
                >
                    <i class="bi bi-list"></i>
                </button>

                <a class="navbar-brand fw-semibold d-none d-sm-inline d-flex align-items-center gap-2" href="#">
                    <span id="pageTitle">Dashboard</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>
            <!-- /Top Navbar -->

            <!-- Main Dashboard Content -->
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <!-- DASHBOARD SECTION -->
                <section class="mb-4" data-section="dashboard">
      <h1 class="h4 fw-semibold mb-1">
    <?php echo $greeting; ?>, 
    <span class="small d-none d-md-inline"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
</h1>
                    <p class="text-muted small mb-0">
                        Here’s an overview of your work cards and recent activity.
                    </p>
                </section>

                <!-- Additional Ticket Metrics -->
                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <!-- Assigned Tickets -->
                        <div class="col-12 <?php echo $isAdmin ? 'col-md-4 col-xl-3' : 'col-md-4'; ?>">
                            <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                <div class="card-body d-flex align-items-center">
                                    <div class="summary-icon-wrap bg-success-subtle text-success me-3">
                                        <i class="bi bi-person-check"></i>
                                    </div>
                                    <div>
                                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                                            Assigned Tickets
                                        </div>
                                        <div class="fs-4 fw-bold"><?php echo (int)$assignedTicketsCount; ?></div>
                                        <div class="text-muted small mt-1">
                                            Tickets with technicians
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo $isAdmin ? 'tickets?view=assigned' : 'usertickets?view=assigned'; ?>" class="stretched-link" aria-label="View assigned tickets"></a>
                            </div>
                        </div>

                        <?php if ($isAdmin) : ?>
                            <!-- Unassigned Tickets (admin only) -->
                            <div class="col-12 col-md-4 col-xl-3">
                                <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="summary-icon-wrap bg-danger-subtle text-danger me-3">
                                            <i class="bi bi-person-dash"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted text-uppercase fw-semibold mb-1">
                                                Unassigned Tickets
                                            </div>
                                            <div class="fs-4 fw-bold"><?php echo (int)$unassignedTicketsCount; ?></div>
                                            <div class="text-muted small mt-1">
                                                No technician assigned
                                            </div>
                                        </div>
                                    </div>
                                    <a href="tickets?view=unassigned" class="stretched-link" aria-label="View unassigned tickets"></a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Overdue Tickets -->
                        <div class="col-12 <?php echo $isAdmin ? 'col-md-4 col-xl-3' : 'col-md-4'; ?>">
                            <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                <div class="card-body d-flex align-items-center">
                                    <div class="summary-icon-wrap bg-danger-subtle text-danger me-3">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </div>
                                    <div>
                                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                                            Overdue Tickets
                                        </div>
                                        <div class="fs-4 fw-bold"><?php echo (int)$overdueTicketsCount; ?></div>
                                        <div class="text-muted small mt-1">
                                            Past their due date
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo $isAdmin ? 'tickets?view=overdue' : 'usertickets?view=overdue'; ?>" class="stretched-link" aria-label="View overdue tickets"></a>
                            </div>
                        </div>

                        <?php if ($isAdmin) : ?>
                            <!-- Due Today Tickets (admin only) -->
                            <div class="col-12 col-md-4 col-xl-3">
                                <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="summary-icon-wrap bg-warning-subtle text-warning me-3">
                                            <i class="bi bi-calendar-x"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted text-uppercase fw-semibold mb-1">
                                                Due Today
                                            </div>
                                            <div class="fs-4 fw-bold"><?php echo (int)$dueTodayTicketsCount; ?></div>
                                            <div class="text-muted small mt-1">
                                                Deadline is today
                                            </div>
                                        </div>
                                    </div>
                                    <a href="tickets?view=today" class="stretched-link" aria-label="View due today tickets"></a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isAdmin) : ?>
                            <!-- Today's Tickets -->
                            <div class="col-12 col-md-4">
                                <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="summary-icon-wrap bg-info-subtle text-info me-3">
                                            <i class="bi bi-calendar-day"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted text-uppercase fw-semibold mb-1">
                                                Today&apos;s Tickets
                                            </div>
                                            <div class="fs-4 fw-bold"><?php echo (int)$todayTicketsCount; ?></div>
                                            <div class="text-muted small mt-1">
                                                Created since midnight
                                            </div>
                                        </div>
                                    </div>
                                    <a href="  usertickets?view=today" class="stretched-link" aria-label="View today&apos;s tickets"></a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- More Stats -->
                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 <?php echo $isAdmin ? 'col-md-3' : 'col-md-4'; ?>">
                            <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                <div class="card-body d-flex align-items-center">
                                    <div class="summary-icon-wrap bg-primary-subtle text-primary me-3">
                                        <i class="bi bi-folder2-open"></i>
                                    </div>
                                    <div>
                                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                                            Open Tickets
                                        </div>
                                        <div class="fs-4 fw-bold"><?php echo (int)$openTicketsCount; ?></div>
                                        <div class="text-muted small mt-1">
                                            Awaiting action
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo $isAdmin ? 'tickets?view=open' : 'usertickets?view=open'; ?>" class="stretched-link" aria-label="View open tickets"></a>
                            </div>
                        </div>

                        <div class="col-12 <?php echo $isAdmin ? 'col-md-3' : 'col-md-4'; ?>">
                            <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                <div class="card-body d-flex align-items-center">
                                    <div class="summary-icon-wrap bg-warning-subtle text-warning me-3">
                                        <i class="bi bi-tools"></i>
                                    </div>
                                    <div>
                                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                                            In Progress
                                        </div>
                                        <div class="fs-4 fw-bold"><?php echo (int)$inProgressTicketsCount; ?></div>
                                        <div class="text-muted small mt-1">
                                            Currently being handled
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo $isAdmin ? 'tickets?view=inprogress' : 'usertickets?view=inprogress'; ?>" class="stretched-link" aria-label="View in progress tickets"></a>
                            </div>
                        </div>

                        <div class="col-12 <?php echo $isAdmin ? 'col-md-3' : 'col-md-4'; ?>">
                            <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                <div class="card-body d-flex align-items-center">
                                    <div class="summary-icon-wrap bg-secondary-subtle text-secondary me-3">
                                        <i class="bi bi-check2-circle"></i>
                                    </div>
                                    <div>
                                        <div class="small text-muted text-uppercase fw-semibold mb-1">
                                            Closed Tickets
                                        </div>
                                        <div class="fs-4 fw-bold"><?php echo (int)$closedTicketsCount; ?></div>
                                        <div class="text-muted small mt-1">
                                            Finished tickets
                                        </div>
                                    </div>
                                </div>
                                <a href="<?php echo $isAdmin ? 'tickets?view=closed' : 'usertickets?view=closed'; ?>" class="stretched-link" aria-label="View closed tickets"></a>
                            </div>
                        </div>

                        <?php if ($isAdmin) : ?>
                            <div class="col-12 col-md-3">
                                <div class="card summary-card border-0 shadow-sm h-100 position-relative">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="summary-icon-wrap bg-info-subtle text-info me-3">
                                            <i class="bi bi-calendar-day"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted text-uppercase fw-semibold mb-1">
                                                Today&apos;s Tickets
                                            </div>
                                            <div class="fs-4 fw-bold"><?php echo (int)$todayTicketsCount; ?></div>
                                            <div class="text-muted small mt-1">
                                                Created since midnight
                                            </div>
                                        </div>
                                    </div>
                                    <a href="tickets?view=today" class="stretched-link" aria-label="View today&apos;s tickets"></a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Ticket Completion + Priority Progress -->
                <?php
                $priorityTotalCount = (int)$lowPriorityCount + (int)$mediumPriorityCount + (int)$highPriorityCount + (int)$urgentPriorityCount;
                $lowPriorityPct = $priorityTotalCount > 0 ? (int)round(((int)$lowPriorityCount / $priorityTotalCount) * 100) : 0;
                $mediumPriorityPct = $priorityTotalCount > 0 ? (int)round(((int)$mediumPriorityCount / $priorityTotalCount) * 100) : 0;
                $highPriorityPct = $priorityTotalCount > 0 ? (int)round(((int)$highPriorityCount / $priorityTotalCount) * 100) : 0;
                $urgentPriorityPct = $priorityTotalCount > 0 ? (int)round(((int)$urgentPriorityCount / $priorityTotalCount) * 100) : 0;
                ?>
                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Resolved vs Unresolved Tickets</h2>
                                    <p class="text-muted small mb-0">
                                        Resolution ratio based on your current ticket count.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 py-3">
                                    <div style="position: relative; height: 320px;">
                                        <canvas id="ticketStatusChart" aria-label="Ticket status chart" role="img"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-xl-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Priority Progress</h2>
                                    <p class="text-muted small mb-0">
                                        Ticket distribution by priority level.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 py-3">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                                            <span>Low</span>
                                            <span><?php echo (int)$lowPriorityCount; ?> (<?php echo (int)$lowPriorityPct; ?>%)</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo (int)$lowPriorityPct; ?>%;" aria-valuenow="<?php echo (int)$lowPriorityPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                                            <span>Medium</span>
                                            <span><?php echo (int)$mediumPriorityCount; ?> (<?php echo (int)$mediumPriorityPct; ?>%)</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo (int)$mediumPriorityPct; ?>%;" aria-valuenow="<?php echo (int)$mediumPriorityPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                                            <span>High</span>
                                            <span><?php echo (int)$highPriorityCount; ?> (<?php echo (int)$highPriorityPct; ?>%)</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo (int)$highPriorityPct; ?>%;" aria-valuenow="<?php echo (int)$highPriorityPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="d-flex justify-content-between small fw-semibold mb-1">
                                            <span>Urgent</span>
                                            <span><?php echo (int)$urgentPriorityCount; ?> (<?php echo (int)$urgentPriorityPct; ?>%)</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo (int)$urgentPriorityPct; ?>%;" aria-valuenow="<?php echo (int)$urgentPriorityPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Recent Activity Table -->
                <section class="mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 px-3 px-md-4">
                            <div>
                                <h2 class="h6 mb-0 fw-semibold">Recent Activity</h2>
                                <p class="text-muted small mb-0">
                                    Latest changes to your work cards.
                                </p>
                            </div>
                            <button class="btn btn-sm btn-outline-primary mt-2 mt-md-0">
                                View all
                            </button>
                        </div>
                        <div class="card-body px-2 px-md-4 py-3">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="small text-uppercase text-muted">Work Card</th>
                                            <th scope="col" class="small text-uppercase text-muted">Type</th>
                                            <th scope="col" class="small text-uppercase text-muted">Status</th>
                                            <th scope="col" class="small text-uppercase text-muted">Updated</th>
                                            <th scope="col" class="small text-uppercase text-muted text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentAssignedBody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted small py-4">
                                                Loading assigned tasks...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- USERS SECTION (Add User form) -->
                <section class="mb-4 d-none" data-section="users">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New User</h2>
                                    <p class="text-muted small mb-0">
                                        Create a user account and assign them to a department.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <!-- Pure front-end form for now -->
                                    <form class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userName">
                                                Full Name
                                            </label>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                id="userName"
                                                placeholder="Jane Doe"
                                                required
                                            />
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userEmail">
                                                Email
                                            </label>
                                            <input
                                                type="email"
                                                class="form-control form-control-sm"
                                                id="userEmail"
                                                placeholder="jane.doe@texol.com"
                                                required
                                            />
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userDepartment">
                                                Department
                                            </label>
                                            <select
                                                class="form-select form-select-sm"
                                                id="userDepartment"
                                                required
                                            >
                                                <option value="" selected disabled>Select department</option>
                                                <option value="Maintenance">Maintenance</option>
                                                <option value="Production">Production</option>
                                                <option value="Quality">Quality</option>
                                                <option value="Engineering">Engineering</option>
                                                <option value="HSE">HSE</option>
                                                <option value="Admin">Admin</option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userRole">
                                                Role
                                            </label>
                                            <select
                                                class="form-select form-select-sm"
                                                id="userRole"
                                                required
                                            >
                                                <option value="" selected disabled>Select role</option>
                                                <option value="Operator">Operator</option>
                                                <option value="Supervisor">Supervisor</option>
                                                <option value="Engineer">Engineer</option>
                                                <option value="Manager">Manager</option>
                                                <option value="Admin">Admin</option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userPassword">
                                                Temporary Password
                                            </label>
                                            <input
                                                type="password"
                                                class="form-control form-control-sm"
                                                id="userPassword"
                                                placeholder="Generate or set a temp password"
                                                required
                                            />
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userStatus">
                                                Status
                                            </label>
                                            <select
                                                class="form-select form-select-sm"
                                                id="userStatus"
                                                required
                                            >
                                                <option value="active" selected>Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2">
                                                Reset
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                Save User
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-3 p-md-4">
                                    <h3 class="h6 fw-semibold mb-2">User management tips</h3>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li class="mb-1">Use work email addresses for all users.</li>
                                        <li class="mb-1">Department controls which work cards they see.</li>
                                        <li class="mb-1">Use roles to differentiate access in your backend logic.</li>
                                        <li>Mark users inactive instead of deleting to preserve history.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
            <!-- /Main Dashboard Content -->
        </div>
        <!-- /Main Content Wrapper -->
    </div>

    <!-- Bootstrap JS Bundle CDN -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

    <!-- App JS (sidebar toggle, nav active) -->
    <script src="app.js"></script>

    <script>
        (function initTicketStatusChart() {
            const canvas = document.getElementById('ticketStatusChart');
            if (!canvas || typeof Chart === 'undefined') return;
            if (typeof ChartDataLabels !== 'undefined') {
                Chart.register(ChartDataLabels);
            }

            const totalTickets = <?php echo (int)($isAdmin ? $totalWorkCards : $assignedTicketsCount); ?>;
            const resolvedTickets = Math.max(0, <?php echo (int)$closedTicketsCount; ?>);
            const unresolvedTickets = Math.max(0, totalTickets - resolvedTickets);

            new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: ['Resolved', 'Unresolved'],
                    datasets: [{
                        label: 'Tickets',
                        data: [resolvedTickets, unresolvedTickets],
                        backgroundColor: [
                            'rgba(0, 77, 152, 0.85)',
                            'rgba(255, 140, 0, 0.85)'
                        ],
                        borderColor: [
                            'rgba(0, 77, 152, 1)',
                            'rgba(255, 140, 0, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed} ticket(s)`;
                                }
                            }
                        },
                        datalabels: {
                            color: '#ffffff',
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                        formatter: function(value, context) {
    const total = (context.dataset.data || []).reduce((sum, n) => sum + n, 0);

    const pct = total > 0 ? ((value / total) * 100).toFixed(2) : "0.00";

    return `${context.chart.data.labels[context.dataIndex]}\n${value.toFixed(2)} (${pct}%)`;
}
                        }
                    }
                }
            });
        })();
    </script>

    <!-- Supabase client for Recent Activity (assigned to me) -->
    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = createClient(supabaseUrl, supabaseKey);

        const currentUserEmail = <?php echo json_encode($_SESSION['user_email'] ?? ''); ?>;
        const currentUserUuid = <?php echo json_encode($_SESSION['user_id'] ?? ''); ?>;
        const tbody = document.getElementById('recentAssignedBody');

        function renderPriorityBadge(priority) {
            const prio = (priority || '').toLowerCase();
            let prioClass = 'bg-secondary-subtle text-secondary';
            if (prio === 'low') prioClass = 'bg-info-subtle text-info';
            else if (prio === 'medium') prioClass = 'bg-warning-subtle text-warning';
            else if (prio === 'high') prioClass = 'bg-danger-subtle text-danger';
            return `<span class="badge rounded-pill ${prioClass} small">${priority || 'N/A'}</span>`;
        }

        function renderStatusBadge(status) {
            const val = (status || '').toLowerCase();
            let cls = 'bg-secondary-subtle text-secondary';
            if (val === 'open') cls = 'bg-primary-subtle text-primary';
            else if (val === 'in progress') cls = 'bg-warning-subtle text-warning';
            else if (val === 'resolved') cls = 'bg-success-subtle text-success';
            else if (val === 'pending review') cls = 'bg-warning-subtle text-warning';
            return `<span class="badge rounded-pill ${cls} small">${status || 'Unknown'}</span>`;
        }

        function escapeHtml(str) {
            return (str || '')
                .toString()
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        async function loadRecentAssigned() {
            if (!tbody) return;
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted small py-4">
                        Loading assigned tasks...
                    </td>
                </tr>`;

            try {
                if (!currentUserEmail) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-danger py-4">
                                Missing user session. Please log in again.
                            </td>
                        </tr>`;
                    return;
                }

                // Fetch assigned tickets (using inner join)
                const { data: assignedData, error: assignedError } = await supabase
                    .from('tickets')
                    .select('id,title,status,priority,created_at,ticket_assignees!inner(technician_email)')
                    .eq('ticket_assignees.technician_email', currentUserEmail)
                    .order('created_at', { ascending: false })
                    .limit(50);

                // Build OR filter for requested_by (email and/or UUID) and requester
                let orFilter = `requester.eq.${currentUserEmail},requested_by.eq.${currentUserEmail}`;
                if (currentUserUuid) {
                    orFilter += `,requested_by.eq.${currentUserUuid}`;
                }

                // Fetch requested tickets
                const { data: requestedData, error: requestedError } = await supabase
                    .from('tickets')
                    .select('id,title,status,priority,created_at,requester,requested_by')
                    .or(orFilter)
                    .order('created_at', { ascending: false })
                    .limit(50);

                if (assignedError || requestedError) {
                    console.error('Assigned error:', assignedError);
                    console.error('Requested error:', requestedError);
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-danger py-4">
                                Failed to load assigned tasks: ${escapeHtml((assignedError || requestedError)?.message || 'Unknown error')}
                            </td>
                        </tr>`;
                    return;
                }

                // Merge and deduplicate by ID
                const merged = [];
                const seenIds = new Set();
                
                [...(assignedData || []), ...(requestedData || [])].forEach(ticket => {
                    if (!seenIds.has(ticket.id)) {
                        seenIds.add(ticket.id);
                        merged.push(ticket);
                    }
                });

                // Sort by created_at desc
                merged.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                const data = merged.slice(0, 50);

                if (!data || data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-muted small py-4">
                                No tasks assigned to you yet.
                            </td>
                        </tr>`;
                    return;
                }

                tbody.innerHTML = '';
                data.forEach((t) => {
                    const title = escapeHtml(t.title || 'Untitled');
                    const id = escapeHtml(t.id || '');
                    const status = t.status || '';
                    const priority = t.priority || '';
                    const whenSrc = t.created_at || null;
                    let when = '—';
                    if (whenSrc) {
                        const d = new Date(whenSrc);
                        when = d.toLocaleString();
                    }

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <div class="fw-semibold small text-truncate" style="max-width: 280px;">${title}</div>
                            <div class="text-muted small">ID: ${id}</div>
                        </td>
                        <td>${renderPriorityBadge(priority)}</td>
                        <td>${renderStatusBadge(status)}</td>
                        <td class="small text-muted">${escapeHtml(when)}</td>
                        <td class="text-end">
                            <form method="post" action="  tickets" class="d-inline">
                                <input type="hidden" name="open_notes_modal" value="1" />
                                <input type="hidden" name="notes_ticket_id" value="${id}" />
                                <input type="hidden" name="notes_ticket_title" value="${title}" />
                                <input type="hidden" name="notes_ticket_status" value="${escapeHtml(status)}" />
                                <input type="hidden" name="notes_ticket_priority" value="${escapeHtml(priority)}" />
                                <input type="hidden" name="notes_ticket_created_at" value="${escapeHtml(t.created_at || '')}" />
                                <input type="hidden" name="notes_ticket_requested_by" value="${escapeHtml(t.requested_by || '')}" />
                                <button class="btn btn-sm btn-outline-secondary me-1" title="View notes">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </form>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo $isAdmin ? 'admintickets' : 'usertickets'; ?>" title="Open in Tickets">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                console.error(err);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center small text-danger py-4">
                            Unexpected error loading assigned tasks.
                        </td>
                    </tr>`;
            }
        }

        loadRecentAssigned();
    </script>
</body>
</html>