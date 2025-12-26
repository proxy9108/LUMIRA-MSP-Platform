<?php
/**
 * PayPal Capture Order API
 * Captures the payment and creates order in database
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/email.php';

session_start();

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['orderID'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing orderID']);
    exit;
}

$paypalOrderID = $input['orderID'];

try {
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

    // Capture the order
    $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders/' . $paypalOrderID . '/capture');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
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
        error_log('PayPal Capture Error: ' . $response);
        throw new Exception('Failed to capture PayPal payment');
    }

    $captureData = json_decode($response, true);

    // Verify payment was successful
    if ($captureData['status'] !== 'COMPLETED') {
        throw new Exception('Payment not completed');
    }

    // Get PayPal customer info
    $payer = $captureData['payer'];
    $paypalEmail = $payer['email_address'];
    $paypalName = $payer['name']['given_name'] . ' ' . $payer['name']['surname'];

    // Get shipping address if available
    $shipping = $captureData['purchase_units'][0]['shipping'] ?? null;
    if ($shipping) {
        $address = $shipping['address'];
        $customerAddress = ($address['address_line_1'] ?? '') . "\n" .
                          ($address['admin_area_2'] ?? '') . ', ' .
                          ($address['admin_area_1'] ?? '') . ' ' .
                          ($address['postal_code'] ?? '');
    } else {
        $customerAddress = 'N/A (PayPal payment)';
    }

    $customerPhone = $payer['phone']['phone_number']['national_number'] ?? 'N/A';

    // Get database connection and find existing pending order
    $pdo = get_db();
    $pdo->beginTransaction();

    // Look up the existing pending order by PayPal order ID
    $stmt = $pdo->prepare('SELECT id, order_number, customer_email, customer_name FROM orders WHERE payment_id = ? AND status = ?');
    $stmt->execute([$paypalOrderID, 'pending_payment']);
    $existing_order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_order) {
        throw new Exception('Order not found for PayPal ID: ' . $paypalOrderID);
    }

    $order_id = $existing_order['id'];
    $order_number = $existing_order['order_number'];
    $customerEmail = $existing_order['customer_email'];
    $customerName = $existing_order['customer_name'];

    // Add PayPal email reference if different
    if ($paypalEmail !== $customerEmail) {
        $customerAddress .= "\n[PayPal: " . $paypalEmail . "]";
    }

    // Update order with PayPal shipping info and mark as paid
    $stmt = $pdo->prepare('
        UPDATE orders
        SET customer_phone = ?,
            customer_address = ?,
            status = ?,
            updated_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute([
        $customerPhone,
        $customerAddress,
        'paid',
        $order_id
    ]);

    // Get order items for email
    $stmt = $pdo->prepare('
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ');
    $stmt->execute([$order_id]);
    $order_items_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $order_items_data = [];
    foreach ($order_items_db as $item) {
        $order_items_data[] = [
            'product_name' => $item['product_name'],
            'qty' => $item['qty'],
            'price_cents' => $item['price_cents']
        ];
    }

    // Handle subscription services from cart
    $cart_items = cart_get_items($pdo);
    $stmt_subscription = $pdo->prepare('
        INSERT INTO subscriptions (
            customer_email, customer_name, service_id,
            price_cents, billing_cycle, status, start_date, next_billing_date,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW() + INTERVAL \'1 month\', NOW(), NOW())
    ');
    $stmt_order_item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, price_cents) VALUES (?, ?, ?, ?)');

    foreach ($cart_items as $item) {
        if ($item['type'] === 'service' && $item['item']['service_category'] === 'subscription') {
            // Create active subscription
            $stmt_subscription->execute([
                $customerEmail,
                $customerName,
                $item['item']['id'],
                $item['item']['price_cents'],
                $item['item']['billing_type'],
                'active'
            ]);

            // ALSO add to order_items so it shows in the order history
            $stmt_order_item->execute([
                $order_id,
                $item['item']['id'],
                1, // Subscriptions always qty 1
                $item['item']['price_cents']
            ]);

            // Add to order items data for email
            $order_items_data[] = [
                'product_name' => $item['item']['name'] . ' (Subscription - Monthly)',
                'qty' => 1,
                'price_cents' => $item['item']['price_cents']
            ];
        }
    }

    $pdo->commit();

    // Send confirmation email to customer's LUMIRA email
    $order_data = [
        'id' => $order_id,
        'order_number' => $order_number,
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'customer_address' => $customerAddress,
        'created_at' => date('Y-m-d H:i:s')
    ];

    send_order_confirmation($order_data, $order_items_data);

    // If PayPal email is different, also send to PayPal email
    if ($paypalEmail !== $customerEmail) {
        $paypal_order_data = $order_data;
        $paypal_order_data['customer_email'] = $paypalEmail;
        $paypal_order_data['customer_name'] = $paypalName;
        send_order_confirmation($paypal_order_data, $order_items_data);
    }

    // Clear cart
    cart_clear();

    // Return success
    echo json_encode([
        'success' => true,
        'orderNumber' => $order_number,
        'orderID' => $order_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PayPal Capture Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
