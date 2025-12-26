<?php
/**
 * LUMIRA Chat API Handler - n8n Integration
 * Routes chat requests through n8n workflow
 */

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';

// Increase execution time for AI API calls
set_time_limit(120);
ini_set('max_execution_time', '120');

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. Missing message.']);
    exit;
}

$userMessage = trim($input['message']);
$chatHistory = $input['history'] ?? [];
$sessionId = session_id();

// Open WebUI Configuration
$OPENWEBUI_API_URL = 'https://139.182.185.119.nip.io/api/chat/completions';
$OPENWEBUI_API_KEY = 'sk-efd8502cffc74ba0bca909283a0c2e71';
$AI_MODEL = 'llama3.1:8b'; // You can change this to any available model

try {
    // Load LUMIRA business data from database
    $pdo = get_db();

    // Get active products
    $stmt = $pdo->query('
        SELECT name, description, price_cents, sku
        FROM products
        WHERE is_active = true
        ORDER BY name
    ');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active services
    $stmt = $pdo->query('
        SELECT name, description
        FROM services
        WHERE is_active = true
        ORDER BY name
    ');
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user info if logged in
    session_start();
    $user = get_logged_in_user();
    $userContext = '';

    if ($user) {
        $userContext = "User: {$user['full_name']} ({$user['email']}) - Role: {$user['role_name']}";
    }

    // Build system prompt with business context
    $systemPrompt = "You are LUMIRA's AI customer support assistant. LUMIRA is a professional IT solutions company.\n\n";
    $systemPrompt .= "LUMIRA Products:\n";
    foreach ($products as $product) {
        $price = number_format($product['price_cents'] / 100, 2);
        $systemPrompt .= "- {$product['name']}: {$product['description']} (SKU: {$product['sku']}, Price: \${price})\n";
    }
    $systemPrompt .= "\nLUMIRA Services:\n";
    foreach ($services as $service) {
        $systemPrompt .= "- {$service['name']}: {$service['description']}\n";
    }
    if ($userContext) {
        $systemPrompt .= "\nCurrent " . $userContext;
    }
    $systemPrompt .= "\n\nProvide helpful, professional support. If the user needs technical assistance that requires a ticket, suggest creating one.";

    // Prepare messages for AI
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    // Add chat history
    foreach ($chatHistory as $msg) {
        $messages[] = [
            'role' => $msg['role'] ?? 'user',
            'content' => $msg['content'] ?? $msg['message'] ?? ''
        ];
    }

    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    // Prepare OpenAI-compatible payload
    $payload = [
        'model' => $AI_MODEL,
        'messages' => $messages,
        'stream' => false,
        'temperature' => 0.7
    ];

    // Call Open WebUI API
    $ch = curl_init($OPENWEBUI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $OPENWEBUI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For self-signed cert

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Connection error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception("AI API Error (HTTP {$httpCode}): Failed to get response");
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response from AI API');
    }

    // Extract AI response
    $aiMessage = $data['choices'][0]['message']['content'];

    // Simple detection if user needs a ticket (you can enhance this)
    $needsTicket = false;
    if (stripos($aiMessage, 'ticket') !== false ||
        stripos($aiMessage, 'technical support') !== false) {
        $needsTicket = true;
    }

    $data = [
        'message' => $aiMessage,
        'action' => $needsTicket ? 'suggest_ticket' : null
    ];

    // Check if n8n suggests creating a ticket
    if (isset($data['action']) && $data['action'] === 'create_ticket') {
        // Auto-create ticket based on AI analysis
        if ($user) {
            $category_id = $data['suggested_category_id'] ?? 1;
            $priority_id = $data['suggested_priority_id'] ?? 3;
            $subject = $data['suggested_subject'] ?? 'Chat support request';
            $description = "Conversation:\n\n" . $userMessage . "\n\nAI Analysis: " . ($data['analysis'] ?? '');

            // Generate ticket number
            $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            // Get default status
            $statusStmt = $pdo->prepare('SELECT id FROM ticket_statuses WHERE name = ? LIMIT 1');
            $statusStmt->execute(['New']);
            $status_id = $statusStmt->fetchColumn() ?: 1;

            // Create ticket
            $stmt = $pdo->prepare('
                INSERT INTO tickets (
                    ticket_number, requester_id, category_id, priority_id, status_id,
                    subject, description, source, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, \'chat\', NOW(), NOW())
                RETURNING id
            ');
            $stmt->execute([
                $ticket_number,
                $user['id'],
                $category_id,
                $priority_id,
                $status_id,
                $subject,
                $description
            ]);
            $ticket_id = $stmt->fetchColumn();

            // Add ticket info to response
            $data['ticket_created'] = true;
            $data['ticket_number'] = $ticket_number;
            $data['ticket_id'] = $ticket_id;
        }
    }

    // Return successful response
    echo json_encode([
        'success' => true,
        'message' => $data['message'],
        'metadata' => [
            'action' => $data['action'] ?? null,
            'confidence' => $data['confidence'] ?? null,
            'ticket_created' => $data['ticket_created'] ?? false,
            'ticket_number' => $data['ticket_number'] ?? null
        ]
    ]);

} catch (PDOException $e) {
    error_log('Chat API DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log('Chat API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
