<?php
/**
 * LUMIRA - Admin Order View
 * Detailed view of customer orders for administrators
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';

session_start();

// Check if user is admin
if (!is_logged_in() || !is_user_admin()) {
    redirect('/login.php');
}

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

$user = get_logged_in_user();
$order_id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

if ($order_id <= 0) {
    redirect('/dashboard-admin.php');
}

$pdo = get_db();

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $new_status = trim($_POST['status'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (!empty($new_status)) {
                $stmt = $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$new_status, $order_id]);

                // TODO: Log order status change history
                // You could create an order_history table similar to ticket_history

                $message = 'Order status updated successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Failed to update order: ' . $e->getMessage();
            error_log('Admin order update error: ' . $e->getMessage());
        }
    }
}

// Fetch order details
try {
    $stmt = $pdo->prepare('
        SELECT * FROM orders WHERE id = ?
    ');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $error = 'Order not found.';
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

        // Get customer info if they have an account
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$order['customer_email']]);
        $customer = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = 'Unable to load order: ' . $e->getMessage();
    error_log('Admin order view error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order ? sanitize($order['order_number'] ?? $order['id']) : 'Not Found' ?> - Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?> - Admin Portal</h1>
                <div class="tagline">Order Management</div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../app/views/layouts/nav.php'; ?>

    <main>
        <div class="container">
            <div style="margin-bottom: 20px;">
                <a href="/dashboard-admin.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= sanitize($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <?php if ($order): ?>
                <!-- Order Header -->
                <div class="order-header">
                    <h2 style="margin-bottom: 10px;">Order #<?= sanitize($order['order_number'] ?? $order['id']) ?></h2>
                    <div class="info-grid" style="margin-top: 20px;">
                        <div class="info-box">
                            <div class="info-label">Status</div>
                            <div class="info-value" style="color: var(--primary);">
                                <?= strtoupper(sanitize($order['status'] ?? 'pending')) ?>
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

                <!-- Customer Information -->
                <div class="order-section">
                    <h3 style="margin-bottom: 20px;">👤 Customer Information</h3>
                    <div class="info-grid">
                        <div class="info-box">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?= sanitize($order['customer_name']) ?></div>
                        </div>

                        <div class="info-box">
                            <div class="info-label">Email</div>
                            <div class="info-value">
                                <a href="mailto:<?= sanitize($order['customer_email']) ?>" style="color: var(--primary);">
                                    <?= sanitize($order['customer_email']) ?>
                                </a>
                            </div>
                        </div>

                        <?php if ($customer): ?>
                        <div class="info-box">
                            <div class="info-label">Account Status</div>
                            <div class="info-value" style="color: #00ff88;">
                                ✓ Registered Customer
                            </div>
                        </div>

                        <?php if ($customer['phone']): ?>
                        <div class="info-box">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?= sanitize($customer['phone']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="info-box">
                            <div class="info-label">Account Status</div>
                            <div class="info-value" style="color: var(--text-secondary);">
                                Guest Checkout
                            </div>
                        </div>
                        <?php endif; ?>
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
                                    <td style="font-weight: bold; font-size: 16px;"><?= format_price($order['subtotal_cents'] ?? $subtotal) ?></td>
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

                <!-- Order Management -->
                <div class="order-section">
                    <h3 style="margin-bottom: 20px;">🛠 Order Management</h3>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label>Order Status</label>
                                <select name="status">
                                    <option value="pending" <?= ($order['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="processing" <?= ($order['status'] ?? '') === 'processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="shipped" <?= ($order['status'] ?? '') === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="delivered" <?= ($order['status'] ?? '') === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                    <option value="cancelled" <?= ($order['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    <option value="refunded" <?= ($order['status'] ?? '') === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Notes (optional)</label>
                                <textarea name="notes" rows="3" placeholder="Add notes about this status change..."></textarea>
                            </div>
                        </div>

                        <button type="submit" name="update_status" class="btn">💾 Update Order Status</button>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="mailto:<?= sanitize($order['customer_email']) ?>?subject=Order <?= sanitize($order['order_number'] ?? $order['id']) ?>" class="btn btn-secondary">✉️ Email Customer</a>
                    <a href="#" onclick="window.print(); return false;" class="btn btn-secondary">🖨️ Print Order</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - <?= SITE_TAGLINE ?></p>
        </div>
    </footer>
</body>
</html>
