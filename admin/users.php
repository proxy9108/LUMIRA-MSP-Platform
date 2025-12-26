<?php
/**
 * LUMIRA - Admin User Management
 * View and manage registered users
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
$error = '';
$message = '';

// Fetch all users
try {
    $pdo = get_db();

    // Get filter parameters
    $role_filter = $_GET['role'] ?? 'all';
    $search = $_GET['search'] ?? '';

    // Build query
    $where_clauses = ['u.is_active = true'];
    $params = [];

    if ($role_filter !== 'all') {
        $where_clauses[] = 'r.name = ?';
        $params[] = $role_filter;
    }

    if (!empty($search)) {
        $where_clauses[] = '(u.full_name ILIKE ? OR u.email ILIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.display_name as role_display_name,
               (SELECT COUNT(*) FROM orders WHERE customer_email = u.email) as order_count,
               (SELECT COUNT(*) FROM tickets WHERE requester_id = u.id) as ticket_count,
               (SELECT COUNT(*) FROM subscriptions WHERE customer_email = u.email) as subscription_count
        FROM users u
        LEFT JOIN app_roles r ON u.role_id = r.id
        $where_sql
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Get role list for filter
    $stmt = $pdo->prepare('SELECT * FROM app_roles ORDER BY display_name');
    $stmt->execute();
    $roles = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Unable to load users. Please try again later.';
    error_log('Admin users error: ' . $e->getMessage());
    $users = [];
    $roles = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        .user-stats {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .role-super_admin { background: rgba(220, 20, 60, 0.2); color: #dc143c; }
        .role-admin { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .role-manager { background: rgba(72, 219, 251, 0.2); color: #48dbfb; }
        .role-technician { background: rgba(0, 184, 148, 0.2); color: #00b894; }
        .role-client_admin { background: rgba(253, 203, 110, 0.2); color: #fdcb6e; }
        .role-client_user { background: rgba(129, 236, 236, 0.2); color: #81ecec; }
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?> - Admin Portal</h1>
                <div class="tagline">User Management</div>
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

            <div class="admin-section">
                <h2>Registered Users (<?= count($users) ?>)</h2>

                <!-- Filter Bar -->
                <form method="GET" action="" class="filter-bar">
                    <div style="flex: 1;">
                        <input type="text" name="search" placeholder="Search by name or email..."
                               value="<?= sanitize($search) ?>"
                               style="width: 100%; padding: 10px; background: rgba(0, 0, 0, 0.3); border: 1px solid #444; border-radius: 5px; color: #fff;">
                    </div>
                    <div>
                        <select name="role" style="padding: 10px; background: rgba(0, 0, 0, 0.3); border: 1px solid #444; border-radius: 5px; color: #fff;">
                            <option value="all">All Roles</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= sanitize($role['name']) ?>" <?= $role_filter === $role['name'] ? 'selected' : '' ?>>
                                    <?= sanitize($role['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn">Filter</button>
                    </div>
                    <?php if ($role_filter !== 'all' || !empty($search)): ?>
                    <div>
                        <a href="/admin-users.php" class="btn btn-secondary">Clear</a>
                    </div>
                    <?php endif; ?>
                </form>

                <?php if (empty($users)): ?>
                    <div class="alert alert-info">No users found matching your criteria.</div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Activity</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td>
                                    <strong><?= sanitize($u['full_name']) ?></strong>
                                    <?php if (!$u['email_verified']): ?>
                                        <br><small style="color: #888;">(Unverified)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($u['email']) ?></td>
                                <td>
                                    <span class="role-badge role-<?= sanitize($u['role_name'] ?? 'client_user') ?>">
                                        <?= sanitize($u['role_display_name'] ?? 'Client User') ?>
                                    </span>
                                </td>
                                <td class="user-stats">
                                    <?= $u['order_count'] ?> orders<br>
                                    <?= $u['ticket_count'] ?> tickets<br>
                                    <?= $u['subscription_count'] ?> subscriptions
                                </td>
                                <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <?php if ($u['last_login']): ?>
                                        <?= date('M j, Y', strtotime($u['last_login'])) ?>
                                    <?php else: ?>
                                        <span style="color: #888;">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['id'] !== $user['id']): ?>
                                        <button onclick="showDeleteUserModal(<?= $u['id'] ?>, '<?= addslashes(sanitize($u['full_name'])) ?>', '<?= addslashes(sanitize($u['email'])) ?>', <?= $u['order_count'] ?>, <?= $u['ticket_count'] ?>, <?= $u['subscription_count'] ?>)"
                                                class="btn btn-sm" style="background: #dc143c; border-color: #dc143c;">
                                            Delete
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #888; font-size: 12px;">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delete User Confirmation Modal -->
        <div id="deleteUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a1a; padding: 40px; border-radius: 15px; max-width: 500px; border: 2px solid #dc143c;">
                <h2 style="color: #dc143c; margin-top: 0;">⚠️ Delete User Account</h2>
                <p style="color: #fff; line-height: 1.6; margin: 20px 0;">
                    Are you sure you want to permanently delete this user account?
                </p>
                <div style="background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 5px 0;"><strong>Name:</strong> <span id="deleteUserName"></span></p>
                    <p style="margin: 5px 0;"><strong>Email:</strong> <span id="deleteUserEmail"></span></p>
                </div>
                <p style="color: #dc143c; font-weight: bold; margin: 20px 0;">
                    This will permanently delete:
                </p>
                <ul style="color: #b0b0b0; line-height: 1.8;">
                    <li><span id="deleteUserOrders"></span> order(s)</li>
                    <li><span id="deleteUserTickets"></span> support ticket(s)</li>
                    <li><span id="deleteUserSubscriptions"></span> subscription(s)</li>
                    <li>All user account data</li>
                </ul>
                <p style="color: #fff; margin: 20px 0;">
                    Type <strong style="color: #dc143c;">DELETE</strong> to confirm:
                </p>
                <input type="text" id="deleteUserConfirmText" placeholder="Type DELETE" style="width: 100%; padding: 10px; margin-bottom: 20px; background: #0a0a0a; border: 1px solid #444; color: #fff; border-radius: 5px;">
                <div id="deleteUserError" style="display: none; color: #dc143c; margin-bottom: 15px; padding: 10px; background: rgba(220, 20, 60, 0.2); border-radius: 5px;"></div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="confirmDeleteUser()" class="btn" style="flex: 1; background: #dc143c; border-color: #dc143c;">
                        Yes, Delete Account
                    </button>
                    <button onclick="closeDeleteUserModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <script>
        let userToDelete = null;

        function showDeleteUserModal(userId, name, email, orders, tickets, subscriptions) {
            userToDelete = userId;
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('deleteUserEmail').textContent = email;
            document.getElementById('deleteUserOrders').textContent = orders;
            document.getElementById('deleteUserTickets').textContent = tickets;
            document.getElementById('deleteUserSubscriptions').textContent = subscriptions;
            document.getElementById('deleteUserModal').style.display = 'flex';
            document.getElementById('deleteUserConfirmText').value = '';
            document.getElementById('deleteUserError').style.display = 'none';
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'none';
            userToDelete = null;
        }

        function confirmDeleteUser() {
            const confirmText = document.getElementById('deleteUserConfirmText').value;
            if (confirmText !== 'DELETE') {
                document.getElementById('deleteUserError').textContent = 'You must type DELETE to confirm.';
                document.getElementById('deleteUserError').style.display = 'block';
                return;
            }

            if (!userToDelete) {
                document.getElementById('deleteUserError').textContent = 'Invalid user ID.';
                document.getElementById('deleteUserError').style.display = 'block';
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
                    user_id: userToDelete,
                    confirmed: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User account has been permanently deleted.');
                    window.location.reload();
                } else {
                    document.getElementById('deleteUserError').textContent = data.error || 'Failed to delete account. Please try again.';
                    document.getElementById('deleteUserError').style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Yes, Delete Account';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('deleteUserError').textContent = 'An error occurred. Please try again.';
                document.getElementById('deleteUserError').style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Yes, Delete Account';
            });
        }

        // Close modal when clicking outside
        document.getElementById('deleteUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteUserModal();
            }
        });
        </script>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - <?= SITE_TAGLINE ?></p>
        </div>
    </footer>
</body>
</html>
