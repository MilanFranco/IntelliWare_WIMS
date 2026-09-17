<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient privileges']);
    exit();
}

require_once __DIR__ . '/../../src/ml/MLForecastManager.php';

try {
    $manager = new MLForecastManager();
    $result = $manager->runForecast();
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}