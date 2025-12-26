<?php
/**
 * LUMIRA - Admin Ticket View & Management
 * Allows admins to view, respond to, and manage tickets
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/config/email.php';

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
$ticket_id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

if ($ticket_id <= 0) {
    redirect('/dashboard-admin.php');
}

$pdo = get_db();

// Handle ticket updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $pdo->beginTransaction();

            // Handle status/priority update
            if (isset($_POST['update_ticket'])) {
                $status_id = (int)$_POST['status_id'];
                $priority_id = (int)$_POST['priority_id'];
                $assigned_to_id = $_POST['assigned_to_id'] !== '' ? (int)$_POST['assigned_to_id'] : null;

                $stmt = $pdo->prepare('
                    UPDATE tickets
                    SET status_id = ?, priority_id = ?, assigned_to_id = ?, updated_at = NOW()
                    WHERE id = ?
                ');
                $stmt->execute([$status_id, $priority_id, $assigned_to_id, $ticket_id]);

                // Log history
                $stmt = $pdo->prepare('
                    INSERT INTO ticket_history (ticket_id, user_id, field_changed, new_value, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$ticket_id, $user['id'], 'ticket_updated', 'Status/Priority/Assignment changed']);

                $message = 'Ticket updated successfully!';
            }

            // Handle admin comment/response
            if (isset($_POST['add_response']) && !empty(trim($_POST['response']))) {
                $response = trim($_POST['response']);
                $is_internal = isset($_POST['internal_note']) ? 1 : 0;

                $stmt = $pdo->prepare('
                    INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$ticket_id, $user['id'], $response, $is_internal]);

                // Update ticket timestamp
                $stmt = $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?');
                $stmt->execute([$ticket_id]);

                // If not internal note, send email to customer
                if (!$is_internal) {
                    // Get ticket details for email
                    $stmt = $pdo->prepare('
                        SELECT t.*, u.email as requester_email, u.full_name as requester_name,
                               ts.name as status_name, tp.name as priority_name
                        FROM tickets t
                        JOIN users u ON t.requester_id = u.id
                        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
                        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
                        WHERE t.id = ?
                    ');
                    $stmt->execute([$ticket_id]);
                    $ticket_data = $stmt->fetch();

                    if ($ticket_data) {
                        send_ticket_update($ticket_data, $response);
                    }
                }

                $message = $is_internal ? 'Internal note added.' : 'Response sent to customer.';
            }

            // Handle close ticket
            if (isset($_POST['close_ticket'])) {
                $stmt = $pdo->prepare('SELECT id FROM ticket_statuses WHERE name = ? LIMIT 1');
                $stmt->execute(['Closed']);
                $closed_status_id = $stmt->fetchColumn();

                if ($closed_status_id) {
                    $stmt = $pdo->prepare('
                        UPDATE tickets
                        SET status_id = ?, closed_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ');
                    $stmt->execute([$closed_status_id, $ticket_id]);

                    // Log history
                    $stmt = $pdo->prepare('
                        INSERT INTO ticket_history (ticket_id, user_id, field_changed, new_value, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$ticket_id, $user['id'], 'status', 'Closed']);

                    $message = 'Ticket closed successfully!';
                }
            }

            // Handle reopen ticket
            if (isset($_POST['reopen_ticket'])) {
                $stmt = $pdo->prepare('SELECT id FROM ticket_statuses WHERE name = ? LIMIT 1');
                $stmt->execute(['In Progress']);
                $reopen_status_id = $stmt->fetchColumn();

                if ($reopen_status_id) {
                    $stmt = $pdo->prepare('
                        UPDATE tickets
                        SET status_id = ?, closed_at = NULL, updated_at = NOW()
                        WHERE id = ?
                    ');
                    $stmt->execute([$reopen_status_id, $ticket_id]);

                    // Log history
                    $stmt = $pdo->prepare('
                        INSERT INTO ticket_history (ticket_id, user_id, field_changed, new_value, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$ticket_id, $user['id'], 'status', 'Reopened']);

                    $message = 'Ticket reopened!';
                }
            }

            // Handle archive ticket
            if (isset($_POST['archive_ticket'])) {
                $stmt = $pdo->prepare('
                    UPDATE tickets
                    SET is_archived = true, updated_at = NOW()
                    WHERE id = ?
                ');
                $stmt->execute([$ticket_id]);

                // Log history
                $stmt = $pdo->prepare('
                    INSERT INTO ticket_history (ticket_id, user_id, field_changed, new_value, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$ticket_id, $user['id'], 'archived', 'true']);

                $message = 'Ticket archived successfully!';
            }

            // Handle unarchive ticket
            if (isset($_POST['unarchive_ticket'])) {
                $stmt = $pdo->prepare('
                    UPDATE tickets
                    SET is_archived = false, updated_at = NOW()
                    WHERE id = ?
                ');
                $stmt->execute([$ticket_id]);

                // Log history
                $stmt = $pdo->prepare('
                    INSERT INTO ticket_history (ticket_id, user_id, field_changed, new_value, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$ticket_id, $user['id'], 'archived', 'false']);

                $message = 'Ticket unarchived successfully!';
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to update ticket: ' . $e->getMessage();
            error_log('Admin ticket update error: ' . $e->getMessage());
        }
    }
}

// Fetch ticket details
try {
    $stmt = $pdo->prepare('
        SELECT t.*,
               tc.name as category_name,
               tp.name as priority_name, tp.color_code as priority_color,
               ts.name as status_name, ts.color_code as status_color,
               u.full_name as requester_name, u.email as requester_email, u.phone as requester_phone,
               a.full_name as assigned_to_name
        FROM tickets t
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users a ON t.assigned_to_id = a.id
        WHERE t.id = ?
    ');
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        $error = 'Ticket not found.';
    } else {
        // Get comments
        $stmt = $pdo->prepare('
            SELECT tc.*, u.full_name as user_name, r.name as user_role
            FROM ticket_comments tc
            LEFT JOIN users u ON tc.user_id = u.id
            LEFT JOIN app_roles r ON u.role_id = r.id
            WHERE tc.ticket_id = ?
            ORDER BY tc.created_at ASC
        ');
        $stmt->execute([$ticket_id]);
        $comments = $stmt->fetchAll();

        // Get history
        $stmt = $pdo->prepare('
            SELECT th.*, u.full_name as user_name
            FROM ticket_history th
            LEFT JOIN users u ON th.user_id = u.id
            WHERE th.ticket_id = ?
            ORDER BY th.created_at DESC
            LIMIT 20
        ');
        $stmt->execute([$ticket_id]);
        $history = $stmt->fetchAll();

        // Get all statuses for dropdown
        $stmt = $pdo->query('SELECT * FROM ticket_statuses ORDER BY id');
        $statuses = $stmt->fetchAll();

        // Get all priorities for dropdown
        $stmt = $pdo->query('SELECT * FROM ticket_priorities ORDER BY id');
        $priorities = $stmt->fetchAll();

        // Get staff users for assignment
        $stmt = $pdo->prepare('
            SELECT u.id, u.full_name, r.name as role_name
            FROM users u
            JOIN app_roles r ON u.role_id = r.id
            WHERE r.name IN (?, ?, ?)
            ORDER BY u.full_name
        ');
        $stmt->execute(['super_admin', 'admin', 'manager']);
        $staff = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = 'Unable to load ticket: ' . $e->getMessage();
    error_log('Admin ticket view error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket ? sanitize($ticket['ticket_number']) : 'Not Found' ?> - Admin</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        .ticket-header {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .ticket-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .ticket-meta-item {
            padding: 15px;
            background: rgba(26, 26, 26, 0.6);
            border-radius: 10px;
            border-left: 3px solid var(--primary);
        }
        .comment {
            background: rgba(26, 26, 26, 0.6);
            border-left: 3px solid var(--primary);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .comment.internal {
            border-left-color: #ffaa00;
            background: rgba(255, 170, 0, 0.1);
        }
        .comment.staff {
            border-left-color: #00ff88;
        }
        .admin-actions {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?> - Admin Portal</h1>
                <div class="tagline">Ticket Management</div>
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

            <?php if ($ticket): ?>
                <!-- Ticket Header -->
                <div class="ticket-header">
                    <h2 style="margin-bottom: 10px;">Ticket #<?= sanitize($ticket['ticket_number']) ?></h2>
                    <h3 style="color: white; font-size: 24px;"><?= sanitize($ticket['subject']) ?></h3>

                    <div class="ticket-meta">
                        <div class="ticket-meta-item">
                            <div style="font-size: 12px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 5px;">Customer</div>
                            <div style="font-size: 16px; font-weight: 600;"><?= sanitize($ticket['requester_name']) ?></div>
                            <div style="font-size: 14px; color: var(--text-secondary);"><?= sanitize($ticket['requester_email']) ?></div>
                            <?php if ($ticket['requester_phone']): ?>
                            <div style="font-size: 14px; color: var(--text-secondary);"><?= sanitize($ticket['requester_phone']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="ticket-meta-item">
                            <div style="font-size: 12px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 5px;">Status</div>
                            <div style="font-size: 16px; font-weight: 600; color: <?= sanitize($ticket['status_color'] ?? '#999') ?>;">
                                <?= sanitize($ticket['status_name'] ?? 'N/A') ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div style="font-size: 12px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 5px;">Priority</div>
                            <div style="font-size: 16px; font-weight: 600; color: <?= sanitize($ticket['priority_color'] ?? '#999') ?>;">
                                <?= sanitize($ticket['priority_name'] ?? 'N/A') ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div style="font-size: 12px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 5px;">Category</div>
                            <div style="font-size: 16px; font-weight: 600;">
                                <?= sanitize($ticket['category_name'] ?? 'General') ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div style="font-size: 12px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 5px;">Assigned To</div>
                            <div style="font-size: 16px; font-weight: 600;">
                                <?= sanitize($ticket['assigned_to_name'] ?? 'Unassigned') ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div style="font-size: 12px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 5px;">Created</div>
                            <div style="font-size: 16px; font-weight: 600;">
                                <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Actions -->
                <div class="admin-actions">
                    <h3 style="margin-bottom: 20px;">🛠 Ticket Management</h3>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status_id">
                                    <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" <?= $status['id'] == $ticket['status_id'] ? 'selected' : '' ?>>
                                        <?= sanitize($status['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Priority</label>
                                <select name="priority_id">
                                    <?php foreach ($priorities as $priority): ?>
                                    <option value="<?= $priority['id'] ?>" <?= $priority['id'] == $ticket['priority_id'] ? 'selected' : '' ?>>
                                        <?= sanitize($priority['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Assign To</label>
                                <select name="assigned_to_id">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($staff as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $ticket['assigned_to_id'] ? 'selected' : '' ?>>
                                        <?= sanitize($s['full_name']) ?> (<?= strtoupper($s['role_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="submit" name="update_ticket" class="btn">💾 Update Ticket</button>

                            <?php if ($ticket['status_name'] !== 'Closed'): ?>
                            <button type="submit" name="close_ticket" class="btn btn-danger" onclick="return confirm('Close this ticket?');">🔒 Close Ticket</button>
                            <?php else: ?>
                            <button type="submit" name="reopen_ticket" class="btn btn-success">🔓 Reopen Ticket</button>
                            <?php endif; ?>

                            <?php if ($ticket['is_archived']): ?>
                            <button type="submit" name="unarchive_ticket" class="btn btn-secondary">📤 Unarchive</button>
                            <?php else: ?>
                            <button type="submit" name="archive_ticket" class="btn btn-secondary" onclick="return confirm('Archive this ticket? It will be hidden from the main dashboard.');">📦 Archive</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Ticket Description -->
                <div style="background: rgba(36, 36, 36, 0.8); border: 1px solid rgba(220, 20, 60, 0.4); border-radius: 15px; padding: 25px; margin-bottom: 30px;">
                    <h3 style="margin-bottom: 15px;">📋 Original Request</h3>
                    <div style="line-height: 1.7; white-space: pre-wrap;"><?= nl2br(sanitize($ticket['description'])) ?></div>
                </div>

                <!-- Comments & Responses -->
                <div style="background: rgba(36, 36, 36, 0.8); border: 1px solid rgba(220, 20, 60, 0.4); border-radius: 15px; padding: 25px; margin-bottom: 30px;">
                    <h3 style="margin-bottom: 20px;">💬 Conversation (<?= count($comments) ?>)</h3>

                    <?php if (empty($comments)): ?>
                        <p style="color: var(--text-secondary);">No responses yet.</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment <?= $comment['is_internal'] ? 'internal' : '' ?> <?= in_array($comment['user_role'], ['admin', 'super_admin', 'manager']) ? 'staff' : '' ?>">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span style="font-weight: 600; color: var(--primary);">
                                    <?= sanitize($comment['user_name'] ?? 'Unknown') ?>
                                    <?php if ($comment['is_internal']): ?>
                                    <span style="background: #ffaa00; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px;">INTERNAL</span>
                                    <?php endif; ?>
                                </span>
                                <span style="color: var(--text-secondary); font-size: 12px;"><?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <div style="white-space: pre-wrap;"><?= nl2br(sanitize($comment['comment'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Add Response Form -->
                    <form method="POST" action="" style="margin-top: 30px; border-top: 2px solid rgba(220, 20, 60, 0.3); padding-top: 20px;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="form-group">
                            <label>Add Response</label>
                            <textarea name="response" rows="5" required placeholder="Type your response to the customer..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="internal_note" value="1">
                                <span style="margin-left: 8px;">Internal Note (not visible to customer)</span>
                            </label>
                        </div>

                        <button type="submit" name="add_response" class="btn">📤 Send Response</button>
                    </form>
                </div>

                <!-- Ticket History -->
                <?php if (!empty($history)): ?>
                <div style="background: rgba(36, 36, 36, 0.8); border: 1px solid rgba(220, 20, 60, 0.4); border-radius: 15px; padding: 25px;">
                    <h3 style="margin-bottom: 20px;">📜 Activity History</h3>
                    <table class="admin-table" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= date('M j, g:i A', strtotime($h['created_at'])) ?></td>
                                <td><?= sanitize($h['user_name'] ?? 'System') ?></td>
                                <td><span style="text-transform: uppercase; font-weight: 600;"><?= sanitize($h['action']) ?></span></td>
                                <td><?= sanitize($h['details'] ?? '') ?></td>
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
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - <?= SITE_TAGLINE ?></p>
        </div>
    </footer>
</body>
</html>
