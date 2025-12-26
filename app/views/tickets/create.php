<?php
/**
 * LUMIRA - Create Support Ticket
 * Allows users to create general support tickets (not service-specific)
 * Categories: Account Issues, Login Problems, Billing, Technical Support, etc.
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
    // Store intended destination
    $_SESSION['redirect_after_login'] = '/create-ticket.php';
    redirect('/login.php');
}

$user = get_logged_in_user();
$message = '';
$error = '';

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

                // Get default status (New)
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

                // Get full ticket data for email
                $stmt = $pdo->prepare('
                    SELECT t.*,
                           ts.name as status_name,
                           tp.name as priority_name,
                           tc.name as category_name
                    FROM tickets t
                    LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
                    LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
                    LEFT JOIN ticket_categories tc ON t.category_id = tc.id
                    WHERE t.id = ?
                ');
                $stmt->execute([$ticket_id]);
                $ticket = $stmt->fetch();

                $pdo->commit();

                // Send confirmation email
                $email_data = [
                    'ticket_number' => $ticket_number,
                    'customer_name' => $user['full_name'],
                    'customer_email' => $user['email'],
                    'service_name' => $ticket['category_name'],
                    'subject' => $subject,
                    'description' => $description,
                    'status_name' => $ticket['status_name'] ?? 'New',
                    'priority_name' => $ticket['priority_name'] ?? 'Medium',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                send_ticket_confirmation($email_data);

                $message = "Support ticket #$ticket_number created successfully! Our team will respond within 24 hours.";
                $_POST = []; // Clear form
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

// Fetch ticket categories
try {
    $pdo = get_db();

    // Get categories suitable for customer tickets
    $stmt = $pdo->query('
        SELECT * FROM ticket_categories
        WHERE name IN (\'Account Issues\', \'Login Problems\', \'Billing\', \'Technical Support\', \'General Inquiry\', \'Product Question\')
        ORDER BY name
    ');
    $categories = $stmt->fetchAll();

    // If no specific categories exist, get all
    if (empty($categories)) {
        $stmt = $pdo->query('SELECT * FROM ticket_categories ORDER BY name');
        $categories = $stmt->fetchAll();
    }

    // Get priorities
    $stmt = $pdo->query('SELECT * FROM ticket_priorities ORDER BY id');
    $priorities = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Unable to load form data. Please try again later.';
    $categories = [];
    $priorities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Support Ticket - <?= SITE_NAME ?></title>
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
            <h2>🎫 Create Support Ticket</h2>

            <div class="alert alert-info" style="margin-bottom: 30px;">
                <strong>Need help with your account or a technical issue?</strong><br>
                Create a support ticket and our team will assist you within 24 hours.<br><br>
                <strong>For service requests</strong> (like IT support, setup, etc.), please visit our <a href="/services.php" style="color: var(--primary); font-weight: 600;">Services page</a>.
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= sanitize($message) ?>
                    <div style="margin-top: 15px;">
                        <a href="/tickets.php" class="btn">View My Tickets</a>
                        <a href="/index.php" class="btn btn-secondary">Return Home</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <?php if (!$message): ?>
            <form method="POST" action="" style="max-width: 800px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">-- Select Issue Type --</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= ($_POST['category_id'] ?? 0) == $category['id'] ? 'selected' : '' ?>>
                            <?= sanitize($category['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        Choose the category that best describes your issue
                    </small>
                </div>

                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority_id">
                        <option value="">-- Normal Priority --</option>
                        <?php foreach ($priorities as $priority): ?>
                        <option value="<?= $priority['id'] ?>" <?= ($_POST['priority_id'] ?? 0) == $priority['id'] ? 'selected' : '' ?>>
                            <?= sanitize($priority['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        Only select high/urgent priority for critical issues
                    </small>
                </div>

                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="subject" required
                           value="<?= sanitize($_POST['subject'] ?? '') ?>"
                           placeholder="Brief description of your issue"
                           maxlength="200">
                </div>

                <div class="form-group">
                    <label>Detailed Description *</label>
                    <textarea name="description" required rows="8"
                              placeholder="Please provide as much detail as possible about your issue. Include any error messages, steps to reproduce the problem, etc."><?= sanitize($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <div style="background: rgba(36, 36, 36, 0.6); padding: 15px; border-left: 3px solid var(--primary); border-radius: 5px;">
                        <strong>Your Information:</strong><br>
                        Name: <?= sanitize($user['full_name']) ?><br>
                        Email: <?= sanitize($user['email']) ?>
                        <?php if ($user['phone']): ?><br>Phone: <?= sanitize($user['phone']) ?><?php endif; ?>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" name="create_ticket" class="btn btn-success">📤 Create Ticket</button>
                    <a href="/tickets.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
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
