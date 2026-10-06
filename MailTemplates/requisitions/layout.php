<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $escape($heading); ?></title>
</head>
<body style="margin:0;padding:20px;background:#f4f6f9;color:#27313d;font-family:Arial,sans-serif;">
    <div style="max-width:650px;margin:0 auto 10px;text-align:center;">
        <img src="https://texolenergies.com/assets/Logo-paGHQfRF.svg" alt="Texol Energies" style="display:block;width:140px;height:auto;margin:0 auto;">
    </div>
    <div style="max-width:650px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
        <div style="padding:25px;background:#1f3c88;color:#fff;text-align:center;">
            <h1 style="margin:0;font-size:20px;"><?php echo $escape($heading); ?></h1>
        </div>
        <div style="padding:25px;">
            <p style="margin:0 0 10px;font-size:14px;color:#555;"><strong>Requisition Number:</strong> <?php echo $escape($requisitionNumber); ?></p>
            <p style="margin:0 0 10px;font-size:14px;color:#555;"><strong>Requested By:</strong> <?php echo $escape($requesterName); ?></p>
            <p style="margin:0 0 10px;font-size:14px;color:#555;"><strong>Required Date:</strong> <?php echo $escape($requiredDate); ?></p>
            <div style="margin:0 0 18px;">
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#e8f0ff;color:#1f3c88;margin:3px;">Status: <?php echo $escape(ucfirst($status)); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#f0f0f0;color:#555;margin:3px;">Department: <?php echo $escape($department); ?></span>
            </div>
            <div style="background:#f7f9fc;padding:15px;border-radius:8px;margin-bottom:20px;">
                <strong style="color:#1f3c88;font-size:16px;">Description</strong>
                <p style="margin:10px 0 0;font-size:14px;color:#555;line-height:1.6;"><?php echo nl2br($escape($description)); ?></p>
            </div>
            <div style="margin-bottom:20px;">
                <strong style="color:#1f3c88;font-size:16px;">Items</strong>
                <?php echo $itemsHtml; ?>
            </div>
            <?php echo $eventDetailsHtml; ?>
            <p style="margin:0 0 20px;font-size:14px;"><a href="<?php echo $escape($requisitionUrl); ?>" style="color:#1f3c88;font-weight:bold;text-decoration:none;">View Requisition</a></p>
            <div style="margin-top:25px;text-align:center;">
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#1f3c88;color:#fff;margin:3px;">Requisition Notification</span>
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
