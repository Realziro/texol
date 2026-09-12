<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: login');
    exit;
}

$branches = [];
$checklists = [];
$userEmail = $_SESSION['user_email'] ?? '';
$userName = $_SESSION['user_name'] ?? $userEmail;
$userDepartment = $_SESSION['user_department'] ?? $_SESSION['department'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$userId = $_SESSION['user_id'] ?? '';

if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;
    $headers = [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Accept: application/json',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/branches?select=id,name,total_cameras&order=name.asc',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $allBranches = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);
    
    // Filter branches that start with "sta" (case-insensitive)
    $branches = array_values(array_filter($allBranches, function($branch) {
        return stripos($branch['name'], 'sta') === 0;
    }));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/cctv_checklists?select=*&order=checklist_date.desc,created_at.desc',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $checklists = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);

    if (empty($userId) && !empty($userEmail)) {
        $userQuery = http_build_query([
            'select' => 'id',
            'email' => 'eq.' . $userEmail,
            'limit' => 1,
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $userQuery,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $userData = json_decode(curl_exec($ch), true) ?: [];
        $userId = $userData[0]['id'] ?? '';
        curl_close($ch);
    }

    $checklists = array_values(array_filter($checklists, function ($checklist) use ($userId) {
        if (empty($userId) || !is_array($checklist)) {
            return false;
        }

        // Only show sta checklists
        if (($checklist['branch_type'] ?? '') !== 'sta') {
            return false;
        }

        if (($checklist['created_by'] ?? '') === $userId) {
            return true;
        }

        $approverIds = array_filter(array_map('trim', explode(',', $checklist['shared_with'] ?? '')));
        return in_array($userId, $approverIds, true);
    }));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Surveillance Checklist (STA) - THI Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style id="cctvPrintStyles">
        .cctv-printout {
            max-width: 950px;
            margin: 0 auto;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
        }

        .cctv-printout table {
            width: 100%;
            border-collapse: collapse;
        }

        .cctv-printout td,
        .cctv-printout th {
            border: 1px solid #000;
            padding: 6px 10px;
            vertical-align: middle;
        }

        .cctv-printout .company-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            letter-spacing: .5px;
        }

        .cctv-printout .logo-cell {
            width: 22%;
            text-align: center;
        }

        .cctv-printout .logo-cell img {
            max-width: 120px;
            max-height: 60px;
        }

        .cctv-printout .logo-cell small {
            display: block;
            margin-top: 2px;
            font-size: 11px;
        }

        .cctv-printout .form-title-cell {
            width: 46%;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            color: #C0392B;
        }

        .cctv-printout .doc-info-cell {
            width: 32%;
            font-size: 12px;
            text-align: center;
        }

        .cctv-printout .page-row {
            border-top: 1px solid #000;
            margin-top: 4px;
            padding-top: 4px;
        }

        .cctv-printout .content {
            padding: 16px 22px 26px 22px;
        }

        .cctv-printout .doc-heading {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 14px;
            letter-spacing: .3px;
        }

        .cctv-printout .purpose {
            font-size: 13px;
            margin-bottom: 20px;
        }

        .cctv-printout .section-heading {
            font-weight: bold;
            text-decoration: underline;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .cctv-printout table.inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 26px;
            font-size: 12.5px;
        }

        .cctv-printout table.inventory-table th,
        .cctv-printout table.inventory-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }

        .cctv-printout table.inventory-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .cctv-printout table.inventory-table td.center {
            text-align: center;
        }

        .cctv-printout .approvals-heading {
            font-weight: bold;
            text-decoration: underline;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .cctv-printout table.approvals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .cctv-printout table.approvals-table th,
        .cctv-printout table.approvals-table td {
            border: 1px solid #000;
            padding: 10px 10px;
            text-align: left;
        }

        .cctv-printout table.approvals-table th {
            background: #f0f0f0;
            font-weight: bold;
        }

        .cctv-printout .footer-note {
            font-style: italic;
            font-size: 11px;
            margin-top: 20px;
        }

        @media print {
            .cctv-printout {
                max-width: none;
                border: none;
            }
        }

        .cctv-table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6c757d;
            background: #f8f9fa;
            border-bottom-width: 1px
        }

        .cctv-table tbody td {
            padding-top: .7rem;
            padding-bottom: .7rem
        }

        .cctv-status {
            font-size: .68rem;
            font-weight: 600
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            padding: .25rem .5rem
        }

        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
            outline: 0
        }

        .cctv-table-wrap {
            overflow: visible !important;
        }

        .cctv-table-wrap .dropdown.show {
            position: relative;
            z-index: 1080;
        }

        .cctv-table-wrap .dropdown-menu {
            z-index: 1090;
        }

        @media (max-width: 767.98px) {
            .cctv-table-wrap {
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
        }
    </style>
</head>

<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php $activeMenu = 'cctv_checklist_sta';
        include __DIR__ . '/partials/sidebar.php'; ?>
        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button"
                    aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                <a class="navbar-brand fw-semibold d-none d-sm-inline" href="#"><span id="pageTitle">CCTV Surveillance
                        Checklist (STA CAFE & XPRESS)</span></a>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">CCTV Surveillance Checklist (STA CAFE & XPRESS)</h1>
                    <p class="text-muted small mb-0">Create, view and print daily CCTV surveillance reports for STA Cafe & Xpress.</p>
                </section>
                <section class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0 fw-semibold">Saved Checklists</h2>
                        <button class="btn btn-primary" id="openCctvFormBtn" type="button">
                            <i class="bi bi-plus-circle me-1"></i>New Checklist
                        </button>
                    </div>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive cctv-table-wrap">
                                <table class="table table-sm table-hover align-middle cctv-table" id="cctvTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Stations</th>
                                            <th>Total Cameras</th>
                                            <th>Functioning</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($checklists)): ?>
                                                <tr>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td><span class="badge bg-secondary">-</span></td>
                                                    <td class="text-end">-</td>
                                                </tr>
                                            <?php else:
                                                foreach ($checklists as $checklist): ?>
                                                    <tr>
                                                       <td><?php echo htmlspecialchars(!empty($checklist['checklist_date']) ? date('d-m-Y', strtotime($checklist['checklist_date'])) : '-'); ?>
</td>
                                                        <td><?php echo htmlspecialchars($checklist['station_count'] ?? '-'); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($checklist['total_cameras'] ?? '-'); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($checklist['functioning_cameras'] ?? '-'); ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status = strtolower($checklist['status'] ?? 'pending');
                                                            $statusClass = match ($status) {
                                                                'approved' => 'bg-success',
                                                                'checked' => 'bg-info text-dark',
                                                                'rejected' => 'bg-danger',
                                                                default => 'bg-warning text-dark',
                                                            };
                                                            ?>
                                                            <span
                                                                class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                                    title="Form actions" aria-label="Form actions">
                                                                    <i class="bi bi-three-dots-vertical"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <button class="dropdown-item" type="button"
                                                                            onclick="viewCctv(<?php echo htmlspecialchars(json_encode($checklist), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-eye me-2"></i>View
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item" type="button"
                                                                            onclick="printCctv(<?php echo htmlspecialchars(json_encode($checklist), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-printer me-2"></i>Print
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item" type="button"
                                                                            onclick="approveCctv(<?php echo htmlspecialchars(json_encode($checklist), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-check-circle me-2"></i>Approve /
                                                                            Check
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item text-danger" type="button"
                                                                            onclick="rejectCctv(<?php echo htmlspecialchars(json_encode($checklist), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-x-circle me-2"></i>Reject
                                                                        </button>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach;
                                            endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
    
    <!-- New Checklist Modal -->
    <div class="modal fade" id="newCctvModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New CCTV Checklist (STA)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="cctvAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                    <form id="cctvForm" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Checklist Date</label>
                            <input class="form-control form-control-sm" id="checklistDate" type="date"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Share With (For Approval)</label>
                            <small class="text-muted d-block mb-1">Select up to two users in order: first is
                                Checked By, second is Approved By.</small>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm" id="sharedWithInput"
                                    placeholder="Search users..." autocomplete="off">
                                <div id="sharedWithDropdown" class="dropdown-menu w-100"
                                    style="position:absolute;z-index:1000;max-height:200px;overflow-y:auto;">
                                </div>
                            </div>
                            <div id="selectedUsers" class="mt-1"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Station Camera Status</label>
                            <div id="stationCameraStatus" class="border rounded p-3" style="max-height: 600px; overflow-y: auto;">
                                <p class="text-muted small text-center py-3">Loading stations...</p>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="resetCctvFormBtn">Reset</button>
                    <button type="submit" class="btn btn-primary" id="saveCctvBtn">
                        <span class="spinner-border spinner-border-sm d-none"></span>
                        <span class="btn-label">Save Checklist</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="cctvModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">CCTV Surveillance Checklist (STA)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="cctvModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="modalEditCctv"
                        title="Edit checklist" aria-label="Edit checklist"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-outline-danger" id="modalDeleteCctv"
                        title="Delete checklist" aria-label="Delete checklist"><i class="bi bi-trash"></i></button>
                    <button type="button" class="btn btn-outline-success" id="modalDownloadCctv"
                        title="Download checklist" aria-label="Download checklist"><i class="bi bi-download"></i></button>
                    <button type="button" class="btn btn-primary" id="modalPrintCctv" title="Print checklist"
                        aria-label="Print checklist"><i class="bi bi-printer"></i></button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>
    <script src="app.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        $(function () {
            $('#cctvTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                order: [],
                language: {
                    search: '_INPUT_',
                    searchPlaceholder: 'Search checklists...'
                },
                columnDefs: [
                    { orderable: false, targets: 5 }
                ],
                autoWidth: false
            });
        });
    </script>
    <script type="module">
        import {
            createClient
        } from 'https://esm.sh/@supabase/supabase-js@2';
        const supabase = createClient(<?php echo json_encode(defined('SUPABASE_URL') ? SUPABASE_URL : ''); ?>,
            <?php echo json_encode(defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : ''); ?>);
        let activeUserId = <?php echo json_encode($userId); ?>;
        const userEmail = <?php echo json_encode($userEmail); ?>;
        let reporterRole = <?php echo json_encode($userRole); ?>;
        let reporterProfile = {
            role: reporterRole,
            signature: ''
        };
        let allUsers = [];
        let selectedApprovers = [];
        let branchesData = <?php echo json_encode($branches); ?>;
        const form = document.getElementById('cctvForm');
        const alertBox = document.getElementById('cctvAlert');
        let selectedCctv = null;
        const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));

        function showAlert(type, message) {
            showFeedbackModal(message, type);
        }

        function showFeedbackModal(message, type = 'info') {
            let modal = document.getElementById('feedbackModal');
            if (!modal) {
                document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="feedbackModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="feedbackModalTitle">Message</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="feedbackModalMessage"></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button></div></div></div></div>`);
                modal = document.getElementById('feedbackModal');
            }
            document.getElementById('feedbackModalTitle').textContent = type === 'danger' ? 'Error' : type === 'success' ? 'Success' : 'Message';
            document.getElementById('feedbackModalMessage').textContent = message;
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }

        window.alert = message => showFeedbackModal(message);

        async function loadUsers() {
            const { data, error } = await supabase.from('users').select('id, email, full_name').order('full_name', { ascending: true });
            if (!error) allUsers = data || [];
        }

        loadUsers();

        const sharedWithInput = document.getElementById('sharedWithInput');
        const sharedWithDropdown = document.getElementById('sharedWithDropdown');
        const selectedUsers = document.getElementById('selectedUsers');

        function renderSelectedApprovers() {
            selectedUsers.innerHTML = selectedApprovers.map((user, index) => `<span class="badge bg-primary me-1 mb-1">${index + 1}. ${esc(user.full_name || user.email)} <button type="button" class="btn-close btn-close-white ms-1" data-user-id="${esc(user.id)}"></button></span>`).join('');
            selectedUsers.querySelectorAll('.btn-close').forEach(button => button.addEventListener('click', () => {
                selectedApprovers = selectedApprovers.filter(user => user.id !== button.dataset.userId);
                renderSelectedApprovers();
            }));
        }

        sharedWithInput.addEventListener('input', () => {
            const term = sharedWithInput.value.trim().toLowerCase();
            sharedWithDropdown.innerHTML = '';
            if (term.length < 2 || selectedApprovers.length >= 2) {
                sharedWithDropdown.classList.remove('show');
                return;
            }
            allUsers.filter(user => user.id !== activeUserId && !selectedApprovers.some(selected => selected.id === user.id) && `${user.full_name || ''} ${user.email || ''}`.toLowerCase().includes(term)).forEach(user => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'dropdown-item';
                option.textContent = `${user.full_name || user.email} (${user.email})`;
                option.addEventListener('click', () => {
                    selectedApprovers.push(user);
                    sharedWithInput.value = '';
                    sharedWithDropdown.classList.remove('show');
                    renderSelectedApprovers();
                });
                sharedWithDropdown.appendChild(option);
            });
            sharedWithDropdown.classList.toggle('show', sharedWithDropdown.children.length > 0);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (sharedWithDropdown && !sharedWithDropdown.contains(e.target) && e.target !== sharedWithInput) {
                sharedWithDropdown.classList.remove('show');
            }
        });

        async function loadReporterRole() {
            const query = supabase.from('users').select('id, role, signature, full_name').limit(1);
            const {
                data,
                error
            } = activeUserId
                    ?
                    await query.eq('id', activeUserId).single() :
                    await query.eq('email', userEmail).single();
            if (!error && data) {
                activeUserId = data.id || activeUserId;
                reporterRole = data.role || reporterRole;
                reporterProfile = data;
            }
            return data;
        }

        async function loadCctvReporterProfile(item) {
            if (!item?.created_by) return loadReporterRole();
            const { data, error } = await supabase
                .from('users')
                .select('id, role, signature, full_name')
                .eq('id', item.created_by)
                .single();
            if (!error && data) {
                reporterProfile = data;
                reporterRole = data.role || reporterRole;
            }
            return data;
        }

        async function loadApprovalUsers(item) {
            // Load actual approval records from the database
            const { data: approvals, error } = await supabase
                .from('cctv_checklist_approvals')
                .select('*')
                .eq('checklist_id', item.id)
                .order('approved_at', { ascending: true });

            if (!error && approvals) {
                item.approved_by_users = approvals;
                
                // Load user profiles for approvers
                const userIds = approvals.map(approval => approval.user_id).filter(Boolean);
                if (userIds.length > 0) {
                    const { data: users } = await supabase
                        .from('users')
                        .select('id, full_name, signature')
                        .in('id', userIds);
                    
                    if (users) {
                        item.approvalUsers = users;
                    }
                }
            } else {
                item.approved_by_users = [];
                item.approvalUsers = [];
            }
        }

        loadReporterRole();

        function renderStationCameraInputs() {
            const container = document.getElementById('stationCameraStatus');
            if (!branchesData || branchesData.length === 0) {
                container.innerHTML = '<p class="text-muted small text-center py-3">No STA stations found. Please add stations in Admin Settings.</p>';
                return;
            }

            container.innerHTML = branchesData.map((branch, index) => `
                <div class="row g-2 mb-2 align-items-center" data-branch-id="${branch.id}" data-branch-name="${esc(branch.name)}" data-total-cameras="${branch.total_cameras || 0}">
                    <div class="col-4">
                        <label class="form-label small mb-0">${esc(branch.name)}</label>
                    </div>
                    <div class="col-2">
                        <input type="number" class="form-control form-control-sm total-cameras" 
                            value="${branch.total_cameras || 0}" readonly title="Total cameras">
                    </div>
                    <div class="col-2">
                        <input type="number" class="form-control form-control-sm functioning-cameras" 
                            placeholder="Working" min="0" max="${branch.total_cameras || 0}" required>
                    </div>
                    <div class="col-2">
                        <input type="number" class="form-control form-control-sm non-functioning-cameras" 
                            placeholder="Not working" min="0" readonly>
                    </div>
                    <div class="col-2">
                        <input type="text" class="form-control form-control-sm comments" 
                            placeholder="Comments">
                    </div>
                </div>
            `).join('');

            // Add event listeners for automatic calculation
            container.querySelectorAll('.functioning-cameras').forEach(input => {
                input.addEventListener('input', function() {
                    const row = this.closest('[data-branch-id]');
                    const totalCameras = parseInt(row.dataset.totalCameras) || 0;
                    const functioning = parseInt(this.value) || 0;
                    const nonFunctioning = totalCameras - functioning;
                    row.querySelector('.non-functioning-cameras').value = nonFunctioning >= 0 ? nonFunctioning : 0;
                });
            });
        }

        // Initialize form when modal opens
        document.getElementById('openCctvFormBtn').addEventListener('click', function() {
            renderStationCameraInputs();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('newCctvModal')).show();
        });

        // Reset button
        document.getElementById('resetCctvFormBtn').addEventListener('click', function() {
            document.getElementById('cctvForm').reset();
            document.getElementById('checklistDate').value = new Date().toISOString().split('T')[0];
            selectedApprovers = [];
            renderSelectedApprovers();
            renderStationCameraInputs();
        });

      // Save button
document.getElementById('saveCctvBtn').addEventListener('click', async function(e) {
    e.preventDefault();
    
    // Validate form
    const checklistDate = document.getElementById('checklistDate').value;
    if (!checklistDate) {
        showAlert('warning', 'Please select a checklist date.');
        return;
    }

    const saveBtn = this;
    const spinner = saveBtn.querySelector('.spinner-border');
    const label = saveBtn.querySelector('.btn-label');

    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    label.textContent = 'Saving...';

    try {
        // --- Check for an existing checklist on this date before saving ---
        const { data: existing, error: checkError } = await supabase
            .from('cctv_checklists')
            .select('id')
            .eq('checklist_date', checklistDate)
            .eq('branch_type', 'sta');
           

        if (checkError) {
            showAlert('danger', checkError.message || 'Failed to verify existing checklist');
            return;
        }

        if (existing && existing.length > 2) {
            showAlert('warning', 'All checklists for this date have already been submitted.');
            return;
        }
        // --- end check ---

        const stationData = [];
        let totalCameras = 0;
        let functioningCameras = 0;

        document.querySelectorAll('#stationCameraStatus [data-branch-id]').forEach(row => {
            const branchId = row.dataset.branchId;
            const branchName = row.dataset.branchName;
            const totalCam = parseInt(row.dataset.totalCameras) || 0;
            const functioningCam = parseInt(row.querySelector('.functioning-cameras').value) || 0;
            const comments = row.querySelector('.comments').value || '';

            stationData.push({
                branch_id: branchId,
                branch_name: branchName,
                total_cameras: totalCam,
                functioning_cameras: functioningCam,
                comments: comments
            });

            totalCameras += totalCam;
            functioningCameras += functioningCam;
        });

        if (stationData.length === 0) {
            showAlert('warning', 'No stations found to record.');
            return;
        }

        const checklistData = {
            checklist_date: checklistDate,
            station_data: JSON.stringify(stationData),
            station_count: stationData.length,
            total_cameras: totalCameras,
            functioning_cameras: functioningCameras,
            branch_type: 'sta',
            shared_with: selectedApprovers.map(u => u.id).join(','),
            created_by: activeUserId,
            reporter_name: reporterProfile.full_name || userEmail,
            status: 'pending'
        };

        console.log('Saving checklist data:', checklistData);

        const { data, error } = await supabase
            .from('cctv_checklists')
            .insert([checklistData])
            .select();

        if (error) {
            console.error('Database error:', error);
            showAlert('danger', error.message || 'Failed to save checklist');
            return;
        }

        showAlert('success', 'CCTV checklist saved successfully');
        bootstrap.Modal.getInstance(document.getElementById('newCctvModal')).hide();
        document.getElementById('cctvForm').reset();
        selectedApprovers = [];
        renderSelectedApprovers();
        document.getElementById('checklistDate').value = new Date().toISOString().split('T')[0];
        setTimeout(() => location.reload(), 1500);
    } catch (err) {
        console.error('Error saving checklist:', err);
        showAlert('danger', 'An unexpected error occurred');
    } finally {
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
        label.textContent = 'Save Checklist';
    }
});

        function cctvFormHtml(item) {
            const stationData = JSON.parse(item.station_data || '[]');
            const totalCameras = stationData.reduce((sum, station) => sum + (station.total_cameras || 0), 0);
            const functioningCameras = stationData.reduce((sum, station) => sum + (station.functioning_cameras || 0), 0);

            const inventoryRows = stationData.map((station, index) => `
                <tr>
                    <td class="center">${index + 1}.</td>
                    <td>${esc(station.branch_name)}</td>
                    <td class="center">${station.total_cameras || 0}</td>
                    <td class="center">${station.functioning_cameras || 0}</td>
                    <td class="center">${(station.total_cameras || 0) - (station.functioning_cameras || 0)}</td>
                    <td>${esc(station.comments || '')}</td>
                </tr>
            `).join('');

            // Get actual approval records
            const approvals = getApprovalRecords(item);
            const approvalUsers = item.approvalUsers || [];
            
            // Find checked by (first approval with type 'checked')
            const checkedApproval = approvals.find(approval => approval.approval_type === 'checked') || approvals[0];
            const checkedUser = checkedApproval ? approvalUsers.find(user => user.id === (checkedApproval.user_id || checkedApproval)) : null;
            
            // Find approved by (approval with type 'approved' or second approval)
            const approvedApproval = approvals.find(approval => approval.approval_type === 'approved') || approvals[1];
            const approvedUser = approvedApproval ? approvalUsers.find(user => user.id === (approvedApproval.user_id || approvedApproval)) : null;

            const signatureImg = (user) => user?.signature ? `<img src="${esc(user.signature)}" alt="Signature" style="max-height:40px;max-width:100px;">` : '';

            return `
<div class="cctv-printout">
  <table class="header-table">
    <tr>
      <td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td>
    </tr>
    <tr>
      <td class="logo-cell">
        <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo">
        <small><i>Reliability Redefined</i></small>
      </td>
      <td class="form-title-cell">CCTV Surveillance Checklist (STA)</td>
      <td class="doc-info-cell">
        <div>TEX-ICT-CHL-003, Ver 000</div>
        <div>Issue Date: 1<sup>st</sup> Nov 2024</div>
        <div class="page-row">Page 1 of 1</div>
      </td>
    </tr>
  </table>

  <div class="content">
    <div class="doc-heading">TEXOL ENERGIES LIMITED CCTV SURVEILLANCE CHECKLIST (STA)</div>

    <div class="purpose">Purpose: To monitor and maintain operational efficiency of CCTV system across all STA stations.</div>

    <div class="section-heading">SECTION 1: CAMERA INVENTORY CHECKLIST</div>

    <table class="inventory-table">
      <tr>
        <th>No.</th>
        <th>Station Name</th>
        <th>Total Cameras Installed</th>
        <th>Cameras Functioning</th>
        <th>Cameras non-functional</th>
        <th>Comments</th>
      </tr>
      ${inventoryRows}
    </table>

    <div class="approvals-heading">APPROVALS</div>

    <table class="approvals-table">
      <tr>
        <th>Role</th>
        <th>Name</th>
        <th>Signature</th>
        <th>Date</th>
      </tr>
      <tr>
        <td>CCTV Officer</td>
        <td>${esc(reporterProfile.full_name || '')}</td>
        <td>${signatureImg(reporterProfile)}</td>
        <td>${esc(item.checklist_date)}</td>
      </tr>
      <tr>
        <td>Checked By (Technician)</td>
        <td>${esc(checkedUser?.full_name || '')}</td>
        <td>${signatureImg(checkedUser)}</td>
        <td>${checkedApproval ? esc(checkedApproval.approved_at?.split('T')[0] || '') : ''}</td>
      </tr>
      <tr>
        <td>Approved By (Department Head)</td>
        <td>${esc(approvedUser?.full_name || '')}</td>
        <td>${signatureImg(approvedUser)}</td>
        <td>${approvedApproval ? esc(approvedApproval.approved_at?.split('T')[0] || '') : ''}</td>
      </tr>
    </table>

    <div class="footer-note">Texol Energies Limited CCTV Surveillance Checklist (STA)</div>
  </div>
</div>`;
        }

       form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const saveBtn = document.getElementById('saveCctvBtn');
    const spinner = saveBtn.querySelector('.spinner-border');
    const label = saveBtn.querySelector('.btn-label');

    const checklistDate = document.getElementById('checklistDate').value;
    if (!checklistDate) {
        showAlert('warning', 'Please select a checklist date.');
        return;
    }

    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    label.textContent = 'Saving...';

    try {
        // --- NEW: check for an existing checklist on this date by this user ---
        const { data: existing, error: checkError } = await supabase
            .from('cctv_checklists')
            .select('id')
            .eq('checklist_date', checklistDate)
            .eq('branch_type', 'sta')
            .limit(1);

        if (checkError) {
            showAlert('danger', checkError.message || 'Failed to verify existing checklist');
            return;
        }

        if (existing && existing.length > 0) {
            showAlert('warning', 'A checklist for this date has already been submitted.');
            return;
        }
        // --- end new check ---

        const stationData = [];
        let totalCameras = 0;
        let functioningCameras = 0;

        document.querySelectorAll('#stationCameraStatus [data-branch-id]').forEach(row => {
            const branchId = row.dataset.branchId;
            const branchName = row.dataset.branchName;
            const totalCam = parseInt(row.dataset.totalCameras) || 0;
            const functioningCam = parseInt(row.querySelector('.functioning-cameras').value) || 0;
            const comments = row.querySelector('.comments').value || '';

            stationData.push({
                branch_id: branchId,
                branch_name: branchName,
                total_cameras: totalCam,
                functioning_cameras: functioningCam,
                comments: comments
            });

            totalCameras += totalCam;
            functioningCameras += functioningCam;
        });

        const checklistData = {
            checklist_date: checklistDate,
            station_data: JSON.stringify(stationData),
            station_count: stationData.length,
            total_cameras: totalCameras,
            functioning_cameras: functioningCameras,
            branch_type: 'sta',
            shared_with: selectedApprovers.map(u => u.id).join(','),
            created_by: activeUserId,
            reporter_name: reporterProfile.full_name || userEmail,
            status: 'pending'
        };

        const { data, error } = await supabase
            .from('cctv_checklists')
            .insert([checklistData])
            .select();

        if (error) {
            showAlert('danger', error.message || 'Failed to save checklist');
            return;
        }

        showAlert('success', 'CCTV checklist saved successfully');
        bootstrap.Modal.getInstance(document.getElementById('newCctvModal')).hide();
        form.reset();
        selectedApprovers = [];
        renderSelectedApprovers();
        document.getElementById('checklistDate').value = new Date().toISOString().split('T')[0];
        setTimeout(() => location.reload(), 1500);
    } catch (err) {
        console.error('Error saving checklist:', err);
        showAlert('danger', 'An unexpected error occurred');
    } finally {
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
        label.textContent = 'Save Checklist';
    }
});

        window.viewCctv = async (item) => {
            selectedCctv = item;
            await loadCctvReporterProfile(item);
            await loadApprovalUsers(item);
            const modalBody = document.getElementById('cctvModalBody');
            modalBody.innerHTML = cctvFormHtml(item);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('cctvModal')).show();
        };

        window.printCctv = async (item) => {
            await loadCctvReporterProfile(item);
            await loadApprovalUsers(item);
            const printWindow = window.open('', '_blank', 'width=900,height=1200');
            if (!printWindow) {
                alert('Please allow pop-ups to print the CCTV checklist.');
                return;
            }
            
            const htmlContent = cctvFormHtml(item);
            const styles = document.getElementById('cctvPrintStyles').textContent;
            
            printWindow.document.write(
                `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>TEX-ICT-CHL-003 CCTV Checklist</title><style>${styles}</style></head><body>${htmlContent}</body></html>`
            );
            printWindow.document.close();
            printWindow.focus();
            printWindow.onload = () => printWindow.print();
        };

        window.approveCctv = async (item) => {
            // Check if user is shared with this checklist
            const sharedWith = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean);
            if (!sharedWith.includes(activeUserId)) {
                showAlert('danger', 'You are not authorized to approve this checklist.');
                return;
            }
            
            // Check if user has already approved
            const existingApprovals = getApprovalRecords(item);
            if (existingApprovals.some(approval => (approval.user_id || approval) === activeUserId)) {
                showAlert('warning', 'You have already approved this checklist.');
                return;
            }
            
            if (!confirm('Are you sure you want to approve this checklist?')) return;
            
            // Determine approval type based on position in shared_with
            const approvalIndex = sharedWith.indexOf(activeUserId);
            const approvalType = approvalIndex === 0 ? 'checked' : 'approved';
            
            // Add approval record
            const { error: approvalError } = await supabase
                .from('cctv_checklist_approvals')
                .insert([{
                    checklist_id: item.id,
                    user_id: activeUserId,
                    approval_type: approvalType,
                    approved_at: new Date().toISOString()
                }]);

            if (approvalError) {
                showAlert('danger', approvalError.message || 'Failed to record approval');
                return;
            }
            
            // Update checklist status
            const newStatus = approvalType === 'approved' ? 'approved' : 'checked';
            const { error } = await supabase
                .from('cctv_checklists')
                .update({ status: newStatus })
                .eq('id', item.id);

            if (error) {
                showAlert('danger', error.message || 'Failed to approve checklist');
                return;
            }

            showAlert('success', 'Checklist approved successfully');
            setTimeout(() => location.reload(), 1500);
        };

        window.rejectCctv = async (item) => {
            // Check if user is shared with this checklist
            const sharedWith = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean);
            if (!sharedWith.includes(activeUserId)) {
                showAlert('danger', 'You are not authorized to reject this checklist.');
                return;
            }
            
            if (!confirm('Are you sure you want to reject this checklist?')) return;
            
            const { error } = await supabase
                .from('cctv_checklists')
                .update({ status: 'rejected' })
                .eq('id', item.id);

            if (error) {
                showAlert('danger', error.message || 'Failed to reject checklist');
                return;
            }

            showAlert('success', 'Checklist rejected successfully');
            setTimeout(() => location.reload(), 1500);
        };

        function getApprovalRecords(item) {
            if (Array.isArray(item.approved_by_users)) return item.approved_by_users;
            try {
                const value = item.approved_by_users ? JSON.parse(item.approved_by_users) : [];
                return Array.isArray(value) ? value : (value ? [value] : []);
            } catch (error) {
                return [];
            }
        }

        // Modal action handlers
        document.getElementById('modalPrintCctv')?.addEventListener('click', () => {
            if (!selectedCctv) return;
            
            const printWindow = window.open('', '_blank', 'width=900,height=1200');
            if (!printWindow) {
                alert('Please allow pop-ups to print the CCTV checklist.');
                return;
            }
            
            const htmlContent = cctvFormHtml(selectedCctv);
            const styles = document.getElementById('cctvPrintStyles').textContent;
            
            printWindow.document.write(
                `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>TEX-ICT-CHL-003 CCTV Checklist</title><style>${styles}</style></head><body>${htmlContent}</body></html>`
            );
            printWindow.document.close();
            printWindow.focus();
            printWindow.onload = () => printWindow.print();
        });

        document.getElementById('modalDeleteCctv')?.addEventListener('click', async () => {
            if (!selectedCctv || !confirm('Are you sure you want to delete this checklist?')) return;
            
            const { error } = await supabase
                .from('cctv_checklists')
                .delete()
                .eq('id', selectedCctv.id);

            if (error) {
                showAlert('danger', error.message || 'Failed to delete checklist');
                return;
            }

            showAlert('success', 'Checklist deleted successfully');
            bootstrap.Modal.getInstance(document.getElementById('cctvModal')).hide();
            setTimeout(() => location.reload(), 1500);
        });

        document.getElementById('modalDownloadCctv')?.addEventListener('click', async () => {
            if (!selectedCctv) return;
            
            const htmlContent = `<div style="padding: 20px; background: white;">${cctvFormHtml(selectedCctv)}</div>`;

            // Create a temporary container
            const container = document.createElement('div');
            container.innerHTML = htmlContent;
            container.style.position = 'fixed';
            container.style.left = '-9999px';
            container.style.top = '0';
            container.style.width = '210mm';
            container.style.background = 'white';
            document.body.appendChild(container);

            try {
                // Wait for any images to load
                await new Promise(resolve => setTimeout(resolve, 500));

                // Use html2canvas to capture the content
                const canvas = await html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                });

                // Create PDF using jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');

                // Calculate dimensions to fit A4
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 297; // A4 height in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;

                let heightLeft = imgHeight;
                let position = 0;

                // Add first page
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                // Add additional pages if needed
                while (heightLeft > 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                // Save the PDF
                pdf.save(`cctv_checklist_sta_${selectedCctv.checklist_date || 'date'}.pdf`);

            } catch (error) {
                console.error('PDF generation error:', error);
                alert('Failed to generate PDF. Please try again.');
            } finally {
                // Clean up
                document.body.removeChild(container);
            }
        });
    </script>
</body>

</html>