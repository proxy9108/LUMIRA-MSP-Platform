<?php
/**
 * Subscription Activation Page
 * Confirms and activates subscription
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
    redirect('/login.php');
}

$user = get_logged_in_user();
$subscription_id = (int)($_GET['id'] ?? 0);

$error = '';
$subscription = null;

try {
    $pdo = get_db();

    // Get subscription details
    $stmt = $pdo->prepare('
        SELECT s.*, srv.name as service_name, srv.description as service_description
        FROM subscriptions s
        JOIN services srv ON s.service_id = srv.id
        WHERE s.id = ? AND s.customer_email = ?
    ');
    $stmt->execute([$subscription_id, $user['email']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        $error = 'Subscription not found.';
    }

    // Auto-activate if pending (simplified for demo)
    if ($subscription && $subscription['status'] === 'pending') {
        $stmt = $pdo->prepare('UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute(['active', $subscription_id]);
        $subscription['status'] = 'active';

        // Send confirmation email
        $subject = 'Subscription Activated - ' . $subscription['service_name'];
        $message_body = "
            <h2>Subscription Activated</h2>
            <p>Dear " . htmlspecialchars($subscription['customer_name']) . ",</p>
            <p>Your subscription to <strong>" . htmlspecialchars($subscription['service_name']) . "</strong> has been activated!</p>
            <p><strong>Details:</strong></p>
            <ul>
                <li>Service: " . htmlspecialchars($subscription['service_name']) . "</li>
                <li>Price: $" . number_format($subscription['price_cents'] / 100, 2) . " / " . $subscription['billing_cycle'] . "</li>
                <li>Next Billing Date: " . date('F j, Y', strtotime($subscription['next_billing_date'])) . "</li>
            </ul>
            <p>You can manage your subscriptions from your dashboard.</p>
        ";

        save_user_message($user['email'], $subject, $message_body, 'general', null);
    }

} catch (PDOException $e) {
    $error = 'Unable to load subscription details.';
    error_log('Subscription activation error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Activated - <?= SITE_NAME ?></title>
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
        <div class="container" style="max-width: 700px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
                <a href="/services.php" class="btn">Back to Services</a>
            <?php elseif ($subscription): ?>
                <div class="alert alert-success">
                    <h2>✅ Subscription Activated!</h2>
                    <p>Thank you for subscribing to our services.</p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin: 30px 0;">
                    <h3><?= sanitize($subscription['service_name']) ?></h3>
                    <p style="color: var(--text-secondary);"><?= sanitize($subscription['service_description']) ?></p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; padding: 20px; background: rgba(220, 20, 60, 0.05); border-radius: 8px;">
                        <div>
                            <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 5px;">Monthly Price</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--primary);">
                                $<?= number_format($subscription['price_cents'] / 100, 2) ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 5px;">Next Billing</div>
                            <div style="font-size: 18px; font-weight: 600;">
                                <?= date('M j, Y', strtotime($subscription['next_billing_date'])) ?>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin-top: 0; color: #333;">What's Next?</h4>
                        <ul style="margin: 15px 0; padding-left: 20px; color: #555;">
                            <li>You'll receive an email confirmation</li>
                            <li>We'll bill your PayPal account monthly</li>
                            <li>You can cancel anytime from your dashboard</li>
                            <li>Our team will contact you within 24 hours to get started</li>
                        </ul>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="/dashboard-customer.php" class="btn">View Dashboard</a>
                    <a href="/services.php" class="btn btn-secondary">Browse More Services</a>
                </div>
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
