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
    <title>Work Card System - Roles</title>

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
        $activeMenu = 'roles';
        include __DIR__ . '/partials/sidebar.php';
        ?>

        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <a class="navbar-brand fw-semibold d-none d-sm-inline d-flex align-items-center gap-2" href="#">
                    <span id="pageTitle">Roles</span>
                </a>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Role Management</h1>
                    <p class="text-muted small mb-0">View all roles and add new roles for the system.</p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New Role</h2>
                                    <p class="text-muted small mb-0">Use role name and description.</p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="roleFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addRoleForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="roleName">Role Name</label>
                                            <input type="text" class="form-control form-control-sm" id="roleName" placeholder="Supervisor" required />
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="roleDescription">Description</label>
                                            <textarea class="form-control form-control-sm" id="roleDescription" rows="4" placeholder="What this role can do..." required></textarea>
                                        </div>
                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetRoleForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveRoleBtn">Save Role</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Roles</h2>
                                        <p class="text-muted small mb-0">Latest roles from the database.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshRolesBtn" type="button">
                                        <i class="bi bi-arrow-clockwise me-1"></i>
                                        Refresh
                                    </button>
                                </div>
                                <div class="card-body px-2 px-md-3 py-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                          <th class="small text-uppercase text-muted">Role Name</th>
<th class="small text-uppercase text-muted">Description</th>
<th class="small text-uppercase text-muted text-end">Actions</th>
                                            <tbody id="rolesTableBody">
                                                <tr>
                                                    <td colspan="2" class="text-center small text-muted py-3">Loading roles...</td>
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
<!-- Edit/Delete Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="editRoleId">

        <div class="mb-3">
          <label class="form-label">Role Name</label>
          <input type="text" class="form-control" id="editRoleName">
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="editRoleDescription"></textarea>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button class="btn btn-danger" id="deleteRoleBtn">
          <i class="bi bi-trash"></i> Delete
        </button>

        <div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="updateRoleBtn">Update</button>
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

        const form = document.getElementById('addRoleForm');
        const saveBtn = document.getElementById('saveRoleBtn');
        const resetBtn = document.getElementById('resetRoleForm');
        const refreshBtn = document.getElementById('refreshRolesBtn');
        const alertBox = document.getElementById('roleFormAlert');
        const tableBody = document.getElementById('rolesTableBody');

        function esc(value) {
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

        async function loadRoles() {
            if (!tableBody) return;
            tableBody.innerHTML = `
                <tr>
                    <td colspan="2" class="text-center small text-muted py-3">Loading roles...</td>
                </tr>`;

            try {
                const { data, error } = await supabase
                    .from('roles')
                    .select('id, name, description, created_at')
                    .order('created_at', { ascending: false });

                if (error) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="2" class="text-center small text-danger py-3">${esc(error.message || 'Failed to load roles.')}</td>
                        </tr>`;
                    return;
                }

                if (!data || data.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="2" class="text-center small text-muted py-3">No roles found yet.</td>
                        </tr>`;
                    return;
                }

                tableBody.innerHTML = '';
            data.forEach((role) => {
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td class="small fw-semibold">${esc(role.name)}</td>
        <td class="small text-muted">${esc(role.description)}</td>
        <td class="text-end">
            <button class="btn btn-sm btn-outline-primary edit-btn"
                data-id="${role.id}"
                data-name="${esc(role.name)}"
                data-description="${esc(role.description)}">
                <i class="bi bi-pencil"></i>
            </button>
        </td>
    `;

    tableBody.appendChild(tr);
});
            } catch (err) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="2" class="text-center small text-danger py-3">Unexpected error loading roles.</td>
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
            refreshBtn.addEventListener('click', () => loadRoles());
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const name = (document.getElementById('roleName')?.value || '').trim();
                const description = (document.getElementById('roleDescription')?.value || '').trim();
                if (!name || !description) {
                    showAlert('warning', 'Please fill in role name and description.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
                try {
                    const { error } = await supabase
                        .from('roles')
                        .insert([{ name, description }]);

                    if (error) {
                        showAlert('danger', error.message || 'Failed to save role.');
                        return;
                    }

                    showAlert('success', 'Role saved successfully.');
                    form.reset();
                    await loadRoles();
                } catch (err) {
                    showAlert('danger', 'Unexpected error saving role.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Role';
                }
            });
        }
const editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));

document.addEventListener('click', (e) => {
    if (e.target.closest('.edit-btn')) {
        const btn = e.target.closest('.edit-btn');

        document.getElementById('editRoleId').value = btn.dataset.id;
        document.getElementById('editRoleName').value = btn.dataset.name;
        document.getElementById('editRoleDescription').value = btn.dataset.description;

        editModal.show();
    }
});
document.getElementById('updateRoleBtn').addEventListener('click', async () => {
    const id = document.getElementById('editRoleId').value;
    const name = document.getElementById('editRoleName').value.trim();
    const description = document.getElementById('editRoleDescription').value.trim();

    if (!name || !description) {
        alert('Fill all fields');
        return;
    }

    const { error } = await supabase
        .from('roles')
        .update({ name, description })
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    editModal.hide();
    loadRoles();
});

document.getElementById('deleteRoleBtn').addEventListener('click', async () => {
    const id = document.getElementById('editRoleId').value;

    if (!confirm('Are you sure you want to delete this role?')) return;

    const { error } = await supabase
        .from('roles')
        .delete()
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    editModal.hide();
    loadRoles();
});
        loadRoles();
    </script>
</body>
</html>
<?php else: header('Location:   404'); exit; endif; ?>
