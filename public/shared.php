<?php
session_start();

// Load env/config (Supabase keys, etc.)
require_once __DIR__ . '/../config.php';

// Protect shared page: only allow access when logged in
if (! isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Work Card System - Shared With Me</title>

    <!-- Bootstrap 5 CSS CDN -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    />

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    />

    <!-- Reuse layout styles -->
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" />
</head>
<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'shared';
        include __DIR__ . '/partials/sidebar.php';
        ?>

        <!-- Main Content -->
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
                    <span id="pageTitle">Shared With Me</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/notifications.php'; ?>
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Shared With Me</h1>
                    <p class="text-muted small mb-0">
                        Tickets assigned to you.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h6 mb-1 fw-semibold">
                                    <i class="bi bi-inbox me-1"></i>
                                    Assigned Tickets
                                </h2>
                                <p class="text-muted small mb-0">
                                    Showing tickets where you are the assigned technician.
                                </p>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" id="refreshAssignedBtn" type="button">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                Refresh
                            </button>
                        </div>
                        <div class="card-body px-2 px-md-3 py-3">
                            <div id="assignedAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small text-uppercase text-muted">Title</th>
                                            <th class="small text-uppercase text-muted">Requested By</th>
                                            <th class="small text-uppercase text-muted">Dept</th>
                                            <th class="small text-uppercase text-muted">Priority</th>
                                            <th class="small text-uppercase text-muted text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="assignedTableBody">
                                        <tr>
                                            <td colspan="5" class="text-center small text-muted py-3">
                                                Loading assigned tickets...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="text-muted small mt-2 mb-0">
                                    Note: Closed tickets are not clickable.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/partials/ticket_notes_modal.php'; ?>

    <!-- Bootstrap JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>

    <!-- Sidebar behavior -->
    <script src="app.js"></script>

    <!-- Supabase client (assigned tickets) -->
    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = createClient(supabaseUrl, supabaseKey);

        const currentUserEmail = <?php echo json_encode($_SESSION['user_email'] ?? ''); ?>;

        const assignedTableBody = document.getElementById('assignedTableBody');
        const assignedAlert = document.getElementById('assignedAlert');
        const refreshAssignedBtn = document.getElementById('refreshAssignedBtn');

        function showAssignedAlert(type, message) {
            if (!assignedAlert) return;
            assignedAlert.className = `alert alert-${type} py-2 px-3 mb-3`;
            assignedAlert.textContent = message;
            assignedAlert.classList.remove('d-none');
        }

        function hideAssignedAlert() {
            if (!assignedAlert) return;
            assignedAlert.classList.add('d-none');
        }

        function renderPriorityBadge(priority) {
            const prio = (priority || '').toLowerCase();
            let prioClass = 'bg-secondary-subtle text-secondary';
            if (prio === 'low') prioClass = 'bg-success-subtle text-success';
            else if (prio === 'medium') prioClass = 'bg-info-subtle text-info';
            else if (prio === 'high') prioClass = 'bg-warning-subtle text-warning';
            else if (prio === 'critical') prioClass = 'bg-danger-subtle text-danger';
            return `<span class="badge rounded-pill ${prioClass} small">${priority || ''}</span>`;
        }

        function renderStatusBadge(status) {
            const statusValue = (status || '').toLowerCase();
            let statusClass = 'bg-secondary-subtle text-secondary';
            if (statusValue === 'open') statusClass = 'bg-danger-subtle text-danger';
            else if (statusValue === 'in progress') statusClass = 'bg-warning-subtle text-warning';
            else if (statusValue === 'resolved') statusClass = 'bg-success-subtle text-success';
            else if (statusValue === 'closed') statusClass = 'bg-secondary-subtle text-secondary';
            return `<span class="badge rounded-pill ${statusClass} small">${status || ''}</span>`;
        }

        // Notes modal (shared ticket notes)
        const notesModalEl = document.getElementById('ticketNotesModal');
        const notesModal = notesModalEl ? new bootstrap.Modal(notesModalEl) : null;
        const ticketNotesMeta = document.getElementById('ticketNotesMeta');
        const ticketNotesAlert = document.getElementById('ticketNotesAlert');
        const ticketNotesTicketId = document.getElementById('ticketNotesTicketId');
        const ticketNotesList = document.getElementById('ticketNotesList');
        const ticketNotesEmpty = document.getElementById('ticketNotesEmpty');
        const ticketNotesComposer = document.getElementById('ticketNotesComposer');
        const ticketNotesComposerHint = document.getElementById('ticketNotesComposerHint');
        const ticketNoteTextarea = document.getElementById('ticketNoteTextarea');
        const addTicketNoteBtn = document.getElementById('addTicketNoteBtn');
        const notesCloseTicketBtn = document.getElementById('notesCloseTicketBtn');

        // Edit note modal elements
        const noteEditModalEl = document.getElementById('ticketNoteEditModal');
        const noteEditModal = noteEditModalEl ? new bootstrap.Modal(noteEditModalEl) : null;
        const ticketNoteEditId = document.getElementById('ticketNoteEditId');
        const ticketNoteEditTextarea = document.getElementById('ticketNoteEditTextarea');
        const saveTicketNoteEditBtn = document.getElementById('saveTicketNoteEditBtn');

        const currentUserName = <?php echo json_encode($_SESSION['user_name'] ?? ''); ?>;
        const currentUserRole = <?php echo json_encode($_SESSION['user_role'] ?? ''); ?>;
        const isAdmin = (currentUserRole || '').toLowerCase() === 'admin';

        let activeTicketForNotes = null;

        function showNotesAlert(type, message) {
            if (!ticketNotesAlert) return;
            ticketNotesAlert.className = `alert alert-${type} py-2 px-3 mb-3`;
            ticketNotesAlert.textContent = message;
            ticketNotesAlert.classList.remove('d-none');
        }

        function hideNotesAlert() {
            if (!ticketNotesAlert) return;
            ticketNotesAlert.classList.add('d-none');
        }

        function isAssignedTechnician(ticket) {
            const assignees = Array.isArray(ticket.ticket_assignees) ? ticket.ticket_assignees : [];
            return assignees.some((a) => (a.technician_email || '').trim() === currentUserEmail);
        }

        function canAddNotes(ticket) {
            if (!ticket) return false;
            return isAdmin || isAssignedTechnician(ticket);
        }

        function canCloseTicketFromNotes(ticket) {
            if (!ticket) return false;
            const status = (ticket.status || '').toLowerCase();
            if (status === 'closed') return false;
            return isAdmin || isAssignedTechnician(ticket);
        }

        function escapeHtml(str) {
            return (str || '')
                .toString()
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function renderNoteItem(note) {
            const authorName = (note.created_by_name || '').trim();
            const authorEmail = (note.created_by_email || '').trim();
            const author = authorName || authorEmail || 'Unknown';
            const createdAt = note.created_at ? new Date(note.created_at).toLocaleString() : '';
            const body = escapeHtml(note.note || '');
            const isOwner = authorEmail && authorEmail === currentUserEmail;
            return `
                <div class="list-group-item" data-note-id="${note.id || ''}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="small fw-semibold">${escapeHtml(author)}</div>
                            <div class="small text-muted">${escapeHtml(createdAt)}</div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            ${isOwner ? `<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none note-edit-btn" title="Edit note">
                                <i class="bi bi-pencil-square"></i>
                            </button>` : ''}
                        </div>
                    </div>
                    <div class="small text-muted mt-1" style="white-space: pre-wrap;">${body}</div>
                </div>
            `;
        }

        async function loadNotes(ticketId) {
            if (!ticketNotesList || !ticketNotesEmpty) return;
            hideNotesAlert();
            ticketNotesList.innerHTML = '';
            ticketNotesEmpty.classList.add('d-none');

            try {
                const { data, error } = await supabase
                    .from('ticket_notes')
                    .select('*')
                    .eq('ticket_id', ticketId)
                    .order('created_at', { ascending: true });

                if (error) {
                    console.error(error);
                    showNotesAlert('danger', error.message || 'Failed to load notes.');
                    ticketNotesEmpty.classList.remove('d-none');
                    return;
                }

                if (!data || data.length === 0) {
                    ticketNotesEmpty.classList.remove('d-none');
                    return;
                }

                ticketNotesList.innerHTML = data.map(renderNoteItem).join('');

                // Attach edit handlers for notes owned by current user (open edit modal)
                const notesById = new Map((data || []).map((n) => [n.id, n]));
                const editButtons = ticketNotesList.querySelectorAll('.note-edit-btn');
                editButtons.forEach((btn) => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const parent = btn.closest('[data-note-id]');
                        const noteId = parent?.getAttribute('data-note-id');
                        if (!noteId || !notesById.has(noteId) || !noteEditModal) return;
                        const note = notesById.get(noteId);
                        if (ticketNoteEditId) ticketNoteEditId.value = note.id || '';
                        if (ticketNoteEditTextarea) ticketNoteEditTextarea.value = note.note || '';
                        hideNotesAlert();
                        noteEditModal.show();
                    });
                });
            } catch (err) {
                console.error(err);
                showNotesAlert('danger', 'Unexpected error loading notes.');
                ticketNotesEmpty.classList.remove('d-none');
            }
        }

        async function openNotes(ticket) {
            if (!notesModal) return;
            activeTicketForNotes = ticket;
            hideNotesAlert();

            const ticketId = ticket?.id || '';
            if (ticketNotesTicketId) ticketNotesTicketId.value = ticketId;

            const metaParts = [];
            if (ticket?.title) metaParts.push(ticket.title);
            if (ticket?.department) metaParts.push(ticket.department);
            if (ticket?.priority) metaParts.push(`Priority: ${ticket.priority}`);
            if (ticket?.status) metaParts.push(`Status: ${ticket.status}`);
            if (ticketNotesMeta) ticketNotesMeta.textContent = metaParts.join(' · ');

            const allowAdd = canAddNotes(ticket);
            if (ticketNotesComposer) ticketNotesComposer.classList.toggle('d-none', !allowAdd);
            if (ticketNotesComposerHint) {
                ticketNotesComposerHint.textContent = allowAdd
                    ? 'Notes are visible to anyone who can access this ticket.'
                    : 'Only the assigned technician or admin can add notes.';
            }

            const allowClose = canCloseTicketFromNotes(ticket);
            if (notesCloseTicketBtn) {
                notesCloseTicketBtn.classList.toggle('d-none', !allowClose);
            }
            if (ticketNoteTextarea) ticketNoteTextarea.value = '';

            notesModal.show();
            await loadNotes(ticketId);
        }

        if (addTicketNoteBtn) {
            addTicketNoteBtn.addEventListener('click', async () => {
                hideNotesAlert();
                const ticket = activeTicketForNotes;
                const ticketId = ticket?.id;
                const body = (ticketNoteTextarea?.value || '').trim();

                if (!ticketId) return;
                if (!body) {
                    showNotesAlert('warning', 'Please write a note first.');
                    return;
                }
                if (!canAddNotes(ticket)) {
                    showNotesAlert('danger', 'You are not allowed to add notes to this ticket.');
                    return;
                }

                addTicketNoteBtn.disabled = true;
                const oldLabel = addTicketNoteBtn.innerHTML;
                addTicketNoteBtn.innerHTML = 'Saving...';

                try {
                    const { error } = await supabase
                        .from('ticket_notes')
                        .insert([{
                            ticket_id: ticketId,
                            note: body,
                            created_by_email: currentUserEmail || null,
                            created_by_name: currentUserName || null,
                        }]);

                    if (error) {
                        console.error(error);
                        showNotesAlert('danger', error.message || 'Failed to save note.');
                        return;
                    }

                    // Move ticket to In Progress when a note is posted
                    try {
                        await supabase
                            .from('tickets')
                            .update({ status: 'In Progress' })
                            .eq('id', ticketId);
                    } catch (statusErr) {
                        console.error('Failed to update status to In Progress', statusErr);
                    }

                    if (ticketNoteTextarea) ticketNoteTextarea.value = '';
                    await loadNotes(ticketId);
                    await loadAssignedTickets();
                } catch (err) {
                    console.error(err);
                    showNotesAlert('danger', 'Unexpected error saving note.');
                } finally {
                    addTicketNoteBtn.disabled = false;
                    addTicketNoteBtn.innerHTML = oldLabel;
                }
            });
        }

        if (saveTicketNoteEditBtn) {
            saveTicketNoteEditBtn.addEventListener('click', async () => {
                hideNotesAlert();
                const noteId = ticketNoteEditId?.value;
                const newBody = (ticketNoteEditTextarea?.value || '').trim();
                const ticketId = ticketNotesTicketId?.value;

                if (!noteId || !ticketId) return;
                if (!newBody) {
                    showNotesAlert('warning', 'Note cannot be empty.');
                    return;
                }

                try {
                    const { error } = await supabase
                        .from('ticket_notes')
                        .update({ note: newBody })
                        .eq('id', noteId)
                        .eq('created_by_email', currentUserEmail);

                    if (error) {
                        console.error(error);
                        showNotesAlert('danger', error.message || 'Failed to update note.');
                        return;
                    }

                    noteEditModal?.hide();
                    await loadNotes(ticketId);
                } catch (err) {
                    console.error(err);
                    showNotesAlert('danger', 'Unexpected error updating note.');
                }
            });
        }

        if (notesCloseTicketBtn) {
            notesCloseTicketBtn.addEventListener('click', async () => {
                hideNotesAlert();
                const ticket = activeTicketForNotes;
                const ticketId = ticket?.id;

                if (!ticketId || !ticket) return;
                if (!canCloseTicketFromNotes(ticket)) {
                    showNotesAlert('danger', 'You are not allowed to close this ticket.');
                    return;
                }

                const confirmClose = window.confirm('Are you sure you want to mark this ticket as Closed?');
                if (!confirmClose) return;

                try {
                    const { error } = await supabase
                        .from('tickets')
                        .update({ status: 'Closed' })
                        .eq('id', ticketId);

                    if (error) {
                        console.error(error);
                        showNotesAlert('danger', error.message || 'Failed to close ticket.');
                        return;
                    }

                    notesModal?.hide();
                    await loadAssignedTickets();
                } catch (err) {
                    console.error(err);
                    showNotesAlert('danger', 'Unexpected error closing ticket.');
                }
            });
        }

        async function resolveRequesterNames(tickets) {
            // Map requested_by email -> full_name
            const emails = Array.from(
                new Set(
                    (tickets || [])
                        .map((t) => (t.requested_by || '').trim())
                        .filter(Boolean)
                )
            );

            if (emails.length === 0) return {};

            const { data, error } = await supabase
                .from('users')
                .select('email, full_name')
                .in('email', emails);

            if (error) {
                console.error('Failed to resolve requester names', error);
                return {};
            }

            const map = {};
            (data || []).forEach((u) => {
                const key = (u.email || '').trim();
                if (!key) return;
                map[key] = (u.full_name || '').trim();
            });
            return map;
        }

        async function loadAssignedTickets() {
            if (!assignedTableBody) return;
            hideAssignedAlert();

            assignedTableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center small text-muted py-3">
                        Loading assigned tickets...
                    </td>
                </tr>`;

            try {
                if (!currentUserEmail) {
                    assignedTableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-danger py-3">
                                Missing user session. Please log in again.
                            </td>
                        </tr>`;
                    return;
                }

                const { data, error } = await supabase
                    .from('tickets')
                    .select('*, ticket_assignees!inner(technician_email)')
                    .eq('ticket_assignees.technician_email', currentUserEmail)
                    .order('created_at', { ascending: false });

                if (error) {
                    console.error(error);
                    assignedTableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-danger py-3">
                                Failed to load assigned tickets: ${error.message}
                            </td>
                        </tr>`;
                    return;
                }

                if (!data || data.length === 0) {
                    assignedTableBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center small text-muted py-3">
                                No tickets have been assigned to you yet.
                            </td>
                        </tr>`;
                    return;
                }

                assignedTableBody.innerHTML = '';

                const requesterNameMap = await resolveRequesterNames(data);

                data.forEach((ticket) => {
                    const tr = document.createElement('tr');

                    const statusValue = (ticket.status || '').toLowerCase();
                    const isClosed = statusValue === 'closed';

                    if (!isClosed) {
                        tr.style.cursor = 'pointer';
                        tr.setAttribute('role', 'button');
                        tr.setAttribute('tabindex', '0');
                        tr.title = 'Click to view notes';
                        tr.addEventListener('click', () => {
                            openNotes(ticket);
                        });
                        tr.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                openNotes(ticket);
                            }
                        });
                    }

                    const requesterEmail = (ticket.requested_by || '').trim();
                    const requesterName = requesterNameMap[requesterEmail] || '';
                    const requestedByDisplay = requesterName || requesterEmail || '';

                    tr.innerHTML = `
                        <td>
                            <div class="fw-semibold small">${ticket.title || ''}</div>
                            <div class="text-muted small">${ticket.description ? ticket.description.substring(0, 60) + (ticket.description.length > 60 ? '…' : '') : ''}</div>
                        </td>
                        <td class="small">${requestedByDisplay}</td>
                        <td class="small">${ticket.department || ''}</td>
                        <td class="small">${renderPriorityBadge(ticket.priority)}</td>
                        <td class="text-end">${renderStatusBadge(ticket.status)}</td>
                    `;

                    assignedTableBody.appendChild(tr);
                });
            } catch (err) {
                console.error(err);
                assignedTableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center small text-danger py-3">
                            Unexpected error loading assigned tickets.
                        </td>
                    </tr>`;
            }
        }

        if (refreshAssignedBtn) {
            refreshAssignedBtn.addEventListener('click', loadAssignedTickets);
        }

        loadAssignedTickets();
    </script>
</body>
</html>

