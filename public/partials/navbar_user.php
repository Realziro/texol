<?php
// Helper function to get user profile picture from Supabase
if (!function_exists('getUserProfilePicture')) {
    function getUserProfilePicture($email) {
        if (empty($email)) {
            return '';
        }
        
        // Check session first
        if (isset($_SESSION['user_profile_picture']) && !empty($_SESSION['user_profile_picture'])) {
            $profilePic = $_SESSION['user_profile_picture'];
            // Extract filename from path
            $filename = basename($profilePic);
            $picturePath = __DIR__ . '/../uploads/profile/' . $filename;
            if (file_exists($picturePath)) {
                return $profilePic;
            } else {
                // Clear invalid session value
                unset($_SESSION['user_profile_picture']);
            }
        }
        
        // Fetch from Supabase if not in session
        if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY') && SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '') {
            $supabaseUrl = rtrim(SUPABASE_URL, '/');
            $supabaseKey = SUPABASE_ANON_KEY;
            
            $query = http_build_query([
                'select' => 'profile_picture',
                'email' => 'eq.' . urlencode($email),
                'limit' => 1,
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $supabaseUrl . '/rest/v1/users?' . $query,
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
                $rows = json_decode($response, true);
                if (is_array($rows) && count($rows) > 0 && !empty($rows[0]['profile_picture'])) {
                    $profilePicture = $rows[0]['profile_picture'];
                    // Verify file exists
                    $filename = basename($profilePicture);
                    $picturePath = __DIR__ . '/../uploads/profile/' . $filename;
                    if (file_exists($picturePath)) {
                        $_SESSION['user_profile_picture'] = $profilePicture;
                        return $profilePicture;
                    }
                }
            }
        }
        
        return '';
    }
}

// Get user info for navbar
$displayName = $_SESSION['user_name'] ?? ($_SESSION['user_email'] ?? 'User');
$email = $_SESSION['user_email'] ?? '';
$profilePicture = getUserProfilePicture($email);

// Generate initials as fallback
$initials = '';
if (!empty($_SESSION['user_name'])) {
    $parts = preg_split('/\s+/', trim($_SESSION['user_name']));
    if (count($parts) >= 2) {
        $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    } else {
        $initials = strtoupper(mb_substr($parts[0], 0, 2));
    }
} elseif (!empty($email)) {
    $initials = strtoupper(mb_substr($email, 0, 2));
} else {
    $initials = 'U';
}
?>

<div class="dropdown">
    <button
        class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2"
        type="button"
        data-bs-toggle="dropdown"
        aria-expanded="false"
    >
        <?php if (!empty($profilePicture)) : ?>
            <img src="<?php echo htmlspecialchars($profilePicture, ENT_QUOTES, 'UTF-8'); ?>" 
                 alt="Profile" 
                 class="avatar-initials"
                 style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb;">
        <?php else : ?>
            <span class="avatar-initials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <span class="small d-none d-md-inline"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
        <i class="bi bi-caret-down-fill small"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header small">Signed in as</h6></li>
        <li><span class="dropdown-item-text small text-muted">
            <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>
        </span></li>
        <li><hr class="dropdown-divider" /></li>
        <li><a class="dropdown-item small" href="profile">Profile</a></li>
        <li><a class="dropdown-item small" href="logout">Logout</a></li>
    </ul>
</div>
