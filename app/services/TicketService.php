<?php
/**
 * osTicket Integration
 * API integration between LUMIRA and osTicket
 */

require_once __DIR__ . '/config.php';

/**
 * Create ticket in osTicket via API
 * @param array $data Ticket data
 * @return array|false osTicket response or false on failure
 */
function osticket_create_ticket($data) {
    if (!defined('OSTICKET_API_KEY') || !defined('OSTICKET_API_ENDPOINT')) {
        error_log('osTicket API not configured');
        return false;
    }

    // Prepare ticket data for osTicket API
    $ticket_data = [
        'alert' => true, // Send email alert to customer
        'autorespond' => true, // Send auto-response
        'source' => 'API',
        'name' => $data['name'] ?? 'Customer',
        'email' => $data['email'] ?? '',
        'phone' => $data['phone'] ?? '',
        'subject' => $data['subject'] ?? 'Support Request',
        'message' => $data['message'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'topicId' => $data['topic_id'] ?? 1, // Default help topic
        'priority' => $data['priority'] ?? 2, // 1=Emergency, 2=Urgent, 3=Normal, 4=Low
    ];

    // Optional fields
    if (isset($data['attachments'])) {
        $ticket_data['attachments'] = $data['attachments'];
    }

    // Make API request to osTicket
    $ch = curl_init(OSTICKET_API_ENDPOINT);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ticket_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . OSTICKET_API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201) {
        // Success - ticket created
        $result = json_decode($response, true);
        error_log('osTicket created: ' . print_r($result, true));
        return $result;
    } else {
        // Failed
        error_log("osTicket API failed (HTTP $http_code): $response");
        return false;
    }
}

/**
 * Get ticket from osTicket by ticket number
 * @param string $ticket_number osTicket ticket number
 * @return array|false Ticket data or false
 */
function osticket_get_ticket($ticket_number) {
    if (!defined('OSTICKET_URL')) {
        return false;
    }

    // Note: osTicket's API doesn't have a public GET endpoint
    // This would require custom API endpoint or direct database access
    // For now, we'll use direct database query

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('
            SELECT * FROM osticket_ticket_links
            WHERE osticket_ticket_number = ?
        ');
        $stmt->execute([$ticket_number]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('osTicket lookup failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Link LUMIRA ticket to osTicket ticket
 * @param int $lumira_ticket_id LUMIRA ticket ID
 * @param int $osticket_ticket_id osTicket ticket ID
 * @param string $osticket_ticket_number osTicket ticket number
 * @return bool Success
 */
function osticket_link_ticket($lumira_ticket_id, $osticket_ticket_id, $osticket_ticket_number) {
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('
            INSERT INTO osticket_ticket_links (lumira_ticket_id, osticket_ticket_id, osticket_ticket_number, created_at)
            VALUES (?, ?, ?, NOW())
            ON CONFLICT DO NOTHING
        ');
        $stmt->execute([$lumira_ticket_id, $osticket_ticket_id, $osticket_ticket_number]);
        return true;
    } catch (PDOException $e) {
        error_log('osTicket link failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get osTicket ticket URL for customer portal
 * @param string $ticket_number Ticket number
 * @param string $email Customer email
 * @return string URL to ticket
 */
function osticket_get_ticket_url($ticket_number, $email) {
    $base_url = defined('OSTICKET_URL') ? OSTICKET_URL : 'http://10.0.1.100/osticket/upload';
    return $base_url . '/view.php?e=' . urlencode($email) . '&t=' . urlencode($ticket_number);
}

/**
 * Get osTicket admin ticket URL
 * @param int $ticket_id Ticket ID
 * @return string URL to admin ticket view
 */
function osticket_get_admin_url($ticket_id) {
    $base_url = defined('OSTICKET_URL') ? OSTICKET_URL : 'http://10.0.1.100/osticket/upload';
    return $base_url . '/scp/tickets.php?id=' . $ticket_id;
}

/**
 * Create osTicket user account
 * @param array $user_data User data
 * @return int|false User ID or false
 */
function osticket_create_user($user_data) {
    // This would use osTicket API or direct database insert
    // For now, osTicket auto-creates users when tickets are created
    return true;
}

/**
 * Sync LUMIRA user to osTicket
 * @param array $lumira_user LUMIRA user data
 * @return bool Success
 */
function osticket_sync_user($lumira_user) {
    try {
        $pdo = get_db();

        // Check if already linked
        $stmt = $pdo->prepare('SELECT * FROM osticket_user_links WHERE lumira_user_id = ?');
        $stmt->execute([$lumira_user['id']]);
        $link = $stmt->fetch();

        if ($link) {
            // Already synced
            return true;
        }

        // Create link (osTicket user will be created when they submit first ticket)
        $stmt = $pdo->prepare('
            INSERT INTO osticket_user_links (lumira_user_id, osticket_email, created_at)
            VALUES (?, ?, NOW())
        ');
        $stmt->execute([$lumira_user['id'], $lumira_user['email']]);

        return true;
    } catch (PDOException $e) {
        error_log('osTicket user sync failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if osTicket is installed and configured
 * @return bool
 */
function osticket_is_configured() {
    return defined('OSTICKET_API_KEY') &&
           defined('OSTICKET_API_ENDPOINT') &&
           !empty(OSTICKET_API_KEY) &&
           OSTICKET_API_KEY !== 'YOUR_API_KEY_HERE';
}

/**
 * Get osTicket status
 * @return array Status information
 */
function osticket_get_status() {
    $status = [
        'configured' => osticket_is_configured(),
        'url' => defined('OSTICKET_URL') ? OSTICKET_URL : null,
        'api_enabled' => defined('OSTICKET_API_KEY') && !empty(OSTICKET_API_KEY),
    ];

    // Try to ping osTicket
    if ($status['url']) {
        $ch = curl_init($status['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $status['reachable'] = ($http_code >= 200 && $http_code < 400);
        $status['http_code'] = $http_code;
    }

    return $status;
}
