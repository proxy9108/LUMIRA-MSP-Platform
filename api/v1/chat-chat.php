<?php
/**
 * LUMIRA Chat API Handler
 * Handles chat requests with LUMIRA-specific context
 */

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message']) || !isset($input['model'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. Missing message or model.']);
    exit;
}

$userMessage = trim($input['message']);
$model = $input['model'];
$chatHistory = $input['history'] ?? [];

// API Configuration
$API_URL = 'https://139.182.185.119.nip.io/api/chat/completions';
$API_KEY = 'sk-efd8502cffc74ba0bca909283a0c2e71';

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

    // Build LUMIRA context
    $productsText = "**LUMIRA PRODUCTS:**\n";
    foreach ($products as $product) {
        $price = number_format($product['price_cents'] / 100, 2);
        $productsText .= "- {$product['name']}: \${$price}\n";
        $productsText .= "  Description: {$product['description']}\n";
        if ($product['sku']) {
            $productsText .= "  SKU: {$product['sku']}\n";
        }
        $productsText .= "\n";
    }

    $servicesText = "**LUMIRA SERVICES:**\n";
    foreach ($services as $service) {
        $servicesText .= "- {$service['name']}\n";
        $servicesText .= "  Description: {$service['description']}\n\n";
    }

    // Create system prompt with LUMIRA context
    $systemPrompt = <<<PROMPT
You are a professional customer service AI assistant for LUMIRA, an IT services and products company.

**IMPORTANT INSTRUCTIONS:**
- ONLY answer questions about LUMIRA's products, services, and business
- If asked about topics unrelated to LUMIRA, politely redirect to LUMIRA's offerings
- Be professional, friendly, and helpful
- Provide accurate information based ONLY on the data below
- Do NOT make up prices, features, or services not listed
- If you don't know something about LUMIRA, say so and suggest contacting support

**COMPANY INFORMATION:**
Company Name: LUMIRA
Tagline: Professional IT Solutions & Quality Products
Website: http://10.0.1.100

{$productsText}

{$servicesText}

**CONTACT & SUPPORT:**
- Customers can browse products at: http://10.0.1.100/products.php
- Customers can request services at: http://10.0.1.100/services.php
- For support, customers can submit tickets through the website
- Professional IT support available for all products and services

**YOUR ROLE:**
Help customers understand what LUMIRA offers, answer questions about products and services, and guide them to make purchases or service requests. Always stay focused on LUMIRA's business.
PROMPT;

    // Build messages array with system context
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    // Add chat history
    foreach ($chatHistory as $msg) {
        $messages[] = ['role' => 'user', 'content' => $msg['user']];
        if (!empty($msg['assistant'])) {
            $messages[] = ['role' => 'assistant', 'content' => $msg['assistant']];
        }
    }

    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    // Call OpenWebUI API
    $ch = curl_init($API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 512,  // Reduced for faster responses
        'stream' => false
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);  // Reduced timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);  // Prevent signal issues

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    // Handle timeout specifically
    if ($curlErrno === CURLE_OPERATION_TIMEDOUT || $curlErrno === 28) {
        echo json_encode([
            'success' => false,
            'error' => 'AI service is taking too long to respond. Please try again with a simpler question or select the "Fast Response" model.'
        ]);
        exit;
    }

    if ($curlError) {
        throw new Exception('Connection error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['detail'] ?? $errorData['error'] ?? 'Unknown error';
        throw new Exception("API Error (HTTP {$httpCode}): {$errorMsg}");
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response from AI service');
    }

    // Return successful response
    echo json_encode([
        'success' => true,
        'message' => $data['choices'][0]['message']['content']
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
