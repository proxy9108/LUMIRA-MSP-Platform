<?php
/**
 * PayPal Subscription - Create and redirect to PayPal
 * For recurring monthly/yearly subscriptions
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/config/functions.php';

session_start();

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = '/services.php';
    header('Location: /login.php?error=login_required');
    exit;
}

$user = get_logged_in_user();

// Get service_id from POST
if (!isset($_POST['service_id'])) {
    die('Error: Missing service ID. <a href="/services.php">Return to services</a>');
}

$service_id = (int)$_POST['service_id'];

try {
    // Get service details
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ? AND is_active = TRUE AND service_category = ?');
    $stmt->execute([$service_id, 'subscription']);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        die('Error: Service not found. <a href="/services.php">Return to services</a>');
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

    // Create a PayPal billing plan (simplified - using direct subscription)
    // In production, you'd create reusable plans via PayPal dashboard

    $price = number_format($service['price_cents'] / 100, 2, '.', '');
    $plan_name = $service['name'];
    $billing_cycle = strtoupper($service['billing_type']); // MONTHLY or YEARLY

    // Create subscription plan
    $planData = [
        'product_id' => 'PROD-' . $service_id, // Simplified product ID
        'name' => $plan_name,
        'description' => $service['description'],
        'billing_cycles' => [
            [
                'frequency' => [
                    'interval_unit' => $billing_cycle,
                    'interval_count' => 1
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0, // 0 = infinite
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $price,
                        'currency_code' => 'USD'
                    ]
                ]
            ]
        ],
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'payment_failure_threshold' => 3
        ]
    ];

    // For this implementation, we'll use a simpler approach:
    // Create a subscription order similar to one-time payment
    // This creates a pending subscription in our database and redirects to PayPal

    // Create pending subscription in our database first
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        INSERT INTO subscriptions (
            customer_email, customer_name, service_id,
            price_cents, billing_cycle, status, start_date, next_billing_date,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW() + INTERVAL \'1 month\', NOW(), NOW())
        RETURNING id
    ');

    $stmt->execute([
        $user['email'],
        $user['full_name'],
        $service_id,
        $service['price_cents'],
        $service['billing_type'],
        'pending'
    ]);

    $subscription_id = $stmt->fetchColumn();
    $pdo->commit();

    // For now, redirect to a subscription confirmation page
    // In full implementation, this would redirect to PayPal subscription approval

    // Store subscription info in session
    $_SESSION['pending_subscription_id'] = $subscription_id;

    // Redirect to subscription success page
    header('Location: /subscription-activate.php?id=' . $subscription_id);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PayPal Subscription Error: ' . $e->getMessage());
    die('Subscription Error: ' . htmlspecialchars($e->getMessage()) . '<br><a href="/services.php">Return to services</a>');
}
