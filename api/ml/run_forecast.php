<?php
header('Content-Type: application/json');
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