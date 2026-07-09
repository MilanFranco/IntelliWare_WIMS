<?php
/**
 * REST endpoint: return forecast chart data as JSON for the insights dashboard.
 */
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/ml/ForecastDataService.php';

$daysLookback = isset($_GET['days']) ? intval($_GET['days']) : 30;
$service = new ForecastDataService();

echo json_encode([
    'success' => true,
    'has_model' => $service->hasTrainedModel(),
    'seasonal' => $service->getSeasonalChartData(),
    'accuracy' => $service->getAccuracyTrend(),
    'segments' => $service->getSegmentAccuracy(),
    'sales_forecast' => $service->getSalesForecastChartData($daysLookback),
    'heatmap' => $service->getProductForecastDemand(8),
    'depletion' => $service->getStockDepletionTrend(),
    'reorder_frequency' => $service->getReorderFrequencyTrend(),
]);
