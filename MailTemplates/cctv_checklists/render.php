<?php
function render_cctv_checklist_email(array $checklist): array
{
    $template = require __DIR__ . '/created.php';
    $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $branchType = strtolower((string) ($checklist['branch_type'] ?? ''));
    $checklistType = $branchType === 'sta' ? 'STA' : 'Texol';
    $checklistDate = $checklist['checklist_date'] ?? 'Not specified';
    $reporterName = $checklist['reporter_name'] ?? $checklist['reporter_email'] ?? 'Unknown';
    $stationCount = (int) ($checklist['station_count'] ?? 0);
    $totalCameras = (int) ($checklist['total_cameras'] ?? 0);
    $functioningCameras = (int) ($checklist['functioning_cameras'] ?? 0);
    $status = $checklist['status'] ?? 'pending';
    $checklistId = (string) ($checklist['id'] ?? '');
    $checklistUrl = 'https://support.texolenergies.com/cctv_checklist' . ($branchType === 'sta' ? '_sta' : '') . '?id=' . rawurlencode($checklistId);

    $stations = $checklist['station_data'] ?? [];
    if (is_string($stations)) {
        $stations = json_decode($stations, true) ?: [];
    }
    $stationTableHtml = '<table role="presentation" style="width:100%;border-collapse:collapse;">'
        . '<thead><tr style="background:#f7f9fc;">'
        . '<th style="padding:9px;text-align:left;border-bottom:2px solid #1f3c88;font-size:12px;">Station</th>'
        . '<th style="padding:9px;text-align:right;border-bottom:2px solid #1f3c88;font-size:12px;">Total</th>'
        . '<th style="padding:9px;text-align:right;border-bottom:2px solid #1f3c88;font-size:12px;">Functioning</th>'
        . '<th style="padding:9px;text-align:left;border-bottom:2px solid #1f3c88;font-size:12px;">Comments</th>'
        . '</tr></thead><tbody>';
    if (is_array($stations) && $stations) {
        foreach ($stations as $station) {
            $stationTableHtml .= '<tr style="border-bottom:1px solid #e9ecef;">'
                . '<td style="padding:9px;font-size:13px;color:#555;">' . $escape($station['branch_name'] ?? 'Unknown') . '</td>'
                . '<td style="padding:9px;text-align:right;font-size:13px;color:#555;">' . $escape($station['total_cameras'] ?? 0) . '</td>'
                . '<td style="padding:9px;text-align:right;font-size:13px;color:#555;">' . $escape($station['functioning_cameras'] ?? 0) . '</td>'
                . '<td style="padding:9px;font-size:13px;color:#555;">' . $escape($station['comments'] ?? '') . '</td>'
                . '</tr>';
        }
    } else {
        $stationTableHtml .= '<tr><td colspan="4" style="padding:10px;font-size:13px;color:#777;">No station results were recorded.</td></tr>';
    }
    $stationTableHtml .= '</tbody></table>';

    $heading = $checklistType . ' ' . $template['heading'];
    ob_start();
    require __DIR__ . '/layout.php';
    $body = ob_get_clean();

    return [
        'event_key' => $template['event_key'],
        'subject' => $template['subject_prefix'] . ' (' . $checklistType . '): ' . $checklistDate,
        'body' => $body,
    ];
}
