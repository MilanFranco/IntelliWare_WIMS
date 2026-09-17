<?php
/**
 * POST /api/log_ai_transaction.php
 *
 * Receives a confirmed Teachable Machine scan result and:
 *   1. Validates the product and quantity.
 *   2. Executes the stock movement via StockMovementService (atomic, audited).
 *   3. Tags the transaction row with transaction_source = 'ai_scan'.
 *   4. Inserts a row into tm_scan_log for ML audit purposes.
 *   5. Returns JSON { success, transaction_id, new_stock, previous_stock, product_name }.
 *
 * Expected JSON POST body:
 * {
 *   "product_id":        2,
 *   "quantity":          5,
 *   "direction":         "in",               // "in" | "out"
 *   "category_slug":     "dairy-products",
 *   "top_class":         "PRD-0002-ChiliMansi",
 *   "top_confidence":    0.94,
 *   "second_class":      "PRD-0003-AnchorMilk",  // nullable
 *   "second_confidence": 0.04,                    // nullable
 *   "resolution":        "auto",             // "auto" | "manual"
 *   "remarks":           "Morning receive"   // optional
 * }
 *
 * All DB writes use prepared statements.
 * Session-gated: requires active $_SESSION['Username'].
 */
session_start();
header('Content-Type: application/json');

// ── auth ───────────────────────────────────────────────────────────────────
if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/StockMovementService.php';

// ── parse JSON body ────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
    exit();
}

// ── validate required fields ───────────────────────────────────────────────
$productId    = (int)($body['product_id']       ?? 0);
$quantity     = (int)($body['quantity']         ?? 0);
$direction    = strtolower(trim((string)($body['direction']         ?? '')));
$categorySlug = trim((string)($body['category_slug']               ?? ''));
$topClass     = trim((string)($body['top_class']                   ?? ''));
$topConf      = round((float)($body['top_confidence']              ?? 0), 4);
$secondClass  = trim((string)($body['second_class']                ?? '')) ?: null;
$secondConf   = isset($body['second_confidence']) ? round((float)$body['second_confidence'], 4) : null;
$resolution   = strtolower(trim((string)($body['resolution']       ?? 'auto')));
$remarks      = trim((string)($body['remarks']                     ?? '')) ?: null;

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product_id.']);
    exit();
}
if ($quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Quantity must be greater than zero.']);
    exit();
}
if (!in_array($direction, ['in', 'out'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'direction must be "in" or "out".']);
    exit();
}
if ($categorySlug === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'category_slug is required.']);
    exit();
}
if ($topClass === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'top_class is required.']);
    exit();
}
if (!in_array($resolution, ['auto', 'manual', 'rejected'], true)) {
    $resolution = 'manual';
}

// Auto-set remarks if not provided
if ($remarks === null) {
    $remarks = $direction === 'in' ? 'Stock received via AI scan' : 'Stock issued via AI scan';
}

// ── execute stock movement (atomic via StockMovementService) ───────────────
try {
    $moveResult = StockMovementService::executeMovement(
        $conn,
        $productId,
        $quantity,
        $direction,   // 'in' | 'out' — StockMovementService::normalizeType() handles both
        $_SESSION['Username'],
        $remarks,
        null,
        'ai_scan'
    );
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

$transactionId = (int)$moveResult['transaction_id'];
$previousStock = (int)$moveResult['previous_stock'];
$newStock      = (int)$moveResult['new_stock'];
$productName   = $moveResult['product']['product_name'] ?? '';

// ── insert tm_scan_log row ─────────────────────────────────────────────────
$userId = StockMovementService::getUserId($conn, $_SESSION['Username']);

$logStmt = $conn->prepare(
    'INSERT INTO tm_scan_log
        (user_id, product_id, category_slug, top_class, top_confidence,
         second_class, second_confidence, resolution)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
if ($logStmt) {
    // Type string: i=int, i=int, s=str, s=str, d=double, s=str(nullable), d=double(nullable), s=str
    $logStmt->bind_param(
        'iissdds',
        $userId,
        $productId,
        $categorySlug,
        $topClass,
        $topConf,
        $secondClass,
        $secondConf,
        $resolution
    );
    $logStmt->execute();
    $logStmt->close();
}

$conn->close();

// ── respond ────────────────────────────────────────────────────────────────
echo json_encode([
    'success'        => true,
    'transaction_id' => $transactionId,
    'previous_stock' => $previousStock,
    'new_stock'      => $newStock,
    'product_name'   => $productName,
    'direction'      => $direction,
]);
