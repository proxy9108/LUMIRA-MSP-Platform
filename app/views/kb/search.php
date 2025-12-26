<?php
/**
 * LUMIRA - Knowledge Base Search
 * Search for articles
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

$user = is_logged_in() ? get_logged_in_user() : null;

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

$results = [];

if (!empty($query)) {
    try {
        $pdo = get_db();

        // Search for articles
        $stmt = $pdo->prepare('
            SELECT a.*, c.name as category_name, c.slug as category_slug
            FROM kb_articles a
            JOIN kb_categories c ON a.category_id = c.id
            WHERE (a.title LIKE ? OR a.content LIKE ?) AND a.published = TRUE
            ORDER BY a.view_count DESC
        ');
        $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
        $results = $stmt->fetchAll();

    } catch (PDOException $e) {
        // In a real app, you'd log this exception
        $results = [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?= sanitize($query) ?>" - Knowledge Base - <?= SITE_NAME ?></title>
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
        <!-- Hero Section for Search -->
        <div class="kb-hero">
            <div class="container">
                <h1>Search Results</h1>
                <p>You searched for: "<strong><?= sanitize($query) ?></strong>"</p>
                <form action="/kb/search.php" method="GET" class="kb-search-box">
                    <input type="text" name="q" value="<?= sanitize($query) ?>" placeholder="Search for help articles..." required>
                    <button type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="container">
            <!-- Search Results -->
            <div class="kb-section">
                <div class="kb-section-header">
                    <h2 class="kb-section-title">Found <?= count($results) ?> results</h2>
                </div>

                <?php if (empty($results)): ?>
                    <div class="kb-empty-state">
                        <p>🤷</p>
                        <h3>No Results Found</h3>
                        <p>We couldn't find any articles matching your search. Try using different keywords.</p>
                    </div>
                <?php else: ?>
                    <div class="kb-articles-list">
                        <?php foreach ($results as $article): ?>
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
                                     <span><a href="/kb/category.php?slug=<?= urlencode($article['category_slug']) ?>">📁 <?= sanitize($article['category_name']) ?></a></span>
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
