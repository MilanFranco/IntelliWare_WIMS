<?php
/**
 * GET /api/get_product_by_id.php?id={product_id}
 *
 * Looks up a product by its integer primary key and returns a JSON
 * payload so the AI scanner can populate the product info panel.
 *
 * Query Parameters:
 *   id  – integer product_id (required)
 *
 * Response shape (found):
 *   {
 *     "success": true,
 *     "found": true,
 *     "product": {
 *       "product_id", "product_name", "category", "supplier",
 *       "location", "quantity", "reorder_point", "unit",
 *       "price", "sku", "expiry_date", "batch_number",
 *       "stock_status", "stock_status_label", "image_url"
 *     }
 *   }
 *
 * Response shape (not found):
 *   { "success": true, "found": false }
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

// ── helpers ────────────────────────────────────────────────────────────────

function tmGetStockStatus(int $qty, int $reorderPoint): string
{
    if ($qty <= 0)             return 'out_of_stock';
    if ($qty <= $reorderPoint) return 'low_stock';
    return 'in_stock';
}

function tmGetStockStatusLabel(string $status): string
{
    switch ($status) {
        case 'out_of_stock': return 'Out of Stock';
        case 'low_stock':    return 'Low Stock';
        default:             return 'In Stock';
    }
}

// ── shared image-URL helper ──────────────────────────────────────────────────
require_once __DIR__ . '/../src/helpers/product_image_url.php';


// ── input validation ────────────────────────────────────────────────────────

$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid product id is required.']);
    exit();
}

// ── query ───────────────────────────────────────────────────────────────────

$stmt = $conn->prepare("
    SELECT p.product_id, p.product_name, p.category, p.supplier, p.location,
           p.quantity, p.reorder_point, p.unit, p.price,
           CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku,
           (SELECT MIN(b.expiry_date)
              FROM inventory_batches b
             WHERE b.product_id = p.product_id AND b.quantity > 0) AS expiry_date,
           (SELECT b.batch_number
              FROM inventory_batches b
             WHERE b.product_id = p.product_id AND b.quantity > 0
             ORDER BY b.expiry_date IS NULL ASC, b.expiry_date ASC
             LIMIT 1) AS batch_number
    FROM products p
    WHERE p.product_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $productId);
$stmt->execute();
$result  = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();
$conn->close();

// ── response ─────────────────────────────────────────────────────────────────

if ($product) {
    $qty           = (int)($product['quantity']     ?? 0);
    $reorderPoint  = (int)($product['reorder_point'] ?? 10);
    $status        = tmGetStockStatus($qty, $reorderPoint);

    $product['quantity']           = $qty;
    $product['reorder_point']      = $reorderPoint;
    $product['stock_status']       = $status;
    $product['stock_status_label'] = tmGetStockStatusLabel($status);
    $product['image_url']          = getProductImageUrl((int)$product['product_id']);

    echo json_encode(['success' => true, 'found' => true, 'product' => $product]);
} else {
    echo json_encode(['success' => true, 'found' => false]);
}
