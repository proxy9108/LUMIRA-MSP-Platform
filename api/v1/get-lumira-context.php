<?php
/**
 * Get LUMIRA Business Context
 * Returns products and services data for AI chat
 */

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/db.php';

try {
    $pdo = get_db();

    // Get active products
    $stmt = $pdo->query('
        SELECT name, description, price_cents, sku
        FROM products
        WHERE is_active = true
        ORDER BY name
    ');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active services
    $stmt = $pdo->query('
        SELECT name, description
        FROM services
        WHERE is_active = true
        ORDER BY name
    ');
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for display
    $formattedProducts = array_map(function($p) {
        return [
            'name' => $p['name'],
            'description' => $p['description'],
            'price' => '$' . number_format($p['price_cents'] / 100, 2),
            'sku' => $p['sku']
        ];
    }, $products);

    echo json_encode([
        'success' => true,
        'products' => $formattedProducts,
        'services' => $services
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load business data'
    ]);
}
