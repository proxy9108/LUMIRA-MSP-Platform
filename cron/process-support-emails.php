<?php
/**
 * LUMIRA - Email-to-Ticket Processor
 *
 * This script processes emails from support@lumira.local and converts them to tickets
 * Run this every 1-5 minutes via Windows Task Scheduler
 *
 * Command: php C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\cron\process-support-emails.php
 */

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/inc/email.php';

// Configuration
$IMAP_HOST = 'localhost';
$IMAP_PORT = 143;
$IMAP_USER = 'support@lumira.local';
$IMAP_PASS = 'your_support_email_password_here'; // TODO: Set this password

// Log file
$log_file = dirname(__DIR__) . '/logs/email-processor.log';

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

log_message("=== Email-to-Ticket Processor Started ===");

try {
    // Connect to IMAP
    $inbox = @imap_open(
        "{{$IMAP_HOST}:{$IMAP_PORT}/imap/novalidate-cert}INBOX",
        $IMAP_USER,
        $IMAP_PASS
    );

    if (!$inbox) {
        throw new Exception('Cannot connect to IMAP: ' . imap_last_error());
    }

    log_message("✓ Connected to IMAP server");

    // Get database connection
    $pdo = get_db();

    // Search for unread emails
    $emails = imap_search($inbox, 'UNSEEN');

    if (!$emails) {
        log_message("No new emails to process");
        imap_close($inbox);
        exit(0);
    }

    log_message("Found " . count($emails) . " unread emails");

    // Process each email
    foreach ($emails as $email_id) {
        try {
            log_message("Processing email ID: {$email_id}");

            // Get email header
            $header = imap_headerinfo($inbox, $email_id);

            // Get email body
            $structure = imap_fetchstructure($inbox, $email_id);
            $body = get_email_body($inbox, $email_id, $structure);

            // Get attachments
            $attachments = get_email_attachments($inbox, $email_id, $structure);

            // Parse sender
            $from_email = '';
            $from_name = '';
            if (isset($header->from[0])) {
                $from_email = strtolower($header->from[0]->mailbox . '@' . $header->from[0]->host);
                $from_name = isset($header->from[0]->personal) ?
                    imap_utf8($header->from[0]->personal) : $from_email;
            }

            // Parse subject
            $subject = isset($header->subject) ? imap_utf8($header->subject) : 'No Subject';

            // Get message ID for threading
            $message_id = isset($header->message_id) ? $header->message_id : null;

            log_message("From: {$from_name} <{$from_email}>");
            log_message("Subject: {$subject}");

            // Check if this is a reply to existing ticket
            if (preg_match('/\[TKT-(\d{8})-([A-Z0-9]{6})\]/', $subject, $matches)) {
                // This is a REPLY
                $ticket_number = 'TKT-' . $matches[1] . '-' . $matches[2];
                log_message("Reply to existing ticket: {$ticket_number}");

                handle_ticket_reply($pdo, $ticket_number, $from_email, $from_name, $body, $attachments, $message_id);

            } else {
                // This is a NEW ticket
                log_message("Creating new ticket");

                handle_new_ticket($pdo, $from_email, $from_name, $subject, $body, $attachments, $message_id);
            }

            // Mark as read
            imap_setflag_full($inbox, $email_id, "\\Seen");
            log_message("✓ Email processed successfully");

        } catch (Exception $e) {
            log_message("✗ Error processing email {$email_id}: " . $e->getMessage());
            // Don't mark as read if error occurred
        }
    }

    imap_close($inbox);
    log_message("=== Email-to-Ticket Processor Finished ===\n");

} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage());
    exit(1);
}

/**
 * Get email body (handles both plain text and HTML)
 */
function get_email_body($inbox, $email_id, $structure) {
    $body = '';

    // Try to get HTML body first
    $html_body = imap_fetchbody($inbox, $email_id, '1.2');
    if (empty($html_body)) {
        $html_body = imap_fetchbody($inbox, $email_id, '1');
    }

    // Try to get plain text body
    $plain_body = imap_fetchbody($inbox, $email_id, '1.1');
    if (empty($plain_body)) {
        $plain_body = imap_fetchbody($inbox, $email_id, '1');
    }

    // Prefer HTML, fall back to plain text
    if (!empty($html_body)) {
        $body = quoted_printable_decode($html_body);
        $body = html_to_text($body);
    } elseif (!empty($plain_body)) {
        $body = quoted_printable_decode($plain_body);
    } else {
        // Last resort: get entire body
        $body = imap_body($inbox, $email_id);
    }

    // Clean the body
    $body = clean_email_body($body);

    return $body;
}

/**
 * Extract attachments from email
 */
function get_email_attachments($inbox, $email_id, $structure) {
    $attachments = [];

    if (!isset($structure->parts) || !is_array($structure->parts)) {
        return $attachments;
    }

    foreach ($structure->parts as $part_num => $part) {
        $disposition = isset($part->disposition) ? strtolower($part->disposition) : '';

        if ($disposition == 'attachment' || $disposition == 'inline') {
            $filename = '';

            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $filename = $param->value;
                    }
                }
            }

            if (empty($filename) && isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename = $param->value;
                    }
                }
            }

            if (!empty($filename)) {
                $data = imap_fetchbody($inbox, $email_id, ($part_num + 1));

                // Decode based on encoding
                if (isset($part->encoding)) {
                    if ($part->encoding == 3) { // BASE64
                        $data = base64_decode($data);
                    } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                        $data = quoted_printable_decode($data);
                    }
                }

                $attachments[] = [
                    'filename' => $filename,
                    'data' => $data,
                    'size' => strlen($data),
                    'mime' => isset($part->subtype) ? $part->subtype : 'application/octet-stream'
                ];
            }
        }
    }

    return $attachments;
}

/**
 * Convert HTML to plain text
 */
function html_to_text($html) {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    return $text;
}

/**
 * Clean email body (remove signatures, quoted text, etc.)
 */
function clean_email_body($body) {
    // Remove common email signatures
    $patterns = [
        '/--\s*$/ms',  // -- signature delimiter
        '/_{10,}/ms',  // Long underscores
        '/Sent from my .*$/mi',
        '/Get Outlook for .*$/mi',
        '/^On .* wrote:$/m',  // Quoted text marker
        '/^>.*$/m',  // Quoted lines
    ];

    foreach ($patterns as $pattern) {
        $body = preg_replace($pattern, '', $body);
    }

    // Remove extra whitespace
    $body = preg_replace('/\n{3,}/', "\n\n", $body);
    $body = trim($body);

    return $body;
}

/**
 * Handle reply to existing ticket
 */
function handle_ticket_reply($pdo, $ticket_number, $from_email, $from_name, $body, $attachments, $message_id) {
    log_message("Adding reply to ticket {$ticket_number}");

    // Find ticket
    $stmt = $pdo->prepare('SELECT id, requester_id, assigned_to_id FROM tickets WHERE ticket_number = ?');
    $stmt->execute([$ticket_number]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        log_message("✗ Ticket {$ticket_number} not found");
        return;
    }

    // Verify sender is authorized (customer or staff)
    $is_authorized = false;

    // Check if sender is the requester
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$ticket['requester_id']]);
    $requester = $stmt->fetch();
    if ($requester && strtolower($requester['email']) == $from_email) {
        $is_authorized = true;
        $user_id = $ticket['requester_id'];
    }

    // Check if sender is a staff member
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND role_id IN (SELECT id FROM app_roles WHERE name IN (\'Admin\', \'Support Agent\'))');
    $stmt->execute([$from_email]);
    $staff = $stmt->fetch();
    if ($staff) {
        $is_authorized = true;
        $user_id = $staff['id'];
    }

    if (!$is_authorized) {
        log_message("✗ Unauthorized sender: {$from_email}");
        return;
    }

    // Add comment
    $stmt = $pdo->prepare('
        INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
        VALUES (?, ?, ?, FALSE, NOW())
        RETURNING id
    ');
    $stmt->execute([$ticket['id'], $user_id, $body]);
    $comment_id = $stmt->fetchColumn();

    // Save attachments
    foreach ($attachments as $file) {
        save_attachment($pdo, $ticket['id'], $comment_id, $file, $user_id, $from_email);
    }

    // Update ticket timestamp
    $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?')->execute([$ticket['id']]);

    // Track email
    if ($message_id) {
        $stmt = $pdo->prepare('
            INSERT INTO ticket_email_tracking (ticket_id, email_message_id, from_address, email_direction)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$ticket['id'], $message_id, $from_email, 'inbound']);
    }

    // Notify the other party
    if ($user_id == $ticket['requester_id']) {
        // Customer replied, notify assigned agent
        notify_agent_of_reply($pdo, $ticket['id']);
    } else {
        // Agent replied, notify customer
        notify_customer_of_reply($pdo, $ticket['id']);
    }

    log_message("✓ Reply added successfully (comment ID: {$comment_id})");
}

/**
 * Handle new ticket creation from email
 */
function handle_new_ticket($pdo, $from_email, $from_name, $subject, $body, $attachments, $message_id) {
    log_message("Creating new ticket from email");

    // Find or create user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$from_email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Create guest customer account
        log_message("Creating new user account for {$from_email}");

        $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        // Get customer role ID
        $stmt = $pdo->prepare('SELECT id FROM app_roles WHERE name = ? LIMIT 1');
        $stmt->execute(['Customer']);
        $role_id = $stmt->fetchColumn() ?: 3;

        $stmt = $pdo->prepare('
            INSERT INTO users (email, password_hash, full_name, role_id, email_verified, created_at)
            VALUES (?, ?, ?, ?, FALSE, NOW())
            RETURNING id
        ');
        $stmt->execute([$from_email, $password_hash, $from_name, $role_id]);
        $user_id = $stmt->fetchColumn();

        log_message("✓ Created user ID: {$user_id}");
    } else {
        $user_id = $user['id'];
    }

    // Auto-detect category from subject/body keywords
    $category_id = detect_category($pdo, $subject . ' ' . $body);

    // Auto-detect priority from subject/body
    $priority_id = detect_priority($pdo, $subject . ' ' . $body);

    // Get default status (New)
    $stmt = $pdo->prepare('SELECT id FROM ticket_statuses WHERE name = ? LIMIT 1');
    $stmt->execute(['New']);
    $status_id = $stmt->fetchColumn() ?: 1;

    // Generate ticket number
    $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    // Determine department from email address (if TO was specific department)
    // For now, use Technical Support as default
    $stmt = $pdo->prepare('SELECT id FROM departments WHERE name = ? LIMIT 1');
    $stmt->execute(['Technical Support']);
    $department_id = $stmt->fetchColumn();

    // Create ticket
    $stmt = $pdo->prepare('
        INSERT INTO tickets (
            ticket_number, requester_id, department_id, category_id, priority_id, status_id,
            subject, description, source, email_thread_id, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        RETURNING id
    ');
    $stmt->execute([
        $ticket_number,
        $user_id,
        $department_id,
        $category_id,
        $priority_id,
        $status_id,
        $subject,
        $body,
        'email',
        $message_id
    ]);
    $ticket_id = $stmt->fetchColumn();

    log_message("✓ Created ticket: {$ticket_number} (ID: {$ticket_id})");

    // Apply SLA policy
    apply_sla_policy($pdo, $ticket_id, $priority_id);

    // Save attachments
    foreach ($attachments as $file) {
        save_attachment($pdo, $ticket_id, null, $file, $user_id, $from_email);
    }

    // Track email
    if ($message_id) {
        $stmt = $pdo->prepare('
            INSERT INTO ticket_email_tracking (ticket_id, email_message_id, email_subject, from_address, email_direction)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$ticket_id, $message_id, $subject, $from_email, 'inbound']);
    }

    // Send confirmation email to customer
    send_ticket_created_confirmation($pdo, $ticket_id);

    // Notify department/agent
    notify_new_ticket($pdo, $ticket_id);

    log_message("✓ Ticket created successfully");
}

/**
 * Detect category from keywords
 */
function detect_category($pdo, $text) {
    $text = strtolower($text);

    $keywords = [
        'Password/Login' => ['password', 'login', 'signin', 'sign in', 'cant login', 'forgot password', 'reset password'],
        'Technical Issue' => ['error', 'broken', 'not working', 'bug', 'issue', 'problem', 'crash'],
        'Billing' => ['billing', 'invoice', 'payment', 'charge', 'refund', 'subscription'],
        'Product Question' => ['product', 'feature', 'how to', 'how do i', 'question about'],
        'Order Issue' => ['order', 'shipping', 'delivery', 'tracking', 'received'],
    ];

    foreach ($keywords as $category_name => $words) {
        foreach ($words as $word) {
            if (strpos($text, $word) !== false) {
                $stmt = $pdo->prepare('SELECT id FROM ticket_categories WHERE name = ? LIMIT 1');
                $stmt->execute([$category_name]);
                $cat = $stmt->fetchColumn();
                if ($cat) {
                    log_message("Detected category: {$category_name}");
                    return $cat;
                }
            }
        }
    }

    // Default category
    $stmt = $pdo->prepare('SELECT id FROM ticket_categories ORDER BY id LIMIT 1');
    $stmt->execute();
    return $stmt->fetchColumn() ?: 1;
}

/**
 * Detect priority from keywords
 */
function detect_priority($pdo, $text) {
    $text = strtolower($text);

    // High priority keywords
    $high_keywords = ['urgent', 'asap', 'emergency', 'critical', 'down', 'broken', 'not working'];
    foreach ($high_keywords as $word) {
        if (strpos($text, $word) !== false) {
            $stmt = $pdo->prepare('SELECT id FROM ticket_priorities WHERE name = ? LIMIT 1');
            $stmt->execute(['High']);
            $priority = $stmt->fetchColumn();
            if ($priority) {
                log_message("Detected priority: High");
                return $priority;
            }
        }
    }

    // Default to Medium
    $stmt = $pdo->prepare('SELECT id FROM ticket_priorities WHERE name = ? LIMIT 1');
    $stmt->execute(['Medium']);
    return $stmt->fetchColumn() ?: 3;
}

/**
 * Save attachment to disk and database
 */
function save_attachment($pdo, $ticket_id, $comment_id, $file, $user_id, $user_email) {
    $upload_dir = dirname(__DIR__) . '/uploads/tickets/' . $ticket_id;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['filename'], PATHINFO_EXTENSION);
    $unique_name = bin2hex(random_bytes(16)) . '.' . $ext;
    $file_path = $upload_dir . '/' . $unique_name;

    // Save file
    file_put_contents($file_path, $file['data']);

    // Save to database
    $stmt = $pdo->prepare('
        INSERT INTO ticket_attachments (
            ticket_id, comment_id, filename, original_filename, file_path,
            file_size, mime_type, uploaded_by_id, uploaded_by_email
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $ticket_id,
        $comment_id,
        $unique_name,
        $file['filename'],
        $file_path,
        $file['size'],
        $file['mime'],
        $user_id,
        $user_email
    ]);

    log_message("✓ Saved attachment: {$file['filename']}");
}

/**
 * Apply SLA policy to ticket
 */
function apply_sla_policy($pdo, $ticket_id, $priority_id) {
    // Find applicable SLA policy
    $stmt = $pdo->prepare('
        SELECT * FROM sla_policies
        WHERE priority_id = ? AND is_active = TRUE
        ORDER BY CASE customer_tier WHEN \'vip\' THEN 1 WHEN \'standard\' THEN 2 ELSE 3 END
        LIMIT 1
    ');
    $stmt->execute([$priority_id]);
    $sla = $stmt->fetch();

    if (!$sla) {
        return; // No SLA policy found
    }

    $now = new DateTime();

    // Calculate first response deadline (simplified - business hours calculation would go here)
    $first_response_due = clone $now;
    $first_response_due->modify("+{$sla['first_response_hours']} hours");

    // Calculate resolution deadline
    $resolution_due = clone $now;
    $resolution_due->modify("+{$sla['resolution_hours']} hours");

    // Update ticket
    $stmt = $pdo->prepare('
        UPDATE tickets SET
            sla_policy_id = ?,
            first_response_due = ?,
            resolution_due = ?,
            sla_status = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $sla['id'],
        $first_response_due->format('Y-m-d H:i:s'),
        $resolution_due->format('Y-m-d H:i:s'),
        'on_track',
        $ticket_id
    ]);

    log_message("✓ Applied SLA policy: {$sla['name']}");
}

/**
 * Send confirmation email to customer
 */
function send_ticket_created_confirmation($pdo, $ticket_id) {
    $stmt = $pdo->prepare('
        SELECT t.*, u.email, u.full_name
        FROM tickets t
        JOIN users u ON t.requester_id = u.id
        WHERE t.id = ?
    ');
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        return;
    }

    $subject = "Your support ticket has been created [{$ticket['ticket_number']}]";
    $body = "
Hello {$ticket['full_name']},

Thank you for contacting LUMIRA support. Your ticket has been received and our team will respond shortly.

Ticket Details:
- Ticket Number: {$ticket['ticket_number']}
- Subject: {$ticket['subject']}
- Priority: " . get_priority_name($pdo, $ticket['priority_id']) . "
- Created: " . date('F j, Y g:i A', strtotime($ticket['created_at'])) . "

You can view and reply to this ticket at:
http://10.0.1.100/ticket-view.php?id={$ticket['id']}

To reply via email, simply respond to this message. Your reply will be automatically added to the ticket.

Best regards,
LUMIRA Support Team
";

    send_simple_email($ticket['email'], $subject, $body);
    log_message("✓ Sent confirmation email to {$ticket['email']}");
}

/**
 * Helper functions
 */
function get_priority_name($pdo, $priority_id) {
    $stmt = $pdo->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
    $stmt->execute([$priority_id]);
    return $stmt->fetchColumn() ?: 'Unknown';
}

function notify_agent_of_reply($pdo, $ticket_id) {
    // TODO: Send email to assigned agent
    log_message("TODO: Notify agent of customer reply");
}

function notify_customer_of_reply($pdo, $ticket_id) {
    // TODO: Send email to customer
    log_message("TODO: Notify customer of agent reply");
}

function notify_new_ticket($pdo, $ticket_id) {
    // TODO: Send email to department/agent
    log_message("TODO: Notify team of new ticket");
}
