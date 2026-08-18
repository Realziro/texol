<?php
session_start();
require_once __DIR__ . '/../config.php';

if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// This page is for non-admin users
if (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin') {
    header('Location:   tickets');
    exit;
}

$view = strtolower(trim($_GET['view'] ?? 'assigned'));
$allowedViews = ['assigned', 'overdue', 'today', 'open', 'inprogress', 'closed'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'assigned';
}

$viewTitles = [
    'assigned' => 'Assigned Tickets',
    'overdue' => 'Overdue Tickets',
    'today' => "Today's Tickets",
    'open' => 'Open Tickets',
    'inprogress' => 'In Progress Tickets',
    'closed' => 'Closed Tickets',
];
$pageTitle = $viewTitles[$view] ?? 'User Tickets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - <?php echo htmlspecialchars($pageTitle); ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    />
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    />
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'tickets';
        include __DIR__ . '/partials/sidebar.php';
        ?>

        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <a class="navbar-brand fw-semibold d-none d-sm-inline d-flex align-items-center gap-2" href="#">
                    <span id="pageTitle"><?php echo htmlspecialchars($pageTitle); ?></span>
                </a>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="text-muted small mb-0">Tickets assigned to you, filtered by the selected dashboard view.</p>
                </section>

                <section class="mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="  usertickets?view=assigned" class="btn btn-sm <?php echo $view === 'assigned' ? 'btn-primary' : 'btn-outline-primary'; ?>">Assigned</a>
                        <a href="  usertickets?view=overdue" class="btn btn-sm <?php echo $view === 'overdue' ? 'btn-primary' : 'btn-outline-primary'; ?>">Overdue</a>
                        <a href="  usertickets?view=today" class="btn btn-sm <?php echo $view === 'today' ? 'btn-primary' : 'btn-outline-primary'; ?>">Today</a>
                        <a href="  usertickets?view=open" class="btn btn-sm <?php echo $view === 'open' ? 'btn-primary' : 'btn-outline-primary'; ?>">Open</a>
                        <a href="  usertickets?view=inprogress" class="btn btn-sm <?php echo $view === 'inprogress' ? 'btn-primary' : 'btn-outline-primary'; ?>">In Progress</a>
                        <a href="  usertickets?view=closed" class="btn btn-sm <?php echo $view === 'closed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Closed</a>
                    </div>
                </section>

                <section class="mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body px-2 px-md-3 py-3">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small text-uppercase text-muted">Title</th>
                                            <th class="small text-uppercase text-muted">Requested By</th>
                                            <th class="small text-uppercase text-muted">Dept</th>
                                            <th class="small text-uppercase text-muted">Priority</th>
                                            <th class="small text-uppercase text-muted">Due Date</th>
                                            <th class="small text-uppercase text-muted text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userTicketsTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted small py-3">Loading tickets...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="app.js"></script>
    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = createClient(supabaseUrl, supabaseKey);
        const currentUserEmail = <?php echo json_encode($_SESSION['user_email'] ?? ''); ?>;
        const currentView = <?php echo json_encode($view); ?>;
        const tbody = document.getElementById('userTicketsTableBody');

        function esc(str) {
            return (str || '').toString()
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        async function resolveRequesterNames(tickets) {
            const emails = Array.from(new Set((tickets || []).map((t) => (t.requested_by || '').trim()).filter(Boolean)));
            if (emails.length === 0) return {};
            const { data } = await supabase.from('users').select('email,full_name').in('email', emails);
            const map = {};
            (data || []).forEach((u) => { if (u.email) map[u.email] = (u.full_name || '').trim(); });
            return map;
        }

        function statusBadge(status) {
            const val = (status || '').toLowerCase();
            let cls = 'bg-secondary-subtle text-secondary';
            if (val === 'open') cls = 'bg-danger-subtle text-danger';
            else if (val === 'in progress') cls = 'bg-warning-subtle text-warning';
            else if (val === 'resolved') cls = 'bg-success-subtle text-success';
            else if (val === 'closed') cls = 'bg-secondary-subtle text-secondary';
            return `<span class="badge rounded-pill ${cls} small">${esc(status || '')}</span>`;
        }

        function priorityBadge(priority) {
            const val = (priority || '').toLowerCase();
            let cls = 'bg-secondary-subtle text-secondary';
            if (val === 'low') cls = 'bg-success-subtle text-success';
            else if (val === 'medium') cls = 'bg-info-subtle text-info';
            else if (val === 'high') cls = 'bg-warning-subtle text-warning';
            else if (val === 'critical') cls = 'bg-danger-subtle text-danger';
            return `<span class="badge rounded-pill ${cls} small">${esc(priority || '')}</span>`;
        }

        async function loadTickets() {
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted small py-3">Loading tickets...</td></tr>';
            try {
                let query = supabase
                    .from('tickets')
                    .select('id,title,description,department,priority,status,due_date,created_at,requested_by,ticket_assignees!inner(technician_email)')
                    .eq('ticket_assignees.technician_email', currentUserEmail);

                if (currentView === 'overdue') {
                    query = query.lt('due_date', new Date().toISOString()).neq('status', 'Closed');
                } else if (currentView === 'today') {
                    const d = new Date();
                    const start = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0);
                    query = query.gte('created_at', start.toISOString());
                } else if (currentView === 'open') {
                    query = query.eq('status', 'Open');
                } else if (currentView === 'inprogress') {
                    query = query.eq('status', 'In Progress');
                } else if (currentView === 'closed') {
                    query = query.eq('status', 'Closed');
                }

                const { data, error } = await query.order('created_at', { ascending: false }).limit(200);
                if (error) throw error;

                if (!data || data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted small py-3">No tickets found for this view.</td></tr>';
                    return;
                }

                const requesterMap = await resolveRequesterNames(data);
                tbody.innerHTML = '';
                data.forEach((t) => {
                    const tr = document.createElement('tr');
                    const due = t.due_date ? new Date(t.due_date) : null;
                    const isOverdue = due && !isNaN(due.getTime()) && due.getTime() < Date.now() && (t.status || '').toLowerCase() !== 'closed';
                    if (isOverdue) tr.classList.add('table-danger');
                    if ((t.status || '').toLowerCase() === 'closed') tr.classList.add('table-secondary');
                    tr.innerHTML = `
                        <td><div class="fw-semibold small">${esc(t.title || '')}</div></td>
                        <td class="small">${esc(requesterMap[(t.requested_by || '').trim()] || (t.requested_by || ''))}</td>
                        <td class="small">${esc(t.department || '')}</td>
                        <td class="small">${priorityBadge(t.priority)}</td>
                        <td class="small">${due ? esc(due.toLocaleString()) : '—'}</td>
                        <td class="text-end">${statusBadge(t.status)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                console.error(e);
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger small py-3">Failed to load tickets.</td></tr>`;
            }
        }

        loadTickets();
    </script>
</body>
</html>

