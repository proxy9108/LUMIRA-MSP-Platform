<?php
/**
 * LUMIRA - Shopping Cart Page
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

$message = '';
$error = '';

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        if (isset($_POST['update_cart'])) {
            foreach ($_POST['qty'] ?? [] as $cart_key => $qty) {
                cart_update($cart_key, (int)$qty);
            }
            $message = 'Cart updated successfully!';
        } elseif (isset($_POST['remove_item'])) {
            cart_remove($_POST['cart_key']);
            $message = 'Item removed from cart.';
        }
    }
}

// Get cart items
try {
    $pdo = get_db();
    $cart_items = cart_get_items($pdo);
    $cart_total = cart_get_total($cart_items);
} catch (PDOException $e) {
    $error = 'Unable to load cart. Please try again later.';
    $cart_items = [];
    $cart_total = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - <?= SITE_NAME ?></title>
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
            <h2>Shopping Cart</h2>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= sanitize($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <div class="alert alert-info">
                    Your cart is empty. <a href="/products.php">Browse our products</a> or <a href="/services.php">explore our services</a> to get started!
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($item['item']['name']) ?></strong>
                                    <?php if ($item['type'] === 'service'): ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: rgba(220, 20, 60, 0.2); color: #dc143c; border-radius: 4px; font-size: 11px; margin-left: 8px;">SERVICE</span>
                                    <?php endif; ?>
                                    <br>
                                    <small><?= sanitize($item['item']['description']) ?></small>
                                </td>
                                <td>
                                    <?= format_price($item['item']['price_cents']) ?>
                                    <?php if ($item['type'] === 'service' && $item['item']['service_category'] === 'subscription'): ?>
                                        <br><small style="color: #888;">/month</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['type'] === 'service' && $item['item']['service_category'] === 'subscription'): ?>
                                        <!-- Subscriptions: qty fixed at 1 -->
                                        <input type="number" name="qty[<?= $item['cart_key'] ?>]"
                                               value="1" readonly style="background: #f5f5f5; cursor: not-allowed;">
                                    <?php else: ?>
                                        <!-- Products and one-time services: qty editable -->
                                        <input type="number" name="qty[<?= $item['cart_key'] ?>]"
                                               value="<?= $item['qty'] ?>" min="0" max="99">
                                    <?php endif; ?>
                                </td>
                                <td><?= format_price($item['item']['price_cents'] * $item['qty']) ?></td>
                                <td>
                                    <button type="submit" name="remove_item" value="1"
                                            class="btn btn-danger"
                                            onclick="this.form.cart_key.value='<?= $item['cart_key'] ?>'">Remove</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <input type="hidden" name="cart_key" value="">

                    <div class="btn-group">
                        <button type="submit" name="update_cart" class="btn">Update Cart</button>
                        <a href="/products.php" class="btn btn-secondary">Continue Shopping</a>
                        <a href="/services.php" class="btn btn-secondary">Browse Services</a>
                    </div>
                </form>

                <div class="cart-summary">
                    <div class="total">Total: <?= format_price($cart_total) ?></div>
                    <a href="/checkout.php" class="btn btn-success">Proceed to Checkout</a>
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
