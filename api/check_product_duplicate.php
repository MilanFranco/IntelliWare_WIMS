<?php
/**
 * api/check_product_duplicate.php
 *
 * Real-time product similarity and duplicate detection API.
 * Used by the Add Product Modal, OCR Camera Scanner, and Inventory Merge Guard.
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
require_once __DIR__ . '/../src/services/ProductMergeService.php';

$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true) ?? [];

$name = trim($_POST['name'] ?? ($_GET['name'] ?? ($_REQUEST['name'] ?? ($jsonBody['name'] ?? ''))));
$unit = trim($_POST['unit'] ?? ($_GET['unit'] ?? ($_REQUEST['unit'] ?? ($jsonBody['unit'] ?? ''))));
$excludeId = (int)($_POST['exclude_id'] ?? ($_GET['exclude_id'] ?? ($_REQUEST['exclude_id'] ?? ($jsonBody['exclude_id'] ?? 0))));

if ($name === '' || strlen($name) < 2) {
    echo json_encode([
        'success' => true,
        'has_duplicate' => false,
        'matches' => []
    ]);
    exit();
}

$result = ProductMergeService::checkDuplicate($conn, $name, $excludeId, $unit !== '' ? $unit : null);
$result['success'] = true;

echo json_encode($result);
