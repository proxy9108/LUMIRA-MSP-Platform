<?php
/**
 * Email Helper Functions
 * Handles sending emails via SMTP
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Save message to user's inbox (database)
 * @param string $user_email User's email address
 * @param string $subject Email subject
 * @param string $message_body HTML email body
 * @param string $message_type Type: 'order', 'ticket', 'general'
 * @param int $related_id Related order_id or ticket_id
 * @return bool Success status
 */
function save_user_message($user_email, $subject, $message_body, $message_type = 'general', $related_id = null) {
    global $pdo;

    try {
        $stmt = $pdo->prepare('
            INSERT INTO user_messages (user_email, subject, message_body, message_type, related_id)
            VALUES (?, ?, ?, ?, ?)
        ');

        return $stmt->execute([
            $user_email,
            $subject,
            $message_body,
            $message_type,
            $related_id
        ]);
    } catch (Exception $e) {
        error_log("Failed to save user message: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using SMTP
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message HTML email body
 * @param string $from_email Optional sender email (uses config default)
 * @param string $from_name Optional sender name (uses config default)
 * @param array $cc Optional CC recipients (array of email addresses)
 * @return bool Success status
 */
function send_email($to, $subject, $message, $from_email = null, $from_name = null, $cc = []) {
    $from_email = $from_email ?? SMTP_FROM_EMAIL;
    $from_name = $from_name ?? SMTP_FROM_NAME;

    $smtp_server = SMTP_HOST;
    $smtp_port = SMTP_PORT;
    $smtp_user = SMTP_USERNAME;
    $smtp_pass = SMTP_PASSWORD;

    try {
        // Open socket connection to SMTP server
        $sock = @fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
        if (!$sock) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }

        // Read server greeting
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP greeting failed: $response");
            fclose($sock);
            return false;
        }

        // Send EHLO
        fputs($sock, "EHLO localhost\r\n");
        // Read ALL EHLO response lines (MailEnable sends multiple lines)
        while ($line = fgets($sock, 515)) {
            if (substr($line, 3, 1) == ' ') {
                // Last line (has space at position 3, not hyphen)
                break;
            }
        }

        // AUTH LOGIN
        fputs($sock, "AUTH LOGIN\r\n");
        fgets($sock, 515);
        fputs($sock, base64_encode($smtp_user) . "\r\n");
        fgets($sock, 515);
        fputs($sock, base64_encode($smtp_pass) . "\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '235') {
            error_log("SMTP auth failed: $response");
            fclose($sock);
            return false;
        }

        // MAIL FROM
        fputs($sock, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("SMTP MAIL FROM failed: $response");
            fclose($sock);
            return false;
        }

        // RCPT TO (primary recipient)
        fputs($sock, "RCPT TO: <$to>\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("SMTP RCPT TO failed: $response");
            fclose($sock);
            return false;
        }

        // RCPT TO (CC recipients)
        foreach ($cc as $cc_email) {
            fputs($sock, "RCPT TO: <$cc_email>\r\n");
            $response = fgets($sock, 515);
            if (substr($response, 0, 3) != '250') {
                error_log("SMTP RCPT TO (CC) failed for $cc_email: $response");
                // Continue anyway - don't fail entire email if one CC fails
            }
        }

        // DATA
        fputs($sock, "DATA\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '354') {
            error_log("SMTP DATA failed: $response");
            fclose($sock);
            return false;
        }

        // Message headers and body
        $headers = "From: " . $from_name . " <$from_email>\r\n";
        $headers .= "To: <$to>\r\n";

        // Add CC header if there are CC recipients
        if (!empty($cc)) {
            $cc_list = implode(', ', array_map(function($email) {
                return "<$email>";
            }, $cc));
            $headers .= "Cc: $cc_list\r\n";
        }

        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";

        fputs($sock, $headers . $message . "\r\n.\r\n");
        $response = fgets($sock, 515);

        // QUIT
        fputs($sock, "QUIT\r\n");
        fclose($sock);

        if (substr($response, 0, 3) != '250') {
            error_log("Email failed to send to: $to, subject: $subject, response: $response");
            return false;
        }

        return true;

    } catch (Exception $e) {
        error_log("Email send exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send simple email (for testing and simple messages)
 * Uses SMTP sockets directly
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Plain text message
 * @return bool Success status
 */
function send_simple_email($to, $subject, $message) {
    $from = SMTP_FROM_EMAIL;
    $smtp_server = SMTP_HOST;
    $smtp_port = SMTP_PORT;
    $smtp_user = SMTP_USERNAME;
    $smtp_pass = SMTP_PASSWORD;

    try {
        // Open socket connection to SMTP server
        $sock = fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
        if (!$sock) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }

        // Read server greeting
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP greeting failed: $response");
            fclose($sock);
            return false;
        }

        // Send EHLO
        fputs($sock, "EHLO localhost\r\n");
        // Read ALL EHLO response lines (MailEnable sends multiple lines)
        while ($line = fgets($sock, 515)) {
            if (substr($line, 3, 1) == ' ') {
                // Last line (has space at position 3, not hyphen)
                break;
            }
        }

        // AUTH LOGIN
        fputs($sock, "AUTH LOGIN\r\n");
        fgets($sock, 515);
        fputs($sock, base64_encode($smtp_user) . "\r\n");
        fgets($sock, 515);
        fputs($sock, base64_encode($smtp_pass) . "\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '235') {
            error_log("SMTP auth failed: $response");
            fclose($sock);
            return false;
        }

        // MAIL FROM
        fputs($sock, "MAIL FROM: <$from>\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("SMTP MAIL FROM failed: $response");
            fclose($sock);
            return false;
        }

        // RCPT TO
        fputs($sock, "RCPT TO: <$to>\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("SMTP RCPT TO failed: $response");
            fclose($sock);
            return false;
        }

        // DATA
        fputs($sock, "DATA\r\n");
        $response = fgets($sock, 515);
        if (substr($response, 0, 3) != '354') {
            error_log("SMTP DATA failed: $response");
            fclose($sock);
            return false;
        }

        // Message headers and body
        $headers = "From: " . SMTP_FROM_NAME . " <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";

        fputs($sock, $headers . $message . "\r\n.\r\n");
        $response = fgets($sock, 515);

        // QUIT
        fputs($sock, "QUIT\r\n");
        fclose($sock);

        return (substr($response, 0, 3) == '250');

    } catch (Exception $e) {
        error_log("Email send exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order confirmation email
 * @param array $order Order data
 * @param array $items Order items
 * @return bool
 */
function send_order_confirmation($order, $items) {
    global $pdo;

    $customer_email = $order['customer_email'];  // Store original customer email
    $subject = 'Order Confirmation - ' . SITE_NAME . ' #' . $order['order_number'];

    // Check if user has an account with this email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$customer_email]);
    $user_exists = $stmt->fetch();

    // Determine recipient
    if ($user_exists) {
        // User has account - they'll see it in My Messages, still notify staff
        $to = NOTIFICATIONS_EMAIL;
        $cc = ['admin@lumira.local', 'support@lumira.local'];
    } else {
        // Guest checkout - only notify staff
        $to = NOTIFICATIONS_EMAIL;
        $cc = ['admin@lumira.local', 'support@lumira.local'];
    }

    // Build items list
    $items_html = '';
    $total = 0;
    foreach ($items as $item) {
        $subtotal = $item['price_cents'] * $item['qty'];
        $total += $subtotal;
        $items_html .= '<tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($item['product_name']) . '</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . $item['qty'] . '</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">$' . number_format($item['price_cents'] / 100, 2) . '</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">$' . number_format($subtotal / 100, 2) . '</td>
        </tr>';
    }

    $message = get_email_template([
        'title' => 'Order Confirmation',
        'body' => '
            <div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0 0 10px 0; color: #856404;">📧 Customer Email (For Staff)</h3>
                <p style="margin: 0; font-size: 16px;"><strong>Send updates to:</strong> ' . htmlspecialchars($customer_email) . '</p>
            </div>

            <p>Dear ' . htmlspecialchars($order['customer_name']) . ',</p>
            <p>Thank you for your order! We have received your order and are processing it now.</p>

            <h2 style="color: #dc143c; margin-top: 30px;">Customer Information</h2>
            <p>
                <strong>Name:</strong> ' . htmlspecialchars($order['customer_name']) . '<br>
                <strong>Email:</strong> ' . htmlspecialchars($customer_email) . '<br>
                <strong>Phone:</strong> ' . htmlspecialchars($order['customer_phone'] ?? 'Not provided') . '
            </p>

            <h2 style="color: #dc143c; margin-top: 30px;">Order Details</h2>
            <p><strong>Order Number:</strong> ' . htmlspecialchars($order['order_number']) . '<br>
            <strong>Order Date:</strong> ' . date('F j, Y, g:i a', strtotime($order['created_at'])) . '</p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background: #dc143c; color: white;">
                        <th style="padding: 10px; text-align: left;">Product</th>
                        <th style="padding: 10px; text-align: center;">Qty</th>
                        <th style="padding: 10px; text-align: right;">Price</th>
                        <th style="padding: 10px; text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $items_html . '
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px;">Total:</td>
                        <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #dc143c;">$' . number_format($total / 100, 2) . '</td>
                    </tr>
                </tfoot>
            </table>

            <h3 style="color: #dc143c;">Shipping Address</h3>
            <p>' . nl2br(htmlspecialchars($order['customer_address'])) . '</p>

            <p style="margin-top: 30px;">We will send you another email when your order ships.</p>
            <p>If you have any questions, please contact us at ' . SITE_EMAIL . '</p>
        '
    ]);

    // Save message to user's inbox (database) - always save regardless of account status
    save_user_message(
        $customer_email,
        $subject,
        $message,
        'order',
        $order['id'] ?? null
    );

    // Send email notification to staff
    return send_email($to, $subject, $message, null, null, $cc);
}

/**
 * Send ticket confirmation email
 * @param array $ticket Ticket data
 * @return bool
 */
function send_ticket_confirmation($ticket) {
    global $pdo;

    $customer_email = $ticket['customer_email'];  // Store original customer email
    $subject = 'Service Request Received - Ticket #' . $ticket['ticket_number'];

    // Check if user has an account with this email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$customer_email]);
    $user_exists = $stmt->fetch();

    // Determine recipient (always notify staff)
    $to = NOTIFICATIONS_EMAIL;
    $cc = ['admin@lumira.local', 'support@lumira.local'];

    // Build osTicket link if available
    $osticket_link = '';
    if (isset($ticket['osticket_url'])) {
        $osticket_link = '<p style="margin-top: 20px;">
            <a href="' . htmlspecialchars($ticket['osticket_url']) . '"
               style="display: inline-block; padding: 12px 25px; background: linear-gradient(135deg, #dc143c, #ff0000);
               color: white; text-decoration: none; border-radius: 5px; font-weight: 600;">
               View Ticket in Support Portal (osTicket #' . htmlspecialchars($ticket['osticket_number']) . ')
            </a>
        </p>';
    }

    $message = get_email_template([
        'title' => 'Service Request Received',
        'body' => '
            <div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0 0 10px 0; color: #856404;">📧 Customer Email (For Staff)</h3>
                <p style="margin: 0; font-size: 16px;"><strong>Send updates to:</strong> ' . htmlspecialchars($customer_email) . '</p>
            </div>

            <p>Dear ' . htmlspecialchars($ticket['customer_name']) . ',</p>
            <p>Thank you for contacting ' . SITE_NAME . '. We have received your service request and our team will review it shortly.</p>

            <h2 style="color: #dc143c; margin-top: 30px;">Customer Information</h2>
            <p>
                <strong>Name:</strong> ' . htmlspecialchars($ticket['customer_name']) . '<br>
                <strong>Email:</strong> ' . htmlspecialchars($customer_email) . '<br>
                <strong>Phone:</strong> ' . htmlspecialchars($ticket['customer_phone'] ?? 'Not provided') . '
            </p>

            <h2 style="color: #dc143c; margin-top: 30px;">Ticket Details</h2>
            <p>
                <strong>LUMIRA Ticket:</strong> ' . htmlspecialchars($ticket['ticket_number']) . '<br>
                ' . (isset($ticket['osticket_number']) ? '<strong>Support Portal:</strong> osTicket #' . htmlspecialchars($ticket['osticket_number']) . '<br>' : '') . '
                <strong>Service:</strong> ' . htmlspecialchars($ticket['service_name']) . '<br>
                <strong>Status:</strong> ' . htmlspecialchars($ticket['status_name']) . '<br>
                <strong>Priority:</strong> ' . htmlspecialchars($ticket['priority_name']) . '<br>
                <strong>Created:</strong> ' . date('F j, Y, g:i a', strtotime($ticket['created_at'])) . '
            </p>

            <h3 style="color: #dc143c;">Your Request</h3>
            <p><strong>Subject:</strong> ' . htmlspecialchars($ticket['subject']) . '</p>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #dc143c; margin: 15px 0;">
                ' . nl2br(htmlspecialchars($ticket['description'])) . '
            </div>

            ' . $osticket_link . '

            <p style="margin-top: 30px;">One of our technicians will contact you within 24 business hours.</p>
            <p>You can track your ticket status:</p>
            <ul style="margin: 10px 0;">
                <li><strong>LUMIRA Portal:</strong> <a href="' . SITE_URL . '/tickets.php">View your tickets</a></li>
                ' . (isset($ticket['osticket_url']) ? '<li><strong>Support Portal:</strong> <a href="' . htmlspecialchars($ticket['osticket_url']) . '">Direct ticket link</a></li>' : '') . '
            </ul>
            <p>If you have any questions, please contact us at ' . SITE_EMAIL . ' or reference ticket #' . htmlspecialchars($ticket['ticket_number']) . '</p>
        '
    ]);

    // Save message to user's inbox (database) - always save regardless of account status
    save_user_message(
        $customer_email,
        $subject,
        $message,
        'ticket',
        $ticket['id'] ?? null
    );

    // Send email notification to staff
    return send_email($to, $subject, $message, null, null, $cc);
}

/**
 * Send ticket update email
 * @param array $ticket Ticket data
 * @param string $update_message Update message
 * @return bool
 */
function send_ticket_update($ticket, $update_message) {
    $to = $ticket['customer_email'];
    $subject = 'Ticket Update - #' . $ticket['ticket_number'];

    $message = get_email_template([
        'title' => 'Ticket Update',
        'body' => '
            <p>Dear ' . htmlspecialchars($ticket['customer_name']) . ',</p>
            <p>Your support ticket has been updated.</p>

            <h2 style="color: #dc143c; margin-top: 30px;">Ticket #' . htmlspecialchars($ticket['ticket_number']) . '</h2>
            <p>
                <strong>Subject:</strong> ' . htmlspecialchars($ticket['subject']) . '<br>
                <strong>Status:</strong> ' . htmlspecialchars($ticket['status_name']) . '<br>
                <strong>Priority:</strong> ' . htmlspecialchars($ticket['priority_name']) . '
            </p>

            <h3 style="color: #dc143c;">Update</h3>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #dc143c; margin: 15px 0;">
                ' . nl2br(htmlspecialchars($update_message)) . '
            </div>

            <p style="margin-top: 30px;">View full ticket details: <a href="' . SITE_URL . '/dashboard-customer.php">' . SITE_URL . '/dashboard-customer.php</a></p>
        '
    ]);

    return send_email($to, $subject, $message);
}

/**
 * Notify staff/workers about ticket updates
 * Sends email and saves to their message inbox
 * @param array $ticket Ticket data
 * @param string $update_type Type: 'new_comment', 'status_change', 'assignment'
 * @param string $update_details Details of the update
 * @return void
 */
function notify_staff_ticket_update($ticket, $update_type, $update_details) {
    global $pdo;

    try {
        // Get all admin and worker emails
        $stmt = $pdo->prepare('
            SELECT DISTINCT u.email, u.full_name
            FROM users u
            JOIN app_roles r ON u.role_id = r.id
            WHERE r.name IN (\'super_admin\', \'admin\', \'manager\', \'technician\')
            AND u.is_active = true
        ');
        $stmt->execute();
        $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build notification message based on type
        $action_text = '';
        switch ($update_type) {
            case 'new_comment':
                $action_text = 'New comment added by customer';
                break;
            case 'status_change':
                $action_text = 'Status changed';
                break;
            case 'assignment':
                $action_text = 'Ticket assigned';
                break;
            default:
                $action_text = 'Ticket updated';
        }

        $subject = '🔔 Ticket Update - #' . $ticket['ticket_number'] . ' - ' . $action_text;

        $message = get_email_template([
            'title' => 'Ticket Update Notification',
            'body' => '
                <div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                    <h3 style="margin: 0 0 10px 0; color: #856404;">🔔 Staff Notification</h3>
                    <p style="margin: 0; font-size: 16px;"><strong>Action:</strong> ' . htmlspecialchars($action_text) . '</p>
                </div>

                <h2 style="color: #dc143c; margin-top: 30px;">Ticket #' . htmlspecialchars($ticket['ticket_number']) . '</h2>
                <p>
                    <strong>Subject:</strong> ' . htmlspecialchars($ticket['subject']) . '<br>
                    <strong>Customer:</strong> ' . htmlspecialchars($ticket['customer_name'] ?? 'N/A') . '<br>
                    <strong>Email:</strong> ' . htmlspecialchars($ticket['customer_email']) . '<br>
                    <strong>Status:</strong> ' . htmlspecialchars($ticket['status_name'] ?? 'N/A') . '<br>
                    <strong>Priority:</strong> ' . htmlspecialchars($ticket['priority_name'] ?? 'N/A') . '
                </p>

                <h3 style="color: #dc143c;">Update Details</h3>
                <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #dc143c; margin: 15px 0;">
                    ' . nl2br(htmlspecialchars($update_details)) . '
                </div>

                <p style="margin-top: 30px;">
                    <a href="' . SITE_URL . '/admin/ticket-view.php?id=' . ($ticket['id'] ?? 0) . '"
                       style="display: inline-block; padding: 12px 25px; background: linear-gradient(135deg, #dc143c, #ff0000);
                       color: white; text-decoration: none; border-radius: 5px; font-weight: 600;">
                       View Ticket in Admin Portal
                    </a>
                </p>
            '
        ]);

        // Send to all staff members and save to their inboxes
        foreach ($staff_members as $staff) {
            // Save to staff member's message inbox
            save_user_message(
                $staff['email'],
                $subject,
                $message,
                'ticket',
                $ticket['id'] ?? null
            );

            // Optionally send email (commented out to avoid spam during testing)
            // send_email($staff['email'], $subject, $message);
        }

    } catch (Exception $e) {
        error_log('Failed to notify staff about ticket update: ' . $e->getMessage());
    }
}

/**
 * Get email template wrapper
 * @param array $data Template data (title, body)
 * @return string HTML email
 */
function get_email_template($data) {
    $title = $data['title'] ?? 'Notification';
    $body = $data['body'] ?? '';

    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #dc143c 0%, #ff0000 100%); padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 800;">' . SITE_NAME . '</h1>
                            <p style="margin: 5px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">' . SITE_TAGLINE . '</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            ' . $body . '
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #1a1a1a; padding: 20px 30px; text-align: center; border-top: 3px solid #dc143c;">
                            <p style="margin: 0 0 10px 0; color: #b0b0b0; font-size: 12px;">
                                &copy; ' . date('Y') . ' ' . SITE_NAME . ' - ' . SITE_TAGLINE . '
                            </p>
                            <p style="margin: 0; color: #b0b0b0; font-size: 12px;">
                                <a href="' . SITE_URL . '" style="color: #dc143c; text-decoration: none;">' . SITE_URL . '</a> |
                                <a href="mailto:' . SITE_EMAIL . '" style="color: #dc143c; text-decoration: none;">' . SITE_EMAIL . '</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}
