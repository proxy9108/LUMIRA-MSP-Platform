<?php
/**
 * LUMIRA - Knowledge Base Article
 * View a single knowledge base article
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

$user = is_logged_in() ? get_logged_in_user() : null;

// Get article slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    redirect('/kb/index.php');
}

try {
    $pdo = get_db();

    // Get the article details
    $stmt = $pdo->prepare('
        SELECT
            a.*,
            c.name as category_name,
            c.slug as category_slug,
            c.icon as category_icon
        FROM kb_articles a
        JOIN kb_categories c ON a.category_id = c.id
        WHERE a.slug = ? AND a.published = TRUE
    ');
    $stmt->execute([$slug]);
    $article = $stmt->fetch();

    // If article not found, redirect
    if (!$article) {
        redirect('/kb/index.php');
    }

    // Increment view count
    $pdo->prepare('UPDATE kb_articles SET view_count = view_count + 1 WHERE id = ?')->execute([$article['id']]);

} catch (PDOException $e) {
    // In a real app, you'd log this exception
    $article = null;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($article ? $article['title'] : 'Article') ?> - Knowledge Base - <?= SITE_NAME ?></title>
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
        <div class="container">
            <?php if ($article): ?>
                <!-- Breadcrumbs -->
                <div class="kb-breadcrumbs">
                    <a href="/kb/index.php">Knowledge Base</a>
                    <span>&raquo;</span>
                    <a href="/kb/category.php?slug=<?= urlencode($article['category_slug']) ?>"><?= sanitize($article['category_name']) ?></a>
                </div>

                <!-- Article Header -->
                <div class="kb-article-header">
                    <h1 class="kb-article-title-main"><?= sanitize($article['title']) ?></h1>
                    <div class="kb-article-meta-main">
                        <span>Published on: <?= date('F j, Y', strtotime($article['created_at'])) ?></span>
                        <span>Last updated: <?= date('F j, Y', strtotime($article['updated_at'])) ?></span>
                        <span>Views: <?= number_format($article['view_count'] + 1) ?></span>
                    </div>
                </div>

                <!-- Article Content -->
                <div class="kb-article-content">
                    <?= nl2br(sanitize($article['content'])) // Using nl2br for simple formatting, consider a Markdown parser for rich text ?>
                </div>

            <?php else: ?>
                <div class="kb-empty-state">
                    <p>😕</p>
                    <h3>Article Not Found</h3>
                    <p>The article you are looking for does not exist or has been removed.</p>
                    <a href="/kb/index.php" class="btn" style="margin-top: 20px;">Back to Knowledge Base</a>
                </div>
            <?php endif; ?>

            <!-- Call to Action -->
            <div class="kb-cta">
                <h2 class="kb-cta-title">Was this article helpful?</h2>
                 <div class="kb-article-rating">
                    <a href="#" class="btn-rating">👍 Yes</a>
                    <a href="#" class="btn-rating">👎 No</a>
                </div>
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
