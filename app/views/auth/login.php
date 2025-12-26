<?php
/**
 * LUMIRA - Unified Login/Registration Page
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

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (is_user_admin()) {
        redirect('/dashboard-admin.php');
    } else {
        redirect('/dashboard-customer.php');
    }
}

$error = '';
$message = '';
$show_register = isset($_GET['register']);

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $pdo = get_db();
                $user = user_login($pdo, $email, $password);

                if ($user) {
                    // Redirect based on role
                    if (is_user_admin()) {
                        redirect('/dashboard-admin.php');
                    } else {
                        redirect('/dashboard-customer.php');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                $error = 'Login failed. Please try again later.';
                error_log('Login error: ' . $e->getMessage());
            }
        }
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');

        // Validation
        if (empty($email) || empty($password) || empty($full_name)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo = get_db();
                $phone = trim($_POST['phone'] ?? '');
                $user_id = user_register($pdo, $email, $password, $full_name, $phone, 'client_user');

                if ($user_id) {
                    $message = 'Registration successful! You can now log in.';
                    $show_register = false;
                    $_POST = []; // Clear form
                } else {
                    $error = 'This email is already registered. Please log in instead.';
                }
            } catch (PDOException $e) {
                $error = 'Registration failed. Please try again later.';
                error_log('Registration error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $show_register ? 'Register' : 'Login' ?> - <?= SITE_NAME ?></title>
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
            <div style="max-width: 500px; margin: 50px auto;">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= sanitize($message) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= sanitize($error) ?></div>
                <?php endif; ?>

                <?php if ($show_register): ?>
                    <!-- Registration Form -->
                    <h2>Create Account</h2>
                    <p style="margin-bottom: 30px;">Join LUMIRA to track your orders and service requests.</p>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" required
                                   value="<?= sanitize($_POST['full_name'] ?? '') ?>"
                                   placeholder="John Doe">
                        </div>

                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" required
                                   value="<?= sanitize($_POST['email'] ?? '') ?>"
                                   placeholder="john@example.com">
                        </div>

                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required
                                   minlength="8"
                                   placeholder="Minimum 8 characters">
                        </div>

                        <div class="form-group">
                            <label>Confirm Password *</label>
                            <input type="password" name="password_confirm" required
                                   minlength="8"
                                   placeholder="Re-enter password">
                        </div>

                        <button type="submit" name="register" class="btn" style="width: 100%;">Create Account</button>
                    </form>

                    <p style="text-align: center; margin-top: 20px;">
                        Already have an account? <a href="/login.php">Sign in here</a>
                    </p>

                <?php else: ?>
                    <!-- Login Form -->
                    <h2>Sign In</h2>
                    <p style="margin-bottom: 30px;">Access your account to view orders, tickets, and more.</p>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" required autofocus
                                   value="<?= sanitize($_POST['email'] ?? '') ?>"
                                   placeholder="your@email.com">
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required
                                   placeholder="Your password">
                        </div>

                        <button type="submit" name="login" class="btn" style="width: 100%;">Sign In</button>
                    </form>

                    <p style="text-align: center; margin-top: 20px;">
                        Don't have an account? <a href="/login.php?register">Create one here</a>
                    </p>
                <?php endif; ?>
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
