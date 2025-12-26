<?php
/**
 * LUMIRA - Services Purchase Page
 * Browse and purchase services (one-time and subscriptions)
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

// Initialize cart
cart_init();

$message = '';
$error = '';

// Handle add to cart for all services
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $service_id = (int)$_POST['service_id'];

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ? AND is_active = TRUE');
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();

        if ($service) {
            // Use cart functions from functions.php
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            // Add service to cart (services use service_id instead of product_id)
            // Subscriptions: qty is always 1
            if ($service['service_category'] === 'subscription') {
                $_SESSION['cart']['service_' . $service_id] = [
                    'type' => 'service',
                    'service_id' => $service_id,
                    'qty' => 1
                ];
            } else {
                // One-time services: can increase qty
                if (isset($_SESSION['cart']['service_' . $service_id])) {
                    $_SESSION['cart']['service_' . $service_id]['qty']++;
                } else {
                    $_SESSION['cart']['service_' . $service_id] = [
                        'type' => 'service',
                        'service_id' => $service_id,
                        'qty' => 1
                    ];
                }
            }

            $message = 'Service added to cart!';
        } else {
            $error = 'Service not found.';
        }
    } catch (PDOException $e) {
        $error = 'Unable to add service to cart.';
        error_log('Add service to cart error: ' . $e->getMessage());
    }
}

// Fetch all services
try {
    $pdo = get_db();

    // Get one-time services
    $stmt = $pdo->query('SELECT * FROM services WHERE is_active = TRUE AND service_category = \'one_time\' ORDER BY name ASC');
    $one_time_services = $stmt->fetchAll();

    // Get subscription services
    $stmt = $pdo->query('SELECT * FROM services WHERE is_active = TRUE AND service_category = \'subscription\' ORDER BY hourly_rate_cents ASC');
    $subscription_services = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Unable to load services. Please try again later.';
    $one_time_services = [];
    $subscription_services = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - <?= SITE_NAME ?></title>
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
            <h2>Professional Services</h2>
            <p style="color: var(--text-secondary); margin-bottom: 40px;">
                Choose from our one-time services or subscribe for ongoing support
            </p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= sanitize($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <!-- Subscription Services -->
            <?php if (!empty($subscription_services)): ?>
            <div style="margin-bottom: 60px;">
                <div class="section-header">
                    <h2>🔄 Subscription Services</h2>
                    <p>Ongoing support with monthly billing</p>
                </div>

                <div class="product-grid">
                    <?php foreach ($subscription_services as $service): ?>
                    <div class="product-card">
                        <h3><?= sanitize($service['name']) ?></h3>
                        <p class="description"><?= sanitize($service['description']) ?></p>

                        <div style="margin: 20px 0;">
                            <div style="font-size: 32px; font-weight: 800; color: var(--primary);">
                                $<?= number_format($service['hourly_rate_cents'] / 100, 2) ?>
                            </div>
                            <div style="color: var(--text-secondary); font-size: 14px;">per month</div>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                            <button type="submit" name="add_to_cart" class="btn" style="width: 100%;">
                                Add to Cart
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- One-Time Services -->
            <?php if (!empty($one_time_services)): ?>
            <div style="margin-bottom: 60px;">
                <div class="section-header">
                    <h2>💼 One-Time Services</h2>
                    <p>Professional services billed once</p>
                </div>

                <div class="product-grid">
                    <?php foreach ($one_time_services as $service): ?>
                    <div class="product-card">
                        <h3><?= sanitize($service['name']) ?></h3>
                        <p class="description"><?= sanitize($service['description']) ?></p>

                        <div style="margin: 20px 0;">
                            <div style="font-size: 32px; font-weight: 800; color: var(--primary);">
                                $<?= number_format($service['hourly_rate_cents'] / 100, 2) ?>
                            </div>
                            <div style="color: var(--text-secondary); font-size: 14px;">one-time fee</div>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                            <button type="submit" name="add_to_cart" class="btn" style="width: 100%;">
                                Add to Cart
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($one_time_services) && empty($subscription_services)): ?>
                <div class="alert alert-info">No services available at this time.</div>
            <?php endif; ?>

            <!-- Help Section -->
            <div style="margin-top: 50px; padding: 30px; background: rgba(220, 20, 60, 0.05); border-radius: 10px; border-left: 4px solid var(--primary);">
                <h3>💡 Need Technical Support?</h3>
                <p style="margin-bottom: 20px; color: var(--text-secondary);">
                    For account issues, technical problems, or general inquiries, submit a free support ticket:
                </p>
                <a href="/support.php" class="btn">Submit Support Ticket</a>
            </div>
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
