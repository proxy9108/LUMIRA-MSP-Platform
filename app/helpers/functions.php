<?php
/**
 * Helper Functions
 * Security, cart management, and utilities
 */

/**
 * Sanitize user input
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 * @return string
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function csrf_check($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Initialize shopping cart
 */
function cart_init() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

/**
 * Add item to cart
 * @param int $product_id
 * @param int $qty
 */
function cart_add($product_id, $qty = 1) {
    cart_init();

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $qty;
    } else {
        $_SESSION['cart'][$product_id] = $qty;
    }
}

/**
 * Remove item from cart
 * @param string|int $cart_key Product ID or service key (e.g., 'service_123')
 */
function cart_remove($cart_key) {
    cart_init();

    if (isset($_SESSION['cart'][$cart_key])) {
        unset($_SESSION['cart'][$cart_key]);
    }
}

/**
 * Update cart item quantity
 * @param string|int $cart_key Product ID or service key (e.g., 'service_123')
 * @param int $qty
 */
function cart_update($cart_key, $qty) {
    cart_init();

    if ($qty <= 0) {
        cart_remove($cart_key);
    } else {
        // Check if it's a service key
        if (is_array($_SESSION['cart'][$cart_key] ?? null)) {
            // Service - update qty inside the array
            $_SESSION['cart'][$cart_key]['qty'] = $qty;
        } else {
            // Product - qty is the value
            $_SESSION['cart'][$cart_key] = $qty;
        }
    }
}

/**
 * Get cart items with product and service details
 * @param PDO $pdo
 * @return array
 */
function cart_get_items($pdo) {
    cart_init();

    if (empty($_SESSION['cart'])) {
        return [];
    }

    $items = [];

    // Process cart items
    foreach ($_SESSION['cart'] as $key => $value) {
        // Check if it's a service (starts with 'service_')
        if (is_array($value) && isset($value['type']) && $value['type'] === 'service') {
            // Fetch service details
            $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND is_active = TRUE");
            $stmt->execute([$value['service_id']]);
            $service = $stmt->fetch();

            if ($service) {
                $items[] = [
                    'type' => 'service',
                    'item' => $service,
                    'qty' => $value['qty'],
                    'cart_key' => $key
                ];
            }
        } else {
            // It's a product (stored as product_id => qty)
            $product_id = $key;
            $qty = $value;

            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if ($product) {
                $items[] = [
                    'type' => 'product',
                    'item' => $product,
                    'qty' => $qty,
                    'cart_key' => $key
                ];
            }
        }
    }

    return $items;
}

/**
 * Get cart total
 * @param array $items
 * @return int Total in cents
 */
function cart_get_total($items) {
    $total = 0;

    foreach ($items as $item) {
        $total += $item['item']['price_cents'] * $item['qty'];
    }

    return $total;
}

/**
 * Clear shopping cart
 */
function cart_clear() {
    cart_init();
    $_SESSION['cart'] = [];
}

/**
 * Get cart item count
 * @return int
 */
function cart_count() {
    cart_init();

    $count = 0;
    foreach ($_SESSION['cart'] as $value) {
        if (is_array($value) && isset($value['qty'])) {
            // Service item
            $count += $value['qty'];
        } else {
            // Product item (qty is the value itself)
            $count += $value;
        }
    }

    return $count;
}

/**
 * Format price from cents to dollars
 * @param int $cents
 * @return string
 */
function format_price($cents) {
    return '$' . number_format($cents / 100, 2);
}

/**
 * Redirect to URL
 * @param string $url
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Check if user is admin (logged in to admin panel)
 * @return bool
 */
function is_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get current logged in user
 * @return array|null User data or null if not logged in
 */
function get_logged_in_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return $_SESSION['user'] ?? null;
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in() {
    return get_logged_in_user() !== null;
}

/**
 * Check if current user has admin role (includes support agents for ticket access)
 * @return bool
 */
function is_user_admin() {
    $user = get_logged_in_user();
    return $user && in_array($user['role_name'], ['super_admin', 'admin', 'manager', 'technician']);
}

/**
 * Check if current user is a full admin (super_admin, admin, or manager)
 * Full admins can access all admin features including orders, users, etc.
 * @return bool
 */
function is_full_admin() {
    $user = get_logged_in_user();
    return $user && in_array($user['role_name'], ['super_admin', 'admin', 'manager']);
}

/**
 * Check if current user is a support agent (technician)
 * Support agents can only handle tickets, not access other admin features
 * @return bool
 */
function is_support_agent() {
    $user = get_logged_in_user();
    return $user && $user['role_name'] === 'technician';
}

/**
 * Check if current user is super admin
 * @return bool
 */
function is_super_admin() {
    $user = get_logged_in_user();
    return $user && $user['role_name'] === 'super_admin';
}

/**
 * Check if current user has customer role
 * @return bool
 */
function is_user_customer() {
    $user = get_logged_in_user();
    return $user && in_array($user['role_name'], ['client_user', 'client_admin']);
}

/**
 * Login user by email and password
 * @param PDO $pdo
 * @param string $email
 * @param string $password
 * @return array|false User data on success, false on failure
 */
function user_login($pdo, $email, $password) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $stmt = $pdo->prepare('
        SELECT u.*, r.name as role_name, r.display_name as role_display_name
        FROM users u
        LEFT JOIN app_roles r ON u.role_id = r.id
        WHERE u.email = ? AND u.is_active = true
    ');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Update last login timestamp
        $updateStmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $updateStmt->execute([$user['id']]);

        // Remove password hash from session
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        return $user;
    }

    return false;
}

/**
 * Register new user
 * @param PDO $pdo
 * @param string $email
 * @param string $password
 * @param string $full_name
 * @param string $phone
 * @param string $role_name Default role name (e.g., 'client_user')
 * @return int|false User ID on success, false on failure
 */
function user_register($pdo, $email, $password, $full_name, $phone = null, $role_name = 'client_user') {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Get role_id from role name
        $roleStmt = $pdo->prepare('SELECT id FROM app_roles WHERE name = ?');
        $roleStmt->execute([$role_name]);
        $role_id = $roleStmt->fetchColumn();

        if (!$role_id) {
            // Default to client_user if role not found
            $roleStmt = $pdo->prepare('SELECT id FROM app_roles WHERE name = ?');
            $roleStmt->execute(['client_user']);
            $role_id = $roleStmt->fetchColumn();
        }

        $stmt = $pdo->prepare('
            INSERT INTO users (email, password_hash, full_name, phone, role_id, is_active, email_verified, created_at)
            VALUES (?, ?, ?, ?, ?, true, false, NOW())
            RETURNING id
        ');
        $stmt->execute([$email, $password_hash, $full_name, $phone, $role_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Likely duplicate email
        error_log("User registration error: " . $e->getMessage());
        return false;
    }
}

/**
 * Logout current user
 */
function user_logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['user']);
    unset($_SESSION['admin_logged_in']);
}

/**
 * Get user's orders
 * @param PDO $pdo
 * @param string $email
 * @return array
 */
function get_user_orders($pdo, $email) {
    $stmt = $pdo->prepare('
        SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.qty * oi.price_cents) as total_cents
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_email = ? AND NOT (o.status = ? AND o.payment_method = ?)
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ');
    $stmt->execute([$email, 'pending_payment', 'paypal']);
    return $stmt->fetchAll();
}

/**
 * Get user's service tickets
 * @param PDO $pdo
 * @param int $user_id
 * @return array
 */
function get_user_tickets($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT t.*,
               tc.name as category_name,
               tp.name as priority_name,
               tp.color_code as priority_color,
               ts.name as status_name,
               ts.color_code as status_color,
               u.full_name as assigned_to_name
        FROM tickets t
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN users u ON t.assigned_to_id = u.id
        WHERE t.requester_id = ?
        ORDER BY t.created_at DESC
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Get tickets for admin/support agents
 * Shows: assigned to them + unassigned tickets
 */
function get_admin_tickets($pdo, $user_id) {
    $stmt = $pdo->prepare('
        SELECT t.*,
               tc.name as category_name,
               tp.name as priority_name,
               tp.color_code as priority_color,
               ts.name as status_name,
               ts.color_code as status_color,
               assigned_user.full_name as assigned_to_name,
               requester.full_name as requester_name,
               requester.email as requester_email
        FROM tickets t
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN users assigned_user ON t.assigned_to_id = assigned_user.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        WHERE (
            t.assigned_to_id = ?           -- Assigned to this admin
            OR t.assigned_to_id IS NULL    -- Unassigned tickets
        )
        ORDER BY
            CASE WHEN t.assigned_to_id IS NULL THEN 0 ELSE 1 END,  -- Unassigned first
            t.priority_id ASC,             -- High priority first
            t.created_at DESC              -- Newest first
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
