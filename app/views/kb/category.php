<?php
/**
 * LUMIRA - Knowledge Base Category
 * List articles within a specific category
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

$user = is_logged_in() ? get_logged_in_user() : null;

// Get category slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    redirect('/kb/index.php');
}

try {
    $pdo = get_db();

    // Get the category details
    $stmt = $pdo->prepare('SELECT * FROM kb_categories WHERE slug = ? AND is_active = TRUE');
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    // If category not found, redirect
    if (!$category) {
        redirect('/kb/index.php');
    }

    // Get all published articles in this category
    $stmt = $pdo->prepare('
        SELECT a.*, c.name as category_name
        FROM kb_articles a
        JOIN kb_categories c ON a.category_id = c.id
        WHERE a.category_id = ? AND a.published = TRUE
        ORDER BY a.display_order, a.title
    ');
    $stmt->execute([$category['id']]);
    $articles = $stmt->fetchAll();

} catch (PDOException $e) {
    // In a real app, you'd log this exception
    $category = null;
    $articles = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($category ? $category['name'] : 'Category') ?> - Knowledge Base - <?= SITE_NAME ?></title>
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
        <!-- Hero Section for Category -->
        <div class="kb-hero">
            <div class="container">
                 <a href="/kb/index.php" class="kb-breadcrumb">&larr; Back to Knowledge Base Home</a>
            </div>
        </div>

        <div class="container">
            <!-- Article List -->
            <div class="kb-section">
                <div class="kb-section-header">
                    <h2 class="kb-section-title">Articles in this category (<?= count($articles) ?>)</h2>
                </div>

                <?php if (empty($articles)): ?>
                    <div class="kb-empty-state">
                        <p>📂</p>
                        <h3>No Articles Found</h3>
                        <p>There are currently no articles in this category. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <div class="kb-articles-list">
                        <?php foreach ($articles as $article): ?>
                            <div class="kb-article-item">
                                <h3 class="kb-article-title">
                                    <a href="/kb/article.php?slug=<?= urlencode($article['slug']) ?>">
                                        <?= sanitize($article['title']) ?>
                                    </a>
                                </h3>
                                <?php if ($article['excerpt']): ?>
                                    <div class="kb-article-summary">
                                        <?= sanitize($article['excerpt']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="kb-article-meta">
                                    <span>👁 <?= number_format($article['view_count']) ?> views</span>
                                    <span>🕒 Last updated: <?= date('M j, Y', strtotime($article['updated_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Call to Action -->
            <div class="kb-cta">
                <h2 class="kb-cta-title">Can't find what you're looking for?</h2>
                <p class="kb-cta-text">Our support team is here to help!</p>
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
