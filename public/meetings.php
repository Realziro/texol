<?php
session_start();

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_email'])) {
    header('Location:   login');
    exit;
}

$userEmail = $_SESSION['user_email'] ?? '';

// Fetch meetings from Supabase
$meetings = [];
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    $query = http_build_query(['select' => '*', 'order' => 'date.desc,time.desc']);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/meetings?' . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Accept' => 'application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $meetings = json_decode($response, true);
    if (!is_array($meetings)) {
        $meetings = [];
    }
    curl_close($ch);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Work Card System - Meetings</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" />

    <!-- Quill Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style id="attendanceFormStyles">
        .attendance-form-printout {
            max-width: 850px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
        }

        .attendance-form-printout table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-form-printout td,
        .attendance-form-printout th {
            border: 1px solid #000;
            padding: 6px 8px;
        }

        .attendance-form-printout .form-header {
            border: 2px solid #000;
            margin-bottom: 22px;
        }

        .attendance-form-printout .company-name {
            text-align: center;
            font-weight: bold;
            font-size: 15px;
        }

        .attendance-form-printout .logo-cell {
            width: 150px;
            text-align: center;
        }

        .attendance-form-printout .logo-text {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .attendance-form-printout .logo-text .x {
            color: #8a1f2b;
        }

        .attendance-form-printout .logo-sub {
            font-size: 7px;
            letter-spacing: 2px;
            color: #444;
        }

        .attendance-form-printout .logo-tag {
            font-size: 8px;
            font-style: italic;
        }

        .attendance-form-printout .form-title-cell {
            text-align: center;
            font-size: 15px;
            width: 42%;
        }

        .attendance-form-printout .doc-info-cell {
            font-size: 12px;
            line-height: 1.5;
            width: 28%;
        }

        .attendance-form-printout .page-of-cell {
            text-align: center;
            font-size: 12px;
        }

        .attendance-form-printout .main-title {
            text-align: center;
            font-size: 22px;
            letter-spacing: 1px;
            margin: 10px 0 20px;
        }

        .attendance-form-printout .meta-table {
            margin-bottom: 18px;
        }

        .attendance-form-printout .meta-table td {
            font-size: 13px;
        }

        .attendance-form-printout .meta-table .label {
            font-weight: bold;
            width: 22%;
        }

        .attendance-form-printout .attendance th {
            background: #7f7f7f;
            color: #fff;
            text-align: left;
            font-size: 12px;
        }

        .attendance-form-printout .attendance td {
            height: 22px;
            font-size: 12px;
        }

        .attendance-form-printout .attendance .no-col {
            width: 4%;
        }

        .attendance-form-printout .attendance .name-col {
            width: 32%;
        }

        .attendance-form-printout .attendance .title-col {
            width: 22%;
        }

        .attendance-form-printout .attendance .email-col {
            width: 30%;
        }

        .attendance-form-printout .attendance .sig-col {
            width: 12%;
        }

        .attendance-form-printout .bottom {
            margin-top: 0;
        }

        .attendance-form-printout .bottom th {
            text-align: left;
            font-size: 13px;
        }

        .attendance-form-printout .bottom td {
            height: 22px;
            font-size: 12px;
        }

        .attendance-form-printout .bottom .num {
            width: 4%;
        }

        .attendance-form-printout .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 12px;
            font-style: italic;
        }

        @media print {
            .attendance-form-printout {
                max-width: none;
                padding: 0;
            }
        }

        .meetings-table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6c757d;
            background: #f8f9fa;
            border-bottom-width: 1px
        }

        .meetings-table tbody td {
            padding-top: .7rem;
            padding-bottom: .7rem
        }

        .meetings-table-wrap {
            overflow: visible !important;
        }

        .meetings-table-wrap .dropdown.show {
            position: relative;
            z-index: 1080;
        }

        .meetings-table-wrap .dropdown-menu {
            z-index: 1090;
        }

        @media (max-width: 767.98px) {
            .meetings-table-wrap {
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
        }
    </style>
</head>

<body class="dashboard-body">
    <div class="d-flex" id="layoutWrapper">
        <?php
        $activeMenu = 'meetings';
        include __DIR__ . '/partials/sidebar.php';
        ?>

        <div class="main-content flex-grow-1 d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom main-navbar px-3 px-lg-4">
                <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggleBtn" type="button"
                    aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>

                <a class="navbar-brand fw-semibold d-none d-sm-inline d-flex align-items-center gap-2" href="#">
                    <span id="pageTitle">Meetings</span>
                </a>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php include __DIR__ . '/partials/navbar_user.php'; ?>
                </div>
            </nav>

            <main class="flex-grow-1 py-4 py-md-5 px-3 px-lg-4 content-area">
                <section class="mb-4">
                    <h1 class="h4 fw-semibold mb-1">Meeting Management</h1>
                    <p class="text-muted small mb-0">
                        Create and manage meetings and training sessions with attendance tracking.
                    </p>
                </section>

                <section class="mb-4">
                    <div class="row g-3 g-lg-4">
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 px-3 px-md-4">
                                    <h2 class="h6 mb-1 fw-semibold">Create New Meeting</h2>
                                    <p class="text-muted small mb-0">
                                        Fill in the details and create a meeting with QR code for attendance.
                                    </p>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div id="meetingFormAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                                    <form id="addMeetingForm" class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold" for="meetingTitle">Title
                                                *</label>
                                            <input type="text" class="form-control form-control-sm" id="meetingTitle"
                                                placeholder="Safety Training" required />
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold" for="meetingType">Type *</label>
                                            <select class="form-select form-select-sm" id="meetingType" required>
                                                <option value="">Select Type</option>
                                                <option value="training">Training</option>
                                                <option value="meeting">Meeting</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold" for="meetingDate">Date *</label>
                                            <input type="date" class="form-control form-control-sm" id="meetingDate"
                                                required />
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold" for="meetingTime">Time *</label>
                                            <input type="time" class="form-control form-control-sm" id="meetingTime"
                                                required />
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold"
                                                for="meetingLocation">Location</label>
                                            <input type="text" class="form-control form-control-sm" id="meetingLocation"
                                                placeholder="Conference Room A" />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-semibold"
                                                for="meetingDescription">Description</label>
                                            <textarea class="form-control form-control-sm" id="meetingDescription"
                                                rows="2" placeholder="Brief description of the meeting"></textarea>
                                        </div>

                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2"
                                                id="resetMeetingForm">Reset</button>
                                            <button type="submit" class="btn btn-sm btn-primary" id="saveMeetingBtn">
                                                <span class="spinner-border spinner-border-sm d-none" role="status"
                                                    aria-hidden="true"></span>
                                                <span class="btn-text">Create Meeting</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm h-100">
                                <div
                                    class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="h6 mb-1 fw-semibold">All Meetings</h2>
                                        <p class="text-muted small mb-0">
                                            View and manage all meetings and their attendance.
                                        </p>
                                    </div>
                                </div>
                                <div class="card-body px-3 px-md-4 pb-4">
                                    <div class="table-responsive meetings-table-wrap">
                                        <table class="table table-sm table-hover align-middle meetings-table"
                                            id="meetingsTable">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Type</th>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Location</th>
                                                    <th>Attendance</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="meetingsTableBody">
                                                <?php if (empty($meetings)): ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted small py-3">No
                                                            meetings found.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($meetings as $meeting): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($meeting['title'] ?? ''); ?></strong>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    class="badge rounded-pill <?php echo ($meeting['type'] ?? '') === 'training' ? 'bg-info' : 'bg-primary'; ?> small">
                                                                    <?php echo ucfirst($meeting['type'] ?? ''); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($meeting['date'] ?? ''); ?></td>
                                                            <td><?php echo htmlspecialchars($meeting['time'] ?? ''); ?></td>
                                                            <td><?php echo htmlspecialchars($meeting['location'] ?? '-'); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-secondary small"
                                                                    id="attendance-<?php echo htmlspecialchars($meeting['id'] ?? ''); ?>">Loading...</span>
                                                            </td>
                                                            <td class="text-end">
                                                                <div class="dropdown">
                                                                    <button class="btn btn-sm btn-outline-secondary"
                                                                        type="button" data-bs-toggle="dropdown"
                                                                        aria-expanded="false" title="Meeting actions"
                                                                        aria-label="Meeting actions">
                                                                        <i class="bi bi-three-dots-vertical"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                                        <li>
                                                                            <button class="dropdown-item" type="button"
                                                                                onclick="viewMeeting('<?php echo htmlspecialchars($meeting['id'] ?? ''); ?>')">
                                                                                <i class="bi bi-eye me-2"></i>View
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button class="dropdown-item" type="button"
                                                                                onclick="editMinutes('<?php echo htmlspecialchars($meeting['id'] ?? ''); ?>')">
                                                                                <i class="bi bi-pencil me-2"></i>Edit Minutes
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button class="dropdown-item" type="button"
                                                                                onclick="showQRCode('<?php echo htmlspecialchars($meeting['id'] ?? ''); ?>')">
                                                                                <i class="bi bi-qr-code me-2"></i>QR Code
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button class="dropdown-item text-danger"
                                                                                type="button"
                                                                                onclick="deleteMeeting('<?php echo htmlspecialchars($meeting['id'] ?? ''); ?>')">
                                                                                <i class="bi bi-trash me-2"></i>Delete
                                                                            </button>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
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

    <!-- View Meeting Modal -->
    <div class="modal fade" id="viewMeetingModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Meeting Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewMeetingContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="modalEditMeeting" title="Edit meeting"
                        aria-label="Edit meeting">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success" id="modalDownloadMeeting"
                        title="Download attendance form" aria-label="Download attendance form">
                        <i class="bi bi-download"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="modalDeleteMeeting" title="Delete meeting"
                        aria-label="Delete meeting">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button type="button" class="btn btn-primary" id="modalPrintMeeting" title="Print attendance form"
                        aria-label="Print attendance form">
                        <i class="bi bi-printer"></i>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrCodeContainer"></div>
                    <p class="text-muted small mt-3">Scan this QR code to sign attendance</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success me-2" id="downloadQRCode">
                        <i class="bi bi-download"></i> Download
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Minutes Modal -->
    <div class="modal fade" id="editMinutesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Meeting Minutes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editMinutesAlert" class="alert d-none py-2 px-3 mb-3" role="alert"></div>
                    <input type="hidden" id="editMeetingId" />
                    <div id="editMeetingMinutes" style="height: 300px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveMinutesBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <span class="btn-text">Save Minutes</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
    <!-- Quill JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- jsPDF and html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="sidebar.js"></script>
    <script src="app.js"></script>

    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        let supabaseUrl = '';
        let supabaseKey = '';
        <?php if (defined('SUPABASE_URL') && SUPABASE_URL): ?>
            supabaseUrl = <?php echo json_encode(SUPABASE_URL); ?>;
        <?php endif; ?>
        <?php if (defined('SUPABASE_ANON_KEY') && SUPABASE_ANON_KEY): ?>
            supabaseKey = <?php echo json_encode(SUPABASE_ANON_KEY); ?>;
        <?php endif; ?>

        // Assign to global variable
        window.supabase = supabaseUrl && supabaseKey ? createClient(supabaseUrl, supabaseKey) : null;

        // Load attendance counts for all meetings
        async function loadAttendanceCounts() {
            if (!window.supabase) return;

            const { data: meetings } = await window.supabase.from('meetings').select('id');
            if (!meetings) return;

            for (const meeting of meetings) {
                const { count } = await window.supabase
                    .from('meeting_attendance')
                    .select('*', { count: 'exact', head: true })
                    .eq('meeting_id', meeting.id);

                const badge = document.getElementById(`attendance-${meeting.id}`);
                if (badge) {
                    badge.textContent = `${count || 0} attendees`;
                    badge.className = count > 0 ? 'badge bg-success small' : 'badge bg-warning small';
                }
            }
        }

        loadAttendanceCounts();

        // Form submission
        const form = document.getElementById('addMeetingForm');
        const saveBtn = document.getElementById('saveMeetingBtn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const title = document.getElementById('meetingTitle').value.trim();
            const type = document.getElementById('meetingType').value;
            const date = document.getElementById('meetingDate').value;
            const time = document.getElementById('meetingTime').value;
            const location = document.getElementById('meetingLocation').value.trim();
            const description = document.getElementById('meetingDescription').value.trim();

            if (!title || !type || !date || !time) {
                showAlert('danger', 'Please fill in all required fields.');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.querySelector('.spinner-border').classList.remove('d-none');
            saveBtn.querySelector('.btn-text').textContent = 'Creating...';

            try {
                const { error } = await window.supabase
                    .from('meetings')
                    .insert([{
                        title,
                        type,
                        date,
                        time,
                        location: location || null,
                        description: description || null,
                        created_by: '<?php echo $_SESSION['user_id'] ?? ''; ?>'
                    }]);

                if (error) {
                    showAlert('danger', error.message || 'Failed to create meeting.');
                    return;
                }

                showAlert('success', 'Meeting created successfully!');
                form.reset();
                location.reload();
            } catch (err) {
                showAlert('success', 'Meeting created successfully!');
                form.reset();
                location.reload();
            } finally {
                saveBtn.disabled = false;
                saveBtn.querySelector('.spinner-border').classList.add('d-none');
                saveBtn.querySelector('.btn-text').textContent = 'Create Meeting';
            }
        });

        // Reset form
        document.getElementById('resetMeetingForm').addEventListener('click', () => {
            form.reset();
        });

        let currentAttendanceForm = '';
        let currentMeeting = null;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function buildAttendanceForm(meeting, attendance) {
            const attendeeRows = Array.from({ length: 20 }, (_, index) => {
                const attendee = attendance[index] || {};
                return `<tr>
                    <td class="no-col">${index + 1}.</td>
                    <td class="name-col">${escapeHtml(attendee.attendee_name)}</td>
                    <td class="title-col"></td>
                    <td class="email-col">${escapeHtml(attendee.attendee_email)}</td>
                    <td class="sig-col"></td>
                </tr>`;
            }).join('');

            const apologyRows = Array.from({ length: 6 }, (_, index) => `
                <tr><td class="num">${index + 1}.</td><td></td><td class="num">${index + 1}.</td><td></td></tr>
            `).join('');

            return `<div class="attendance-form-printout">
               <table class="header-table">
    <tr>
      <td colspan="3" class="company-title">TEXOL ENERGIES LIMITED</td>
    </tr>
    <tr>
      <td class="logo-cell">
        <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies Logo" style="max-width: 120px; max-height: 60px;">
        <br>
        <small><i>Reliability Redefined</i></small>
      </td>
      <td class="form-title-cell">ATTENDANCE FORM</td>
      <td class="doc-info-cell">
        <div>TEX-RET-FRM-004, Ver 000</div>
        <div>Issue Date: 1<sup>st</sup> Nov 2024</div>
        <div class="page-row">Page 1 of 1</div>
      </td>
    </tr>
  </table>
                <h1 class="main-title">ATTENDANCE FORM</h1>
                <table class="meta-table">
                    <tr><td class="label">Meeting Title:</td><td>${escapeHtml(meeting.title)}</td></tr>
                    <tr><td class="label">Date and Time</td><td>${escapeHtml(meeting.date)} ${escapeHtml(meeting.time)}</td></tr>
                    <tr><td class="label">Venue:</td><td>${escapeHtml(meeting.location || '')}</td></tr>
                    <tr><td class="label">Chairperson:</td><td></td></tr>
                </table>
                <table class="attendance">
                    <tr><th class="no-col">No</th><th class="name-col">Name</th><th class="title-col">Title/ Department</th><th class="email-col">Email address/Contact</th><th class="sig-col">Signature</th></tr>
                    ${attendeeRows}
                </table>
                <table class="bottom">
                    <tr><th colspan="2">Apologies (If any)</th><th colspan="2">Absent (If any)</th></tr>
                    ${apologyRows}
                </table>
                <div class="form-footer"><div>Texol Energies Limited Attendance List</div><div>1</div></div>
            </div>`;
        }

        function buildMeetingDetails(meeting, attendance) {
            const attendeeCount = attendance ? attendance.length : 0;
            return `<div class="row g-3">
                <div class="col-md-6"><small class="text-muted">Title</small><div>${escapeHtml(meeting.title)}</div></div>
                <div class="col-md-6"><small class="text-muted">Type</small><div><span class="badge ${meeting.type === 'training' ? 'bg-info' : 'bg-primary'}">${escapeHtml(ucfirst(meeting.type))}</span></div></div>
                <div class="col-md-6"><small class="text-muted">Date</small><div>${escapeHtml(meeting.date)}</div></div>
                <div class="col-md-6"><small class="text-muted">Time</small><div>${escapeHtml(meeting.time)}</div></div>
                <div class="col-md-6"><small class="text-muted">Location</small><div>${escapeHtml(meeting.location || '-')}</div></div>
                <div class="col-md-6"><small class="text-muted">Attendance</small><div>${attendeeCount} attendee(s)</div></div>
                <div class="col-12"><small class="text-muted">Description</small><div class="border rounded p-3 bg-light" style="white-space:pre-wrap">${escapeHtml(meeting.description || '-')}</div></div>
                <div class="col-12"><small class="text-muted">Minutes</small><div class="border rounded p-3 bg-light">${meeting.minutes || '-'}</div></div>
            </div>`;
        }

        // View meeting
        window.viewMeeting = async function (meetingId) {
            const { data: meeting, error } = await window.supabase
                .from('meetings')
                .select('*')
                .eq('id', meetingId)
                .single();

            if (error || !meeting) {
                alert('Failed to load meeting details.');
                return;
            }

            const { data: attendance } = await window.supabase
                .from('meeting_attendance')
                .select('*')
                .eq('meeting_id', meetingId);

            currentMeeting = meeting;
            currentAttendanceForm = buildAttendanceForm(meeting, attendance || []);
            document.getElementById('viewMeetingContent').innerHTML = buildMeetingDetails(meeting, attendance || []);

            // Setup modal buttons
            document.getElementById('modalEditMeeting').onclick = () => editMinutes(meetingId);
            document.getElementById('modalDeleteMeeting').onclick = () => deleteMeeting(meetingId);
            document.getElementById('modalPrintMeeting').onclick = () => printAttendanceForm();
            document.getElementById('modalDownloadMeeting').onclick = () => downloadAttendanceForm();

            new bootstrap.Modal(document.getElementById('viewMeetingModal')).show();
        };

        function printAttendanceForm() {
            if (!currentAttendanceForm || !currentMeeting) return;

            const printWindow = window.open('', '_blank', 'width=900,height=1200');
            if (!printWindow) {
                alert('Please allow pop-ups to print the attendance form.');
                return;
            }

            const styles = document.getElementById('attendanceFormStyles').textContent;
            printWindow.document.write(`<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>TEX-ADM-FRM-002 Attendance Form</title><style>${styles}</style></head><body>${currentAttendanceForm}</body></html>`);
            printWindow.document.close();
            printWindow.focus();
            printWindow.onload = () => {
                printWindow.print();
            };
        }

        async function downloadAttendanceForm() {
            if (!currentAttendanceForm || !currentMeeting) return;

            const htmlContent = `<div style="padding: 20px; background: white;">${currentAttendanceForm}</div>`;

            // Create a temporary container
            const container = document.createElement('div');
            container.innerHTML = htmlContent;
            container.style.position = 'fixed';
            container.style.left = '-9999px';
            container.style.top = '0';
            container.style.width = '210mm';
            container.style.background = 'white';
            document.body.appendChild(container);

            try {
                // Wait for any images to load
                await new Promise(resolve => setTimeout(resolve, 500));

                // Use html2canvas to capture the content
                const canvas = await html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                });

                // Create PDF using jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');

                // Calculate dimensions to fit A4
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 297; // A4 height in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;

                let heightLeft = imgHeight;
                let position = 0;

                // Add first page
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                // Add additional pages if needed
                while (heightLeft > 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                // Save the PDF
                pdf.save(`attendance_form_${currentMeeting.date || 'date'}.pdf`);

            } catch (error) {
                console.error('PDF generation error:', error);
                alert('Failed to generate PDF. Please try again.');
            } finally {
                // Clean up
                document.body.removeChild(container);
            }
        }

        // Show QR Code
        window.showQRCode = function (meetingId) {
            const attendanceUrl = `${window.location.origin}/meeting_attendance.php?id=${meetingId}`;

            document.getElementById('qrCodeContainer').innerHTML = '';

            try {
                new QRCode(document.getElementById('qrCodeContainer'), {
                    text: attendanceUrl,
                    width: 256,
                    height: 256
                });
            } catch (error) {
                console.error(error);
                document.getElementById('qrCodeContainer').innerHTML = '<p class="text-danger">Failed to generate QR code.</p>';
            }

            new bootstrap.Modal(document.getElementById('qrCodeModal')).show();
        };

        // Download QR Code
        document.getElementById('downloadQRCode').addEventListener('click', function () {
            const img = document.querySelector('#qrCodeContainer img');
            if (!img) {
                alert('No QR code to download.');
                return;
            }

            const link = document.createElement('a');
            link.download = 'attendance-qr-code.png';
            link.href = img.src;
            link.click();
        });

        // Delete meeting
        window.deleteMeeting = async function (meetingId) {
            if (!confirm('Are you sure you want to delete this meeting? This will also delete all attendance records.')) {
                return;
            }

            const { error } = await window.supabase
                .from('meetings')
                .delete()
                .eq('id', meetingId);

            if (error) {
                alert('Failed to delete meeting: ' + error.message);
                return;
            }

            alert('Meeting deleted successfully.');
            location.reload();
        };

        // Edit minutes
        let editQuill = null;
        window.editMinutes = async function (meetingId) {
            const { data: meeting, error } = await window.supabase
                .from('meetings')
                .select('minutes')
                .eq('id', meetingId)
                .single();

            if (error || !meeting) {
                alert('Failed to load meeting minutes.');
                return;
            }

            document.getElementById('editMeetingId').value = meetingId;

            // Initialize or reinitialize Quill editor
            if (editQuill) {
                editQuill.root.innerHTML = meeting.minutes || '';
            } else {
                editQuill = new Quill('#editMeetingMinutes', {
                    theme: 'snow',
                    placeholder: 'Enter meeting minutes or notes here...',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            [{ 'color': [] }, { 'background': [] }],
                            ['link', 'clean']
                        ]
                    }
                });
                editQuill.root.innerHTML = meeting.minutes || '';
            }

            new bootstrap.Modal(document.getElementById('editMinutesModal')).show();
        };

        // Save minutes
        document.getElementById('saveMinutesBtn').addEventListener('click', async () => {
            const meetingId = document.getElementById('editMeetingId').value;
            const minutes = editQuill.root.innerHTML;

            if (!meetingId) {
                showEditAlert('danger', 'Invalid meeting ID.');
                return;
            }

            const saveBtn = document.getElementById('saveMinutesBtn');
            saveBtn.disabled = true;
            saveBtn.querySelector('.spinner-border').classList.remove('d-none');
            saveBtn.querySelector('.btn-text').textContent = 'Saving...';

            try {
                const { error } = await window.supabase
                    .from('meetings')
                    .update({ minutes })
                    .eq('id', meetingId);

                if (error) {
                    showEditAlert('danger', error.message || 'Failed to save minutes.');
                    return;
                }

                showEditAlert('success', 'Minutes saved successfully!');
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('editMinutesModal')).hide();
                    location.reload();
                }, 1000);
            } catch (err) {
                showEditAlert('danger', 'Unexpected error saving minutes.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.querySelector('.spinner-border').classList.add('d-none');
                saveBtn.querySelector('.btn-text').textContent = 'Save Minutes';
            }
        });

        function showEditAlert(type, message) {
            const alert = document.getElementById('editMinutesAlert');
            alert.className = `alert alert-${type} py-2 px-3 mb-3`;
            alert.textContent = message;
            alert.classList.remove('d-none');
            setTimeout(() => alert.classList.add('d-none'), 5000);
        }

        function showAlert(type, message) {
            const alert = document.getElementById('meetingFormAlert');
            alert.className = `alert alert-${type} py-2 px-3 mb-3`;
            alert.textContent = message;
            alert.classList.remove('d-none');
            setTimeout(() => alert.classList.add('d-none'), 5000);
        }
    </script>
</body>

</html>