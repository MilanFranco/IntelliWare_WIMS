<?php
/**
 * GET /api/get_categories_with_models.php
 *
 * Returns a JSON array of product categories that have a deployed
 * Teachable Machine model on disk.
 *
 * A category is considered "deployed" when the file
 *   uploads/tm_models/{category-slug}/model.json
 * exists and is readable by the web server.
 *
 * Response shape:
 *   {
 *     "success": true,
 *     "categories": [
 *       { "id": 1, "name": "Dairy Products", "slug": "dairy-products", "model_url": "uploads/tm_models/dairy-products/" },
 *       ...
 *     ]
 *   }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$modelsBaseDir = __DIR__ . '/../uploads/tm_models/';
$modelsBaseUrl = 'uploads/tm_models/';

// Pull all categories that have a slug
$stmt = $conn->prepare('SELECT id, name, slug FROM categories WHERE slug IS NOT NULL AND slug != "" ORDER BY name ASC');
$stmt->execute();
$result = $stmt->get_result();
$rows   = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$deployed = [];
foreach ($rows as $row) {
    $slug      = $row['slug'];
    $modelFile = $modelsBaseDir . $slug . '/model.json';

    if (is_file($modelFile) && is_readable($modelFile)) {
        $deployed[] = [
            'id'        => (int)$row['id'],
            'name'      => $row['name'],
            'slug'      => $slug,
            'model_url' => $modelsBaseUrl . $slug . '/',
        ];
    }
}

echo json_encode(['success' => true, 'categories' => $deployed]);
