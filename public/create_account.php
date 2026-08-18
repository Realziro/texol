<?php
// Get email from URL
$email = $_GET['email'] ?? '';

// Validate email
$emailValid = !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);

// Supabase config
$supabase_url = "https://pjwvfuyzbzayxqxisysi.supabase.co/rest/v1/users";
$supabase_key = "sb_publishable_0irc-UsepAkekLihnXz8Mw_ouNmhqld"; // replace with your key

$hasPassword = false;
$userExists = false;
$errorMsg = '';

if ($emailValid) {
    // Query Supabase
    $url = $supabase_url . "?email=eq." . urlencode($email);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabase_key",
        "Authorization: Bearer $supabase_key",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $errorMsg = curl_error($ch);
    } else {
        $data = json_decode($response, true);
        if (!empty($data)) {
            $userExists = true;
            $hasPassword = !empty($data[0]['temp_password']); // adjust column name if different
        }
    }

    curl_close($ch);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account - Texol Energies</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">

<style>
body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
.card { border-radius:16px; max-width:450px; width:100%; padding:30px; box-shadow:0 20px 60px rgba(0,0,0,0.3); background:white; }
.logo { text-align:center; margin-bottom:20px; }
.logo img { height:60px; }
.password-strength { height:4px; background:#e9ecef; border-radius:2px; margin-top:8px; overflow:hidden; }
.password-strength-bar { height:100%; width:0; transition:all 0.3s ease; }
.strength-weak { width:33%; background:#dc3545; }
.strength-medium { width:66%; background:#ffc107; }
.strength-strong { width:100%; background:#198754; }
.requirements { font-size:0.875rem; margin-top:8px; }
.requirements .requirement { display:flex; align-items:center; gap:6px; margin-bottom:4px; }
.requirements .requirement.met { color:#198754; }
.requirements i { font-size:0.75rem; }
#success { display:none; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <img src="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png" alt="Texol Energies">
    </div>

    <?php if (!$emailValid): ?>
        <div class="alert alert-danger">Invalid email link</div>
    <?php elseif ($errorMsg): ?>
        <div class="alert alert-danger">Error: <?php echo htmlspecialchars($errorMsg); ?></div>
    <?php elseif ($userExists && $hasPassword): ?>
        <div class="text-center">
            <i class="bi bi-exclamation-circle-fill text-warning" style="font-size:3rem;"></i>
            <h4 class="mt-2">Account Already Exists</h4>
            <p class="text-muted">This account already has a password set. Please <a href="login">login</a> instead.</p>
        </div>
    <?php else: ?>
        <h4 class="mb-3 text-center">Create Your Account</h4>
        <p class="text-center"><?php echo htmlspecialchars($email); ?></p>

        <div id="alertContainer"></div>

        <form id="form">
            <input type="hidden" id="email" value="<?php echo htmlspecialchars($email); ?>">

            <div class="mb-3">
                <label>Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="password" class="form-control" placeholder="Password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="password-strength">
                    <div id="strengthBar" class="password-strength-bar"></div>
                </div>
                <div class="requirements">
                    <div class="requirement" id="reqLength"><i class="bi bi-circle"></i>At least 8 characters</div>
                    <div class="requirement" id="reqUpper"><i class="bi bi-circle"></i>One uppercase letter</div>
                    <div class="requirement" id="reqLower"><i class="bi bi-circle"></i>One lowercase letter</div>
                    <div class="requirement" id="reqNumber"><i class="bi bi-circle"></i>One number</div>
                    <div class="requirement" id="reqSpecial"><i class="bi bi-circle"></i>One special character (!@#$%^&*)</div>
                </div>
            </div>

            <div class="mb-3">
                <label>Confirm Password</label>
                <input type="password" id="confirm" class="form-control" placeholder="Confirm Password" required>
                <div id="matchMessage" class="form-text text-danger mt-1" style="display:none;">
                    <i class="bi bi-x-circle me-1"></i>Passwords do not match
                </div>
            </div>

            <button class="btn btn-primary w-100" id="btn"><i class="bi bi-check-circle me-2"></i>Create Account</button>
        </form>

        <div id="success" class="text-center mt-3">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
            <h4 class="text-success mt-2">Account Created!</h4>
            <p class="text-muted">Your password has been set successfully.</p>
            <a href="index.php" class="btn btn-outline-primary mt-2"><i class="bi bi-box-arrow-in-right me-2"></i>Go to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SUPABASE_URL = 'https://pjwvfuyzbzayxqxisysi.supabase.co';
const SUPABASE_KEY = 'sb_publishable_0irc-UsepAkekLihnXz8Mw_ouNmhqld';

const form = document.getElementById('form');
const btn = document.getElementById('btn');
const password = document.getElementById('password');
const confirm = document.getElementById('confirm');
const alertBox = document.getElementById('alertContainer');
const togglePassword = document.getElementById('togglePassword');
const strengthBar = document.getElementById('strengthBar');

function showAlert(msg) { alertBox.innerHTML = `<div class="alert alert-danger">${msg}</div>`; }

togglePassword.addEventListener('click', ()=>{
    const type = password.type === 'password' ? 'text' : 'password';
    password.type = type;
    togglePassword.innerHTML = type==='password'?'<i class="bi bi-eye"></i>':'<i class="bi bi-eye-slash"></i>';
});

function checkStrength(pw){
    const req = {length:pw.length>=8, upper:/[A-Z]/.test(pw), lower:/[a-z]/.test(pw), number:/[0-9]/.test(pw), special:/[!@#$%^&*(),.?":{}|<>]/.test(pw)};
    for(const k in req){
        const el = document.getElementById('req'+k.charAt(0).toUpperCase()+k.slice(1));
        if(req[k]){ el.classList.add('met'); el.querySelector('i').className='bi bi-check-circle-fill'; }
        else{ el.classList.remove('met'); el.querySelector('i').className='bi bi-circle'; }
    }
    const metCount = Object.values(req).filter(Boolean).length;
    strengthBar.className='password-strength-bar';
    if(metCount<=2) strengthBar.classList.add('strength-weak');
    else if(metCount<=4) strengthBar.classList.add('strength-medium');
    else strengthBar.classList.add('strength-strong');
    return metCount===5;
}

function checkMatch(){
    const match = password.value===confirm.value && confirm.value!=='';
    document.getElementById('matchMessage').style.display = match?'none':'block';
    return match;
}

password.addEventListener('input',()=>{ checkStrength(password.value); if(confirm.value) checkMatch(); });
confirm.addEventListener('input',checkMatch);

form?.addEventListener('submit', async e=>{
    e.preventDefault(); alertBox.innerHTML='';
    if(!checkStrength(password.value)){ showAlert('Password does not meet requirements'); return; }
    if(!checkMatch()){ showAlert('Passwords do not match'); return; }

    btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split me-2"></i>Creating Account...';

    try{
        const response = await fetch(`${SUPABASE_URL}/rest/v1/users?email=eq.${encodeURIComponent(document.getElementById('email').value)}`,{
            method:'PATCH',
            headers:{'apikey':SUPABASE_KEY,'Authorization':`Bearer ${SUPABASE_KEY}`,'Content-Type':'application/json','Prefer':'return=representation'},
            body: JSON.stringify({ temp_password: password.value, updated_at: new Date().toISOString() })
        });
        const text = await response.text();
        if(!response.ok) throw new Error(text||'Failed');
        if(text==='[]') throw new Error('No user found with this email');

        form.style.display='none'; document.getElementById('success').style.display='block';
    }catch(err){ console.error(err); showAlert(err.message); btn.disabled=false; btn.innerHTML='<i class="bi bi-check-circle me-2"></i>Create Account'; }
});
</script>
</body>
</html>