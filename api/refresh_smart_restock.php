<?php
/**
 * api/refresh_smart_restock.php
 *
 * Endpoint to trigger an on-demand recalculation of the 30-Day Demand Forecast
 * and Smart Restock recommendations. Forces cache regeneration and reconciles
 * with live warehouse stock levels in real time.
 */

session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Please log in to refresh forecast analytics.'
    ]);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/insights-service/InsightsEngine.php';

try {
    $period = trim((string)($_GET['period'] ?? $_POST['period'] ?? 'monthly'));
    $validPeriods = ['weekly', 'monthly', 'quarterly', 'semi-annual', 'annual'];
    if (!in_array($period, $validPeriods, true)) {
        $period = 'monthly';
    }

    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
    if ($month !== null && ($month < 1 || $month > 12)) {
        $month = null;
    }

    $engine = new InsightsEngine($conn);

    // Force complete recalculation of forecast caches from live products & transactions
    $engine->computeAndStoreForecasts();
    $engine->computeAndStoreSummary();

    // Fetch the updated forecast with guaranteed live inventory hydration
    $forecast = $engine->getForecast($period, $month, false, false);
    $items = $forecast['items'] ?? [];

    echo json_encode([
        'success'                => true,
        'message'                => 'Forecast and restock intelligence successfully refreshed with live warehouse stock.',
        'period_type'            => $period,
        'computed_at'            => date('Y-m-d H:i:s'),
        'last_updated_formatted' => 'Just now',
        'items'                  => $items,
        'total_items'            => count($items)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to refresh smart restock forecast: ' . $e->getMessage()
    ]);
}
