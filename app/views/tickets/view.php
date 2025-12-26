<?php
/**
 * LUMIRA - Ticket Detail View
 * View individual ticket details and comments
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

// Require login
if (!is_logged_in()) {
    redirect('/login.php');
}

$user = get_logged_in_user();
$ticket_id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';
$is_admin = is_user_admin();

// Handle ticket assignment
if (isset($_GET['assign_to_me']) && $is_admin && $ticket_id > 0) {
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('UPDATE tickets SET assigned_to_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$user['id'], $ticket_id]);
        $message = 'Ticket assigned to you successfully!';
        // Redirect to remove the query parameter
        header('Location: /ticket-view.php?id=' . $ticket_id);
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to assign ticket. Please try again.';
        error_log('Ticket assignment error: ' . $e->getMessage());
    }
}

// Handle ticket unassignment
if (isset($_GET['unassign']) && $is_admin && $ticket_id > 0) {
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('UPDATE tickets SET assigned_to_id = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$ticket_id]);
        $message = 'Ticket unassigned successfully!';
        // Redirect to remove the query parameter
        header('Location: /ticket-view.php?id=' . $ticket_id);
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to unassign ticket. Please try again.';
        error_log('Ticket unassignment error: ' . $e->getMessage());
    }
}

// Handle assigning to specific staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_to_staff']) && $is_admin && $ticket_id > 0) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $assign_to_id = (int)($_POST['assign_to_id'] ?? 0);
        try {
            $pdo = get_db();
            if ($assign_to_id === 0) {
                // Unassign
                $stmt = $pdo->prepare('UPDATE tickets SET assigned_to_id = NULL, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$ticket_id]);
                $message = 'Ticket unassigned successfully!';
            } else {
                // Assign to specific staff
                $stmt = $pdo->prepare('UPDATE tickets SET assigned_to_id = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$assign_to_id, $ticket_id]);
                $message = 'Ticket assigned successfully!';
            }
            // Redirect to clean URL
            header('Location: /ticket-view.php?id=' . $ticket_id);
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to assign ticket. Please try again.';
            error_log('Ticket assignment error: ' . $e->getMessage());
        }
    }
}

// Fetch ticket details
try {
    $pdo = get_db();

    // Get ticket (ensure user owns it or is admin/support staff)
    $stmt = $pdo->prepare('
        SELECT t.*,
               tc.name as category_name,
               tp.name as priority_name, tp.color_code as priority_color,
               ts.name as status_name, ts.color_code as status_color,
               u.full_name as requester_name,
               u.email as requester_email,
               a.full_name as assigned_to_name
        FROM tickets t
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users a ON t.assigned_to_id = a.id
        WHERE t.id = ?
        AND (
            t.requester_id = ?                                    -- User created the ticket
            OR EXISTS (                                            -- OR user is admin/support staff
                SELECT 1 FROM users u2
                JOIN app_roles r ON u2.role_id = r.id
                WHERE u2.id = ?
                AND r.name IN (\'super_admin\', \'admin\', \'manager\', \'technician\')
            )
        )
    ');
    $stmt->execute([$ticket_id, $user['id'], $user['id']]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        $error = 'Ticket not found or access denied.';
        $comments = [];
        $staff_members = [];
    } else {
        // Get comments (exclude internal notes)
        $stmt = $pdo->prepare('
            SELECT tc.*, u.full_name as user_name
            FROM ticket_comments tc
            LEFT JOIN users u ON tc.user_id = u.id
            WHERE tc.ticket_id = ? AND tc.is_internal = false
            ORDER BY tc.created_at ASC
        ');
        $stmt->execute([$ticket_id]);
        $comments = $stmt->fetchAll();

        // Get all staff members (admins, managers, technicians) for assignment dropdown
        if ($is_admin) {
            $stmt = $pdo->query("
                SELECT u.id, u.full_name, u.email, r.display_name as role_display
                FROM users u
                JOIN app_roles r ON u.role_id = r.id
                WHERE r.name IN ('super_admin', 'admin', 'manager', 'technician')
                AND u.is_active = TRUE
                ORDER BY r.name, u.full_name
            ");
            $staff_members = $stmt->fetchAll();
        } else {
            $staff_members = [];
        }
    }
} catch (PDOException $e) {
    $error = 'Unable to load ticket. Please try again later.';
    error_log('Ticket view error: ' . $e->getMessage());
    $ticket = null;
    $comments = [];
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && $ticket) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $comment = trim($_POST['comment'] ?? '');

        if (empty($comment)) {
            $error = 'Please enter a comment.';
        } else {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at)
                    VALUES (?, ?, ?, false, NOW())
                ');
                $stmt->execute([$ticket_id, $user['id'], $comment]);

                // Update ticket updated_at
                $stmt = $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?');
                $stmt->execute([$ticket_id]);

                $message = 'Comment added successfully!';

                // Reload comments (exclude internal notes)
                $stmt = $pdo->prepare('
                    SELECT tc.*, u.full_name as user_name
                    FROM ticket_comments tc
                    LEFT JOIN users u ON tc.user_id = u.id
                    WHERE tc.ticket_id = ? AND tc.is_internal = false
                    ORDER BY tc.created_at ASC
                ');
                $stmt->execute([$ticket_id]);
                $comments = $stmt->fetchAll();

                $_POST = []; // Clear form
            } catch (PDOException $e) {
                $error = 'Failed to add comment. Please try again.';
                error_log('Comment error: ' . $e->getMessage());
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
    <title><?= $ticket ? 'Ticket #' . sanitize($ticket['ticket_number']) : 'Ticket Not Found' ?> - <?= SITE_NAME ?></title>
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

        .ticket-meta-label {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .ticket-meta-value {
            font-size: 16px;
            font-weight: 600;
        }

        .ticket-description {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .comments-section {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .comment {
            background: rgba(26, 26, 26, 0.6);
            border-left: 3px solid var(--primary);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .comment-author {
            font-weight: 600;
            color: var(--primary);
        }

        .comment-date {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .comment-body {
            line-height: 1.6;
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
            <div style="margin-bottom: 20px;">
                <a href="/support.php" class="btn btn-secondary">← Back to Tickets</a>
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
                            <div class="ticket-meta-label">Status</div>
                            <div class="ticket-meta-value" style="color: <?= sanitize($ticket['status_color'] ?? '#999') ?>;">
                                <?= sanitize($ticket['status_name'] ?? 'N/A') ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div class="ticket-meta-label">Priority</div>
                            <div class="ticket-meta-value" style="color: <?= sanitize($ticket['priority_color'] ?? '#999') ?>;">
                                <?= sanitize($ticket['priority_name'] ?? 'N/A') ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div class="ticket-meta-label">Category</div>
                            <div class="ticket-meta-value">
                                <?= sanitize($ticket['category_name'] ?? 'N/A') ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div class="ticket-meta-label">Created</div>
                            <div class="ticket-meta-value">
                                <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div class="ticket-meta-label">Assigned To</div>
                            <div class="ticket-meta-value">
                                <?php if ($ticket['assigned_to_name']): ?>
                                    <?= sanitize($ticket['assigned_to_name']) ?>
                                <?php else: ?>
                                    <span style="color: #dc143c;">Unassigned</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ticket-meta-item">
                            <div class="ticket-meta-label">Requester</div>
                            <div class="ticket-meta-value">
                                <?= sanitize($ticket['requester_name']) ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                    <div style="margin-top: 20px; padding: 15px; background: rgba(220, 20, 60, 0.1); border-radius: 8px; border: 1px solid rgba(220, 20, 60, 0.3);">
                        <strong style="display: block; margin-bottom: 15px;">🛠 Admin Actions:</strong>

                        <!-- Quick Assign to Me Button -->
                        <div style="margin-bottom: 15px;">
                            <?php if (!$ticket['assigned_to_id']): ?>
                                <a href="/ticket-view.php?id=<?= $ticket['id'] ?>&assign_to_me=1" class="btn" style="background: #28a745;">
                                    ✓ Assign to Me
                                </a>
                            <?php elseif ($ticket['assigned_to_id'] == $user['id']): ?>
                                <a href="/ticket-view.php?id=<?= $ticket['id'] ?>&unassign=1" class="btn" style="background: #6c757d;">
                                    ✗ Unassign Ticket
                                </a>
                            <?php else: ?>
                                <a href="/ticket-view.php?id=<?= $ticket['id'] ?>&assign_to_me=1" class="btn" style="background: #17a2b8;">
                                    ↔ Reassign to Me
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Assign to Specific Staff Member -->
                        <form method="POST" action="" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                            <div style="flex: 1; min-width: 250px;">
                                <label style="display: block; margin-bottom: 5px; font-size: 14px; color: #ffffff;">Assign to Staff Member:</label>
                                <select name="assign_to_id" class="form-control" style="width: 100%; padding: 8px; background: rgba(26, 26, 26, 0.8); border: 1px solid rgba(220, 20, 60, 0.3); color: #ffffff; border-radius: 5px;">
                                    <option value="0">-- Unassigned --</option>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?= $staff['id'] ?>" <?= $ticket['assigned_to_id'] == $staff['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($staff['full_name']) ?> (<?= sanitize($staff['role_display']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" name="assign_to_staff" class="btn" style="background: #17a2b8;">
                                👤 Assign Ticket
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="ticket-description">
                    <h3 style="margin-bottom: 15px;">Description</h3>
                    <p style="line-height: 1.7;"><?= nl2br(sanitize($ticket['description'])) ?></p>
                </div>

                <!-- Comments -->
                <div class="comments-section">
                    <h3 style="margin-bottom: 20px;">💬 Comments (<?= count($comments) ?>)</h3>

                    <?php if (empty($comments)): ?>
                        <p style="color: var(--text-secondary);">No comments yet. Be the first to add one!</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <span class="comment-author"><?= sanitize($comment['user_name'] ?? 'Unknown') ?></span>
                                <span class="comment-date"><?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <div class="comment-body"><?= nl2br(sanitize($comment['comment'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Add Comment Form -->
                    <form method="POST" action="" style="margin-top: 30px;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="form-group">
                            <label>Add Comment</label>
                            <textarea name="comment" rows="4" required
                                      placeholder="Type your comment here..."><?= sanitize($_POST['comment'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" name="add_comment" class="btn">Post Comment</button>
                    </form>
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
