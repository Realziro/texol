<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: login');
    exit;
}

$branches = [];
$settlements = [];
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
        CURLOPT_URL => $supabaseUrl . '/rest/v1/incident_settlements?select=*&order=created_at.desc',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $settlements = json_decode(curl_exec($ch), true) ?: [];
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

    $settlements = array_values(array_filter($settlements, function ($settlement) use ($userId) {
        if (empty($userId) || !is_array($settlement)) {
            return false;
        }

        if (($settlement['created_by'] ?? '') === $userId) {
            return true;
        }

        $approverIds = array_filter(array_map('trim', explode(',', $settlement['shared_with'] ?? '')));
        return in_array($userId, $approverIds, true);
    }));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Settlement Form - THI Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style id="settlementPrintStyles">
        .settlement-printout {
            max-width: 900px;
            margin: 0 auto;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
        }

        .settlement-printout table {
            width: 100%;
            border-collapse: collapse;
        }

        .settlement-printout td,
        .settlement-printout th {
            border: 1px solid #000;
            padding: 6px 10px;
            vertical-align: middle;
        }

        .settlement-printout .company-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            letter-spacing: .5px;
        }

        .settlement-printout .logo-cell {
            width: 22%;
            text-align: center;
        }

        .settlement-printout .logo-cell img {
            max-width: 120px;
            max-height: 60px;
        }

        .settlement-printout .logo-cell small {
            display: block;
            margin-top: 2px;
            font-size: 11px;
        }

        .settlement-printout .form-title-cell {
            width: 46%;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            color: #C0392B;
        }

        .settlement-printout .doc-info-cell {
            width: 32%;
            font-size: 12px;
            text-align: center;
        }

        .settlement-printout .page-row {
            border-top: 1px solid #000;
            margin-top: 4px;
            padding-top: 4px;
        }

        .settlement-printout .content {
            padding: 18px 26px 28px 26px;
            font-size: 13.5px;
            line-height: 1.6;
        }

        .settlement-printout .doc-heading {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            font-size: 15px;
            margin-bottom: 22px;
        }

        .settlement-printout .blank-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 90px;
        }

        .settlement-printout .blank-line.long {
            min-width: 260px;
        }

        .settlement-printout .blank-line.medium {
            min-width: 160px;
        }

        .settlement-printout .blank-line.short {
            min-width: 50px;
        }

        .settlement-printout p {
            margin: 0 0 16px 0;
        }

        .settlement-printout .section-title {
            font-weight: bold;
            margin: 20px 0 10px 0;
        }

        .settlement-printout ol.clauses {
            padding-left: 22px;
            margin: 0;
        }

        .settlement-printout ol.clauses > li {
            margin-bottom: 18px;
        }

        .settlement-printout ol.clauses > li > .clause-title {
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }

        .settlement-printout .signature-block {
            margin-top: 6px;
        }

        .settlement-printout .signature-block .role-title {
            font-weight: bold;
            margin: 18px 0 6px 0;
        }

        .settlement-printout .sig-line {
            margin-bottom: 14px;
        }

        .settlement-printout .sig-line .label {
            display: inline-block;
            min-width: 170px;
        }

        .settlement-printout .sig-fill {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 260px;
            margin-right: 20px;
        }

        .settlement-printout .date-fill {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 180px;
        }

        .settlement-printout .footer-note {
            font-style: italic;
            font-size: 11px;
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            .settlement-printout {
                max-width: none;
                border: none;
            }
        }

        .settlement-table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6c757d;
            background: #f8f9fa;
            border-bottom-width: 1px
        }

        .settlement-table tbody td {
            padding-top: .7rem;
            padding-bottom: .7rem
        }

        .settlement-status {
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

        .settlement-table-wrap {
            overflow: visible !important;
        }

        .settlement-table-wrap .dropdown.show {
            position: relative;
            z-index: 1080;
        }

        .settlement-table-wrap .dropdown-menu {
            z-index: 1090;
        }

        @media (max-width: 767.98px) {
            .settlement-table-wrap {
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
        }
    </style>
</head>

<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php $activeMenu = 'incident_settlement';
        include __DIR__ . '/partials/sidebar.php'; ?>
        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button"
                    aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                <a class="navbar-brand fw-semibold d-none d-sm-inline" href="#"><span id="pageTitle">Incident
                        Settlement Form</span></a>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Incident Settlement Form</h1>
                    <p class="text-muted small mb-0">Create, view and print incident settlement agreements.</p>
                </section>
                <section class="row g-3 g-lg-4">
                    <div class="col-12 col-xl-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0 fw-semibold">New Settlement Agreement</h2>
                            </div>
                            <div class="card-body">
                                <div id="settlementAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                <form id="settlementForm" class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Agreement Date</label>
                                        <input class="form-control form-control-sm" id="agreementDate" type="date"
                                            value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Station/Branch</label>
                                        <select class="form-select form-select-sm" id="branchId" required>
                                            <option value="">Select station</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo htmlspecialchars($branch['id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($branch['name']); ?>">
                                                    <?php echo htmlspecialchars($branch['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Client Name</label>
                                        <input class="form-control form-control-sm" id="clientName" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Client NIN</label>
                                        <input class="form-control form-control-sm" id="clientNIN">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Client Telephone</label>
                                        <input class="form-control form-control-sm" id="clientTelephone">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Incident Location</label>
                                        <input class="form-control form-control-sm" id="incidentLocation" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Incident Description</label>
                                        <input class="form-control form-control-sm" id="incidentDescription" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Vehicle Registration No.</label>
                                        <input class="form-control form-control-sm" id="vehicleRegistration" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Settlement Amount (UGX)</label>
                                        <input class="form-control form-control-sm" id="settlementAmount" type="number" required>
                                    </div>
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
                                    <div class="col-12 d-flex justify-content-end gap-2">
                                        <button type="reset" class="btn btn-sm btn-outline-secondary">Reset</button>
                                        <button type="submit" class="btn btn-sm btn-primary" id="saveSettlementBtn">
                                            <span class="spinner-border spinner-border-sm d-none"></span>
                                            <span class="btn-label">Save Settlement</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0 fw-semibold">Saved Settlement Agreements</h2>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive settlement-table-wrap">
                                    <table class="table table-sm table-hover align-middle settlement-table"
                                        id="settlementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Client</th>
                                                <th>Vehicle</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($settlements)): ?>
                                                <tr>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td><span class="badge bg-secondary">-</span></td>
                                                    <td class="text-end">-</td>
                                                </tr>
                                            <?php else:
                                                foreach ($settlements as $settlement): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($settlement['agreement_date'] ?? '-'); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($settlement['client_name'] ?? '-'); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($settlement['vehicle_registration'] ?? '-'); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($settlement['settlement_amount'] ?? '-'); ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status = strtolower($settlement['status'] ?? 'pending');
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
                                                                            onclick="viewSettlement(<?php echo htmlspecialchars(json_encode($settlement), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-eye me-2"></i>View
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item" type="button"
                                                                            onclick="printSettlement(<?php echo htmlspecialchars(json_encode($settlement), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-printer me-2"></i>Print
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item" type="button"
                                                                            onclick="approveSettlement(<?php echo htmlspecialchars(json_encode($settlement), ENT_QUOTES, 'UTF-8'); ?>)">
                                                                            <i class="bi bi-check-circle me-2"></i>Approve /
                                                                            Check
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item text-danger" type="button"
                                                                            onclick="rejectSettlement(<?php echo htmlspecialchars(json_encode($settlement), ENT_QUOTES, 'UTF-8'); ?>)">
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
    <div class="modal fade" id="settlementModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Incident Settlement Agreement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="settlementModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="modalEditSettlement"
                        title="Edit settlement" aria-label="Edit settlement"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-outline-danger" id="modalDeleteSettlement"
                        title="Delete settlement" aria-label="Delete settlement"><i class="bi bi-trash"></i></button>
                    <button type="button" class="btn btn-outline-success" id="modalDownloadSettlement"
                        title="Download settlement" aria-label="Download settlement"><i class="bi bi-download"></i></button>
                    <button type="button" class="btn btn-primary" id="modalPrintSettlement" title="Print settlement"
                        aria-label="Print settlement"><i class="bi bi-printer"></i></button>
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
            $('#settlementsTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                order: [],
                language: {
                    search: '_INPUT_',
                    searchPlaceholder: 'Search settlements...'
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
        const form = document.getElementById('settlementForm');
        const alertBox = document.getElementById('settlementAlert');
        let selectedSettlement = null;
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
            }
            return data;
        }

        async function loadSettlementReporterProfile(item) {
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

        function settlementFormHtml(item) {
            const agreementDate = new Date(item.agreement_date);
            const day = agreementDate.getDate();
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const month = monthNames[agreementDate.getMonth()];
            const year = agreementDate.getFullYear().toString().slice(-2);

            const branchName = item.branch_name || item.station_name || '';

            return `
<div class="settlement-printout">
  <table class="header-table">
    <tr>
      <td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td>
    </tr>
    <tr>
      <td class="logo-cell">
        <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo">
        <small><i>Reliability Redefined</i></small>
      </td>
      <td class="form-title-cell">INCIDENT SETTLEMENT FORM</td>
      <td class="doc-info-cell">
        <div>TEX-RET-FRM-020, Ver 000</div>
        <div>Issue Date: 1<sup>st</sup> Nov 2024</div>
        <div class="page-row">Page 1 of 3</div>
      </td>
    </tr>
  </table>

  <div class="content">
    <div class="doc-heading">DAMAGE SETTLEMENT AGREEMENT</div>

    <p>This Settlement Agreement is made and entered into on this <span class="blank-line short">${day}</span> day of <span class="blank-line medium">${month}</span>, 20<span class="blank-line short" style="min-width:30px;">${year}</span>.</p>

    <p><strong>Between:</strong></p>

    <p><span class="blank-line long">${esc(item.client_name)}</span> of NIN. <span class="blank-line medium">${esc(item.client_nin || '')}</span></p>

    <p><span class="blank-line long">${esc(item.client_name)}</span> Telephone No. <span class="blank-line medium">${esc(item.client_telephone || '')}</span> (hereinafter referred to as the <strong>"Client"</strong>).</p>

    <p style="text-align:center;">And</p>

    <p>Texol Energies Limited of PO. BOX 11559 (hereinafter referred to as the <strong>"Company."</strong>)</p>

    <p class="section-title">WHEREAS:</p>

    <p>An incident occurred at the company's premises located at <span class="blank-line long">${esc(branchName)}</span></p>

    <p>involving <span class="blank-line long">${esc(item.incident_description)}</span> which has resulted to damage of the Client's motor vehicle with registration No. <span class="blank-line medium">${esc(item.vehicle_registration)}</span> which is subject to repair.</p>

    <p class="section-title">AND WHEREAS;</p>

    <p>The Company has agreed to cover the costs of repairs in full. Both Parties are desirous of amicably settling this matter fully and finally on the terms set-forth.</p>

    <p><strong>NOW, THEREFORE, the Parties have mutually agreed as follows:</strong></p>

    <ol class="clauses" start="1">
      <li>
        <span class="clause-title">Payment and Repair Completion</span>
        The Company has at the execution of this agreement paid cash of a sum of <strong>Ugx</strong> <span class="blank-line long">${esc(item.settlement_amount)}</span> to the Client as a result of negotiation between the client and the authorized company Representative (Employee), which covers the total cost of repairs and the Client has acknowledged receipt of the amount mentioned above.
      </li>
      <li>
        <span class="clause-title">No Further Claims</span>
        The Parties have agreed that the amount received by the Client constitutes final settlement of any and all claims related to the said damage, and the Client shall not seek any additional compensation from the Company.
      </li>
    </ol>

    <div class="footer-note">
      <span>Texol Energies Limited &nbsp;Incident Settlement Form</span>
      <span>1</span>
    </div>
  </div>
</div>

<div class="settlement-printout">
  <table class="header-table">
    <tr>
      <td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td>
    </tr>
    <tr>
      <td class="logo-cell">
        <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo">
        <small><i>Reliability Redefined</i></small>
      </td>
      <td class="form-title-cell">INCIDENT SETTLEMENT FORM</td>
      <td class="doc-info-cell">
        <div>TEX-RET-FRM-020, Ver 000</div>
        <div>Issue Date: 1<sup>st</sup> Nov 2024</div>
        <div class="page-row">Page 2 of 3</div>
      </td>
    </tr>
  </table>

  <div class="content">
    <ol class="clauses" start="3">
      <li>
        <span class="clause-title">Release of Liability</span>
        The Client hereby releases and discharges the Company from any further liability, claims, demands, or damages that may arise from or related to the said incident.
      </li>
      <li>
        <span class="clause-title">Final Agreement</span>
        This Agreement constitutes the entire understanding between the Parties and supersedes any prior discussions or agreements.
      </li>
      <li>
        <span class="clause-title">Amendment</span>
        No amendments or modifications shall be made unless agreed upon in writing by both Parties.
      </li>
      <li>
        <span class="clause-title">BINDING</span>
        It is hereby agreed that the Parties are bound by the terms stipulated herein.
      </li>
      <li>
        <span class="clause-title">Governing Law</span>
        This Agreement shall be governed by and construed in accordance with the laws of the Republic of Uganda.
      </li>
    </ol>

    <p><strong>IN WITNESS WHEREOF</strong>, the Parties hereto have executed this Agreement as of the date first written above.</p>

    <div class="footer-note" style="margin-top:220px;">
      <span>Texol Energies Limited &nbsp;Incident Settlement Form</span>
      <span>2</span>
    </div>
  </div>
</div>

<div class="settlement-printout">
  <table class="header-table">
    <tr>
      <td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td>
    </tr>
    <tr>
      <td class="logo-cell">
        <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo">
        <small><i>Reliability Redefined</i></small>
      </td>
      <td class="form-title-cell">INCIDENT SETTLEMENT FORM</td>
      <td class="doc-info-cell">
        <div>TEX-RET-FRM-020, Ver 000</div>
        <div>Issue Date: 1<sup>st</sup> Nov 2024</div>
        <div class="page-row">Page 3 of 3</div>
      </td>
    </tr>
  </table>

  <div class="content">
    <p><strong>Signed by the said;</strong></p>

    <div class="signature-block">
      <div class="role-title">Client:</div>
      <div class="sig-line">Name in own handwriting: <span class="sig-fill">${esc(item.client_name)}</span></div>
      <div class="sig-line">Signature: <span class="sig-fill" style="min-width:200px;"></span> Date: <span class="date-fill">${esc(item.agreement_date)}</span></div>

      <p>In the presence of;</p>

      <div class="sig-line">Name in own handwriting: <span class="sig-fill"></span></div>
      <div class="sig-line">Signature: <span class="sig-fill" style="min-width:200px;"></span> Date: <span class="date-fill"></span></div>
    </div>

    <p><strong>Signed by the Said;</strong></p>

    <div class="signature-block">
      <div class="role-title">Authorized Company Representative:</div>
      <div class="sig-line">Name in Own Handwriting: <span class="sig-fill" style="min-width:320px;">${esc(reporterProfile.full_name || '')}</span></div>
      <div class="sig-line">Signature <span class="blank-line medium"></span></div>
      <div class="sig-line">Designation: <span class="blank-line medium">${esc(reporterProfile.role || '')}</span> &nbsp;&nbsp; Date <span class="blank-line medium">${esc(item.agreement_date)}</span></div>

      <p>In the presence of;</p>

      <div class="sig-line">Name in own handwriting: <span class="sig-fill" style="min-width:280px;"></span></div>
      <div class="sig-line">Signature: <span class="sig-fill" style="min-width:200px;"></span> Date: <span class="date-fill"></span></div>
    </div>

    <div class="footer-note" style="margin-top:100px;">
      <span>Texol Energies Limited &nbsp;Incident Settlement Form</span>
      <span>3</span>
    </div>
  </div>
</div>`;
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (sharedWithDropdown && !sharedWithDropdown.contains(e.target) && e.target !== sharedWithInput) {
                sharedWithDropdown.classList.remove('show');
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const saveBtn = document.getElementById('saveSettlementBtn');
            const spinner = saveBtn.querySelector('.spinner-border');
            const label = saveBtn.querySelector('.btn-label');

            saveBtn.disabled = true;
            spinner.classList.remove('d-none');
            label.textContent = 'Saving...';

            try {
                const branchSelect = document.getElementById('branchId');
                const branchOption = branchSelect.options[branchSelect.selectedIndex];

                const settlementData = {
                    agreement_date: document.getElementById('agreementDate').value,
                    branch_id: branchSelect.value,
                    branch_name: branchOption.dataset.name || branchOption.textContent,
                    client_name: document.getElementById('clientName').value,
                    client_nin: document.getElementById('clientNIN').value,
                    client_telephone: document.getElementById('clientTelephone').value,
                    incident_location: document.getElementById('incidentLocation').value,
                    incident_description: document.getElementById('incidentDescription').value,
                    vehicle_registration: document.getElementById('vehicleRegistration').value,
                    settlement_amount: document.getElementById('settlementAmount').value,
                    shared_with: selectedApprovers.map(u => u.id).join(','),
                    created_by: activeUserId,
                    reporter_name: reporterProfile.full_name || userEmail,
                    status: 'pending'
                };

                const { data, error } = await supabase
                    .from('incident_settlements')
                    .insert([settlementData])
                    .select();

                if (error) {
                    showAlert('danger', error.message || 'Failed to save settlement');
                    return;
                }

                showAlert('success', 'Settlement agreement saved successfully');
                form.reset();
                selectedApprovers = [];
                renderSelectedApprovers();
                document.getElementById('agreementDate').value = new Date().toISOString().split('T')[0];
                setTimeout(() => location.reload(), 1500);
            } catch (err) {
                console.error('Error saving settlement:', err);
                showAlert('danger', 'An unexpected error occurred');
            } finally {
                saveBtn.disabled = false;
                spinner.classList.add('d-none');
                label.textContent = 'Save Settlement';
            }
        });

        window.viewSettlement = async (item) => {
            selectedSettlement = item;
            await loadSettlementReporterProfile(item);
            const modalBody = document.getElementById('settlementModalBody');
            modalBody.innerHTML = settlementFormHtml(item);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('settlementModal')).show();
        };

        window.printSettlement = async (item) => {
            await viewSettlement(item);
            setTimeout(() => window.print(), 500);
        };

        window.approveSettlement = async (item) => {
            if (!confirm('Are you sure you want to approve this settlement?')) return;
            
            const { error } = await supabase
                .from('incident_settlements')
                .update({ status: 'approved' })
                .eq('id', item.id);

            if (error) {
                showAlert('danger', error.message || 'Failed to approve settlement');
                return;
            }

            showAlert('success', 'Settlement approved successfully');
            setTimeout(() => location.reload(), 1500);
        };

        window.rejectSettlement = async (item) => {
            if (!confirm('Are you sure you want to reject this settlement?')) return;
            
            const { error } = await supabase
                .from('incident_settlements')
                .update({ status: 'rejected' })
                .eq('id', item.id);

            if (error) {
                showAlert('danger', error.message || 'Failed to reject settlement');
                return;
            }

            showAlert('success', 'Settlement rejected successfully');
            setTimeout(() => location.reload(), 1500);
        };

        // Modal action handlers
        document.getElementById('modalPrintSettlement')?.addEventListener('click', () => {
            window.print();
        });

        document.getElementById('modalDeleteSettlement')?.addEventListener('click', async () => {
            if (!selectedSettlement || !confirm('Are you sure you want to delete this settlement?')) return;
            
            const { error } = await supabase
                .from('incident_settlements')
                .delete()
                .eq('id', selectedSettlement.id);

            if (error) {
                showAlert('danger', error.message || 'Failed to delete settlement');
                return;
            }

            showAlert('success', 'Settlement deleted successfully');
            bootstrap.Modal.getInstance(document.getElementById('settlementModal')).hide();
            setTimeout(() => location.reload(), 1500);
        });

        document.getElementById('modalDownloadSettlement')?.addEventListener('click', async () => {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById('settlementModalBody');
            
            const canvas = await html2canvas(element, {
                scale: 2,
                useCORS: true,
                logging: false
            });
            
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgWidth = 210;
            const pageHeight = 297;
            const imgHeight = canvas.height * imgWidth / canvas.width;
            
            let heightLeft = imgHeight;
            let position = 0;
            
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
            
            while (heightLeft > 0) {
                position = heightLeft - imgHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }
            
            pdf.save(`incident_settlement_${selectedSettlement.id}.pdf`);
        });
    </script>
</body>

</html>