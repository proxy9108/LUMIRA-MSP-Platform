<?php
/**
 * LUMIRA - Knowledge Base Home
 * Browse all categories
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

$user = is_logged_in() ? get_logged_in_user() : null;

try {
    $pdo = get_db();

    // Get all active categories with article counts
    $stmt = $pdo->query('
        SELECT
            c.id,
            c.name,
            c.slug,
            c.description,
            c.icon,
            COUNT(a.id) as article_count
        FROM kb_categories c
        LEFT JOIN kb_articles a ON c.id = a.category_id AND a.published = TRUE
        WHERE c.is_active = TRUE
        GROUP BY c.id, c.name, c.slug, c.description, c.icon, c.display_order
        ORDER BY c.display_order, c.name
    ');
    $categories = $stmt->fetchAll();

    // Get featured articles
    $stmt = $pdo->query('
        SELECT
            a.id,
            a.title,
            a.slug,
            a.excerpt as summary,
            a.view_count,
            c.name as category_name,
            c.icon as category_icon
        FROM kb_articles a
        JOIN kb_categories c ON a.category_id = c.id
        WHERE a.published = TRUE AND a.featured = TRUE
        ORDER BY a.view_count DESC
        LIMIT 6
    ');
    $featured = $stmt->fetchAll();

    // Get recently added articles
    $stmt = $pdo->query('
        SELECT
            a.id,
            a.title,
            a.slug,
            a.excerpt as summary,
            a.view_count,
            a.created_at,
            c.name as category_name
        FROM kb_articles a
        JOIN kb_categories c ON a.category_id = c.id
        WHERE a.published = TRUE
        ORDER BY a.created_at DESC
        LIMIT 5
    ');
    $recent = $stmt->fetchAll();

    // Get total stats
    $stmt = $pdo->query('
        SELECT
            COUNT(DISTINCT a.id) as total_articles,
            SUM(a.view_count) as total_views
        FROM kb_articles a
        WHERE a.published = TRUE
    ');
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    $categories = [];
    $featured = [];
    $recent = [];
    $stats = ['total_articles' => 0, 'total_views' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?></h1>
                <div class="tagline">Knowledge Base</div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <!-- Hero Section -->
        <div class="kb-hero">
            <div class="container">
                <h1>📚 Knowledge Base</h1>
                <p>Find answers to your questions and learn how to get the most out of LUMIRA</p>

                <!-- Search Box -->
                <form action="/kb/search.php" method="GET" class="kb-search-box">
                    <input type="text" name="q" placeholder="Search for help articles..." required>
                    <button type="submit">Search</button>
                </form>

                <!-- Stats -->
                <div class="kb-stats">
                    <div class="kb-stat">
                        <div class="kb-stat-number"><?= number_format($stats['total_articles']) ?></div>
                        <div class="kb-stat-label">Articles</div>
                    </div>
                    <div class="kb-stat">
                        <div class="kb-stat-number"><?= number_format($stats['total_views']) ?></div>
                        <div class="kb-stat-label">Views</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Categories -->
            <div class="kb-section">
                <div class="kb-section-header">
                    <h2 class="kb-section-title">Browse by Category</h2>
                </div>

                <?php if (empty($categories)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #b0b0b0;">
                        <p style="font-size: 48px; margin: 0;">📚</p>
                        <h3>No Categories Yet</h3>
                        <p>Check back soon for helpful articles!</p>
                    </div>
                <?php else: ?>
                    <div class="kb-categories">
                        <?php foreach ($categories as $cat): ?>
                            <a href="/kb/category.php?slug=<?= urlencode($cat['slug']) ?>" style="text-decoration: none;">
                                <div class="kb-category-card">
                                    <div class="kb-category-icon"><?= $cat['icon'] ?: '📁' ?></div>
                                    <div class="kb-category-name"><?= sanitize($cat['name']) ?></div>
                                    <?php if ($cat['description']): ?>
                                        <div class="kb-category-desc"><?= sanitize($cat['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="kb-category-count">
                                        <?= $cat['article_count'] ?> article<?= $cat['article_count'] != 1 ? 's' : '' ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Featured Articles -->
            <?php if (!empty($featured)): ?>
                <div class="kb-section">
                    <div class="kb-section-header">
                        <h2 class="kb-section-title">⭐ Featured Articles</h2>
                    </div>

                    <div class="featured-grid">
                        <?php foreach ($featured as $article): ?>
                            <div class="featured-card">
                                <span class="featured-badge">FEATURED</span>
                                <h3 class="kb-article-title">
                                    <a href="/kb/article.php?slug=<?= urlencode($article['slug']) ?>">
                                        <?= sanitize($article['title']) ?>
                                    </a>
                                </h3>
                                <?php if ($article['summary']): ?>
                                    <div class="kb-article-summary">
                                        <?= sanitize(substr($article['summary'], 0, 120)) ?>...
                                    </div>
                                <?php endif; ?>
                                <div class="kb-article-meta">
                                    <span><?= $article['category_icon'] ?: '📁' ?> <?= sanitize($article['category_name']) ?></span>
                                    <span>👁 <?= number_format($article['view_count']) ?> views</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recently Added -->
            <?php if (!empty($recent)): ?>
                <div class="kb-section">
                    <div class="kb-section-header">
                        <h2 class="kb-section-title">🆕 Recently Added</h2>
                        <a href="/kb/search.php?sort=newest" style="color: #dc143c; text-decoration: none;">View All →</a>
                    </div>

                    <div class="kb-articles-list">
                        <?php foreach ($recent as $article): ?>
                            <div class="kb-article-item">
                                <h3 class="kb-article-title">
                                    <a href="/kb/article.php?slug=<?= urlencode($article['slug']) ?>">
                                        <?= sanitize($article['title']) ?>
                                    </a>
                                </h3>
                                <?php if ($article['summary']): ?>
                                    <div class="kb-article-summary">
                                        <?= sanitize(substr($article['summary'], 0, 200)) ?>...
                                    </div>
                                <?php endif; ?>
                                <div class="kb-article-meta">
                                    <span>📁 <?= sanitize($article['category_name']) ?></span>
                                    <span>👁 <?= number_format($article['view_count']) ?> views</span>
                                    <span>🕒 <?= date('M j, Y', strtotime($article['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Call to Action -->
            <div style="background: rgba(220, 20, 60, 0.1); padding: 40px; border-radius: 12px; text-align: center; margin: 50px 0;">
                <h2 style="margin: 0 0 15px 0;">Can't find what you're looking for?</h2>
                <p style="color: #b0b0b0; margin-bottom: 25px;">
                    Our support team is here to help!
                </p>
                <?php if ($user): ?>
                    <a href="/support.php?create=1" class="btn">📝 Create Support Ticket</a>
                <?php else: ?>
                    <a href="/login.php" class="btn">Login for Support</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <?php if (file_exists(__DIR__ . '/../chat/widget.php')): ?>
        <?php require_once __DIR__ . '/../chat/widget.php'; ?>
    <?php endif; ?>
</body>
</html>
