<?php
/**
 * Database Connection Handler
 * Returns PDO connection to PostgreSQL
 */

require_once __DIR__ . '/config.php';

/**
 * Get database connection
 * @return PDO
 * @throws PDOException
 */
function get_db() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Unable to connect to database');
        }
    }

    return $pdo;
}
