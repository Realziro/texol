<?php
session_start();

require_once __DIR__ . '/../config.php';

if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

$userRole = $_SESSION['user_role'] ?? '';
$userDepartment = $_SESSION['user_department'] ?? $_SESSION['department'] ?? '';
?>
<?php if (isset($_SESSION['user_role']) && (strtolower($_SESSION['user_role']) === 'admin' || strtolower($_SESSION['user_role']) === 'hod')) : ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - Items</title>

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
        $activeMenu = 'items';
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
                    <span id="pageTitle">Items</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Items Management</h1>
                    <p class="text-muted small mb-0">
                        View all items and add new items.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New Item</h2>
                                    <p class="text-muted small mb-0">
                                        Fill in the details and save to the items table.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="itemFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addItemForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="itemName">Name</label>
                                            <input type="text" class="form-control form-control-sm" id="itemName" placeholder="Item name" required />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="itemDescription">Description</label>
                                            <textarea class="form-control form-control-sm" id="itemDescription" rows="3" placeholder="Item description..."></textarea>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="itemUnit">Unit</label>
                                            <select class="form-select form-select-sm" id="itemUnit" required>
                                                <option value="">Select Unit</option>
                                                <option value="kg">kg</option>
                                                <option value="liters">liters</option>
                                                <option value="pieces">pieces</option>
                                                <option value="bags">bags</option>
                                                <option value="boxes">boxes</option>
                                                <option value="tons">tons</option>
                                                <option value="meters">meters</option>
                                                <option value="hours">hours</option>
                                            </select>
                                        </div>

                                        <div class="col-12" id="itemDepartmentWrapper" style="display: none;">
                                            <label class="form-label small fw-semibold" for="itemDepartment">Department</label>
                                            <select class="form-select form-select-sm" id="itemDepartment">
                                                <option value="">Select Department</option>
                                            </select>
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetItemForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveItemBtn">
                                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                                <span class="btn-text">Save Item</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Items</h2>
                                        <p class="text-muted small mb-0">Latest items from the database.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshItemsBtn" type="button">
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
                                                    <th class="small text-uppercase text-muted">Unit</th>
                                                    <th class="small text-uppercase text-muted">Department</th>
                                                    <th class="small text-uppercase text-muted text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="itemsTableBody">
                                                <tr>
                                                    <td colspan="5" class="text-center small text-muted py-3">
                                                        Loading items...
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
<!-- Edit/Delete Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="editItemId">

        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" id="editItemName">
        </div>

        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="editItemDescription"></textarea>
        </div>

        <div class="mb-2">
          <label class="form-label">Unit</label>
          <select class="form-select" id="editItemUnit">
              <option value="">Select Unit</option>
              <option value="kg">kg</option>
              <option value="liters">liters</option>
              <option value="pieces">pieces</option>
              <option value="bags">bags</option>
              <option value="boxes">boxes</option>
              <option value="tons">tons</option>
              <option value="meters">meters</option>
              <option value="hours">hours</option>
          </select>
        </div>

        <div class="mb-2">
          <label class="form-label">Department</label>
          <select class="form-select" id="editItemDepartment"></select>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button class="btn btn-danger" id="deleteItemBtn">
          <i class="bi bi-trash"></i> Delete
        </button>

        <div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="updateItemBtn">Update</button>
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

        const userRole = '<?php echo strtolower($userRole); ?>';
        const userDepartment = '<?php echo htmlspecialchars($userDepartment); ?>';

        console.log('Session variables - userRole:', userRole, 'userDepartment:', userDepartment);

        const form = document.getElementById('addItemForm');
        const saveBtn = document.getElementById('saveItemBtn');
        const resetBtn = document.getElementById('resetItemForm');
        const refreshBtn = document.getElementById('refreshItemsBtn');
        const alertBox = document.getElementById('itemFormAlert');
        const tableBody = document.getElementById('itemsTableBody');

        async function loadDepartments() {
            try {
                const { data, error } = await supabase
                    .from('departments')
                    .select('id, name')
                    .order('name', { ascending: true });

                if (error) {
                    console.error('Failed to load departments:', error.message);
                    return;
                }

                const deptSelect = document.getElementById('itemDepartment');
                const editDeptSelect = document.getElementById('editItemDepartment');

                // Populate both dropdowns
                function populateSelect(selectElement) {
                    selectElement.innerHTML = '<option value="">Select Department</option>';
                    data.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id;
                        option.textContent = dept.name;
                        selectElement.appendChild(option);
                    });
                }

                populateSelect(deptSelect);
                populateSelect(editDeptSelect);

                // Hide department dropdown for all users (auto-assign from session)
                const deptWrapper = document.getElementById('itemDepartmentWrapper');
                if (deptWrapper) {
                    deptWrapper.style.display = 'none';
                }

                // Lock user's department in edit modal for non-admin users
                if (userRole !== 'admin' && userDepartment) {
                    const editDeptOption = Array.from(editDeptSelect.options).find(opt => opt.textContent === userDepartment);
                    if (editDeptOption) {
                        editDeptOption.selected = true;
                        editDeptSelect.disabled = true;
                    }
                }

            } catch (err) {
                console.error('Unexpected error fetching departments:', err);
            }
        }

        // Get user's department ID for auto-assignment
        let userDepartmentId = null;
        async function getUserDepartmentId() {
            if (userRole === 'admin') return null;
            if (!userDepartment) return null;

            try {
                const { data, error } = await supabase
                    .from('departments')
                    .select('id')
                    .eq('name', userDepartment)
                    .single();

                if (error) {
                    console.error('Failed to get user department ID:', error.message);
                    return null;
                }

                userDepartmentId = data?.id;
                return userDepartmentId;
            } catch (err) {
                console.error('Unexpected error fetching user department ID:', err);
                return null;
            }
        }

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

        async function loadItems() {
            if (!tableBody) return;
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center small text-muted py-3">Loading items...</td>
                </tr>`;

            try {
                console.log('Loading items - userRole:', userRole, 'userDepartment:', userDepartment);

                // Get user's department ID first
                let userDeptId = null;
                if (userDepartment) {
                    const { data: deptData, error: deptError } = await supabase
                        .from('departments')
                        .select('id')
                        .eq('name', userDepartment)
                        .single();

                    if (deptError) {
                        console.error('Error fetching user department:', deptError);
                    } else if (deptData) {
                        userDeptId = deptData.id;
                        console.log('User department found:', userDepartment, 'ID:', userDeptId);
                    }
                }

                // Fetch items without relationship
                let query = supabase
                    .from('items')
                    .select('id, name, description, unit, department_id')
                    .order('created_at', { ascending: false });

                // Filter by department for all users (including admins)
                if (userDeptId) {
                    console.log('Filtering items by department_id:', userDeptId);
                    query = query.eq('department_id', userDeptId);
                } else {
                    console.log('Not filtering - userDeptId is null');
                }

                const { data: items, error } = await query;

                if (error) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-danger py-3">${escapeHtml(error.message || 'Failed to load items.')}</td>
                        </tr>`;
                    return;
                }

                if (!items || items.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-muted py-3">No items found yet.</td>
                        </tr>`;
                    return;
                }

                // Fetch all departments for name lookup
                const { data: departments } = await supabase
                    .from('departments')
                    .select('id, name');

                const deptMap = {};
                if (departments) {
                    departments.forEach(dept => {
                        deptMap[dept.id] = dept.name;
                    });
                }

                tableBody.innerHTML = '';
                items.forEach((item) => {
                    const tr = document.createElement('tr');

                    tr.innerHTML = `
                        <td class="small fw-semibold">${escapeHtml(item.name)}</td>
                        <td class="small text-muted">${escapeHtml(item.description)}</td>
                        <td class="small">${escapeHtml(item.unit)}</td>
                        <td class="small">${escapeHtml(deptMap[item.department_id] || '-')}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary edit-item-btn"
                                data-id="${item.id}"
                                data-name="${escapeHtml(item.name)}"
                                data-description="${escapeHtml(item.description)}"
                                data-unit="${escapeHtml(item.unit)}"
                                data-department-id="${item.department_id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    `;

                    tableBody.appendChild(tr);
                });
            } catch (err) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center small text-danger py-3">Unexpected error loading items.</td>
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
            refreshBtn.addEventListener('click', () => loadItems());
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const name = document.getElementById('itemName')?.value.trim() || '';
                const description = document.getElementById('itemDescription')?.value.trim() || '';
                const unit = document.getElementById('itemUnit')?.value.trim() || '';

                if (!name || !unit) {
                    showAlert('warning', 'Please fill in all required fields.');
                    return;
                }

                // Get department ID from logged-in user's department name
                let departmentId = userDepartmentId;
                if (!departmentId && userDepartment) {
                    const { data: deptData, error: deptError } = await supabase
                        .from('departments')
                        .select('id')
                        .eq('name', userDepartment)
                        .single();

                    if (deptError || !deptData) {
                        showAlert('danger', 'Could not determine your department. Please contact administrator.');
                        return;
                    }
                    departmentId = deptData.id;
                    userDepartmentId = departmentId; // Cache for future use
                }

                if (!departmentId) {
                    showAlert('danger', 'Could not determine your department. Please contact administrator.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.querySelector('.spinner-border').classList.remove('d-none');
                saveBtn.querySelector('.btn-text').textContent = 'Saving...';

                try {
                    const { error } = await supabase
                        .from('items')
                        .insert([{
                            name,
                            description,
                            unit,
                            department_id: departmentId
                        }]);

                    if (error) {
                        showAlert('danger', error.message || 'Failed to save item.');
                        return;
                    }

                    showAlert('success', 'Item saved successfully.');
                    form.reset();
                    await loadItems();
                } catch (err) {
                    showAlert('danger', 'Unexpected error saving item.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.querySelector('.spinner-border').classList.add('d-none');
                    saveBtn.querySelector('.btn-text').textContent = 'Save Item';
                }
            });
        }

        const itemModal = new bootstrap.Modal(document.getElementById('editItemModal'));

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.edit-item-btn');
            if (!btn) return;

            document.getElementById('editItemId').value = btn.dataset.id;
            document.getElementById('editItemName').value = btn.dataset.name;
            document.getElementById('editItemDescription').value = btn.dataset.description;
            document.getElementById('editItemUnit').value = btn.dataset.unit;
            document.getElementById('editItemDepartment').value = btn.dataset.departmentId;

            itemModal.show();
        });

        document.getElementById('updateItemBtn').addEventListener('click', async () => {
            const id = document.getElementById('editItemId').value;

            if (!id) {
                alert("Invalid item ID");
                return;
            }

            const name = document.getElementById('editItemName').value.trim();
            const description = document.getElementById('editItemDescription').value.trim();
            const unit = document.getElementById('editItemUnit').value.trim();
            const department_id = document.getElementById('editItemDepartment').value.trim();

            if (!name || !unit || !department_id) {
                alert('Fill all required fields');
                return;
            }

            const { error } = await supabase
                .from('items')
                .update({ name, description, unit, department_id })
                .eq('id', id);

            if (error) {
                alert(error.message);
                return;
            }

            itemModal.hide();
            loadItems();
        });

        document.getElementById('deleteItemBtn').addEventListener('click', async () => {
            const id = document.getElementById('editItemId').value;

            if (!id) {
                alert("Invalid item ID");
                return;
            }

            if (!confirm('Are you sure you want to delete this item?')) return;

            const { error } = await supabase
                .from('items')
                .delete()
                .eq('id', id);

            if (error) {
                alert(error.message);
                return;
            }

            itemModal.hide();
            loadItems();
        });

        // Initialize
        loadDepartments();
        getUserDepartmentId();
        loadItems();
    </script>
</body>
</html>
<?php else: header('Location:   404'); exit; endif; ?>
