<?php
/**
 * LUMIRA - Products Page
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

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $product_id = (int)$_POST['product_id'];
        $qty = (int)($_POST['qty'] ?? 1);

        if ($product_id > 0 && $qty > 0) {
            cart_add($product_id, $qty);
            $message = 'Product added to cart!';
        } else {
            $error = 'Invalid product or quantity.';
        }
    }
}

// Fetch products
try {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT * FROM products ORDER BY id ASC');
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Unable to load products. Please try again later.';
    $products = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?= SITE_NAME ?></title>
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
            <h2>Our Products</h2>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= sanitize($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                <div class="product-card" id="product-<?= $product['id'] ?>">
                    <h3><?= sanitize($product['name']) ?></h3>
                    <p class="description"><?= sanitize($product['description']) ?></p>
                    <div class="price"><?= format_price($product['price_cents']) ?></div>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                        <div class="form-group">
                            <label>Quantity:</label>
                            <input type="number" name="qty" value="1" min="1" max="99" style="width: 100px;">
                        </div>

                        <button type="submit" name="add_to_cart" class="btn">Add to Cart</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($products)): ?>
                <div class="alert alert-info">No products available at this time.</div>
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
