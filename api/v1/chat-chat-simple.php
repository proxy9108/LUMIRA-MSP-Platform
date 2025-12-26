<?php
/**
 * Simple Chat API for Testing
 */

header('Content-Type: application/json');

$API_URL = 'https://139.182.185.119/api/chat/completions';  // Use IP directly
$API_KEY = 'sk-efd8502cffc74ba0bca909283a0c2e71';

// Get request
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? 'Hello';
$model = $input['model'] ?? 'granite4:latest';

// Simple system prompt
$systemPrompt = "You are a helpful assistant for LUMIRA IT company. Keep responses brief.";

// Build messages
$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $message]
];

// Call API
$ch = curl_init($API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => $messages,
    'temperature' => 0.7,
    'max_tokens' => 200,
    'stream' => false
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $API_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => "HTTP $httpCode", 'raw' => $response]);
    exit;
}

$data = json_decode($response, true);
$msg = $data['choices'][0]['message']['content'] ?? 'No response';

echo json_encode(['success' => true, 'message' => $msg]);
