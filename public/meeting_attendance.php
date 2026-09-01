<?php
session_start();

require_once __DIR__ . '/../config.php';

$meetingId = $_GET['id'] ?? '';

if (empty($meetingId)) {
    die('Invalid meeting ID.');
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?id=' . htmlspecialchars($meetingId));
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
        $supabaseUrl = rtrim(SUPABASE_URL, '/');
        $supabaseKey = SUPABASE_ANON_KEY;

        // Query users table for authentication (matching login.php pattern)
        $query = http_build_query([
            'select' => 'id,full_name,email,department,role,status,temp_password,profile_picture',
            'email' => 'eq.' . $email,
            'status' => 'eq.active',
            'limit' => 1,
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept' => 'application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $userData = json_decode($response, true);
            
            if (is_array($userData) && !empty($userData[0])) {
                $user = $userData[0];
                $storedPassword = $user['temp_password'] ?? '';

                // Use hash_equals for timing-safe comparison (like login.php)
                if (hash_equals($storedPassword, $password)) {
                    $_SESSION['user_id'] = $user['id'] ?? '';
                    $_SESSION['user_email'] = $user['email'] ?? $email;
                    $_SESSION['user_name'] = $user['full_name'] ?? '';
                    $_SESSION['user_role'] = $user['role'] ?? '';
                    $_SESSION['user_department'] = $user['department'] ?? '';

                    // Load profile picture if available
                    if (isset($user['profile_picture']) && !empty($user['profile_picture'])) {
                        $_SESSION['user_profile_picture'] = $user['profile_picture'];
                    }
                    
                    // Redirect to avoid form resubmission
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    $loginError = 'Invalid email or password.';
                }
            } else {
                $loginError = 'Invalid email or password.';
            }
        } else {
            $loginError = 'Invalid email or password.';
        }
    }
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_email']);

// Check if user has already submitted attendance for this meeting
$hasSubmittedAttendance = false;
if ($isLoggedIn && defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;
    $userId = $_SESSION['user_id'] ?? '';

    if ($userId) {
        $query = http_build_query([
            'select' => '*',
            'meeting_id' => 'eq.' . $meetingId,
            'attendee_email' => 'eq.' . $_SESSION['user_email']
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/meeting_attendance?' . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept' => 'application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $attendanceData = json_decode($response, true);
        curl_close($ch);

        if (is_array($attendanceData) && !empty($attendanceData)) {
            $hasSubmittedAttendance = true;
            $existingAttendance = $attendanceData[0];
        }
    }
}

// Fetch meeting details
$meeting = null;
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    $query = http_build_query(['select' => '*', 'id' => 'eq.' . $meetingId]);
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
    $meetingData = json_decode($response, true);
    $meeting = is_array($meetingData) && !empty($meetingData[0]) ? $meetingData[0] : null;
    curl_close($ch);
}

if (!$meeting) {
    die('Meeting not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Meeting Attendance - <?php echo htmlspecialchars($meeting['title'] ?? ''); ?></title>

    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" />
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #0f172a;
            padding: 20px;
        }

        .attendance-card {
            width: 100%;
            max-width: 420px;
            padding: 32px 28px 30px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow:
                0 18px 45px rgba(15, 23, 42, 0.28),
                0 0 0 1px rgba(148, 163, 184, 0.12);
        }

        .brand {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-logo {
            height: 40px;
            margin-bottom: 8px;
        }

        .brand-subtitle {
            margin-top: 2px;
            font-size: 13px;
            color: #6b7280;
        }

        .attendance-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #111827;
            text-align: center;
        }

        .attendance-description {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 18px;
            text-align: center;
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
        }

        .field input,
        .field textarea {
            width: 100%;
            padding: 9px 11px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            font-size: 14px;
            color: #111827;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
            background-color: #f9fafb;
        }

        .field input:focus,
        .field textarea:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.3);
            background-color: #ffffff;
        }

        .field input[readonly] {
            background-color: #f3f4f6;
            cursor: default;
        }

        .attendance-actions {
            margin-top: 16px;
        }

        .btn-primary {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
            box-shadow: 0 12px 25px rgba(79, 70, 229, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
            box-shadow: 0 16px 32px rgba(79, 70, 229, 0.5);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.4);
        }

        .helper-text {
            margin-top: 10px;
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
        }

        .error-message {
            margin-bottom: 12px;
            padding: 8px 10px;
            border-radius: 8px;
            background-color: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
            border: 1px solid #fecaca;
        }

        @media (max-width: 480px) {
            .attendance-card {
                margin: 12px;
                padding: 26px 20px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="attendance-card">
        <div class="brand">
            <img
                src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg"
                alt="Texol Energies"
                class="brand-logo"
            />
        </div>

        <h1 class="attendance-title"><?php echo htmlspecialchars($meeting['title'] ?? ''); ?></h1>
        <p class="attendance-description">
            <?php echo ucfirst($meeting['type'] ?? ''); ?> - <?php echo htmlspecialchars($meeting['date'] ?? ''); ?> at <?php echo htmlspecialchars($meeting['time'] ?? ''); ?>
        </p>

        <div>
            <?php if (!$isLoggedIn): ?>
                <!-- Login Form -->
                <?php if (isset($loginError)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="field">
                        <label for="email">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            required
                        />
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                        />
                    </div>

                    <div class="attendance-actions">
                        <button type="submit" name="login" class="btn-primary">Login to Sign Attendance</button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Attendance Form -->
                <?php if ($hasSubmittedAttendance): ?>
                    <div class="error-message" style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                        You have already signed attendance for this meeting.
                        <?php if (isset($existingAttendance['comments']) && !empty($existingAttendance['comments'])): ?>
                            <br><small><strong>Your comments:</strong> <?php echo htmlspecialchars($existingAttendance['comments']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="helper-text">
                        <a href="?id=<?php echo htmlspecialchars($meetingId); ?>&logout=1" style="color: #0d6efd; text-decoration: none;">Logout</a>
                    </div>
                <?php else: ?>
                    <div id="attendanceAlert" class="error-message d-none"></div>
                    
                    <form id="attendanceForm">
                        <div class="field">
                            <label for="attendeeName">Full Name</label>
                            <input type="text" id="attendeeName" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" readonly />
                        </div>

                        <div class="field">
                            <label for="attendeeEmail">Email</label>
                            <input type="email" id="attendeeEmail" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" readonly />
                        </div>

                        <div class="field">
                            <label for="attendeeComments">Comments (Optional)</label>
                            <textarea id="attendeeComments" rows="3" placeholder="Add any comments or notes..."></textarea>
                        </div>

                        <div class="attendance-actions">
                            <button type="submit" class="btn-primary" id="submitAttendance">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <span class="btn-text">Sign Attendance</span>
                            </button>
                        </div>
                        
                        <div class="helper-text">
                            <a href="?id=<?php echo htmlspecialchars($meetingId); ?>&logout=1" style="color: #0d6efd; text-decoration: none;">Not you? Logout</a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isLoggedIn): ?>
    <script type="module">
        import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

        const supabaseUrl = '<?php echo defined("SUPABASE_URL") ? SUPABASE_URL : ""; ?>';
        const supabaseKey = '<?php echo defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""; ?>';
        const supabase = supabaseUrl && supabaseKey ? createClient(supabaseUrl, supabaseKey) : null;
        const meetingId = '<?php echo htmlspecialchars($meetingId); ?>';

        // Form submission
        const form = document.getElementById('attendanceForm');
        const submitBtn = document.getElementById('submitAttendance');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const name = document.getElementById('attendeeName').value.trim();
            const email = document.getElementById('attendeeEmail').value.trim();
            const comments = document.getElementById('attendeeComments').value.trim();

            if (!name || !email) {
                showAlert('danger', 'Please fill in all required fields.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.querySelector('.spinner-border').classList.remove('d-none');
            submitBtn.querySelector('.btn-text').textContent = 'Submitting...';

            try {
                const { error } = await supabase
                    .from('meeting_attendance')
                    .insert([{
                        meeting_id: meetingId,
                        attendee_name: name,
                        attendee_email: email,
                        comments: comments || null
                    }]);

                if (error) {
                    showAlert('danger', error.message || 'Failed to submit attendance.');
                    return;
                }

                showAlert('success', 'Attendance recorded successfully!');
                
                setTimeout(() => {
                    window.location.href = 'https://texolenergies.com';
                }, 2000);
            } catch (err) {
                showAlert('danger', 'Unexpected error submitting attendance.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.querySelector('.spinner-border').classList.add('d-none');
                submitBtn.querySelector('.btn-text').textContent = 'Sign Attendance';
            }
        });

        function showAlert(type, message) {
            const alert = document.getElementById('attendanceAlert');
            if (type === 'success') {
                alert.style.backgroundColor = '#ecfdf5';
                alert.style.color = '#047857';
                alert.style.borderColor = '#a7f3d0';
            } else {
                alert.style.backgroundColor = '#fef2f2';
                alert.style.color = '#b91c1c';
                alert.style.borderColor = '#fecaca';
            }
            alert.textContent = message;
            alert.classList.remove('d-none');
            setTimeout(() => alert.classList.add('d-none'), 5000);
        }
    </script>
    <?php endif; ?>
</body>
</html>
