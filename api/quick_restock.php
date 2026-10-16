<?php
/**
 * api/quick_restock.php
 *
 * Fast restock endpoint for Critical Stock Inspection modal and Dashboard widgets.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/ApiClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$productId = (int)($data['product_id'] ?? 0);
$quantity = (int)($data['quantity'] ?? 0);
$expiryDate = !empty($data['expiry_date']) ? trim($data['expiry_date']) : null;
$batchNumber = !empty($data['batch_number']) ? trim($data['batch_number']) : null;

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid product ID is required.']);
    exit();
}

if ($quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Restock quantity must be at least 1.']);
    exit();
}

// Fetch current product details
$pStmt = $conn->prepare("SELECT product_id, product_name, quantity, reorder_point, unit, location FROM products WHERE product_id = ? AND archived = 0");
$pStmt->bind_param("i", $productId);
$pStmt->execute();
$product = $pStmt->get_result()->fetch_assoc();
$pStmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Product not found or archived.']);
    exit();
}

$metadata = ['source' => 'dashboard_quick_restock'];
if (!empty($expiryDate)) $metadata['expiry_date'] = $expiryDate;
if (!empty($batchNumber)) $metadata['batch_number'] = $batchNumber;

try {
    ApiClient::receiveInventory(
        $productId,
        $quantity,
        $_SESSION['Username'],
        'Quick restock from Critical Stock Levels',
        $metadata
    );

    // Fetch refreshed quantity
    $qStmt = $conn->prepare("SELECT quantity, reorder_point FROM products WHERE product_id = ?");
    $qStmt->bind_param("i", $productId);
    $qStmt->execute();
    $updatedRow = $qStmt->get_result()->fetch_assoc();
    $qStmt->close();

    $newQty = (int)($updatedRow['quantity'] ?? ($product['quantity'] + $quantity));
    $reorderPoint = (int)($updatedRow['reorder_point'] ?? $product['reorder_point']);
    $sku = 'P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT);
    $unit = $product['unit'] ?? 'units';
    $isNowOk = $newQty > $reorderPoint;

    echo json_encode([
        'success'        => true,
        'product_id'     => $productId,
        'sku'            => $sku,
        'product_name'   => $product['product_name'],
        'added_quantity' => $quantity,
        'new_quantity'   => $newQty,
        'reorder_point'  => $reorderPoint,
        'unit'           => $unit,
        'is_healthy'     => $isNowOk,
        'message'        => "⚡ Successfully added {$quantity} {$unit} to {$product['product_name']} ({$sku}). Total stock is now {$newQty} {$unit}."
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Could not complete restock: ' . $e->getMessage()
    ]);
}
