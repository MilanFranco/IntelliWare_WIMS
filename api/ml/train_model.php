<?php
/**
 * REST endpoint: trigger ML model training (admin only).
 */
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['Username']) || ($_SESSION['Role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

require_once __DIR__ . '/../../src/ml/MLForecastManager.php';

try {
    set_time_limit(300);
    $manager = new MLForecastManager();
    $result = $manager->runForecast();

    echo json_encode([
        'success' => true,
        'message' => 'Model trained successfully.',
        'run_id' => $result['run_id'] ?? null,
        'metrics' => $result['result']['metrics'] ?? null,
        'model_type' => $result['result']['model_type'] ?? null,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
