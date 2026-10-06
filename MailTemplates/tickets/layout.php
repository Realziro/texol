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
        <div style="padding:24px;background:#1f3c88;color:#fff;text-align:center;">
            <h1 style="margin:0;font-size:21px;"><?php echo $escape($heading); ?></h1>
        </div>
        <div style="padding:24px;">
            <p style="margin:0 0 10px;font-size:14px;color:#555;line-height:1.6;"><strong>Ticket ID:</strong> <?php echo $escape($ticketId); ?></p>
            <h2 style="margin:0 0 10px;font-size:18px;color:#333;"><?php echo $escape($title); ?></h2>
            <p style="margin:0 0 10px;font-size:14px;color:#555;line-height:1.6;"><strong>Description:</strong><br><?php echo nl2br($escape($description)); ?></p>
            <p style="margin:0 0 20px;font-size:14px;color:#555;line-height:1.6;"><strong>Requested By:</strong> <?php echo $escape($requester); ?></p>
            <div style="margin-bottom:20px;">
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#e8f0ff;color:#1f3c88;margin:3px;">Status: <?php echo $escape($status); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#fff4e5;color:#b26a00;margin:3px;">Priority: <?php echo $escape($priority); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#f0f0f0;color:#555;margin:3px;">Department: <?php echo $escape($department); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#f0f0f0;color:#555;margin:3px;">Branch: <?php echo $escape($branch); ?></span>
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#f0f0f0;color:#555;margin:3px;">Category: <?php echo $escape($category); ?></span>
            </div>
            <?php echo $eventDetailsHtml; ?>
            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;"><a href="<?php echo $escape($ticketUrl); ?>" style="color:#1f3c88;font-weight:bold;text-decoration:none;">View Ticket</a></p>
            <div style="margin-top:25px;text-align:center;">
                <span style="display:inline-block;padding:6px 12px;border-radius:20px;font-size:12px;background:#1f3c88;color:#fff;margin:3px;">Ticket Notification</span>
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
