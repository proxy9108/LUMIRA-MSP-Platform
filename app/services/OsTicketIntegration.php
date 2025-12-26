<?php
/**
 * Service Request Handler with osTicket Integration
 *
 * This is a replacement for the service request logic in services.php
 * Use this code to integrate with osTicket
 */

require_once 'app/config/config.php';
require_once 'app/config/database.php';
require_once 'app/helpers/functions.php';
require_once 'app/config/email.php';
require_once 'app/services/TicketService.php';

/**
 * Handle service request with osTicket integration
 * Call this function from services.php when form is submitted
 *
 * @param array $form_data Form data from $_POST
 * @return array Result with 'success', 'message', 'ticket_number'
 */
function handle_service_request_osticket($form_data) {
    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        // Extract and validate form data
        $service_id = (int)($form_data['service_id'] ?? 0);
        $customer_name = trim($form_data['customer_name'] ?? '');
        $customer_email = trim($form_data['customer_email'] ?? '');
        $customer_phone = trim($form_data['phone'] ?? '');
        $subject = trim($form_data['subject'] ?? '');
        $details = trim($form_data['details'] ?? '');

        // Validate
        if (empty($customer_name) || empty($customer_email) || empty($subject)) {
            throw new Exception('Please fill in all required fields.');
        }

        if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        if ($service_id <= 0) {
            throw new Exception('Please select a valid service.');
        }

        // Get service details
        $stmt = $pdo->prepare('SELECT name FROM services WHERE id = ?');
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        if (!$service) {
            throw new Exception('Invalid service selected.');
        }
        $service_name = $service['name'];

        // Get or create user account
        $stmt = $pdo->prepare('SELECT id, email, full_name FROM users WHERE email = ?');
        $stmt->execute([$customer_email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Create new user account
            $temp_password = bin2hex(random_bytes(16));
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $roleStmt = $pdo->prepare('SELECT id FROM app_roles WHERE name = ? LIMIT 1');
            $roleStmt->execute(['client_user']);
            $role_id = $roleStmt->fetchColumn();

            if (!$role_id) {
                // Fallback to first available role
                $roleStmt = $pdo->prepare('SELECT id FROM app_roles LIMIT 1');
                $roleStmt->execute();
                $role_id = $roleStmt->fetchColumn();
            }

            $stmt = $pdo->prepare('
                INSERT INTO users (email, password_hash, full_name, phone, role_id, is_active, email_verified, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, true, false, NOW(), NOW())
                RETURNING id
            ');
            $stmt->execute([$customer_email, $password_hash, $customer_name, $customer_phone, $role_id]);
            $requester_id = $stmt->fetchColumn();

            // Sync to osTicket
            osticket_sync_user([
                'id' => $requester_id,
                'email' => $customer_email,
                'name' => $customer_name
            ]);
        } else {
            $requester_id = $user['id'];
        }

        // Create ticket in LUMIRA database
        $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        // Get category, status, priority
        $catStmt = $pdo->prepare('SELECT id FROM ticket_categories WHERE name = ? LIMIT 1');
        $catStmt->execute(['Service Request']);
        $category_id = $catStmt->fetchColumn();

        if (!$category_id) {
            $catStmt = $pdo->prepare('SELECT id FROM ticket_categories LIMIT 1');
            $catStmt->execute();
            $category_id = $catStmt->fetchColumn();
        }

        $statusStmt = $pdo->prepare('SELECT id FROM ticket_statuses WHERE name = ? LIMIT 1');
        $statusStmt->execute(['New']);
        $status_id = $statusStmt->fetchColumn() ?: 1;

        $priorityStmt = $pdo->prepare('SELECT id FROM ticket_priorities WHERE name = ? LIMIT 1');
        $priorityStmt->execute(['Medium']);
        $priority_id = $priorityStmt->fetchColumn() ?: 2;

        // Insert into LUMIRA tickets table
        $stmt = $pdo->prepare('
            INSERT INTO tickets (
                ticket_number, requester_id, category_id, priority_id, status_id,
                subject, description, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            RETURNING id
        ');

        $full_description = "Service Requested: $service_name\n\n$details";

        $stmt->execute([
            $ticket_number,
            $requester_id,
            $category_id,
            $priority_id,
            $status_id,
            $subject,
            $full_description
        ]);
        $lumira_ticket_id = $stmt->fetchColumn();

        // Create ticket in osTicket (if configured)
        $osticket_result = null;
        $osticket_ticket_number = null;

        if (osticket_is_configured()) {
            $osticket_data = [
                'name' => $customer_name,
                'email' => $customer_email,
                'phone' => $customer_phone,
                'subject' => $subject,
                'message' => "Service: $service_name\n\nLUMIRA Ticket: $ticket_number\n\n$details",
                'priority' => 2, // Normal priority
                'topic_id' => 1, // Default help topic (configure in osTicket)
            ];

            $osticket_result = osticket_create_ticket($osticket_data);

            if ($osticket_result) {
                // Extract ticket number from response
                if (isset($osticket_result['ticket_number'])) {
                    $osticket_ticket_number = $osticket_result['ticket_number'];
                } elseif (isset($osticket_result['number'])) {
                    $osticket_ticket_number = $osticket_result['number'];
                }

                // Link tickets
                if ($osticket_ticket_number) {
                    $osticket_ticket_id = $osticket_result['id'] ?? null;
                    osticket_link_ticket($lumira_ticket_id, $osticket_ticket_id, $osticket_ticket_number);

                    // Update LUMIRA ticket with osTicket reference
                    $stmt = $pdo->prepare('
                        UPDATE tickets
                        SET description = description || ?
                        WHERE id = ?
                    ');
                    $stmt->execute([
                        "\n\n[osTicket: $osticket_ticket_number]",
                        $lumira_ticket_id
                    ]);
                }
            }
        }

        $pdo->commit();

        // Get full ticket data for email
        $stmt = $pdo->prepare('
            SELECT t.*, ts.name as status_name, tp.name as priority_name
            FROM tickets t
            LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
            LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
            WHERE t.id = ?
        ');
        $stmt->execute([$lumira_ticket_id]);
        $ticket = $stmt->fetch();

        // Send confirmation email via hMailServer
        $email_data = [
            'ticket_number' => $ticket_number,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'service_name' => $service_name,
            'subject' => $subject,
            'description' => $details,
            'status_name' => $ticket['status_name'] ?? 'New',
            'priority_name' => $ticket['priority_name'] ?? 'Medium',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Add osTicket link if available
        if ($osticket_ticket_number) {
            $email_data['osticket_url'] = osticket_get_ticket_url($osticket_ticket_number, $customer_email);
            $email_data['osticket_number'] = $osticket_ticket_number;
        }

        send_ticket_confirmation($email_data);

        // Build success message
        $message = 'Service request submitted successfully! ';
        $message .= "LUMIRA Ticket #$ticket_number has been created. ";

        if ($osticket_ticket_number) {
            $message .= "osTicket #$osticket_ticket_number has also been created for tracking. ";
        }

        $message .= 'Check your email for confirmation.';

        return [
            'success' => true,
            'message' => $message,
            'ticket_number' => $ticket_number,
            'osticket_number' => $osticket_ticket_number,
            'lumira_ticket_id' => $lumira_ticket_id
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Service request error: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => $e->getMessage(),
            'ticket_number' => null
        ];
    }
}
