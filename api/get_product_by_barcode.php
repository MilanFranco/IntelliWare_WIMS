<?php
/**
 * GET /api/get_product_by_barcode.php?barcode={code}
 *
 * Looks up a product in the store database by its barcode.
 * Returns product details if found, or success:false if not yet registered.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';

$barcode = trim($_GET['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['success' => false, 'error' => 'Barcode parameter required']);
    exit();
}

$stmt = $conn->prepare("
    SELECT p.product_id, p.product_name, p.barcode, p.category, p.supplier, p.location,
           p.quantity, p.reorder_point, p.unit, p.price, p.shelf_life,
           CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku
    FROM products p
    WHERE p.barcode = ? AND p.archived = 0
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database query preparation failed']);
    exit();
}

$stmt->bind_param('s', $barcode);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $baseDir = __DIR__ . '/../uploads/products/';
    $webBase = 'uploads/products/';
    $productId = (int)$row['product_id'];
    $imageUrl = null;

    $patterns = [
        (string)$productId,
        'product_' . $productId,
        'P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT),
    ];
    $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    foreach ($patterns as $name) {
        foreach ($extensions as $ext) {
            if (is_file($baseDir . $name . '.' . $ext)) {
                $imageUrl = $webBase . $name . '.' . $ext;
                break 2;
            }
        }
    }

    $row['image_url'] = $imageUrl;
    echo json_encode(['success' => true, 'found' => true, 'product' => $row]);
} else {
    echo json_encode(['success' => true, 'found' => false, 'barcode' => $barcode]);
}
$stmt->close();
