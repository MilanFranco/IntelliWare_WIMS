<?php
/**
 * GET /api/search_products.php?q={query}&limit={n}
 *
 * Full-text product search for the scanner search bar.
 * Returns products matching product_name or category.
 *
 * Response:
 *   { "success": true, "data": [ { product fields + image_url }, ... ] }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

function spGetProductImageUrl(int $productId): ?string
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

$q     = trim($_GET['q'] ?? '');
$limit = max(1, min(20, (int)($_GET['limit'] ?? 8)));

if ($q === '') {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

$like  = '%' . $q . '%';
$stmt  = $conn->prepare("
    SELECT p.product_id, p.product_name, p.category, p.supplier, p.location,
           p.quantity, p.reorder_point, p.unit, p.price, p.shelf_life,
           CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku,
           (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS expiry_date,
           (SELECT b.batch_number FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0 ORDER BY b.expiry_date IS NULL ASC, b.expiry_date ASC LIMIT 1) AS batch_number
    FROM products p
    WHERE p.product_name LIKE ? OR p.category LIKE ? OR p.supplier LIKE ?
    ORDER BY
        CASE WHEN p.product_name LIKE ? THEN 0 ELSE 1 END,
        p.product_name ASC
    LIMIT ?
");

$stmt->bind_param('ssssi', $like, $like, $like, $like, $limit);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $id  = (int)$row['product_id'];
    $qty = (int)$row['quantity'];
    $rp  = (int)($row['reorder_point'] ?? 10);

    if ($qty <= 0)        $status = 'out_of_stock';
    elseif ($qty <= $rp)  $status = 'low_stock';
    else                  $status = 'in_stock';

    switch ($status) {
        case 'out_of_stock': $statusLabel = 'Out of Stock'; break;
        case 'low_stock':    $statusLabel = 'Low Stock';    break;
        default:             $statusLabel = 'In Stock';
    }

    $products[] = [
        'product_id'         => $id,
        'product_name'       => $row['product_name'],
        'category'           => $row['category'] ?? '',
        'supplier'           => $row['supplier'] ?? '',
        'location'           => $row['location'] ?? '',
        'quantity'           => $qty,
        'reorder_point'      => $rp,
        'unit'               => $row['unit'] ?? '',
        'price'              => (float)($row['price'] ?? 0),
        'shelf_life'         => $row['shelf_life'] !== null ? (int)$row['shelf_life'] : null,
        'sku'                => $row['sku'] ?? '',
        'expiry_date'        => $row['expiry_date'] ?? null,
        'batch_number'       => $row['batch_number'] ?? '',
        'stock_status'       => $status,
        'stock_status_label' => $statusLabel,
        'image_url'          => spGetProductImageUrl($id),
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $products]);
