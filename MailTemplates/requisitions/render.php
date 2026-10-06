<?php
function render_requisition_email(string $action, array $requisition, array $details = []): array
{
    $templateFiles = [
        'created' => __DIR__ . '/created.php',
        'updated' => __DIR__ . '/updated.php',
        'approved' => __DIR__ . '/approved.php',
        'rejected' => __DIR__ . '/rejected.php',
    ];
    if (!isset($templateFiles[$action])) {
        throw new InvalidArgumentException('Unsupported requisition email event.');
    }

    $template = require $templateFiles[$action];
    $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $requisitionId = $requisition['id'] ?? '';
    $requisitionNumber = $requisition['requisition_number'] ?? $requisitionId;
    $requesterName = $requisition['requester_name'] ?? $requisition['requester_email'] ?? 'Unknown';
    $department = $requisition['department'] ?? 'Not specified';
    $requiredDate = $requisition['required_date'] ?? 'Not specified';
    $description = trim(strip_tags((string) ($requisition['description'] ?? '')));
    $status = $requisition['status'] ?? 'pending';
    $requisitionUrl = 'https://support.texolenergies.com/requisition?id=' . rawurlencode((string) $requisitionId);

    $items = $requisition['items'] ?? [];
    $itemsHtml = '<table role="presentation" style="width:100%;border-collapse:collapse;margin-top:12px;">'
        . '<thead><tr style="background:#f7f9fc;">'
        . '<th style="padding:10px;text-align:left;border-bottom:2px solid #1f3c88;font-size:12px;">Item</th>'
        . '<th style="padding:10px;text-align:center;border-bottom:2px solid #1f3c88;font-size:12px;">Quantity</th>'
        . '<th style="padding:10px;text-align:center;border-bottom:2px solid #1f3c88;font-size:12px;">Unit</th>'
        . '<th style="padding:10px;text-align:right;border-bottom:2px solid #1f3c88;font-size:12px;">Unit Price</th>'
        . '</tr></thead><tbody>';
    if (is_array($items) && $items) {
        foreach ($items as $row) {
            $item = $row['item'] ?? [];
            $itemsHtml .= '<tr style="border-bottom:1px solid #e9ecef;">'
                . '<td style="padding:10px;font-size:13px;color:#555;">' . $escape($item['name'] ?? 'Unknown') . '</td>'
                . '<td style="padding:10px;text-align:center;font-size:13px;color:#555;">' . $escape($row['quantity'] ?? 0) . '</td>'
                . '<td style="padding:10px;text-align:center;font-size:13px;color:#555;">' . $escape($row['unit'] ?? $item['unit'] ?? '') . '</td>'
                . '<td style="padding:10px;text-align:right;font-size:13px;color:#555;">' . $escape(number_format((float) ($row['unit_price'] ?? 0), 2)) . '</td>'
                . '</tr>';
        }
    } else {
        $itemsHtml .= '<tr><td colspan="4" style="padding:10px;font-size:13px;color:#777;font-style:italic;">No items in this requisition</td></tr>';
    }
    $itemsHtml .= '</tbody></table>';

    $eventDetailsHtml = '';
    if (in_array($action, ['approved', 'rejected'], true)) {
        $eventDetailsHtml = '<div style="padding:15px;background:#e9f7ef;border-radius:8px;margin-bottom:20px;">'
            . '<strong style="color:#1e7e34;">' . $escape($template['heading']) . '</strong>'
            . '<p style="margin:8px 0 0;font-size:14px;color:#555;">Updated by: ' . $escape($details['added_by'] ?? 'Unknown') . '</p></div>';
    } elseif ($action === 'updated' && !empty($details['added_by'])) {
        $eventDetailsHtml = '<p style="margin:0 0 18px;font-size:13px;color:#66717e;">Updated by: ' . $escape($details['added_by']) . '</p>';
    }

    $heading = $template['heading'];
    ob_start();
    require __DIR__ . '/layout.php';
    $body = ob_get_clean();

    return [
        'event_key' => $template['event_key'],
        'subject' => $template['subject_prefix'] . ': ' . preg_replace('/[\r\n]+/', ' ', (string) $requisitionNumber),
        'body' => $body,
    ];
}
