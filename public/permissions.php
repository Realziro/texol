<?php
require_once __DIR__ . '/../auth.php';

require_permission('permissions', 'view');
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
                    <h1 class="h4 fw-semibold mb-1">Permissions Management</h1>
                    <p class="text-muted small mb-0">
                        Manage roles, role permissions, and user assignments.
                    </p>
                </section>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="permissionsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab">
                            <i class="bi bi-shield-lock me-1"></i>Roles
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                            <i class="bi bi-envelope me-1"></i>Email Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                            <i class="bi bi-people me-1"></i>User Assignment
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="permissionsTabsContent">
                    <!-- Roles Tab -->
                    <div class="tab-pane fade show active" id="roles" role="tabpanel">
                        <div class="row g-3 g-lg-4">
                            <div class="col-12 col-xl-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white py-3 px-3 px-md-4">
                                        <h2 class="h6 mb-1 fw-semibold">Manage Roles</h2>
                                        <p class="text-muted small mb-0">
                                            Create or edit roles and their permissions.
                                        </p>
                                    </div>
                                    <div class="card-body px-3 px-md-4 pb-4">
                                        <div id="roleFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                        <form id="roleForm" class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold" for="roleName">Role Name</label>
                                                <input type="text" class="form-control form-control-sm" id="roleName" placeholder="e.g., HOD, Technician" required />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold" for="roleDescription">Description</label>
                                                <textarea class="form-control form-control-sm" id="roleDescription" rows="2" placeholder="Role description..."></textarea>
                                            </div>
                                            <div class="col-12 d-flex justify-content-end mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetRoleForm">Reset</button>
                                                <button type="submit" class="btn btn-sm btn-primary" id="saveRoleBtn">Save Role</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm mt-3">
                                    <div class="card-header bg-white py-3 px-3 px-md-4">
                                        <h2 class="h6 mb-1 fw-semibold">Existing Roles</h2>
                                        <p class="text-muted small mb-0">Click to edit role permissions.</p>
                                    </div>
                                    <div class="card-body px-2 px-md-3 py-3">
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0" id="rolesTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="small text-uppercase text-muted">Name</th>
                                                        <th class="small text-uppercase text-muted">Description</th>
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

                            <div class="col-12 col-xl-8">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                        <div>
                                            <h2 class="h6 mb-1 fw-semibold">Role Permissions</h2>
                                            <p class="text-muted small mb-0" id="rolePermissionsSubtitle">Select a role to view and manage permissions.</p>
                                        </div>
                                        <button class="btn btn-sm btn-primary d-none" id="saveRolePermissionsBtn" type="button">
                                            <i class="bi bi-save me-1"></i>Save Permissions
                                        </button>
                                    </div>
                                    <div class="card-body px-2 px-md-3 py-3">
                                        <div id="rolePermissionsContainer" class="d-none">
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
                                                            <th class="small text-uppercase text-muted text-center">Scope</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="rolePermissionsTableBody">
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div id="noRoleSelected" class="text-center py-5">
                                            <i class="bi bi-shield-lock display-4 text-muted"></i>
                                            <p class="text-muted small mt-3">Select a role from the list to manage its permissions.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Notifications Tab -->
                    <div class="tab-pane fade" id="notifications" role="tabpanel">
                        <div class="row g-3 g-lg-4">
                            <div class="col-12 col-xl-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white py-3 px-3 px-md-4">
                                        <h2 class="h6 mb-1 fw-semibold">Manage Role Notifications</h2>
                                        <p class="text-muted small mb-0">
                                            Select a role to configure email notifications.
                                        </p>
                                    </div>
                                    <div class="card-body px-3 px-md-4 pb-4">
                                        <div id="notificationFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                        <form id="notificationRoleForm" class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold" for="notificationRole">Role</label>
                                                <select class="form-select form-select-sm" id="notificationRole">
                                                    <option value="">Select a role</option>
                                                </select>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm mt-3">
                                    <div class="card-header bg-white py-3 px-3 px-md-4">
                                        <h2 class="h6 mb-1 fw-semibold">Available Events</h2>
                                        <p class="text-muted small mb-0">Email events that can be configured.</p>
                                    </div>
                                    <div class="card-body px-2 px-md-3 py-3">
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="small text-uppercase text-muted">Event Key</th>
                                                        <th class="small text-uppercase text-muted">Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td class="small">requisition_submitted</td><td class="small text-muted">Requisition submitted</td></tr>
                                                    <tr><td class="small">requisition_approved</td><td class="small text-muted">Requisition approved</td></tr>
                                                    <tr><td class="small">requisition_updated</td><td class="small text-muted">Requisition updated</td></tr>
                                                    <tr><td class="small">requisition_rejected</td><td class="small text-muted">Requisition rejected</td></tr>
                                                    <tr><td class="small">ticket_created</td><td class="small text-muted">Ticket created</td></tr>
                                                    <tr><td class="small">ticket_updated</td><td class="small text-muted">Ticket updated</td></tr>
                                                    <tr><td class="small">ticket_closed</td><td class="small text-muted">Ticket closed</td></tr>
                                                    <tr><td class="small">job_card_created</td><td class="small text-muted">Job card created</td></tr>
                                                    <tr><td class="small">job_card_updated</td><td class="small text-muted">Job card updated</td></tr>
                                                    <tr><td class="small">incident_reported</td><td class="small text-muted">Incident reported</td></tr>
                                                    <tr><td class="small">incident_settled</td><td class="small text-muted">Incident settled</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-8">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                        <div>
                                            <h2 class="h6 mb-1 fw-semibold">Role Email Notifications</h2>
                                            <p class="text-muted small mb-0" id="notificationSubtitle">Select a role to configure notifications.</p>
                                        </div>
                                        <button class="btn btn-sm btn-primary d-none" id="saveNotificationsBtn" type="button">
                                            <i class="bi bi-save me-1"></i>Save Notifications
                                        </button>
                                    </div>
                                    <div class="card-body px-2 px-md-3 py-3">
                                        <div id="notificationsContainer" class="d-none">
                                            <div class="table-responsive">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="small text-uppercase text-muted">Event</th>
                                                            <th class="small text-uppercase text-muted text-center">Receive Email</th>
                                                            <th class="small text-uppercase text-muted text-center">Scope</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="notificationsTableBody">
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div id="noNotificationRole" class="text-center py-5">
                                            <i class="bi bi-envelope display-4 text-muted"></i>
                                            <p class="text-muted small mt-3">Select a role to configure email notifications.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Assignment Tab -->
                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <div class="row g-3 g-lg-4">
                            <div class="col-12 col-xl-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white py-3 px-3 px-md-4">
                                        <h2 class="h6 mb-1 fw-semibold">Assign Role to User</h2>
                                        <p class="text-muted small mb-0">
                                            Select a user to assign a role.
                                        </p>
                                    </div>
                                    <div class="card-body px-3 px-md-4 pb-4">
                                        <div id="userAssignmentAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                        <form id="userAssignmentForm" class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold" for="assignmentUser">User</label>
                                                <div class="position-relative">
                                                    <input type="text" class="form-control form-control-sm" id="assignmentUserInput"
                                                        placeholder="Search users..." autocomplete="off">
                                                    <div id="assignmentUserDropdown" class="dropdown-menu w-100"
                                                        style="position:absolute;z-index:1000;max-height:200px;overflow-y:auto;">
                                                    </div>
                                                </div>
                                                <div id="selectedAssignmentUser" class="mt-1"></div>
                                                <input type="hidden" id="assignmentUser" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold" for="userRole">Role</label>
                                                <select class="form-select form-select-sm" id="userRole">
                                                    <option value="">Select a role</option>
                                                </select>
                                            </div>
                                            <div class="col-12 d-flex justify-content-end mt-2">
                                                <button type="submit" class="btn btn-sm btn-primary" id="saveUserRoleBtn">Save Assignment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-8">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-white py-3 px-3 px-md-4">
                                        <h2 class="h6 mb-1 fw-semibold">User Permissions</h2>
                                        <p class="text-muted small mb-0">Shows role permissions (marked with "Role" badge) plus custom overrides.</p>
                                    </div>
                                    <div class="card-body px-2 px-md-3 py-3">
                                        <div id="userOverridesContainer" class="d-none">
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
                                                            <th class="small text-uppercase text-muted text-center">Scope</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="userOverridesTableBody">
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-3">
                                                <button class="btn btn-sm btn-primary" id="saveUserOverridesBtn">
                                                    <i class="bi bi-save me-1"></i>Save Permissions
                                                </button>
                                            </div>
                                        </div>
                                        <div id="noUserAssigned" class="text-center py-5">
                                            <i class="bi bi-person-check display-4 text-muted"></i>
                                            <p class="text-muted small mt-3">Select a user to view and manage their permission overrides.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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

        const modules = ['dashboard', 'job_cards', 'tickets', 'mytickets', 'users', 'departments', 'categories', 'roles', 'permissions', 'requisition_approval', 'requisitions_view_all', 'customer_feedback', 'incident_settlement', 'cctv_checklist_texol', 'cctv_checklist_sta'];
        const actions = ['view', 'create', 'edit', 'delete', 'all'];
        const scopes = ['own', 'department', 'all'];

        let selectedRole = null;
        let selectedUser = null;
        let allRoles = [];
        let allUsers = [];

        function escapeHtml(value) {
            return (value || '')
                .toString()
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function showAlert(alertId, type, message) {
            const alertBox = document.getElementById(alertId);
            if (!alertBox) return;
            alertBox.className = `alert alert-${type} py-2 px-3 mb-3`;
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
            setTimeout(() => alertBox.classList.add('d-none'), 5000);
        }

        function hideAlert(alertId) {
            const alertBox = document.getElementById(alertId);
            if (!alertBox) return;
            alertBox.classList.add('d-none');
        }

        // ==========================
        // ROLES TAB
        // ==========================
        async function loadRoles() {
            try {
                const { data, error } = await supabase
                    .from('roles')
                    .select('*')
                    .order('name', { ascending: true });

                if (error) {
                    console.error('Failed to load roles:', error.message);
                    return;
                }

                allRoles = data || [];
                renderRolesTable();
                populateRoleDropdown();
                populateNotificationRoleDropdown();
            } catch (err) {
                console.error('Unexpected error fetching roles:', err);
            }
        }

        function renderRolesTable() {
            const tbody = document.querySelector('#rolesTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';

            allRoles.forEach(role => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="small fw-semibold">${escapeHtml(role.name)}</td>
                    <td class="small text-muted">${escapeHtml(role.description || '-')}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="window.loadRolePermissions('${role.id}')">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function populateRoleDropdown() {
            const select = document.getElementById('userRole');
            if (!select) return;
            select.innerHTML = '<option value="">Select a role</option>';
            allRoles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.name;
                select.appendChild(option);
            });
        }

        // Make loadRolePermissions globally accessible
        window.loadRolePermissions = async function(roleId) {
            selectedRole = allRoles.find(r => r.id === roleId);
            if (!selectedRole) return;

            document.getElementById('roleName').value = selectedRole.name;
            document.getElementById('roleDescription').value = selectedRole.description || '';
            document.getElementById('rolePermissionsSubtitle').textContent = `Managing permissions for: ${selectedRole.name}`;

            const container = document.getElementById('rolePermissionsContainer');
            const noRole = document.getElementById('noRoleSelected');
            const saveBtn = document.getElementById('saveRolePermissionsBtn');

            container.classList.remove('d-none');
            noRole.classList.add('d-none');
            saveBtn.classList.remove('d-none');

            await loadRolePermissionsData(roleId);
        };

        async function loadRolePermissionsData(roleId) {
            const tbody = document.getElementById('rolePermissionsTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="7" class="text-center small text-muted py-3">Loading permissions...</td></tr>';

            try {
                const { data, error } = await supabase
                    .from('role_permissions')
                    .select('module, action, scope')
                    .eq('role_id', roleId);

                if (error) {
                    console.error('Failed to load role permissions:', error.message);
                    return;
                }

                const permissions = {};
                if (data) {
                    data.forEach(perm => {
                        const key = `${perm.module}_${perm.action}`;
                        permissions[key] = perm.scope || 'all';
                    });
                }

                renderPermissionsMatrix('rolePermissionsTableBody', permissions);
            } catch (err) {
                console.error('Unexpected error loading role permissions:', err);
            }
        }

        function renderPermissionsMatrix(tbodyId, permissions, showRoleIndicator = false) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tbody.innerHTML = '';

            modules.forEach(module => {
                const tr = document.createElement('tr');

                let checkboxes = '';
                actions.forEach(action => {
                    const key = `${module}_${action}`;
                    const isChecked = permissions[key] ? 'checked' : '';
                    const scopeValue = permissions[key] || 'all';

                    // Check if this permission comes from role (for user overrides tab)
                    const isFromRole = showRoleIndicator && window.currentUserRolePermissions && window.currentUserRolePermissions[key];
                    const roleBadge = isFromRole ? ' <span class="badge bg-info ms-1" style="font-size: 9px;">Role</span>' : '';

                    checkboxes += `
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input permission-checkbox"
                                data-module="${module}" data-action="${action}" ${isChecked}>
                        </td>
                    `;
                });

                // Scope dropdown for the module (use first action's scope as default)
                const firstActionScope = permissions[`${module}_view`] || permissions[`${module}_create`] || permissions[`${module}_edit`] || permissions[`${module}_delete`] || permissions[`${module}_all`] || 'all';

                tr.innerHTML = `
                    <td class="small fw-semibold">${escapeHtml(module.replace('_', ' ').toUpperCase())}${showRoleIndicator ? roleBadge : ''}</td>
                    ${checkboxes}
                    <td class="text-center">
                        <select class="form-select form-select-sm scope-select" data-module="${module}">
                            ${scopes.map(scope => `<option value="${scope}" ${scope === firstActionScope ? 'selected' : ''}>${scope}</option>`).join('')}
                        </select>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Role form submission
        document.getElementById('roleForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('roleName').value.trim();
            const description = document.getElementById('roleDescription').value.trim();
            const saveBtn = document.getElementById('saveRoleBtn');

            if (!name) {
                showAlert('roleFormAlert', 'warning', 'Role name is required.');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';

            try {
                // Call PHP endpoint for security (service role key)
                const formData = new FormData();
                formData.append('action', 'save_role');
                formData.append('name', name);
                formData.append('description', description);
                if (selectedRole) {
                    formData.append('role_id', selectedRole.id);
                }

                const response = await fetch('permissions_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('roleFormAlert', 'success', 'Role saved successfully.');
                    document.getElementById('roleForm').reset();
                    selectedRole = null;
                    await loadRoles();
                } else {
                    showAlert('roleFormAlert', 'danger', result.message || 'Failed to save role.');
                }
            } catch (err) {
                console.error('Error saving role:', err);
                showAlert('roleFormAlert', 'danger', 'Failed to save role.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save Role';
            }
        });

        // Save role permissions
        document.getElementById('saveRolePermissionsBtn').addEventListener('click', async () => {
            if (!selectedRole) {
                showAlert('roleFormAlert', 'warning', 'Please select a role first.');
                return;
            }

            const saveBtn = document.getElementById('saveRolePermissionsBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';

            try {
                // Collect permissions
                const permissions = [];
                const checkboxes = document.querySelectorAll('#rolePermissionsTableBody .permission-checkbox:checked');
                const scopeSelects = document.querySelectorAll('#rolePermissionsTableBody .scope-select');

                checkboxes.forEach(cb => {
                    const module = cb.dataset.module;
                    const action = cb.dataset.action;
                    const scopeSelect = document.querySelector(`#rolePermissionsTableBody .scope-select[data-module="${module}"]`);
                    const scope = scopeSelect ? scopeSelect.value : 'all';

                    permissions.push({
                        role_id: selectedRole.id,
                        module: module,
                        action: action,
                        scope: scope
                    });
                });

                // Call PHP endpoint
                const formData = new FormData();
                formData.append('action', 'save_role_permissions');
                formData.append('role_id', selectedRole.id);
                formData.append('permissions', JSON.stringify(permissions));

                const response = await fetch('permissions_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('roleFormAlert', 'success', 'Permissions saved successfully.');
                } else {
                    showAlert('roleFormAlert', 'danger', result.message || 'Failed to save permissions.');
                }
            } catch (err) {
                console.error('Error saving permissions:', err);
                showAlert('roleFormAlert', 'danger', 'Failed to save permissions.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-save me-1"></i>Save Permissions';
            }
        });

        // ==========================
        // USER ASSIGNMENT TAB
        // ==========================
        async function loadUsers() {
            try {
                const { data, error } = await supabase
                    .from('users')
                    .select('id, email, full_name, role_id, department, role:roles(id,name)')
                    .order('full_name', { ascending: true });

                if (error) {
                    console.error('Failed to load users:', error.message);
                    return;
                }

                allUsers = data || [];
            } catch (err) {
                console.error('Unexpected error fetching users:', err);
            }
        }

        // User search
        const userSearchInput = document.getElementById('assignmentUserInput');
        const userSearchDropdown = document.getElementById('assignmentUserDropdown');

        userSearchInput.addEventListener('input', () => {
            const term = userSearchInput.value.trim().toLowerCase();
            userSearchDropdown.innerHTML = '';
            if (term.length < 2) {
                userSearchDropdown.classList.remove('show');
                return;
            }
            allUsers.filter(user => `${user.full_name || ''} ${user.email || ''}`.toLowerCase().includes(term)).forEach(user => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'dropdown-item';
                option.textContent = `${user.full_name || user.email} (${user.email})`;
                option.addEventListener('click', () => {
                    selectedUser = user;
                    document.getElementById('assignmentUser').value = user.id;
                    userSearchInput.value = `${user.full_name || user.email} (${user.email})`;
                    userSearchDropdown.classList.remove('show');
                    document.getElementById('selectedAssignmentUser').innerHTML = `<span class="badge bg-primary">${escapeHtml(user.full_name || user.email)} <button type="button" class="btn-close btn-close-white ms-1"></button></span>`;

                    // Set current role
                    const roleSelect = document.getElementById('userRole');
                    roleSelect.value = user.role_id || '';

                    console.log('Selected user:', selectedUser);
                    console.log('User role_id:', user.role_id);

                    // Load user overrides
                    loadUserOverrides(user.id);
                });
                userSearchDropdown.appendChild(option);
            });
            userSearchDropdown.classList.toggle('show', userSearchDropdown.children.length > 0);
        });

        async function loadUserOverrides(userId) {
            const container = document.getElementById('userOverridesContainer');
            const noUser = document.getElementById('noUserAssigned');

            if (!userId) {
                container.classList.add('d-none');
                noUser.classList.remove('d-none');
                return;
            }

            container.classList.remove('d-none');
            noUser.classList.add('d-none');

            const tbody = document.getElementById('userOverridesTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center small text-muted py-3">Loading permissions...</td></tr>';

            try {
                // Get the user's role first
                const userRoleId = selectedUser.role_id;

                // Load role permissions as base
                let rolePermissions = {};
                if (userRoleId) {
                    const { data: rolePerms, error: roleError } = await supabase
                        .from('role_permissions')
                        .select('module, action, scope')
                        .eq('role_id', userRoleId);

                    if (!roleError && rolePerms) {
                        rolePerms.forEach(perm => {
                            const key = `${perm.module}_${perm.action}`;
                            rolePermissions[key] = perm.scope || 'all';
                        });
                    }
                }

                // Load user overrides
                const { data, error } = await supabase
                    .from('user_permissions')
                    .select('module, action, scope')
                    .eq('user_email', selectedUser.email);

                const userOverrides = {};
                if (data) {
                    data.forEach(perm => {
                        const key = `${perm.module}_${perm.action}`;
                        userOverrides[key] = perm.scope || 'all';
                    });
                }

                // Merge: user overrides take precedence over role permissions
                const effectivePermissions = { ...rolePermissions, ...userOverrides };

                // Store role permissions for comparison when saving
                window.currentUserRolePermissions = rolePermissions;

                renderPermissionsMatrix('userOverridesTableBody', effectivePermissions, true);
            } catch (err) {
                console.error('Unexpected error loading user overrides:', err);
            }
        }

        // Save user role assignment
        document.getElementById('userAssignmentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!selectedUser) {
                showAlert('userAssignmentAlert', 'warning', 'Please select a user first.');
                return;
            }

            const roleId = document.getElementById('userRole').value;
            const saveBtn = document.getElementById('saveUserRoleBtn');

            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';

            try {
                const formData = new FormData();
                formData.append('action', 'save_user_role');
                formData.append('user_id', selectedUser.id);
                formData.append('role_id', roleId || '');

                const response = await fetch('permissions_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('userAssignmentAlert', 'success', 'Role assigned successfully.');
                    await loadUsers();
                } else {
                    showAlert('userAssignmentAlert', 'danger', result.message || 'Failed to assign role.');
                }
            } catch (err) {
                console.error('Error assigning role:', err);
                showAlert('userAssignmentAlert', 'danger', 'Failed to assign role.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save Assignment';
            }
        });

        // Save user overrides
        document.getElementById('saveUserOverridesBtn').addEventListener('click', async () => {
            if (!selectedUser) {
                showAlert('userAssignmentAlert', 'warning', 'Please select a user first.');
                return;
            }

            const saveBtn = document.getElementById('saveUserOverridesBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';

            try {
                const permissions = [];
                const checkboxes = document.querySelectorAll('#userOverridesTableBody .permission-checkbox:checked');
                const scopeSelects = document.querySelectorAll('#userOverridesTableBody .scope-select');

                checkboxes.forEach(cb => {
                    const module = cb.dataset.module;
                    const action = cb.dataset.action;
                    const scopeSelect = document.querySelector(`#userOverridesTableBody .scope-select[data-module="${module}"]`);
                    const scope = scopeSelect ? scopeSelect.value : 'all';

                    permissions.push({
                        user_email: selectedUser.email,
                        module: module,
                        action: action,
                        scope: scope
                    });
                });

                const formData = new FormData();
                formData.append('action', 'save_user_overrides');
                formData.append('user_email', selectedUser.email);
                formData.append('permissions', JSON.stringify(permissions));

                const response = await fetch('permissions_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('userAssignmentAlert', 'success', 'Permissions saved successfully. User now has role permissions plus these overrides.');
                } else {
                    showAlert('userAssignmentAlert', 'danger', result.message || 'Failed to save overrides.');
                }
            } catch (err) {
                console.error('Error saving overrides:', err);
                showAlert('userAssignmentAlert', 'danger', 'Failed to save overrides.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-save me-1"></i>Save Overrides';
            }
        });

        // Reset buttons
        document.getElementById('resetRoleForm').addEventListener('click', () => {
            document.getElementById('roleForm').reset();
            selectedRole = null;
        });

        // Close dropdowns on outside click
        document.addEventListener('click', (e) => {
            if (userSearchDropdown && !userSearchDropdown.contains(e.target) && e.target !== userSearchInput) {
                userSearchDropdown.classList.remove('show');
            }
        });

        // ==========================
        // EMAIL NOTIFICATIONS TAB
        // ==========================
        const notificationEvents = [
            { key: 'requisition_submitted', desc: 'Requisition submitted' },
            { key: 'requisition_approved', desc: 'Requisition approved' },
            { key: 'requisition_updated', desc: 'Requisition updated' },
            { key: 'requisition_rejected', desc: 'Requisition rejected' },
            { key: 'ticket_created', desc: 'Ticket created' },
            { key: 'ticket_updated', desc: 'Ticket updated' },
            { key: 'ticket_closed', desc: 'Ticket closed' },
            { key: 'job_card_created', desc: 'Job card created' },
            { key: 'job_card_updated', desc: 'Job card updated' },
            { key: 'incident_reported', desc: 'Incident reported' },
            { key: 'incident_settled', desc: 'Incident settled' }
        ];

        let selectedNotificationRole = null;

        // Populate notification role dropdown
        function populateNotificationRoleDropdown() {
            const select = document.getElementById('notificationRole');
            if (!select) return;
            select.innerHTML = '<option value="">Select a role</option>';
            allRoles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.name;
                select.appendChild(option);
            });
        }

        // Load role notifications
        document.getElementById('notificationRole').addEventListener('change', async (e) => {
            const roleId = e.target.value;
            if (!roleId) {
                selectedNotificationRole = null;
                document.getElementById('notificationsContainer').classList.add('d-none');
                document.getElementById('noNotificationRole').classList.remove('d-none');
                document.getElementById('saveNotificationsBtn').classList.add('d-none');
                return;
            }

            selectedNotificationRole = allRoles.find(r => r.id === roleId);
            const roleName = selectedNotificationRole.name;
            document.getElementById('notificationSubtitle').textContent = `Managing notifications for: ${roleName}`;

            const container = document.getElementById('notificationsContainer');
            const noRole = document.getElementById('noNotificationRole');
            const saveBtn = document.getElementById('saveNotificationsBtn');

            container.classList.remove('d-none');
            noRole.classList.add('d-none');
            saveBtn.classList.remove('d-none');

            await loadRoleNotifications(roleId);
        });

        async function loadRoleNotifications(roleId) {
            const tbody = document.getElementById('notificationsTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="3" class="text-center small text-muted py-3">Loading notifications...</td></tr>';

            try {
                const { data, error } = await supabase
                    .from('role_notifications')
                    .select('event_key, scope')
                    .eq('role_id', roleId);

                if (error) {
                    console.error('Failed to load notifications:', error.message);
                    return;
                }

                const notifications = {};
                if (data) {
                    data.forEach(notif => {
                        notifications[notif.event_key] = notif.scope || 'own';
                    });
                }

                renderNotificationsMatrix(notifications);
            } catch (err) {
                console.error('Unexpected error loading notifications:', err);
            }
        }

        function renderNotificationsMatrix(notifications) {
            const tbody = document.getElementById('notificationsTableBody');
            if (!tbody) return;
            tbody.innerHTML = '';

            notificationEvents.forEach(event => {
                const tr = document.createElement('tr');
                const isChecked = notifications[event.key] ? 'checked' : '';
                const scopeValue = notifications[event.key] || 'own';

                tr.innerHTML = `
                    <td class="small fw-semibold">${escapeHtml(event.key)}</td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input notification-checkbox"
                            data-event="${event.key}" ${isChecked}>
                    </td>
                    <td class="text-center">
                        <select class="form-select form-select-sm notification-scope-select" data-event="${event.key}">
                            <option value="own" ${scopeValue === 'own' ? 'selected' : ''}>Own Only</option>
                            <option value="department" ${scopeValue === 'department' ? 'selected' : ''}>Department</option>
                            <option value="all" ${scopeValue === 'all' ? 'selected' : ''}>All Departments</option>
                        </select>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Save role notifications
        document.getElementById('saveNotificationsBtn').addEventListener('click', async () => {
            if (!selectedNotificationRole) {
                showAlert('notificationFormAlert', 'warning', 'Please select a role first.');
                return;
            }

            const saveBtn = document.getElementById('saveNotificationsBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';

            try {
                // Collect notifications
                const notifications = [];
                const checkboxes = document.querySelectorAll('#notificationsTableBody .notification-checkbox:checked');

                checkboxes.forEach(cb => {
                    const eventKey = cb.dataset.event;
                    const scopeSelect = document.querySelector(`#notificationsTableBody .notification-scope-select[data-event="${eventKey}"]`);
                    const scope = scopeSelect ? scopeSelect.value : 'own';

                    notifications.push({
                        role_id: selectedNotificationRole.id,
                        event_key: eventKey,
                        scope: scope
                    });
                });

                // Call PHP endpoint
                const formData = new FormData();
                formData.append('action', 'save_role_notifications');
                formData.append('role_id', selectedNotificationRole.id);
                formData.append('notifications', JSON.stringify(notifications));

                const response = await fetch('permissions_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('notificationFormAlert', 'success', 'Notifications saved successfully.');
                } else {
                    showAlert('notificationFormAlert', 'danger', result.message || 'Failed to save notifications.');
                }
            } catch (err) {
                console.error('Error saving notifications:', err);
                showAlert('notificationFormAlert', 'danger', 'Failed to save notifications.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-save me-1"></i>Save Notifications';
            }
        });

        // Initialize
        loadRoles();
        loadUsers();
    </script>
</body>
</html>
