<?php
/**
 * GET /api/get_all_products_for_scanner.php
 *
 * Returns a lightweight list of all active products that have a reference image,
 * so the client-side image-similarity scanner can pre-compute embeddings on load.
 *
 * Response:
 *   { "success": true, "products": [ { "product_id", "product_name", "category", "image_url" }, ... ] }
 *
 * Products without an image_url are omitted — they cannot participate in visual matching.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

// ── helpers ────────────────────────────────────────────────────────────────

function scannerGetProductImageUrl(int $productId): ?string
{
    $baseDir = __DIR__ . '/../uploads/products/';
    $webBase = 'uploads/products/';
    $patterns = [
        (string)$productId,
        'product_' . $productId,
        'P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT),
    ];
    $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    foreach ($patterns as $name) {
        foreach ($extensions as $ext) {
            if (is_file($baseDir . $name . '.' . $ext)) {
                return $webBase . $name . '.' . $ext;
            }
        }
    }
    return null;
}

// ── query ───────────────────────────────────────────────────────────────────

$result = $conn->query("
    SELECT product_id, product_name, category, quantity, reorder_point, unit, price
    FROM products
    ORDER BY product_name ASC
");

$products = [];

while ($row = $result->fetch_assoc()) {
    $id       = (int)$row['product_id'];
    $imageUrl = scannerGetProductImageUrl($id);

    $products[] = [
        'product_id'   => $id,
        'product_name' => $row['product_name'],
        'category'     => $row['category'] ?? '',
        'unit'         => $row['unit'] ?? '',
        'price'        => (float)($row['price'] ?? 0),
        'quantity'     => (int)$row['quantity'],
        'reorder_point'=> (int)($row['reorder_point'] ?? 10),
        'image_url'    => $imageUrl,
    ];
}

$conn->close();

echo json_encode(['success' => true, 'products' => $products]);
