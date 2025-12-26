<?php
/**
 * LUMIRA - Home Page
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';

session_start();

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

// Get featured products
try {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT * FROM products WHERE is_active = TRUE ORDER BY created_at DESC LIMIT 3');
    $featured_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured_products = [];
}

// Get user info if logged in
$user = is_logged_in() ? get_logged_in_user() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - <?= SITE_TAGLINE ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #dc143c 0%, #8b0000 100%);
            padding: 80px 20px;
            text-align: center;
            margin: -20px -20px 40px -20px;
            border-radius: 0 0 20px 20px;
        }

        .hero-section h1 {
            font-size: 48px;
            margin: 0 0 20px 0;
            color: #ffffff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-section p {
            font-size: 20px;
            color: #ffffff;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-buttons .btn {
            padding: 15px 40px;
            font-size: 16px;
            font-weight: 600;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(220, 20, 60, 0.3);
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: #dc143c;
            box-shadow: 0 5px 20px rgba(220, 20, 60, 0.3);
        }

        .feature-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .feature-card h3 {
            color: #dc143c;
            margin: 15px 0;
        }

        .feature-card p {
            color: #b0b0b0;
            line-height: 1.6;
        }

        .section-header {
            text-align: center;
            margin: 60px 0 30px 0;
        }

        .section-header h2 {
            font-size: 36px;
            color: #ffffff;
            margin-bottom: 10px;
        }

        .section-header p {
            color: #b0b0b0;
            font-size: 18px;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
            padding: 40px;
            background: rgba(220, 20, 60, 0.1);
            border-radius: 12px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 42px;
            font-weight: bold;
            color: #dc143c;
        }

        .stat-label {
            color: #b0b0b0;
            margin-top: 5px;
        }

        .cta-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
            padding: 60px 40px;
            border-radius: 12px;
            text-align: center;
            margin: 60px 0;
            border: 2px solid #dc143c;
        }

        .cta-section h2 {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .cta-section p {
            font-size: 18px;
            color: #b0b0b0;
            margin-bottom: 30px;
        }
    </style>
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

    <?php require_once __DIR__ . '/../app/views/layouts/nav.php'; ?>

    <main>
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="container">
                <?php if ($user): ?>
                    <h1>Welcome Back, <?= sanitize($user['full_name']) ?>! 👋</h1>
                    <p>Ready to find the perfect IT solution for your needs?</p>
                <?php else: ?>
                    <h1>Professional IT Solutions for Your Business</h1>
                    <p>Quality products, expert services, and reliable support - all in one place</p>
                <?php endif; ?>
                <div class="hero-buttons">
                    <a href="/products.php" class="btn">🛍️ Shop Products</a>
                    <a href="/services.php" class="btn" style="background: #ffffff; color: #dc143c;">🔧 Request Service</a>
                    <?php if (!$user): ?>
                        <a href="/login.php?register" class="btn" style="background: transparent; border: 2px solid white;">Create Account</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Features Section -->
            <div class="section-header">
                <h2>Why Choose LUMIRA?</h2>
                <p>Your trusted partner for all IT needs</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">💻</div>
                    <h3>Quality Products</h3>
                    <p>Refurbished laptops, professional docking stations, genuine Windows licenses - all backed by warranties and expert support.</p>
                    <a href="/products.php" class="btn btn-sm" style="margin-top: 15px;">Browse Products</a>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🛠️</div>
                    <h3>Expert Services</h3>
                    <p>From helpdesk subscriptions to on-site setup and data recovery - our experienced team handles all your IT needs.</p>
                    <a href="/services.php" class="btn btn-sm" style="margin-top: 15px;">View Services</a>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Reliable Support</h3>
                    <p>Fast response times, dedicated account management, and comprehensive ticket tracking to keep your business running smoothly.</p>
                    <?php if ($user): ?>
                        <a href="/support.php" class="btn btn-sm" style="margin-top: 15px;">Get Support</a>
                    <?php else: ?>
                        <a href="/login.php" class="btn btn-sm" style="margin-top: 15px;">Login for Support</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Happy Clients</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Products Sold</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support Available</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">99%</div>
                    <div class="stat-label">Satisfaction Rate</div>
                </div>
            </div>

            <!-- Featured Products -->
            <?php if (!empty($featured_products)): ?>
                <div class="section-header">
                    <h2><a href="/products.php" style="color: inherit; text-decoration: none;">Featured Products</a></h2>
                    <p><a href="/products.php" style="color: var(--text-secondary); text-decoration: none;">Check out our latest offerings →</a></p>
                </div>

                <div class="product-grid">
                    <?php foreach ($featured_products as $product): ?>
                        <div class="product-card">
                            <h3><a href="/products.php#product-<?= $product['id'] ?>" style="color: inherit; text-decoration: none;"><?= sanitize($product['name']) ?></a></h3>
                            <p class="price">$<?= number_format($product['price_cents'] / 100, 2) ?></p>
                            <p class="description"><?= sanitize(substr($product['description'], 0, 100)) ?>...</p>
                            <p class="stock">
                                <?php if ($product['is_active']): ?>
                                    <span style="color: #28a745;">✓ Available</span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">Not Available</span>
                                <?php endif; ?>
                            </p>
                            <a href="/products.php" class="btn">View All Products</a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="/products.php" class="btn btn-lg">View All Products →</a>
                </div>
            <?php endif; ?>

            <!-- Call to Action -->
            <div class="cta-section">
                <h2>Ready to Get Started?</h2>
                <p>Join hundreds of satisfied customers who trust LUMIRA for their IT needs</p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <?php if ($user): ?>
                        <a href="/products.php" class="btn btn-lg">Start Shopping</a>
                        <a href="/dashboard-customer.php" class="btn btn-lg" style="background: #6c757d;">Go to Dashboard</a>
                    <?php else: ?>
                        <a href="/login.php?register" class="btn btn-lg">Create Free Account</a>
                        <a href="/login.php" class="btn btn-lg" style="background: #6c757d;">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Services Overview -->
            <div class="section-header">
                <h2>Our Services</h2>
                <p>Comprehensive IT solutions tailored to your business</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📞</div>
                    <h3>Helpdesk Support</h3>
                    <p>Monthly subscription plans with unlimited tickets, priority response, and dedicated support agents.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🔌</div>
                    <h3>On-Site Setup</h3>
                    <p>Professional installation and configuration of hardware, software, and network equipment at your location.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">💾</div>
                    <h3>Data Recovery</h3>
                    <p>Expert recovery services for damaged or corrupted storage devices with high success rates.</p>
                </div>
            </div>

            <div style="text-align: center; margin: 40px 0;">
                <a href="/services.php" class="btn btn-lg">Explore All Services →</a>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - <?= SITE_TAGLINE ?></p>
        </div>
    </footer>

    <?php if (file_exists(__DIR__ . '/../app/views/chat/widget.php')): ?>
        <?php require_once __DIR__ . '/../app/views/chat/widget.php'; ?>
    <?php endif; ?>
</body>
</html>
