<?php
/**
 * Account Deletion API
 * Permanently deletes user account and all associated data
 */

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';

session_start();

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = get_logged_in_user();
$input = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
if (!isset($input['csrf_token']) || !csrf_check($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// Verify confirmation
if (!isset($input['confirmed']) || $input['confirmed'] !== true) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Deletion must be confirmed']);
    exit;
}

// Determine which user to delete
if (isset($input['user_id']) && is_user_admin()) {
    // Admin deleting another user's account
    $user_id_to_delete = (int)$input['user_id'];

    // Prevent admin from deleting themselves via this endpoint
    if ($user_id_to_delete === $user['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Use the customer endpoint to delete your own account']);
        exit;
    }

    // Get the user to delete
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user_id_to_delete]);
        $user_to_delete = $stmt->fetch();

        if (!$user_to_delete) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
    } catch (PDOException $e) {
        error_log('Delete account - user lookup error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
} else {
    // User deleting their own account
    $user_id_to_delete = $user['id'];
    $user_to_delete = $user;
}

try {
    $pdo = get_db();
    $pdo->beginTransaction();

    $email = $user_to_delete['email'];

    // 1. Delete all tickets where user is requester (must be done first due to RESTRICT constraint)
    // This will CASCADE delete: ticket_comments, ticket_history, ticket_attachments,
    // sla_breaches, ticket_watchers, ticket_relationships, ticket_surveys, ticket_email_tracking
    $stmt = $pdo->prepare('DELETE FROM tickets WHERE requester_id = ?');
    $stmt->execute([$user_id_to_delete]);
    $deleted_tickets = $stmt->rowCount();

    // 2. Delete all subscriptions associated with the user's email
    $stmt = $pdo->prepare('DELETE FROM subscriptions WHERE customer_email = ?');
    $stmt->execute([$email]);
    $deleted_subscriptions = $stmt->rowCount();

    // 3. Delete all orders associated with the user's email
    // This will CASCADE delete: order_items
    $stmt = $pdo->prepare('DELETE FROM orders WHERE customer_email = ?');
    $stmt->execute([$email]);
    $deleted_orders = $stmt->rowCount();

    // 4. Update any foreign key references to SET NULL (these should be automatic but we'll be explicit)
    // - tickets.assigned_to_id
    $stmt = $pdo->prepare('UPDATE tickets SET assigned_to_id = NULL WHERE assigned_to_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // - leads.assigned_to_id
    $stmt = $pdo->prepare('UPDATE leads SET assigned_to_id = NULL WHERE assigned_to_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // - clients.assigned_account_manager_id
    $stmt = $pdo->prepare('UPDATE clients SET assigned_account_manager_id = NULL WHERE assigned_account_manager_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // - sla_policies.escalation_user_id
    $stmt = $pdo->prepare('UPDATE sla_policies SET escalation_user_id = NULL WHERE escalation_user_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // - sla_breaches.escalated_to_id
    $stmt = $pdo->prepare('UPDATE sla_breaches SET escalated_to_id = NULL WHERE escalated_to_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // - canned_responses.created_by
    $stmt = $pdo->prepare('UPDATE canned_responses SET created_by = NULL WHERE created_by = ?');
    $stmt->execute([$user_id_to_delete]);

    // - ticket_relationships.created_by
    $stmt = $pdo->prepare('UPDATE ticket_relationships SET created_by = NULL WHERE created_by = ?');
    $stmt->execute([$user_id_to_delete]);

    // - ticket_history.user_id
    $stmt = $pdo->prepare('UPDATE ticket_history SET user_id = NULL WHERE user_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // - kb_article_views.user_id
    $stmt = $pdo->prepare('UPDATE kb_article_views SET user_id = NULL WHERE user_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // - kb_search_log.user_id
    $stmt = $pdo->prepare('UPDATE kb_search_log SET user_id = NULL WHERE user_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // 5. Delete records that CASCADE (will be automatically deleted, but listed for clarity)
    // - password_reset_tokens (CASCADE)
    // - user_sessions (CASCADE)
    // - department_members (CASCADE)
    // - client_contacts (CASCADE)
    // - ticket_watchers (CASCADE)

    // 6. Handle RESTRICT constraints on other tables
    // - kb_articles.author_id (RESTRICT) - keep articles but we need to handle this
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM kb_articles WHERE author_id = ?');
    $stmt->execute([$user_id_to_delete]);
    $kb_article_count = $stmt->fetchColumn();

    if ($kb_article_count > 0) {
        // Can't delete user if they authored KB articles - update to NULL if possible
        // Or we could assign to a system user. For now, prevent deletion.
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Cannot delete account: User has authored ' . $kb_article_count . ' knowledge base articles. Please reassign or delete these first.'
        ]);
        exit;
    }

    // - ticket_comments.user_id (RESTRICT)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ticket_comments WHERE user_id = ?');
    $stmt->execute([$user_id_to_delete]);
    $ticket_comment_count = $stmt->fetchColumn();

    if ($ticket_comment_count > 0) {
        // Comments on OTHER people's tickets - we'll delete these too
        $stmt = $pdo->prepare('DELETE FROM ticket_comments WHERE user_id = ?');
        $stmt->execute([$user_id_to_delete]);
    }

    // - ticket_attachments.uploaded_by_id (RESTRICT)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ticket_attachments WHERE uploaded_by_id = ?');
    $stmt->execute([$user_id_to_delete]);
    $ticket_attachment_count = $stmt->fetchColumn();

    if ($ticket_attachment_count > 0) {
        // Attachments on OTHER people's tickets - delete these
        $stmt = $pdo->prepare('DELETE FROM ticket_attachments WHERE uploaded_by_id = ?');
        $stmt->execute([$user_id_to_delete]);
    }

    // - lead_activities.user_id (RESTRICT)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lead_activities WHERE user_id = ?');
    $stmt->execute([$user_id_to_delete]);
    $lead_activity_count = $stmt->fetchColumn();

    if ($lead_activity_count > 0) {
        // Delete lead activities
        $stmt = $pdo->prepare('DELETE FROM lead_activities WHERE user_id = ?');
        $stmt->execute([$user_id_to_delete]);
    }

    // - departments.manager_id - no ON DELETE clause, need to check
    $stmt = $pdo->prepare('UPDATE departments SET manager_id = NULL WHERE manager_id = ?');
    $stmt->execute([$user_id_to_delete]);

    // 7. Finally, delete the user account
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$user_id_to_delete]);

    $pdo->commit();

    // Log the deletion
    error_log(sprintf(
        'Account deleted: user_id=%d, email=%s, deleted_tickets=%d, deleted_subscriptions=%d, deleted_orders=%d',
        $user_id_to_delete,
        $email,
        $deleted_tickets,
        $deleted_subscriptions,
        $deleted_orders
    ));

    // If user deleted their own account, log them out
    if ($user_id_to_delete === $user['id']) {
        user_logout();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Account and all associated data have been permanently deleted',
        'deleted' => [
            'tickets' => $deleted_tickets,
            'subscriptions' => $deleted_subscriptions,
            'orders' => $deleted_orders
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete account error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete account. Please contact support.',
        'debug' => ENVIRONMENT === 'development' ? $e->getMessage() : null
    ]);
}
