<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: login');
    exit;
}

$branches = [];
$shortages = [];
$userEmail = $_SESSION['user_email'] ?? '';
$userName = $_SESSION['user_name'] ?? $userEmail;
$userDepartment = $_SESSION['user_department'] ?? $_SESSION['department'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$userId = $_SESSION['user_id'] ?? '';

if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;
    $headers = ['apikey: ' . $supabaseKey, 'Authorization: Bearer ' . $supabaseKey, 'Accept: application/json'];

    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $supabaseUrl . '/rest/v1/branches?select=id,name&order=name.asc', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    $branches = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);

    if (empty($userId) && $userEmail) {
        $query = http_build_query(['select' => 'id', 'email' => 'eq.' . $userEmail, 'limit' => 1]);
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $query, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
        $userData = json_decode(curl_exec($ch), true) ?: [];
        $userId = $userData[0]['id'] ?? '';
        curl_close($ch);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $supabaseUrl . '/rest/v1/shortage_acknowledgements?select=*&order=form_date.desc,created_at.desc', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    $shortages = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);

    $shortages = array_values(array_filter($shortages, function ($record) use ($userId) {
        if (!$userId || !is_array($record)) return false;
        if (($record['created_by'] ?? '') === $userId) return true;
        return in_array($userId, array_filter(array_map('trim', explode(',', $record['shared_with'] ?? ''))), true);
    }));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortage Acknowledgement - THI Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style id="shortagePrintStyles">
    .shortage-printout {
        max-width: 900px;
        margin: 0 auto;
        border: 2px solid #000;
        background: #fff;
        color: #000;
        font-family: "Century Gothic", "CenturyGothic", "Apple Gothic", Arial, sans-serif
    }

    .shortage-printout table {
        width: 100%;
        border-collapse: collapse
    }

    .shortage-printout td,
    .shortage-printout th {
        border: 1px solid #000;
        padding: 6px 10px;
        vertical-align: middle
    }

    .shortage-printout .company-title {
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        letter-spacing: .5px
    }

    .shortage-printout .logo-cell {
        width: 22%;
        text-align: center
    }

    .shortage-printout .logo-cell img {
        max-width: 120px;
        max-height: 60px
    }

    .shortage-printout .logo-cell small {
        display: block;
        margin-top: 2px;
        font-size: 11px
    }

    .shortage-printout .form-title-cell {
        width: 46%;
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        color: #C0392B
    }

    .shortage-printout .doc-info-cell {
        width: 32%;
        font-size: 12px;
        text-align: center
    }

    .shortage-printout .page-row {
        border-top: 1px solid #000;
        margin-top: 4px;
        padding-top: 4px
    }

    .shortage-printout .content {
        padding: 14px 20px 24px
    }

    .shortage-printout .form-heading {
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        margin: 6px 0 20px;
        letter-spacing: .5px
    }

    .shortage-printout .field-row {
        display: flex;
        align-items: baseline;
        margin-bottom: 14px;
        font-size: 13px
    }

    .shortage-printout .field-label {
        white-space: nowrap;
        margin-right: 6px
    }

    .shortage-printout .field-fill {
        flex: 1;
        border-bottom: 1px dotted #000;
        min-height: 14px
    }

    .shortage-printout .two-col {
        display: flex;
        justify-content: space-between;
        gap: 40px
    }

    .shortage-printout .two-col .field-row {
        flex: 1
    }

    .shortage-printout .amount-words-lines {
        margin-bottom: 18px
    }

    .shortage-printout .amount-words-lines .field-fill {
        height: 18px;
        margin-bottom: 8px
    }

    .shortage-printout .comments-label {
        font-size: 13px;
        margin-bottom: 6px
    }

    .shortage-printout .lined-box {
        border: 1px solid #000;
        margin-bottom: 22px
    }

    .shortage-printout .lined-box .line {
        border-bottom: 1px solid #000;
        min-height: 26px;
        padding: 3px 6px;
        white-space: pre-wrap
    }

    .shortage-printout .lined-box .line:last-child {
        border-bottom: none
    }

    .shortage-printout .signoff-block {
        margin-bottom: 22px
    }

    .shortage-printout .signoff-block .role {
        font-weight: bold;
        font-size: 13px;
        margin-bottom: 8px
    }

    .shortage-printout .sign-row {
        display: flex;
        font-size: 13px;
        margin-bottom: 4px
    }

    .shortage-printout .name-fill {
        flex: 2;
        border-bottom: 1px dotted #000;
        margin: 0 8px;
        min-height: 14px
    }

    .shortage-printout .sig-fill {
        flex: 1;
        border-bottom: 1px dotted #000;
        min-height: 14px
    }

    .shortage-printout .signature-img {
        max-height: 40px;
        max-width: 100px;
        vertical-align: middle
    }

    .shortage-printout .footer-note {
        font-style: italic;
        font-size: 11px;
        margin-top: 10px
    }

    @media print {
        .shortage-printout {
            max-width: none;
            border: none
        }
    }

    .shortage-table thead th {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6c757d;
        background: #f8f9fa;
        border-bottom-width: 1px
    }

    .shortage-table tbody td {
        padding-top: .7rem;
        padding-bottom: .7rem
    }

    .shortage-status {
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

    .shortage-table-wrap {
        overflow: visible !important;
    }

    .shortage-table-wrap .dropdown.show {
        position: relative;
        z-index: 1080;
    }

    .shortage-table-wrap .dropdown-menu {
        z-index: 1090;
    }

    @media (max-width: 767.98px) {
        .shortage-table-wrap {
            overflow-x: auto !important;
            overflow-y: visible !important;
        }
    }
    </style>
</head>

<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php $activeMenu = 'shortage_acknowledgement'; include __DIR__ . '/partials/sidebar.php'; ?>
        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4"><button
                    class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button"
                    aria-label="Toggle sidebar"><i class="bi bi-list"></i></button><a
                    class="navbar-brand fw-semibold d-none d-sm-inline" href="#"><span id="pageTitle">Shortage
                        Acknowledgement</span></a>
                <div class="ms-auto d-flex align-items-center gap-3">
                </div>
                   <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Shortage Acknowledgement</h1>
                    <p class="text-muted small mb-0">Create, review and print shortage acknowledgement forms.</p>
                </section>
                <section class="row g-3 g-lg-4">
                    <div class="col-12 col-xl-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0 fw-semibold">New Shortage Acknowledgement</h2>
                            </div>
                            <div class="card-body">
                                <div id="shortageAlert" class="alert d-none py-2 px-3 mb-3"></div>
                                <form id="shortageForm" class="row g-3">
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Date</label><input
                                            class="form-control form-control-sm" id="formDate" type="date"
                                            value="<?php echo date('Y-m-d'); ?>" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Reference PCV
                                            no.</label><input class="form-control form-control-sm" id="referencePcv">
                                    </div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Attendant
                                            Name</label><input class="form-control form-control-sm" id="attendantName"
                                            value="<?php echo htmlspecialchars($userName); ?>" required></div>
                                    <div class="col-md-6"><label
                                            class="form-label small fw-semibold">Position</label><input
                                            class="form-control form-control-sm" id="attendantRole"
                                            value="<?php echo htmlspecialchars($userRole); ?>" readonly></div>
                                    <div class="col-md-6"><label
                                            class="form-label small fw-semibold">Department</label><input
                                            class="form-control form-control-sm" id="attendantDepartment"
                                            value="<?php echo htmlspecialchars($userDepartment); ?>" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Station
                                        </label><select class="form-select form-select-sm" id="branchId" required>
                                            <option value="">Select station</option>
                                            <?php foreach ($branches as $branch): ?><option
                                                value="<?php echo htmlspecialchars($branch['id']); ?>"
                                                data-name="<?php echo htmlspecialchars($branch['name']); ?>">
                                                <?php echo htmlspecialchars($branch['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Pump
                                            *</label><input class="form-control form-control-sm" id="pump" required>
                                    </div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Shift
                                            *</label><select class="form-select form-select-sm" id="shift" required>
                                            <option value="">Select shift</option>
                                            <option>Morning</option>
                                            <option>Afternoon</option>
                                            <option>Night</option>
                                            <option>Other</option>
                                        </select></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Amount of shortage
                                            (UGX) *</label><input class="form-control form-control-sm"
                                            id="shortageAmount" type="number" step="0.01" min="0" required></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Amount in
                                            words</label><input class="form-control form-control-sm" id="amountWords">
                                    </div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Share With (For
                                            Approval)</label><small class="text-muted d-block mb-1">First selected user
                                            is Supervisor; second is Manager.</small>
                                        <div class="position-relative"><input class="form-control form-control-sm"
                                                id="sharedWithInput" placeholder="Search users..." autocomplete="off">
                                            <div id="sharedWithDropdown" class="dropdown-menu w-100"
                                                style="position:absolute;z-index:1000;max-height:200px;overflow-y:auto">
                                            </div>
                                        </div>
                                        <div id="selectedUsers" class="mt-1"></div>
                                    </div>
                                    <div class="col-12"><label
                                            class="form-label small fw-semibold">Comments</label><textarea
                                            class="form-control form-control-sm" id="comments" rows="3"></textarea>
                                    </div>
                                    <div class="col-12"><label class="form-label small fw-semibold">How do you intend to
                                            pay off the shortage?</label><textarea class="form-control form-control-sm"
                                            id="paymentPlan" rows="3"></textarea></div>
                                    <div class="col-12 d-flex justify-content-end gap-2"><button type="reset"
                                            class="btn btn-sm btn-outline-secondary">Reset</button><button type="submit"
                                            class="btn btn-sm btn-primary" id="saveShortageBtn"><span
                                                class="spinner-border spinner-border-sm d-none"></span><span
                                                class="btn-label">Save</span></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0 fw-semibold">Saved Shortage Forms</h2>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive shortage-table-wrap">
                                    <table class="table table-sm table-hover align-middle shortage-table"
                                        id="shortagesTable">
                                        <thead>
                                            <tr>
                                                <th class="text-nowrap">Date</th>
                                                <th>Reference / Status</th>
                                                <th>Attendant</th>
                                                <th>Station</th>
                                                <th>Amount</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php if (!$shortages): ?><tr>
                                                <td colspan="6" class="text-center text-muted small py-3">No shortage
                                                    forms found.</td>
                                            </tr><?php else: foreach ($shortages as $record): ?><tr>
                                                <td class="text-nowrap">
                                                    <?php echo htmlspecialchars($record['form_date'] ?? '-'); ?></td>
                                                <td>
                                                    <div class="fw-semibold">
                                                        <?php echo htmlspecialchars($record['reference_pcv_no'] ?? '-'); ?>
                                                    </div>
                                                    <?php $status = strtolower($record['status'] ?? 'pending'); $statusClass = match ($status) { 'approved' => 'bg-success', 'checked' => 'bg-info text-dark', 'rejected' => 'bg-danger', default => 'bg-warning text-dark' }; ?><span
                                                        class="badge <?php echo $statusClass; ?> shortage-status"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['attendant_name'] ?? '-'); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['station_name'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($record['shortage_amount'] ?? '-'); ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="dropdown"><button
                                                            class="btn btn-sm btn-outline-secondary" type="button"
                                                            data-bs-toggle="dropdown" aria-expanded="false"
                                                            title="Form actions" aria-label="Form actions"><i
                                                                class="bi bi-three-dots-vertical"></i></button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><button class="dropdown-item" type="button"
                                                                    onclick="viewShortage(<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>)"><i
                                                                        class="bi bi-eye me-2"></i>View</button></li>
                                                            <li><button class="dropdown-item" type="button"
                                                                    onclick="printShortage(<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>)"><i
                                                                        class="bi bi-printer me-2"></i>Print</button>
                                                            </li>
                                                            <li><button class="dropdown-item" type="button"
                                                                    onclick="approveShortage(<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>)"><i
                                                                        class="bi bi-check-circle me-2"></i>Approve /
                                                                    Check</button></li>
                                                            <li><button class="dropdown-item text-danger" type="button"
                                                                    onclick="rejectShortage(<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>)"><i
                                                                        class="bi bi-x-circle me-2"></i>Reject</button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr><?php endforeach; endif; ?></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <div class="modal fade" id="shortageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Shortage Acknowledgement</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="shortageModalBody"></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" id="editShortage"
                        title="Edit form" aria-label="Edit form"><i class="bi bi-pencil"></i></button><button
                        type="button" class="btn btn-outline-danger" id="deleteShortage" title="Delete form"
                        aria-label="Delete form"><i class="bi bi-trash"></i></button><button type="button"
                        class="btn btn-primary" id="printShortage" title="Print form" aria-label="Print form"><i
                            class="bi bi-printer"></i></button><button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>
    <script src="app.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(function() {
        $('#shortagesTable').DataTable({
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            order: [],
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Search shortage forms...'
            },
            columnDefs: [{
                orderable: false,
                targets: 5
            }]
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
    let attendantProfile = {
        role: <?php echo json_encode($userRole); ?>,
        signature: '',
        full_name: <?php echo json_encode($userName); ?>
    };
    let allUsers = [],
        selectedApprovers = [],
        selectedRecord = null;
    const form = document.getElementById('shortageForm'),
        alertBox = document.getElementById('shortageAlert');
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    } [char]));

    function showFeedbackModal(message, type = 'info') {
        let modal = document.getElementById('feedbackModal');
        if (!modal) {
            document.body.insertAdjacentHTML('beforeend',
                `<div class="modal fade" id="feedbackModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="feedbackModalTitle">Message</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="feedbackModalMessage"></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button></div></div></div></div>`
                );
            modal = document.getElementById('feedbackModal');
        }
        document.getElementById('feedbackModalTitle').textContent = type === 'danger' ? 'Error' : type === 'success' ?
            'Success' : 'Message';
        document.getElementById('feedbackModalMessage').textContent = message;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    window.alert = message => showFeedbackModal(message);

    function getApprovals(item) {
        if (Array.isArray(item.approved_by_users)) return item.approved_by_users;
        try {
            const value = item.approved_by_users ? JSON.parse(item.approved_by_users) : [];
            return Array.isArray(value) ? value : (value ? [value] : []);
        } catch (error) {
            return [];
        }
    }
    async function loadActiveUser() {
        const query = supabase.from('users').select('id,full_name,role,signature').limit(1);
        const result = activeUserId ? await query.eq('id', activeUserId).single() : await query.eq('email',
            userEmail).single();
        if (!result.error && result.data) {
            activeUserId = result.data.id;
            attendantProfile = result.data;
            document.getElementById('attendantName').value = result.data.full_name || '';
            document.getElementById('attendantRole').value = result.data.role || '';
        }
    }
    async function loadUsers() {
        const {
            data,
            error
        } = await supabase.from('users').select('id,email,full_name').order('full_name', {
            ascending: true
        });
        if (!error) allUsers = data || [];
    }
    loadActiveUser();
    loadUsers();
    const search = document.getElementById('sharedWithInput'),
        dropdown = document.getElementById('sharedWithDropdown'),
        selected = document.getElementById('selectedUsers');

    function renderApprovers() {
        selected.innerHTML = selectedApprovers.map((user, index) =>
            `<span class="badge bg-primary me-1 mb-1">${index + 1}. ${esc(user.full_name || user.email)} <button type="button" class="btn-close btn-close-white ms-1" data-user-id="${esc(user.id)}"></button></span>`
        ).join('');
        selected.querySelectorAll('.btn-close').forEach(button => button.addEventListener('click', () => {
            selectedApprovers = selectedApprovers.filter(user => user.id !== button.dataset.userId);
            renderApprovers();
        }));
    }
    search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        dropdown.innerHTML = '';
        if (term.length < 2 || selectedApprovers.length >= 2) {
            dropdown.classList.remove('show');
            return;
        }
        allUsers.filter(user => user.id !== activeUserId && !selectedApprovers.some(selectedUser => selectedUser
                .id === user.id) && `${user.full_name||''} ${user.email||''}`.toLowerCase().includes(term))
            .forEach(user => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'dropdown-item';
                option.textContent = `${user.full_name||user.email} (${user.email})`;
                option.onclick = () => {
                    selectedApprovers.push(user);
                    search.value = '';
                    dropdown.classList.remove('show');
                    renderApprovers();
                };
                dropdown.appendChild(option);
            });
        dropdown.classList.toggle('show', dropdown.children.length > 0);
    });

    function profiles(item) {
        const ids = [...(item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean), ...getApprovals(item)
            .map(approval => approval.user_id || approval).filter(Boolean)
        ];
        return [...new Set(ids)];
    }
    async function loadProfiles(item) {
        const ids = profiles(item);
        if (ids.length) {
            const {
                data
            } = await supabase.from('users').select('id,full_name,email,role,signature').in('id', ids);
            item.profiles = ids.map(id => (data || []).find(user => user.id === id) || {
                id
            });
        } else {
            item.profiles = [];
        }
        if (item.created_by) {
            const {
                data
            } = await supabase.from('users').select('id,full_name,role,signature').eq('id', item.created_by)
                .single();
            if (data) attendantProfile = data;
        }
    }

    function approvalUser(item, type, index) {
        const approvals = getApprovals(item);
        const approval = type === 'approved' ? (approvals.find(record => record.approval_type === 'approved') ||
            approvals[1]) : (approvals.find(record => record.approval_type === 'checked') || approvals[0]);
        return approval ? item.profiles?.find(user => user.id === (approval.user_id || approval)) : null;
    }

    function requestApprovalPassword() {
        return new Promise(resolve => {
            const modalId = 'approvalPasswordModal';
            document.getElementById(modalId)?.remove();
            document.body.insertAdjacentHTML('beforeend',
                `<div class="modal fade" id="${modalId}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Approval Password</h5><button type="button" class="btn-close" data-password-cancel></button></div><form id="approvalPasswordForm"><div class="modal-body"><label class="form-label" for="approvalTempPassword">Enter your password to approve or check this form</label><input type="password" class="form-control" id="approvalTempPassword" name="temp_password" required autocomplete="current-password"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-password-cancel>Cancel</button><button type="submit" class="btn btn-primary">Verify</button></div></form></div></div></div>`
                );
            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);
            const finish = value => {
                modal.hide();
                resolve(value);
                setTimeout(() => modalElement.remove(), 300);
            };
            modalElement.querySelectorAll('[data-password-cancel]').forEach(button => button.addEventListener(
                'click', () => finish(null)));
            modalElement.querySelector('form').addEventListener('submit', event => {
                event.preventDefault();
                finish(modalElement.querySelector('[name="temp_password"]').value);
            });
            modalElement.addEventListener('hidden.bs.modal', () => modalElement.remove(), {
                once: true
            });
            modal.show();
        });
    }
    async function verifyApprovalPassword() {
        const password = await requestApprovalPassword();
        if (password === null || password === '') return false;
        const {
            data,
            error
        } = await supabase.from('users').select('temp_password').eq('id', activeUserId).single();
        if (error || !data || password !== data.temp_password) {
            alert('Incorrect password.');
            return false;
        }
        return true;
    }

    function details(item) {
        const supervisor = approvalUser(item, 'checked', 0),
            manager = approvalUser(item, 'approved', 1);
        return `<div class="row g-3"><div class="col-md-6"><small class="text-muted">Date</small><div>${esc(item.form_date)}</div></div><div class="col-md-6"><small class="text-muted">Reference PCV</small><div>${esc(item.reference_pcv_no||'-')}</div></div><div class="col-md-6"><small class="text-muted">Attendant</small><div>${esc(item.attendant_name)}</div></div><div class="col-md-6"><small class="text-muted">Station / Pump</small><div>${esc(item.station_name)} / ${esc(item.pump)}</div></div><div class="col-md-6"><small class="text-muted">Shift</small><div>${esc(item.shift)}</div></div><div class="col-md-6"><small class="text-muted">Shortage</small><div>UGX ${esc(item.shortage_amount)}</div></div><div class="col-12"><small class="text-muted">Amount in words</small><div>${esc(item.amount_words||'-')}</div></div><div class="col-12"><small class="text-muted">Comments</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${esc(item.comments||'-')}</div></div><div class="col-12"><small class="text-muted">Payment plan</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${esc(item.payment_plan||'-')}</div></div><div class="col-md-6"><small class="text-muted">Supervisor / Checked By</small><div>${esc(supervisor?.full_name||'-')}</div></div><div class="col-md-6"><small class="text-muted">Manager / Approved By</small><div>${esc(manager?.full_name||'-')}</div></div></div>`;
    }

    function lines(value, count) {
        return Array.from({
            length: count
        }, (_, index) => `<div class="line">${index===0?esc(value||''):''}</div>`).join('');
    }

    function signature(user) {
        return user?.signature ? `<img class="signature-img" src="${esc(user.signature)}" alt="Signature">` : '';
    }

    function printHtml(item) {
        const supervisor = approvalUser(item, 'checked', 0),
            manager = approvalUser(item, 'approved', 1);
        return `<div class="shortage-printout"><table class="header-table"><tr><td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td></tr><tr><td class="logo-cell"><img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo"><small><i>Reliability Redefined</i></small></td><td class="form-title-cell">SHORTAGE ACKNOWLEDGEMENT FORM</td><td class="doc-info-cell"><div>TEX-RET-FRM-006, Ver 000</div><div>Issue Date: 1<sup>st</sup> Nov 2024</div><div class="page-row">Page 1 of 1</div></td></tr></table><div class="content"><div class="form-heading">SHORTAGE ACKNOWLEDGEMENT FORM</div><div class="two-col"><div class="field-row"><span class="field-label">DATE</span><span class="field-fill">${esc(item.form_date)}</span></div><div class="field-row"><span class="field-label">Reference PCV no.</span><span class="field-fill">${esc(item.reference_pcv_no||'')}</span></div></div><div class="field-row"><span class="field-label">ATTENDANT NAME</span><span class="field-fill">${esc(item.attendant_name||'')}</span></div><div class="field-row"><span class="field-label">PUMP</span><span class="field-fill">${esc(item.pump||'')}</span></div><div class="field-row"><span class="field-label">SHIFT</span><span class="field-fill">${esc(item.shift||'')}</span></div><div class="field-row"><span class="field-label">AMOUNT OF SHORTAGE&nbsp;&nbsp;UGX</span><span class="field-fill">${esc(item.shortage_amount||'')}</span></div><div class="amount-words-lines"><div class="field-row" style="margin-bottom:8px"><span class="field-label">AMOUNT IN WORDS</span></div><div class="field-fill">${esc(item.amount_words||'')}</div><div class="field-fill"></div></div><div class="comments-label">COMMENTS (How did you get the shortage)</div><div class="lined-box">${lines(item.comments,3)}</div><div class="comments-label"><b>How do you intend to pay off the shortage?</b></div><div class="lined-box">${lines(item.payment_plan,3)}</div><div class="signoff-block"><div class="role">ATTENDANT</div><div class="sign-row"><span>NAME</span><span class="name-fill">${esc(item.attendant_name||'')}</span><span>POSITION</span><span class="name-fill">${esc(attendantProfile.role||'')}</span><span>SIGNATURE</span><span class="sig-fill">${signature(attendantProfile)}</span></div></div><div class="signoff-block"><div class="role">SUPERVISOR</div><div class="sign-row"><span>NAME</span><span class="name-fill">${esc(supervisor?.full_name||'')}</span><span>POSITION</span><span class="name-fill">${esc(supervisor?.role||'')}</span><span>SIGNATURE</span><span class="sig-fill">${signature(supervisor)}</span></div></div><div class="signoff-block"><div class="role">MANAGER</div><div class="sign-row"><span>NAME</span><span class="name-fill">${esc(manager?.full_name||'')}</span><span>POSITION</span><span class="name-fill">${esc(manager?.role||'')}</span><span>SIGNATURE</span><span class="sig-fill">${signature(manager)}</span></div></div><div class="footer-note">Texol Energies. Shortage Acknowledgement Form</div></div></div>`;
    }
    const originalShortagePrintHtml = printHtml;
    printHtml = item => originalShortagePrintHtml(item).replace(
        /<span>POSITION<\/span><span class="name-fill">.*?<\/span>/g, '');

    async function prepare(item) {
        await loadActiveUser();
        await loadProfiles(item);
    }
    window.viewShortage = async item => {
        await prepare(item);
        selectedRecord = item;
        document.getElementById('shortageModalBody').innerHTML = details(item);
        const locked = getApprovals(item).length > 0 || ['checked', 'approved'].includes(item.status);
        document.getElementById('editShortage').disabled = locked;
        new bootstrap.Modal(document.getElementById('shortageModal')).show();
    };
    window.printShortage = async item => {
        await prepare(item);
        const win = window.open('', '_blank', 'width=900,height=1200');
        if (!win) {
            alert('Please allow pop-ups to print.');
            return;
        }
        win.document.write(
            `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>TEX-RET-FRM-006 Shortage Acknowledgement Form</title><style>${document.getElementById('shortagePrintStyles').textContent}</style></head><body>${printHtml(item)}</body></html>`
        );
        win.document.close();
        win.focus();
        win.onload = () => win.print();
    };
    window.approveShortage = async item => {
        if (['approved', 'rejected'].includes(item.status)) {
            alert('This form is already closed.');
            return;
        }
        const ids = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean).slice(0, 2),
            index = ids.indexOf(activeUserId);
        if (index < 0) {
            alert('You are not assigned to approve this form.');
            return;
        }
        const approvals = getApprovals(item);
        if (approvals.some(record => (record.user_id || record) === activeUserId)) {
            alert('You have already approved this form.');
            return;
        }
        if (index === 1 && !approvals.some(record => (record.user_id || record) === ids[0])) {
            alert('The Supervisor must check this form first.');
            return;
        }
        if (!await verifyApprovalPassword()) return;
        approvals.push({
            user_id: activeUserId,
            approval_type: index === 0 ? 'checked' : 'approved',
            approved_at: new Date().toISOString()
        });
        const {
            error
        } = await supabase.from('shortage_acknowledgements').update({
            approved_by_users: approvals,
            status: index === 1 ? 'approved' : 'checked'
        }).eq('id', item.id);
        if (error) {
            alert(error.message);
            return;
        }
        location.reload();
    };
    window.rejectShortage = async item => {
        if (getApprovals(item).length || ['checked', 'approved'].includes(item.status)) {
            alert('This form cannot be rejected after approval.');
            return;
        }
        const ids = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean).slice(0, 2);
        if (!ids.includes(activeUserId)) {
            alert('Only assigned approvers can reject this form.');
            return;
        }
        if (!confirm('Reject this shortage acknowledgement?')) return;
        const {
            error
        } = await supabase.from('shortage_acknowledgements').update({
            status: 'rejected'
        }).eq('id', item.id);
        if (error) {
            alert(error.message);
            return;
        }
        location.reload();
    };
    window.deleteShortage = async item => {
        if (!confirm('Permanently delete this form?')) return;
        const {
            error
        } = await supabase.from('shortage_acknowledgements').delete().eq('id', item.id);
        if (error) {
            alert(error.message);
            return;
        }
        location.reload();
    };
    window.editShortageForm = async item => {
        if (getApprovals(item).length || ['checked', 'approved'].includes(item.status)) {
            alert('This form cannot be edited after approval.');
            return;
        }
        await loadProfiles(item);
        const ids = (item.shared_with || '').split(',').map(id => id.trim()).filter(Boolean);
        document.getElementById('shortageModalBody').innerHTML =
            `<form id="editShortageForm" class="row g-3"><div class="col-md-6"><label class="form-label">Date</label><input class="form-control form-control-sm" id="editDate" type="date" value="${esc(item.form_date)}" required></div><div class="col-md-6"><label class="form-label">Reference PCV no.</label><input class="form-control form-control-sm" id="editReference" value="${esc(item.reference_pcv_no||'')}"></div><div class="col-md-6"><label class="form-label">Attendant Name</label><input class="form-control form-control-sm" id="editAttendant" value="${esc(item.attendant_name)}" required></div><div class="col-md-6"><label class="form-label">Station / Pump</label><select class="form-select form-select-sm" id="editBranch">${Array.from(document.getElementById('branchId').options).map(option=>`<option value="${esc(option.value)}" ${option.value==item.branch_id?'selected':''}>${esc(option.textContent.trim())}</option>`).join('')}</select></div><div class="col-md-6"><label class="form-label">Pump</label><input class="form-control form-control-sm" id="editPump" value="${esc(item.pump)}" required></div><div class="col-md-6"><label class="form-label">Shift</label><input class="form-control form-control-sm" id="editShift" value="${esc(item.shift)}" required></div><div class="col-md-6"><label class="form-label">Shortage amount</label><input class="form-control form-control-sm" id="editAmount" type="number" step="0.01" value="${esc(item.shortage_amount)}" required></div><div class="col-12"><label class="form-label">Amount in words</label><input class="form-control form-control-sm" id="editWords" value="${esc(item.amount_words||'')}"></div><div class="col-12"><label class="form-label">Comments</label><textarea class="form-control form-control-sm" id="editComments">${esc(item.comments||'')}</textarea></div><div class="col-12"><label class="form-label">Payment plan</label><textarea class="form-control form-control-sm" id="editPayment">${esc(item.payment_plan||'')}</textarea></div><div class="col-12 text-end"><button type="button" class="btn btn-sm btn-secondary" id="cancelShortageEdit">Cancel</button><button class="btn btn-sm btn-primary ms-2">Save Changes</button></div></form>`;
        document.getElementById('cancelShortageEdit').onclick = () => window.viewShortage(item);
        document.getElementById('editShortageForm').onsubmit = async event => {
            event.preventDefault();
            const branch = document.getElementById('editBranch').selectedOptions[0];
            const update = {
                form_date: document.getElementById('editDate').value,
                reference_pcv_no: document.getElementById('editReference').value.trim(),
                attendant_name: document.getElementById('editAttendant').value.trim(),
                branch_id: branch.value,
                station_name: branch.textContent.trim(),
                pump: document.getElementById('editPump').value.trim(),
                shift: document.getElementById('editShift').value,
                shortage_amount: document.getElementById('editAmount').value,
                amount_words: document.getElementById('editWords').value.trim(),
                comments: document.getElementById('editComments').value.trim(),
                payment_plan: document.getElementById('editPayment').value.trim(),
                shared_with: ids.join(','),
                approved_by_users: item.approved_by_users || [],
                status: item.status || 'pending'
            };
            const result = await supabase.from('shortage_acknowledgements').update(update).eq('id', item
                .id);
            if (result.error) {
                alert(result.error.message);
                return;
            }
            location.reload();
        };
    };
    document.getElementById('printShortage').onclick = () => selectedRecord && window.printShortage(selectedRecord);
    document.getElementById('editShortage').onclick = () => selectedRecord && window.editShortageForm(selectedRecord);
    document.getElementById('deleteShortage').onclick = () => selectedRecord && window.deleteShortage(selectedRecord);
    let submittingShortage = false;
    form.onsubmit = async event => {
        event.preventDefault();
        if (submittingShortage) return;
        submittingShortage = true;
        const option = document.getElementById('branchId').selectedOptions[0],
            button = document.getElementById('saveShortageBtn');
        const record = {
            form_date: document.getElementById('formDate').value,
            reference_pcv_no: document.getElementById('referencePcv').value.trim(),
            attendant_name: document.getElementById('attendantName').value.trim(),
            department: document.getElementById('attendantDepartment').value.trim(),
            branch_id: option.value,
            station_name: option.dataset.name || option.textContent,
            pump: document.getElementById('pump').value.trim(),
            shift: document.getElementById('shift').value,
            shortage_amount: document.getElementById('shortageAmount').value,
            amount_words: document.getElementById('amountWords').value.trim(),
            comments: document.getElementById('comments').value.trim(),
            payment_plan: document.getElementById('paymentPlan').value.trim(),
            shared_with: selectedApprovers.map(user => user.id).join(','),
            approved_by_users: [],
            status: 'pending',
            created_by: activeUserId || null
        };
        button.disabled = true;
        button.querySelector('.spinner-border').classList.remove('d-none');
        button.querySelector('.btn-label').textContent = 'Saving...';
        try {
            const {
                error
            } = await supabase.from('shortage_acknowledgements').insert(record);
            if (error) throw error;
            location.reload();
        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
            button.querySelector('.spinner-border').classList.add('d-none');
            button.querySelector('.btn-label').textContent = 'Save';
            submittingShortage = false;
        }
    };
    </script>
</body>

</html>