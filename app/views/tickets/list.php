<?php
/**
 * LUMIRA - My Tickets Page
 * View and manage support tickets
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
$message = '';
$error = '';

// Fetch user's tickets
try {
    $pdo = get_db();
    $tickets = get_user_tickets($pdo, $user['id']);
} catch (PDOException $e) {
    $error = 'Unable to load tickets. Please try again later.';
    $tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Support Tickets - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
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
            <h2>🎫 My Support Tickets</h2>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= sanitize($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <div style="margin-bottom: 20px;">
                <a href="/services.php" class="btn">📝 Create New Ticket</a>
            </div>

            <?php if (empty($tickets)): ?>
                <div class="alert alert-info">
                    You don't have any support tickets yet. <a href="/services.php">Request a service</a> to create one.
                </div>
            <?php else: ?>
                <div class="admin-section">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><strong><?= sanitize($ticket['ticket_number']) ?></strong></td>
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
                                <td><?= date('M j, Y', strtotime($ticket['created_at'])) ?></td>
                                <td>
                                    <a href="/ticket-view.php?id=<?= $ticket['id'] ?>" class="btn" style="padding: 8px 15px; font-size: 12px;">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
