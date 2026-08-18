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
    <title>Work Card System - Departments</title>

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
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" />
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'departments';
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
                    <span id="pageTitle">Departments</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Department Management</h1>
                    <p class="text-muted small mb-0">
                        View all departments and add new departments.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New Department</h2>
                                    <p class="text-muted small mb-0">
                                        Fill in the details and save to the departments table.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="departmentFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addDepartmentForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="departmentName">Name</label>
                                            <input type="text" class="form-control form-control-sm" id="departmentName" placeholder="Maintenance" required />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="departmentDescription">Description</label>
                                            <textarea class="form-control form-control-sm" id="departmentDescription" rows="3" placeholder="Responsible for..." required></textarea>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="departmentHead">Department Head</label>
<select class="form-select form-select-sm" id="departmentHead" required>
    <option value="">Select Department Head</option>
</select>                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="primeUser">Prime User</label>
<select class="form-select form-select-sm" id="primeUser" required>
    <option value="">Select Prime User</option>
</select>                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetDepartmentForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveDepartmentBtn">Save Department</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Departments</h2>
                                        <p class="text-muted small mb-0">Latest departments from the database.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshDepartmentsBtn" type="button">
                                        <i class="bi bi-arrow-clockwise me-1"></i>
                                        Refresh
                                    </button>
                                </div>
                                <div class="card-body px-2 px-md-3 py-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="small text-uppercase text-muted">Name</th>
                                                    <th class="small text-uppercase text-muted">Description</th>
                                                    <th class="small text-uppercase text-muted">Department Head</th>
                                                    <th class="small text-uppercase text-muted">Prime User</th>
                                              <th class="small text-uppercase text-muted text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="departmentsTableBody">
                                                <tr>
                                                    <td colspan="4" class="text-center small text-muted py-3">
                                                        Loading departments...
                                                    </td>
                                                </tr>
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
<!-- Edit/Delete Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Department</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="editDepartmentId">

        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" id="editDepartmentName">
        </div>

        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="editDepartmentDescription"></textarea>
        </div>

        <div class="mb-2">
          <label class="form-label">Department Head</label>
<select class="form-select" id="editDepartmentHead"></select>
        </div>

        <div class="mb-2">
          <label class="form-label">Prime User</label>
<select class="form-select" id="editPrimeUser"></select>        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button class="btn btn-danger" id="deleteDepartmentBtn">
          <i class="bi bi-trash"></i> Delete
        </button>

        <div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="updateDepartmentBtn">Update</button>
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

    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = createClient(supabaseUrl, supabaseKey);

        const form = document.getElementById('addDepartmentForm');
        const saveBtn = document.getElementById('saveDepartmentBtn');
        const resetBtn = document.getElementById('resetDepartmentForm');
        const refreshBtn = document.getElementById('refreshDepartmentsBtn');
        const alertBox = document.getElementById('departmentFormAlert');
        const tableBody = document.getElementById('departmentsTableBody');


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

        const deptHeadSelect = document.getElementById('departmentHead');
        const primeUserSelect = document.getElementById('primeUser');
        const editDeptHeadSelect = document.getElementById('editDepartmentHead');
        const editPrimeUserSelect = document.getElementById('editPrimeUser');

        function populateSelect(selectElement) {
            selectElement.innerHTML = '<option value="">Select User</option>';
            data.forEach(user => {
                const option = document.createElement('option');
                option.value = user.email; // store email in db
                option.textContent = `${user.full_name} (${user.email})`;
                selectElement.appendChild(option);
            });
        }

        populateSelect(deptHeadSelect);
        populateSelect(primeUserSelect);
        populateSelect(editDeptHeadSelect);
        populateSelect(editPrimeUserSelect);

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
        }

        function hideAlert() {
            if (!alertBox) return;
            alertBox.classList.add('d-none');
        }

        async function loadDepartments() {
            if (!tableBody) return;
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center small text-muted py-3">Loading departments...</td>
                </tr>`;

            try {
                const { data, error } = await supabase
                    .from('departments')
                    .select('id, name, description, department_head, prime_user, created_at')
                    .order('created_at', { ascending: false });

                if (error) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center small text-danger py-3">${escapeHtml(error.message || 'Failed to load departments.')}</td>
                        </tr>`;
                    return;
                }

                if (!data || data.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center small text-muted py-3">No departments found yet.</td>
                        </tr>`;
                    return;
                }

                tableBody.innerHTML = '';
               data.forEach((dept) => {
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td class="small fw-semibold">${escapeHtml(dept.name)}</td>
        <td class="small text-muted">${escapeHtml(dept.description)}</td>
        <td class="small">${escapeHtml(dept.department_head)}</td>
        <td class="small">${escapeHtml(dept.prime_user)}</td>
        <td class="text-end">
            <button class="btn btn-sm btn-outline-primary edit-dept-btn"
                data-id="${dept.id}"
                data-name="${escapeHtml(dept.name)}"
                data-description="${escapeHtml(dept.description)}"
                data-head="${escapeHtml(dept.department_head)}"
                data-prime="${escapeHtml(dept.prime_user)}">
                <i class="bi bi-pencil"></i>
            </button>
        </td>
    `;

    tableBody.appendChild(tr);
});
            } catch (err) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center small text-danger py-3">Unexpected error loading departments.</td>
                    </tr>`;
            }
        }

        if (resetBtn && form) {
            resetBtn.addEventListener('click', () => {
                form.reset();
                hideAlert();
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadDepartments());
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const name = document.getElementById('departmentName')?.value.trim() || '';
                const description = document.getElementById('departmentDescription')?.value.trim() || '';
                const departmentHead = document.getElementById('departmentHead')?.value.trim() || '';
                const primeUser = document.getElementById('primeUser')?.value.trim() || '';

                if (!name || !description || !departmentHead || !primeUser) {
                    showAlert('warning', 'Please fill in all required fields.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                try {
                    const { error } = await supabase
                        .from('departments')
                        .insert([{
                            name,
                            description,
                            department_head: departmentHead,
                            prime_user: primeUser
                        }]);

                    if (error) {
                        showAlert('danger', error.message || 'Failed to save department.');
                        return;
                    }

                    showAlert('success', 'Department saved successfully.');
                    form.reset();
                    await loadDepartments();
                } catch (err) {
                    showAlert('danger', 'Unexpected error saving department.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Department';
                }
            });
        }
const deptModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.edit-dept-btn');
    if (!btn) return;

    document.getElementById('editDepartmentId').value = btn.dataset.id;
    document.getElementById('editDepartmentName').value = btn.dataset.name;
    document.getElementById('editDepartmentDescription').value = btn.dataset.description;
    document.getElementById('editDepartmentHead').value = btn.dataset.head;
    document.getElementById('editPrimeUser').value = btn.dataset.prime;

    deptModal.show();
});

document.getElementById('updateDepartmentBtn').addEventListener('click', async () => {
    const id = document.getElementById('editDepartmentId').value;

    if (!id) {
        alert("Invalid department ID");
        return;
    }

    const name = document.getElementById('editDepartmentName').value.trim();
    const description = document.getElementById('editDepartmentDescription').value.trim();
    const department_head = document.getElementById('editDepartmentHead').value.trim();
    const prime_user = document.getElementById('editPrimeUser').value.trim();

    if (!name || !description || !department_head || !prime_user) {
        alert('Fill all fields');
        return;
    }

    const { error } = await supabase
        .from('departments')
        .update({ name, description, department_head, prime_user })
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    deptModal.hide();
    loadDepartments();
});

document.getElementById('deleteDepartmentBtn').addEventListener('click', async () => {
    const id = document.getElementById('editDepartmentId').value;

    if (!id) {
        alert("Invalid department ID");
        return;
    }

    if (!confirm('Are you sure you want to delete this department?')) return;

    const { error } = await supabase
        .from('departments')
        .delete()
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    deptModal.hide();
    loadDepartments();
});
        loadDepartments();
    </script>
</body>
</html>
<?php else: header('Location:   404'); exit; endif; ?>
