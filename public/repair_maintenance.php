<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_email'])) { header('Location: login'); exit; }
$branches=[]; $records=[]; $email=$_SESSION['user_email']??''; $name=$_SESSION['user_name']??$email; $department=$_SESSION['user_department']??$_SESSION['department']??''; $role=$_SESSION['user_role']??''; $userId=$_SESSION['user_id']??'';
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
 $url=rtrim(SUPABASE_URL,'/'); $key=SUPABASE_ANON_KEY; $headers=['apikey: '.$key,'Authorization: Bearer '.$key,'Accept: application/json'];
 $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>$url.'/rest/v1/branches?select=id,name&order=name.asc',CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers]); $branches=json_decode(curl_exec($ch),true)?:[]; curl_close($ch);
 if (!$userId && $email) { $q=http_build_query(['select'=>'id','email'=>'eq.'.$email,'limit'=>1]); $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>$url.'/rest/v1/users?'.$q,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers]); $u=json_decode(curl_exec($ch),true)?:[]; $userId=$u[0]['id']??''; curl_close($ch); }
 $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>$url.'/rest/v1/repair_maintenance_forms?select=*&order=form_date.desc,created_at.desc',CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers]); $records=json_decode(curl_exec($ch),true)?:[]; curl_close($ch);
 $records=array_values(array_filter($records,function($r)use($userId){if(!$userId||!is_array($r))return false;return ($r['created_by']??'')===$userId||in_array($userId,array_filter(array_map('trim',explode(',',$r['shared_with']??''))),true);}));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Repair and Maintenance - THI Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style id="repairPrintStyles">
    .repair-printout {
        max-width: 900px;
        margin: 0 auto;
        border: 2px solid #000;
        background: #fff;
        color: #000;
        font-family: "Century Gothic", "CenturyGothic", "Apple Gothic", Arial, sans-serif
    }

    .repair-printout table {
        width: 100%;
        border-collapse: collapse
    }

    .repair-printout td,
    .repair-printout th {
        border: 1px solid #000;
        padding: 6px 10px;
        vertical-align: middle
    }

    .repair-printout .company-title {
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        letter-spacing: .5px
    }

    .repair-printout .logo-cell {
        width: 22%;
        text-align: center
    }

    .repair-printout .logo-cell img {
        max-width: 120px;
        max-height: 60px
    }

    .repair-printout .logo-cell small {
        display: block;
        margin-top: 2px;
        font-size: 11px
    }

    .repair-printout .form-title-cell {
        width: 46%;
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        color: #C0392B
    }

    .repair-printout .doc-info-cell {
        width: 32%;
        font-size: 12px;
        text-align: center
    }

    .repair-printout .page-row {
        border-top: 1px solid #000;
        margin-top: 4px;
        padding-top: 4px
    }

    .repair-printout .content {
        padding: 14px 20px 24px
    }

    .repair-printout .form-heading {
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        margin: 6px 0 20px;
        letter-spacing: .5px
    }

    .repair-printout .field-row {
        display: flex;
        align-items: baseline;
        margin-bottom: 16px;
        font-size: 13px;
        font-weight: bold
    }

    .repair-printout .field-label {
        white-space: nowrap;
        margin-right: 6px
    }

    .repair-printout .field-fill {
        flex: 1;
        border-bottom: 1px dotted #000;
        min-height: 14px;
        white-space: pre-wrap
    }

    .repair-printout .section-label {
        font-size: 13px;
        margin-bottom: 6px
    }

    .repair-printout .lined-box {
        border: 1px solid #000;
        margin-bottom: 20px;
        min-height: 90px;
        white-space: pre-wrap;
        padding: 5px
    }

    .repair-printout .technician-row {
        font-size: 13px;
        font-weight: bold;
        margin-bottom: 16px;
        display: flex;
        align-items: baseline
    }

    .repair-printout .technician-row .field-fill {
        flex: 1;
        margin-left: 6px
    }

    .repair-printout .for-label {
        font-size: 13px;
        margin-bottom: 10px
    }

    .repair-printout .supervisor-row {
        display: flex;
        align-items: baseline;
        font-size: 13px;
        margin-bottom: 24px;
        gap: 20px;
        flex-wrap: wrap
    }

    .repair-printout .sup-fill {
        border-bottom: 1px dotted #000;
        min-width: 200px;
        min-height: 14px
    }

    .repair-printout .stamp-fill,
    .repair-printout .sign-fill {
        border-bottom: 1px dotted #000;
        min-width: 100px;
        min-height: 14px
    }

    .repair-printout .approval-table {
        margin-top: 10px
    }

    .repair-printout .approval-table td {
        font-size: 13px;
        height: 26px;
        vertical-align: top
    }

    .repair-printout .header-cell {
        font-weight: bold
    }

    .repair-printout .signature-img {
        max-height: 40px;
        max-width: 100px;
        vertical-align: middle
    }

    .repair-printout .footer-note {
        font-style: italic;
        font-size: 11px;
        margin-top: 18px
    }

    @media print {
        .repair-printout {
            max-width: none;
            border: none
        }
    }

    .repair-table-wrap {
        overflow: visible !important;
    }

    .repair-table-wrap .dropdown.show {
        position: relative;
        z-index: 1080;
    }

    .repair-table-wrap .dropdown-menu {
        z-index: 1090;
    }

    @media (max-width: 767.98px) {
        .repair-table-wrap {
            overflow-x: auto !important;
            overflow-y: visible !important;
        }
    }
    </style>
</head>

<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php $activeMenu='repair_maintenance';include __DIR__.'/partials/sidebar.php'; ?><div
            class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4"><button
                    class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button"><i
                        class="bi bi-list"></i></button><a class="navbar-brand fw-semibold d-none d-sm-inline"
                    href="#"><span>Repair and Maintenance</span></a>
                <div class="ms-auto d-flex align-items-center gap-3">
                </div>
                   <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Repair and Maintenance</h1>
                    <p class="text-muted small mb-0">Create, review and print repair and maintenance forms.</p>
                </section>
                <section class="row g-3 g-lg-4">
                    <div class="col-12 col-xl-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0">New Repair and Maintenance Form</h2>
                            </div>
                            <div class="card-body">
                                <form id="repairForm" class="row g-3">
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Date</label><input
                                            id="formDate" class="form-control form-control-sm" type="date"
                                            value="<?php echo date('Y-m-d'); ?>" required></div>
                                    <div class="col-md-6"><label
                                            class="form-label small fw-semibold">Station</label><select id="branchId"
                                            class="form-select form-select-sm" required>
                                            <option value="">Select station</option><?php foreach($branches as $b):?>
                                            <option value="<?php echo htmlspecialchars($b['id']);?>"
                                                data-name="<?php echo htmlspecialchars($b['name']);?>">
                                                <?php echo htmlspecialchars($b['name']);?></option><?php endforeach;?>
                                        </select></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Type of
                                            repair</label><textarea id="repairType" class="form-control form-control-sm"
                                            rows="3" required
                                            placeholder="Specify pump, electrical works, generator, etc."></textarea>
                                    </div>
                                    <div class="col-12"><label
                                            class="form-label small fw-semibold">Remarks</label><textarea id="remarks"
                                            class="form-control form-control-sm" rows="3"></textarea></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Technician
                                            name</label><input id="technicianName" class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($name);?>" required></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Technician
                                            role</label><input id="technicianRole" class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($role);?>" readonly></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Remarks and
                                            requirements</label><textarea id="requirements"
                                            class="form-control form-control-sm" rows="3"></textarea></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">Share With (For
                                            Approval)</label><small class="text-muted d-block mb-1">First selected user
                                            is Reviewed By; second is Approved By.</small>
                                        <div class="position-relative"><input id="sharedInput"
                                                class="form-control form-control-sm" placeholder="Search users..."
                                                autocomplete="off">
                                            <div id="sharedDropdown" class="dropdown-menu w-100"
                                                style="position:absolute;z-index:1000;max-height:200px;overflow-y:auto">
                                            </div>
                                        </div>
                                        <div id="selectedApprovers" class="mt-1"></div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end gap-2"><button type="reset"
                                            class="btn btn-sm btn-outline-secondary">Reset</button><button
                                            class="btn btn-sm btn-primary" id="saveBtn"><span
                                                class="spinner-border spinner-border-sm d-none"></span><span
                                                class="btn-label">Save Form</span></button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h2 class="h6 mb-0">Saved Repair Forms</h2>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive repair-table-wrap">
                                    <table id="repairTable" class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Station / Status</th>
                                                <th>Technician</th>
                                                <th>Type of Repair</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php if(!$records):?><tr>
                                                <td class="text-center text-muted py-3">No repair forms found.</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr><?php else:foreach($records as $r):?><tr>
                                                <td class="text-nowrap">
                                                    <?php echo htmlspecialchars($r['form_date']??'-');?></td>
                                                <td>
                                                    <div class="fw-semibold">
                                                        <?php echo htmlspecialchars($r['station_name']??'-');?></div>
                                                    <?php $s=strtolower($r['status']??'pending');$c=$s==='approved'?'bg-success':($s==='checked'?'bg-info text-dark':($s==='rejected'?'bg-danger':'bg-warning text-dark'));?><span
                                                        class="badge <?php echo $c;?>"><?php echo htmlspecialchars(ucfirst($s));?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['technician_name']??'-');?></td>
                                                <td><?php echo htmlspecialchars(mb_strimwidth($r['repair_type']??'-',0,45,'...'));?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="dropdown"><button
                                                            class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="dropdown" aria-label="Form actions"
                                                            title="Form actions"><i
                                                                class="bi bi-three-dots-vertical"></i></button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><button class="dropdown-item"
                                                                    onclick="viewRepair(<?php echo htmlspecialchars(json_encode($r),ENT_QUOTES,'UTF-8');?>)"><i
                                                                        class="bi bi-eye me-2"></i>View</button></li>
                                                            <li><button class="dropdown-item"
                                                                    onclick="printRepair(<?php echo htmlspecialchars(json_encode($r),ENT_QUOTES,'UTF-8');?>)"><i
                                                                        class="bi bi-printer me-2"></i>Print</button>
                                                            </li>
                                                            <li><button class="dropdown-item"
                                                                    onclick="approveRepair(<?php echo htmlspecialchars(json_encode($r),ENT_QUOTES,'UTF-8');?>)"><i
                                                                        class="bi bi-check-circle me-2"></i>Approve /
                                                                    Check</button></li>
                                                            <li><button class="dropdown-item text-danger"
                                                                    onclick="rejectRepair(<?php echo htmlspecialchars(json_encode($r),ENT_QUOTES,'UTF-8');?>)"><i
                                                                        class="bi bi-x-circle me-2"></i>Reject</button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr><?php endforeach;endif;?></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <div class="modal fade" id="repairModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Repair and Maintenance</h5><button class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="repairModalBody"></div>
                <div class="modal-footer"><button class="btn btn-outline-primary" id="editRepair" title="Edit form"><i
                            class="bi bi-pencil"></i></button><button class="btn btn-outline-danger" id="deleteRepair"
                        title="Delete form"><i class="bi bi-trash"></i></button><button class="btn btn-primary"
                        id="printRepair" title="Print form"><i class="bi bi-printer"></i></button><button
                        class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(function() {
        $('#repairTable').DataTable({
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            order: [],
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Search repair forms...'
            },
            columnDefs: [{
                orderable: false,
                targets: 4
            }]
        });
    });
    </script>
    <script type="module">
    import {
        createClient
    } from 'https://esm.sh/@supabase/supabase-js@2';
    const supabase = createClient(<?php echo json_encode(defined('SUPABASE_URL')?SUPABASE_URL:'');?>,
        <?php echo json_encode(defined('SUPABASE_ANON_KEY')?SUPABASE_ANON_KEY:'');?>);
    let activeUserId = <?php echo json_encode($userId);?>,
        users = [],
        approvers = [],
        selected = null;
    let technicianProfile = {
        role: <?php echo json_encode($role);?>,
        signature: '',
        full_name: <?php echo json_encode($name);?>
    };
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    } [c]));

    function approvals(item) {
        if (Array.isArray(item.approved_by_users)) return item.approved_by_users;
        try {
            const v = item.approved_by_users ? JSON.parse(item.approved_by_users) : [];
            return Array.isArray(v) ? v : (v ? [v] : []);
        } catch (e) {
            return [];
        }
    }

    function modalMessage(message) {
        let m = document.getElementById('messageModal');
        if (!m) {
            document.body.insertAdjacentHTML('beforeend',
                '<div class="modal fade" id="messageModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Message</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="messageText"></div><div class="modal-footer"><button class="btn btn-primary" data-bs-dismiss="modal">OK</button></div></div></div></div>'
            );
            m = document.getElementById('messageModal');
        }
        document.getElementById('messageText').textContent = message;
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
    window.alert = modalMessage;
    async function loadUser() {
        const q = supabase.from('users').select('id,full_name,role,signature').limit(1),
            r = activeUserId ? await q.eq('id', activeUserId).single() : await q.eq('email',
                '<?php echo addslashes($email);?>').single();
        if (!r.error && r.data) {
            activeUserId = r.data.id;
            technicianProfile = r.data;
            document.getElementById('technicianName').value = r.data.full_name || '';
            document.getElementById('technicianRole').value = r.data.role || '';
        }
    }
    async function loadUsers() {
        const r = await supabase.from('users').select('id,email,full_name').order('full_name');
        if (!r.error) users = r.data || [];
    }
    loadUser();
    loadUsers();
    const search = document.getElementById('sharedInput'),
        drop = document.getElementById('sharedDropdown'),
        selectedView = document.getElementById('selectedApprovers');
    search.oninput = () => {
        const t = search.value.toLowerCase();
        drop.innerHTML = '';
        if (t.length < 2 || approvers.length >= 2) {
            drop.classList.remove('show');
            return;
        }
        users.filter(u => u.id !== activeUserId && !approvers.some(a => a.id === u.id) &&
            `${u.full_name||''} ${u.email||''}`.toLowerCase().includes(t)).forEach(u => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'dropdown-item';
            b.textContent = `${u.full_name||u.email} (${u.email})`;
            b.onclick = () => {
                approvers.push(u);
                search.value = '';
                drop.classList.remove('show');
                selectedView.innerHTML = approvers.map((a, i) =>
                    `<span class="badge bg-primary me-1">${i+1}. ${esc(a.full_name||a.email)}</span>`
                ).join('');
            };
            drop.appendChild(b);
        });
        drop.classList.toggle('show', drop.children.length > 0);
    };

    function profileIds(item) {
        return [...(item.shared_with || '').split(',').map(x => x.trim()).filter(Boolean), ...approvals(item).map(x => x
            .user_id || x).filter(Boolean)];
    }
    async function profiles(item) {
        const ids = [...new Set(profileIds(item))];
        const r = ids.length ? await supabase.from('users').select('id,full_name,email,role,signature').in('id',
            ids) : {
            data: []
        };
        item.profiles = ids.map(id => (r.data || []).find(x => x.id === id) || {
            id
        });
        if (item.created_by) {
            const p = await supabase.from('users').select('id,full_name,role,signature').eq('id', item.created_by)
                .single();
            if (p.data) technicianProfile = p.data;
        }
    }

    function approver(item, type) {
        const a = approvals(item).find(x => x.approval_type === type) || approvals(item)[type === 'approved' ? 1 : 0];
        return a ? item.profiles?.find(u => u.id === (a.user_id || a)) : null;
    }

    function detail(item) {
        const sup = approver(item, 'checked'),
            man = approver(item, 'approved');
        return `<div class="row g-3"><div class="col-md-6"><small class="text-muted">Date</small><div>${esc(item.form_date)}</div></div><div class="col-md-6"><small class="text-muted">Station</small><div>${esc(item.station_name)}</div></div><div class="col-md-6"><small class="text-muted">Technician</small><div>${esc(item.technician_name)}</div></div><div class="col-md-6"><small class="text-muted">Status</small><div>${esc(item.status||'pending')}</div></div><div class="col-12"><small class="text-muted">Type of repair</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${esc(item.repair_type)}</div></div><div class="col-12"><small class="text-muted">Remarks</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${esc(item.remarks||'-')}</div></div><div class="col-12"><small class="text-muted">Remarks and requirements</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${esc(item.requirements||'-')}</div></div><div class="col-md-6"><small class="text-muted">Reviewed By</small><div>${esc(sup?.full_name||'-')}</div></div><div class="col-md-6"><small class="text-muted">Approved By</small><div>${esc(man?.full_name||'-')}</div></div></div>`;
    }

    function sig(u) {
        return u?.signature ? `<img class="signature-img" src="${esc(u.signature)}" alt="Signature">` : '';
    }

    function printHtml(item) {
        const sup = approver(item, 'checked'),
            man = approver(item, 'approved');
        return `<div class="repair-printout"><table class="header-table"><tr><td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td></tr><tr><td class="logo-cell"><img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg"><small><i>Reliability Redefined</i></small></td><td class="form-title-cell">Repair and Maintenance Form</td><td class="doc-info-cell"><div>TEX-RET-FRM-004, Ver 000</div><div>Issue Date: 1<sup>st</sup> Nov 2024</div><div class="page-row">Page 1 of 1</div></td></tr></table><div class="content"><div class="form-heading">REPAIR AND MAINTENANCE FORM</div><div class="field-row"><span class="field-label">DATE:</span><span class="field-fill">${esc(item.form_date)}</span></div><div class="field-row"><span class="field-label">STATION:</span><span class="field-fill">${esc(item.station_name)}</span></div><div class="section-label">Type of repair- specify pump, electrical works, generator</div><div class="lined-box">${esc(item.repair_type)}</div><div class="section-label">Remarks</div><div class="lined-box">${esc(item.remarks||'')}</div><div class="technician-row"><span>Technician name:</span><span class="field-fill">${esc(item.technician_name)}</span></div><div class="section-label">Remarks and requirements</div><div class="lined-box">${esc(item.requirements||'')}</div><div class="for-label">For:</div><div class="supervisor-row"><span>Supervisor/ Manager:</span><span class="sup-fill">${esc(sup?.full_name||man?.full_name||'')}</span><span>Stamp:</span><span class="stamp-fill"></span><span>Sign:</span><span class="sign-fill">${sig(sup||man)}</span></div><table class="approval-table"><tr><td class="header-cell" style="width:50%">Reviewed by:</td><td class="header-cell" style="width:50%">Approved by:</td></tr><tr><td class="header-cell">Inspectorate committee</td><td class="header-cell">Finance &amp; Accounts</td></tr><tr><td class="header-cell">Name: ${esc(sup?.full_name||'')}</td><td class="header-cell">Name: ${esc(man?.full_name||'')}</td></tr><tr><td class="header-cell">Signature: ${sig(sup)}</td><td class="header-cell">Signature: ${sig(man)}</td></tr><tr><td class="header-cell">Date: ${esc(item.form_date)}</td><td class="header-cell">Date: ${esc(item.form_date)}</td></tr></table><div class="footer-note">Texol Energies Limited Repair and Maintenance Form</div></div></div>`;
    }
    async function prepare(item) {
        await profiles(item);
    }

    function requestPassword() {
        return new Promise(resolve => {
            document.getElementById('passModal')?.remove();
            document.body.insertAdjacentHTML('beforeend',
                '<div class="modal fade" id="passModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Approval Password</h5><button class="btn-close" data-cancel></button></div><form id="passForm"><div class="modal-body"><label class="form-label">Enter your password</label><input type="password" name="temp_password" class="form-control" required></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-cancel>Cancel</button><button class="btn btn-primary">Verify</button></div></form></div></div></div></div>'
            );
            const e = document.getElementById('passModal'),
                m = new bootstrap.Modal(e),
                done = v => {
                    m.hide();
                    resolve(v);
                    setTimeout(() => e.remove(), 300)
                };
            e.querySelectorAll('[data-cancel]').forEach(b => b.onclick = () => done(null));
            e.querySelector('form').onsubmit = x => {
                x.preventDefault();
                done(e.querySelector('[name=temp_password]').value)
            };
            m.show();
        });
    }
    async function verify() {
        const p = await requestPassword();
        if (!p) return false;
        const r = await supabase.from('users').select('temp_password').eq('id', activeUserId).single();
        if (r.error || !r.data || p !== r.data.temp_password) {
            alert('Incorrect password.');
            return false;
        }
        return true;
    }
    window.viewRepair = async item => {
        await prepare(item);
        selected = item;
        document.getElementById('repairModalBody').innerHTML = detail(item);
        document.getElementById('editRepair').disabled = approvals(item).length > 0;
        new bootstrap.Modal(document.getElementById('repairModal')).show();
    };
    window.printRepair = async item => {
        await prepare(item);
        const w = window.open('', '_blank');
        if (!w) {
            alert('Allow pop-ups to print.');
            return;
        }
        w.document.write(
            `<!doctype html><html><head><title>Repair and Maintenance Form</title><style>${document.getElementById('repairPrintStyles').textContent}</style></head><body>${printHtml(item)}</body></html>`
        );
        w.document.close();
        w.onload = () => w.print();
    };
    window.approveRepair = async item => {
        const ids = (item.shared_with || '').split(',').map(x => x.trim()).filter(Boolean).slice(0, 2),
            i = ids.indexOf(activeUserId),
            a = approvals(item);
        if (['approved', 'rejected'].includes(item.status)) {
            alert('This form is closed.');
            return;
        }
        if (i < 0) {
            alert('You are not assigned to approve this form.');
            return;
        }
        if (a.some(x => (x.user_id || x) === activeUserId)) {
            alert('Already approved.');
            return;
        }
        if (i === 1 && !a.some(x => (x.user_id || x) === ids[0])) {
            alert('The first reviewer must check this form first.');
            return;
        }
        if (!await verify()) return;
        a.push({
            user_id: activeUserId,
            approval_type: i === 0 ? 'checked' : 'approved',
            approved_at: new Date().toISOString()
        });
        const r = await supabase.from('repair_maintenance_forms').update({
            approved_by_users: a,
            status: i === 1 ? 'approved' : 'checked'
        }).eq('id', item.id);
        if (r.error) {
            alert(r.error.message);
            return;
        }
        location.reload();
    };
    window.rejectRepair = async item => {
        const ids = (item.shared_with || '').split(',').map(x => x.trim()).filter(Boolean).slice(0, 2);
        if (approvals(item).length || !ids.includes(activeUserId)) {
            alert('Only assigned approvers can reject before approval.');
            return;
        }
        if (!confirm('Reject this form?')) return;
        const r = await supabase.from('repair_maintenance_forms').update({
            status: 'rejected'
        }).eq('id', item.id);
        if (r.error) alert(r.error.message);
        else location.reload();
    };
    window.deleteRepair = async item => {
        if (!confirm('Delete this form?')) return;
        const r = await supabase.from('repair_maintenance_forms').delete().eq('id', item.id);
        if (r.error) alert(r.error.message);
        else location.reload();
    };
    window.editRepairForm = async item => {
        if (approvals(item).length > 0 || ['checked', 'approved'].includes(item.status)) {
            alert('This form cannot be edited after approval.');
            return;
        }
        await prepare(item);
        const approverIds = (item.shared_with || '').split(',').map(x => x.trim()).filter(Boolean);
        const userOptions = selectedId =>
            `<option value="">Not assigned</option>${users.filter(user => user.id !== activeUserId).map(user => `<option value="${esc(user.id)}" ${user.id === selectedId ? 'selected' : ''}>${esc(user.full_name || user.email)} (${esc(user.email)})</option>`).join('')}`;
        const branchOptions = Array.from(document.getElementById('branchId').options).map(option =>
            `<option value="${esc(option.value)}" ${option.value === item.branch_id ? 'selected' : ''}>${esc(option.textContent.trim())}</option>`
            ).join('');
        document.getElementById('repairModalBody').innerHTML =
            `<form id="editRepairForm" class="row g-3"><div class="col-md-6"><label class="form-label small fw-semibold">Date</label><input class="form-control form-control-sm" id="editRepairDate" type="date" value="${esc(item.form_date || '')}" required></div><div class="col-md-6"><label class="form-label small fw-semibold">Station</label><select class="form-select form-select-sm" id="editRepairBranch" required>${branchOptions}</select></div><div class="col-12"><label class="form-label small fw-semibold">Type of repair</label><textarea class="form-control form-control-sm" id="editRepairType" rows="3" required>${esc(item.repair_type || '')}</textarea></div><div class="col-12"><label class="form-label small fw-semibold">Remarks</label><textarea class="form-control form-control-sm" id="editRepairRemarks" rows="3">${esc(item.remarks || '')}</textarea></div><div class="col-md-6"><label class="form-label small fw-semibold">Technician name</label><input class="form-control form-control-sm" id="editTechnician" value="${esc(item.technician_name || '')}" required></div><div class="col-12"><label class="form-label small fw-semibold">Remarks and requirements</label><textarea class="form-control form-control-sm" id="editRequirements" rows="3">${esc(item.requirements || '')}</textarea></div><div class="col-md-6"><label class="form-label small fw-semibold">Reviewed By</label><select class="form-select form-select-sm" id="editReviewer">${userOptions(approverIds[0])}</select></div><div class="col-md-6"><label class="form-label small fw-semibold">Approved By</label><select class="form-select form-select-sm" id="editApprover">${userOptions(approverIds[1])}</select></div><div class="col-12 text-end"><button type="button" class="btn btn-sm btn-secondary" id="cancelRepairEdit">Cancel</button><button type="submit" class="btn btn-sm btn-primary ms-2">Save Changes</button></div></form>`;
        document.getElementById('cancelRepairEdit').onclick = () => window.viewRepair(item);
        document.getElementById('editRepairForm').onsubmit = async event => {
            event.preventDefault();
            const branch = document.getElementById('editRepairBranch').selectedOptions[0];
            const newApprovers = [document.getElementById('editReviewer').value, document
                .getElementById('editApprover').value
            ].filter(Boolean);
            if (new Set(newApprovers).size !== newApprovers.length) {
                alert('Reviewed By and Approved By must be different users.');
                return;
            }
            const sameApprovers = newApprovers.join(',') === (item.shared_with || '');
            const update = {
                form_date: document.getElementById('editRepairDate').value,
                branch_id: branch.value,
                station_name: branch.textContent.trim(),
                repair_type: document.getElementById('editRepairType').value.trim(),
                remarks: document.getElementById('editRepairRemarks').value.trim(),
                technician_name: document.getElementById('editTechnician').value.trim(),
                requirements: document.getElementById('editRequirements').value.trim(),
                shared_with: newApprovers.join(','),
                approved_by_users: sameApprovers ? item.approved_by_users || [] : [],
                status: sameApprovers ? item.status || 'pending' : 'pending'
            };
            const result = await supabase.from('repair_maintenance_forms').update(update).eq('id', item
                .id);
            if (result.error) {
                alert(result.error.message);
                return;
            }
            location.reload();
        };
    };
    document.getElementById('printRepair').onclick = () => selected && printRepair(selected);
    document.getElementById('editRepair').onclick = () => selected && editRepairForm(selected);
    document.getElementById('deleteRepair').onclick = () => selected && deleteRepair(selected);
    let submittingRepair = false;
    document.getElementById('repairForm').onsubmit = async e => {
        e.preventDefault();
        if (submittingRepair) return;
        submittingRepair = true;
        const o = document.getElementById('branchId').selectedOptions[0],
            b = document.getElementById('saveBtn');
        const r = {
            form_date: document.getElementById('formDate').value,
            branch_id: o.value,
            station_name: o.dataset.name || o.textContent,
            repair_type: document.getElementById('repairType').value.trim(),
            remarks: document.getElementById('remarks').value.trim(),
            technician_name: document.getElementById('technicianName').value.trim(),
            department: '<?php echo addslashes($department);?>',
            requirements: document.getElementById('requirements').value.trim(),
            shared_with: approvers.map(x => x.id).join(','),
            approved_by_users: [],
            status: 'pending',
            created_by: activeUserId || null
        };
        b.disabled = true;
        b.querySelector('.spinner-border').classList.remove('d-none');
        b.querySelector('.btn-label').textContent = 'Saving...';
        try {
            const x = await supabase.from('repair_maintenance_forms').insert(r);
            if (x.error) throw x.error;
            location.reload();
        } catch (x) {
            alert(x.message);
        } finally {
            b.disabled = false;
            b.querySelector('.spinner-border').classList.add('d-none');
            b.querySelector('.btn-label').textContent = 'Save Form';
            submittingRepair = false;
        }
    };
    </script>
</body>

</html>