<?php
/**
 * Barcode lookup API — returns product info for a scanned/entered barcode.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../config/db.php';

$barcode = trim($_GET['barcode'] ?? $_GET['code'] ?? '');

if ($barcode === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Barcode is required']);
    exit();
}

$stmt = $conn->prepare("
    SELECT product_id, product_name, category, supplier, location,
           quantity, reorder_point, unit, barcode,
           CONCAT('P-', LPAD(product_id, 4, '0')) AS sku
    FROM products
    WHERE barcode = ?
    LIMIT 1
");
$stmt->bind_param('s', $barcode);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();
$conn->close();

if ($product) {
    echo json_encode(['success' => true, 'found' => true, 'product' => $product]);
} else {
    echo json_encode(['success' => true, 'found' => false, 'barcode' => $barcode]);
}
