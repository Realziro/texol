<?php
session_start();

// Load env/config (Supabase keys, etc.)
require_once __DIR__ . '/../config.php';

// Protect users page: only allow access when logged in
if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Check permission for users module
if (!check_permission('users', 'view')) {
    header('Location:   404');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Work Card System - Users</title>

    <!-- Bootstrap 5 CSS CDN -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    />

    <!-- Bootstrap Icons (for sidebar icons) -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    />

    <!-- Custom CSS: Sidebar + Dashboard (reuse for layout) -->
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" />
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        // Shared sidebar, mark "users" as active here
        $activeMenu = 'users';
        include __DIR__ . '/partials/sidebar.php';
        ?>

        <!-- Main Content Wrapper -->
        <div class="main-content flex-grow-1 d-flex flex-column">
            <!-- Top Navbar -->
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
                    <span id="pageTitle">Users</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <!-- Users Content -->
            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">User Management</h1>
                    <p class="text-muted small mb-0">
                        Add new users and manage departments and roles.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New User</h2>
                                    <p class="text-muted small mb-0">
                                        Create a user account and assign them to a department.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <!-- Supabase-powered Add User form -->
                                    <div id="userFormAlert" class="alert alert-sm d-none mb-3" role="alert"></div>
                                    <form id="addUserForm" class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userName">
                                                Full Name
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">
                                                    <i class="bi bi-person"></i>
                                                </span>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="userName"
                                                    placeholder="Jane Doe"
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userEmail">
                                                Email
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">
                                                    <i class="bi bi-envelope"></i>
                                                </span>
                                                <input
                                                    type="email"
                                                    class="form-control"
                                                    id="userEmail"
                                                    placeholder="jane.doe@texol.com"
                                                    required
                                                />
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userDepartment">
                                                Department
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">
                                                    <i class="bi bi-building"></i>
                                                </span>
                                                <select
                                                    class="form-select"
                                                    id="userDepartment"
                                                    
                                                >
                                                    <option value="" selected disabled>Loading departments...</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userRole">
                                                Role
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">
                                                    <i class="bi bi-person-badge"></i>
                                                </span>
                                                <select
                                                    class="form-select"
                                                    id="userRole"
                                                    required
                                                >
                                                    <option value="" selected disabled>Loading roles...</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userPassword">
                                                Temporary Password
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">
                                                    <i class="bi bi-shield-lock"></i>
                                                </span>
                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    id="userPassword"
                                                    placeholder="Set a temporary password"
                                                    
                                                />
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-semibold" for="userStatus">
                                                Status
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">
                                                    <i class="bi bi-toggle-on"></i>
                                                </span>
                                                <select
                                                    class="form-select"
                                                    id="userStatus"
                                                    required
                                                >
                                                    <option value="active" selected>Active</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetUserForm">
                                                Reset
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveUserBtn">
                                                Save User
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card border-0 shadow-sm ">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Bulk Import Users</h2>
                                    <p class="text-muted small mb-0">
                                        Import multiple users from a CSV file.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="importAlert" class="alert alert-sm d-none mb-3" role="alert"></div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Upload CSV File</label>
                                        <input type="file" class="form-control" id="importUsersFile" accept=".csv" />
                                        <small class="text-muted">CSV format: Full Name, Email, Department, Role, Password (optional)</small>
                                    </div>
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-sm btn-success" id="importUsersBtn">
                                            <i class="bi bi-upload me-1"></i> Import Users
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="h6 fw-semibold mb-1">Recent Users</h3>
                                        <p class="text-muted small mb-0">
                                            Placeholder list for your latest users.
                                        </p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-success" id="exportUsersBtn" type="button">
                                            <i class="bi bi-file-earmark-excel me-1"></i>
                                            Export
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" id="refreshUsersBtn" type="button">
                                            <i class="bi bi-arrow-clockwise me-1"></i>
                                            Refresh
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body px-2 px-md-3 py-3">
                                    <div class="row g-2 mb-3">
                                        <div class="col-12 col-md-4">
                                            <input type="text" class="form-control form-control-sm" id="usersFilterSearch" placeholder="Search name or email..." />
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <select class="form-select form-select-sm" id="usersFilterDepartment">
                                                <option value="">All Departments</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <select class="form-select form-select-sm" id="usersFilterRole">
                                                <option value="">All Roles</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <select class="form-select form-select-sm" id="usersFilterStatus">
                                                <option value="">All Statuses</option>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-1 d-grid">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" id="clearUsersFiltersBtn">Clear</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="small text-uppercase text-muted">Name</th>
                                                    <th class="small text-uppercase text-muted">Department</th>
                                                    <th class="small text-uppercase text-muted">Role</th>
                                                    <th class="small text-uppercase text-muted text-end">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="usersTableBody">
                                                <tr id="usersTableEmptyRow">
                                                    <td colspan="4" class="text-center small text-muted py-3">
                                                        No users found yet. Add a user using the form on the left.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <nav class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="small text-muted" id="usersPaginationInfo">Showing 0 of 0</span>
                                        <ul class="pagination pagination-sm mb-0" id="usersPagination">
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editUserAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                    <form id="editUserForm" class="row g-3">
                        <input type="hidden" id="editUserOriginalEmail" />

                        <div class="col-12">
                            <div class="small text-muted">
                                Click <strong>Update User</strong> to save changes, or <strong>Delete User</strong> to remove this user.
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold" for="editUserName">Full Name</label>
                            <input type="text" class="form-control form-control-sm" id="editUserName" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold" for="editUserEmail">Email</label>
                            <input type="email" class="form-control form-control-sm" id="editUserEmail" required />
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold" for="editUserDepartment">Department</label>
                            <select class="form-select form-select-sm" id="editUserDepartment" >
                                <option value="" selected disabled>Loading departments...</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold" for="editUserRole">Role</label>
                            <select class="form-select form-select-sm" id="editUserRole" required>
                                <option value="" selected disabled>Loading roles...</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold" for="editUserStatus">Status</label>
                            <select class="form-select form-select-sm" id="editUserStatus" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <hr class="my-2" />
                            <h6 class="fw-semibold mb-2">Assigned Tickets</h6>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small text-uppercase text-muted">Title</th>
                                            <th class="small text-uppercase text-muted">Department</th>
                                            <th class="small text-uppercase text-muted">Priority</th>
                                            <th class="small text-uppercase text-muted text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="editUserTicketsBody">
                                        <tr>
                                            <td colspan="4" class="text-center small text-muted py-3">Select a user to load tickets.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-danger me-auto" id="deleteUserBtn">Delete User</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="updateUserBtn">Update User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle CDN -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>

    <!-- Reuse app.js for sidebar behavior -->
    <script src="app.js"></script>

    <!-- Supabase client (browser) - using Supabase only as a database, not for auth -->
    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = createClient(supabaseUrl, supabaseKey);

        const form = document.getElementById('addUserForm');
        const alertBox = document.getElementById('userFormAlert');
        const saveBtn = document.getElementById('saveUserBtn');
        const resetBtn = document.getElementById('resetUserForm');
        const usersTableBody = document.getElementById('usersTableBody');
        const usersTableEmptyRow = document.getElementById('usersTableEmptyRow');
        const refreshUsersBtn = document.getElementById('refreshUsersBtn');
        const userDepartmentSelect = document.getElementById('userDepartment');
        const userRoleSelect = document.getElementById('userRole');
        const editUserModalEl = document.getElementById('editUserModal');
        const editUserModal = editUserModalEl ? new bootstrap.Modal(editUserModalEl) : null;
        const editUserAlert = document.getElementById('editUserAlert');
        const editUserOriginalEmail = document.getElementById('editUserOriginalEmail');
        const editUserName = document.getElementById('editUserName');
        const editUserEmail = document.getElementById('editUserEmail');
        const editUserDepartment = document.getElementById('editUserDepartment');
        const editUserRole = document.getElementById('editUserRole');
        const editUserStatus = document.getElementById('editUserStatus');
        const updateUserBtn = document.getElementById('updateUserBtn');
        const deleteUserBtn = document.getElementById('deleteUserBtn');
        const editUserTicketsBody = document.getElementById('editUserTicketsBody');
        const usersFilterSearch = document.getElementById('usersFilterSearch');
        const usersFilterDepartment = document.getElementById('usersFilterDepartment');
        const usersFilterRole = document.getElementById('usersFilterRole');
        const usersFilterStatus = document.getElementById('usersFilterStatus');
        const clearUsersFiltersBtn = document.getElementById('clearUsersFiltersBtn');
        let allUsersData = [];

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

        function showEditAlert(type, message) {
            if (!editUserAlert) return;
            editUserAlert.className = `alert alert-${type} py-2 px-3 mb-3`;
            editUserAlert.textContent = message;
            editUserAlert.classList.remove('d-none');
        }

        function hideEditAlert() {
            if (!editUserAlert) return;
            editUserAlert.classList.add('d-none');
        }

        function esc(value) {
            return (value || '')
                .toString()
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function fillUsersFilterOptions(users) {
            const deptValues = Array.from(new Set((users || []).map((u) => (u.department || '').trim()).filter(Boolean))).sort();
            const roleValues = Array.from(new Set((users || []).map((u) => (u.role || '').trim()).filter(Boolean))).sort();

            if (usersFilterDepartment) {
                usersFilterDepartment.innerHTML = '<option value="">All Departments</option>';
                deptValues.forEach((v) => {
                    const opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    usersFilterDepartment.appendChild(opt);
                });
            }

            if (usersFilterRole) {
                usersFilterRole.innerHTML = '<option value="">All Roles</option>';
                roleValues.forEach((v) => {
                    const opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    usersFilterRole.appendChild(opt);
                });
            }
        }

        function getFilteredUsers(users) {
            const q = (usersFilterSearch?.value || '').trim().toLowerCase();
            const dept = (usersFilterDepartment?.value || '').trim().toLowerCase();
            const role = (usersFilterRole?.value || '').trim().toLowerCase();
            const status = (usersFilterStatus?.value || '').trim().toLowerCase();

            return (users || []).filter((user) => {
                const name = (user.full_name || '').toLowerCase();
                const email = (user.email || '').toLowerCase();
                const userDept = (user.department || '').toLowerCase();
                const userRole = (user.role || '').toLowerCase();
                const userStatus = (user.status || '').toLowerCase();

                if (q && !name.includes(q) && !email.includes(q)) return false;
                if (dept && userDept !== dept) return false;
                if (role && userRole !== role) return false;
                if (status && userStatus !== status) return false;
                return true;
            });
        }

        let usersCurrentPage = 1;
        const usersItemsPerPage = 10;

        function renderUsersTable(users) {
            if (!usersTableBody) return;
            usersTableBody.innerHTML = '';

            if (!users || users.length === 0) {
                usersTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center small text-muted py-3">
                            No users match the current filters.
                        </td>
                    </tr>`;
                renderUsersPagination(0);
                return;
            }

            const totalPages = Math.ceil(users.length / usersItemsPerPage);
            const startIndex = (usersCurrentPage - 1) * usersItemsPerPage;
            const endIndex = startIndex + usersItemsPerPage;
            const pageUsers = users.slice(startIndex, endIndex);

            pageUsers.forEach((user) => {
                const tr = document.createElement('tr');
                tr.className = 'user-row';
                tr.style.cursor = 'pointer';
                tr.title = 'Click to view/edit user details';
                tr.setAttribute('data-email', user.email || '');

                const nameCell = document.createElement('td');
                nameCell.innerHTML = `
                    <div class="fw-semibold small">${user.full_name || ''}</div>
                    <div class="text-muted small">${user.email || ''}</div>
                `;

                const deptCell = document.createElement('td');
                deptCell.className = 'small';
                deptCell.textContent = user.department || '';

                const roleCell = document.createElement('td');
                roleCell.className = 'small';
                roleCell.textContent = user.role || '';

                const statusCell = document.createElement('td');
                statusCell.className = 'text-end';
                const isActive = (user.status || '').toLowerCase() === 'active';
                statusCell.innerHTML = `
                    <span class="badge rounded-pill ${isActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} small">
                        ${user.status || (isActive ? 'Active' : 'Inactive')}
                    </span>
                `;

                tr.appendChild(nameCell);
                tr.appendChild(deptCell);
                tr.appendChild(roleCell);
                tr.appendChild(statusCell);

                usersTableBody.appendChild(tr);
            });

            renderUsersPagination(users.length);
        }

        function renderUsersPagination(totalItems) {
            const pagination = document.getElementById('usersPagination');
            const paginationInfo = document.getElementById('usersPaginationInfo');
            if (!pagination || !paginationInfo) return;

            const totalPages = Math.ceil(totalItems / usersItemsPerPage);
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                paginationInfo.textContent = `Showing ${totalItems} users`;
                return;
            }

            const startIndex = (usersCurrentPage - 1) * usersItemsPerPage + 1;
            const endIndex = Math.min(usersCurrentPage * usersItemsPerPage, totalItems);
            paginationInfo.textContent = `Showing ${startIndex}-${endIndex} of ${totalItems}`;

            let html = '';
            
            // Previous button
            html += `<li class="page-item ${usersCurrentPage === 1 ? 'disabled' : ''}">
                <button class="page-link" data-page="${usersCurrentPage - 1}">Previous</button>
            </li>`;

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= usersCurrentPage - 1 && i <= usersCurrentPage + 1)) {
                    html += `<li class="page-item ${i === usersCurrentPage ? 'active' : ''}">
                        <button class="page-link" data-page="${i}">${i}</button>
                    </li>`;
                } else if (i === usersCurrentPage - 2 || i === usersCurrentPage + 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            // Next button
            html += `<li class="page-item ${usersCurrentPage === totalPages ? 'disabled' : ''}">
                <button class="page-link" data-page="${usersCurrentPage + 1}">Next</button>
            </li>`;

            pagination.innerHTML = html;

            // Add click handlers
            pagination.querySelectorAll('button[data-page]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const page = parseInt(btn.getAttribute('data-page'));
                    if (page >= 1 && page <= totalPages && page !== usersCurrentPage) {
                        usersCurrentPage = page;
                        renderUsersTable(getFilteredUsers(allUsersData));
                    }
                });
            });
        }

        function applyUsersFilters() {
            usersCurrentPage = 1;
            renderUsersTable(getFilteredUsers(allUsersData));
        }

        async function loadUserTickets(userEmail) {
            if (!editUserTicketsBody) return;
            editUserTicketsBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center small text-muted py-3">Loading assigned tickets...</td>
                </tr>`;

            try {
                const { data, error } = await supabase
                    .from('tickets')
                    .select('title,department,priority,status,created_at,ticket_assignees!inner(technician_email)')
                    .eq('ticket_assignees.technician_email', userEmail)
                    .order('created_at', { ascending: false })
                    .limit(25);

                if (error) {
                    editUserTicketsBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center small text-danger py-3">${esc(error.message || 'Failed to load tickets.')}</td>
                        </tr>`;
                    return;
                }

                if (!data || data.length === 0) {
                    editUserTicketsBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center small text-muted py-3">No assigned tickets found for this user.</td>
                        </tr>`;
                    return;
                }

                editUserTicketsBody.innerHTML = '';
                data.forEach((t) => {
                    const statusValue = (t.status || '').toLowerCase();
                    let statusClass = 'bg-secondary-subtle text-secondary';
                    if (statusValue === 'open') statusClass = 'bg-danger-subtle text-danger';
                    else if (statusValue === 'in progress') statusClass = 'bg-warning-subtle text-warning';
                    else if (statusValue === 'resolved') statusClass = 'bg-success-subtle text-success';
                    else if (statusValue === 'closed') statusClass = 'bg-secondary-subtle text-secondary';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="small">${esc(t.title || '')}</td>
                        <td class="small">${esc(t.department || '')}</td>
                        <td class="small">${esc(t.priority || '')}</td>
                        <td class="text-end">
                            <span class="badge rounded-pill ${statusClass} small">${esc(t.status || '')}</span>
                        </td>
                    `;
                    editUserTicketsBody.appendChild(tr);
                });
            } catch (err) {
                console.error(err);
                editUserTicketsBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center small text-danger py-3">Unexpected error loading tickets.</td>
                    </tr>`;
            }
        }

        if (resetBtn && form) {
            resetBtn.addEventListener('click', () => {
                form.reset();
                hideAlert();
            });
        }

        async function loadDepartmentsForSelect() {
            if (!userDepartmentSelect) return;

            userDepartmentSelect.innerHTML = '<option value="" selected disabled>Loading departments...</option>';
            try {
                const { data, error } = await supabase
                    .from('departments')
                    .select('name')
                    .order('name', { ascending: true });

                if (error) {
                    console.error(error);
                    userDepartmentSelect.innerHTML = '<option value="" selected disabled>Failed to load departments</option>';
                    return;
                }

                if (!data || data.length === 0) {
                    userDepartmentSelect.innerHTML = '<option value="" selected disabled>No departments found</option>';
                    return;
                }

                userDepartmentSelect.innerHTML = '<option value="" selected disabled>Select department</option>';
                data.forEach((dept) => {
                    const name = (dept?.name || '').toString().trim();
                    if (!name) return;
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    userDepartmentSelect.appendChild(opt);
                });
            } catch (err) {
                console.error(err);
                userDepartmentSelect.innerHTML = '<option value="" selected disabled>Failed to load departments</option>';
            }
        }

        async function loadRolesForSelect() {
            if (!userRoleSelect) return;

            userRoleSelect.innerHTML = '<option value="" selected disabled>Loading roles...</option>';
            try {
                const { data, error } = await supabase
                    .from('roles')
                    .select('name')
                    .order('name', { ascending: true });

                if (error) {
                    console.error(error);
                    userRoleSelect.innerHTML = '<option value="" selected disabled>Failed to load roles</option>';
                    return;
                }

                if (!data || data.length === 0) {
                    userRoleSelect.innerHTML = '<option value="" selected disabled>No roles found</option>';
                    return;
                }

                userRoleSelect.innerHTML = '<option value="" selected disabled>Select role</option>';
                data.forEach((role) => {
                    const name = (role?.name || '').toString().trim();
                    if (!name) return;
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    userRoleSelect.appendChild(opt);
                });
            } catch (err) {
                console.error(err);
                userRoleSelect.innerHTML = '<option value="" selected disabled>Failed to load roles</option>';
            }
        }

        async function loadDepartmentsIntoEditSelect(selectedValue = '') {
            if (!editUserDepartment) return;

            editUserDepartment.innerHTML = '<option value="" selected disabled>Loading departments...</option>';
            try {
                const { data, error } = await supabase
                    .from('departments')
                    .select('name')
                    .order('name', { ascending: true });

                if (error) {
                    console.error(error);
                    editUserDepartment.innerHTML = '<option value="" selected disabled>Failed to load departments</option>';
                    return;
                }

                if (!data || data.length === 0) {
                    editUserDepartment.innerHTML = '<option value="" selected disabled>No departments found</option>';
                    return;
                }

                editUserDepartment.innerHTML = '<option value="" disabled>Select department</option>';
                data.forEach((dept) => {
                    const name = (dept?.name || '').toString().trim();
                    if (!name) return;
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    if (name === selectedValue) opt.selected = true;
                    editUserDepartment.appendChild(opt);
                });
            } catch (err) {
                console.error(err);
                editUserDepartment.innerHTML = '<option value="" selected disabled>Failed to load departments</option>';
            }
        }

        async function loadRolesIntoEditSelect(selectedValue = '') {
            if (!editUserRole) return;

            editUserRole.innerHTML = '<option value="" selected disabled>Loading roles...</option>';
            try {
                const { data, error } = await supabase
                    .from('roles')
                    .select('name')
                    .order('name', { ascending: true });

                if (error) {
                    console.error(error);
                    editUserRole.innerHTML = '<option value="" selected disabled>Failed to load roles</option>';
                    return;
                }

                if (!data || data.length === 0) {
                    editUserRole.innerHTML = '<option value="" selected disabled>No roles found</option>';
                    return;
                }

                editUserRole.innerHTML = '<option value="" disabled>Select role</option>';
                data.forEach((role) => {
                    const name = (role?.name || '').toString().trim();
                    if (!name) return;
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    if (name === selectedValue) opt.selected = true;
                    editUserRole.appendChild(opt);
                });
            } catch (err) {
                console.error(err);
                editUserRole.innerHTML = '<option value="" selected disabled>Failed to load roles</option>';
            }
        }

        async function loadUsers() {
            if (!usersTableBody) return;

            // Clear existing rows (except empty row placeholder)
            usersTableBody.innerHTML = '';

            try {
                const { data, error } = await supabase
                    .from('users')
                    .select('*')
                    .order('created_at', { ascending: false });

                if (error) {
                    console.error(error);
                    if (usersTableBody) {
                        usersTableBody.innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center small text-danger py-3">
                                    Failed to load users: ${error.message}
                                </td>
                            </tr>`;
                    }
                    return;
                }

                if (!data || data.length === 0) {
                    allUsersData = [];
                    usersTableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center small text-muted py-3">
                                No users found yet. Add a user using the form on the left.
                            </td>
                        </tr>`;
                    return;
                }

                allUsersData = data || [];
                fillUsersFilterOptions(allUsersData);
                applyUsersFilters();
            } catch (err) {
                console.error(err);
                usersTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center small text-danger py-3">
                            Unexpected error loading users.
                        </td>
                    </tr>`;
            }
        }

        if (usersTableBody) {
            usersTableBody.addEventListener('click', async (event) => {
                const row = event.target.closest('.user-row');
                if (!row) return;

                hideEditAlert();
                const email = (row.getAttribute('data-email') || '').trim();
                if (!email) return;

                const { data, error } = await supabase
                    .from('users')
                    .select('*')
                    .eq('email', email)
                    .limit(1)
                    .maybeSingle();

                if (error || !data) {
                    showAlert('danger', (error && error.message) ? error.message : 'Failed to load user details.');
                    return;
                }

                const fullName = data.full_name || '';
                const department = data.department || '';
                const role = data.role || '';
                const status = (data.status || 'active').toLowerCase();

                if (editUserOriginalEmail) editUserOriginalEmail.value = email;
                if (editUserName) editUserName.value = fullName;
                if (editUserEmail) editUserEmail.value = email;
                if (editUserStatus) editUserStatus.value = status;
                await loadDepartmentsIntoEditSelect(department);
                await loadRolesIntoEditSelect(role);
                await loadUserTickets(email);

                if (editUserModal) editUserModal.show();
            });
        }

        if (updateUserBtn) {
            updateUserBtn.addEventListener('click', async () => {
                hideEditAlert();
                const originalEmail = (editUserOriginalEmail?.value || '').trim();
                const newFullName = (editUserName?.value || '').trim();
                const newEmail = (editUserEmail?.value || '').trim();
                const newDepartment = editUserDepartment?.value || '';
                const newRole = editUserRole?.value || '';
                const newStatus = editUserStatus?.value || '';

                if (!originalEmail || !newFullName || !newEmail || !newRole || !newStatus) {
                    showEditAlert('warning', 'Please fill in all fields before updating.');
                    return;
                }

                updateUserBtn.disabled = true;
                updateUserBtn.textContent = 'Updating...';
                try {
                    const { error } = await supabase
                        .from('users')
                        .update({
                            full_name: newFullName,
                            email: newEmail,
                            department: newDepartment,
                            role: newRole,
                            status: newStatus
                        })
                        .eq('email', originalEmail);

                    if (error) {
                        console.error(error);
                        showEditAlert('danger', error.message || 'Failed to update user.');
                        return;
                    }

                    if (editUserModal) editUserModal.hide();
                    showAlert('success', 'User updated successfully.');
                    await loadUsers();
                } catch (err) {
                    console.error(err);
                    showEditAlert('danger', 'Unexpected error updating user.');
                } finally {
                    updateUserBtn.disabled = false;
                    updateUserBtn.textContent = 'Update User';
                }
            });
        }

        if (deleteUserBtn) {
            deleteUserBtn.addEventListener('click', async () => {
                hideEditAlert();
                const originalEmail = (editUserOriginalEmail?.value || '').trim();
                if (!originalEmail) {
                    showEditAlert('warning', 'Missing user identifier.');
                    return;
                }

                const confirmed = window.confirm(`Delete user "${originalEmail}"? This cannot be undone.`);
                if (!confirmed) return;

                deleteUserBtn.disabled = true;
                const originalBtnText = deleteUserBtn.textContent;
                deleteUserBtn.textContent = 'Deleting...';
                try {
                    const { error } = await supabase
                        .from('users')
                        .delete()
                        .eq('email', originalEmail);

                    if (error) {
                        console.error(error);
                        showEditAlert('danger', error.message || 'Failed to delete user.');
                        return;
                    }

                    if (editUserModal) editUserModal.hide();
                    showAlert('success', 'User deleted successfully.');
                    await loadUsers();
                } catch (err) {
                    console.error(err);
                    showEditAlert('danger', 'Unexpected error deleting user.');
                } finally {
                    deleteUserBtn.disabled = false;
                    deleteUserBtn.textContent = originalBtnText;
                }
            });
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const fullName = document.getElementById('userName').value.trim();
                const email = document.getElementById('userEmail').value.trim();
                const department = document.getElementById('userDepartment').value;
                const role = document.getElementById('userRole').value;
                const password = document.getElementById('userPassword').value;
                const status = document.getElementById('userStatus').value;

                if (!fullName || !email || !role || !password) {
                    showAlert('warning', 'Please fill in all required fields.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                try {
                    // Insert into a plain "users" table in Supabase (no Supabase Auth)
                    const { data, error } = await supabase
                        .from('users')
                        .insert([{
                            full_name: fullName,
                            email,
                            department,
                            role,
                            status,
                            temp_password: password
                        }]);

                    if (error) {
                        console.error(error);
                        showAlert('danger', error.message || 'Failed to save user to Supabase table.');
                        return;
                    }
try {
    const userEmail = document.getElementById('userEmail').value.trim();
                        const userName = document.getElementById('userName').value.trim();
                        const userPassword = document.getElementById('userPassword').value;
                        const userRole = document.getElementById('userRole').value;
                        const userDepartment = document.getElementById('userDepartment').value;
 
    const loginUrl = `${window.location.origin}/login`;
    const subject = 'Welcome to Support Portal - Your Account Has Been Created';

    const body = `
<div style="font-family: Arial, Helvetica, sans-serif; background-color: #f4f6f8; padding: 20px;">

    <div style="max-width: 600px; margin: auto; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; overflow: hidden;">

        <div style="background: #198754; color: #fff; padding: 15px 20px;">
            <h2 style="margin: 0; font-size: 18px;">Welcome to Support Portal</h2>
        </div>

        <div style="padding: 20px;">
            <p>Hello ${userName},</p>

            <p>Your account has been created successfully. Below are your login details:</p>

            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><strong>Email</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${userEmail}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><strong>Password</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${userPassword}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><strong>Role</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${userRole}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><strong>Department</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${userDepartment || 'N/A'}</td>
                </tr>
            </table>

            <p style="text-align:center;">
                <a href="${loginUrl}" style="background:#198754;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;">
                    Login to Support Portal
                </a>
            </p>

            <p>Please change your password after first login.</p>

            <p>Best regards,<br>Support Team</p>
        </div>
    </div>
</div>
`;

    // SIMPLE sendmail.php format (same as your working one)
    await fetch('sendmail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            to: userEmail,
            subject: subject,
            body: body
        })
    });

} catch (err) {
    console.error('Error sending welcome email:', err);
}
                    showAlert('success', 'User saved successfully to Supabase users table.');
                    form.reset();
                    await loadUsers();
                } catch (err) {
                    console.error(err);
                    showAlert('danger', 'Unexpected error saving user.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save User';
                }
            });
        }

        if (refreshUsersBtn) {
            refreshUsersBtn.addEventListener('click', () => {
                loadUsers();
            });
        }

        if (exportUsersBtn) {
            exportUsersBtn.addEventListener('click', () => {
                exportUsersToExcel();
            });
        }

        if (importUsersBtn) {
            importUsersBtn.addEventListener('click', () => {
                importUsersFromCSV();
            });
        }

        function showImportAlert(type, message) {
            const alert = document.getElementById('importAlert');
            if (!alert) return;
            alert.className = `alert alert-${type} py-2 px-3 mb-3`;
            alert.textContent = message;
            alert.classList.remove('d-none');
        }

        function hideImportAlert() {
            const alert = document.getElementById('importAlert');
            if (!alert) return;
            alert.classList.add('d-none');
        }

        async function importUsersFromCSV() {
            const fileInput = document.getElementById('importUsersFile');
            const file = fileInput.files[0];

            if (!file) {
                showImportAlert('warning', 'Please select a CSV file to import.');
                return;
            }

            hideImportAlert();
            importUsersBtn.disabled = true;
            importUsersBtn.textContent = 'Importing...';

            try {
                const text = await file.text();
                const lines = text.split('\n').filter(line => line.trim());
                
                if (lines.length < 2) {
                    showImportAlert('warning', 'CSV file is empty or has no data rows.');
                    return;
                }

                // Skip header row
                const dataLines = lines.slice(1);
                let successCount = 0;
                let errorCount = 0;
                const errors = [];

                for (let i = 0; i < dataLines.length; i++) {
                    const line = dataLines[i].trim();
                    if (!line) continue;

                    // Parse CSV line (handle quoted values)
                    const values = parseCSVLine(line);
                    
                    if (values.length < 4) {
                        errorCount++;
                        errors.push(`Row ${i + 2}: Invalid format (expected at least 4 columns)`);
                        continue;
                    }

                    const [fullName, email, department, role, password] = values.map(v => v.trim());

                    if (!fullName || !email || !department || !role) {
                        errorCount++;
                        errors.push(`Row ${i + 2}: Missing required fields`);
                        continue;
                    }

                    // Generate password if not provided
                    const userPassword = password || generateRandomPassword();

                    try {
                        const { error: insertError } = await supabase
                            .from('users')
                            .insert([{
                                full_name: fullName,
                                email: email.toLowerCase(),
                                department: department,
                                role: role.toLowerCase(),
                                status: 'active',
                                created_at: new Date().toISOString()
                            }]);

                        if (insertError) {
                            errorCount++;
                            errors.push(`Row ${i + 2}: ${insertError.message}`);
                        } else {
                            successCount++;
                        }
                    } catch (err) {
                        errorCount++;
                        errors.push(`Row ${i + 2}: ${err.message}`);
                    }
                }

                if (successCount > 0) {
                    showImportAlert('success', `Imported ${successCount} users successfully${errorCount > 0 ? `. ${errorCount} failed.` : ''}`);
                    await loadUsers();
                } else {
                    showImportAlert('danger', 'No users were imported. Check the errors.');
                }

                if (errors.length > 0) {
                    console.error('Import errors:', errors);
                }

                fileInput.value = '';
            } catch (err) {
                console.error('Import error:', err);
                showImportAlert('danger', 'Error processing CSV file: ' + err.message);
            } finally {
                importUsersBtn.disabled = false;
                importUsersBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Import Users';
            }
        }

        function parseCSVLine(line) {
            const result = [];
            let current = '';
            let inQuotes = false;

            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                const nextChar = line[i + 1];

                if (char === '"') {
                    if (inQuotes && nextChar === '"') {
                        current += '"';
                        i++;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === ',' && !inQuotes) {
                    result.push(current);
                    current = '';
                } else {
                    current += char;
                }
            }

            result.push(current);
            return result;
        }

        function generateRandomPassword(length = 10) {
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            return password;
        }

        function exportUsersToExcel() {
            const filteredUsers = getFilteredUsers(allUsersData);
            if (!filteredUsers || filteredUsers.length === 0) {
                showAlert('warning', 'No users to export.');
                return;
            }

            // Create CSV content
            const headers = ['Name', 'Email', 'Department', 'Role', 'Status', 'Created At'];
            const rows = filteredUsers.map(user => [
                user.full_name || '',
                user.email || '',
                user.department || '',
                user.role || '',
                user.status || '',
                user.created_at || ''
            ]);

            let csvContent = headers.join(',') + '\n';
            rows.forEach(row => {
                const escapedRow = row.map(cell => {
                    const cellStr = String(cell || '');
                    if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
                        return `"${cellStr.replace(/"/g, '""')}"`;
                    }
                    return cellStr;
                });
                csvContent += escapedRow.join(',') + '\n';
            });

            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `users_export_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        [usersFilterSearch, usersFilterDepartment, usersFilterRole, usersFilterStatus]
            .filter(Boolean)
            .forEach((el) => {
                const evt = el.tagName === 'INPUT' ? 'input' : 'change';
                el.addEventListener(evt, applyUsersFilters);
            });

        if (clearUsersFiltersBtn) {
            clearUsersFiltersBtn.addEventListener('click', () => {
                if (usersFilterSearch) usersFilterSearch.value = '';
                if (usersFilterDepartment) usersFilterDepartment.value = '';
                if (usersFilterRole) usersFilterRole.value = '';
                if (usersFilterStatus) usersFilterStatus.value = '';
                applyUsersFilters();
            });
        }

        // Initial load
        loadDepartmentsForSelect();
        loadRolesForSelect();
        loadUsers();
    </script>
</body>
</html>