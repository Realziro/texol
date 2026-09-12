<?php
session_start();

require_once __DIR__ . '/../config.php';

if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}
?>
<?php if (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin') : ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - Branches</title>

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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" />
    <style>
        .branches-table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6c757d;
            background: #f8f9fa;
            border-bottom-width: 1px
        }

        .branches-table tbody td {
            padding-top: .7rem;
            padding-bottom: .7rem
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

        .branches-table-wrap {
            overflow: visible !important;
        }

        .branches-table-wrap .dropdown.show {
            position: relative;
            z-index: 1080;
        }

        .branches-table-wrap .dropdown-menu {
            z-index: 1090;
        }

        @media (max-width: 767.98px) {
            .branches-table-wrap {
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'branches';
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
                    <span id="pageTitle">Branches</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Branch Management</h1>
                    <p class="text-muted small mb-0">
                        View all branches and add new branches.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New Branch</h2>
                                    <p class="text-muted small mb-0">
                                        Fill in the details and save to the branches table.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="branchFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addBranchForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="branchName">Name</label>
                                            <input type="text" class="form-control form-control-sm" id="branchName" placeholder="Main Office" required />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="branchManager">Manager</label>
                                            <select class="form-select form-select-sm" id="branchManager" required>
                                                <option value="">Select Manager</option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="totalCameras">Total CCTV Cameras</label>
                                            <input type="number" class="form-control form-control-sm" id="totalCameras" placeholder="0" min="0" />
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetBranchForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveBranchBtn">Save Branch</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Branches</h2>
                                        <p class="text-muted small mb-0">Latest branches from the database.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshBranchesBtn" type="button">
                                        <i class="bi bi-arrow-clockwise me-1"></i>
                                        Refresh
                                    </button>
                                </div>
                                <div class="card-body px-2 px-md-3 py-3">
                                    <div class="table-responsive branches-table-wrap">
                                        <table class="table table-sm table-hover align-middle branches-table" id="branchesTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="small text-uppercase text-muted">Name</th>
                                                    <th class="small text-uppercase text-muted">Manager</th>
                                                    <th class="small text-uppercase text-muted">Total Cameras</th>
                                                    <th class="small text-uppercase text-muted">Created At</th>
                                                    <th class="small text-uppercase text-muted text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
<!-- Edit/Delete Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="editBranchId">

        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" id="editBranchName">
        </div>

        <div class="mb-2">
          <label class="form-label">Manager</label>
          <select class="form-select" id="editBranchManager"></select>
        </div>

        <div class="mb-2">
          <label class="form-label">Total CCTV Cameras</label>
          <input type="number" class="form-control" id="editTotalCameras" min="0">
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button class="btn btn-danger" id="deleteBranchBtn">
          <i class="bi bi-trash"></i> Delete
        </button>

        <div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="updateBranchBtn">Update</button>
        </div>
      </div>

    </div>
  </div>
</div>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>
    <script src="app.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';
        
        let branchesTable;
        
        // Initialize DataTables
        $(document).ready(function() {
            branchesTable = $('#branchesTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                order: [[3, 'desc']],
                language: {
                    search: '_INPUT_',
                    searchPlaceholder: 'Search branches...'
                },
                columnDefs: [
                    { orderable: false, targets: 4 }
                ],
                autoWidth: false
            });
            
            // Load branches after DataTables is initialized
            loadBranches();
        });

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = createClient(supabaseUrl, supabaseKey);

        const form = document.getElementById('addBranchForm');
        const saveBtn = document.getElementById('saveBranchBtn');
        const resetBtn = document.getElementById('resetBranchForm');
        const refreshBtn = document.getElementById('refreshBranchesBtn');
        const alertBox = document.getElementById('branchFormAlert');


        async function loadUsers() {
    try {
        const { data, error } = await supabase
            .from('users')
            .select('id, full_name, email')
            .order('full_name', { ascending: true });

        if (error) {
            console.error('Failed to load users:', error.message);
            return;
        }

        const managerSelect = document.getElementById('branchManager');
        const editManagerSelect = document.getElementById('editBranchManager');

        function populateSelect(selectElement) {
            selectElement.innerHTML = '<option value="">Select Manager</option>';
            data.forEach(user => {
                const option = document.createElement('option');
                option.value = user.email; // store email in db
                option.textContent = `${user.full_name} (${user.email})`;
                selectElement.appendChild(option);
            });
        }

        populateSelect(managerSelect);
        populateSelect(editManagerSelect);

    } catch (err) {
        console.error('Unexpected error fetching users:', err);
    }
}

// Call this once when page loads
loadUsers();
        function escapeHtml(value) {
            return (value || '')
                .toString()
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function showAlert(type, message) {
            if (!alertBox) return;
            alertBox.className = `alert alert-${type} py-2 px-3 mb-3`;
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
            setTimeout(() => alertBox.classList.add('d-none'), 5000);
        }

        function hideAlert() {
            if (!alertBox) return;
            alertBox.classList.add('d-none');
        }

        async function loadBranches() {
            if (!branchesTable) {
                console.error('DataTables not initialized');
                return;
            }
            
            branchesTable.clear();
            
            try {
                const { data, error } = await supabase
                    .from('branches')
                    .select('id, name, manager_email, total_cameras, created_at')
                    .order('created_at', { ascending: false });

                if (error) {
                    branchesTable.row.add(['-', '-', '-', '-', '-']).draw();
                    showAlert('danger', error.message || 'Failed to load branches.');
                    return;
                }

                if (!data || data.length === 0) {
                    branchesTable.row.add(['-', '-', '-', '-', '-']).draw();
                    return;
                }

                data.forEach((branch) => {
                    const editButton = `<button class="btn btn-sm btn-outline-primary edit-branch-btn"
                        data-id="${branch.id}"
                        data-name="${escapeHtml(branch.name)}"
                        data-manager="${escapeHtml(branch.manager_email)}"
                        data-cameras="${escapeHtml(branch.total_cameras || 0)}">
                        <i class="bi bi-pencil"></i>
                    </button>`;
                    
                    branchesTable.row.add([
                        escapeHtml(branch.name),
                        escapeHtml(branch.manager_email),
                        escapeHtml(branch.total_cameras || 0),
                        branch.created_at ? new Date(branch.created_at).toLocaleString() : '-',
                        editButton
                    ]);
                });
                
                branchesTable.draw();
                
                // Re-attach event listeners to edit buttons
                document.querySelectorAll('.edit-branch-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.getElementById('editBranchId').value = this.dataset.id;
                        document.getElementById('editBranchName').value = this.dataset.name;
                        document.getElementById('editBranchManager').value = this.dataset.manager;
                        document.getElementById('editTotalCameras').value = this.dataset.cameras;
                        branchModal.show();
                    });
                });
                
            } catch (err) {
                console.error('Error loading branches:', err);
                branchesTable.row.add(['-', '-', '-', '-', '-']).draw();
                showAlert('danger', 'Unexpected error loading branches.');
            }
        }

        if (resetBtn && form) {
            resetBtn.addEventListener('click', () => {
                form.reset();
                hideAlert();
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadBranches());
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const name = document.getElementById('branchName')?.value.trim() || '';
                const managerEmail = document.getElementById('branchManager')?.value.trim() || '';
                const totalCameras = document.getElementById('totalCameras')?.value || 0;

                if (!name || !managerEmail) {
                    showAlert('warning', 'Please fill in all required fields.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                try {
                    const { error } = await supabase
                        .from('branches')
                        .insert([{
                            name,
                            manager_email: managerEmail,
                            total_cameras: parseInt(totalCameras) || 0
                        }]);

                    if (error) {
                        showAlert('danger', error.message || 'Failed to save branch.');
                        return;
                    }

                    showAlert('success', 'Branch saved successfully.');
                    form.reset();
                    loadBranches();
                } catch (err) {
                    showAlert('danger', 'Unexpected error saving branch.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Branch';
                }
            });
        }
const branchModal = new bootstrap.Modal(document.getElementById('editBranchModal'));

document.getElementById('updateBranchBtn').addEventListener('click', async () => {
    const id = document.getElementById('editBranchId').value;

    if (!id) {
        alert("Invalid branch ID");
        return;
    }

    const name = document.getElementById('editBranchName').value.trim();
    const manager_email = document.getElementById('editBranchManager').value.trim();
    const total_cameras = document.getElementById('editTotalCameras').value || 0;

    if (!name || !manager_email) {
        alert('Fill all fields');
        return;
    }

    const { error } = await supabase
        .from('branches')
        .update({ name, manager_email, total_cameras: parseInt(total_cameras) || 0 })
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    branchModal.hide();
    loadBranches();
});

document.getElementById('deleteBranchBtn').addEventListener('click', async () => {
    const id = document.getElementById('editBranchId').value;

    if (!id) {
        alert("Invalid branch ID");
        return;
    }

    if (!confirm('Are you sure you want to delete this branch?')) return;

    const { error } = await supabase
        .from('branches')
        .delete()
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    branchModal.hide();
    loadBranches();
});
    </script>
</body>
</html>
<?php else: header('Location:   404'); exit; endif; ?>
