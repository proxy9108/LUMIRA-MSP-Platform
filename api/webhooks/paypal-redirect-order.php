<?php
/**
 * PayPal Redirect Order - Creates order and redirects to PayPal
 * Alternative to JS SDK for SSL certificate issues
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/config/functions.php';

session_start();

// Get logged-in user info
$logged_in_user = get_logged_in_user();

try {
    // Get cart items and total
    $pdo = get_db();
    $cart_items = cart_get_items($pdo);
    $cart_total = cart_get_total($cart_items);

    if (empty($cart_items)) {
        die('Error: Cart is empty. <a href="/cart.php">Return to cart</a>');
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
        throw new Exception('Failed to authenticate with PayPal');
    }

    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'];

    // Build order items for PayPal
    $items = [];
    foreach ($cart_items as $item) {
        $items[] = [
            'name' => $item['product']['name'],
            'description' => substr($item['product']['description'], 0, 127),
            'unit_amount' => [
                'currency_code' => 'USD',
                'value' => number_format($item['product']['price_cents'] / 100, 2, '.', '')
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
            'return_url' => SITE_URL . '/api/paypal-return.php',
            'cancel_url' => SITE_URL . '/checkout.php?paypal_cancel=1'
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

    // Create pending order in database with logged-in user's info
    try {
        $pdo->beginTransaction();

        $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $subtotal_cents = $cart_total;
        $tax_cents = 0;
        $total_cents = $subtotal_cents + $tax_cents;

        // Get customer info from logged-in user or use placeholder
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

        // Insert order items
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, price_cents) VALUES (?, ?, ?, ?)');
        foreach ($cart_items as $item) {
            $stmt->execute([
                $db_order_id,
                $item['product']['id'],
                $item['qty'],
                $item['product']['price_cents']
            ]);
        }

        $pdo->commit();
    } catch (Exception $db_error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('PayPal: Database error creating pending order: ' . $db_error->getMessage());
        throw new Exception('Failed to create order. Please try again.');
    }

    // Store order ID in session
    $_SESSION['paypal_order_id'] = $paypal_order_id;
    $_SESSION['db_order_id'] = $db_order_id;

    // Get approval URL and redirect
    foreach ($orderResponse['links'] as $link) {
        if ($link['rel'] === 'approve') {
            header('Location: ' . $link['href']);
            exit;
        }
    }

    throw new Exception('No approval URL found in PayPal response');

} catch (Exception $e) {
    error_log('PayPal Redirect Error: ' . $e->getMessage());
    die('PayPal Error: ' . htmlspecialchars($e->getMessage()) . '<br><a href="/checkout.php">Return to checkout</a>');
}
