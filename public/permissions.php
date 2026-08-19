<?php
session_start();

require_once __DIR__ . '/../config.php';

if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Check permission for permissions module
if (!check_permission('permissions', 'view')) {
    header('Location:   404');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - Permissions</title>

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
        $activeMenu = 'permissions';
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
                    <span id="pageTitle">Permissions</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">User Permissions Management</h1>
                    <p class="text-muted small mb-0">
                        Manage user permissions and access levels.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Select User</h2>
                                    <p class="text-muted small mb-0">
                                        Choose a user to manage their permissions.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="permissionFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="selectUserForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="permissionUser">User</label>
                                            <select class="form-select form-select-sm" id="permissionUser" required>
                                                <option value="">Select User</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-8">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">User Permissions</h2>
                                        <p class="text-muted small mb-0">Select a user to view and manage their permissions.</p>
                                    </div>
                                    <button class="btn btn-sm btn-primary d-none" id="savePermissionsBtn" type="button">
                                        <i class="bi bi-save me-1"></i>Save Permissions
                                    </button>
                                </div>
                                <div class="card-body px-2 px-md-3 py-3">
                                    <div id="permissionsContainer" class="d-none">
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="small text-uppercase text-muted">Module</th>
                                                        <th class="small text-uppercase text-muted text-center">View</th>
                                                        <th class="small text-uppercase text-muted text-center">Create</th>
                                                        <th class="small text-uppercase text-muted text-center">Edit</th>
                                                        <th class="small text-uppercase text-muted text-center">Delete</th>
                                                        <th class="small text-uppercase text-muted text-center">All</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="permissionsTableBody">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div id="noUserSelected" class="text-center py-5">
                                        <i class="bi bi-person-check display-4 text-muted"></i>
                                        <p class="text-muted small mt-3">Select a user from the dropdown to manage their permissions.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
<!-- Delete Permission Modal -->
<div class="modal fade" id="deletePermissionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete Permission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="deletePermissionId">
        <p>Are you sure you want to delete this permission?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="confirmDeletePermissionBtn">Delete</button>
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

        const userSelect = document.getElementById('permissionUser');
        const savePermissionsBtn = document.getElementById('savePermissionsBtn');
        const refreshBtn = document.getElementById('refreshPermissionsBtn');
        const alertBox = document.getElementById('permissionFormAlert');
        const tableBody = document.getElementById('permissionsTableBody');
        const permissionsContainer = document.getElementById('permissionsContainer');
        const noUserSelected = document.getElementById('noUserSelected');

        const modules = ['dashboard', 'job_cards', 'tickets', 'mytickets', 'users', 'departments', 'categories', 'roles', 'permissions', 'requisition_approval', 'requisitions_view_all', 'customer_feedback'];
        const actions = ['view', 'create', 'edit', 'delete', 'all'];

        let currentUserEmail = null;

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

        async function loadUsers() {
            try {
                const { data, error } = await supabase
                    .from('users')
                    .select('id, email, full_name')
                    .order('full_name', { ascending: true });

                if (error) {
                    console.error('Failed to load users:', error.message);
                    return;
                }

                userSelect.innerHTML = '<option value="">Select User</option>';
                data.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.email;
                    option.textContent = `${user.full_name} (${user.email})`;
                    userSelect.appendChild(option);
                });
            } catch (err) {
                console.error('Unexpected error fetching users:', err);
            }
        }

        async function loadUserPermissions(userEmail) {
            if (!tableBody) return;
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center small text-muted py-3">Loading permissions...</td></tr>';

            try {
                const { data, error } = await supabase
                    .from('user_permissions')
                    .select('module, action')
                    .eq('user_email', userEmail);

                if (error) {
                    console.error('Failed to load permissions:', error.message);
                    return;
                }

                const permissions = {};
                if (data) {
                    data.forEach(perm => {
                        if (!permissions[perm.module]) {
                            permissions[perm.module] = [];
                        }
                        permissions[perm.module].push(perm.action);
                    });
                }

                renderPermissionsMatrix(permissions);
            } catch (err) {
                console.error('Unexpected error loading permissions:', err);
            }
        }

        function renderPermissionsMatrix(permissions) {
            if (!tableBody) return;
            tableBody.innerHTML = '';

            modules.forEach(module => {
                const tr = document.createElement('tr');
                const modulePerms = permissions[module] || [];

                let checkboxes = '';
                actions.forEach(action => {
                    const isChecked = modulePerms.includes(action) ? 'checked' : '';
                    checkboxes += `
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input permission-checkbox"
                                data-module="${module}" data-action="${action}" ${isChecked}>
                        </td>
                    `;
                });

                tr.innerHTML = `
                    <td class="small fw-semibold">${escapeHtml(module.replace('_', ' ').toUpperCase())}</td>
                    ${checkboxes}
                `;
                tableBody.appendChild(tr);
            });
        }

        if (userSelect) {
            userSelect.addEventListener('change', async () => {
                currentUserEmail = userSelect.value.trim();
                
                if (currentUserEmail) {
                    permissionsContainer.classList.remove('d-none');
                    noUserSelected.classList.add('d-none');
                    savePermissionsBtn.classList.remove('d-none');
                    await loadUserPermissions(currentUserEmail);
                } else {
                    permissionsContainer.classList.add('d-none');
                    noUserSelected.classList.remove('d-none');
                    savePermissionsBtn.classList.add('d-none');
                }
            });
        }

        if (savePermissionsBtn) {
            savePermissionsBtn.addEventListener('click', async () => {
                if (!currentUserEmail) {
                    showAlert('warning', 'Please select a user first.');
                    return;
                }

                savePermissionsBtn.disabled = true;
                savePermissionsBtn.innerHTML = '<i class="bi bi-save me-1"></i>Saving...';

                try {
                    // Delete all existing permissions for this user
                    const { error: deleteError } = await supabase
                        .from('user_permissions')
                        .delete()
                        .eq('user_email', currentUserEmail);

                    if (deleteError) {
                        showAlert('danger', deleteError.message || 'Failed to update permissions.');
                        return;
                    }

                    // Get all checked permissions
                    const checkboxes = document.querySelectorAll('.permission-checkbox:checked');
                    const newPermissions = [];
                    checkboxes.forEach(cb => {
                        newPermissions.push({
                            user_email: currentUserEmail,
                            module: cb.dataset.module,
                            action: cb.dataset.action
                        });
                    });

                    // Insert new permissions
                    if (newPermissions.length > 0) {
                        const { error: insertError } = await supabase
                            .from('user_permissions')
                            .insert(newPermissions);

                        if (insertError) {
                            showAlert('danger', insertError.message || 'Failed to save permissions.');
                            return;
                        }
                    }

                    showAlert('success', 'Permissions saved successfully.');
                } catch (err) {
                    console.error('Unexpected error saving permissions:', err);
                    showAlert('danger', 'Unexpected error saving permissions.');
                } finally {
                    savePermissionsBtn.disabled = false;
                    savePermissionsBtn.innerHTML = '<i class="bi bi-save me-1"></i>Save Permissions';
                }
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', async () => {
                if (currentUserEmail) {
                    await loadUserPermissions(currentUserEmail);
                }
            });
        }

        loadUsers();
    </script>
</body>
</html>
