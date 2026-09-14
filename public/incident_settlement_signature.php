<?php
session_start();
require_once __DIR__ . '/../config.php';

$settlementId = $_GET['id'] ?? '';

if (empty($settlementId)) {
    die('Invalid settlement ID.');
}

// Fetch settlement details
$settlement = null;
if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
    $supabaseUrl = rtrim(SUPABASE_URL, '/');
    $supabaseKey = SUPABASE_ANON_KEY;

    $query = http_build_query([
        'select' => '*',
        'id' => 'eq.' . urlencode($settlementId)
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $supabaseUrl . '/rest/v1/incident_settlements?' . $query,
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
    
    // Debug output (remove in production)
    // error_log("Query: " . $query);
    // error_log("Response: " . $response);
    // error_log("HTTP Code: " . $httpCode);
    
    $settlementData = json_decode($response, true);
    $settlement = is_array($settlementData) && !empty($settlementData[0]) ? $settlementData[0] : null;
}

if (!$settlement) {
    die('Settlement not found. Please check the settlement ID and try again.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file uploads for signatures
    function uploadSignature($file) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
            return null;
        }
        
        // Simple approach: convert to base64 without compression
        $imageData = file_get_contents($file['tmp_name']);
        $mimeType = mime_content_type($file['tmp_name']);
        
        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }
    
    $signatureData = [
        'settlement_id' => $settlementId,
        'client_name_handwriting' => $_POST['client_name_handwriting'] ?? '',
        'client_signature' => uploadSignature($_FILES['client_signature'] ?? null),
        'client_date' => $_POST['client_date'] ?? '',
        'witness_name' => $_POST['witness_name'] ?? '',
        'witness_signature' => uploadSignature($_FILES['witness_signature'] ?? null),
        'witness_date' => $_POST['witness_date'] ?? '',
        'rep_name_handwriting' => $_POST['rep_name_handwriting'] ?? '',
        'rep_signature' => uploadSignature($_FILES['rep_signature'] ?? null),
        'rep_designation' => $_POST['rep_designation'] ?? '',
        'rep_date' => $_POST['rep_date'] ?? '',
        'rep_witness_name' => $_POST['rep_witness_name'] ?? '',
        'rep_witness_signature' => uploadSignature($_FILES['rep_witness_signature'] ?? null),
        'rep_witness_date' => $_POST['rep_witness_date'] ?? '',
    ];

    if (defined('SUPABASE_URL') && defined('SUPABASE_ANON_KEY')) {
        $supabaseUrl = rtrim(SUPABASE_URL, '/');
        $supabaseKey = SUPABASE_ANON_KEY;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $supabaseUrl . '/rest/v1/incident_settlement_signatures',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($signatureData),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $successMessage = 'Signature information saved successfully!';
        } else {
            $errorMessage = 'Failed to save signature information.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settlement Signature - Texol Energies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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

        .signature-card {
            width: 100%;
            max-width: 600px;
            padding: 32px 28px 30px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(148, 163, 184, 0.12);
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

        .signature-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #111827;
            text-align: center;
        }

        .signature-description {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 18px;
            text-align: center;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin: 20px 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }

        .signature-field {
            margin-bottom: 16px;
        }

        .signature-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
        }

        .signature-field input,
        .signature-field textarea {
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

        .signature-field input:focus,
        .signature-field textarea:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.3);
            background-color: #ffffff;
        }

        .signature-field textarea {
            min-height: 60px;
            resize: vertical;
        }

        .camera-btn {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #374151;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .camera-btn:hover {
            background: #e5e7eb;
        }

        .camera-btn i {
            margin-right: 6px;
        }

        .signature-preview {
            margin-top: 8px;
            max-width: 100%;
            max-height: 60px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            object-fit: contain;
        }

        .submit-btn {
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

        .submit-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
            box-shadow: 0 16px 32px rgba(79, 70, 229, 0.5);
        }

        .submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.4);
        }

        .success-message {
            margin-bottom: 12px;
            padding: 8px 10px;
            border-radius: 8px;
            background-color: #ecfdf5;
            color: #047857;
            font-size: 13px;
            border: 1px solid #a7f3d0;
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
            .signature-card {
                margin: 12px;
                padding: 26px 20px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="signature-card">
        <div class="brand">
            <img src="https://www.texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies" class="brand-logo">
        </div>

        <h1 class="signature-title">Settlement Signature</h1>
        <p class="signature-description">
            <?php echo htmlspecialchars($settlement['client_name'] ?? ''); ?> - <?php echo htmlspecialchars($settlement['agreement_date'] ?? ''); ?>
        </p>

        <?php if (isset($successMessage)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form id="signatureForm" method="POST" enctype="multipart/form-data">
            <!-- Client Section -->
            <div class="section-title">Client</div>
            
            <div class="signature-field">
                <label for="client_name_handwriting">Name in own handwriting</label>
                <input type="text" id="client_name_handwriting" name="client_name_handwriting" required>
            </div>
            
            <div class="signature-field">
                <label for="client_signature">Signature</label>
                <textarea id="client_signature" name="client_signature" placeholder="Type your signature"></textarea>
                <button type="button" class="camera-btn" onclick="captureSignature('client_signature')">
                    <i class="bi bi-camera"></i> Capture Signature
                </button>
                <img id="client_signature_preview" class="signature-preview d-none">
            </div>
            
            <div class="signature-field">
                <label for="client_date">Date</label>
                <input type="date" id="client_date" name="client_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <!-- Witness Section -->
            <div class="section-title">In the presence of</div>
            
            <div class="signature-field">
                <label for="witness_name">Name in own handwriting</label>
                <input type="text" id="witness_name" name="witness_name">
            </div>
            
            <div class="signature-field">
                <label for="witness_signature">Signature</label>
                <textarea id="witness_signature" name="witness_signature" placeholder="Type your signature"></textarea>
                <button type="button" class="camera-btn" onclick="captureSignature('witness_signature')">
                    <i class="bi bi-camera"></i> Capture Signature
                </button>
                <img id="witness_signature_preview" class="signature-preview d-none">
            </div>
            
            <div class="signature-field">
                <label for="witness_date">Date</label>
                <input type="date" id="witness_date" name="witness_date">
            </div>

            <!-- Company Representative Section -->
            <div class="section-title">Authorized Company Representative</div>
            
            <div class="signature-field">
                <label for="rep_name_handwriting">Name in Own Handwriting</label>
                <input type="text" id="rep_name_handwriting" name="rep_name_handwriting" value="<?php echo htmlspecialchars($settlement['reporter_name'] ?? ''); ?>" required>
            </div>
            
            <div class="signature-field">
                <label for="rep_signature">Signature</label>
                <textarea id="rep_signature" name="rep_signature" placeholder="Type your signature"></textarea>
                <button type="button" class="camera-btn" onclick="captureSignature('rep_signature')">
                    <i class="bi bi-camera"></i> Capture Signature
                </button>
                <img id="rep_signature_preview" class="signature-preview d-none">
            </div>
            
            <div class="signature-field">
                <label for="rep_designation">Designation</label>
                <input type="text" id="rep_designation" name="rep_designation" value="<?php echo htmlspecialchars($settlement['reporter_name'] ?? ''); ?>" required>
            </div>
            
            <div class="signature-field">
                <label for="rep_date">Date</label>
                <input type="date" id="rep_date" name="rep_date" value="<?php echo htmlspecialchars($settlement['agreement_date'] ?? ''); ?>" required>
            </div>

            <!-- Company Witness Section -->
            <div class="section-title">In the presence of</div>
            
            <div class="signature-field">
                <label for="rep_witness_name">Name in own handwriting</label>
                <input type="text" id="rep_witness_name" name="rep_witness_name">
            </div>
            
            <div class="signature-field">
                <label for="rep_witness_signature">Signature</label>
                <textarea id="rep_witness_signature" name="rep_witness_signature" placeholder="Type your signature"></textarea>
                <button type="button" class="camera-btn" onclick="captureSignature('rep_witness_signature')">
                    <i class="bi bi-camera"></i> Capture Signature
                </button>
                <img id="rep_witness_signature_preview" class="signature-preview d-none">
            </div>
            
            <div class="signature-field">
                <label for="rep_witness_date">Date</label>
                <input type="date" id="rep_witness_date" name="rep_witness_date">
            </div>

            <div class="submit-btn">
                <button type="submit">Save Signatures</button>
            </div>
        </form>
    </div>

    <script>
        function captureSignature(fieldId) {
            const input = document.getElementById(fieldId);
            const preview = document.getElementById(fieldId + '_preview');
            
            // Check if using HTTPS (required for camera access)
            if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                alert('Camera access requires HTTPS. Please upload an image instead or ensure this site is served over HTTPS.');
                return;
            }
            
            // Check if device has camera support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera not supported on this device. Please upload an image or type your signature in the text field.');
                return;
            }

            // Request camera permissions
            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                } 
            })
            .then(function(stream) {
                // Create modal for capturing
                const modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:9999;flex-direction:column;';
                
                // Create container for video and overlay
                const container = document.createElement('div');
                container.style.cssText = 'position:relative;max-width:90%;max-height:70%;border-radius:10px;overflow:hidden;';
                
                // Create video element
                const video = document.createElement('video');
                video.srcObject = stream;
                video.autoplay = true;
                video.muted = true;
                video.playsInline = true;
                video.style.cssText = 'width:100%;height:100%;object-fit:contain;';
                
                // Create signature guide overlay
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80%;height:60px;border:3px dashed #4f46e5;border-radius:8px;background:rgba(79,70,229,0.1);pointer-events:none;';
                
                // Add signature guide text
                const guideText = document.createElement('div');
                guideText.textContent = 'Place signature here';
                guideText.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#4f46e5;font-weight:bold;font-size:16px;text-shadow:0 0 10px rgba(255,255,255,0.8);';
                overlay.appendChild(guideText);
                
                // Create canvas for capturing (horizontal signature format)
                const canvas = document.createElement('canvas');
                canvas.style.cssText = 'display:none;';
                const ctx = canvas.getContext('2d');
                
                // Set canvas to horizontal signature format (400x60)
                canvas.width = 400;
                canvas.height = 60;
                
                // Add elements to container
                container.appendChild(video);
                container.appendChild(overlay);
                modal.appendChild(container);
                
                // Wait for video to be ready
                video.onloadedmetadata = function() {
                    video.play();
                };
                
                const captureBtn = document.createElement('button');
                captureBtn.textContent = 'Capture Signature';
                captureBtn.style.cssText = 'margin:20px;padding:12px 24px;border-radius:8px;border:none;background:#4f46e5;color:white;font-size:16px;cursor:pointer;';
                
                const cancelBtn = document.createElement('button');
                cancelBtn.textContent = 'Cancel';
                cancelBtn.style.cssText = 'margin:20px;padding:12px 24px;border-radius:8px;border:none;background:#6b7280;color:white;font-size:16px;cursor:pointer;';
                
                captureBtn.onclick = async function() {
                    // Create temporary canvas to capture full video frame
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = video.videoWidth;
                    tempCanvas.height = video.videoHeight;
                    const tempCtx = tempCanvas.getContext('2d');
                    tempCtx.drawImage(video, 0, 0);
                    
                    // Calculate the signature area from the center of the video
                    const signatureWidth = 400;
                    const signatureHeight = 60;
                    const centerX = tempCanvas.width / 2;
                    const centerY = tempCanvas.height / 2;
                    
                    // Extract the signature area from the center
                    ctx.fillStyle = 'white';
                    ctx.fillRect(0, 0, signatureWidth, signatureHeight);
                    ctx.drawImage(
                        tempCanvas,
                        centerX - signatureWidth / 2,  // Source X
                        centerY - signatureHeight / 2, // Source Y
                        signatureWidth,               // Source width
                        signatureHeight,              // Source height
                        0,                            // Destination X
                        0,                            // Destination Y
                        signatureWidth,               // Destination width
                        signatureHeight               // Destination height
                    );
                    
                    // Convert canvas to blob for whitening
                    canvas.toBlob(async function(blob) {
                        try {
                            // Whiten the signature background
                            const whitenedBlob = await whitenSignatureBackground(blob);
                            
                            // Convert whitened blob to data URL for preview
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                saveCapturedSignature(fieldId, e.target.result);
                            };
                            reader.readAsDataURL(whitenedBlob);
                        } catch (error) {
                            console.error('Signature whitening error:', error);
                            // Continue with original image if whitening fails
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                saveCapturedSignature(fieldId, e.target.result);
                            };
                            reader.readAsDataURL(blob);
                        }
                    }, 'image/jpeg', 0.7);
                    
                    // Stop video stream and cleanup
                    stream.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                    document.body.removeChild(modal);
                };
                
                cancelBtn.onclick = function() {
                    // Stop video stream and cleanup
                    stream.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                    document.body.removeChild(modal);
                };
                
                const buttons = document.createElement('div');
                buttons.style.cssText = 'display:flex;gap:10px;justify-content:center;';
                buttons.appendChild(captureBtn);
                buttons.appendChild(cancelBtn);
                
                modal.appendChild(buttons);
                document.body.appendChild(modal);
            })
            .catch(function(err) {
                console.error('Camera error:', err);
                
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    alert('Camera permission denied. Please allow camera access in your browser settings, or upload an image instead.');
                } else if (err.name === 'NotFoundError') {
                    alert('No camera found on this device. Please upload an image or type your signature manually.');
                } else if (err.name === 'NotReadableError') {
                    alert('Camera is being used by another application. Please upload an image instead.');
                } else if (err.name === 'SecurityError') {
                    alert('Camera access blocked by browser security. Please upload an image instead.');
                } else {
                    alert('Camera access failed: ' + err.message + '. Please upload an image or type your signature manually.');
                }
            });
        }

        // Handle camera capture by converting to file input
        function saveCapturedSignature(fieldId, imageData) {
            const fileInput = document.getElementById(fieldId + '_file');
            const preview = document.getElementById(fieldId + '_preview');
            
            // Convert base64 to blob if needed
            if (typeof imageData === 'string') {
                fetch(imageData)
                    .then(res => res.blob())
                    .then(blob => {
                        const file = new File([blob], 'signature.jpg', { type: 'image/jpeg' });
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fileInput.files = dataTransfer.files;
                        
                        preview.src = imageData;
                        preview.classList.remove('d-none');
                    });
            } else {
                // imageData is already a blob
                const file = new File([imageData], 'signature.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                };
                reader.readAsDataURL(imageData);
            }
        }

        // Whiten signature background function (same as profile.php)
        function whitenSignatureBackground(imageFile, threshold = 200) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                const reader = new FileReader();

                reader.onload = e => { img.src = e.target.result; };
                reader.onerror = reject;
                img.onload = () => {
                    const w = img.width, h = img.height;

                    // 1. Full-res original
                    const canvas = document.createElement('canvas');
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    const original = ctx.getImageData(0, 0, w, h).data;

                    // 2. Cheap blur estimate of local lighting (downscale -> upscale)
                    const small = document.createElement('canvas');
                    const smallW = Math.max(1, Math.round(w / 24));
                    const smallH = Math.max(1, Math.round(h / 24));
                    small.width = smallW;
                    small.height = smallH;
                    small.getContext('2d').drawImage(img, 0, 0, smallW, smallH);

                    const blurCanvas = document.createElement('canvas');
                    blurCanvas.width = w;
                    blurCanvas.height = h;
                    const blurCtx = blurCanvas.getContext('2d');
                    blurCtx.imageSmoothingEnabled = true;
                    blurCtx.drawImage(small, 0, 0, w, h);
                    const blurred = blurCtx.getImageData(0, 0, w, h).data;

                    // 3. Normalize lighting, then force background to pure white
                    const output = ctx.createImageData(w, h);
                    const outData = output.data;

                    for (let i = 0; i < original.length; i += 4) {
                        const origGray = (original[i] + original[i + 1] + original[i + 2]) / 3;
                        const bgGray = (blurred[i] + blurred[i + 1] + blurred[i + 2]) / 3 || 1;
                        const corrected = Math.min(255, (origGray / bgGray) * 255);

                        if (corrected >= threshold) {
                            // background -> pure white
                            outData[i] = 255;
                            outData[i + 1] = 255;
                            outData[i + 2] = 255;
                        } else {
                            // ink -> keep original color (usually dark blue/black)
                            outData[i] = original[i];
                            outData[i + 1] = original[i + 1];
                            outData[i + 2] = original[i + 2];
                        }
                        outData[i + 3] = 255; // fully opaque, no transparency needed
                    }

                    ctx.putImageData(output, 0, 0);
                    canvas.toBlob(blob => resolve(blob), 'image/jpeg', 0.92); // JPEG fine now — no transparency to preserve
                };
                img.onerror = reject;
                reader.readAsDataURL(imageFile);
            });
        }

        // Also add file upload fallback
        function addFileUploadFallback(fieldId) {
            const input = document.getElementById(fieldId);
            const parent = input.parentElement;
            
            // Create hidden file input
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.name = fieldId;
            fileInput.accept = 'image/*';
            fileInput.id = fieldId + '_file';
            fileInput.style.cssText = 'display:none;';
            
            // Create visible upload button
            const uploadBtn = document.createElement('button');
            uploadBtn.type = 'button';
            uploadBtn.textContent = 'Upload Image';
            uploadBtn.style.cssText = 'margin-top:8px;padding:8px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;color:#374151;font-size:13px;cursor:pointer;';
            uploadBtn.onclick = function() {
                fileInput.click();
            };
            
            fileInput.onchange = async function(e) {
                const file = e.target.files[0];
                if (file) {
                    try {
                        // Whiten the signature background using the same method as profile.php
                        const whitenedBlob = await whitenSignatureBackground(file);
                        
                        // Create new file with whitened image
                        const whitenedFile = new File([whitenedBlob], 'signature.jpg', { type: 'image/jpeg' });
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(whitenedFile);
                        fileInput.files = dataTransfer.files;
                        
                        // Show preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            input.value = e.target.result;
                            const preview = document.getElementById(fieldId + '_preview');
                            preview.src = e.target.result;
                            preview.classList.remove('d-none');
                        };
                        reader.readAsDataURL(whitenedBlob);
                    } catch (error) {
                        console.error('Signature whitening error:', error);
                        // Continue with original image if whitening fails
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            input.value = e.target.result;
                            const preview = document.getElementById(fieldId + '_preview');
                            preview.src = e.target.result;
                            preview.classList.remove('d-none');
                        };
                        reader.readAsDataURL(file);
                    }
                }
            };
            
            parent.appendChild(fileInput);
            parent.appendChild(uploadBtn);
        }

        // Add file upload fallbacks to all signature fields
        document.addEventListener('DOMContentLoaded', function() {
            ['client_signature', 'witness_signature', 'rep_signature', 'rep_witness_signature'].forEach(function(fieldId) {
                addFileUploadFallback(fieldId);
            });
        });

        document.getElementById('signatureForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Form is submitted via POST to the same page
            this.submit();
        });
    </script>
</body>
</html>