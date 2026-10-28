<?php
/**
 * api/get_product_batches.php
 *
 * Returns the active batch breakdown for a given product ID.
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

$productId = (int)($_GET['product_id'] ?? ($_POST['product_id'] ?? 0));
if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit();
}

// Fetch product info
$pStmt = $conn->prepare("
    SELECT product_id, product_name, CONCAT('P-', LPAD(product_id, 4, '0')) AS sku, quantity, unit, location, category, supplier, price, reorder_point, shelf_life
    FROM products
    WHERE product_id = ?
    LIMIT 1
");
$pStmt->bind_param("i", $productId);
$pStmt->execute();
$prod = $pStmt->get_result()->fetch_assoc();
$pStmt->close();

if (!$prod) {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit();
}

// Fetch active batches from product_batches
$batches = [];
$totalQty = 0;
$bStmt = $conn->prepare("
    SELECT batch_id, batch_number, qty, expiry_date, received_at
    FROM product_batches
    WHERE product_id = ? AND qty > 0
    ORDER BY (expiry_date >= CURDATE()) DESC, expiry_date ASC, batch_id ASC
");
if ($bStmt) {
    $bStmt->bind_param("i", $productId);
    $bStmt->execute();
    $res = $bStmt->get_result();
    $idx = 1;
    while ($row = $res->fetch_assoc()) {
        $q = (int)$row['qty'];
        $totalQty += $q;
        $exp = !empty($row['expiry_date']) ? substr($row['expiry_date'], 0, 10) : 'No Expiry';
        $batches[] = [
            'batch_id'         => (int)$row['batch_id'],
            'batch_number'     => $row['batch_number'] ?: ('Batch ' . $idx),
            'qty'              => $q,
            'expiry_date'      => $row['expiry_date'] ?: '',
            'expiry_formatted' => $exp,
            'received_at'      => $row['received_at'] ?? ''
        ];
        $idx++;
    }
    $bStmt->close();
}

// Fallback to inventory_batches if product_batches empty
if (empty($batches)) {
    $ibStmt = $conn->prepare("
        SELECT batch_id, batch_number, quantity, expiry_date, date_received
        FROM inventory_batches
        WHERE product_id = ? AND quantity > 0
        ORDER BY expiry_date ASC, batch_id ASC
    ");
    if ($ibStmt) {
        $ibStmt->bind_param("i", $productId);
        $ibStmt->execute();
        $ibRes = $ibStmt->get_result();
        $idx = 1;
        while ($ibRow = $ibRes->fetch_assoc()) {
            $q = (int)$ibRow['quantity'];
            $totalQty += $q;
            $exp = !empty($ibRow['expiry_date']) ? substr($ibRow['expiry_date'], 0, 10) : 'No Expiry';
            $batches[] = [
                'batch_id'         => (int)$ibRow['batch_id'],
                'batch_number'     => $ibRow['batch_number'] ?: ('Batch ' . $idx),
                'qty'              => $q,
                'expiry_date'      => $ibRow['expiry_date'] ?: '',
                'expiry_formatted' => $exp,
                'received_at'      => $ibRow['date_received'] ?? ''
            ];
            $idx++;
        }
        $ibStmt->close();
    }
}

echo json_encode([
    'success'      => true,
    'product'      => $prod,
    'total_stock'  => $totalQty > 0 ? $totalQty : (int)$prod['quantity'],
    'batch_count'  => count($batches),
    'batches'      => $batches
]);
