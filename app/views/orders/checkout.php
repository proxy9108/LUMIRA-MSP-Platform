<?php
/**
 * LUMIRA - Checkout Page
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';
require_once __DIR__ . '/../../../app/config/email.php';

session_start();

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

$error = '';
$order_id = null;
$paypal_success = false;
$order_number_display = null;

// Check for PayPal success
if (isset($_GET['paypal_success'])) {
    $paypal_success = true;
    $order_number_display = sanitize($_GET['paypal_success']);
    $order_id = 1; // Set to non-null to show confirmation
}

// Get database connection and check cart (skip cart check if returning from PayPal success)
try {
    $pdo = get_db();

    if (!$paypal_success) {
        $cart_items = cart_get_items($pdo);
        $cart_total = cart_get_total($cart_items);

        if (empty($cart_items)) {
            redirect('/cart.php');
        }
    }
} catch (PDOException $e) {
    $error = 'Unable to process checkout. Please try again later.';
    $cart_items = [];
    $cart_total = 0;
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$order_id) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Validate inputs
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $address_street = trim($_POST['address_street'] ?? '');
        $address_city = trim($_POST['address_city'] ?? '');
        $address_state = trim($_POST['address_state'] ?? '');
        $address_zip = trim($_POST['address_zip'] ?? '');

        // Combine address fields
        $customer_address = $address_street . "\n" . $address_city . ', ' . $address_state . ' ' . $address_zip;

        if (empty($customer_name)) {
            $error = 'Please enter your name.';
        } elseif (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (empty($customer_phone)) {
            $error = 'Please enter your phone number.';
        } elseif (empty($address_street) || empty($address_city) || empty($address_state) || empty($address_zip)) {
            $error = 'Please enter your complete shipping address.';
        } else {
            // Process order
            try {
                $pdo->beginTransaction();

                // Generate unique order number
                $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

                // Calculate totals
                $subtotal_cents = $cart_total;
                $tax_cents = 0; // Add tax calculation if needed
                $total_cents = $subtotal_cents + $tax_cents;

                // Insert order
                $stmt = $pdo->prepare('
                    INSERT INTO orders (
                        order_number, customer_name, customer_email, customer_phone, customer_address,
                        subtotal_cents, tax_cents, total_cents, status,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    RETURNING id
                ');
                $stmt->execute([
                    $order_number,
                    $customer_name,
                    $customer_email,
                    $customer_phone,
                    $customer_address,
                    $subtotal_cents,
                    $tax_cents,
                    $total_cents,
                    'pending'
                ]);
                $order_id = $stmt->fetchColumn();

                // Insert order items and handle subscriptions
                $stmt_order_item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, price_cents) VALUES (?, ?, ?, ?)');
                $stmt_subscription = $pdo->prepare('
                    INSERT INTO subscriptions (
                        customer_email, customer_name, service_id,
                        price_cents, billing_cycle, status, start_date, next_billing_date,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW() + INTERVAL \'1 month\', NOW(), NOW())
                ');
                $order_items_data = [];
                $subscription_items_data = [];

                foreach ($cart_items as $item) {
                    if ($item['type'] === 'product') {
                        // Regular product - add to order items
                        $stmt_order_item->execute([
                            $order_id,
                            $item['item']['id'],
                            $item['qty'],
                            $item['item']['price_cents']
                        ]);

                        $order_items_data[] = [
                            'product_name' => $item['item']['name'],
                            'qty' => $item['qty'],
                            'price_cents' => $item['item']['price_cents']
                        ];

                    } elseif ($item['type'] === 'service') {
                        if ($item['item']['service_category'] === 'subscription') {
                            // Subscription service - create subscription record
                            $stmt_subscription->execute([
                                $customer_email,
                                $customer_name,
                                $item['item']['id'],
                                $item['item']['price_cents'],
                                $item['item']['billing_type'],
                                'active'
                            ]);

                            // ALSO add to order_items so it shows in the order history
                            $stmt_order_item->execute([
                                $order_id,
                                $item['item']['id'],
                                $item['qty'],
                                $item['item']['price_cents']
                            ]);

                            $subscription_items_data[] = [
                                'service_name' => $item['item']['name'],
                                'price_cents' => $item['item']['price_cents'],
                                'billing_type' => $item['item']['billing_type']
                            ];

                            $order_items_data[] = [
                                'product_name' => $item['item']['name'] . ' (Subscription)',
                                'qty' => $item['qty'],
                                'price_cents' => $item['item']['price_cents']
                            ];
                        } else {
                            // One-time service - add to order items
                            $stmt_order_item->execute([
                                $order_id,
                                $item['item']['id'],
                                $item['qty'],
                                $item['item']['price_cents']
                            ]);

                            $order_items_data[] = [
                                'product_name' => $item['item']['name'] . ' (Service)',
                                'qty' => $item['qty'],
                                'price_cents' => $item['item']['price_cents']
                            ];
                        }
                    }
                }

                $pdo->commit();

                // Send confirmation email
                $order_data = [
                    'id' => $order_id,
                    'order_number' => $order_number,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'customer_phone' => $customer_phone,
                    'customer_address' => $customer_address,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                send_order_confirmation($order_data, $order_items_data);

                // Clear cart
                cart_clear();

                // Store order number for display
                $order_number_display = $order_number;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to process your order. Please try again.';
                error_log('Checkout error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <!-- PayPal SDK with card support -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?= PAYPAL_CLIENT_ID ?>&currency=USD&enable-funding=card&components=buttons"></script>
    <style>
        #paypal-button-container {
            margin-top: 20px;
            min-height: 150px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?></h1>
                <div class="tagline"><?= SITE_TAGLINE ?></div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container">
            <?php if ($order_id): ?>
                <!-- Order Confirmation -->
                <div class="alert alert-success">
                    <h2><?= $paypal_success ? 'Payment Successful!' : 'Order Confirmed!' ?></h2>
                    <p>Thank you for your order. Your order number is: <strong><?= isset($order_number_display) ? sanitize($order_number_display) : '#' . $order_id ?></strong></p>
                    <?php if ($paypal_success): ?>
                        <p>Your PayPal payment has been processed successfully. We've sent a confirmation email with your order details.</p>
                    <?php elseif (isset($customer_email)): ?>
                        <p>We've sent a confirmation email to <strong><?= sanitize($customer_email) ?></strong></p>
                    <?php endif; ?>
                </div>

                <div class="btn-group">
                    <a href="/index.php" class="btn">Return to Home</a>
                    <a href="/products.php" class="btn btn-secondary">Continue Shopping</a>
                </div>

            <?php else: ?>
                <!-- Checkout Form -->
                <h2>Checkout</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= sanitize($error) ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['paypal_cancel'])): ?>
                    <div class="alert alert-warning">
                        <strong>Payment Cancelled</strong><br>
                        You cancelled the payment. Your cart items are still saved. You can try again below.
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">
                    <!-- Payment Section -->
                    <div>
                        <h3>Complete Your Purchase</h3>
                        <p style="color: #b0b0b0; margin-bottom: 20px;">
                            Pay securely with PayPal or credit/debit card. You don't need a PayPal account to pay with a card.
                        </p>

                        <!-- PayPal Payment Button -->
                        <div id="paypal-button-container"></div>

                        <p style="color: #888; font-size: 13px; margin-top: 20px; line-height: 1.6;">
                            <strong>Payment Options:</strong><br>
                            • Pay with your PayPal account<br>
                            • Pay with credit or debit card (no PayPal account needed)<br>
                            • All transactions are secure and encrypted
                        </p>
                    </div>

                    <!-- Order Summary -->
                    <div>
                        <div class="cart-summary">
                            <h3>Order Summary</h3>

                            <?php if (!empty($cart_items)): ?>
                                <?php foreach ($cart_items as $item): ?>
                                <div style="padding: 10px 0; border-bottom: 1px solid #e0e0e0;">
                                    <strong><?= sanitize($item['item']['name']) ?></strong>
                                    <?php if ($item['type'] === 'service'): ?>
                                        <span style="display: inline-block; padding: 2px 6px; background: rgba(220, 20, 60, 0.2); color: #dc143c; border-radius: 3px; font-size: 10px; margin-left: 5px;">SERVICE</span>
                                    <?php endif; ?>
                                    <br>
                                    <small>Qty: <?= $item['qty'] ?> × <?= format_price($item['item']['price_cents']) ?>
                                    <?php if ($item['type'] === 'service' && $item['item']['service_category'] === 'subscription'): ?>
                                        /month
                                    <?php endif; ?>
                                    </small><br>
                                    <strong><?= format_price($item['item']['price_cents'] * $item['qty']) ?></strong>
                                </div>
                                <?php endforeach; ?>

                                <div class="total" style="margin-top: 20px;">
                                    Total: <?= format_price($cart_total) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - <?= SITE_TAGLINE ?></p>
        </div>
    </footer>

    <?php require_once __DIR__ . '/../chat/widget.php'; ?>

    <script>
    // Initialize PayPal button
    <?php if (!$order_id): ?>
    if (typeof paypal !== 'undefined') {
        paypal.Buttons({
            style: {
                layout: 'vertical',
                label: 'pay'
            },

            createOrder: function(data, actions) {
                return fetch('/api/paypal-create-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (!data.success) {
                        alert('Error creating order: ' + (data.error || 'Unknown error'));
                        throw new Error(data.error || 'Failed to create PayPal order');
                    }
                    return data.orderID;
                });
            },

            onApprove: function(data, actions) {
                return fetch('/api/paypal-capture-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        orderID: data.orderID
                    })
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (!data.success) {
                        alert('Payment Error: ' + (data.error || 'Unknown error'));
                        throw new Error(data.error || 'Failed to capture payment');
                    }
                    window.location.href = '/checkout.php?paypal_success=' + data.orderNumber;
                });
            },

            onCancel: function(data) {
                window.location.href = '/checkout.php?paypal_cancel=1';
            }
        }).render('#paypal-button-container');
    }
    <?php endif; ?>
    </script>
</body>
</html>
