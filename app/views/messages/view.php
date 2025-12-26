<?php
/**
 * LUMIRA - Message View
 * Display single message
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

// Check if user is logged in
if (!is_logged_in()) {
    redirect('/login.php');
}

$user = get_logged_in_user();
$user_email = $user['email'];
$message_id = intval($_GET['id'] ?? 0);

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

// Get database connection
$pdo = get_db();

// Get message - must belong to this user
$stmt = $pdo->prepare('SELECT * FROM user_messages WHERE id = ? AND user_email = ?');
$stmt->execute([$message_id, $user_email]);
$message = $stmt->fetch();

if (!$message) {
    redirect('/my-messages.php');
}

// Mark as read
if (!$message['is_read']) {
    $stmt = $pdo->prepare('UPDATE user_messages SET is_read = TRUE WHERE id = ?');
    $stmt->execute([$message_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($message['subject']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        /* Override the grey background - make it black like other pages */
        body {
            background: #000000 !important;
        }

        main {
            background: #000000 !important;
        }

        /* Email container with white background */
        .message-body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background: #ffffff !important;
            padding: 0 !important;
            border-radius: 8px;
            overflow: hidden;
        }

        /* Override all email table backgrounds to white */
        .message-body table {
            background: #ffffff !important;
            width: 100%;
            max-width: 100%;
        }

        /* Override email template colors for readability */
        .message-body * {
            color: #1a1a1a !important;
        }

        .message-body h1,
        .message-body h2,
        .message-body h3 {
            color: #dc143c !important;
        }

        .message-body a {
            color: #dc143c !important;
            text-decoration: underline;
        }

        /* Table headers with red background */
        .message-body table thead,
        .message-body table thead tr,
        .message-body table thead th {
            background: #dc143c !important;
            color: #ffffff !important;
        }

        .message-body table thead * {
            color: #ffffff !important;
        }

        /* Table footer (total row) */
        .message-body table tfoot * {
            color: #1a1a1a !important;
        }

        .message-body img {
            max-width: 100%;
            height: auto;
        }

        /* Yellow box for staff info */
        .message-body div[style*="background: #fff3cd"],
        .message-body div[style*="#fff3cd"] {
            background: #fff3cd !important;
            color: #856404 !important;
        }

        .message-body div[style*="background: #fff3cd"] *,
        .message-body div[style*="#fff3cd"] * {
            color: #856404 !important;
        }

        /* Footer in email */
        .message-body td[style*="background-color: #1a1a1a"],
        .message-body td[style*="#1a1a1a"] {
            background: #1a1a1a !important;
        }

        .message-body td[style*="background-color: #1a1a1a"] *,
        .message-body td[style*="#1a1a1a"] * {
            color: #b0b0b0 !important;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?></h1>
                <div class="tagline">Message View</div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container">
            <div style="margin-bottom: 20px;">
                <a href="/my-messages.php" style="color: #dc143c; text-decoration: none; font-size: 16px;">
                    ← Back to My Messages
                </a>
            </div>

            <div class="admin-section">
                <!-- Message Header -->
                <div style="border-bottom: 2px solid #dc143c; padding-bottom: 20px; margin-bottom: 20px;">
                    <h2 style="margin: 0 0 10px 0; color: #ffffff;">
                        <?= sanitize($message['subject']) ?>
                    </h2>
                    <div style="color: #b0b0b0; font-size: 14px;">
                        <p style="margin: 5px 0;">
                            📅 <strong>Received:</strong> <?= date('F j, Y, g:i A', strtotime($message['created_at'])) ?>
                        </p>
                        <p style="margin: 5px 0;">
                            📧 <strong>To:</strong> <?= sanitize($message['user_email']) ?>
                        </p>
                        <?php if ($message['message_type']): ?>
                            <p style="margin: 5px 0;">
                                🏷️ <strong>Type:</strong>
                                <span style="background: rgba(255,255,255,0.1); padding: 3px 10px; border-radius: 3px; font-size: 12px;">
                                    <?php
                                    $type_emoji = [
                                        'order' => '🛒 Order Confirmation',
                                        'ticket' => '🎫 Support Ticket',
                                        'general' => '📧 General Message'
                                    ];
                                    echo $type_emoji[$message['message_type']] ?? '📧 Message';
                                    ?>
                                </span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Related Links -->
                <?php if ($message['related_id']): ?>
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <p style="margin: 0; font-weight: 600;">
                            <?php if ($message['message_type'] === 'order'): ?>
                                🛒 <a href="/order-view.php?id=<?= $message['related_id'] ?>" style="color: #dc143c;">View Order Details</a>
                            <?php elseif ($message['message_type'] === 'ticket'): ?>
                                🎫 <a href="/ticket-view.php?id=<?= $message['related_id'] ?>" style="color: #dc143c;">View Ticket Details</a>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Message Body (HTML Email) -->
                <div class="message-body">
                    <?= $message['message_body'] ?>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: center;">
                <a href="/my-messages.php" class="btn">← Back to All Messages</a>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>
