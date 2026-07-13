<?php
/**
 * Barcode Lookup API
 * 
 * Searches the database for a product matching a manufacturer-assigned barcode.
 * Barcodes are read from product packaging (EAN-13, UPC, Code 128, etc.) and must
 * be registered in the system before inventory transactions can reference them.
 * 
 * This API does NOT generate, assign, or create barcodes.
 * It only searches for existing manufacturer barcodes already in the database.
 * 
 * Query Parameters:
 *   - barcode: The scanned/entered barcode value (required)
 *   - code: Alternative parameter name (falls back to this if 'barcode' is empty)
 * 
 * Response:
 *   - success: Always true if API called successfully
 *   - found: Boolean indicating if product was found
 *   - product: Product details if found (includes name, category, quantity, barcode)
 *   - barcode: Echo of the searched barcode value
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../config/db.php';
require_once '../src/services/StockMovementService.php';

StockMovementService::ensureBatchColumn($conn);

function getStockStatus(int $qty, int $reorderPoint): string
{
    if ($qty <= 0) {
        return 'out_of_stock';
    }
    if ($qty <= $reorderPoint) {
        return 'low_stock';
    }
    return 'in_stock';
}

function getStockStatusLabel(string $status): string
{
    switch ($status) {
        case 'out_of_stock':
            return 'Out of Stock';
        case 'low_stock':
            return 'Low Stock';
        default:
            return 'In Stock';
    }
}

function getProductImageUrl(int $productId): ?string
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
            $path = $baseDir . $name . '.' . $ext;
            if (is_file($path)) {
                return $webBase . $name . '.' . $ext;
            }
        }
    }

    return null;
}

$barcode = trim($_GET['barcode'] ?? $_GET['code'] ?? '');

if ($barcode === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Barcode is required']);
    exit();
}

function barcodeLookupCandidates(string $barcode): array
{
    $candidates = [];
    $seen = [];

    $add = static function (string $value) use (&$candidates, &$seen): void {
        $value = trim($value);
        if ($value === '' || isset($seen[$value])) {
            return;
        }
        $seen[$value] = true;
        $candidates[] = $value;
    };

    $add($barcode);

    if (preg_match('/^\d+$/', $barcode)) {
        if (strlen($barcode) === 12) {
            $add('0' . $barcode);
        } elseif (strlen($barcode) === 13 && $barcode[0] === '0') {
            $add(substr($barcode, 1));
        } elseif (strlen($barcode) < 13) {
            $add(str_pad($barcode, 13, '0', STR_PAD_LEFT));
        }
    }

    return $candidates;
}

$stmt = $conn->prepare("
    SELECT p.product_id, p.product_name, p.category, p.supplier, p.location,
           p.quantity, p.reorder_point, p.unit, p.barcode, p.shelf_life,
           (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS expiry_date,
           (SELECT b.batch_number FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0 ORDER BY b.expiry_date IS NULL ASC, b.expiry_date ASC LIMIT 1) AS batch_number,
           CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku
    FROM products p
    WHERE p.barcode = ?
    LIMIT 1
");

$product = null;
foreach (barcodeLookupCandidates($barcode) as $candidate) {
    $lookupCode = $candidate;
    $stmt->bind_param('s', $lookupCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    if ($product) {
        break;
    }
}
$stmt->close();
$conn->close();

if ($product) {
    $qty = (int)($product['quantity'] ?? 0);
    $reorderPoint = (int)($product['reorder_point'] ?? 10);
    $status = getStockStatus($qty, $reorderPoint);
    $product['quantity'] = $qty;
    $product['reorder_point'] = $reorderPoint;
    $product['stock_status'] = $status;
    $product['stock_status_label'] = getStockStatusLabel($status);
    $product['image_url'] = getProductImageUrl((int)$product['product_id']);

    echo json_encode(['success' => true, 'found' => true, 'product' => $product]);
} else {
    echo json_encode(['success' => true, 'found' => false, 'barcode' => $barcode]);
}
