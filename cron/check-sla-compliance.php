<?php
/**
 * LUMIRA - SLA Compliance Monitor
 *
 * This script monitors all open tickets and checks SLA compliance
 * Run this every 5 minutes via Windows Task Scheduler
 *
 * Command: php C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\cron\check-sla-compliance.php
 */

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/inc/email.php';

// Log file
$log_file = dirname(__DIR__) . '/logs/sla-monitor.log';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
    echo "[{$timestamp}] {$message}\n";
}

log_message("=== SLA Compliance Monitor Started ===");

try {
    $pdo = get_db();
    $now = new DateTime();

    // Get all open tickets with SLA policies
    $stmt = $pdo->query('
        SELECT t.*, s.first_response_hours, s.resolution_hours, s.escalation_user_id, s.name as sla_name,
               u.email as requester_email, u.full_name as requester_name,
               a.email as assigned_email, a.full_name as assigned_name
        FROM tickets t
        JOIN sla_policies s ON t.sla_policy_id = s.id
        JOIN users u ON t.requester_id = u.id
        LEFT JOIN users a ON t.assigned_to_id = a.id
        WHERE t.status_id NOT IN (
            SELECT id FROM ticket_statuses WHERE name IN (\'Closed\', \'Resolved\')
        )
        AND t.sla_policy_id IS NOT NULL
    ');

    $tickets = $stmt->fetchAll();
    log_message("Checking " . count($tickets) . " tickets with SLA policies");

    $breached_count = 0;
    $at_risk_count = 0;
    $on_track_count = 0;

    foreach ($tickets as $ticket) {
        $ticket_id = $ticket['id'];
        $ticket_number = $ticket['ticket_number'];

        // Check first response SLA
        if (!$ticket['first_responded_at'] && $ticket['first_response_due']) {
            $response_due = new DateTime($ticket['first_response_due']);
            $hours_until_due = hours_between($now, $response_due);

            if ($hours_until_due <= 0) {
                // BREACHED - First Response
                log_message("❌ BREACH: {$ticket_number} - First response overdue by " . abs($hours_until_due) . " hours");

                mark_sla_breached($pdo, $ticket_id, 'first_response', $response_due, $now);
                escalate_ticket($pdo, $ticket_id, $ticket['escalation_user_id']);
                update_ticket_sla_status($pdo, $ticket_id, 'breached');

                // Send alert email
                send_breach_alert($ticket, 'first_response', abs($hours_until_due));

                $breached_count++;

            } elseif ($hours_until_due <= 0.5) {
                // AT RISK - Less than 30 minutes remaining
                log_message("⚠️  AT RISK: {$ticket_number} - First response due in " . round($hours_until_due * 60) . " minutes");

                update_ticket_sla_status($pdo, $ticket_id, 'at_risk');

                // Send warning email (once)
                send_risk_warning($pdo, $ticket, 'first_response', $hours_until_due);

                $at_risk_count++;

            } else {
                // ON TRACK
                update_ticket_sla_status($pdo, $ticket_id, 'on_track');
                $on_track_count++;
            }
        }

        // Check resolution SLA
        if ($ticket['resolution_due']) {
            $resolution_due = new DateTime($ticket['resolution_due']);
            $hours_until_resolution = hours_between($now, $resolution_due);

            if ($hours_until_resolution <= 0 && !$ticket['resolved_at']) {
                // BREACHED - Resolution
                log_message("❌ BREACH: {$ticket_number} - Resolution overdue by " . abs($hours_until_resolution) . " hours");

                mark_sla_breached($pdo, $ticket_id, 'resolution', $resolution_due, $now);
                escalate_ticket($pdo, $ticket_id, $ticket['escalation_user_id']);
                update_ticket_sla_status($pdo, $ticket_id, 'breached');

                // Send alert email
                send_breach_alert($ticket, 'resolution', abs($hours_until_resolution));

            } elseif ($hours_until_resolution <= ($ticket['resolution_hours'] * 0.2)) {
                // AT RISK - 20% of time remaining
                if ($ticket['sla_status'] != 'breached') {
                    update_ticket_sla_status($pdo, $ticket_id, 'at_risk');
                    send_risk_warning($pdo, $ticket, 'resolution', $hours_until_resolution);
                }
            }
        }
    }

    log_message("Summary: {$on_track_count} on track, {$at_risk_count} at risk, {$breached_count} breached");
    log_message("=== SLA Compliance Monitor Finished ===\n");

} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage());
    exit(1);
}

/**
 * Calculate hours between two dates
 */
function hours_between($from, $to) {
    $diff = $to->getTimestamp() - $from->getTimestamp();
    return $diff / 3600; // Convert seconds to hours
}

/**
 * Mark SLA as breached
 */
function mark_sla_breached($pdo, $ticket_id, $breach_type, $target_time, $actual_time) {
    $hours_overdue = hours_between($target_time, $actual_time);

    $stmt = $pdo->prepare('
        INSERT INTO sla_breaches (ticket_id, sla_policy_id, breach_type, target_time, actual_time, hours_overdue, escalated)
        SELECT ?, sla_policy_id, ?, ?, ?, ?, TRUE
        FROM tickets WHERE id = ?
    ');
    $stmt->execute([
        $ticket_id,
        $breach_type,
        $target_time->format('Y-m-d H:i:s'),
        $actual_time->format('Y-m-d H:i:s'),
        $hours_overdue,
        $ticket_id
    ]);
}

/**
 * Escalate ticket to manager
 */
function escalate_ticket($pdo, $ticket_id, $escalation_user_id) {
    if (!$escalation_user_id) {
        return;
    }

    // Assign ticket to escalation user
    $stmt = $pdo->prepare('UPDATE tickets SET assigned_to_id = ? WHERE id = ?');
    $stmt->execute([$escalation_user_id, $ticket_id]);

    // Add internal note about escalation
    $stmt = $pdo->prepare('
        INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
        VALUES (?, ?, ?, TRUE, NOW())
    ');
    $stmt->execute([
        $ticket_id,
        $escalation_user_id,
        '🚨 Ticket automatically escalated due to SLA breach.'
    ]);

    log_message("✓ Escalated ticket to user ID: {$escalation_user_id}");
}

/**
 * Update ticket SLA status
 */
function update_ticket_sla_status($pdo, $ticket_id, $status) {
    $stmt = $pdo->prepare('UPDATE tickets SET sla_status = ? WHERE id = ?');
    $stmt->execute([$status, $ticket_id]);
}

/**
 * Send breach alert email
 */
function send_breach_alert($ticket, $breach_type, $hours_overdue) {
    if (!$ticket['assigned_email']) {
        return; // No one to notify
    }

    $breach_label = $breach_type == 'first_response' ? 'First Response' : 'Resolution';
    $subject = "🚨 SLA BREACH - Ticket {$ticket['ticket_number']} requires immediate attention";

    $body = "
A ticket has breached its SLA and requires immediate attention:

Ticket Number: {$ticket['ticket_number']}
Subject: {$ticket['subject']}
Customer: {$ticket['requester_name']} ({$ticket['requester_email']})

SLA Policy: {$ticket['sla_name']}
Breach Type: {$breach_label}
OVERDUE BY: " . round($hours_overdue, 1) . " hours

This ticket has been automatically escalated.

View Ticket: http://10.0.1.100/ticket-view.php?id={$ticket['id']}

LUMIRA Support System
";

    send_simple_email($ticket['assigned_email'], $subject, $body);
    log_message("✓ Sent breach alert to {$ticket['assigned_email']}");
}

/**
 * Send at-risk warning email (only once)
 */
function send_risk_warning($pdo, $ticket, $warning_type, $hours_remaining) {
    // Check if we already sent a warning
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM ticket_comments
        WHERE ticket_id = ? AND is_internal = TRUE
        AND comment LIKE ?
    ');
    $stmt->execute([$ticket['id'], "%SLA WARNING: {$warning_type}%"]);
    if ($stmt->fetchColumn() > 0) {
        return; // Already warned
    }

    if (!$ticket['assigned_email']) {
        return;
    }

    $warning_label = $warning_type == 'first_response' ? 'First Response' : 'Resolution';
    $minutes_remaining = round($hours_remaining * 60);
    $subject = "⚠️ SLA WARNING - Ticket {$ticket['ticket_number']} approaching deadline";

    $body = "
A ticket is approaching its SLA deadline:

Ticket Number: {$ticket['ticket_number']}
Subject: {$ticket['subject']}
Customer: {$ticket['requester_name']} ({$ticket['requester_email']})

SLA Policy: {$ticket['sla_name']}
Warning Type: {$warning_label}
Time Remaining: {$minutes_remaining} minutes

Please respond as soon as possible to avoid SLA breach.

View Ticket: http://10.0.1.100/ticket-view.php?id={$ticket['id']}

LUMIRA Support System
";

    send_simple_email($ticket['assigned_email'], $subject, $body);

    // Log warning in ticket
    $stmt = $pdo->prepare('
        INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
        VALUES (?, NULL, ?, TRUE, NOW())
    ');
    $stmt->execute([
        $ticket['id'],
        "⚠️ SLA WARNING: {$warning_type} - {$minutes_remaining} minutes remaining"
    ]);

    log_message("✓ Sent warning email to {$ticket['assigned_email']}");
}
