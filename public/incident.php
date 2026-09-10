<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: login');
    exit;
}

$branches = [];
$incidents = [];
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
        CURLOPT_URL => $supabaseUrl . '/rest/v1/branches?select=id,name&order=name.asc',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $branches = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/incidents?select=*&order=incident_date.desc,created_at.desc',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $incidents = json_decode(curl_exec($ch), true) ?: [];
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

    $incidents = array_values(array_filter($incidents, function ($incident) use ($userId) {
        if (empty($userId) || !is_array($incident)) {
            return false;
        }

        if (($incident['created_by'] ?? '') === $userId) {
            return true;
        }

        $approverIds = array_filter(array_map('trim', explode(',', $incident['shared_with'] ?? '')));
        return in_array($userId, $approverIds, true);
    }));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Form - THI Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style id="incidentPrintStyles">
        .incident-printout {
            max-width: 900px;
            margin: 0 auto;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            font-family: "Century Gothic", "CenturyGothic", "Apple Gothic", Arial, sans-serif;
        }

        .incident-printout table {
            width: 100%;
            border-collapse: collapse;
        }

        .incident-printout td,
        .incident-printout th {
            border: 1px solid #000;
            padding: 6px 10px;
            vertical-align: middle;
        }

        .incident-printout .company-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            letter-spacing: .5px;
        }

        .incident-printout .logo-cell {
            width: 22%;
            text-align: center;
        }

        .incident-printout .logo-cell img {
            max-width: 120px;
            max-height: 60px;
        }

        .incident-printout .logo-cell small {
            display: block;
            margin-top: 2px;
            font-size: 11px;
        }

        .incident-printout .form-title-cell {
            width: 46%;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            color: #C0392B;
        }

        .incident-printout .doc-info-cell {
            width: 32%;
            font-size: 12px;
            text-align: center;
        }

        .incident-printout .page-row {
            border-top: 1px solid #000;
            margin-top: 4px;
            padding-top: 4px;
        }

        .incident-printout .content {
            padding: 14px 16px 20px;
        }

        .incident-printout .info-table {
            margin-bottom: 14px;
        }

        .incident-printout .info-table td {
            font-weight: bold;
            font-size: 13px;
        }

        .incident-printout .fill {
            font-weight: normal;
        }

        .incident-printout .section-heading {
            font-weight: bold;
            text-decoration: underline;
            font-size: 14px;
            margin: 14px 0 10px;
        }

        .incident-printout .inline-fields {
            display: flex;
            gap: 60px;
            margin-bottom: 16px;
            font-weight: bold;
            font-size: 13px;
        }

        .incident-printout .field-line {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 220px;
            margin-left: 6px;
            font-weight: normal;
        }

        .incident-printout .describe-label,
        .incident-printout .witness-heading,
        .incident-printout .manager-heading {
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .incident-printout .describe-label i,
        .incident-printout .manager-heading .note {
            font-weight: normal;
        }

        .incident-printout .lined-box {
            border: 1px solid #000;
            margin-bottom: 20px;
        }

        .incident-printout .lined-box .line {
            border-bottom: 1px solid #000;
            min-height: 26px;
            padding: 3px 6px;
            white-space: pre-wrap;
        }

        .incident-printout .lined-box .line:last-child {
            border-bottom: none;
        }

        .incident-printout .witness-table,
        .incident-printout .signoff-table {
            margin-bottom: 20px;
        }

        .incident-printout .witness-table td,
        .incident-printout .signoff-table td {
            font-size: 13px;
            height: 30px;
            vertical-align: top;
        }

        .incident-printout .label,
        .incident-printout .signoff-table tr:first-child td {
            font-weight: bold;
        }

        .incident-printout .footer-note {
            font-style: italic;
            font-size: 11px;
            margin-top: 18px;
            padding-left: 16px;
        }

        @media print {
            .incident-printout {
                max-width: none;
                border: none;
            }
        }

        .incident-table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6c757d;
            background: #f8f9fa;
            border-bottom-width: 1px
        }

        .incident-table tbody td {
            padding-top: .7rem;
            padding-bottom: .7rem
        }

        .incident-status {
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

        .incident-table-wrap {
            overflow: visible !important;
        }

        .incident-table-wrap .dropdown.show {
            position: relative;
            z-index: 1080;
        }

        .incident-table-wrap .dropdown-menu {
            z-index: 1090;
        }

        @media (max-width: 767.98px) {
            .incident-table-wrap {
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
        }
    </style>
</head>

<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php $activeMenu = 'incident';
        include __DIR__ . '/partials/sidebar.php'; ?>
        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button"
                    aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                <a class="navbar-brand fw-semibold d-none d-sm-inline" href="#"><span id="pageTitle">Incident
                        Form</span></a>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Incident Form</h1>
                    <p class="text-muted small mb-0">Create, view and print incident reports.</p>
                </section>
                <section class="row g-3 g-lg-4">
                    <div class="col-12 col-xl-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0 fw-semibold">New Incident Report</h2>
                            </div>
                            <div class="card-body">
                                <div id="incidentAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                <form id="incidentForm" class="row g-3">
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Date</label><input
                                            class="form-control form-control-sm" id="incidentDate" type="date"
                                            value="<?php echo date('Y-m-d'); ?>" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Time
                                            *</label><input class="form-control form-control-sm" id="incidentTime"
                                            type="time" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Name</label><input
                                            class="form-control form-control-sm" id="reporterName"
                                            value="<?php echo htmlspecialchars($userName); ?>" required></div>
                                    <div class="col-md-6"><label
                                            class="form-label small fw-semibold">Position</label><input
                                            class="form-control form-control-sm" id="reporterPosition"
                                            value="<?php echo htmlspecialchars($userRole); ?>" readonly></div>
                                    <div class="col-md-6"><label
                                            class="form-label small fw-semibold">Department</label><input
                                            class="form-control form-control-sm" id="reporterDepartment"
                                            value="<?php echo htmlspecialchars($userDepartment); ?>" required></div>
                                    <div class="col-12">
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
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Station name
                                            *</label><select class="form-select form-select-sm" id="branchId" required>
                                            <option value="">Select station</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo htmlspecialchars($branch['id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($branch['name']); ?>">
                                                    <?php echo htmlspecialchars($branch['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Shift
                                            *</label><select class="form-select form-select-sm" id="shift" required>
                                            <option value="">Select shift</option>
                                            <option>Day</option>
                                            <option>Night</option>
                                        </select></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Describe the
                                            incident *</label><textarea class="form-control form-control-sm"
                                            id="incidentDescription" rows="4" required></textarea></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Witness
                                            information</label></div>
                                    <div class="col-md-6"><input class="form-control form-control-sm mb-2"
                                            id="witness1Name" placeholder="Witness 1 name"><input
                                            class="form-control form-control-sm" id="witness1Position"
                                            placeholder="Witness 1 position"></div>
                                    <div class="col-md-6"><input class="form-control form-control-sm mb-2"
                                            id="witness2Name" placeholder="Witness 2 name"><input
                                            class="form-control form-control-sm" id="witness2Position"
                                            placeholder="Witness 2 position"></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Manager/Supervisor
                                            comments</label><textarea class="form-control form-control-sm"
                                            id="managerComments" rows="3"></textarea></div>
                                    <div class="col-12 d-flex justify-content-end gap-2"><button type="reset"
                                            class="btn btn-sm btn-outline-secondary">Reset</button><button type="submit"
                                            class="btn btn-sm btn-primary" id="saveIncidentBtn"><span
                                                class="spinner-border spinner-border-sm d-none"></span><span
                                                class="btn-label">Save
                                                Incident</span></button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0 fw-semibold">Saved Incident Reports</h2>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive incident-table-wrap">
                                    <table class="table table-sm table-hover align-middle incident-table"
                                        id="incidentsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Station</th>
                                                <th>Shift</th>
                                                <th>Reported by</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php if (empty($incidents)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted small py-3">No incident
                                                        reports found.</td>
                                                </tr><?php else:
                                            foreach ($incidents as $incident): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($incident['incident_date'] ?? '-'); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($incident['station_name'] ?? '-'); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($incident['shift'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($incident['reporter_name'] ?? '-'); ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status = strtolower($incident['status'] ?? 'pending');
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
                                                                            onclick="viewIncident(<?php echo htmlspecialchars(json_encode($incident), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-eye me-2"></i>View
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item" type="button"
                                                                            onclick="printIncident(<?php echo htmlspecialchars(json_encode($incident), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-printer me-2"></i>Print
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item" type="button"
                                                                            onclick="approveIncident(<?php echo htmlspecialchars(json_encode($incident), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-check-circle me-2"></i>Approve /
                                                                            Check
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item text-danger" type="button"
                                                                            onclick="rejectIncident(<?php echo htmlspecialchars(json_encode($incident), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-x-circle me-2"></i>Reject
                                                                        </button>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr><?php endforeach; endif; ?>
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
    <div class="modal fade" id="incidentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Incident Report</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="incidentModalBody"></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" id="modalEditIncident"
                        title="Edit incident" aria-label="Edit incident"><i class="bi bi-pencil"></i></button><button
                        type="button" class="btn btn-outline-danger" id="modalDeleteIncident" title="Delete incident"
                        aria-label="Delete incident"><i class="bi bi-trash"></i></button><button type="button"
                        class="btn btn-outline-success" id="modalDownloadIncident" title="Download incident"
                        aria-label="Download incident"><i class="bi bi-download"></i></button><button type="button"
                        class="btn btn-primary" id="modalPrintIncident" title="Print incident"
                        aria-label="Print incident"><i class="bi bi-printer"></i></button><button type="button"
                        class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
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
            $('#incidentsTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                order: [],
                language: {
                    search: '_INPUT_',
                    searchPlaceholder: 'Search incidents...'
                },
                columnDefs: [
                    { orderable: false, targets: 5 }
                ]
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
        const form = document.getElementById('incidentForm');
        const alertBox = document.getElementById('incidentAlert');
        let selectedIncident = null;
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
                document.getElementById('reporterPosition').value = data.role || '';
            }
            return data;
        }
        async function loadIncidentReporterProfile(item) {
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
        loadReporterRole();

        function incidentFormHtml(item) {
            const lines = (value, count) => Array.from({
                length: count
            }, (_, index) => `<div class="line">${index === 0 ? esc(value) : ''}</div>`).join('');
            return `<div class="incident-printout"><table><tr><td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td></tr><tr><td class="logo-cell"><img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo"><small><i>Reliability Redefined</i></small></td><td class="form-title-cell">Incident Report Form</td><td class="doc-info-cell"><div>TEX-RET-FRM-011, Ver 000</div><div>Issue Date: 1<sup>st</sup> Nov 2024</div><div class="page-row">Page 1 of 1</div></td></tr></table><div class="content"><table class="info-table"><tr><td style="width:50%">Date: <span class="fill">${esc(item.incident_date)}</span></td><td>Name: <span class="fill">${esc(item.reporter_name)}</span></td></tr><tr><td>Employee ID: <span class="fill"></span></td><td>Position: <span class="fill"></span></td></tr><tr><td>Station name: <span class="fill">${esc(item.station_name)}</span></td><td>Department: <span class="fill">${esc(item.department)}</span></td></tr></table><div class="section-heading">INCIDENT DETAILS:</div><div class="inline-fields"><div>TIME: <span class="field-line">${esc(item.incident_time)}</span></div><div>SHIFT: <span class="field-line">${esc(item.shift)}</span></div></div><div class="describe-label">Describe the incident - <i>(Be brief, include any damage caused and remedy action)</i></div><div class="lined-box">${lines(item.description, 7)}</div><div class="witness-heading">Witness Information:</div><table class="witness-table"><tr><td class="label" style="width:50%">Witness 1</td><td class="label" style="width:50%">Witness 2:</td></tr><tr><td>Name: ${esc(item.witness1_name)}</td><td>Name: ${esc(item.witness2_name)}</td></tr><tr><td>Position: ${esc(item.witness1_position)}</td><td>Position: ${esc(item.witness2_position)}</td></tr></table><div class="manager-heading">Manager/Supervisor comments: <span class="note">- [Any additional information relevant to the incident]</span></div><div class="lined-box">${lines(item.manager_comments, 6)}</div><table class="signoff-table"><tr><td style="width:33.3%">Completed By:</td><td style="width:33.3%">Checked By: Supervisor</td><td style="width:33.4%">Approved By: Manager</td></tr><tr><td class="label">Name<br><span class="fill">${esc(item.reporter_name)}</span></td><td class="label">Name</td><td class="label">Name</td></tr><tr><td class="label">Position</td><td class="label">Position</td><td class="label">Position</td></tr><tr><td class="label">Signature</td><td class="label">Signature</td><td class="label">Signature</td></tr></table><div class="footer-note">Texol Energies. Incident Report Form</div></div></div>`;
        }
        const originalIncidentFormHtml = incidentFormHtml;
        incidentFormHtml = item => {
            const approvals = getApprovalRecords(item);
            const approverIds = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean).slice(0, 2);
            const checkedApproval = approvals.find(approval => approval.approval_type === 'checked') || approvals.find(approval => (approval.user_id || approval) === approverIds[0]);
            const approvedApproval = approvals.find(approval => approval.approval_type === 'approved') || approvals[1] || approvals.find(approval => (approval.user_id || approval) === approverIds[1]);
            const checkedUser = checkedApproval && item.approvalUsers?.find(user => user.id === (checkedApproval.user_id || checkedApproval));
            const approvedUser = approvedApproval && item.approvalUsers?.find(user => user.id === (approvedApproval.user_id || approvedApproval));
            const signature = user => user?.signature ? `<img src="${esc(user.signature)}" alt="Signature" style="max-height:40px;max-width:100px;">` : '';
            const signoffHtml = `<table class="signoff-table"><tr><td style="width:33.3%">Completed By:</td><td style="width:33.3%">Checked By: Supervisor</td><td style="width:33.4%">Approved By: Manager</td></tr><tr><td class="label">Name<br><span class="fill">${esc(item.reporter_name || '')}</span></td><td class="label">Name<br><span class="fill">${esc(checkedUser?.full_name || '')}</span></td><td class="label">Name<br><span class="fill">${esc(approvedUser?.full_name || '')}</span></td></tr><tr><td class="label">Position<br><span class="fill">${esc(reporterProfile.role || reporterRole)}</span></td><td class="label">Position<br><span class="fill">${esc(checkedUser?.role || '')}</span></td><td class="label">Position<br><span class="fill">${esc(approvedUser?.role || '')}</span></td></tr><tr><td class="label">Signature<br>${signature(reporterProfile)}</td><td class="label">Signature<br>${signature(checkedUser)}</td><td class="label">Signature<br>${signature(approvedUser)}</td></tr></table>`;
            const html = originalIncidentFormHtml(item).replace(
                'Position: <span class="fill"></span>',
                `Position: <span class="fill">${esc(reporterProfile.role || reporterRole)}</span>`
            ).replace(/<table class="signoff-table">[\s\S]*?<\/table>/, signoffHtml);
            return html;
        };
        function incidentDetailsHtml(item) {
            const approvals = getApprovalRecords(item);
            const firstApproverId = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean)[0];
            const secondApproverId = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean)[1];
            const checkedApproval = approvals.find(approval => (approval.user_id || approval) === firstApproverId && (!approval.approval_type || approval.approval_type === 'checked'));
            const approvedApproval = approvals.find(approval => approval.approval_type === 'approved') || approvals.find(approval => (approval.user_id || approval) === secondApproverId);
            const checkedUser = item.approvalUsers?.find(user => user.id === (checkedApproval?.user_id || checkedApproval));
            const approvedUser = item.approvalUsers?.find(user => user.id === (approvedApproval?.user_id || approvedApproval));
            const checkedBy = checkedUser?.full_name || checkedUser?.email || '-';
            const approvedBy = approvedUser?.full_name || approvedUser?.email || '-';
            return `<div class="row g-3"><div class="col-md-6"><small class="text-muted">Date</small><div>${esc(item.incident_date)}</div></div><div class="col-md-6"><small class="text-muted">Time</small><div>${esc(item.incident_time)}</div></div><div class="col-md-6"><small class="text-muted">Reporter</small><div>${esc(item.reporter_name)}</div></div><div class="col-md-6"><small class="text-muted">Position</small><div>${esc(reporterRole)}</div></div><div class="col-md-6"><small class="text-muted">Department</small><div>${esc(item.department)}</div></div><div class="col-md-6"><small class="text-muted">Station</small><div>${esc(item.station_name)}</div></div><div class="col-md-6"><small class="text-muted">Shift</small><div>${esc(item.shift)}</div></div><div class="col-md-6"><small class="text-muted">Status</small><div><span class="badge bg-secondary">${esc(item.status || 'pending')}</span></div></div><div class="col-12"><small class="text-muted">Incident description</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${esc(item.description)}</div></div><div class="col-md-6"><small class="text-muted">Witness 1</small><div>${esc(item.witness1_name || '-')}<br>${esc(item.witness1_position || '')}</div></div><div class="col-md-6"><small class="text-muted">Witness 2</small><div>${esc(item.witness2_name || '-')}<br>${esc(item.witness2_position || '')}</div></div><div class="col-12"><small class="text-muted">Manager/Supervisor comments</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${esc(item.manager_comments || '-')}</div></div><div class="col-md-6"><small class="text-muted">Checked By</small><div>${esc(checkedBy)}</div></div><div class="col-md-6"><small class="text-muted">Approved By</small><div>${esc(approvedBy)}</div></div></div>`;
        }
        function userOptions(selectedId) {
            return `<option value="">Not assigned</option>${allUsers.filter(user => user.id !== activeUserId).map(user => `<option value="${esc(user.id)}" ${user.id === selectedId ? 'selected' : ''}>${esc(user.full_name || user.email)} (${esc(user.email)})</option>`).join('')}`;
        }
        function branchOptions(selectedId) {
            return Array.from(document.getElementById('branchId').options).map(option => `<option value="${esc(option.value)}" ${option.value === selectedId ? 'selected' : ''}>${esc(option.textContent.trim())}</option>`).join('');
        }
        window.editIncident = async function (item) {
            if (getApprovalRecords(item).length > 0 || ['checked', 'approved'].includes(item.status)) {
                alert('This incident cannot be edited after an approval has been recorded.');
                return;
            }
            await loadApprovalUsers(item);
            const approverIds = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean);
            document.getElementById('incidentModalBody').innerHTML = `<form id="editIncidentForm" class="row g-3"><div class="col-md-6"><label class="form-label small fw-semibold">Date</label><input class="form-control form-control-sm" id="editIncidentDate" type="date" value="${esc(item.incident_date || '')}" required></div><div class="col-md-6"><label class="form-label small fw-semibold">Time</label><input class="form-control form-control-sm" id="editIncidentTime" type="time" value="${esc(item.incident_time || '')}" required></div><div class="col-md-6"><label class="form-label small fw-semibold">Reporter</label><input class="form-control form-control-sm" id="editReporterName" value="${esc(item.reporter_name || '')}" required></div><div class="col-md-6"><label class="form-label small fw-semibold">Department</label><input class="form-control form-control-sm" id="editReporterDepartment" value="${esc(item.department || '')}" required></div><div class="col-md-6"><label class="form-label small fw-semibold">Station</label><select class="form-select form-select-sm" id="editBranchId" required>${branchOptions(item.branch_id)}</select></div><div class="col-md-6"><label class="form-label small fw-semibold">Shift</label><select class="form-select form-select-sm" id="editShift" required><option ${item.shift === 'Morning' ? 'selected' : ''}>Morning</option><option ${item.shift === 'Afternoon' ? 'selected' : ''}>Afternoon</option><option ${item.shift === 'Night' ? 'selected' : ''}>Night</option><option ${item.shift === 'Other' ? 'selected' : ''}>Other</option></select></div><div class="col-12"><label class="form-label small fw-semibold">Incident description</label><textarea class="form-control form-control-sm" id="editDescription" rows="4" required>${esc(item.description || '')}</textarea></div><div class="col-md-6"><label class="form-label small fw-semibold">Witness 1 name</label><input class="form-control form-control-sm mb-2" id="editWitness1Name" value="${esc(item.witness1_name || '')}"><input class="form-control form-control-sm" id="editWitness1Position" value="${esc(item.witness1_position || '')}" placeholder="Position"></div><div class="col-md-6"><label class="form-label small fw-semibold">Witness 2 name</label><input class="form-control form-control-sm mb-2" id="editWitness2Name" value="${esc(item.witness2_name || '')}"><input class="form-control form-control-sm" id="editWitness2Position" value="${esc(item.witness2_position || '')}" placeholder="Position"></div><div class="col-12"><label class="form-label small fw-semibold">Manager/Supervisor comments</label><textarea class="form-control form-control-sm" id="editManagerComments" rows="3">${esc(item.manager_comments || '')}</textarea></div><div class="col-md-6"><label class="form-label small fw-semibold">Checked By</label><select class="form-select form-select-sm" id="editApprover1">${userOptions(approverIds[0])}</select></div><div class="col-md-6"><label class="form-label small fw-semibold">Approved By</label><select class="form-select form-select-sm" id="editApprover2">${userOptions(approverIds[1])}</select></div><div class="col-12 text-end"><button type="button" class="btn btn-sm btn-secondary" id="cancelEditIncident">Cancel</button><button type="submit" class="btn btn-sm btn-primary ms-2">Save Changes</button></div></form>`;
            document.getElementById('cancelEditIncident').addEventListener('click', () => window.viewIncident(item));
            document.getElementById('editIncidentForm').addEventListener('submit', async event => {
                event.preventDefault();
                if (getApprovalRecords(item).length > 0 || ['checked', 'approved'].includes(item.status)) {
                    alert('This incident cannot be edited after an approval has been recorded.');
                    return;
                }
                const branch = document.getElementById('editBranchId').selectedOptions[0];
                const approvers = [document.getElementById('editApprover1').value, document.getElementById('editApprover2').value].filter(Boolean);
                if (new Set(approvers).size !== approvers.length) { alert('Checked By and Approved By must be different users.'); return; }
                const update = { incident_date: document.getElementById('editIncidentDate').value, incident_time: document.getElementById('editIncidentTime').value, reporter_name: document.getElementById('editReporterName').value.trim(), department: document.getElementById('editReporterDepartment').value.trim(), branch_id: branch.value, station_name: branch.textContent.trim(), shift: document.getElementById('editShift').value, description: document.getElementById('editDescription').value.trim(), witness1_name: document.getElementById('editWitness1Name').value.trim(), witness1_position: document.getElementById('editWitness1Position').value.trim(), witness2_name: document.getElementById('editWitness2Name').value.trim(), witness2_position: document.getElementById('editWitness2Position').value.trim(), manager_comments: document.getElementById('editManagerComments').value.trim(), shared_with: approvers.join(','), approved_by_users: approvers.join(',') === (item.shared_with || '') ? item.approved_by_users || [] : [], status: approvers.join(',') === (item.shared_with || '') ? item.status || 'pending' : 'pending' };
                const { error } = await supabase.from('incidents').update(update).eq('id', item.id);
                if (error) { alert('Failed to update incident: ' + error.message); return; }
                location.reload();
            });
        };
        window.viewIncident = async function (item) {
            await loadIncidentReporterProfile(item);
            await loadApprovalUsers(item);
            selectedIncident = item;
            const approvalLocked = getApprovalRecords(item).length > 0 || ['checked', 'approved'].includes(item.status);
            const editButton = document.getElementById('modalEditIncident');
            editButton.disabled = approvalLocked;
            editButton.title = approvalLocked ? 'Editing disabled after approval' : 'Edit incident';
            editButton.setAttribute('aria-label', editButton.title);
            document.getElementById('incidentModalBody').innerHTML = incidentDetailsHtml(item);

            // Setup download button
            const downloadButton = document.getElementById('modalDownloadIncident');
            downloadButton.onclick = () => downloadIncident(item);

            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        };
        window.printIncident = async function (item) {
            await loadIncidentReporterProfile(item);
            await loadApprovalUsers(item);
            const printWindow = window.open('', '_blank', 'width=900,height=1200');
            if (!printWindow) {
                alert('Please allow pop-ups to print the incident report.');
                return;
            }
            printWindow.document.write(
                `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>TEX-RET-FRM-011 Incident Report</title><style>${document.getElementById('incidentPrintStyles').textContent}</style></head><body>${incidentFormHtml(item)}</body></html>`
            );
            printWindow.document.close();
            printWindow.focus();
            printWindow.onload = () => printWindow.print();
        };

        window.downloadIncident = async function (item) {
            await loadIncidentReporterProfile(item);
            await loadApprovalUsers(item);

            const htmlContent = `<div style="padding: 20px; background: white;">${incidentFormHtml(item)}</div>`;

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
                pdf.save(`incident_report_${item.incident_date || 'date'}.pdf`);

            } catch (error) {
                console.error('PDF generation error:', error);
                alert('Failed to generate PDF. Please try again.');
            } finally {
                // Clean up
                document.body.removeChild(container);
            }
        };
        document.getElementById('modalPrintIncident').addEventListener('click', () => {
            if (selectedIncident) window.printIncident(selectedIncident);
        });
        document.getElementById('modalDownloadIncident').addEventListener('click', () => {
            if (selectedIncident) window.downloadIncident(selectedIncident);
        });
        document.getElementById('modalEditIncident').addEventListener('click', () => {
            if (selectedIncident) window.editIncident(selectedIncident);
        });
        document.getElementById('modalDeleteIncident').addEventListener('click', () => {
            if (selectedIncident) window.deleteIncident(selectedIncident);
        });
        function getApprovalRecords(item) {
            if (Array.isArray(item.approved_by_users)) return item.approved_by_users;
            try {
                const records = item.approved_by_users ? JSON.parse(item.approved_by_users) : [];
                return Array.isArray(records) ? records : (records ? [records] : []);
            } catch (error) { return []; }
        }
        async function loadApprovalUsers(item) {
            const approverIds = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean).slice(0, 2);
            const approvalIds = getApprovalRecords(item).map(approval => approval.user_id || approval).filter(Boolean);
            const userIds = [...new Set([...approverIds, ...approvalIds])];
            if (userIds.length === 0) { item.approvalUsers = []; return; }
            const { data } = await supabase.from('users').select('id, full_name, email, role, signature').in('id', userIds);
            item.approvalUsers = userIds.map(id => (data || []).find(user => user.id === id) || { id });
        }
        function requestApprovalPassword() {
            return new Promise(resolve => {
                const modalId = 'approvalPasswordModal';
                document.getElementById(modalId)?.remove();
                document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="${modalId}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Approval Password</h5><button type="button" class="btn-close" data-password-cancel></button></div><form id="approvalPasswordForm"><div class="modal-body"><label class="form-label" for="approvalTempPassword">Enter your password to approve or check this incident</label><input type="password" class="form-control" id="approvalTempPassword" name="temp_password" required autocomplete="current-password"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-password-cancel>Cancel</button><button type="submit" class="btn btn-primary">Verify</button></div></form></div></div></div>`);
                const modalElement = document.getElementById(modalId);
                const modal = new bootstrap.Modal(modalElement);
                const finish = value => { modal.hide(); resolve(value); setTimeout(() => modalElement.remove(), 300); };
                modalElement.querySelectorAll('[data-password-cancel]').forEach(button => button.addEventListener('click', () => finish(null)));
                modalElement.querySelector('form').addEventListener('submit', event => { event.preventDefault(); finish(modalElement.querySelector('[name="temp_password"]').value); });
                modalElement.addEventListener('hidden.bs.modal', () => modalElement.remove(), { once: true });
                modal.show();
            });
        }
        async function verifyApprovalPassword() {
            const password = await requestApprovalPassword();
            if (password === null || password === '') return false;
            const { data, error } = await supabase.from('users').select('temp_password').eq('id', activeUserId).single();
            if (error || !data || password !== data.temp_password) {
                alert('Incorrect password.');
                return false;
            }
            return true;
        }
        window.approveIncident = async function (item) {
            if (item.status === 'approved' || item.status === 'rejected') { alert('This incident is already closed.'); return; }
            const approverIds = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean).slice(0, 2);
            const currentIndex = approverIds.indexOf(activeUserId);
            if (currentIndex < 0) { alert('You are not assigned to approve this incident.'); return; }
            const approvals = getApprovalRecords(item);
            if (approvals.some(approval => (approval.user_id || approval) === activeUserId)) { alert('You have already approved this incident.'); return; }
            if (currentIndex === 1 && !approvals.some(approval => (approval.user_id || approval) === approverIds[0])) { alert('The first approver must check this incident before the manager can approve it.'); return; }
            if (!await verifyApprovalPassword()) return;
            approvals.push({ user_id: activeUserId, approval_type: currentIndex === 0 ? 'checked' : 'approved', approved_at: new Date().toISOString() });
            const update = { approved_by_users: approvals, status: currentIndex === 1 ? 'approved' : 'checked' };
            const { error } = await supabase.from('incidents').update(update).eq('id', item.id);
            if (error) { alert('Failed to update incident approval: ' + error.message); return; }
            alert(currentIndex === 0 ? 'Incident checked successfully.' : 'Incident approved successfully.');
            location.reload();
        };
        window.rejectIncident = async function (item) {
            if (getApprovalRecords(item).length > 0 || ['checked', 'approved'].includes(item.status)) {
                alert('This incident cannot be rejected after an approval has been recorded.');
                return;
            }
            const approverIds = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean).slice(0, 2);
            if (!approverIds.includes(activeUserId)) {
                alert('Only the assigned approvers can reject this incident.');
                return;
            }
            if (!confirm('Are you sure you want to reject this incident?')) return;
            const { error } = await supabase.from('incidents').update({ status: 'rejected' }).eq('id', item.id);
            if (error) { alert('Failed to reject incident: ' + error.message); return; }
            alert('Incident rejected successfully.');
            location.reload();
        };
        window.deleteIncident = async function (item) {
            if (!confirm('Are you sure you want to permanently delete this incident report?')) return;
            const { error } = await supabase.from('incidents').delete().eq('id', item.id);
            if (error) { alert('Failed to delete incident: ' + error.message); return; }
            location.reload();
        };
        form.addEventListener('reset', () => {
            setTimeout(() => document.getElementById('incidentDate').value = new Date().toISOString().slice(0, 10),
                0);
        });
        let submittingIncident = false;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (submittingIncident) return;
            submittingIncident = true;
            const button = document.getElementById('saveIncidentBtn');
            const option = document.getElementById('branchId').selectedOptions[0];
            const record = {
                incident_date: document.getElementById('incidentDate').value,
                incident_time: document.getElementById('incidentTime').value,
                reporter_name: document.getElementById('reporterName').value.trim(),
                department: document.getElementById('reporterDepartment').value.trim(),
                shared_with: selectedApprovers.map(user => user.id).join(','),
                approved_by_users: [],
                status: 'pending',
                branch_id: option.value,
                station_name: option.dataset.name || option.textContent,
                shift: document.getElementById('shift').value,
                description: document.getElementById('incidentDescription').value.trim(),
                witness1_name: document.getElementById('witness1Name').value.trim(),
                witness1_position: document.getElementById('witness1Position').value.trim(),
                witness2_name: document.getElementById('witness2Name').value.trim(),
                witness2_position: document.getElementById('witness2Position').value.trim(),
                manager_comments: document.getElementById('managerComments').value.trim(),
                created_by: activeUserId || null
            };
            button.disabled = true;
            button.querySelector('.btn-label').textContent = 'Saving...';
            button.querySelector('.spinner-border').classList.remove('d-none');
            try {
                const {
                    error
                } = await supabase.from('incidents').insert(record);
                if (error) throw error;
                showAlert('success', 'Incident report saved successfully.');
                setTimeout(() => location.reload(), 500);
            } catch (error) {
                showAlert('danger', error.message || 'Failed to save incident report.');
            } finally {
                button.disabled = false;
                button.querySelector('.spinner-border').classList.add('d-none');
                button.querySelector('.btn-label').textContent = 'Save Incident';
                submittingIncident = false;
            }
        });
    </script>
</body>

</html>