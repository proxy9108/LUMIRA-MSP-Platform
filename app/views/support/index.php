<?php
/**
 * LUMIRA - Support Center
 * Unified page for creating and viewing support tickets
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

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = '/support.php';
    redirect('/login.php');
}

$user = get_logged_in_user();
$message = '';
$error = '';
$show_form = isset($_GET['create']) || isset($_POST['create_ticket']);

// Handle ticket creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $priority_id = (int)($_POST['priority_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Validation
        if (empty($subject)) {
            $error = 'Please enter a subject for your ticket.';
        } elseif (empty($description)) {
            $error = 'Please describe your issue.';
        } elseif ($category_id <= 0) {
            $error = 'Please select a category.';
        } else {
            try {
                $pdo = get_db();
                $pdo->beginTransaction();

                // Generate ticket number
                $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

                // Get default status
                $statusStmt = $pdo->prepare('SELECT id FROM ticket_statuses WHERE name = ? LIMIT 1');
                $statusStmt->execute(['New']);
                $status_id = $statusStmt->fetchColumn() ?: 1;

                // Use selected priority or default to Medium
                if ($priority_id <= 0) {
                    $priorityStmt = $pdo->prepare('SELECT id FROM ticket_priorities WHERE name = ? LIMIT 1');
                    $priorityStmt->execute(['Medium']);
                    $priority_id = $priorityStmt->fetchColumn() ?: 3;
                }

                // Create ticket
                $stmt = $pdo->prepare('
                    INSERT INTO tickets (
                        ticket_number, requester_id, category_id, priority_id, status_id,
                        subject, description, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    RETURNING id
                ');
                $stmt->execute([
                    $ticket_number,
                    $user['id'],
                    $category_id,
                    $priority_id,
                    $status_id,
                    $subject,
                    $description
                ]);
                $ticket_id = $stmt->fetchColumn();

                $pdo->commit();

                // Send confirmation email
                $ticket_data = [
                    'id' => $ticket_id,
                    'ticket_number' => $ticket_number,
                    'customer_name' => $user['full_name'],
                    'customer_email' => $user['email'],
                    'customer_phone' => $user['phone'] ?? '',
                    'subject' => $subject,
                    'description' => $description,
                    'service_name' => '',
                    'status_name' => 'New',
                    'priority_name' => 'Medium',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                send_ticket_confirmation($ticket_data);

                // Notify staff/workers about new ticket
                notify_staff_ticket_update(
                    $ticket_data,
                    'new_ticket',
                    'New ticket created by customer: ' . $customer_name . "\n\nSubject: " . $subject . "\n\nDescription: " . $description
                );

                $message = 'Ticket created successfully! Ticket #' . $ticket_number;
                $show_form = false; // Hide form after successful submission

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to create ticket. Please try again.';
                error_log('Ticket creation error: ' . $e->getMessage());
            }
        }
    }
}

// Fetch categories and priorities for the form
try {
    $pdo = get_db();
    $categories = $pdo->query('SELECT * FROM ticket_categories ORDER BY name')->fetchAll();
    $priorities = $pdo->query('SELECT * FROM ticket_priorities ORDER BY id')->fetchAll();

    // Check if user is admin/support agent
    $is_admin = is_user_admin();

    if ($is_admin) {
        // Admins see: assigned tickets + unassigned tickets
        $tickets = get_admin_tickets($pdo, $user['id']);
    } else {
        // Customers see: their own tickets
        $tickets = get_user_tickets($pdo, $user['id']);
    }
} catch (PDOException $e) {
    $error = 'Unable to load support data. Please try again later.';
    $categories = [];
    $priorities = [];
    $tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Center - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        .support-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .support-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dc143c;
            padding-bottom: 10px;
        }

        .support-tabs a {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s;
        }

        .support-tabs a:hover {
            background: rgba(220, 20, 60, 0.2);
        }

        .support-tabs a.active {
            background: #dc143c;
            font-weight: 600;
        }

        .ticket-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #dc143c;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #dc143c;
        }

        .stat-label {
            color: #b0b0b0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?></h1>
                <div class="tagline">Support Center</div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container">
            <div class="support-header">
                <h2>🎫 <?= $is_admin ? 'Support Queue' : 'Support Center' ?></h2>
                <?php if (!$show_form && !$is_admin): ?>
                    <!-- Only customers can create tickets -->
                    <a href="/support.php?create=1" class="btn">📝 Create New Ticket</a>
                <?php elseif ($show_form): ?>
                    <a href="/support.php" class="btn" style="background: #6c757d;">← Back to Tickets</a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert" style="background: #28a745; color: white; margin-bottom: 20px;">
                    ✅ <?= sanitize($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert" style="background: #dc3545; color: white; margin-bottom: 20px;">
                    ❌ <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <!-- CREATE TICKET FORM -->
                <div class="admin-section">
                    <h3>Create Support Ticket</h3>
                    <p style="color: #b0b0b0; margin-bottom: 20px;">
                        Need help? Submit a support ticket and our team will assist you.
                    </p>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" required>
                                <option value="">Select a category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority_id">
                                <option value="0">Medium (Default)</option>
                                <?php foreach ($priorities as $priority): ?>
                                    <option value="<?= $priority['id'] ?>" <?= (isset($_POST['priority_id']) && $_POST['priority_id'] == $priority['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($priority['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" name="subject" required
                                   value="<?= sanitize($_POST['subject'] ?? '') ?>"
                                   placeholder="Brief description of your issue"
                                   maxlength="200">
                        </div>

                        <div class="form-group">
                            <label>Description *</label>
                            <textarea name="description" required rows="6"
                                      placeholder="Please provide details about your issue..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="create_ticket" class="btn">Submit Ticket</button>
                            <a href="/support.php" class="btn" style="background: #6c757d;">Cancel</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- MY TICKETS VIEW -->

                <!-- Ticket Statistics -->
                <?php
                $total_tickets = count($tickets);
                $open_tickets = count(array_filter($tickets, function($t) {
                    return in_array(strtolower($t['status_name'] ?? ''), ['new', 'open', 'in progress']);
                }));
                $closed_tickets = $total_tickets - $open_tickets;
                ?>

                <div class="ticket-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_tickets ?></div>
                        <div class="stat-label">Total Tickets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $open_tickets ?></div>
                        <div class="stat-label">Open Tickets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $closed_tickets ?></div>
                        <div class="stat-label">Closed Tickets</div>
                    </div>
                </div>

                <!-- Tickets List -->
                <?php if (empty($tickets)): ?>
                    <div class="admin-section" style="text-align: center; padding: 60px 20px;">
                        <p style="font-size: 48px; margin: 0;">📋</p>
                        <h3 style="color: #b0b0b0;">No Support Tickets Yet</h3>
                        <p style="color: #b0b0b0; margin-bottom: 20px;">
                            Have a question or need assistance? Create your first support ticket.
                        </p>
                        <a href="/support.php?create=1" class="btn">Create Your First Ticket</a>
                    </div>
                <?php else: ?>
                    <div class="admin-section">
                        <h3><?= $is_admin ? 'Support Queue' : 'My Support Tickets' ?> (<?= count($tickets) ?>)</h3>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <?php if ($is_admin): ?>
                                        <th>Customer</th>
                                    <?php endif; ?>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <?php if ($is_admin): ?>
                                        <th>Assigned To</th>
                                    <?php endif; ?>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                <tr style="<?= $ticket['assigned_to_id'] ? '' : 'background: rgba(220, 20, 60, 0.1);' ?>">
                                    <td><strong><?= sanitize($ticket['ticket_number']) ?></strong></td>
                                    <?php if ($is_admin): ?>
                                        <td>
                                            <?= sanitize($ticket['requester_name'] ?? 'Unknown') ?><br>
                                            <small style="color: #888;"><?= sanitize($ticket['requester_email'] ?? '') ?></small>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= sanitize($ticket['subject']) ?></td>
                                    <td><?= sanitize($ticket['category_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span style="color: <?= sanitize($ticket['priority_color'] ?? '#999') ?>;">
                                            <?= sanitize($ticket['priority_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: <?= sanitize($ticket['status_color'] ?? '#999') ?>;">
                                            <?= sanitize($ticket['status_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <?php if ($is_admin): ?>
                                        <td>
                                            <?php if ($ticket['assigned_to_id']): ?>
                                                <?= sanitize($ticket['assigned_to_name']) ?>
                                            <?php else: ?>
                                                <span style="color: #dc143c; font-weight: 600;">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= date('M j, Y', strtotime($ticket['created_at'])) ?></td>
                                    <td>
                                        <a href="/ticket-view.php?id=<?= $ticket['id'] ?>" class="btn btn-sm">View</a>
                                        <?php if ($is_admin && !$ticket['assigned_to_id']): ?>
                                            <a href="/ticket-view.php?id=<?= $ticket['id'] ?>&assign_to_me=1" class="btn btn-sm" style="background: #28a745;">Assign to Me</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>
