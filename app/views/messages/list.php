<?php
/**
 * LUMIRA - My Messages
 * User inbox for viewing all emails sent to them
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

// Check if user is logged in
if (!is_logged_in()) {
    redirect('/login.php?redirect=' . urlencode('/my-messages.php'));
}

$user = get_logged_in_user();
$user_email = $user['email'];

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

// Get database connection
$pdo = get_db();

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $message_id = intval($_POST['message_id']);
    $stmt = $pdo->prepare('UPDATE user_messages SET is_read = TRUE WHERE id = ? AND user_email = ?');
    $stmt->execute([$message_id, $user_email]);
    redirect('/my-messages.php');
}

// Get unread count
$stmt = $pdo->prepare('SELECT COUNT(*) as unread_count FROM user_messages WHERE user_email = ? AND is_read = FALSE');
$stmt->execute([$user_email]);
$unread = $stmt->fetch();
$unread_count = $unread['unread_count'];

// Get all messages for this user
$stmt = $pdo->prepare('
    SELECT *
    FROM user_messages
    WHERE user_email = ?
    ORDER BY created_at DESC
');
$stmt->execute([$user_email]);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?></h1>
                <div class="tagline">My Messages</div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container">
            <h2>📧 My Messages</h2>

            <?php if ($unread_count > 0): ?>
                <div class="alert" style="background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; margin-bottom: 20px;">
                    <p style="margin: 0; font-weight: 600;">
                        You have <?= $unread_count ?> unread message<?= $unread_count > 1 ? 's' : '' ?>
                    </p>
                </div>
            <?php endif; ?>

            <p>All emails and notifications sent to <strong><?= sanitize($user_email) ?></strong></p>

            <?php if (empty($messages)): ?>
                <div class="admin-section" style="text-align: center; padding: 60px 20px;">
                    <p style="font-size: 48px; margin: 0;">📭</p>
                    <h2 style="color: #b0b0b0;">No Messages Yet</h2>
                    <p style="color: #b0b0b0;">You'll see order confirmations, ticket updates, and other notifications here.</p>
                </div>
            <?php else: ?>
                <div class="messages-list">
                    <?php foreach ($messages as $msg): ?>
                        <div class="admin-section" style="<?= !$msg['is_read'] ? 'border-left: 4px solid #dc143c;' : '' ?> margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; flex-wrap: wrap; gap: 15px;">
                                <div style="flex: 1; min-width: 300px;">
                                    <h3 style="margin: 0 0 5px 0; color: #ffffff;">
                                        <?php if (!$msg['is_read']): ?>
                                            <span style="background: #dc143c; color: white; font-size: 10px; padding: 3px 8px; border-radius: 3px; margin-right: 8px;">NEW</span>
                                        <?php endif; ?>
                                        <?= sanitize($msg['subject']) ?>
                                    </h3>
                                    <p style="margin: 0; color: #b0b0b0; font-size: 14px;">
                                        📅 <?= date('F j, Y, g:i A', strtotime($msg['created_at'])) ?>
                                        <?php if ($msg['message_type']): ?>
                                            <span style="margin-left: 10px; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php
                                                $type_emoji = [
                                                    'order' => '🛒',
                                                    'ticket' => '🎫',
                                                    'general' => '📧'
                                                ];
                                                echo $type_emoji[$msg['message_type']] ?? '📧';
                                                echo ' ' . ucfirst($msg['message_type']);
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <a href="/message-view.php?id=<?= $msg['id'] ?>" class="btn btn-sm">
                                        View Message
                                    </a>
                                    <?php if (!$msg['is_read']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm" style="background: #6c757d;">
                                                Mark Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($msg['related_id']): ?>
                                <p style="margin: 10px 0 0 0; font-size: 14px; color: #b0b0b0;">
                                    <?php if ($msg['message_type'] === 'order'): ?>
                                        Related Order: <a href="/order-view.php?id=<?= $msg['related_id'] ?>" style="color: #dc143c;">View Order</a>
                                    <?php elseif ($msg['message_type'] === 'ticket'): ?>
                                        Related Ticket: <a href="/ticket-view.php?id=<?= $msg['related_id'] ?>" style="color: #dc143c;">View Ticket</a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
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
