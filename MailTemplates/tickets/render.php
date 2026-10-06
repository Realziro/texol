<?php
function render_ticket_email(string $action, array $ticket, array $details = []): array
{
    $templateFiles = [
        'created' => __DIR__ . '/created.php',
        'updated' => __DIR__ . '/updated.php',
        'assigned' => __DIR__ . '/assigned.php',
        'note' => __DIR__ . '/note.php',
        'closed' => __DIR__ . '/closed.php',
    ];

    if (!isset($templateFiles[$action])) {
        throw new InvalidArgumentException('Unsupported ticket email event.');
    }

    $template = require $templateFiles[$action];
    $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $ticketId = $ticket['ticket_id'] ?? $ticket['id'] ?? '';
    $title = $ticket['title'] ?? 'Support ticket';
    $description = trim(strip_tags((string) ($ticket['description'] ?? '')));
    $requester = $ticket['requester_name'] ?? $ticket['requester_email'] ?? 'Unknown';
    $department = $ticket['department'] ?? 'Not specified';
    $status = $ticket['status'] ?? 'Not specified';
    $priority = $ticket['priority'] ?? 'Not specified';
    $branch = $ticket['branch'] ?? 'Not specified';
    $category = $ticket['category'] ?? 'Not specified';
    $ticketUrl = 'https://support.texolenergies.com/tickets?ticket_id=' . rawurlencode((string) $ticketId);
    $eventDetailsHtml = '';

    if ($action === 'note' && !empty($details['note'])) {
        $eventDetailsHtml = '<div style="padding:14px;background:#f7f9fc;border-left:3px solid #1f3c88;margin:18px 0;">'
            . '<strong>New note</strong><p style="margin:8px 0 0;">' . nl2br($escape($details['note'])) . '</p>'
            . '<p style="margin:8px 0 0;color:#66717e;font-size:12px;">Added by: ' . $escape($details['added_by'] ?? 'Unknown') . '</p></div>';
    } elseif ($action === 'assigned' && !empty($details['assignees'])) {
        $assignees = array_map($escape, $details['assignees']);
        $eventDetailsHtml = '<div style="padding:14px;background:#f7f9fc;margin:18px 0;"><strong>Assigned to:</strong> '
            . implode(', ', $assignees) . '</div>';
    } elseif ($action === 'updated' && !empty($details['updated_by'])) {
        $eventDetailsHtml = '<p style="margin:18px 0;color:#66717e;font-size:13px;">Updated by: '
            . $escape($details['updated_by']) . '</p>';
    } elseif ($action === 'closed' && !empty($details['closed_by'])) {
        $eventDetailsHtml = '<p style="margin:18px 0;color:#66717e;font-size:13px;">Closed by: '
            . $escape($details['closed_by']) . '</p>';
    }

    $heading = $template['heading'];
    ob_start();
    require __DIR__ . '/layout.php';
    $body = ob_get_clean();

    return [
        'event_key' => $template['event_key'],
        'subject' => $template['subject_prefix'] . ': ' . preg_replace('/[\r\n]+/', ' ', (string) $title),
        'body' => $body,
    ];
}
