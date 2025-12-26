<?php
/**
 * LUMIRA - Customer Order View
 * Allows customers to view their order details
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

// Check if user is logged in
if (!is_logged_in()) {
    redirect('/login.php');
}

// If admin, redirect to admin order view
if (is_user_admin()) {
    redirect('/admin-order-view.php?id=' . (int)($_GET['id'] ?? 0));
}

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

$user = get_logged_in_user();
$order_id = (int)($_GET['id'] ?? 0);
$error = '';

if ($order_id <= 0) {
    redirect('/dashboard-customer.php');
}

$pdo = get_db();

// Fetch order details - must belong to logged-in user
try {
    $stmt = $pdo->prepare('
        SELECT * FROM orders WHERE id = ? AND customer_email = ?
    ');
    $stmt->execute([$order_id, $user['email']]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = 'Order not found or you do not have permission to view it.';
    } else {
        // Get order items with product AND service details
        $stmt = $pdo->prepare('
            SELECT oi.*,
                   COALESCE(p.name, s.name) as product_name,
                   p.sku,
                   COALESCE(p.description, s.description) as product_description,
                   CASE WHEN s.id IS NOT NULL THEN true ELSE false END as is_service
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN services s ON oi.product_id = s.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ');
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();

        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ($item['price_cents'] * $item['qty']);
        }
    }
} catch (PDOException $e) {
    $error = 'Unable to load order: ' . $e->getMessage();
    error_log('Order view error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= $order ? ('#' . sanitize($order['order_number'] ?? $order['id'])) : 'Not Found' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        .order-header {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .order-section {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-box {
            padding: 15px;
            background: rgba(26, 26, 26, 0.6);
            border-radius: 10px;
            border-left: 3px solid var(--primary);
        }
        .info-label {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 600;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-processing { background: rgba(33, 150, 243, 0.2); color: #2196f3; }
        .status-shipped { background: rgba(156, 39, 176, 0.2); color: #9c27b0; }
        .status-delivered { background: rgba(76, 175, 80, 0.2); color: #4caf50; }
        .status-cancelled { background: rgba(244, 67, 54, 0.2); color: #f44336; }
        .status-refunded { background: rgba(158, 158, 158, 0.2); color: #9e9e9e; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?> - Customer Portal</h1>
                <div class="tagline">Order Details</div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container">
            <div style="margin-bottom: 20px;">
                <a href="/dashboard-customer.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
                <a href="/dashboard-customer.php" class="btn">Return to Dashboard</a>
            <?php elseif ($order): ?>
                <!-- Order Header -->
                <div class="order-header">
                    <h2 style="margin-bottom: 10px;">Order #<?= sanitize($order['order_number'] ?? $order['id']) ?></h2>
                    <div class="info-grid" style="margin-top: 20px;">
                        <div class="info-box">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?= sanitize($order['status'] ?? 'pending') ?>">
                                    <?= strtoupper(sanitize($order['status'] ?? 'pending')) ?>
                                </span>
                            </div>
                        </div>

                        <div class="info-box">
                            <div class="info-label">Order Date</div>
                            <div class="info-value">
                                <?= date('F j, Y g:i A', strtotime($order['created_at'])) ?>
                            </div>
                        </div>

                        <div class="info-box">
                            <div class="info-label">Total Amount</div>
                            <div class="info-value" style="color: var(--primary);">
                                <?= format_price($order['total_cents'] ?? $subtotal) ?>
                            </div>
                        </div>

                        <?php if ($order['paid_at']): ?>
                        <div class="info-box">
                            <div class="info-label">Paid Date</div>
                            <div class="info-value">
                                <?= date('F j, Y g:i A', strtotime($order['paid_at'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="order-section">
                    <h3 style="margin-bottom: 20px;">📦 Shipping Information</h3>
                    <div class="info-grid">
                        <div class="info-box">
                            <div class="info-label">Recipient Name</div>
                            <div class="info-value"><?= sanitize($order['customer_name']) ?></div>
                        </div>

                        <div class="info-box">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?= sanitize($order['customer_email']) ?></div>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <div class="info-label">Shipping Address</div>
                        <div style="background: rgba(26, 26, 26, 0.6); padding: 15px; border-radius: 10px; margin-top: 10px; white-space: pre-wrap;"><?= nl2br(sanitize($order['customer_address'])) ?></div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="order-section">
                    <h3 style="margin-bottom: 20px;">📦 Order Items</h3>

                    <?php if (empty($items)): ?>
                        <div class="alert alert-info">No items found for this order.</div>
                    <?php else: ?>
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= sanitize($item['product_name'] ?? 'Unknown Product') ?></strong>
                                        <?php if ($item['is_service']): ?>
                                            <span style="display: inline-block; padding: 2px 8px; background: rgba(220, 20, 60, 0.2); color: #dc143c; border-radius: 4px; font-size: 11px; margin-left: 8px;">SERVICE</span>
                                        <?php endif; ?>
                                        <?php if ($item['product_description']): ?>
                                        <br><small style="color: var(--text-secondary);"><?= sanitize(substr($item['product_description'], 0, 60)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= sanitize($item['sku'] ?? 'N/A') ?></td>
                                    <td><?= $item['qty'] ?></td>
                                    <td><?= format_price($item['price_cents']) ?></td>
                                    <td><strong><?= format_price($item['price_cents'] * $item['qty']) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="text-align: right; font-weight: bold; font-size: 16px;">Subtotal:</td>
                                    <td style="font-weight: bold; font-size: 16px;"><?= format_price($subtotal) ?></td>
                                </tr>
                                <?php if (($order['tax_cents'] ?? 0) > 0): ?>
                                <tr>
                                    <td colspan="4" style="text-align: right; font-weight: bold;">Tax:</td>
                                    <td style="font-weight: bold;"><?= format_price($order['tax_cents']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="4" style="text-align: right; font-weight: bold; font-size: 18px; color: var(--primary);">TOTAL:</td>
                                    <td style="font-weight: bold; font-size: 18px; color: var(--primary);"><?= format_price($order['total_cents'] ?? $subtotal) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Order Status Information -->
                <div class="order-section">
                    <h3 style="margin-bottom: 15px;">ℹ️ Order Status</h3>
                    <?php
                    $status_messages = [
                        'pending' => 'Your order has been received and is awaiting processing.',
                        'processing' => 'Your order is being prepared for shipment.',
                        'shipped' => 'Your order has been shipped! You should receive it soon.',
                        'delivered' => 'Your order has been delivered. We hope you enjoy your purchase!',
                        'cancelled' => 'This order has been cancelled. If you have any questions, please contact support.',
                        'refunded' => 'This order has been refunded.'
                    ];
                    $status = $order['status'] ?? 'pending';
                    ?>
                    <div style="background: rgba(26, 26, 26, 0.6); padding: 20px; border-radius: 10px;">
                        <p style="font-size: 15px; line-height: 1.6;">
                            <?= $status_messages[$status] ?? 'Status information not available.' ?>
                        </p>

                        <?php if ($status === 'shipped' || $status === 'delivered'): ?>
                        <p style="margin-top: 15px; font-size: 14px; color: var(--text-secondary);">
                            If you have tracking information, it will be sent to <?= sanitize($order['customer_email']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="/create-ticket.php" class="btn">Need Help? Contact Support</a>
                    <a href="#" onclick="window.print(); return false;" class="btn btn-secondary">🖨️ Print Order</a>
                    <?php if (in_array($status, ['pending', 'processing'])): ?>
                    <a href="mailto:<?= SUPPORT_EMAIL ?>?subject=Cancel Order <?= sanitize($order['order_number'] ?? $order['id']) ?>" class="btn btn-secondary">Request Cancellation</a>
                    <?php endif; ?>
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
</body>
</html>
