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
    <title>Work Card System - Priorities</title>

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
        $activeMenu = 'priorities';
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
                    <span id="pageTitle">Priorities</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Priority Management</h1>
                    <p class="text-muted small mb-0">
                        View all priorities and add new priorities.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New Priority</h2>
                                    <p class="text-muted small mb-0">
                                        Fill in the details and save to the priorities table.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="priorityFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addPriorityForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="priorityName">Name</label>
                                            <input type="text" class="form-control form-control-sm" id="priorityName" placeholder="High" required />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="priorityLevel">Level</label>
                                            <input type="number" class="form-control form-control-sm" id="priorityLevel" placeholder="1" min="1" required />
                                            <div class="form-text small">Lower number = higher priority (used for sorting).</div>
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetPriorityForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="savePriorityBtn">Save Priority</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Priorities</h2>
                                        <p class="text-muted small mb-0">Latest priorities from the database.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshPrioritiesBtn" type="button">
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
                                                    <th class="small text-uppercase text-muted">Level</th>
                                                    <th class="small text-uppercase text-muted">Color</th>
                                                    <th class="small text-uppercase text-muted">Created At</th>
                                                    <th class="small text-uppercase text-muted text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prioritiesTableBody">
                                                <tr>
                                                    <td colspan="5" class="text-center small text-muted py-3">
                                                        Loading priorities...
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
<!-- Edit/Delete Priority Modal -->
<div class="modal fade" id="editPriorityModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Priority</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="editPriorityId">

        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" id="editPriorityName">
        </div>

        <div class="mb-2">
          <label class="form-label">Level</label>
          <input type="number" class="form-control" id="editPriorityLevel" min="1">
        </div>

        <div class="mb-2">
          <label class="form-label">Color</label>
          <input type="color" class="form-control form-control-color" id="editPriorityColor">
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button class="btn btn-danger" id="deletePriorityBtn">
          <i class="bi bi-trash"></i> Delete
        </button>

        <div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="updatePriorityBtn">Update</button>
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

        const form = document.getElementById('addPriorityForm');
        const saveBtn = document.getElementById('savePriorityBtn');
        const resetBtn = document.getElementById('resetPriorityForm');
        const refreshBtn = document.getElementById('refreshPrioritiesBtn');
        const alertBox = document.getElementById('priorityFormAlert');
        const tableBody = document.getElementById('prioritiesTableBody');
        const DEFAULT_PRIORITY_COLOR = '#cccccc';

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

        async function loadPriorities() {
            if (!tableBody) return;
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center small text-muted py-3">Loading priorities...</td>
                </tr>`;

            try {
                const { data, error } = await supabase
                    .from('priorities')
                    .select('id, name, level, color, created_at')
                    .order('level', { ascending: true });

                if (error) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-danger py-3">${escapeHtml(error.message || 'Failed to load priorities.')}</td>
                        </tr>`;
                    return;
                }

                if (!data || data.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-muted py-3">No priorities found yet.</td>
                        </tr>`;
                    return;
                }

                tableBody.innerHTML = '';
                data.forEach((priority) => {
                    const tr = document.createElement('tr');
                    const swatch = `<span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:${escapeHtml(priority.color || '#cccccc')};border:1px solid rgba(0,0,0,.1);"></span>`;

                    tr.innerHTML = `
                        <td class="small fw-semibold">${escapeHtml(priority.name)}</td>
                        <td class="small">${escapeHtml(priority.level)}</td>
                        <td class="small">${swatch} <span class="text-muted">${escapeHtml(priority.color || '-')}</span></td>
                        <td class="small text-muted">${priority.created_at ? new Date(priority.created_at).toLocaleString() : '-'}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary edit-priority-btn"
                                data-id="${priority.id}"
                                data-name="${escapeHtml(priority.name)}"
                                data-level="${escapeHtml(priority.level)}"
                                data-color="${escapeHtml(priority.color || '#cccccc')}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    `;

                    tableBody.appendChild(tr);
                });
            } catch (err) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center small text-danger py-3">Unexpected error loading priorities.</td>
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
            refreshBtn.addEventListener('click', () => loadPriorities());
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const name = document.getElementById('priorityName')?.value.trim() || '';
                const level = document.getElementById('priorityLevel')?.value.trim() || '';

                if (!name || !level) {
                    showAlert('warning', 'Please fill in all required fields.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                try {
                    const { error } = await supabase
                        .from('priorities')
                        .insert([{
                            name,
                            level: parseInt(level, 10),
                            color: DEFAULT_PRIORITY_COLOR
                        }]);

                    if (error) {
                        showAlert('danger', error.message || 'Failed to save priority.');
                        return;
                    }

                    showAlert('success', 'Priority saved successfully.');
                    form.reset();
                    await loadPriorities();
                } catch (err) {
                    showAlert('danger', 'Unexpected error saving priority.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Priority';
                }
            });
        }

        const priorityModal = new bootstrap.Modal(document.getElementById('editPriorityModal'));

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.edit-priority-btn');
            if (!btn) return;

            document.getElementById('editPriorityId').value = btn.dataset.id;
            document.getElementById('editPriorityName').value = btn.dataset.name;
            document.getElementById('editPriorityLevel').value = btn.dataset.level;
            document.getElementById('editPriorityColor').value = btn.dataset.color;

            priorityModal.show();
        });

        document.getElementById('updatePriorityBtn').addEventListener('click', async () => {
            const id = document.getElementById('editPriorityId').value;

            if (!id) {
                alert("Invalid priority ID");
                return;
            }

            const name = document.getElementById('editPriorityName').value.trim();
            const level = document.getElementById('editPriorityLevel').value.trim();
            const color = document.getElementById('editPriorityColor').value.trim();

            if (!name || !level) {
                alert('Fill all fields');
                return;
            }

            const { error } = await supabase
                .from('priorities')
                .update({ name, level: parseInt(level, 10), color })
                .eq('id', id);

            if (error) {
                alert(error.message);
                return;
            }

            priorityModal.hide();
            loadPriorities();
        });

        document.getElementById('deletePriorityBtn').addEventListener('click', async () => {
            const id = document.getElementById('editPriorityId').value;

            if (!id) {
                alert("Invalid priority ID");
                return;
            }

            if (!confirm('Are you sure you want to delete this priority?')) return;

            const { error } = await supabase
                .from('priorities')
                .delete()
                .eq('id', id);

            if (error) {
                alert(error.message);
                return;
            }

            priorityModal.hide();
            loadPriorities();
        });

        loadPriorities();
    </script>
</body>
</html>
<?php else: header('Location:   404'); exit; endif; ?>