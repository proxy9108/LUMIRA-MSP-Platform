<?php
/**
 * Database Connection Test
 * DELETE THIS FILE after verification
 */

require_once 'app/config/config.php';
require_once 'app/config/database.php';

header('Content-Type: text/plain');

try {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT version()');
    $version = $stmt->fetchColumn();

    echo "SUCCESS: Connected to PostgreSQL\n";
    echo "Version: " . $version . "\n";
    echo "\nDatabase: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . ":" . DB_PORT . "\n";
    echo "User: " . DB_USER . "\n";
    echo "\nConnection test passed!\n";
    echo "\n** DELETE THIS FILE (dbtest.php) after verification **\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo "ERROR: Database connection failed\n";
    echo "Message: " . $e->getMessage() . "\n";
}
