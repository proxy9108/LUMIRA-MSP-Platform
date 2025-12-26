<?php
/**
 * Navigation Bar Component
 * Shows appropriate links based on login status
 */

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = is_logged_in();
$is_admin = $is_logged_in && is_user_admin();
$is_full_admin = $is_logged_in && is_full_admin();
$is_support_agent = $is_logged_in && is_support_agent();
$user = $is_logged_in ? get_logged_in_user() : null;
?>
<nav>
    <div class="container">
        <ul>
            <li><a href="/index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a></li>
            <li><a href="/products.php" class="<?= $current_page === 'products.php' ? 'active' : '' ?>">Products</a></li>
            <li><a href="/services.php" class="<?= $current_page === 'services.php' ? 'active' : '' ?>">Services</a></li>
            <li><a href="/support.php" class="<?= $current_page === 'support.php' || $current_page === 'ticket-view.php' ? 'active' : '' ?>">Support</a></li>
            <li><a href="/cart.php" class="<?= $current_page === 'cart.php' ? 'active' : '' ?>">Cart <?php if (cart_count() > 0): ?><span class="cart-badge"><?= cart_count() ?></span><?php endif; ?></a></li>

            <?php if ($is_logged_in): ?>
                <li><a href="/my-messages.php" class="<?= $current_page === 'my-messages.php' || $current_page === 'message-view.php' ? 'active' : '' ?>">
                    My Messages
                    <?php
                    // Show unread count badge
                    try {
                        if (isset($pdo)) {
                            $stmt = $pdo->prepare('SELECT COUNT(*) as unread FROM user_messages WHERE user_email = ? AND is_read = FALSE');
                            $stmt->execute([$user['email']]);
                            $unread_result = $stmt->fetch();
                            if ($unread_result['unread'] > 0):
                            ?>
                                <span class="cart-badge"><?= $unread_result['unread'] ?></span>
                            <?php
                            endif;
                        }
                    } catch (Exception $e) {
                        // Silently fail - don't show badge if error
                    }
                    ?>
                </a></li>
                <?php if ($is_full_admin): ?>
                    <li><a href="/dashboard-admin.php" class="<?= $current_page === 'dashboard-admin.php' ? 'active' : '' ?>">Admin Dashboard</a></li>
                <?php elseif ($is_support_agent): ?>
                    <!-- Support agents don't have a separate dashboard, they go to Support -->
                <?php else: ?>
                    <li><a href="/dashboard-customer.php" class="<?= $current_page === 'dashboard-customer.php' ? 'active' : '' ?>">My Dashboard</a></li>
                <?php endif; ?>
                <li><a href="?logout=1">Logout (<?= sanitize($user['full_name']) ?>)</a></li>
            <?php else: ?>
                <li><a href="/login.php" class="<?= $current_page === 'login.php' ? 'active' : '' ?>">Login</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
