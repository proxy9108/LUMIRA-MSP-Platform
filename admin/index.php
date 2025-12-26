<?php
/**
 * LUMIRA - Admin Panel
 * Simple password-protected admin panel
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';

session_start();

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $password = $_POST['password'] ?? '';

    if ($password === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = 'Invalid password.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    redirect('/admin.php');
}

// Check if logged in
$logged_in = is_admin();

// Fetch data if logged in
if ($logged_in) {
    try {
        $pdo = get_db();

        // Get recent orders
        $stmt = $pdo->query('
            SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.qty * oi.price_cents) as total_cents
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 100
        ');
        $orders = $stmt->fetchAll();

        // Get recent tickets
        $stmt = $pdo->query('
            SELECT t.*, s.name as service_name
            FROM tickets t
            LEFT JOIN services s ON t.service_id = s.id
            ORDER BY t.created_at DESC
            LIMIT 100
        ');
        $tickets = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error = 'Unable to load data. Please try again later.';
        $orders = [];
        $tickets = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?> - Admin Panel</h1>
                <div class="tagline">Administrative Dashboard</div>
            </div>
        </div>
    </header>

    <nav>
        <div class="container">
            <ul>
                <li><a href="/index.php">Home</a></li>
                <li><a href="/products.php">Products</a></li>
                <li><a href="/services.php">Services</a></li>
                <li><a href="/cart.php">Cart</a></li>
                <li><a href="/admin.php" class="active">Admin</a></li>
                <?php if ($logged_in): ?>
                <li><a href="?logout=1">Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <main>
        <div class="container">
            <?php if (!$logged_in): ?>
                <!-- Login Form -->
                <h2>Admin Login</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= sanitize($error) ?></div>
                <?php endif; ?>

                <div style="max-width: 400px; margin: 50px auto;">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Admin Password</label>
                            <input type="password" name="password" required autofocus>
                        </div>

                        <button type="submit" name="login" class="btn">Login</button>
                    </form>
                </div>

            <?php else: ?>
                <!-- Admin Dashboard -->
                <h2>Administrative Dashboard</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= sanitize($error) ?></div>
                <?php endif; ?>

                <!-- Orders Section -->
                <div class="admin-section">
                    <h3>Recent Orders (<?= count($orders) ?>)</h3>

                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info">No orders found.</div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Address</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= sanitize($order['customer_name']) ?></td>
                                    <td><?= sanitize($order['customer_email']) ?></td>
                                    <td><?= sanitize(substr($order['customer_address'], 0, 50)) ?>...</td>
                                    <td><?= $order['item_count'] ?></td>
                                    <td><?= format_price($order['total_cents'] ?? 0) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Tickets Section -->
                <div class="admin-section">
                    <h3>Service Requests / Tickets (<?= count($tickets) ?>)</h3>

                    <?php if (empty($tickets)): ?>
                        <div class="alert alert-info">No service requests found.</div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Service</th>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Details</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td>#<?= $ticket['id'] ?></td>
                                    <td><?= sanitize($ticket['service_name'] ?? 'N/A') ?></td>
                                    <td><?= sanitize($ticket['customer_name']) ?></td>
                                    <td><?= sanitize($ticket['customer_email']) ?></td>
                                    <td><?= sanitize(substr($ticket['details'] ?? '', 0, 100)) ?><?= strlen($ticket['details'] ?? '') > 100 ? '...' : '' ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
</body>
</html>
