<?php
/**
 * LUMIRA - Knowledge Base Article Editor
 * Create or edit a knowledge base article
 */

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';

session_start();

// Check if user is logged in and is an admin
if (!is_logged_in() || !get_logged_in_user()['is_admin']) {
    $_SESSION['error_message'] = "You are not authorized to access this page.";
    redirect('/login.php');
}

$user = get_logged_in_user();
$pdo = get_db();

// Determine if we are editing an existing article or creating a new one
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_editing = $article_id > 0;

$article = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'content' => '',
    'excerpt' => '',
    'category_id' => 0,
    'published' => 1,
    'featured' => 0,
    'display_order' => 100
];

$errors = [];

// If editing, fetch the article from the database
if ($is_editing) {
    $stmt = $pdo->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();

    if (!$article) {
        $_SESSION['error_message'] = "Article not found.";
        redirect('/admin/kb-articles.php'); // A page to list all articles (to be created)
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve form data
    $article['title'] = trim($_POST['title']);
    $article['slug'] = trim($_POST['slug']);
    $article['category_id'] = (int)$_POST['category_id'];
    $article['content'] = trim($_POST['content']);
    $article['excerpt'] = trim($_POST['excerpt']);
    $article['published'] = isset($_POST['published']) ? 1 : 0;
    $article['featured'] = isset($_POST['featured']) ? 1 : 0;

    // Auto-generate slug if empty
    if (empty($article['slug'])) {
        $article['slug'] = generate_slug($article['title']);
    }

    // Validation
    if (empty($article['title'])) {
        $errors[] = "Title is required.";
    }
    if (empty($article['category_id'])) {
        $errors[] = "Category is required.";
    }
    if (empty($article['content'])) {
        $errors[] = "Content is required.";
    }

    // If no errors, proceed with database operation
    if (empty($errors)) {
        if ($is_editing) {
            // Update existing article
            $sql = "UPDATE kb_articles SET title = ?, slug = ?, category_id = ?, content = ?, excerpt = ?, published = ?, featured = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $article['title'],
                $article['slug'],
                $article['category_id'],
                $article['content'],
                $article['excerpt'],
                $article['published'],
                $article['featured'],
                $article_id
            ]);
            $_SESSION['success_message'] = "Article updated successfully.";
        } else {
            // Insert new article
            $sql = "INSERT INTO kb_articles (title, slug, category_id, content, excerpt, published, featured, author_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $article['title'],
                $article['slug'],
                $article['category_id'],
                $article['content'],
                $article['excerpt'],
                $article['published'],
                $article['featured'],
                $user['id']
            ]);
            $article_id = $pdo->lastInsertId();
            $_SESSION['success_message'] = "Article created successfully.";
        }
        redirect('/admin/kb-article-edit.php?id=' . $article_id);
    }
}

// Fetch categories for the dropdown
$stmt = $pdo->query('SELECT id, name FROM kb_categories ORDER BY name');
$categories = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_editing ? 'Edit Article' : 'Create Article' ?> - Admin - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head>
<body>
    <header>
        <div class="container">
            <h1>Admin Panel</h1>
        </div>
    </header>

    <?php require_once dirname(__DIR__) . '/inc/nav.php'; ?>

    <main>
        <div class="container">
            <h2><?= $is_editing ? 'Edit Article' : 'Create New Article' ?></h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Error!</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success_message'] ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?= sanitize($article['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="slug">Slug (URL-friendly)</label>
                    <input type="text" id="slug" name="slug" value="<?= sanitize($article['slug']) ?>">
                    <small>Leave blank to auto-generate from title.</small>
                </div>

                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">-- Select a Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= ($article['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                <?= sanitize($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" rows="15"><?= sanitize($article['content']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="excerpt">Excerpt (Short Summary)</label>
                    <textarea id="excerpt" name="excerpt" rows="3"><?= sanitize($article['excerpt']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="published" value="1" <?= ($article['published']) ? 'checked' : '' ?>>
                        Published
                    </label>
                </div>

                 <div class="form-group">
                    <label>
                        <input type="checkbox" name="featured" value="1" <?= ($article['featured']) ? 'checked' : '' ?>>
                        Featured Article
                    </label>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-success"><?= $is_editing ? 'Update' : 'Create' ?> Article</button>
                    <a href="/admin/kb-articles.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>
