<?php
function render_repair_maintenance_email(array $form): array
{
    $template = require __DIR__ . '/created.php';
    $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $formId = $form['id'] ?? '';
    $formDate = $form['form_date'] ?? 'Not specified';
    $stationName = $form['station_name'] ?? 'Not specified';
    $reporterName = $form['reporter_name'] ?? $form['reporter_email'] ?? 'Unknown';
    $department = $form['department'] ?? 'Not specified';
    $status = $form['status'] ?? 'pending';
    $repairType = trim(strip_tags((string) ($form['repair_type'] ?? '')));
    $remarks = trim(strip_tags((string) ($form['remarks'] ?? '')));
    $requirements = trim(strip_tags((string) ($form['requirements'] ?? '')));
    $formUrl = 'https://support.texolenergies.com/repair_maintenance';
    $heading = $template['heading'];

    ob_start();
    require __DIR__ . '/layout.php';
    $body = ob_get_clean();

    return [
        'subject' => $template['subject_prefix'] . ': ' . preg_replace('/[\r\n]+/', ' ', (string) $stationName) . ' - ' . $formDate,
        'body' => $body,
    ];
}
