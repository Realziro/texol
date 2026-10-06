<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $escape($heading); ?></title>
</head>
<body style="margin:0;padding:20px;background:#f4f6f9;color:#27313d;font-family:Arial,sans-serif;">
    <div style="max-width:720px;margin:0 auto 10px;text-align:center;">
        <img src="https://texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies" style="display:block;width:140px;height:auto;margin:0 auto;">
    </div>
    <div style="max-width:720px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
        <div style="padding:25px;background:#1f3c88;color:#fff;text-align:center;">
            <h1 style="margin:0;font-size:20px;"><?php echo $escape($heading); ?></h1>
        </div>
        <div style="padding:25px;">
            <p style="margin:0 0 10px;font-size:14px;color:#555;"><strong>Checklist Type:</strong> <?php echo $escape($checklistType); ?></p>
            <p style="margin:0 0 10px;font-size:14px;color:#555;"><strong>Checklist Date:</strong> <?php echo $escape($checklistDate); ?></p>
            <p style="margin:0 0 18px;font-size:14px;color:#555;"><strong>Reported By:</strong> <?php echo $escape($reporterName); ?></p>
            <div style="margin:0 0 20px;">
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#e8f0ff;color:#1f3c88;margin:3px;">Stations: <?php echo $escape($stationCount); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#f0f0f0;color:#555;margin:3px;">Cameras: <?php echo $escape($totalCameras); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#e9f7ef;color:#1e7e34;margin:3px;">Functioning: <?php echo $escape($functioningCameras); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#fff4e5;color:#b26a00;margin:3px;">Status: <?php echo $escape(ucfirst($status)); ?></span>
            </div>
            <h2 style="margin:0 0 10px;font-size:16px;color:#1f3c88;">Station Results</h2>
            <?php echo $stationTableHtml; ?>
            <p style="margin:20px 0;font-size:14px;"><a href="<?php echo $escape($checklistUrl); ?>" style="color:#1f3c88;font-weight:bold;text-decoration:none;">View CCTV Checklists</a></p>
            <div style="margin-top:25px;text-align:center;">
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#1f3c88;color:#fff;margin:3px;">CCTV Checklist Notification</span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#e9f7ef;color:#1e7e34;margin:3px;">System Generated</span>
            </div>
        </div>
        <div style="padding:15px;background:#f4f6f9;color:#777;text-align:center;font-size:12px;">
            <p style="margin:0;">Texol Energies - THI Support</p>
            <p style="margin:5px 0 0;">Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
