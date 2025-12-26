<?php
/**
 * LUMIRA - Customer Dashboard
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

// Check if user is logged in and is a customer
if (!is_logged_in()) {
    redirect('/login.php');
}

$user = get_logged_in_user();

// If admin, redirect to admin dashboard
if (is_user_admin()) {
    redirect('/dashboard-admin.php');
}

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

// Fetch user's data
try {
    $pdo = get_db();
    $orders = get_user_orders($pdo, $user['email']);
    $tickets = get_user_tickets($pdo, $user['id']);
} catch (PDOException $e) {
    $error = 'Unable to load your data. Please try again later.';
    error_log('Dashboard error: ' . $e->getMessage());
    $orders = [];
    $tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?> - Customer Portal</h1>
                <div class="tagline">Welcome, <?= sanitize($user['full_name']) ?></div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container">
            <h2>My Dashboard</h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <!-- Account Information -->
            <div class="admin-section">
                <h3>Account Information</h3>
                <div style="background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 10px;">
                    <p><strong>Name:</strong> <?= sanitize($user['full_name']) ?></p>
                    <p><strong>Email:</strong> <?= sanitize($user['email']) ?></p>
                    <p><strong>Member Since:</strong> <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                </div>
            </div>

            <!-- My Orders -->
            <div class="admin-section">
                <h3>My Orders (<?= count($orders) ?>)</h3>

                <?php if (empty($orders)): ?>
                    <div class="alert alert-info">
                        You haven't placed any orders yet. <a href="/products.php">Browse our products</a> to get started!
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Shipping Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td><?= $order['item_count'] ?> item(s)</td>
                                <td><?= format_price($order['total_cents'] ?? 0) ?></td>
                                <td><?= sanitize(substr($order['customer_address'], 0, 50)) ?><?= strlen($order['customer_address']) > 50 ? '...' : '' ?></td>
                                <td><a href="/order-view.php?id=<?= $order['id'] ?>" class="btn btn-sm">View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- My Service Requests -->
            <div class="admin-section">
                <h3>My Service Requests (<?= count($tickets) ?>)</h3>

                <?php if (empty($tickets)): ?>
                    <div class="alert alert-info">
                        You haven't submitted any service requests yet. <a href="/services.php">View our services</a> to request assistance.
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><?= sanitize($ticket['ticket_number']) ?></td>
                                <td><?= sanitize($ticket['subject']) ?></td>
                                <td><span style="color: <?= $ticket['priority_color'] ?? '#666' ?>"><?= sanitize($ticket['priority_name'] ?? 'Normal') ?></span></td>
                                <td><span style="color: <?= $ticket['status_color'] ?? '#666' ?>"><?= sanitize($ticket['status_name'] ?? 'New') ?></span></td>
                                <td><?= date('M j, Y', strtotime($ticket['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="btn-group" style="margin-top: 30px;">
                <a href="/products.php" class="btn">Browse Products</a>
                <a href="/services.php" class="btn btn-secondary">Request Service</a>
            </div>

            <!-- Close Account Section -->
            <div class="admin-section" style="margin-top: 50px; border: 2px solid #dc143c; background: rgba(220, 20, 60, 0.1);">
                <h3 style="color: #dc143c;">⚠️ Danger Zone</h3>
                <div style="padding: 20px; background: rgba(0, 0, 0, 0.3); border-radius: 10px;">
                    <h4 style="margin-top: 0;">Close Account</h4>
                    <p style="color: #b0b0b0; line-height: 1.6;">
                        Once you close your account, there is no going back. This action will permanently delete:
                    </p>
                    <ul style="color: #b0b0b0; line-height: 1.8;">
                        <li>Your account and profile information</li>
                        <li>All order history and receipts</li>
                        <li>All active and past subscriptions</li>
                        <li>All support tickets and communications</li>
                    </ul>
                    <p style="color: #dc143c; font-weight: bold;">
                        ⚠️ This action cannot be undone. All data will be permanently deleted.
                    </p>
                    <button onclick="showDeleteConfirmation()" class="btn" style="background: #dc143c; border-color: #dc143c;">
                        Close My Account
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a1a; padding: 40px; border-radius: 15px; max-width: 500px; border: 2px solid #dc143c;">
                <h2 style="color: #dc143c; margin-top: 0;">⚠️ Confirm Account Deletion</h2>
                <p style="color: #fff; line-height: 1.6; margin: 20px 0;">
                    Are you absolutely sure you want to close your account?
                </p>
                <p style="color: #dc143c; font-weight: bold; margin: 20px 0;">
                    This will permanently delete all your data including:
                </p>
                <ul style="color: #b0b0b0; line-height: 1.8;">
                    <li><?= count($orders) ?> order(s)</li>
                    <li><?= count($tickets) ?> support ticket(s)</li>
                    <li>All subscriptions</li>
                    <li>Your account information</li>
                </ul>
                <p style="color: #fff; margin: 20px 0;">
                    Type <strong style="color: #dc143c;">DELETE</strong> to confirm:
                </p>
                <input type="text" id="confirmText" placeholder="Type DELETE" style="width: 100%; padding: 10px; margin-bottom: 20px; background: #0a0a0a; border: 1px solid #444; color: #fff; border-radius: 5px;">
                <div id="deleteError" style="display: none; color: #dc143c; margin-bottom: 15px; padding: 10px; background: rgba(220, 20, 60, 0.2); border-radius: 5px;"></div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="confirmDelete()" class="btn" style="flex: 1; background: #dc143c; border-color: #dc143c;">
                        Yes, Delete Everything
                    </button>
                    <button onclick="closeDeleteModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <script>
        function showDeleteConfirmation() {
            document.getElementById('deleteModal').style.display = 'flex';
            document.getElementById('confirmText').value = '';
            document.getElementById('deleteError').style.display = 'none';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function confirmDelete() {
            const confirmText = document.getElementById('confirmText').value;
            if (confirmText !== 'DELETE') {
                document.getElementById('deleteError').textContent = 'You must type DELETE to confirm.';
                document.getElementById('deleteError').style.display = 'block';
                return;
            }

            // Disable button and show loading
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Deleting...';

            // Send deletion request
            fetch('/api/delete-account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    csrf_token: '<?= csrf_token() ?>',
                    confirmed: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Your account has been permanently deleted. You will now be redirected to the home page.');
                    window.location.href = '/index.php';
                } else {
                    document.getElementById('deleteError').textContent = data.error || 'Failed to delete account. Please try again.';
                    document.getElementById('deleteError').style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Yes, Delete Everything';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('deleteError').textContent = 'An error occurred. Please try again.';
                document.getElementById('deleteError').style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Yes, Delete Everything';
            });
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        </script>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - <?= SITE_TAGLINE ?></p>
        </div>
    </footer>

    <?php require_once __DIR__ . '/../chat/widget.php'; ?>
</body>
</html>
