<?php
/**
 * api/insights_live_pulse.php
 *
 * Real-time Insights & System Activity Pulse Endpoint
 * Continuously monitors database events (transactions, inventory movements, catalog edits),
 * calculates system study metrics, and provides on-demand full database re-studies.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Please sign in to view live insights.'
    ]);
    exit();
}

$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Forbidden: Insufficient permissions.'
    ]);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/insights-service/InsightsEngine.php';

try {
    $engine = new InsightsEngine($conn);
    $action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'pulse'));
    $clientLastTxId = isset($_GET['client_last_tx_id']) ? (int)$_GET['client_last_tx_id'] : 0;
    $forceStudy = ($action === 'study_now' || $action === 'refresh' || !empty($_POST) || !empty($_GET['force']));

    if ($forceStudy) {
        // Force complete database re-study: summary & forecasts
        $summary = $engine->computeAndStoreSummary();
        $engine->computeAndStoreForecasts();

        $forecast = $engine->getForecast('monthly', null, false, false);
        $forecastItems = $forecast['items'] ?? [];

        // Build recommendations summary
        $productMovement = $summary['product_movement'] ?? [];
        $urgentOos = count(array_filter($productMovement, function($p) { return ($p['stock_status'] ?? '') === 'out_of_stock'; }));
        $urgentLow = count(array_filter($productMovement, function($p) { return ($p['stock_status'] ?? '') === 'low_stock'; }));
        $overstocked = array_values(array_filter($productMovement, function($p) { return ($p['stock_status'] ?? '') === 'overstocked'; }));
        $slowMoving = array_values(array_filter($productMovement, function($p) { return ($p['movement_class'] ?? '') === 'slow_moving' && ($p['current_stock'] ?? 0) > 20; }));

        $catLabels = array_column($summary['category_performance'] ?? [], 'category_name');
        $catSales  = array_column($summary['category_performance'] ?? [], 'monthly_sales');
        $trendDates = array_column(array_reverse($summary['sales_trends'] ?? []), 'sale_date');
        $trendQtys  = array_column(array_reverse($summary['sales_trends'] ?? []), 'total_quantity_sold');

        echo json_encode([
            'success'                => true,
            'action'                 => 'study_now',
            'message'                => 'System successfully re-studied database activity and refreshed all insights.',
            'db_signature'           => $summary['db_signature'] ?? $engine->getDbSignature(),
            'study_metadata'         => $summary['study_metadata'] ?? $engine->getSystemStudyMetrics(),
            'kpis' => [
                'total_products'         => (int)($summary['total_products'] ?? 0),
                'attention_needed_count' => (int)($summary['attention_needed_count'] ?? 0),
                'fast_moving_count'      => (int)($summary['fast_moving_count'] ?? 0),
                'expiring_soon_count'    => (int)($summary['expiring_soon_count'] ?? 0),
            ],
            'recommendations' => [
                'urgent_oos'        => $urgentOos,
                'urgent_low'        => $urgentLow,
                'total_urgent'      => $urgentOos + $urgentLow,
                'overstocked_count' => count($overstocked),
                'top_overstocked'   => !empty($overstocked[0]['product_name']) ? $overstocked[0]['product_name'] : null,
                'slow_count'        => count($slowMoving),
                'top_slow'          => !empty($slowMoving[0]['product_name']) ? $slowMoving[0]['product_name'] : null,
            ],
            'chart_data' => [
                'category_labels'   => $catLabels,
                'category_sales'    => $catSales,
                'trend_dates'       => $trendDates,
                'trend_quantities'  => $trendQtys,
            ],
            'product_movement'       => $productMovement,
            'category_performance'   => $summary['category_performance'] ?? [],
            'sales_trends'           => $summary['sales_trends'] ?? [],
            'supplier_performance'   => $summary['supplier_performance'] ?? [],
            'expiry_risk_analysis'   => $summary['expiry_risk_analysis'] ?? [],
            'forecast_items'         => $forecastItems,
            'computed_at'            => date('Y-m-d H:i:s'),
            'last_updated_formatted' => 'Just now'
        ]);
        exit();
    }

    // Default: Lightweight Pulse Check (<10ms)
    $sig = $engine->getDbSignature();
    $needsSync = false;
    if ($clientLastTxId > 0 && $sig['max_tx_id'] > $clientLastTxId) {
        $needsSync = true;
    }

    // Fast fetch current summary (auto-detects if DB drifted)
    $summary = $engine->getSummary(true, false);
    $studyMeta = $summary['study_metadata'] ?? $engine->getSystemStudyMetrics($sig);

    echo json_encode([
        'success'                => true,
        'action'                 => 'pulse',
        'needs_sync'             => $needsSync,
        'db_signature'           => $sig,
        'study_metadata'         => $studyMeta,
        'kpis' => [
            'total_products'         => (int)($summary['total_products'] ?? 0),
            'attention_needed_count' => (int)($summary['attention_needed_count'] ?? 0),
            'fast_moving_count'      => (int)($summary['fast_moving_count'] ?? 0),
            'expiring_soon_count'    => (int)($summary['expiring_soon_count'] ?? 0),
        ],
        'last_updated_formatted' => $summary['last_updated_formatted'] ?? date('F j, Y, g:i A'),
        'reconciled_at'          => date('Y-m-d H:i:s')
    ]);
    exit();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Live pulse check error: ' . $e->getMessage()
    ]);
}
