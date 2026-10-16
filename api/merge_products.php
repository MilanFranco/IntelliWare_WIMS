<?php
/**
 * api/merge_products.php
 *
 * Safe 1-click Merge Duplicate SKUs Tool Backend.
 * Combines stock counts, reassigns transactions & batches, and archives duplicate SKU.
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

$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only administrators and managers can merge SKUs.']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/ProductMergeService.php';

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?? [];

$sourceId = (int)($_POST['source_id'] ?? ($data['source_id'] ?? 0));
$targetId = (int)($_POST['target_id'] ?? ($data['target_id'] ?? 0));

if ($sourceId <= 0 || $targetId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Source and Target product IDs are required.']);
    exit();
}

if ($sourceId === $targetId) {
    echo json_encode(['success' => false, 'error' => 'Source and Target SKU cannot be the same product.']);
    exit();
}

try {
    $result = ProductMergeService::mergeSkus($conn, $sourceId, $targetId, $_SESSION['Username']);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
