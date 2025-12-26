<?php
/**
 * PayPal Return Handler - Captures payment after user approves
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/config/functions.php';
require_once __DIR__ . '/../../app/config/email.php';

session_start();

// Check for PayPal token
if (!isset($_GET['token'])) {
    die('Error: Missing PayPal token. <a href="/checkout.php">Return to checkout</a>');
}

$paypalOrderID = $_GET['token'];

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
        throw new Exception('Failed to authenticate with PayPal');
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
        throw new Exception('Failed to capture payment');
    }

    $captureData = json_decode($response, true);

    // Verify payment completed
    if ($captureData['status'] !== 'COMPLETED') {
        throw new Exception('Payment not completed');
    }

    // Get customer info
    $payer = $captureData['payer'];
    $paypalEmail = $payer['email_address'];
    $paypalName = $payer['name']['given_name'] . ' ' . $payer['name']['surname'];

    // Use logged-in customer's LUMIRA email if available, otherwise use PayPal email
    error_log('PayPal Return: Session email = ' . ($_SESSION['paypal_customer_email'] ?? 'NOT SET'));
    error_log('PayPal Return: PayPal email = ' . $paypalEmail);
    $customerEmail = $_SESSION['paypal_customer_email'] ?? $paypalEmail;
    $customerName = $_SESSION['paypal_customer_name'] ?? $paypalName;
    error_log('PayPal Return: Using customer email = ' . $customerEmail);

    // Get shipping address
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

    // Add PayPal email reference if different from customer email
    if ($paypalEmail !== $customerEmail) {
        $customerAddress .= "\n[PayPal: " . $paypalEmail . "]";
    }

    $customerPhone = $payer['phone']['phone_number']['national_number'] ?? 'N/A';

    // Get database connection
    $pdo = get_db();
    $pdo->beginTransaction();

    // Look up the existing pending order by PayPal order ID
    $stmt = $pdo->prepare('SELECT id, order_number, customer_email, customer_name FROM orders WHERE payment_id = ? AND status = ? FOR UPDATE');
    $stmt->execute([$paypalOrderID, 'pending_payment']);
    $existing_order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_order) {
        throw new Exception('Order not found. Please contact support with PayPal transaction ID: ' . $paypalOrderID);
    }

    $order_id = $existing_order['id'];
    $order_number = $existing_order['order_number'];

    // Use the customer email from the database (which came from the logged-in user)
    $customerEmail = $existing_order['customer_email'];
    $customerName = $existing_order['customer_name'];

    // Update the order with PayPal shipping info and mark as paid
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

    // Send confirmation email to primary customer email (LUMIRA account)
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

    // If PayPal email is different, also send confirmation to PayPal email
    if ($paypalEmail !== $customerEmail) {
        $paypal_order_data = $order_data;
        $paypal_order_data['customer_email'] = $paypalEmail;
        $paypal_order_data['customer_name'] = $paypalName;
        send_order_confirmation($paypal_order_data, $order_items_data);
    }

    $pdo->commit();

    // Clear cart
    cart_clear();

    // Clear session
    unset($_SESSION['paypal_order_id']);
    unset($_SESSION['paypal_customer_email']);
    unset($_SESSION['paypal_customer_name']);

    // Redirect to success page
    header('Location: /checkout.php?paypal_success=' . urlencode($order_number));
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PayPal Return Error: ' . $e->getMessage());
    die('Payment Error: ' . htmlspecialchars($e->getMessage()) . '<br><a href="/checkout.php">Return to checkout</a>');
}
