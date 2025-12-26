<?php
/**
 * PayPal Create Order API
 * Creates a PayPal order for checkout
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

session_start();

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get cart items and total
    $pdo = get_db();
    $cart_items = cart_get_items($pdo);
    $cart_total = cart_get_total($cart_items);

    if (empty($cart_items)) {
        throw new Exception('Cart is empty');
    }

    // Get PayPal access token
    $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to get PayPal access token');
    }

    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'];

    // Build order items for PayPal
    $items = [];
    foreach ($cart_items as $item) {
        $itemName = $item['item']['name'];
        if ($item['type'] === 'service') {
            $itemName .= ' (Service)';
            if ($item['item']['service_category'] === 'subscription') {
                $itemName .= ' - Monthly';
            }
        }

        $items[] = [
            'name' => $itemName,
            'description' => substr($item['item']['description'], 0, 127),
            'unit_amount' => [
                'currency_code' => 'USD',
                'value' => number_format($item['item']['price_cents'] / 100, 2, '.', '')
            ],
            'quantity' => (string)$item['qty']
        ];
    }

    // Calculate total
    $totalAmount = number_format($cart_total / 100, 2, '.', '');

    // Create PayPal order
    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $totalAmount,
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => 'USD',
                            'value' => $totalAmount
                        ]
                    ]
                ],
                'items' => $items
            ]
        ],
        'application_context' => [
            'brand_name' => SITE_NAME,
            'landing_page' => 'BILLING',
            'user_action' => 'PAY_NOW',
            'return_url' => SITE_URL . '/checkout.php?paypal=success',
            'cancel_url' => SITE_URL . '/checkout.php?paypal=cancel'
        ]
    ];

    $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 201) {
        error_log('PayPal Create Order Error: ' . $response);
        throw new Exception('Failed to create PayPal order');
    }

    $orderResponse = json_decode($response, true);
    $paypal_order_id = $orderResponse['id'];

    // Create pending order in database with logged-in user's email
    $logged_in_user = get_logged_in_user();

    $pdo->beginTransaction();

    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $subtotal_cents = $cart_total;
    $tax_cents = 0;
    $total_cents = $subtotal_cents + $tax_cents;

    if ($logged_in_user) {
        $customer_email = $logged_in_user['email'];
        $customer_name = $logged_in_user['full_name'];
    } else {
        $customer_email = 'pending@paypal.com';
        $customer_name = 'PayPal Customer';
    }

    $stmt = $pdo->prepare('
        INSERT INTO orders (
            order_number, customer_name, customer_email, customer_phone, customer_address,
            subtotal_cents, tax_cents, total_cents, status, payment_method, payment_id,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        RETURNING id
    ');
    $stmt->execute([
        $order_number,
        $customer_name,
        $customer_email,
        'Pending',
        'PayPal Order - Address will be updated after payment',
        $subtotal_cents,
        $tax_cents,
        $total_cents,
        'pending_payment',
        'paypal',
        $paypal_order_id
    ]);
    $db_order_id = $stmt->fetchColumn();

    // Insert order items (products and one-time services)
    // Note: Subscription services will be created when payment is captured
    $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, price_cents) VALUES (?, ?, ?, ?)');
    foreach ($cart_items as $item) {
        // Only add products and one-time services to order_items
        // Subscription services will be handled separately after payment confirmation
        if ($item['type'] === 'product' ||
            ($item['type'] === 'service' && $item['item']['service_category'] !== 'subscription')) {
            $stmt->execute([
                $db_order_id,
                $item['item']['id'],
                $item['qty'],
                $item['item']['price_cents']
            ]);
        }
    }

    $pdo->commit();

    // Return order ID to client
    echo json_encode([
        'success' => true,
        'orderID' => $paypal_order_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('PayPal Create Order Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
