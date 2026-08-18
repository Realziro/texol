<!-- Ticket Notes Modal (reusable) -->
<div class="modal fade" id="ticketNotesModal" tabindex="-1" aria-labelledby="ticketNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h6 fw-semibold mb-1" id="ticketNotesModalLabel">Ticket Notes</h2>
                    <div class="text-muted small" id="ticketNotesMeta"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ticketNotesAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>

                <input type="hidden" id="ticketNotesTicketId" value="" />

                <div class="mb-3">
                    <div class="fw-semibold small mb-2">Notes</div>
                    <div class="list-group" id="ticketNotesList"></div>
                    <div class="text-center small text-muted py-3 d-none" id="ticketNotesEmpty">
                        No notes yet.
                    </div>
                </div>

                <div id="ticketNotesComposer" class="border-top pt-3">
                    <label for="ticketNoteTextarea" class="form-label small fw-semibold">Add a note</label>
                    <textarea
                        class="form-control form-control-sm"
                        id="ticketNoteTextarea"
                        rows="3"
                        placeholder="Write a progress update, findings, actions taken..."
                    ></textarea>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="button" class="btn btn-sm btn-primary" id="addTicketNoteBtn">
                            <i class="bi bi-plus-circle me-1"></i>
                            Add Note
                        </button>
                    </div>
                    <div class="small text-muted mt-2" id="ticketNotesComposerHint"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-danger d-none" id="notesCloseTicketBtn">
                    <i class="bi bi-x-octagon me-1"></i>
                    Close Ticket
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- /Ticket Notes Modal -->

<!-- Edit Note Modal (reusable) -->
<div class="modal fade" id="ticketNoteEditModal" tabindex="-1" aria-labelledby="ticketNoteEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 fw-semibold mb-0" id="ticketNoteEditModalLabel">Edit Note</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ticketNoteEditId" value="" />
                <div class="mb-2">
                    <label for="ticketNoteEditTextarea" class="form-label small fw-semibold">Note</label>
                    <textarea
                        class="form-control form-control-sm"
                        id="ticketNoteEditTextarea"
                        rows="4"
                    ></textarea>
                </div>
                <div class="small text-muted">
                    Only the user who created a note can edit it.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveTicketNoteEditBtn">
                    Save changes
                </button>
            </div>
        </div>
    </div>
</div>
<!-- /Edit Note Modal -->
