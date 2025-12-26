<?php
/**
 * LUMIRA - AI Chat API
 * Handles chat requests with authentication-based access control
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

session_start();

header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$user_message = trim($input['message'] ?? '');
$chat_session_id = $_SESSION['chat_session_id'] ?? null;

if (empty($user_message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Create or retrieve chat session ID
if (!$chat_session_id) {
    $chat_session_id = bin2hex(random_bytes(16));
    $_SESSION['chat_session_id'] = $chat_session_id;
    $_SESSION['chat_history'] = [];
}

// Check if user is logged in
$is_authenticated = is_logged_in();
$user = $is_authenticated ? get_logged_in_user() : null;

try {
    $pdo = get_db();

    // Build system prompt based on authentication status
    $system_prompt = buildSystemPrompt($pdo, $is_authenticated, $user);

    // Retrieve chat history from session
    $chat_history = $_SESSION['chat_history'] ?? [];

    // Build messages array for AI
    $messages = [
        ['role' => 'system', 'content' => $system_prompt]
    ];

    // Add chat history (last 10 messages to avoid token limits)
    $recent_history = array_slice($chat_history, -10);
    foreach ($recent_history as $msg) {
        $messages[] = ['role' => 'user', 'content' => $msg['user']];
        $messages[] = ['role' => 'assistant', 'content' => $msg['assistant']];
    }

    // Add current message
    $messages[] = ['role' => 'user', 'content' => $user_message];

    // Call AI API
    $ai_response = callAiApi($messages);

    if ($ai_response['success']) {
        $assistant_message = $ai_response['message'];

        // Save to session history
        $_SESSION['chat_history'][] = [
            'user' => $user_message,
            'assistant' => $assistant_message,
            'timestamp' => time()
        ];

        // Keep only last 20 exchanges to prevent session bloat
        if (count($_SESSION['chat_history']) > 20) {
            $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -20);
        }

        echo json_encode([
            'success' => true,
            'message' => $assistant_message,
            'is_authenticated' => $is_authenticated
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $ai_response['error']
        ]);
    }

} catch (Exception $e) {
    error_log('Chat API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred processing your request'
    ]);
}

/**
 * Build system prompt based on user authentication
 */
function buildSystemPrompt($pdo, $is_authenticated, $user) {
    $prompt = "You are a professional customer service AI assistant for LUMIRA, an IT services and products company.\n\n";

    if ($is_authenticated && $user) {
        // AUTHENTICATED USER MODE - Can access user's own data
        $prompt .= "**AUTHENTICATION STATUS:** User is logged in as " . $user['full_name'] . " (" . $user['email'] . ")\n\n";

        $prompt .= "**IMPORTANT SECURITY RULES:**\n";
        $prompt .= "- You can access and discuss THIS USER'S information ONLY\n";
        $prompt .= "- NEVER share information about other users or customers\n";
        $prompt .= "- You can help with: their orders, tickets, account info, product recommendations, service requests\n";
        $prompt .= "- Guide users to specific pages when needed (e.g., /support.php for tickets, /dashboard-customer.php for account)\n";
        $prompt .= "- Be helpful and personalized for this specific user\n\n";

        // Get user's data
        $user_data = getUserData($pdo, $user['id'], $user['email']);

        // Check if user is admin by role
        $is_admin = is_user_admin();

        $prompt .= "**THIS USER'S INFORMATION:**\n";
        $prompt .= "- Name: " . $user['full_name'] . "\n";
        $prompt .= "- Email: " . $user['email'] . "\n";
        $prompt .= "- Account Type: " . ($is_admin ? 'Admin' : 'Customer') . "\n";

        if (!empty($user_data['orders'])) {
            $prompt .= "\n**USER'S RECENT ORDERS:**\n";
            foreach ($user_data['orders'] as $order) {
                $prompt .= "- Order #{$order['order_number']} - {$order['status']} - {$order['total']} - {$order['date']}\n";
            }
        } else {
            $prompt .= "\n- User has no orders yet\n";
        }

        if (!empty($user_data['tickets'])) {
            $prompt .= "\n**USER'S SUPPORT TICKETS:**\n";
            foreach ($user_data['tickets'] as $ticket) {
                $prompt .= "- Ticket #{$ticket['ticket_number']} - {$ticket['subject']} - {$ticket['status']} - {$ticket['date']}\n";
            }
        } else {
            $prompt .= "\n- User has no support tickets\n";
        }

        $prompt .= "\n**AVAILABLE ACTIONS FOR AUTHENTICATED USERS:**\n";
        $prompt .= "- View orders at: /dashboard-customer.php or specific order at /order-view.php?id=ORDER_ID\n";
        $prompt .= "- Create support ticket at: /support.php?create=1\n";
        $prompt .= "- View tickets at: /support.php\n";
        $prompt .= "- View messages at: /my-messages.php\n";
        $prompt .= "- Browse products at: /products.php\n";
        $prompt .= "- Request services at: /services.php\n\n";

    } else {
        // GUEST MODE - Limited to products and services only
        $prompt .= "**AUTHENTICATION STATUS:** Guest user (not logged in)\n\n";

        $prompt .= "**IMPORTANT RESTRICTIONS FOR GUESTS:**\n";
        $prompt .= "- ONLY discuss LUMIRA products and services\n";
        $prompt .= "- DO NOT access or discuss any customer data, orders, or tickets\n";
        $prompt .= "- Encourage users to create an account for personalized support\n";
        $prompt .= "- Guide to /register.php for account creation or /login.php to sign in\n";
        $prompt .= "- Focus on helping them find the right products and services\n\n";

        $prompt .= "**AVAILABLE ACTIONS FOR GUESTS:**\n";
        $prompt .= "- Browse products at: /products.php\n";
        $prompt .= "- View services at: /services.php\n";
        $prompt .= "- Create account at: /register.php\n";
        $prompt .= "- Login at: /login.php\n\n";
    }

    // Add products and services (available to all users)
    $products = getProducts($pdo);
    $services = getServices($pdo);

    $prompt .= "**LUMIRA PRODUCTS:**\n";
    foreach ($products as $p) {
        $prompt .= "- {$p['name']}: {$p['price']}\n";
        $prompt .= "  Description: {$p['description']}\n";
        if (!empty($p['sku'])) {
            $prompt .= "  SKU: {$p['sku']}\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "\n**LUMIRA SERVICES:**\n";
    foreach ($services as $s) {
        $prompt .= "- {$s['name']}\n";
        $prompt .= "  Description: {$s['description']}\n\n";
    }

    $prompt .= "\n**GENERAL INSTRUCTIONS:**\n";
    $prompt .= "- Be professional, friendly, and helpful\n";
    $prompt .= "- Provide accurate information based ONLY on the data provided\n";
    $prompt .= "- If you don't know something, say so and suggest contacting support\n";
    $prompt .= "- NEVER make up information or discuss topics unrelated to LUMIRA\n";
    $prompt .= "- When providing links, use the exact URLs shown above\n";
    $prompt .= "- Keep responses concise and helpful\n";

    return $prompt;
}

/**
 * Get user's orders and tickets
 */
function getUserData($pdo, $user_id, $user_email) {
    $data = ['orders' => [], 'tickets' => []];

    // Get recent orders (last 5)
    try {
        $stmt = $pdo->prepare('
            SELECT id, order_number, status, total_cents, created_at
            FROM orders
            WHERE customer_email = ?
            ORDER BY created_at DESC
            LIMIT 5
        ');
        $stmt->execute([$user_email]);
        $orders = $stmt->fetchAll();

        foreach ($orders as $order) {
            $data['orders'][] = [
                'order_number' => $order['order_number'],
                'status' => ucfirst($order['status']),
                'total' => '$' . number_format($order['total_cents'] / 100, 2),
                'date' => date('M j, Y', strtotime($order['created_at']))
            ];
        }
    } catch (PDOException $e) {
        error_log('Error fetching orders: ' . $e->getMessage());
    }

    // Get recent tickets (last 5)
    try {
        $stmt = $pdo->prepare('
            SELECT t.id, t.ticket_number, t.subject, ts.name as status_name, t.created_at
            FROM tickets t
            LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
            WHERE t.requester_id = ?
            ORDER BY t.created_at DESC
            LIMIT 5
        ');
        $stmt->execute([$user_id]);
        $tickets = $stmt->fetchAll();

        foreach ($tickets as $ticket) {
            $data['tickets'][] = [
                'ticket_number' => $ticket['ticket_number'],
                'subject' => $ticket['subject'],
                'status' => $ticket['status_name'] ?? 'Unknown',
                'date' => date('M j, Y', strtotime($ticket['created_at']))
            ];
        }
    } catch (PDOException $e) {
        error_log('Error fetching tickets: ' . $e->getMessage());
    }

    return $data;
}

/**
 * Get products
 */
function getProducts($pdo) {
    try {
        $stmt = $pdo->query('
            SELECT name, description, price_cents, sku, is_active
            FROM products
            WHERE is_active = TRUE
            ORDER BY created_at DESC
            LIMIT 20
        ');
        $products = $stmt->fetchAll();

        return array_map(function($p) {
            return [
                'name' => $p['name'],
                'description' => substr($p['description'], 0, 150),
                'price' => '$' . number_format($p['price_cents'] / 100, 2),
                'sku' => $p['sku']
            ];
        }, $products);
    } catch (PDOException $e) {
        error_log('Error fetching products: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get services
 */
function getServices($pdo) {
    try {
        $stmt = $pdo->query('
            SELECT name, description
            FROM services
            ORDER BY name
            LIMIT 20
        ');
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching services: ' . $e->getMessage());
        return [];
    }
}

/**
 * Call AI API
 */
function callAiApi($messages) {
    $api_url = 'http://139.182.185.119.nip.io';  // Changed to HTTP
    $api_key = 'sk-efd8502cffc74ba0bca909283a0c2e71';
    $model = 'granite4:latest';

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 512,
        'stream' => false
    ];

    // Parse URL - use HTTP instead of HTTPS to avoid SSL requirement
    $url_parts = parse_url($api_url);
    $host = $url_parts['host'];
    $port = 80; // HTTP (not HTTPS)
    $path = '/api/chat/completions';

    $payload_json = json_encode($payload);

    // Build HTTP request
    $http_request = "POST {$path} HTTP/1.1\r\n";
    $http_request .= "Host: {$host}\r\n";
    $http_request .= "Authorization: Bearer {$api_key}\r\n";
    $http_request .= "Content-Type: application/json\r\n";
    $http_request .= "Content-Length: " . strlen($payload_json) . "\r\n";
    $http_request .= "Connection: close\r\n";
    $http_request .= "\r\n";
    $http_request .= $payload_json;

    // Use fsockopen to connect (no SSL)
    $fp = @fsockopen($host, $port, $errno, $errstr, 30);

    if (!$fp) {
        return ['success' => false, 'error' => "Connection failed: {$errstr} ({$errno})"];
    }

    // Send request
    fwrite($fp, $http_request);

    // Read response
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 1024);
    }
    fclose($fp);

    // Split headers and body
    $parts = explode("\r\n\r\n", $response, 2);
    if (count($parts) < 2) {
        return ['success' => false, 'error' => 'Invalid response format'];
    }

    $headers = $parts[0];
    $body = $parts[1];

    // Check status code
    if (!preg_match('/HTTP\/[\d.]+\s+(\d+)/', $headers, $matches)) {
        return ['success' => false, 'error' => 'Could not parse status code'];
    }

    $status_code = (int)$matches[1];
    if ($status_code !== 200) {
        return ['success' => false, 'error' => "API returned HTTP {$status_code}"];
    }

    // Parse JSON response
    $data = json_decode($body, true);

    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => 'Invalid API response format'];
    }

    return [
        'success' => true,
        'message' => $data['choices'][0]['message']['content']
    ];
}
