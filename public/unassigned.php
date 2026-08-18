<?php
session_start();

// Load env/config (Supabase keys, etc.)
require_once __DIR__ . '/../config.php';

// Protect page: only allow access when logged in
if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Admin only
if (! isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header('Location:   index');
    exit;
}

// Redirect to tickets page using view filter
header('Location:   tickets?view=unassigned');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - Unassigned Tickets</title>

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
        $activeMenu = 'unassigned';
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
                    <span id="pageTitle">Unassigned Tickets</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Unassigned Tickets</h1>
                    <p class="text-muted small mb-0">Tickets that currently have no technicians assigned.</p>
                </section>

                <section class="mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h6 mb-1 fw-semibold">All Unassigned Tickets</h2>
                                <p class="text-muted small mb-0">Click “Open in Tickets” to assign technicians and manage the ticket.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-secondary" href="  tickets">
                                    <i class="bi bi-arrow-right me-1"></i>Open Tickets Page
                                </a>
                                <button class="btn btn-sm btn-outline-primary" id="refreshUnassignedBtn" type="button">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body px-2 px-md-3 py-3">
                            <div id="unassignedAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small text-uppercase text-muted">Title</th>
                                            <th class="small text-uppercase text-muted">Requested By</th>
                                            <th class="small text-uppercase text-muted">Dept</th>
                                            <th class="small text-uppercase text-muted">Priority</th>
                                            <th class="small text-uppercase text-muted text-end">Status</th>
                                            <th class="small text-uppercase text-muted text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="unassignedTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center small text-muted py-3">
                                                Loading unassigned tickets...
                                            </td>
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

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>
    <script src="app.js"></script>

    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = createClient(supabaseUrl, supabaseKey);

        const tbody = document.getElementById('unassignedTableBody');
        const alertEl = document.getElementById('unassignedAlert');
        const refreshBtn = document.getElementById('refreshUnassignedBtn');

        function showAlert(type, message) {
            if (!alertEl) return;
            alertEl.className = `alert alert-${type} py-2 px-3 mb-3`;
            alertEl.textContent = message;
            alertEl.classList.remove('d-none');
        }

        function hideAlert() {
            if (!alertEl) return;
            alertEl.classList.add('d-none');
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

        async function resolveRequesterNames(tickets) {
            const emails = Array.from(new Set((tickets || [])
                .map((t) => (t.requested_by || '').trim())
                .filter(Boolean)));
            if (emails.length === 0) return {};

            const { data, error } = await supabase
                .from('users')
                .select('email, full_name')
                .in('email', emails);

            if (error) {
                console.error('Failed to resolve requester names', error);
                return {};
            }

            const map = {};
            (data || []).forEach((u) => {
                const key = (u.email || '').trim();
                if (!key) return;
                map[key] = (u.full_name || '').trim();
            });
            return map;
        }

        function renderPriorityBadge(priority) {
            const prio = (priority || '').toLowerCase();
            let prioClass = 'bg-secondary-subtle text-secondary';
            if (prio === 'low') prioClass = 'bg-success-subtle text-success';
            else if (prio === 'medium') prioClass = 'bg-info-subtle text-info';
            else if (prio === 'high') prioClass = 'bg-warning-subtle text-warning';
            else if (prio === 'critical') prioClass = 'bg-danger-subtle text-danger';
            return `<span class="badge rounded-pill ${prioClass} small">${escapeHtml(priority || '')}</span>`;
        }

        function renderStatusBadge(status) {
            const val = (status || '').toLowerCase();
            let statusClass = 'bg-secondary-subtle text-secondary';
            if (val === 'open') statusClass = 'bg-danger-subtle text-danger';
            else if (val === 'in progress') statusClass = 'bg-warning-subtle text-warning';
            else if (val === 'resolved') statusClass = 'bg-success-subtle text-success';
            else if (val === 'closed') statusClass = 'bg-secondary-subtle text-secondary';
            return `<span class="badge rounded-pill ${statusClass} small">${escapeHtml(status || '')}</span>`;
        }

        async function loadUnassigned() {
            if (!tbody) return;
            hideAlert();
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center small text-muted py-3">Loading unassigned tickets...</td>
                </tr>`;

            try {
                // Step 1: get all ticket_ids that ARE assigned in ticket_assignees
                const { data: assignedRows, error: assignedError } = await supabase
                    .from('ticket_assignees')
                    .select('ticket_id');

                if (assignedError) {
                    console.error(assignedError);
                    showAlert('danger', assignedError.message || 'Failed to load assignments.');
                    return;
                }

                const assignedIds = new Set(
                    (assignedRows || [])
                        .map((r) => r.ticket_id)
                        .filter(Boolean)
                );

                // Step 2: get tickets and filter by id NOT IN assignedIds
                const { data: tickets, error: ticketsError } = await supabase
                    .from('tickets')
                    .select('id,title,description,department,priority,status,requested_by,created_at')
                    .order('created_at', { ascending: false })
                    .limit(500);

                if (ticketsError) {
                    console.error(ticketsError);
                    showAlert('danger', ticketsError.message || 'Failed to load tickets.');
                    return;
                }

                const trulyUnassigned = (tickets || []).filter((t) => t.id && !assignedIds.has(t.id));

                if (!trulyUnassigned || trulyUnassigned.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center small text-muted py-3">No unassigned tickets 🎉</td>
                        </tr>`;
                    return;
                }

                const requesterNameMap = await resolveRequesterNames(trulyUnassigned);
                tbody.innerHTML = '';

                trulyUnassigned.forEach((t) => {
                    const requesterEmail = (t.requested_by || '').trim();
                    const requesterName = requesterNameMap[requesterEmail] || '';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <div class="fw-semibold small">${escapeHtml(t.title || '')}</div>
                            <div class="text-muted small">${escapeHtml((t.description || '').substring(0, 60))}${(t.description || '').length > 60 ? '…' : ''}</div>
                        </td>
                        <td class="small">${escapeHtml(requesterName || requesterEmail || '')}</td>
                        <td class="small">${escapeHtml(t.department || '')}</td>
                        <td class="small">${renderPriorityBadge(t.priority)}</td>
                        <td class="text-end">${renderStatusBadge(t.status)}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="  tickets" title="Open in Tickets">
                                Open in Tickets
                            </a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                console.error(err);
                showAlert('danger', 'Unexpected error loading unassigned tickets.');
            }
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadUnassigned);
        }

        loadUnassigned();
    </script>
</body>
</html>

