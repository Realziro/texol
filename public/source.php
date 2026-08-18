<?php
session_start();

require_once __DIR__ . '/../config.php';

if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

// Check permission for source module
if (!check_permission('source', 'view')) {
    header('Location:   404');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Support System - Source</title>

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
        $activeMenu = 'source';
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
                    <span id="pageTitle">Source</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Sources</h1>
                    <p class="text-muted small mb-0">
                        View all sources and add new sources.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Add New Source</h2>
                                    <p class="text-muted small mb-0">
                                        Fill in the details and save to the sources table.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="sourceFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addSourceForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="sourceName">Name</label>
                                            <input type="text" class="form-control form-control-sm" id="sourceName" placeholder="Phone, Email, Portal, etc." required />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="sourceDescription">Description</label>
                                            <textarea class="form-control form-control-sm" id="sourceDescription" rows="3" placeholder="Description of the source..." required></textarea>
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="resetSourceForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveSourceBtn">Save Source</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Sources</h2>
                                        <p class="text-muted small mb-0">Latest sources from the database.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" id="refreshSourcesBtn" type="button">
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
                                                    <th class="small text-uppercase text-muted text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="sourcesTableBody">
                                                <tr>
                                                    <td colspan="3" class="text-center small text-muted py-3">
                                                        Loading sources...
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
<!-- Edit/Delete Source Modal -->
<div class="modal fade" id="editSourceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Source</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="editSourceId">

        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" id="editSourceName">
        </div>

        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="editSourceDescription"></textarea>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button class="btn btn-danger" id="deleteSourceBtn">
          <i class="bi bi-trash"></i> Delete
        </button>

        <div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="updateSourceBtn">Update</button>
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

        const form = document.getElementById('addSourceForm');
        const saveBtn = document.getElementById('saveSourceBtn');
        const resetBtn = document.getElementById('resetSourceForm');
        const refreshBtn = document.getElementById('refreshSourcesBtn');
        const alertBox = document.getElementById('sourceFormAlert');
        const tableBody = document.getElementById('sourcesTableBody');

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

        async function loadSources() {
            if (!tableBody) return;
            tableBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center small text-muted py-3">Loading sources...</td>
                </tr>`;

            try {
                const { data, error } = await supabase
                    .from('source')
                    .select('id, name, description, created_at')
                    .order('created_at', { ascending: false });

                if (error) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center small text-danger py-3">${escapeHtml(error.message || 'Failed to load sources.')}</td>
                        </tr>`;
                    return;
                }

                if (!data || data.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center small text-muted py-3">No sources found yet.</td>
                        </tr>`;
                    return;
                }

                tableBody.innerHTML = '';
               data.forEach((source) => {
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td class="small fw-semibold">${escapeHtml(source.name)}</td>
        <td class="small text-muted">${escapeHtml(source.description)}</td>
        <td class="text-end">
            <button class="btn btn-sm btn-outline-primary edit-source-btn"
                data-id="${source.id}"
                data-name="${escapeHtml(source.name)}"
                data-description="${escapeHtml(source.description)}">
                <i class="bi bi-pencil"></i>
            </button>
        </td>
    `;

    tableBody.appendChild(tr);
});
            } catch (err) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center small text-danger py-3">Unexpected error loading sources.</td>
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
            refreshBtn.addEventListener('click', () => loadSources());
        }

        if (form && saveBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const name = document.getElementById('sourceName')?.value.trim() || '';
                const description = document.getElementById('sourceDescription')?.value.trim() || '';

                if (!name || !description) {
                    showAlert('warning', 'Please fill in all required fields.');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                try {
                    const { error } = await supabase
                        .from('source')
                        .insert([{
                            name,
                            description
                        }]);

                    if (error) {
                        showAlert('danger', error.message || 'Failed to save source.');
                        return;
                    }

                    showAlert('success', 'Source saved successfully.');
                    form.reset();
                    await loadSources();
                } catch (err) {
                    showAlert('danger', 'Unexpected error saving source.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Source';
                }
            });
        }
const sourceModal = new bootstrap.Modal(document.getElementById('editSourceModal'));

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.edit-source-btn');
    if (!btn) return;

    document.getElementById('editSourceId').value = btn.dataset.id;
    document.getElementById('editSourceName').value = btn.dataset.name;
    document.getElementById('editSourceDescription').value = btn.dataset.description;

    sourceModal.show();
});

document.getElementById('updateSourceBtn').addEventListener('click', async () => {
    const id = document.getElementById('editSourceId').value;

    if (!id) {
        alert("Invalid source ID");
        return;
    }

    const name = document.getElementById('editSourceName').value.trim();
    const description = document.getElementById('editSourceDescription').value.trim();

    if (!name || !description) {
        alert('Fill all fields');
        return;
    }

    const { error } = await supabase
        .from('source')
        .update({ name, description })
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    sourceModal.hide();
    loadSources();
});

document.getElementById('deleteSourceBtn').addEventListener('click', async () => {
    const id = document.getElementById('editSourceId').value;

    if (!id) {
        alert("Invalid source ID");
        return;
    }

    if (!confirm('Are you sure you want to delete this source?')) return;

    const { error } = await supabase
        .from('source')
        .delete()
        .eq('id', id);

    if (error) {
        alert(error.message);
        return;
    }

    sourceModal.hide();
    loadSources();
});
        loadSources();
    </script>
</body>
</html>
