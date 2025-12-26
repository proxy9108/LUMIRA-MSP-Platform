<?php
/**
 * LUMIRA - Admin Dashboard
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

// Check if user is logged in
if (!is_logged_in()) {
    redirect('/login.php');
}

// Support agents should go to support page, not admin dashboard
if (is_support_agent()) {
    redirect('/support.php');
}

// Only full admins can access this dashboard
if (!is_full_admin()) {
    redirect('/index.php');
}

$user = get_logged_in_user();

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

$error = '';
$message = '';

// Handle creating new support agent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_agent'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'technician';

        if (empty($email) || empty($full_name) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                $pdo = get_db();

                // Check if email already exists
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists.';
                } else {
                    // Get role_id
                    $stmt = $pdo->prepare('SELECT id FROM app_roles WHERE name = ?');
                    $stmt->execute([$role]);
                    $role_id = $stmt->fetchColumn();

                    if (!$role_id) {
                        $error = 'Invalid role selected.';
                    } else {
                        // Create user
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('
                            INSERT INTO users (email, password_hash, full_name, role_id, is_active, email_verified, created_at)
                            VALUES (?, ?, ?, ?, TRUE, TRUE, NOW())
                        ');
                        $stmt->execute([$email, $password_hash, $full_name, $role_id]);
                        $message = 'User account created successfully!';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Failed to create user. Please try again.';
                error_log('User creation error: ' . $e->getMessage());
            }
        }
    }
}

// Handle order archiving
if (isset($_GET['archive_order'])) {
    $order_id = (int)$_GET['archive_order'];
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('UPDATE orders SET is_archived = TRUE WHERE id = ?');
        $stmt->execute([$order_id]);
        $message = 'Order archived successfully!';
        // Redirect to clean URL
        $redirect_url = '?order_filter=' . ($_GET['order_filter'] ?? 'recent');
        if (isset($_GET['show_archived'])) {
            $redirect_url .= '&show_archived=' . $_GET['show_archived'];
        }
        header('Location: ' . $redirect_url);
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to archive order.';
        error_log('Order archive error: ' . $e->getMessage());
    }
}

// Handle order unarchiving
if (isset($_GET['unarchive_order'])) {
    $order_id = (int)$_GET['unarchive_order'];
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('UPDATE orders SET is_archived = FALSE WHERE id = ?');
        $stmt->execute([$order_id]);
        $message = 'Order restored successfully!';
        // Redirect to clean URL
        $redirect_url = '?order_filter=' . ($_GET['order_filter'] ?? 'recent');
        if (isset($_GET['show_archived'])) {
            $redirect_url .= '&show_archived=' . $_GET['show_archived'];
        }
        header('Location: ' . $redirect_url);
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to restore order.';
        error_log('Order unarchive error: ' . $e->getMessage());
    }
}

// Fetch all data for admin view
try {
    $pdo = get_db();

    // Get orders with filtering
    $order_filter = $_GET['order_filter'] ?? 'recent';

    $order_where = '';
    $order_limit = 'LIMIT 100';

    switch ($order_filter) {
        case 'all':
            // All non-archived orders (exclude PayPal temporary pending_payment orders, but keep manual pending_payment)
            $order_where = "WHERE o.is_archived = false AND NOT (o.status = 'pending_payment' AND o.payment_method = 'paypal')";
            break;
        case 'recent':
            // Orders from last 30 days (non-archived, exclude PayPal temporary pending_payment orders)
            $order_where = "WHERE o.created_at >= NOW() - INTERVAL '30 days' AND o.is_archived = false AND NOT (o.status = 'pending_payment' AND o.payment_method = 'paypal')";
            break;
        case 'pending':
            // Show both 'pending' status and manual 'pending_payment' orders
            $order_where = "WHERE (o.status = 'pending' OR (o.status = 'pending_payment' AND o.payment_method IS NULL)) AND o.is_archived = false";
            break;
        case 'paid':
            $order_where = "WHERE o.status = 'paid' AND o.is_archived = false";
            break;
        case 'completed':
            $order_where = "WHERE o.status = 'completed' AND o.is_archived = false";
            break;
        case 'archived':
            $order_where = "WHERE o.is_archived = true AND NOT (o.status = 'pending_payment' AND o.payment_method = 'paypal')";
            break;
    }

    $stmt = $pdo->query("
        SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.total_cents) as items_total
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $order_where
        GROUP BY o.id
        ORDER BY o.created_at DESC
        $order_limit
    ");
    $orders = $stmt->fetchAll();

    // Get all tickets (exclude archived by default unless requested)
    $show_archived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
    $archive_filter = $show_archived ? '' : 'WHERE t.is_archived = false';

    $stmt = $pdo->query('
        SELECT t.*,
               tc.name as category_name,
               tp.name as priority_name,
               tp.color_code as priority_color,
               ts.name as status_name,
               ts.color_code as status_color,
               u.full_name as requester_name,
               u2.full_name as assigned_to_name
        FROM tickets t
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users u2 ON t.assigned_to_id = u2.id
        ' . $archive_filter . '
        ORDER BY t.created_at DESC
        LIMIT 100
    ');
    $tickets = $stmt->fetchAll();

    // Get all users
    $stmt = $pdo->query('
        SELECT u.id, u.email, u.full_name, u.created_at, r.name as role_name, r.display_name as role_display
        FROM users u
        LEFT JOIN app_roles r ON u.role_id = r.id
        ORDER BY u.created_at DESC
    ');
    $users = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->query('SELECT COUNT(*) FROM orders');
    $total_orders = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM tickets');
    $total_tickets = $stmt->fetchColumn();

    $stmt = $pdo->query('
        SELECT COUNT(*) FROM users u
        JOIN app_roles r ON u.role_id = r.id
        WHERE r.name IN (\'client_user\', \'client_admin\')
    ');
    $total_customers = $stmt->fetchColumn();

} catch (PDOException $e) {
    $error = 'Unable to load data. Please try again later.';
    error_log('Admin dashboard error: ' . $e->getMessage());
    $orders = [];
    $tickets = [];
    $users = [];
    $total_orders = 0;
    $total_tickets = 0;
    $total_customers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?> - Admin Portal</h1>
                <div class="tagline">Welcome, <?= sanitize($user['full_name']) ?></div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container">
            <h2>Administrative Dashboard</h2>

            <?php if ($message): ?>
                <div class="alert" style="background: #28a745; color: white; margin-bottom: 20px;">
                    ✅ <?= sanitize($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
                <div class="product-card" style="text-align: center;">
                    <h3 style="font-size: 3em; margin: 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= $total_orders ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="product-card" style="text-align: center;">
                    <h3 style="font-size: 3em; margin: 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= $total_tickets ?></h3>
                    <p>Service Tickets</p>
                </div>
                <div class="product-card" style="text-align: center;">
                    <h3 style="font-size: 3em; margin: 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= $total_customers ?></h3>
                    <p>Customers</p>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="admin-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <h3 style="margin: 0;">Orders (<?= count($orders) ?>)</h3>

                    <!-- Order Filter Dropdown -->
                    <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                        <?php if (isset($_GET['show_archived'])): ?>
                            <input type="hidden" name="show_archived" value="<?= $_GET['show_archived'] ?>">
                        <?php endif; ?>

                        <label style="font-size: 14px; color: #b0b0b0;">Filter:</label>
                        <select name="order_filter" onchange="this.form.submit()" style="padding: 8px 16px; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(220, 20, 60, 0.3); color: #ffffff; border-radius: 5px; font-size: 14px;">
                            <option value="recent" <?= $order_filter === 'recent' ? 'selected' : '' ?>>📅 Recent (30 days)</option>
                            <option value="all" <?= $order_filter === 'all' ? 'selected' : '' ?>>📋 All Orders</option>
                            <option value="pending" <?= $order_filter === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                            <option value="paid" <?= $order_filter === 'paid' ? 'selected' : '' ?>>💳 Paid (PayPal)</option>
                            <option value="completed" <?= $order_filter === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                            <option value="archived" <?= $order_filter === 'archived' ? 'selected' : '' ?>>📦 Archived</option>
                        </select>
                    </form>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="alert alert-info">No orders found.</div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr<?= ($order['is_archived'] ?? false) ? ' style="opacity: 0.6; background: rgba(100,100,100,0.1);"' : '' ?>>
                                <td>
                                    <strong><?= sanitize($order['order_number'] ?? '#' . $order['id']) ?></strong>
                                    <?php if ($order['is_archived'] ?? false): ?>
                                        <span style="font-size: 11px; background: #666; padding: 2px 6px; border-radius: 3px; margin-left: 5px;">ARCHIVED</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($order['customer_name']) ?></td>
                                <td><?= sanitize($order['customer_email']) ?></td>
                                <td><?= $order['item_count'] ?></td>
                                <td><?= format_price($order['total_cents'] ?? 0) ?></td>
                                <td><span style="padding: 4px 8px; background: rgba(220,20,60,0.2); border-radius: 4px;"><?= strtoupper($order['status'] ?? 'pending') ?></span></td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <a href="/admin-order-view.php?id=<?= $order['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 12px;">👁 View</a>
                                    <?php if (!($order['is_archived'] ?? false)): ?>
                                        <a href="?archive_order=<?= $order['id'] ?>&order_filter=<?= $order_filter ?><?= isset($_GET['show_archived']) ? '&show_archived=' . $_GET['show_archived'] : '' ?>"
                                           class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;"
                                           onclick="return confirm('Archive this order?');">📦 Archive</a>
                                    <?php else: ?>
                                        <a href="?unarchive_order=<?= $order['id'] ?>&order_filter=<?= $order_filter ?><?= isset($_GET['show_archived']) ? '&show_archived=' . $_GET['show_archived'] : '' ?>"
                                           class="btn" style="padding: 6px 12px; font-size: 12px; background: #28a745;">↩ Unarchive</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Service Tickets -->
            <div class="admin-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <h3 style="margin: 0;">Service Requests / Tickets (<?= count($tickets) ?>)</h3>

                    <!-- Ticket Filter Dropdown -->
                    <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                        <?php if (isset($_GET['order_filter'])): ?>
                            <input type="hidden" name="order_filter" value="<?= $_GET['order_filter'] ?>">
                        <?php endif; ?>

                        <label style="font-size: 14px; color: #b0b0b0;">Show:</label>
                        <select name="show_archived" onchange="this.form.submit()" style="padding: 8px 16px; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(220, 20, 60, 0.3); color: #ffffff; border-radius: 5px; font-size: 14px;">
                            <option value="0" <?= !$show_archived ? 'selected' : '' ?>>👁 Active Tickets</option>
                            <option value="1" <?= $show_archived ? 'selected' : '' ?>>📦 Archived Tickets</option>
                        </select>
                    </form>
                </div>

                <?php if (empty($tickets)): ?>
                    <div class="alert alert-info">No service requests found.</div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Subject</th>
                                <th>Customer</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                            <tr<?= $ticket['is_archived'] ? ' style="opacity: 0.5; background: rgba(100,100,100,0.1);"' : '' ?>>
                                <td>
                                    <strong><?= sanitize($ticket['ticket_number']) ?></strong>
                                    <?php if ($ticket['is_archived']): ?>
                                        <span style="font-size: 11px; background: #666; padding: 2px 6px; border-radius: 3px; margin-left: 5px;">ARCHIVED</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize(substr($ticket['subject'], 0, 40)) ?><?= strlen($ticket['subject']) > 40 ? '...' : '' ?></td>
                                <td><?= sanitize($ticket['requester_name']) ?></td>
                                <td><?= sanitize($ticket['category_name'] ?? 'General') ?></td>
                                <td><span style="color: <?= $ticket['priority_color'] ?? '#666' ?>; font-weight: 600;"><?= sanitize($ticket['priority_name'] ?? 'Normal') ?></span></td>
                                <td><span style="color: <?= $ticket['status_color'] ?? '#666' ?>; font-weight: 600;"><?= sanitize($ticket['status_name'] ?? 'New') ?></span></td>
                                <td><?= sanitize($ticket['assigned_to_name'] ?? 'Unassigned') ?></td>
                                <td><?= date('M j, Y', strtotime($ticket['created_at'])) ?></td>
                                <td>
                                    <a href="/admin-ticket-view.php?id=<?= $ticket['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 12px;">👁 View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Create New User/Agent -->
            <div class="admin-section">
                <h3>➕ Create New User Account</h3>
                <form method="POST" action="" style="max-width: 600px;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" required placeholder="John Doe">
                    </div>

                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" required placeholder="user@example.com">
                    </div>

                    <div class="form-group">
                        <label>Password * (minimum 8 characters)</label>
                        <input type="password" name="password" required minlength="8" placeholder="Secure password">
                    </div>

                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" required>
                            <option value="technician">Support Agent (Technician) - Can only handle tickets</option>
                            <option value="manager">Manager - Can handle tickets and view admin dashboard</option>
                            <option value="admin">Administrator - Full admin access</option>
                            <option value="super_admin">Super Administrator - Full system access</option>
                            <option value="client_user">Client User - Standard customer</option>
                            <option value="client_admin">Client Admin - Customer with admin access</option>
                        </select>
                    </div>

                    <button type="submit" name="create_agent" class="btn">Create User Account</button>
                </form>
            </div>

            <!-- Registered Users -->
            <div class="admin-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Registered Users (<?= count($users) ?>)</h3>
                    <a href="/admin-users.php" class="btn">👥 Manage All Users</a>
                </div>

                <?php if (empty($users)): ?>
                    <div class="alert alert-info">No users found.</div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Show only first 5 users
                            $displayed_users = array_slice($users, 0, 5);
                            foreach ($displayed_users as $u):
                            ?>
                            <tr>
                                <td>#<?= $u['id'] ?></td>
                                <td><?= sanitize($u['full_name']) ?></td>
                                <td><?= sanitize($u['email']) ?></td>
                                <td><span style="background: <?= in_array($u['role_name'], ['super_admin', 'admin']) ? '#ff006e' : '#00f0ff' ?>; padding: 2px 10px; border-radius: 5px; font-size: 0.9em;"><?= strtoupper($u['role_display'] ?? $u['role_name'] ?? 'USER') ?></span></td>
                                <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($users) > 5): ?>
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="/admin-users.php" class="btn btn-secondary">View All <?= count($users) ?> Users →</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
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
