<?php
/**
 * services/insights-service/index.php
 * REST API Router for the Insights/Analytics Microservice.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/InsightsEngine.php';

$engine = new InsightsEngine();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$endpoint = $_GET['endpoint'] ?? '';
if (empty($endpoint) && !empty($_SERVER['PATH_INFO'])) {
    $endpoint = trim($_SERVER['PATH_INFO'], '/');
}
if (empty($endpoint)) {
    $endpoint = 'summary';
}

try {
    // 1. GET /summary
    if ($endpoint === 'summary' && $method === 'GET') {
        $data = $engine->getSummary();
        echo json_encode(array_merge(['success' => true], $data));
        exit();
    }

    // 2. GET /forecast?period=monthly
    if ($endpoint === 'forecast' && $method === 'GET') {
        $period = trim((string)($_GET['period'] ?? 'monthly'));
        $data = $engine->getForecast($period);
        echo json_encode(array_merge(['success' => true], $data));
        exit();
    }

    // 3. GET /movement
    if ($endpoint === 'movement' && $method === 'GET') {
        $data = $engine->getMovementAndRisks();
        echo json_encode(array_merge(['success' => true], $data));
        exit();
    }

    // 4. POST /regenerate (Background computation worker trigger)
    if ($endpoint === 'regenerate' && $method === 'POST') {
        $result = $engine->regenerateAll();
        echo json_encode($result);
        exit();
    }

    // 5. POST /event (Async webhook from Inventory Service on stock movements)
    if ($endpoint === 'event' || (isset($_GET['action']) && $_GET['action'] === 'event')) {
        // Fast response to acknowledge event
        echo json_encode(['success' => true, 'acknowledged' => true]);
        // Background computation
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $engine->computeAndStoreSummary();
        exit();
    }

    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => "Unknown insights endpoint: {$endpoint}",
        'service' => 'insights-service'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[InsightsMicroservice Error] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An internal insights service error occurred.']);
}
