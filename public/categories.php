<?php
session_start();

require_once __DIR__ . '/../config.php';

if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Check permission for categories module
if (!check_permission('categories', 'view')) {
    header('Location:   404');
    exit;
}

// Fetch departments from database
$departments = [];
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY') && SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    $query = http_build_query([
        'select' => 'id,name',
        'order' => 'name.asc'
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/departments?' . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $departments = json_decode($response, true) ?: [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - Categories</title>

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
        $activeMenu = 'categories';
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
                    <span id="pageTitle">Categories</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Category Management</h1>
                    <p class="text-muted small mb-0">
                        View all categories and add new categories.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New Category</h2>
                                    <p class="text-muted small mb-0">
                                        Fill in the details and save to the categories table.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="categoryFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addCategoryForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="categoryName">Name</label>
                                            <input type="text" class="form-control form-control-sm" id="categoryName" placeholder="" required />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="categoryDescription">Description</label>
                                            <textarea class="form-control form-control-sm" id="categoryDescription" rows="3" placeholder="" required></textarea>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="categoryDepartment">Department</label>
                                            <select class="form-select form-select-sm" id="categoryDepartment" required>
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo htmlspecialchars($dept['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetCategoryForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveCategoryBtn">Save Category</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Categories</h2>
                                        <p class="text-muted small mb-0">Latest categories from the database.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshCategoriesBtn" type="button">
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
                                                    <th class="small text-uppercase text-muted">Department</th>
                                                    <th class="small text-uppercase text-muted text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="categoriesTableBody">
                                                <tr>
                                                    <td colspan="4" class="text-center small text-muted py-3">
                                                        Loading categories...
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
<!-- Edit/Delete Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="editCategoryId">

        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" id="editCategoryName">
        </div>

        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="editCategoryDescription"></textarea>
        </div>

        <div class="mb-2">
          <label class="form-label">Department</label>
          <select class="form-select" id="editCategoryDepartment">
            <option value="">Select Department</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?php echo htmlspecialchars($dept['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button class="btn btn-danger" id="deleteCategoryBtn">
          <i class="bi bi-trash"></i> Delete
        </button>

        <div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="updateCategoryBtn">Update</button>
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

        const form = document.getElementById('addCategoryForm');
        const saveBtn = document.getElementById('saveCategoryBtn');
        const resetBtn = document.getElementById('resetCategoryForm');
        const refreshBtn = document.getElementById('refreshCategoriesBtn');
        const alertBox = document.getElementById('categoryFormAlert');
        const tableBody = document.getElementById('categoriesTableBody');

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

        async function loadCategories() {
            if (!tableBody) return;
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center small text-muted py-3">Loading categories...</td>
                </tr>`;

            try {
                const { data, error } = await supabase
                    .from('categories')
                    .select('id, name, description, department_id')
                    .order('created_at', { ascending: false });

                if (error) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center small text-danger py-3">${escapeHtml(error.message || 'Failed to load categories.')}</td>
                        </tr>`;
                    return;
                }

                if (!data || data.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center small text-muted py-3">No categories found yet.</td>
                        </tr>`;
                    return;
                }

                // Fetch departments for mapping
                const { data: deptData, error: deptError } = await supabase
                    .from('departments')
                    .select('id, name');

                const deptMap = {};
                if (!deptError && deptData) {
                    deptData.forEach(dept => {
                        deptMap[dept.id] = dept.name;
                    });
                }

                tableBody.innerHTML = '';
               data.forEach((cat) => {
    const tr = document.createElement('tr');
    const departmentName = cat.department_id && deptMap[cat.department_id] ? deptMap[cat.department_id] : '';

    tr.innerHTML = `
        <td class="small fw-semibold">${escapeHtml(cat.name)}</td>
        <td class="small text-muted">${escapeHtml(cat.description)}</td>
        <td class="small text-muted">${escapeHtml(departmentName)}</td>
        <td class="text-end">
            <button class="btn btn-sm btn-outline-primary edit-cat-btn"
                data-id="${cat.id}"
                data-name="${escapeHtml(cat.name)}"
                data-description="${escapeHtml(cat.description)}"
                data-department-id="${cat.department_id || ''}">
                <i class="bi bi-pencil"></i>
            </button>
        </td>
    `;

    tableBody.appendChild(tr);
});
            } catch (err) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center small text-danger py-3">Unexpected error loading categories.</td>
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
            refreshBtn.addEventListener('click', () => loadCategories());
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const name = document.getElementById('categoryName')?.value.trim() || '';
                const description = document.getElementById('categoryDescription')?.value.trim() || '';
                const departmentId = document.getElementById('categoryDepartment')?.value || '';

                if (!name || !description || !departmentId) {
                    showAlert('warning', 'Please fill in all required fields.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                try {
                    const { error } = await supabase
                        .from('categories')
                        .insert([{
                            name,
                            description,
                            department_id: departmentId
                        }]);

                    if (error) {
                        showAlert('danger', error.message || 'Failed to save category.');
                        return;
                    }

                    showAlert('success', 'Category saved successfully.');
                    form.reset();
                    await loadCategories();
                } catch (err) {
                    showAlert('danger', 'Unexpected error saving category.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Category';
                }
            });
        }
const catModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.edit-cat-btn');
    if (!btn) return;

    document.getElementById('editCategoryId').value = btn.dataset.id;
    document.getElementById('editCategoryName').value = btn.dataset.name;
    document.getElementById('editCategoryDescription').value = btn.dataset.description;
    document.getElementById('editCategoryDepartment').value = btn.dataset.departmentId || '';

    catModal.show();
});

document.getElementById('updateCategoryBtn').addEventListener('click', async () => {
    const id = document.getElementById('editCategoryId').value;

    if (!id) {
        alert("Invalid category ID");
        return;
    }

    const name = document.getElementById('editCategoryName').value.trim();
    const description = document.getElementById('editCategoryDescription').value.trim();
    const departmentId = document.getElementById('editCategoryDepartment').value;

    if (!name || !description || !departmentId) {
        alert('Fill all fields');
        return;
    }

    const { error } = await supabase
        .from('categories')
        .update({ name, description, department_id: departmentId })
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    catModal.hide();
    loadCategories();
});

document.getElementById('deleteCategoryBtn').addEventListener('click', async () => {
    const id = document.getElementById('editCategoryId').value;

    if (!id) {
        alert("Invalid category ID");
        return;
    }

    if (!confirm('Are you sure you want to delete this category?')) return;

    const { error } = await supabase
        .from('categories')
        .delete()
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    catModal.hide();
    loadCategories();
});
        loadCategories();
    </script>
</body>
</html>
