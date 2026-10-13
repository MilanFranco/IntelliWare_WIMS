<?php
session_start();

if (!isset($_SESSION['Username'])) {
    header('Location: signin.php');
    exit();
}
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) {
    header('Location: dash_staff.php');
    exit();
}

require_once 'config/db.php';
require_once 'src/services/LocationCategoryService.php';
require_once 'src/services/PhilippineSeasonalService.php';
require_once 'src/ml/ForecastDataService.php';
require_once 'src/ml/MLForecastManager.php';
require_once 'src/services/ApiClient.php';

$forecastService = new ForecastDataService($conn);
$mlManager = new MLForecastManager();
$hasMlModel = $forecastService->hasTrainedModel();

/**
 * Map insight/forecast shorthand categories to canonical product category names.
 */
function resolveInsightCategory(string $category, mysqli $conn): string
{
    $category = trim($category);
    if ($category === '') {
        return '';
    }

    static $aliases = [
        'Household' => 'Household Items',
        'Bev' => 'Beverages',
        'Dry' => 'Dry Goods',
        'Canned' => 'Canned Goods',
        'Frozen' => 'Frozen Foods',
        'Bakery' => 'Bakery Products',
        'Care' => 'Personal Care',
        'Stationery' => 'Stationery and Office Supplies',
        'Staples' => 'Dry Goods',
        'Staple Foods' => 'Dry Goods',
        'Condiments & Sauces' => 'Condiments and Sauces',
        'Noodles and Instant Food' => 'Dry Goods',
        'Snacks and Biscuits' => 'Dry Goods',
        'Alcohol and Spirits' => 'Beverages',
    ];

    if (isset($aliases[$category])) {
        return $aliases[$category];
    }

    static $knownCategories = null;
    if ($knownCategories === null) {
        $map = LocationCategoryService::buildMap($conn);
        $knownCategories = $map['categories'];
        $res = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND archived = 0");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $knownCategories[] = trim((string)$row['category']);
            }
        }
        $knownCategories = array_values(array_unique($knownCategories));
    }

    foreach ($knownCategories as $known) {
        if (strcasecmp($known, $category) === 0) {
            return $known;
        }
    }

    foreach ($knownCategories as $known) {
        if (stripos($known, $category) === 0) {
            return $known;
        }
    }

    return $category;
}

if (!function_exists('formatSafeSeasonalHtml')) {
    function formatSafeSeasonalHtml(?string $str): string {
        if ($str === null || $str === '') {
            return '';
        }
        $str = str_ireplace(
            ['&lt;strong&gt;', '&lt;/strong&gt;', '&lt;b&gt;', '&lt;/b&gt;', '&lt;em&gt;', '&lt;/em&gt;'],
            ['<strong>', '</strong>', '<b>', '</b>', '<em>', '</em>'],
            $str
        );
        $escaped = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        return str_ireplace(
            ['&lt;strong&gt;', '&lt;/strong&gt;', '&lt;b&gt;', '&lt;/b&gt;', '&lt;em&gt;', '&lt;/em&gt;'],
            ['<strong>', '</strong>', '<b>', '</b>', '<em>', '</em>'],
            $escaped
        );
    }
}

// All forecast defaults are dynamically queried and calculated in real-time from active inventory DB & ML models.

$selectedPeriod = $_GET['period'] ?? 'monthly';
$daysLookback = 30;

switch ($selectedPeriod) {
    case 'weekly':      $daysLookback = 7; break;
    case 'monthly':     $daysLookback = 30; break;
    case 'quarterly':   $daysLookback = 90; break;
    case 'semi-annual': $daysLookback = 180; break;
    case 'annual':      $daysLookback = 365; break;
}

function getCategoryPerformance($conn) {
    $query = "SELECT p.category AS category_name, COUNT(p.product_id) AS product_count, SUM(p.quantity) AS total_stock,
        AVG(p.quantity) AS avg_stock, COUNT(CASE WHEN p.quantity <= p.reorder_point THEN 1 END) AS low_stock_count,
        (SELECT COUNT(*) FROM transactions t JOIN products p2 ON t.product_id = p2.product_id
        WHERE p2.category = p.category AND t.transaction_type = 'stock_out') AS monthly_sales
        FROM products p
        WHERE p.category IS NOT NULL AND p.category != '' AND p.archived = 0
        GROUP BY p.category ORDER BY monthly_sales DESC";
    $result = $conn->query($query);
    $categories = [];
    while ($row = $result->fetch_assoc()) { $categories[] = $row; }
    return $categories;
}

function getProductMovementAnalysis($conn) {
    $query = "SELECT p.product_id, p.product_name, p.category AS category_name, p.quantity AS current_stock, p.reorder_point,
        (SELECT SUM(quantity) FROM transactions WHERE product_id = p.product_id AND transaction_type = 'stock_out') AS monthly_sales
        FROM products p WHERE p.archived = 0 ORDER BY monthly_sales DESC";
    
    $result = $conn->query($query);
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['daily_sales_velocity'] = $row['monthly_sales'] ? $row['monthly_sales'] / 30 : 0;
        $row['days_of_supply'] = $row['daily_sales_velocity'] > 0 ? round($row['current_stock'] / $row['daily_sales_velocity'], 1) : 999;
        
        if ($row['current_stock'] <= 0) { $row['stock_status'] = 'out_of_stock'; } 
        elseif ($row['current_stock'] < $row['reorder_point']) { $row['stock_status'] = 'low_stock'; } 
        elseif ($row['days_of_supply'] > 90) { $row['stock_status'] = 'overstocked'; } 
        else { $row['stock_status'] = 'normal'; }
        
        if ($row['monthly_sales'] >= 50) { $row['movement_class'] = 'fast_moving'; } 
        elseif ($row['monthly_sales'] >= 10) { $row['movement_class'] = 'medium_moving'; } 
        elseif ($row['monthly_sales'] > 0) { $row['movement_class'] = 'slow_moving'; } 
        else { $row['movement_class'] = 'non_moving'; }
        $products[] = $row;
    }
    return $products;
}

function getSalesTrends($conn, $limit) {
    $query = "SELECT DATE(t.transaction_date) as sale_date, DAYNAME(t.transaction_date) as day_name,
        COUNT(DISTINCT t.product_id) as unique_products_sold, SUM(t.quantity) as total_quantity_sold,
        AVG(t.quantity) as avg_quantity_per_transaction
        FROM transactions t WHERE t.transaction_type = 'stock_out'
        GROUP BY DATE(t.transaction_date), DAYNAME(t.transaction_date)
        ORDER BY sale_date DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $trends = [];
    while ($row = $result->fetch_assoc()) { $trends[] = $row; }
    return $trends;
}

function getSupplierPerformance($conn) {
    $query = "SELECT p.supplier, COUNT(p.product_id) as product_count, SUM(p.quantity) as total_stock_value,
        AVG(p.quantity) as avg_stock_per_product,
        (SELECT COUNT(*) FROM transactions t JOIN products p2 ON t.product_id = p2.product_id 
        WHERE p2.supplier = p.supplier AND t.transaction_type = 'stock_out') as monthly_sales,
        COUNT(CASE WHEN p.quantity <= p.reorder_point THEN 1 END) as low_stock_count
        FROM products p WHERE p.supplier IS NOT NULL AND p.supplier != '' AND p.archived = 0
        GROUP BY p.supplier ORDER BY monthly_sales DESC";
    $result = $conn->query($query);
    $suppliers = [];
    while ($row = $result->fetch_assoc()) { $suppliers[] = $row; }
    return $suppliers;
}

function getExpiryRiskAnalysis($conn) {
    $query = "SELECT p.product_id, p.product_name, p.category AS category_name, b.quantity, b.expiry_date,
        DATEDIFF(b.expiry_date, CURDATE()) AS days_until_expiry,
        (SELECT SUM(quantity) FROM transactions WHERE product_id = p.product_id AND transaction_type = 'stock_out') AS monthly_sales
        FROM inventory_batches b
        JOIN products p ON b.product_id = p.product_id
        WHERE b.expiry_date IS NOT NULL AND b.expiry_date > CURDATE() AND b.quantity > 0 AND p.archived = 0
        ORDER BY days_until_expiry ASC";
    $result = $conn->query($query);
    $expiryRisks = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['days_until_expiry'] <= 30) { $row['expiry_risk'] = 'high'; } 
        elseif ($row['days_until_expiry'] <= 90) { $row['expiry_risk'] = 'medium'; } 
        else { $row['expiry_risk'] = 'low'; }
        $daily_sales = $row['monthly_sales'] / 30;
        $row['sell_through_days'] = $daily_sales > 0 ? round($row['quantity'] / $daily_sales, 1) : 999;
        $expiryRisks[] = $row;
    }
    return $expiryRisks;
}

// --- Fetch pre-computed analytics from Insights Microservice ---
$insightsSummary = ApiClient::getInsightsSummary();

$categoryPerformance = $insightsSummary['category_performance'] ?? [];
$productMovement     = $insightsSummary['product_movement'] ?? [];
$salesTrends         = $insightsSummary['sales_trends'] ?? [];
$supplierPerformance = $insightsSummary['supplier_performance'] ?? [];
$expiryRiskAnalysis  = $insightsSummary['expiry_risk_analysis'] ?? [];

$totalProducts        = (int)($insightsSummary['total_products'] ?? count($productMovement));
$attentionNeededCount = (int)($insightsSummary['attention_needed_count'] ?? 0);
$fastMovingCount      = (int)($insightsSummary['fast_moving_count'] ?? 0);
$expiringSoonCount    = (int)($insightsSummary['expiring_soon_count'] ?? 0);
$lastUpdatedTimestamp = $insightsSummary['last_updated_formatted'] ?? date('F j, Y, g:i A');

$forecastPeriod = $_GET['forecast_period'] ?? 'monthly';
$selectedQuarter = isset($_GET['selected_quarter']) ? intval($_GET['selected_quarter']) : ceil(date('n')/3);
$selectedSemester = isset($_GET['selected_semester']) ? intval($_GET['selected_semester']) : (date('n') <= 6 ? 1 : 2);
$selectedMonth = isset($_GET['selected_month']) ? intval($_GET['selected_month']) : (isset($_GET['month']) ? intval($_GET['month']) : (int)date('n'));
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('n');
}

$philippineProfiles = PhilippineSeasonalService::getAllMonthProfiles();
$philippineQuarterProfiles = PhilippineSeasonalService::getAllQuarterProfiles();
$philippineSemesterProfiles = PhilippineSeasonalService::getAllSemesterProfiles();
$activeMonthProfile = PhilippineSeasonalService::getMonthProfile($selectedMonth);

// Pre-compute all horizons for instant 0ms modal navigation
$allSeasonalMonthsData = [];
for ($m = 1; $m <= 12; $m++) {
    $allSeasonalMonthsData[$m] = PhilippineSeasonalService::matchInventoryForMonth($conn, $m);
}
$allSeasonalQuartersData = [];
for ($q = 1; $q <= 4; $q++) {
    $allSeasonalQuartersData[$q] = PhilippineSeasonalService::matchInventoryForQuarter($conn, $q);
}
$allSeasonalSemestersData = [];
for ($s = 1; $s <= 2; $s++) {
    $allSeasonalSemestersData[$s] = PhilippineSeasonalService::matchInventoryForSemester($conn, $s);
}

$periodTitle = '';
$sqlTimeFilter = '';
$currentKey = 1; 
$currentMonthStr = date('M'); 

switch ($forecastPeriod) {
    case 'weekly':
        $targetWeekDate = strtotime('+1 week');
        $nextWeekNumYear = date('W', $targetWeekDate);
        $nextMonthStr = date('M', $targetWeekDate);
        $dayOfMonth = date('j', $targetWeekDate);
        $weekOfMonth = ceil($dayOfMonth / 7);
        if($weekOfMonth > 4) $weekOfMonth = 4;

        $periodTitle = "Next Week (Week $weekOfMonth of $nextMonthStr)";
        $sqlTimeFilter = "WEEK(t.transaction_date, 1) = $nextWeekNumYear";
        $currentKey = $weekOfMonth; 
        $currentMonthStr = $nextMonthStr;
        break;

    case 'quarterly':
        if (!isset($_GET['selected_quarter'])) {
            $currentQuarter = ceil(date('n') / 3);
            $selectedQuarter = ($currentQuarter == 4) ? 1 : $currentQuarter + 1;
        }
        $periodTitle = "Quarter $selectedQuarter Market Trends";
        $sqlTimeFilter = "QUARTER(t.transaction_date) = $selectedQuarter";
        $currentKey = $selectedQuarter;
        break;

    case 'semi-annual':
        $periodTitle = ($selectedSemester == 1 ? "1st" : "2nd") . " Semester Forecast";
        if ($selectedSemester == 1) {
            $sqlTimeFilter = "MONTH(t.transaction_date) BETWEEN 1 AND 6";
        } else {
            $sqlTimeFilter = "MONTH(t.transaction_date) BETWEEN 7 AND 12";
        }
        $currentKey = $selectedSemester;
        break;

    case 'annual':
        $nextYear = date('Y', strtotime('+1 year'));
        $periodTitle = "$nextYear Annual Forecast";
        $sqlTimeFilter = "1=1"; 
        $currentKey = 1;
        break;

    case 'monthly':
    default:
        $nextMonthNum = date('n', strtotime('+1 month'));
        $nextMonthName = date('F', strtotime('+1 month'));
        $periodTitle = "$nextMonthName Forecast";
        $sqlTimeFilter = "MONTH(t.transaction_date) = $nextMonthNum";
        $currentKey = $nextMonthNum;
        break;
}

$forecastData = [];
$forecastResult = ApiClient::getInsightsForecast($forecastPeriod);
if (!empty($forecastResult['items'])) {
    $forecastData = $forecastResult['items'];
}

if (empty($forecastData)) {
    // 1. Force compute fresh forecast via InsightsEngine
    try {
        $engine = new InsightsEngine($conn);
        $computed = $engine->getForecast($forecastPeriod, $selectedMonth, true, true);
        if (!empty($computed['items'])) {
            $forecastData = $computed['items'];
        }
    } catch (Throwable $e) {
        error_log('[Insights] Dynamic forecast calculation error: ' . $e->getMessage());
    }

    // 2. Direct database query fallback: always query real warehouse products and live inventory
    if (empty($forecastData) && isset($conn)) {
        $directSql = "
            SELECT p.product_id, p.product_name, p.quantity AS current_stock, p.reorder_point, p.category AS category_name,
                   COALESCE(t.recent_sales, 0) AS total_historical_sales
            FROM products p
            LEFT JOIN (
                SELECT product_id, SUM(quantity) as recent_sales
                FROM transactions
                WHERE transaction_type = 'stock_out' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL {$daysLookback} DAY)
                GROUP BY product_id
            ) t ON p.product_id = t.product_id
            WHERE p.archived = 0
            ORDER BY (p.quantity <= p.reorder_point) DESC, p.quantity ASC, total_historical_sales DESC
            LIMIT 12
        ";
        $directRes = $conn->query($directSql);
        if ($directRes) {
            while ($r = $directRes->fetch_assoc()) {
                $stock = (int)$r['current_stock'];
                $reorder = (int)$r['reorder_point'];
                $sales = (float)$r['total_historical_sales'];
                $insight = InsightsEngine::buildSmartInsight($stock, $sales, $daysLookback, $reorder);
                $r['type']          = $insight['type'];
                $r['action_needed'] = $insight['action_needed'];
                $r['descriptive']   = $insight['descriptive'];
                $r['predictive']    = $insight['predictive'];
                $r['prescriptive']  = $insight['prescriptive'];
                $r['insight']       = $insight['insight'];
                $forecastData[]     = $r;
            }
        }
    }
}

$highlightProductId = null;
if (isset($_GET['product_id']) && (int)$_GET['product_id'] > 0) {
    $highlightProductId = (int)$_GET['product_id'];
}

if ($highlightProductId && isset($conn)) {
    $alreadyListed = false;
    foreach ($forecastData as &$forecastItem) {
        if (isset($forecastItem['product_id']) && (int)$forecastItem['product_id'] === $highlightProductId) {
            $forecastItem['highlight'] = true;
            $alreadyListed = true;
        }
    }
    unset($forecastItem);

    if (!$alreadyListed) {
        $productStmt = $conn->prepare("
            SELECT product_id, product_name, quantity AS current_stock, reorder_point, category AS category_name
            FROM products WHERE product_id = ?
        ");
        if ($productStmt) {
            $productStmt->bind_param("i", $highlightProductId);
            $productStmt->execute();
            $productResult = $productStmt->get_result();
            if ($productRow = $productResult->fetch_assoc()) {
                $stock = (int)$productRow['current_stock'];
                $reorderPoint = isset($productRow['reorder_point']) ? (int)$productRow['reorder_point'] : null;

                // Query historical sales velocity in past 30 days
                $salesStmt = $conn->prepare("
                    SELECT COALESCE(SUM(quantity), 0) AS total_sales
                    FROM transactions
                    WHERE product_id = ? AND transaction_type = 'stock_out' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ");
                $recentSales = 0.0;
                if ($salesStmt) {
                    $salesStmt->bind_param("i", $highlightProductId);
                    $salesStmt->execute();
                    $salesRes = $salesStmt->get_result()->fetch_assoc();
                    $recentSales = (float)($salesRes['total_sales'] ?? 0);
                    $salesStmt->close();
                }

                // If no sales in past 30 days, check all-time sales average
                if ($recentSales <= 0) {
                    $allTimeStmt = $conn->prepare("
                        SELECT COALESCE(SUM(quantity), 0) AS total_sales, COUNT(*) as cnt
                        FROM transactions
                        WHERE product_id = ? AND transaction_type = 'stock_out'
                    ");
                    if ($allTimeStmt) {
                        $allTimeStmt->bind_param("i", $highlightProductId);
                        $allTimeStmt->execute();
                        $allRes = $allTimeStmt->get_result()->fetch_assoc();
                        $cnt = max(1, (int)($allRes['cnt'] ?? 0));
                        $allTimeSales = (float)($allRes['total_sales'] ?? 0);
                        if ($allTimeSales > 0) {
                            $recentSales = round($allTimeSales / $cnt);
                        }
                        $allTimeStmt->close();
                    }
                }

                // If still zero and reorder point exists, estimate baseline from reorder point
                if ($recentSales <= 0 && $reorderPoint && $reorderPoint > 0) {
                    $recentSales = (float)$reorderPoint;
                }

                $insightData = InsightsEngine::buildSmartInsight($stock, $recentSales, 30, $reorderPoint);

                $productRow['total_historical_sales'] = $recentSales;
                $productRow['type'] = $insightData['type'];
                $productRow['action_needed'] = $insightData['action_needed'];
                $productRow['descriptive'] = $insightData['descriptive'];
                $productRow['predictive'] = $insightData['predictive'];
                $productRow['prescriptive'] = $insightData['prescriptive'];
                $productRow['insight'] = $insightData['insight'];
                $productRow['highlight'] = true;
                array_unshift($forecastData, $productRow);
            }
            $productStmt->close();
        }
    }
}


if (isset($conn)) {
    foreach ($forecastData as &$forecastItem) {
        if (!empty($forecastItem['category_name'])) {
            $forecastItem['category_name'] = resolveInsightCategory($forecastItem['category_name'], $conn);
        }
    }
    unset($forecastItem);
}

// --- ML Forecast Data (read-only; never trains on page load) ---
$seasonalChartData = $forecastService->getSeasonalChartData();
$accuracyTrend = $forecastService->getAccuracyTrend();
$segmentAccuracy = $forecastService->getSegmentAccuracy();
$salesForecastChart = $forecastService->getSalesForecastChartData($daysLookback);
$heatmapForecast = $forecastService->getProductForecastDemand(8);
$depletionTrend = $forecastService->getStockDepletionTrend();
$reorderFrequencyTrend = $forecastService->getReorderFrequencyTrend();
$lastMlRun = $mlManager->getLastRunStatus();

$seasonalHighlights = [];
if ($hasMlModel && !empty($seasonalChartData['month_labels']) && !empty($seasonalChartData['seasonal_index'])) {
    $pairs = [];
    for ($i = 0; $i < count($seasonalChartData['month_labels']); $i++) {
        $pairs[] = [
            'month' => $seasonalChartData['month_labels'][$i],
            'index' => (float)($seasonalChartData['seasonal_index'][$i] ?? 1.0),
        ];
    }
    usort($pairs, function ($a, $b) {
        return $b['index'] <=> $a['index'];
    });
    $seasonalHighlights = array_slice($pairs, 0, 3);
}
// $conn is kept open for layout and cleanly closed at end of request

require_once 'src/layout.php';
renderLayoutStart('Smart Insights & Analytics', $role, $_SESSION['Username']);
?>



<style>
:root, [data-theme="dark"] {
    --insights-bg: #07111f;
    --insights-surface: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
    --insights-surface-alt: rgba(19, 39, 91, 0.4);
    --insights-border: rgba(42, 68, 132, 0.6);
    --insights-border-soft: rgba(42, 68, 132, 0.32);
    --insights-text: #E3E3E3;
    --insights-text-muted: #9aa6d1;
    --insights-heading: #FFFFFF;
    --insights-input: rgba(19, 39, 91, 0.8);
    --insights-input-border: rgba(42, 68, 132, 0.5);
    --insights-table-head: rgba(19, 39, 91, 0.6);
    --insights-row-hover: rgba(81, 213, 90, 0.06);
    --insights-shadow: 0 10px 30px rgba(0, 0, 0, 0.22);
    --insights-banner: linear-gradient(135deg, #1D387B 0%, #0F204B 100%);
    --insights-accent: #51D55A;
}

[data-theme="light"] {
    --insights-bg: #EAF2FA;
    --insights-surface: #FFFFFF;
    --insights-surface-alt: #FFFFFF;
    --insights-border: #BFCFE0;
    --insights-border-soft: #D0DCE8;
    --insights-text: #1E293B;
    --insights-text-muted: #4A5D72;
    --insights-heading: #0F172A;
    --insights-input: #F1F5F9;
    --insights-input-border: #BFCFE0;
    --insights-table-head: #EFF6FF;
    --insights-row-hover: rgba(59, 130, 246, 0.06);
    --insights-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 4px 16px rgba(15, 23, 42, 0.08);
    --insights-banner: #FFFFFF;
    --insights-accent: #0F6E2C;
}

body {
    font-family: 'Onest', sans-serif !important;
    color: var(--insights-text);
    background: var(--insights-bg);
}

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.grid-forecast-ml { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; margin-bottom: 24px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 24px; }
@media(max-width: 1200px) { .grid-4 { grid-template-columns: 1fr 1fr; } }
@media(max-width: 768px) { .grid-2, .grid-3, .grid-4, .grid-forecast-ml { grid-template-columns: 1fr; } }

.card, .forecast-card {
    background: var(--insights-surface);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--insights-border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--insights-shadow);
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, opacity 0.3s;
}

.card:hover, .forecast-card:hover {
    transform: translateY(-2px);
}

.forecast-highlight-card {
    border-color: var(--insights-border);
}

.search-box, .filter-select, .dropdown-select {
    height: 46px;
    background-color: var(--insights-input);
    border: 1px solid var(--insights-input-border);
    border-radius: 12px;
    color: var(--insights-text);
    padding: 0 16px;
    padding-right: 32px;
    font-family: 'Onest', sans-serif;
    font-size: 14px;
    outline: none;
    transition: all 0.3s;
    appearance: auto;
    -webkit-appearance: menulist;
    cursor: pointer;
}
.search-box::placeholder {
    color: var(--insights-text-muted);
}
.search-box:focus, .filter-select:focus, .dropdown-select:focus {
    border-color: var(--insights-accent);
    box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.16);
}
.search-filter-container {
    display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
}
.search-box { flex: 1; min-width: 200px; }

h1, h2, h3, .product-title { color: var(--insights-heading); font-weight: 600; }
.text-muted, .product-sub { color: var(--insights-text-muted); }
.text-accent { color: var(--insights-accent); }

.card [style*="color: #E3E3E3"],
.card [style*="color:#E3E3E3"],
.card [style*="color: #fff"],
.card [style*="color:#fff"],
.card [style*="color: #9aa6d1"],
.card [style*="color:#9aa6d1"] {
    color: var(--insights-heading) !important;
}

.card [style*="color: #9aa6d1"],
.card [style*="color:#9aa6d1"] {
    color: var(--insights-text-muted) !important;
}

.metric-card {
    background: var(--insights-surface);
    border: 1px solid var(--insights-border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    box-shadow: var(--insights-shadow);
    text-align: left !important;
}
.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}
.metric-card.active {
    border-color: rgba(81, 213, 90, 0.6) !important;
    box-shadow: 0 2px 10px rgba(81, 213, 90, 0.15) !important;
}
[data-theme="light"] .metric-card:hover {
    box-shadow: 0 6px 18px rgba(55, 75, 99, 0.1);
}
[data-theme="light"] .metric-card.active {
    border-color: #10b981 !important;
    box-shadow: 0 2px 10px rgba(16, 185, 129, 0.12) !important;
}

.stat-card-sub {
    font-family: 'Onest', sans-serif;
    font-size: 12px;
    font-weight: 500;
    color: var(--insights-text-muted, #9aa6d1);
    margin-top: 4px;
    line-height: 1.3;
    text-transform: none !important;
    letter-spacing: normal !important;
}
[data-theme="light"] .stat-card-sub {
    color: #64748B !important;
    font-weight: 500 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
}

.card-section-title,
.forecast-card-main-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--insights-heading, #E3E3E3);
    line-height: 1.35;
}
[data-theme="light"] .card-section-title,
[data-theme="light"] .forecast-card-main-title {
    color: #0F172A !important;
}

.smart-restock-heading-text {
    font-size: 15px;
    font-weight: 600;
    color: #51D55A;
    line-height: 1.35;
}
[data-theme="light"] .smart-restock-heading-text {
    color: #16a34a !important;
}

.smart-restock-subtext {
    font-size: 12.5px;
    color: var(--insights-text-muted, #9aa6d1);
    line-height: 1.4;
}
[data-theme="light"] .smart-restock-subtext {
    color: #64748B !important;
}

.iw-table-scroll {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
}

.tab-container {
    display: flex; gap: 18px; border-bottom: 1px solid var(--insights-border); margin-bottom: 24px; padding-bottom: 0;
}
.tab {
    padding: 12px 4px 14px;
    color: var(--insights-text-muted);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    position: relative;
    transition: color 0.2s ease, transform 0.2s ease;
}
.tab:hover { color: var(--insights-heading); }
.tab.active { color: var(--insights-accent); }
.tab.active::after {
    content: ''; position: absolute; bottom: -1px; left: 0; width: 100%; height: 3px;
    background: var(--insights-accent);
    border-radius: 999px;
}
.tab-content { display: none; animation: fadeIn 0.4s ease; }
.tab-content.active { display: block; }
/* br helpers inside tab labels — hidden globally, shown only on mobile */
.tab-br { display: none; }

.table { width: 100%; border-collapse: collapse; }
.table th {
    text-align: left;
    color: var(--insights-text-muted);
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.06em;
    padding: 14px 16px;
    border-bottom: 1px solid var(--insights-border-soft);
    background: var(--insights-table-head);
}
.table td {
    padding: 14px 16px;
    color: var(--insights-text);
    font-size: 14px;
    border-bottom: 1px solid var(--insights-border-soft);
    vertical-align: middle;
}
.table tr:hover td { background-color: var(--insights-row-hover); }
.table tr.hidden, .table tbody tr.hidden, tr.hidden,
.table tr[style*="display: none"], .table tr[style*="display:none"] {
    display: none !important;
}

.badge {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
    display: inline-block;
}
.badge.fast_moving, .badge.normal, .badge.low { background: rgba(81, 213, 90, 0.15) !important; color: #51D55A !important; border: 1px solid rgba(81, 213, 90, 0.3) !important; }
.badge.medium_moving, .badge.low_stock, .badge.medium { background: rgba(234, 179, 8, 0.15) !important; color: #fde047 !important; border: 1px solid rgba(234, 179, 8, 0.3) !important; }
.badge.slow_moving, .badge.overstocked { background: rgba(168, 85, 247, 0.15) !important; color: #c084fc !important; border: 1px solid rgba(168, 85, 247, 0.3) !important; }
.badge.non_moving, .badge.out_of_stock, .badge.high { background: rgba(239, 68, 68, 0.15) !important; color: #f87171 !important; border: 1px solid rgba(239, 68, 68, 0.3) !important; }


.btn {
    background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
    color: #ffffff;
    border: 1px solid transparent;
    border-radius: 10px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 8px 18px rgba(81, 213, 90, 0.18);
}
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(81, 213, 90, 0.22);
    color: #ffffff;
}
.btn-secondary {
    background: rgba(81, 213, 90, 0.12) !important;
    color: #51D55A !important;
    border: 1.5px solid rgba(81, 213, 90, 0.45) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(81, 213, 90, 0.1) !important;
}
.btn-secondary:hover {
    background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important;
    color: #ffffff !important;
    border-color: #51D55A !important;
    box-shadow: 0 4px 16px rgba(81, 213, 90, 0.4) !important;
    transform: translateY(-1.5px);
}

@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
.recommendation-card {
    padding: 16px;
    background: var(--insights-surface-alt);
    border-radius: 12px;
    border: 1px solid var(--insights-border);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}
.recommendation-card.success { border-left: 4px solid #16a34a; }
.recommendation-card.warning { border-left: 4px solid #f59e0b; }
.recommendation-card.info { border-left: 4px solid #3b82f6; }
.recommendation-card.purple { border-left: 4px solid #8b5cf6; }
.recommendation-card .card-label {
    font-weight: 600; font-size: 14px; margin-bottom: 4px; color: var(--insights-heading);
}
.recommendation-card .card-copy {
    color: var(--insights-text-muted); font-size: 13px; line-height: 1.5;
}
.loading-overlay {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(10, 18, 42, 0.82);
    backdrop-filter: blur(5px);
    display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 12px;
    border-radius: 16px; z-index: 10; opacity: 0; pointer-events: none; transition: opacity 0.25s ease;
}
.forecast-card.loading .loading-overlay { opacity: 1; pointer-events: all; }
.spinner {
    width: 42px; height: 42px; border: 3px solid rgba(148, 163, 184, 0.2);
    border-top: 3px solid var(--insights-accent); border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
.loading-overlay-text {
    font-size: 13px; font-weight: 600; color: #51D55A; letter-spacing: 0.3px;
    font-family: 'Onest', sans-serif;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* ─── Refresh Forecast & Live Stock Sync Button ────────── */
.refresh-forecast-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 34px;
    padding: 0 14px;
    color: #51D55A;
    background: rgba(81, 213, 90, 0.12);
    border: 1px solid rgba(81, 213, 90, 0.38);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    letter-spacing: 0.2px;
    white-space: nowrap;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}
.refresh-forecast-btn:hover {
    background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%);
    color: #ffffff !important;
    border-color: #51D55A;
    box-shadow: 0 4px 14px rgba(81, 213, 90, 0.38);
    transform: translateY(-1px);
}
.refresh-forecast-btn:active {
    transform: translateY(1px);
}
.refresh-forecast-btn.is-loading {
    background: rgba(81, 213, 90, 0.18) !important;
    border-color: rgba(81, 213, 90, 0.6) !important;
    color: #51D55A !important;
    pointer-events: none;
    box-shadow: 0 0 14px rgba(81, 213, 90, 0.25);
}
.refresh-forecast-btn.is-success {
    background: rgba(16, 185, 129, 0.2) !important;
    border-color: #10b981 !important;
    color: #34d399 !important;
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
}

.refresh-icon-holder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    position: relative;
}
.refresh-default-icon {
    display: inline-block !important;
}
.refresh-spinner-svg {
    animation: forecastSpin 0.75s linear infinite;
    display: none !important;
}
.refresh-check-icon {
    display: none !important;
}
.refresh-forecast-btn.is-loading .refresh-default-icon {
    display: none !important;
}
.refresh-forecast-btn.is-loading .refresh-spinner-svg {
    display: inline-block !important;
}
.refresh-forecast-btn.is-loading .refresh-check-icon {
    display: none !important;
}
.refresh-forecast-btn.is-success .refresh-default-icon,
.refresh-forecast-btn.is-success .refresh-spinner-svg {
    display: none !important;
}
.refresh-forecast-btn.is-success .refresh-check-icon {
    display: inline-block !important;
}

@keyframes forecastSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.forecast-sync-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 500;
    font-family: 'Onest', sans-serif;
    color: var(--insights-text-muted);
    background: rgba(148, 163, 184, 0.09);
    border: 1px solid rgba(148, 163, 184, 0.2);
}
.forecast-sync-pill .sync-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #51D55A;
    box-shadow: 0 0 6px rgba(81, 213, 90, 0.8);
    flex-shrink: 0;
    transition: all 0.3s ease;
}
.forecast-sync-pill.is-loading .sync-dot {
    background: #f59e0b;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.85);
    animation: syncDotPulse 0.8s infinite alternate;
}
@keyframes syncDotPulse {
    0% { transform: scale(0.85); opacity: 0.6; }
    100% { transform: scale(1.25); opacity: 1; }
}

#forecastTableBody.is-refreshing {
    opacity: 0.45;
    filter: blur(0.5px);
    transition: all 0.25s ease;
}

.info-icon {
    display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px;
    border-radius: 50%; border: 1px solid var(--insights-text-muted); color: var(--insights-text-muted);
    font-size: 11px; cursor: pointer; margin-left: 8px;
}
.tooltip-text {
    visibility: hidden; width: 240px; background-color: var(--insights-bg); color: var(--insights-text);
    border: 1px solid var(--insights-border); border-radius: 8px; padding: 12px; position: absolute; z-index: 20;
    top: 100%; right: 0; opacity: 0; transition: opacity 0.3s; font-size: 12px; line-height: 1.5;
    box-shadow: 0 10px 25px rgba(0,0,0,0.12); margin-top: 8px;
}
.tooltip-container:hover .tooltip-text { visibility: visible; opacity: 1; }
.chart-container { position: relative; height: 300px; width: 100%; }
.chart-container-lg { position: relative; height: 320px; width: 100%; }
.chart-container-xl { position: relative; height: 280px; width: 100%; }

.info-tooltip { display: none; }

.forecast-nav-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid var(--insights-border); padding-bottom: 12px; align-items: center; }
.forecast-nav-btn {
    color: var(--insights-text-muted); text-decoration: none; font-size: 13px; padding: 8px 16px; border-radius: 999px;
    transition: all 0.2s; background: var(--insights-surface-alt); border: 1px solid transparent; cursor: pointer;
}
.forecast-nav-btn:hover { background: var(--insights-surface); color: var(--insights-heading); border-color: var(--insights-border); }
.forecast-nav-btn.active {
    background: rgba(81, 213, 90, 0.12);
    color: var(--insights-accent); border: 1px solid rgba(81, 213, 90, 0.28); font-weight: 700;
}

/* ─── Philippine Seasonal Intelligence Dual Dropdowns ─── */
.seasonal-dropdown-row {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    margin-bottom: 20px;
    padding: 2px 0;
}
.seasonal-dropdown-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}
.seasonal-dropdown-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Onest', sans-serif;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--insights-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    user-select: none;
}
.seasonal-dropdown-label svg {
    color: #51D55A;
    flex-shrink: 0;
}
.seasonal-select-wrapper {
    position: relative;
    width: 100%;
}
.seasonal-dropdown-control {
    width: 100%;
    height: 44px;
    background-color: var(--insights-input);
    border: 1.5px solid var(--insights-border-soft);
    border-radius: 10px;
    color: var(--insights-text);
    padding: 0 38px 0 14px;
    font-family: 'Onest', sans-serif;
    font-size: 13.5px;
    font-weight: 600;
    outline: none;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
}
.seasonal-dropdown-control:hover {
    border-color: rgba(81, 213, 90, 0.45);
    background-color: rgba(81, 213, 90, 0.04);
}
.seasonal-dropdown-control:focus {
    border-color: #51D55A;
    box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.2);
}
.seasonal-dropdown-control option {
    background-color: #0b1329;
    color: #F3F4F6;
    padding: 10px;
    font-size: 13.5px;
    font-weight: 500;
}
[data-theme="light"] .seasonal-dropdown-control {
    background-color: #ffffff;
    border-color: #e5e7eb;
    color: #111827;
}
[data-theme="light"] .seasonal-dropdown-control:hover {
    border-color: #16a34a;
    background-color: rgba(22, 163, 74, 0.04);
}
[data-theme="light"] .seasonal-dropdown-control:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
}
[data-theme="light"] .seasonal-dropdown-control option {
    background-color: #ffffff;
    color: #111827;
}
.seasonal-dropdown-arrow {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--insights-text-muted);
    pointer-events: none;
    transition: transform 0.15s ease, color 0.15s ease;
}
.seasonal-select-wrapper:focus-within .seasonal-dropdown-arrow {
    color: #51D55A;
}
[data-theme="light"] .seasonal-select-wrapper:focus-within .seasonal-dropdown-arrow {
    color: #16a34a;
}
@media (max-width: 640px) {
    .seasonal-dropdown-row {
        flex-direction: column;
        gap: 12px;
    }
}

/* ─── Intelligence Overview Banner ────────────────────────── */
.ph-intel-banner {
    background: var(--insights-surface-alt);
    border: 1px solid var(--insights-border);
    border-radius: 14px;
    padding: 22px 24px;
    margin-bottom: 22px;
}
[data-theme="light"] .ph-intel-banner {
    background: #ffffff;
    border-color: #cbd5e1;
}
.ph-header-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.ph-season-pill {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    background: rgba(81, 213, 90, 0.12);
    color: #51D55A;
    border: 1px solid rgba(81, 213, 90, 0.35);
}
[data-theme="light"] .ph-season-pill {
    background: rgba(81, 213, 90, 0.15);
    color: #15803d;
    border-color: rgba(22, 163, 74, 0.4);
}
.ph-weather-subtitle {
    font-size: 12px;
    color: var(--insights-text-muted);
    font-weight: 500;
}
.ph-main-heading {
    font-size: 20px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 6px 0;
    letter-spacing: -0.2px;
}
[data-theme="light"] .ph-main-heading {
    color: #0f172a;
}
.ph-main-subtext {
    font-size: 13px;
    color: var(--insights-text-muted);
    margin: 0;
    line-height: 1.45;
}

/* Section labels */
.ph-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--insights-text-muted);
    margin: 22px 0 10px 0;
}

/* Consumption triggers: 2-column grid */
.consumption-triggers-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
@media (max-width: 640px) {
    .consumption-triggers-grid {
        grid-template-columns: 1fr;
    }
}
.trigger-card {
    background: var(--insights-input);
    border: 1px solid var(--insights-border-soft);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    line-height: 1.45;
    color: var(--insights-text);
}
[data-theme="light"] .trigger-card {
    background: #f8fafc;
    border-color: #e2e8f0;
}

/* Demand lift: 3x2 metric cards */
.demand-lift-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
@media (max-width: 768px) {
    .demand-lift-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 480px) {
    .demand-lift-grid {
        grid-template-columns: 1fr;
    }
}
.metric-lift-card {
    background: var(--insights-input);
    border: 1px solid var(--insights-border-soft);
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
[data-theme="light"] .metric-lift-card {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.metric-lift-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--insights-text-muted);
}
.metric-lift-value {
    display: flex;
    align-items: baseline;
    gap: 6px;
}
/* GREEN IS RESERVED STRICTLY FOR POSITIVE NUMBERS */
.lift-multiplier {
    font-size: 18px;
    font-weight: 800;
    color: #51D55A;
    letter-spacing: -0.3px;
}
[data-theme="light"] .lift-multiplier {
    color: #16a34a;
}
.lift-percentage {
    font-size: 12px;
    font-weight: 600;
    color: var(--insights-text-muted);
}

/* ─── Products Table Redesign (High Density, Line-item) ───── */
.ph-products-container {
    background: var(--insights-surface-alt);
    border: 1px solid var(--insights-border);
    border-radius: 14px;
    padding: 20px;
    contain: layout paint;
}
[data-theme="light"] .ph-products-container {
    background: #ffffff;
    border-color: #cbd5e1;
}
.ph-table-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 8px;
}
.ph-line-table {
    width: 100%;
    border-collapse: collapse;
}
.ph-line-table th {
    text-align: left;
    color: var(--insights-text-muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--insights-border);
}
.ph-line-table td {
    padding: 12px 12px;
    border-bottom: 1px solid var(--insights-border-soft);
    vertical-align: top;
    background: transparent;
}
.ph-line-table tr:hover td {
    background: var(--insights-row-hover);
}

/* Product Column */
.table-product-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--insights-heading);
    margin-bottom: 3px;
}
.table-product-meta {
    font-size: 12px;
    color: var(--insights-text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.meta-sep {
    color: var(--insights-border);
}
/* Surge % in green right in product header line */
.surge-tag {
    color: #51D55A;
    font-weight: 700;
}
[data-theme="light"] .surge-tag {
    color: #16a34a;
}
.table-status-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 5px;
}
.table-status-pill.status-critical {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.table-status-pill.status-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.table-status-pill.status-opportunity {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

/* Intel / Sentence Column */
.table-intel-sentence {
    font-size: 12.5px;
    line-height: 1.45;
    color: var(--insights-text-muted);
}
/* Non-routine action alert box */
.table-action-callout {
    display: inline-flex;
    align-items: flex-start;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 500;
    line-height: 1.35;
    margin-top: 6px;
}
.table-action-callout strong {
    font-weight: 700;
    color: inherit;
}
.table-intel-sentence strong {
    font-weight: 700;
    color: var(--insights-heading);
}
.table-action-callout.callout-urgent {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}
[data-theme="light"] .table-action-callout.callout-urgent {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}
.table-action-callout.callout-warning {
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #fde68a;
}
[data-theme="light"] .table-action-callout.callout-warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #d97706;
}
.table-action-callout.callout-opportunity {
    background: rgba(59, 130, 246, 0.12);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #93c5fd;
}
[data-theme="light"] .table-action-callout.callout-opportunity {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #2563eb;
}

/* Stock & Action Column */
.table-stock-wrap {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}
.table-stock-num {
    font-size: 13px;
    font-weight: 700;
    color: var(--insights-heading);
}
.table-stock-label {
    font-size: 11px;
    font-weight: 400;
    color: var(--insights-text-muted);
}
.btn-table-check {
    background: transparent;
    border: 1px solid var(--insights-border);
    color: var(--insights-text-muted);
    font-family: 'Onest', sans-serif;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.btn-table-check:hover {
    color: #51D55A;
    border-color: #51D55A;
    background: rgba(81, 213, 90, 0.08);
}

/* Philippine Seasonal Intelligence styles moved to src/partials/ph_seasonal_modal.php */

.smart-restock-banner {
    background: var(--insights-banner);
    border: 1px solid rgba(81, 213, 90, 0.25); border-radius: 16px; padding: 24px; position: relative; overflow: hidden;
}
.bg-icon {
    position: absolute; top: -15px; right: -10px; width: 140px; height: 140px; opacity: 0.05;
    transform: rotate(15deg); pointer-events: none; color: var(--insights-heading);
}
.bg-icon svg { width: 100%; height: 100%; }
.forecast-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
.forecast-table th {
    text-align: left; color: var(--insights-text-muted); font-size: 11px; font-weight: 700; uppercase; tracking-wider;
    padding: 12px; border-bottom: 1px solid var(--insights-border-soft); background: var(--insights-surface-alt);
}
.forecast-table td { padding: 16px 12px; border-bottom: 1px solid var(--insights-border-soft); vertical-align: top; }
.insight-text { color: var(--insights-text); font-size: 13px; line-height: 1.6; }
.insight-text strong { color: var(--insights-heading); }
/* ─── Button Design System & Visual Hierarchy ───────────────── */
/* Tier 1: Primary Action Buttons */
.premium-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 38px;
    padding: 0 20px;
    background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%) !important;
    border: 1px solid #51D55A !important;
    border-radius: 10px;
    color: #ffffff !important;
    font-family: 'Onest', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(81, 213, 90, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    text-decoration: none;
    user-select: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.premium-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(81, 213, 90, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.3);
    filter: brightness(1.05);
}
.premium-button:active:not(:disabled) {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(81, 213, 90, 0.3);
}
.premium-button:disabled {
    opacity: 0.4 !important;
    cursor: not-allowed !important;
    pointer-events: none !important;
    box-shadow: none !important;
}

/* Tier 3: Pagination & Navigation Buttons */
.pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 38px;
    min-width: 38px;
    padding: 0 12px;
    background: rgba(19, 39, 91, 0.95) !important;
    border: 1.5px solid rgba(81, 213, 90, 0.5) !important;
    border-radius: 10px;
    color: #51D55A !important;
    font-family: 'Onest', sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    text-decoration: none;
    user-select: none;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.pagination-btn.pagination-num {
    min-width: 38px !important;
    width: 38px !important;
    height: 38px !important;
    padding: 0 !important;
}
.pagination-btn.pagination-nav {
    height: 38px !important;
    min-width: 86px !important;
    padding: 0 16px !important;
}
.pagination-btn:hover:not(:disabled):not(.disabled) {
    background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important;
    color: #ffffff !important;
    border-color: #51D55A !important;
    box-shadow: 0 4px 16px rgba(81, 213, 90, 0.45);
    transform: translateY(-1.5px);
}
.pagination-btn:hover:not(:disabled):not(.disabled) svg {
    stroke: #ffffff !important;
}
.pagination-btn:active:not(:disabled):not(.disabled) {
    transform: translateY(1px);
    box-shadow: 0 1px 4px rgba(81, 213, 90, 0.2);
}
.pagination-btn.active,
.pagination-btn.active:disabled {
    background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important;
    color: #ffffff !important;
    border-color: #51D55A !important;
    box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4) !important;
    opacity: 1 !important;
    pointer-events: none !important;
    cursor: default !important;
    font-weight: 800 !important;
}
.pagination-btn:disabled:not(.active),
.pagination-btn.disabled:not(.active) {
    opacity: 0.35 !important;
    cursor: not-allowed !important;
    pointer-events: none !important;
    border-color: rgba(42, 68, 132, 0.35) !important;
    color: #6b7aab !important;
    background: rgba(19, 39, 91, 0.4) !important;
    box-shadow: none !important;
}
.pagination-info-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    border-radius: 10px;
    background: rgba(19, 39, 91, 0.6);
    border: 1px solid rgba(42, 68, 132, 0.45);
    color: #9aa6d1;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Onest', sans-serif;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 34px;
    padding: 0 16px;
    color: #51D55A;
    border: 1px solid rgba(81, 213, 90, 0.4);
    border-radius: 8px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.2px;
    white-space: nowrap;
    cursor: pointer;
    background: rgba(81, 213, 90, 0.1);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.action-btn:hover {
    background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
    color: #ffffff !important;
    border-color: #51D55A;
    box-shadow: 0 4px 14px rgba(81, 213, 90, 0.35);
    transform: translateY(-1px);
}
.action-btn:active {
    transform: translateY(1px);
}

.forecast-row--highlighted { background: rgba(81, 213, 90, 0.1) !important; }
.forecast-row--highlighted td { border-bottom-color: rgba(81, 213, 90, 0.25) !important; }


/* ─── Smart Restock Visual Hierarchy ───────────────────────── */
.forecast-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}
.forecast-stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    background: rgba(148, 163, 184, 0.12);
    color: var(--insights-text-muted);
    border: 1px solid rgba(148, 163, 184, 0.2);
}
.forecast-stock-pill strong {
    color: var(--insights-heading);
}
.insight-hierarchy {
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.insight-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    line-height: 1.5;
}
.insight-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.6px;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
    flex-shrink: 0;
    width: 68px;
    text-align: center;
    margin-top: 1px;
}
.insight-badge--situation {
    background: rgba(59, 130, 246, 0.14);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}
.insight-badge--forecast {
    background: rgba(245, 158, 11, 0.14);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.insight-badge--action {
    background: rgba(81, 213, 90, 0.16);
    color: #51D55A;
    border: 1px solid rgba(81, 213, 90, 0.35);
}
.insight-badge--urgent {
    background: rgba(239, 68, 68, 0.16);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.35);
}
.insight-badge--pause {
    background: rgba(168, 85, 247, 0.16);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.35);
}
.insight-badge--monitor {
    background: rgba(234, 179, 8, 0.16);
    color: #fde047;
    border: 1px solid rgba(234, 179, 8, 0.35);
}
.insight-row--action {
    background: rgba(81, 213, 90, 0.07);
    border-left: 3px solid #51D55A;
    padding: 7px 10px;
    border-radius: 0 6px 6px 0;
    margin-top: 2px;
}
.insight-row--action.action-urgent {
    background: rgba(239, 68, 68, 0.08);
    border-left: 3px solid #ef4444;
}
.insight-row--action.action-pause {
    background: rgba(168, 85, 247, 0.08);
    border-left: 3px solid #a855f7;
}
.insight-row--action.action-monitor {
    background: rgba(234, 179, 8, 0.08);
    border-left: 3px solid #eab308;
}
.insight-desc {
    color: var(--insights-text);
    flex: 1;
}
.insight-desc strong {
    color: var(--insights-heading);
    font-weight: 600;
}
.insight-desc--action {
    color: var(--insights-heading);
    font-weight: 500;
}

.info-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1px solid rgba(81, 213, 90, 0.4);
    color: #51D55A;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    background: rgba(81, 213, 90, 0.08);
    user-select: none;
    transition: all 0.2s ease;
}
.info-circle:hover {
    border-color: #51D55A;
    background: rgba(81, 213, 90, 0.2);
}

/* ─── Light Mode: Demand Forecasting & Smart Restock Redesign ─── */
[data-theme="light"] .smart-restock-banner {
    background: #FFFFFF !important;
    border: 1px solid var(--insights-border) !important;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.03) !important;
}

[data-theme="light"] .smart-restock-banner .bg-icon {
    opacity: 0.03 !important;
    color: #475569 !important;
}

[data-theme="light"] .smart-restock-heading-text,
[data-theme="light"] .smart-restock-banner [style*="color: #51D55A"] {
    color: #0F172A !important;
}

[data-theme="light"] .smart-restock-banner .section-heading-icon {
    color: #0F6E2C !important;
}

[data-theme="light"] .info-circle {
    border-color: #CBD5E1 !important;
    color: #475569 !important;
    background: #F1F5F9 !important;
}
[data-theme="light"] .info-circle:hover {
    border-color: #94A3B8 !important;
    background: #E2E8F0 !important;
    color: #1E293B !important;
}

[data-theme="light"] .forecast-table {
    background: #FFFFFF !important;
}

[data-theme="light"] .forecast-table th {
    background: #F8FAFC !important;
    color: #475569 !important;
    border-bottom: 1px solid #E2E8F0 !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
    padding: 12px 14px !important;
}

[data-theme="light"] .forecast-table td {
    border-bottom: 1px solid #F1F5F9 !important;
    padding: 16px 14px !important;
    background: transparent !important;
}

[data-theme="light"] .forecast-table tbody tr {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03) !important;
}

[data-theme="light"] .forecast-table tbody td {
    border-bottom: 1px solid #F1F5F9 !important;
}

[data-theme="light"] .forecast-table tr:hover td {
    background: #F8FAFC !important;
}

[data-theme="light"] .forecast-row--highlighted td,
[data-theme="light"] .forecast-table tbody tr.forecast-row--highlighted {
    background: #F0FDF4 !important;
    border-color: #86EFAC !important;
}

[data-theme="light"] .forecast-table .product-title {
    color: #0F172A !important;
    font-weight: 700 !important;
    font-size: 14px !important;
}

[data-theme="light"] .forecast-table .product-sub {
    color: #64748B !important;
    font-size: 12px !important;
}

[data-theme="light"] .forecast-stock-pill {
    background: #F1F5F9 !important;
    color: #475569 !important;
    border: 1px solid #CBD5E1 !important;
}

[data-theme="light"] .forecast-stock-pill strong {
    color: #0F172A !important;
}

[data-theme="light"] .forecast-stock-pill[style*="51D55A"] {
    background: #F0FDF4 !important;
    border-color: #86EFAC !important;
    color: #15803D !important;
}

/* Insight Badges: Context, Forecast, Action */
[data-theme="light"] .insight-badge--situation {
    background: #EFF6FF !important;
    color: #1D4ED8 !important;
    border: 1px solid #BFDBFE !important;
}

[data-theme="light"] .insight-badge--forecast {
    background: #FFFBEB !important;
    color: #B45309 !important;
    border: 1px solid #FDE68A !important;
}

[data-theme="light"] .insight-badge--action {
    background: #F0FDF4 !important;
    color: #15803D !important;
    border: 1px solid #BBF7D0 !important;
}

[data-theme="light"] .insight-badge--urgent {
    background: #FEF2F2 !important;
    color: #DC2626 !important;
    border: 1px solid #FECACA !important;
}

[data-theme="light"] .insight-badge--pause {
    background: #FAF5FF !important;
    color: #7E22CE !important;
    border: 1px solid #E9D5FF !important;
}

[data-theme="light"] .insight-badge--monitor {
    background: #FEFCE8 !important;
    color: #A16207 !important;
    border: 1px solid #FEF08A !important;
}

/* Insight Action Row Strips */
[data-theme="light"] .insight-row--action {
    background: #F0FDF4 !important;
    border-left: 3px solid #16A34A !important;
    border-radius: 0 6px 6px 0 !important;
}

[data-theme="light"] .insight-row--action.action-urgent {
    background: #FEF2F2 !important;
    border-left: 3px solid #DC2626 !important;
}

[data-theme="light"] .insight-row--action.action-pause {
    background: #FAF5FF !important;
    border-left: 3px solid #9333EA !important;
}

[data-theme="light"] .insight-row--action.action-monitor {
    background: #FEFCE8 !important;
    border-left: 3px solid #CA8A04 !important;
}

[data-theme="light"] .insight-desc {
    color: #334155 !important;
}

[data-theme="light"] .insight-desc strong {
    color: #0F172A !important;
    font-weight: 600 !important;
}

[data-theme="light"] .insight-desc--action {
    color: #0F172A !important;
    font-weight: 600 !important;
}

/* Check Stock Button in Light Mode */
[data-theme="light"] .action-btn,
[data-theme="light"] .check-stock-btn {
    background: #F0FDF4 !important;
    color: #15803D !important;
    border: 1.5px solid #86EFAC !important;
    box-shadow: 0 1px 3px rgba(16, 185, 129, 0.12) !important;
    font-weight: 600 !important;
}

[data-theme="light"] .action-btn:hover,
[data-theme="light"] .check-stock-btn:hover {
    background: #16A34A !important;
    color: #FFFFFF !important;
    border-color: #16A34A !important;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25) !important;
    transform: translateY(-1px) !important;
}

/* Philippine Seasonal Guide Button (FAB) styles moved to src/partials/ph_seasonal_modal.php */

[data-theme="light"] .pagination-info-pill {
    background: #F1F5F9 !important;
    border-color: #CBD5E1 !important;
    color: #475569 !important;
}
@media (max-width: 767px) {
    .insight-row {
        flex-direction: column;
        gap: 4px;
    }
    .insight-badge {
        width: auto;
        min-width: 60px;
    }
}

.forecast-product-link {
    display: block;
    text-decoration: none;
    color: inherit;
    border-radius: 8px;
    padding: 4px;
    margin: -4px;
    transition: transform 0.2s ease, background-color 0.2s ease;
    cursor: pointer;
}
.forecast-product-link:hover {
    background: rgba(81, 213, 90, 0.08);
    transform: translateX(4px);
}
.forecast-product-link:hover .product-title { color: var(--insights-accent); }
.section-heading-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    color: var(--insights-accent);
}
.section-heading-icon svg { width: 22px; height: 22px; }

/* ─── Mobile-only fixes (≤ 767 px) ────────────────────────────── */
@media (max-width: 767px) {

    /* ── 1. STICKY FIRST COLUMN ──────────────────────────────────
       Tables that need horizontal scroll get a sticky first column.
       We give it a solid background so scrolling columns never bleed
       through, and a right-side shadow to show it's "floating". */
    .table th:first-child,
    .table td:first-child {
        position: sticky;
        left: 0;
        z-index: 2;
        /* Solid card background colours matching each theme */
        background: #12275c;          /* dark theme default */
        box-shadow: 4px 0 8px rgba(0, 0, 0, 0.35);
    }
    [data-theme="light"] .table th:first-child,
    [data-theme="light"] .table td:first-child {
        background: #ffffff !important;          /* light theme card colour */
        box-shadow: 4px 0 8px rgba(0, 0, 0, 0.08);
    }
    /* Keep hover state readable on the sticky cell */
    .table tr:hover td:first-child {
        background: #1b3474;
    }
    [data-theme="light"] .table tr:hover td:first-child {
        background: #f1f5f9 !important;
    }
    /* th sticky header also needs a higher z-index so it sits above sticky td */
    .table th:first-child { z-index: 3; }

    #productTableWrap {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    #productTable {
        min-width: 760px !important;
        width: 100% !important;
        border-collapse: collapse !important;
    }
    #productTable th,
    #productTable td {
        white-space: nowrap !important;
    }
    #productTable td:first-child,
    #productTable th:first-child {
        white-space: normal !important;
        min-width: 140px !important;
        max-width: 200px !important;
    }

    /* ── 2. GREEN SCROLLBARS ──────────────────────────────────────
       Apply the app's green accent (#51D55A) to all scrollable areas
       on mobile. Covers tab bar, table wrappers, overflow-auto divs. */
    ::-webkit-scrollbar              { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track        { background: rgba(42, 68, 132, 0.25); border-radius: 999px; }
    ::-webkit-scrollbar-thumb        { background: #51D55A; border-radius: 999px; }
    ::-webkit-scrollbar-thumb:hover  { background: #34b840; }
    /* Firefox */
    * { scrollbar-width: thin; scrollbar-color: #51D55A rgba(42, 68, 132, 0.25); }

    /* ── 3. TAB MENU — 5 equal tabs, two-line labels, no scroll ──── */
    .tab-container {
        display: flex;
        flex-wrap: nowrap;          /* single row — all 5 in one line */
        overflow-x: visible !important;
        gap: 0;
        padding-bottom: 0;
        border-bottom: 1px solid var(--insights-border);
    }
    .tab {
        flex: 1 1 0;                /* equal width, shares 100% */
        min-width: 0;
        text-align: center;
        font-size: 12px;
        font-weight: 600;
        padding: 10px 6px 14px;
        line-height: 1.35;
        white-space: normal;
        word-break: break-word;
        color: var(--insights-text-muted);
        cursor: pointer;
        position: relative;
        transition: color 0.2s ease;
    }
    .tab-br { display: inline; }    /* show br helpers on mobile */
    .tab:hover  { color: var(--insights-heading); }
    .tab.active { color: var(--insights-accent); font-weight: 700; }
    .tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 10%;
        width: 80%;
        height: 3px;
        background: var(--insights-accent);
        border-radius: 999px;
    }

    /* ── 4. SUMMARY METRIC CARDS — Row layout matching standard summary cards ── */
    .metric-card {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        text-align: left !important;
        padding: 14px 14px !important;
        min-height: 84px !important;
        border-radius: 14px !important;
        box-sizing: border-box !important;
    }
    .metric-card > div:first-child {
        min-width: 0;
        flex: 1;
    }
    .metric-card .text-xs {
        font-size: 11px !important;
        line-height: 1.25 !important;
        letter-spacing: 0.03em !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .metric-card .stat-card-sub {
        font-size: 9.5px !important;
        line-height: 1.25 !important;
        margin-top: 2px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        display: block !important;
        text-transform: none !important;
        letter-spacing: normal !important;
        font-weight: 500 !important;
    }

    /* Header uniformity across cards and forecast widget */
    .card-section-title,
    .forecast-card-main-title {
        font-size: 13.5px !important;
        font-weight: 600 !important;
        line-height: 1.3 !important;
        margin-bottom: 8px !important;
    }

    .smart-restock-heading-text {
        font-size: 13px !important;
        font-weight: 600 !important;
        line-height: 1.3 !important;
    }

    .smart-restock-subtext {
        font-size: 11px !important;
        line-height: 1.35 !important;
        margin-top: 3px !important;
    }

    .section-heading-icon {
        width: 20px !important;
        height: 20px !important;
    }
    .section-heading-icon svg {
        width: 16px !important;
        height: 16px !important;
    }
    .metric-card .num {
        font-size: 24px !important;
        line-height: 1.2 !important;
        font-weight: 800 !important;
    }
    .metric-card .w-12.h-12 {
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
        border-radius: 11px !important;
        flex-shrink: 0 !important;
    }
    .metric-card .w-12.h-12 svg {
        width: 20px !important;
        height: 20px !important;
        flex-shrink: 0 !important;
    }

    /* ── 5. PAGINATION — Single compact row, prevent wrapping next button ── */
    #productPagination,
    .pagination-container {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        width: 100% !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
    }
    .pagination-btn-group,
    #productPagination .pagination-btn-group,
    .pagination-container .pagination-btn-group {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 3px !important;
        width: auto !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        overflow: visible !important;
        padding: 0 !important;
    }
    #productPageNumbers {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 3px !important;
    }
    .pagination-nav-text {
        display: none !important;
    }
    .pagination-neighbor {
        display: none !important;
    }
    .pagination-btn {
        height: 32px !important;
        font-size: 12px !important;
        border-radius: 8px !important;
        flex-shrink: 0 !important;
    }
    .pagination-btn.pagination-num {
        min-width: 28px !important;
        width: 28px !important;
        height: 32px !important;
        padding: 0 !important;
        font-size: 12px !important;
        flex-shrink: 0 !important;
    }
    .pagination-btn.pagination-nav,
    #prevPageBtn,
    #nextPageBtn {
        min-width: 32px !important;
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        gap: 0 !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        white-space: nowrap !important;
    }
    .pagination-btn svg {
        width: 13px !important;
        height: 13px !important;
        flex-shrink: 0 !important;
    }
    .pagination-info-pill {
        font-size: 11px !important;
        padding: 4px 10px !important;
    }
    .pagination-ellipsis {
        font-size: 11px !important;
        padding: 0 1px !important;
        min-width: 8px !important;
        text-align: center !important;
        flex-shrink: 0 !important;
    }
}
</style>

<!-- Hero Page Header Banner -->
<div class="insights-hero-card" style="background: linear-gradient(135deg, rgba(29, 56, 123, 0.92) 0%, rgba(18, 39, 91, 0.96) 100%); border: 1px solid rgba(42, 68, 132, 0.6); border-radius: 18px; padding: 24px 28px; box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35); position: relative; overflow: hidden; margin-bottom: 24px;">
    <div style="position:absolute; top:-60px; right:-40px; width:300px; height:300px; background: radial-gradient(circle, rgba(81,213,90,0.08) 0%, rgba(0,0,0,0) 70%); pointer-events:none;"></div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="insights-hero-title text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="insights-hero-icon w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"></path></svg>
                </div>
                Smart Insights &amp; Analytics
            </h1>
            <p class="insights-hero-desc text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Advanced analytics powered by data mining algorithms — forecast demand, identify movement trends, and optimize warehouse restocking decisions.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="insights-hero-pill-a inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="insights-hero-pill-b inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Engine: <strong class="text-white">AI-Powered</strong>
            </span>
        </div>
    </div>
</div>


<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-[#51D55A]/50 cursor-pointer transition-all active" onclick="filterData('all')" data-filter="all">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total Products</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($totalProducts); ?></span>
            </div>
            <div class="stat-card-sub mt-1 font-onest" style="text-transform: none !important; letter-spacing: normal !important;">Active catalog SKUs</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-[#51D55A]/10 border border-[#51D55A]/20 flex items-center justify-center text-[#51D55A] group-hover:scale-110 transition-transform shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-amber-500/50 cursor-pointer transition-all" onclick="filterData('attention_needed')" data-filter="attention_needed">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Low & Out of Stock</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($attentionNeededCount); ?></span>
            </div>
            <div class="stat-card-sub mt-1 font-onest" style="text-transform: none !important; letter-spacing: normal !important;"><?php echo $attentionNeededCount === 0 ? 'All items well-stocked' : ($attentionNeededCount === 1 ? '1 item needs reorder' : number_format($attentionNeededCount) . ' items need reorder'); ?></div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-emerald-500/50 cursor-pointer transition-all" onclick="filterData('fast_moving')" data-filter="fast_moving">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Fast Moving</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #34d399 0%, #059669 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($fastMovingCount); ?></span>
            </div>
            <div class="stat-card-sub mt-1 font-onest" style="text-transform: none !important; letter-spacing: normal !important;">High sales velocity</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition-transform shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-6 flex items-center justify-between group hover:border-red-500/50 cursor-pointer transition-all" onclick="filterData('expiring')" data-filter="expiring">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Expiring Soon</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($expiringSoonCount); ?></span>
            </div>
            <div class="stat-card-sub mt-1 font-onest" style="text-transform: none !important; letter-spacing: normal !important;"><?php echo $expiringSoonCount === 0 ? 'No batches near expiry' : ($expiringSoonCount === 1 ? '1 batch expiring in 30d' : number_format($expiringSoonCount) . ' batches expiring in 30d'); ?></div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>
</div>

<div class="tab-container">
    <div class="tab active" data-tab="product-analysis">Product<br class="tab-br"> Analysis</div>
    <div class="tab" data-tab="category-performance">Category<br class="tab-br"> Performance</div>
    <div class="tab" data-tab="sales-trends">Sales<br class="tab-br"> Trends</div>
    <div class="tab" data-tab="supplier-analysis">Supplier<br class="tab-br"> Analysis</div>
    <div class="tab" data-tab="expiry-risk">Expiry<br class="tab-br"> Alerts</div>
</div>

<div class="tab-content active" id="product-analysis">
    <div class="card">
        <div class="card-section-title" style="margin-bottom: 16px;">Product Movement Analysis</div>
        <div class="search-filter-container">
            <div class="search-input-group flex items-center gap-2 flex-1 w-full min-w-0">
                <input type="text" id="productSearch" oninput="currentProductPage = 1; filterProductTable();" onkeydown="if(event.key==='Enter'){event.preventDefault(); currentProductPage = 1; filterProductTable();}" class="search-box flex-1 min-w-0" style="height: 42px; width: 100%;" placeholder="Search products...">
                <button type="button" onclick="currentProductPage = 1; filterProductTable();" class="btn-secondary h-[42px] w-[42px] min-w-[42px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Search Products" aria-label="Search">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
                <button type="button" onclick="resetProductFilters();" class="btn-secondary h-[42px] w-[42px] min-w-[42px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Reset Filters" aria-label="Reset Filters">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <select id="categoryFilter" onchange="currentProductPage = 1; filterProductTable();" class="filter-select">
                <option value="">All Categories</option>
                <?php
                $categories = array_filter(array_unique(array_column($productMovement, 'category_name')));
                sort($categories);
                foreach ($categories as $category) {
                    $catEsc = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
                    echo "<option value=\"$catEsc\">$catEsc</option>";
                }
                ?>
            </select>
            <select id="movementFilter" onchange="currentProductPage = 1; filterProductTable();" class="filter-select">
                <option value="">All Movement Types</option>
                <option value="fast_moving">Fast Moving</option>
                <option value="medium_moving">Medium Moving</option>
                <option value="slow_moving">Slow Moving</option>
                <option value="non_moving">Non Moving</option>
            </select>
            <select id="stockFilter" onchange="currentProductPage = 1; filterProductTable();" class="filter-select">
                <option value="">All Stock Status</option>
                <option value="attention_needed">Low &amp; Out of Stock</option>
                <option value="normal">Normal</option>
                <option value="low_stock">Low Stock</option>
                <option value="out_of_stock">Out of Stock</option>
                <option value="overstocked">Overstocked</option>
            </select>
        </div>
        <div id="productTableWrap" class="iw-table-scroll" style="overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; width: 100% !important; max-width: 100% !important; display: block !important;">
            <table class="table" id="productTable" style="min-width: 760px; width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Product</th><th>Category</th><th>Current Stock</th><th>Monthly Sales</th>
                        <th>Daily Velocity</th><th>Days Supply</th><th>Movement</th><th>Status</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php foreach ($productMovement as $index => $product): ?>
                    <tr data-category="<?php echo htmlspecialchars($product['category_name']); ?>"
                        data-movement="<?php echo $product['movement_class']; ?>"
                        data-stock="<?php echo $product['stock_status']; ?>"
                        class="product-row <?php echo $index >= 8 ? 'hidden' : ''; ?>"
                        <?php echo $index >= 8 ? 'style="display: none !important;"' : ''; ?>>
                        <td style="font-weight: 500; color: #fff;"><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td style="color: #9aa6d1;"><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td><?php echo $product['current_stock']; ?></td>
                        <td><?php echo $product['monthly_sales'] ?? 0; ?></td>
                        <td><?php echo number_format($product['daily_sales_velocity'], 2); ?></td>
                        <td><?php echo $product['days_of_supply']; ?></td>
                        <td><span class="badge <?php echo $product['movement_class']; ?>"><?php echo ucfirst(str_replace('_', ' ', $product['movement_class'])); ?></span></td>
                        <td><span class="badge <?php echo $product['stock_status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $product['stock_status'])); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="productPagination" class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(42, 68, 132, 0.3); flex-wrap: wrap; gap: 12px;">
            <div class="pagination-info-pill">
                Showing <span id="paginationInfoStart"><?php echo !empty($productMovement) ? '1' : '0'; ?></span> to <span id="paginationInfoEnd"><?php echo min(8, count($productMovement)); ?></span> of <span id="paginationInfoTotal"><?php echo count($productMovement); ?></span> entries
            </div>
            <div class="pagination-btn-group flex items-center gap-1 sm:gap-1.5">
                <button type="button" class="pagination-btn pagination-nav" id="prevPageBtn" aria-label="Previous page" title="Previous page">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    <span class="pagination-nav-text">Previous</span>
                </button>
                <div id="productPageNumbers" class="flex items-center gap-1 sm:gap-1.5"></div>
                <button type="button" class="pagination-btn pagination-nav" id="nextPageBtn" aria-label="Next page" title="Next page">
                    <span class="pagination-nav-text">Next</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="tab-content" id="category-performance">
    <div class="grid-2">
        <div class="card">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Category Performance</div>
            <div style="overflow: auto; max-height: 500px;">
                <table class="table" id="categoryTable">
                    <thead><tr><th>Category</th><th class="text-right">Products</th><th class="text-right">Total Stock</th><th class="text-right">Avg Stock</th><th class="text-center">Low Stock</th><th class="text-right">Sales</th></tr></thead>
                    <tbody id="categoryTableBody">
                        <?php foreach ($categoryPerformance as $category): ?>
                        <tr>
                            <td style="color: #fff; font-weight: 500;"><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td class="text-right"><?php echo $category['product_count']; ?></td>
                            <td class="text-right"><?php echo $category['total_stock']; ?></td>
                            <td class="text-right"><?php echo number_format($category['avg_stock'], 1); ?></td>
                            <td class="text-center"><span class="badge <?php echo $category['low_stock_count'] > 0 ? 'medium' : 'low'; ?>"><?php echo $category['low_stock_count']; ?></span></td>
                            <td class="text-right"><?php echo $category['monthly_sales'] ?? 0; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="categoryPagination" class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 10px;"></div>
            </div>
        </div>
        <div class="card">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Sales Distribution</div>
            <div class="chart-container"><canvas id="categoryChart"></canvas></div>
        </div>
    </div>
</div>

<div class="tab-content" id="sales-trends">
    <div class="grid-2">
        <div class="card">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">
                Sales Trends (Last <?php echo $daysLookback; ?> Days)
            </div>
            <div style="overflow: auto; max-height: 500px;">
                <table class="table" id="trendsTable">
                    <thead><tr><th>Date</th><th>Day</th><th class="text-right">Products Sold</th><th class="text-right">Total Qty</th><th class="text-right">Avg/Trans</th></tr></thead>
                    <tbody id="trendsTableBody">
                        <?php foreach ($salesTrends as $trend): ?>
                        <tr>
                            <td style="color: #fff;"><?php echo $trend['sale_date']; ?></td>
                            <td style="color: #9aa6d1;"><?php echo $trend['day_name']; ?></td>
                            <td class="text-right"><?php echo $trend['unique_products_sold']; ?></td>
                            <td class="text-right"><?php echo $trend['total_quantity_sold']; ?></td>
                            <td class="text-right"><?php echo number_format($trend['avg_quantity_per_transaction'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="trendsPagination" class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 10px;"></div>
            </div>
        </div>
        <div class="card">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Trend Visualization</div>
            <div class="chart-container"><canvas id="salesTrendChart"></canvas></div>
        </div>
    </div>
</div>

<div class="tab-content" id="supplier-analysis">
    <div class="card">
        <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Supplier Performance</div>
        <div style="overflow: auto; max-height: 500px;">
            <table class="table" id="supplierTable">
                <thead><tr><th>Supplier</th><th class="text-right">Products</th><th class="text-right">Total Stock</th><th class="text-right">Avg Stock</th><th class="text-right">Monthly Sales</th><th class="text-center">Low Stock</th><th class="text-center">Status</th></tr></thead>
                <tbody id="supplierTableBody">
                    <?php foreach ($supplierPerformance as $supplier): ?>
                    <tr>
                        <td style="color:#fff; font-weight:500;"><?php echo htmlspecialchars($supplier['supplier']); ?></td>
                        <td class="text-right"><?php echo $supplier['product_count']; ?></td>
                        <td class="text-right"><?php echo $supplier['total_stock_value']; ?></td>
                        <td class="text-right"><?php echo number_format($supplier['avg_stock_per_product'], 1); ?></td>
                        <td class="text-right"><?php echo $supplier['monthly_sales'] ?? 0; ?></td>
                        <td class="text-center"><span class="badge <?php echo $supplier['low_stock_count'] > 0 ? 'medium' : 'low'; ?>"><?php echo $supplier['low_stock_count']; ?></span></td>
                        <td class="text-center">
                            <?php
                            $performance = 'Good'; $badgeClass = 'low';
                            if ($supplier['low_stock_count'] > $supplier['product_count'] * 0.3) { $performance = 'Needs Attention'; $badgeClass = 'medium'; }
                            if ($supplier['monthly_sales'] === 0 || $supplier['monthly_sales'] === null) { $performance = 'Poor'; $badgeClass = 'high'; }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $performance; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="supplierPagination" class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 10px;"></div>
        </div>
    </div>
</div>

<div class="tab-content" id="expiry-risk">
    <div class="card">
        <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Expiry Risk Analysis</div>
        <div style="overflow: auto; max-height: 500px;">
            <table class="table" id="expiryTable">
                <thead><tr><th>Product</th><th>Category</th><th class="text-right">Stock</th><th class="text-right">Expiry Date</th><th class="text-right">Days Left</th><th class="text-right">Sales</th><th class="text-right">Sell Through</th><th class="text-center">Risk</th></tr></thead>
                <tbody id="expiryTableBody">
                    <?php foreach ($expiryRiskAnalysis as $item): ?>
                    <tr>
                        <td style="color:#fff; font-weight:500;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td style="color:#9aa6d1;"><?php echo htmlspecialchars($item['category_name']); ?></td>
                        <td class="text-right"><?php echo $item['quantity']; ?></td>
                        <td class="text-right"><?php echo $item['expiry_date']; ?></td>
                        <td class="text-right" style="font-weight:700; color: <?php echo ($item['days_until_expiry'] < 30 ? '#f87171' : '#E3E3E3'); ?>;"><?php echo $item['days_until_expiry']; ?></td>
                        <td class="text-right"><?php echo $item['monthly_sales'] ?? 0; ?></td>
                        <td class="text-right"><?php echo $item['sell_through_days']; ?></td>
                        <td class="text-center"><span class="badge <?php echo $item['expiry_risk']; ?>"><?php echo ucfirst($item['expiry_risk']); ?> Risk</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="expiryPagination" class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 10px;"></div>
        </div>
    </div>
</div>

<div class="forecast-card" id="forecastWidget">
    <div class="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-overlay-text">Recalculating forecast with live warehouse inventory...</div>
    </div>
    
    <div class="card-section-title forecast-card-main-title" style="margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg></span>
            <span>Demand Forecasting &amp; Smart Restock</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <!-- PH Seasonal Guide button replaced by floating FAB (see #phSeasonalFab) -->
        </div>
    </div>

    <!-- Gemini AI Real-Time Philippine Market Pulse & Consumer Demand Intelligence -->
    <?php require __DIR__ . '/src/partials/gemini_market_pulse_card.php'; ?>

    <div class="smart-restock-banner">
        <div class="bg-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg></div>
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; position: relative; z-index: 2; flex-wrap: wrap; gap: 14px;">
            <div>
                <div class="smart-restock-heading-text" style="color: #51D55A; font-weight: 600; display: flex; align-items: center; gap: 7px;">
                    <span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg></span>
                    <span>30-Day Inventory Replenishment &amp; Demand Forecast</span>
                </div>
                <div class="smart-restock-subtext" style="color: var(--insights-text-muted); font-size: 13px; margin-top: 5px;">
                    Prescriptive restocking recommendations based on historical sales velocity, depletion rate, and stock safety buffers.
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                <div class="forecast-sync-pill" id="forecastSyncStatusPill" title="Live status of warehouse stock reconciliation">
                    <span class="sync-dot"></span>
                    <span id="forecastSyncStatusText">Live Stock Active</span>
                </div>

                <button type="button" id="btnRefreshSmartRestock" class="refresh-forecast-btn" onclick="refreshSmartRestockForecast(event)" title="Reconcile and recalculate forecast using live warehouse inventory">
                    <span class="refresh-icon-holder">
                        <svg class="refresh-default-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                            <path d="M21 3v5h-5"/>
                        </svg>
                        <svg class="refresh-spinner-svg" width="14" height="14" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.8" opacity="0.25"/>
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.8" stroke-dasharray="38" stroke-dashoffset="14" stroke-linecap="round"/>
                        </svg>
                        <svg class="refresh-check-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    <span class="refresh-text" id="refreshBtnLabel">Refresh Forecast</span>
                </button>
            </div>
        </div>

        <div style="overflow-x: auto; position: relative; z-index: 2;">
            <table class="forecast-table">
                <thead>
                    <tr>
                        <th width="28%">Top In-Demand Product / Category</th>
                        <th width="58%">Seasonal Demand &amp; Stock Intelligence</th>
                        <th width="14%">Action</th>
                    </tr>
                </thead>
                <tbody id="forecastTableBody">
                    <?php foreach ($forecastData as $item): ?>
                    <tr class="<?php echo !empty($item['highlight']) ? 'forecast-row--highlighted' : ''; ?>"<?php echo !empty($item['highlight']) ? ' id="highlighted-product-row"' : ''; ?>>
                        <td>
                            <div class="product-title"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="product-sub"><?php echo htmlspecialchars($item['category_name']); ?></div>
                            <div style="display: flex; gap: 6px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
                                <?php 
                                    $badgeColor = '#9aa6d1'; 
                                    $badgeBg = 'rgba(154, 166, 209, 0.15)';
                                    $typeStr = $item['type'] ?? $item['status'] ?? '';
                                    if(stripos($typeStr, 'Critical') !== false || stripos($typeStr, 'Out of Stock') !== false) {
                                        $badgeColor = '#ef4444';
                                        $badgeBg = 'rgba(239, 68, 68, 0.15)';
                                    } elseif(stripos($typeStr, 'Risk') !== false || stripos($typeStr, 'Low') !== false) {
                                        $badgeColor = '#f59e0b';
                                        $badgeBg = 'rgba(245, 158, 11, 0.15)';
                                    } elseif(stripos($typeStr, 'Well Stocked') !== false || stripos($typeStr, 'Optimal') !== false) {
                                        $badgeColor = '#10b981';
                                        $badgeBg = 'rgba(16, 185, 129, 0.15)';
                                    } elseif(stripos($typeStr, 'Opportunity') !== false) {
                                        $badgeColor = '#3b82f6';
                                        $badgeBg = 'rgba(59, 130, 246, 0.15)';
                                    } elseif(stripos($typeStr, 'Moderate') !== false || stripos($typeStr, 'Cover') !== false) {
                                        $badgeColor = '#eab308';
                                        $badgeBg = 'rgba(234, 179, 8, 0.15)';
                                    }
                                ?>
                                <span class="forecast-status-badge" style="background-color: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeColor; ?>40;">
                                    <?php echo htmlspecialchars($typeStr); ?>
                                </span>
                                <?php if (isset($item['current_stock']) && ($item['matched_in_stock'] ?? true)): ?>
                                <span class="forecast-stock-pill">
                                    Stock: <strong><?php echo number_format((int)$item['current_stock']); ?></strong>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($item['demand_lift'])): ?>
                                <span class="forecast-stock-pill" style="border-color: rgba(81, 213, 90, 0.4); color: #51D55A;">
                                    <strong><?php echo htmlspecialchars($item['demand_lift']); ?> Demand</strong>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($item['descriptive']) && !empty($item['predictive']) && !empty($item['prescriptive'])): ?>
                                <?php
                                    $actionClass = 'action-optimal';
                                    $actionBadgeClass = 'insight-badge--action';
                                    if (isset($item['action_needed'])) {
                                        if ($item['action_needed'] === 'urgent') {
                                            $actionClass = 'action-urgent';
                                            $actionBadgeClass = 'insight-badge--urgent';
                                        } elseif ($item['action_needed'] === 'pause') {
                                            $actionClass = 'action-pause';
                                            $actionBadgeClass = 'insight-badge--pause';
                                        } elseif ($item['action_needed'] === 'restock' || $item['action_needed'] === 'monitor') {
                                            $actionClass = 'action-monitor';
                                            $actionBadgeClass = 'insight-badge--monitor';
                                        }
                                    }
                                ?>
                                <div class="insight-hierarchy">
                                    <div class="insight-row insight-row--situation">
                                        <span class="insight-badge insight-badge--situation">Context</span>
                                        <div class="insight-desc"><?php echo $item['descriptive']; ?></div>
                                    </div>
                                    <div class="insight-row insight-row--forecast">
                                        <span class="insight-badge insight-badge--forecast">Forecast</span>
                                        <div class="insight-desc"><?php echo $item['predictive']; ?></div>
                                    </div>
                                    <div class="insight-row insight-row--action <?php echo $actionClass; ?>">
                                        <span class="insight-badge <?php echo $actionBadgeClass; ?>">Action</span>
                                        <div class="insight-desc insight-desc--action"><?php echo $item['prescriptive']; ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="insight-text"><?php echo $item['insight']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="action-btn check-stock-btn"
                                data-product="<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-category="<?php echo htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                <?php if (!empty($item['product_id'])): ?>data-id="<?php echo (int)$item['product_id']; ?>"<?php endif; ?>>
                                Check Stock
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="forecastPagination" class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 10px;"></div>
        </div>

    </div>
</div>

<div class="grid-2" style="margin-bottom: 20px;">
    <div class="card forecast-highlight-card">
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px;"><span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/></svg></span><?php echo $daysLookback; ?>-Day Sales Forecast</div>
            <div class="info-icon" data-tooltip="sales-forecast-tooltip">?</div>
        </div>
        <div class="chart-container"><canvas id="salesForecastChart"></canvas></div>
    </div>
    <div class="card">
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px;"><span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg></span>Product Demand Heatmap</div>
            <div class="info-icon" data-tooltip="heatmap-tooltip">?</div>
        </div>
        <div class="chart-container"><canvas id="demandHeatmapChart"></canvas></div>
    </div>
</div>

<div class="grid-forecast-ml" style="margin-bottom: 20px;">
    <div class="card">
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px;"><span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg></span>Seasonal Demand Patterns</div>
            <div class="info-icon" data-tooltip="seasonal-tooltip">?</div>
        </div>
        <?php if (!$hasMlModel): ?>
        <div id="seasonalNoModel" class="chart-container-lg" style="display: flex; align-items: center; justify-content: center; text-align: center; padding: 16px;">
            <p style="color: #9aa6d1; font-size: 13px; margin: 0;">No trained forecasting model available.<br>Please train the model from <a href="settings.php" style="color: #51D55A;">Settings</a>.</p>
        </div>
        <?php endif; ?>
        <div class="chart-container-lg"<?php echo !$hasMlModel ? ' style="display:none;"' : ''; ?>><canvas id="seasonalChart"></canvas></div>
        <?php if ($hasMlModel && !empty($seasonalHighlights)): ?>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px;">
            <?php foreach ($seasonalHighlights as $highlight): ?>
            <span style="padding:6px 10px; border-radius:999px; font-size:11px; background: rgba(81,213,90,0.12); color: #51D55A; border: 1px solid rgba(81,213,90,0.25);">
                <?php echo htmlspecialchars($highlight['month']); ?> ×<?php echo number_format($highlight['index'], 2); ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div style="display: flex; align-items: center; margin-bottom: 8px;">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px;"><span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg></span>Forecast Accuracy</div>
            <div class="info-icon" data-tooltip="accuracy-tooltip">?</div>
        </div>
        <?php if ($hasMlModel && $accuracyTrend['current'] !== null): ?>
        <div style="display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 80px; padding: 8px 10px; background: rgba(81,213,90,0.1); border-radius: 6px; text-align: center;">
                <div style="font-size: 18px; font-weight: 700; color: #51D55A;"><?php echo number_format($accuracyTrend['current'], 1); ?>%</div>
                <div style="font-size: 10px; color: #9aa6d1;">Accuracy</div>
            </div>
            <div style="flex: 1; min-width: 80px; padding: 8px 10px; background: rgba(239,68,68,0.1); border-radius: 6px; text-align: center;">
                <div style="font-size: 18px; font-weight: 700; color: #ef4444;"><?php echo number_format($accuracyTrend['error_rate'], 1); ?>%</div>
                <div style="font-size: 10px; color: #9aa6d1;">Error Rate</div>
            </div>
            <?php if ($accuracyTrend['change'] !== null): ?>
            <div style="flex: 1; min-width: 80px; padding: 8px 10px; background: rgba(59,130,246,0.1); border-radius: 6px; text-align: center;">
                <div style="font-size: 18px; font-weight: 700; color: <?php echo $accuracyTrend['direction'] === 'improved' ? '#51D55A' : ($accuracyTrend['direction'] === 'declined' ? '#ef4444' : '#9aa6d1'); ?>;">
                    <?php echo ($accuracyTrend['change'] >= 0 ? '+' : '') . number_format($accuracyTrend['change'], 1); ?>%
                </div>
                <div style="font-size: 10px; color: #9aa6d1;">vs Previous</div>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif (!$hasMlModel): ?>
        <div id="accuracyNoModel" class="chart-container-lg" style="display: flex; align-items: center; justify-content: center; text-align: center; padding: 16px;">
            <p style="color: #9aa6d1; font-size: 13px; margin: 0;">No trained forecasting model available.<br>Please train the model from <a href="settings.php" style="color: #51D55A;">Settings</a>.</p>
        </div>
        <?php endif; ?>
        <div class="chart-container-xl"<?php echo !$hasMlModel ? ' style="display:none;"' : ''; ?>><canvas id="accuracyChart"></canvas></div>
    </div>
</div>

<div class="grid-2" style="margin-bottom: 20px;">
    <div class="card">
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px;"><span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.318 11.318 0 0 1 5.814 5.814L21.75 18M2.25 18V15a2.25 2.25 0 0 1 2.25-2.25h1.5M2.25 18h16.5"/></svg></span>Stock Depletion Trend</div>
            <div class="info-icon" data-tooltip="depletion-tooltip">?</div>
        </div>
        <?php if (!$depletionTrend['available']): ?>
        <div class="chart-container" style="display:flex; align-items:center; justify-content:center; text-align:center; padding:16px;">
            <p style="color: #9aa6d1; font-size: 13px; margin: 0;">No stock movement history yet. Add stock in/out transactions to view depletion trends.</p>
        </div>
        <?php else: ?>
        <div class="chart-container"><canvas id="depletionTrendChart"></canvas></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px;"><span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3 3-3"/></svg></span>Reorder Frequency Over Time</div>
            <div class="info-icon" data-tooltip="reorder-tooltip">?</div>
        </div>
        <?php if (!$reorderFrequencyTrend['available']): ?>
        <div class="chart-container" style="display:flex; align-items:center; justify-content:center; text-align:center; padding:16px;">
            <p style="color: #9aa6d1; font-size: 13px; margin: 0;">No restock history yet. Reorder events will appear once stock is replenished.</p>
        </div>
        <?php else: ?>
        <div class="chart-container"><canvas id="reorderFrequencyChart"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<div class="info-tooltip" id="sales-forecast-tooltip"><strong><?php echo $daysLookback; ?>-Day Sales Forecast</strong><br><br>• <strong>Green Line:</strong> Historical daily sales from transactions<br>• <strong>Orange Dashed Line:</strong> ML-predicted future demand<br>• Powered by Prophet or Random Forest model<br>• Train the model from Settings to enable predictions</div>
<div class="info-tooltip" id="heatmap-tooltip"><strong>Product Demand Heatmap</strong><br><br>• Shows top products by forecasted demand<br>• <span style="color: #ef4444">High</span> = 50+ units (Priority restock)<br>• <span style="color: #f59e0b">Medium-High</span> = 25-50 units<br>• <span style="color: #3b82f6">Medium</span> = 10-25 units<br>• <span style="color: #10b981">Low</span> = Under 10 units</div>
<div class="info-tooltip" id="seasonal-tooltip"><strong>Seasonal Demand Patterns</strong><br><br>• <strong>Green:</strong> Historical monthly sales from transactions<br>• <strong>Orange:</strong> ML forecasted demand for upcoming months<br>• <strong>Purple dashed:</strong> Trend line projection<br>• Identifies monthly peaks, declines, and seasonality</div>
<div class="info-tooltip" id="accuracy-tooltip"><strong>Forecast Accuracy</strong><br><br>• Calculated from backtest: predicted vs actual sales<br>• <strong>MAE:</strong> Mean Absolute Error (units)<br>• <strong>MAPE:</strong> Mean Absolute Percentage Error<br>• <strong>Accuracy %</strong> = 100 − MAPE<br>• Segment chart shows accuracy by product movement class</div>
<div class="info-tooltip" id="depletion-tooltip"><strong>Stock Depletion Trend</strong><br><br>• Compares monthly stock-outs against replenishment activity<br>• Helps identify where inventory is draining faster than it is restocked</div>
<div class="info-tooltip" id="reorder-tooltip"><strong>Reorder Frequency Over Time</strong><br><br>• Tracks how often stock is replenished each month<br>• Helps spot recurring restock cycles and supplier cadence</div>

<div class="card" style="margin-bottom: 0;">
    <div style="color: #E3E3E3; font-weight: 600; font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;"><span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 12H18M7.757 14.743l-1.59 1.59M6 12H4.5m4.243-5.657L6.75 4.5M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg></span>Forecast Recommendations</div>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
        <div class="recommendation-card success">
            <div class="card-label">High Priority Restock</div>
            <div class="card-copy">
                <?php
                $urgentOos = count(array_filter($productMovement, function($p) { return ($p['stock_status'] ?? '') === 'out_of_stock'; }));
                $urgentLow = count(array_filter($productMovement, function($p) { return ($p['stock_status'] ?? '') === 'low_stock'; }));
                $totalUrgent = $urgentOos + $urgentLow;
                if ($totalUrgent === 0) {
                    echo 'All catalog items are currently well-stocked above minimum thresholds.';
                } else {
                    echo "<strong>{$totalUrgent} products</strong> require replenishment (" .
                         ($urgentOos > 0 ? "<span style='color:#ef4444;'>{$urgentOos} out of stock</span>, " : "") .
                         "{$urgentLow} at/below reorder point).";
                }
                ?>
            </div>
        </div>
        <div class="recommendation-card warning" onclick="openPhilippineSeasonalModal()" style="cursor: pointer; position: relative; transition: all 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-label">Seasonal Stock Build-up</div>
                <span class="seasonal-guide-action-badge" title="Open Philippine Seasonal Guide" style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 6px; border-radius: 999px; background: rgba(245, 158, 11, 0.16); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.35); flex-shrink: 0;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                        <circle cx="12" cy="12" r="3.8" fill="rgba(245, 158, 11, 0.25)" stroke="currentColor" stroke-width="1.6"/>
                        <line x1="12" y1="2.2"  x2="12" y2="5"    stroke="currentColor" stroke-width="1.8"/>
                        <line x1="12" y1="19"   x2="12" y2="21.8" stroke="currentColor" stroke-width="1.8"/>
                        <line x1="2.2" y1="12" x2="5"   y2="12"   stroke="currentColor" stroke-width="1.8"/>
                        <line x1="19"  y1="12" x2="21.8" y2="12"  stroke="currentColor" stroke-width="1.8"/>
                        <line x1="5.1"  y1="5.1"  x2="7"    y2="7"    stroke="currentColor" stroke-width="1.5"/>
                        <line x1="17"   y1="17"   x2="18.9" y2="18.9" stroke="currentColor" stroke-width="1.5"/>
                        <line x1="5.1"  y1="18.9" x2="7"    y2="17"   stroke="currentColor" stroke-width="1.5"/>
                        <line x1="17"   y1="7"    x2="18.9" y2="5.1"  stroke="currentColor" stroke-width="1.5"/>
                        <polyline points="8.2,14.5 10.2,11.8 12,13.2 14.5,9.8" stroke="currentColor" stroke-width="1.7" fill="none"/>
                    </svg>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                        <path d="M5 12h14m-6-6 6 6-6 6"/>
                    </svg>
                </span>
            </div>
            <div class="card-copy">
                <?php
                $mCount = count($allSeasonalMonthsData[$selectedMonth]['items'] ?? []);
                $mName = $activeMonthProfile['name'] ?? date('F');
                $mTag = $activeMonthProfile['tagline'] ?? 'Seasonal Demand Shift';
                echo "<strong>{$mName} Peak:</strong> {$mCount} warehouse items matched for <em>{$mTag}</em>. Click to explore Philippine seasonal recommendations.";
                ?>
            </div>
        </div>
        <div class="recommendation-card info">
            <div class="card-label">Optimize Inventory</div>
            <div class="card-copy">
                <?php
                $overstocked = array_values(array_filter($productMovement, function($p) { return ($p['stock_status'] ?? '') === 'overstocked'; }));
                $overstockedCount = count($overstocked);
                if ($overstockedCount === 0) {
                    echo 'Inventory turnover is healthy. No severely overstocked products detected.';
                } else {
                    $topOverName = htmlspecialchars($overstocked[0]['product_name'] ?? '');
                    echo "<strong>{$overstockedCount} overstocked items</strong> identified (>90 days supply). Reallocate working capital from surplus stock (e.g. {$topOverName}).";
                }
                ?>
            </div>
        </div>
        <div class="recommendation-card purple">
            <div class="card-label">Promotional Opportunities</div>
            <div class="card-copy">
                <?php
                $slowMoving = array_values(array_filter($productMovement, function($p) { return ($p['movement_class'] ?? '') === 'slow_moving' && ($p['current_stock'] ?? 0) > 20; }));
                $slowCount = count($slowMoving);
                if ($slowCount === 0) {
                    echo 'All stock lines are maintaining steady or active sales velocity.';
                } else {
                    $sampleSlow = htmlspecialchars($slowMoving[0]['product_name'] ?? '');
                    echo "<strong>{$slowCount} slow-moving products</strong> with surplus units (e.g. {$sampleSlow}). Bundle with fast movers or run weekend discounts.";
                }
                ?>
            </div>
        </div>
    </div>
</div>


<script src="assets/vendor/chart.umd.min.js"></script>
<script>
// --- Pagination Logic ---
const itemsPerPage = 10;
function renderPagination(tableBodyId, paginationId, pageVar, setPageFunc, customPerPage) {
    const tableBody = document.getElementById(tableBodyId);
    if (!tableBody) return;
    
    const allRows = Array.from(tableBody.getElementsByTagName('tr'));
    if (allRows.length === 0) return;
    
    const perPage = customPerPage || itemsPerPage;
    const totalPages = Math.ceil(allRows.length / perPage) || 1;
    if (pageVar > totalPages) pageVar = totalPages;
    if (pageVar < 1) pageVar = 1;
    
    const start = (pageVar - 1) * perPage;
    const end = start + perPage;
    
    allRows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
    
    const pagContainer = document.getElementById(paginationId);
    if (pagContainer) {
        const isSingleEmptyRow = (allRows.length === 1 && allRows[0].querySelector('td[colspan]'));
        if (allRows.length === 0 || isSingleEmptyRow) {
            pagContainer.innerHTML = '';
            pagContainer.style.display = 'none';
            return;
        }
        pagContainer.style.display = 'flex';
        pagContainer.style.alignItems = 'center';
        pagContainer.style.justifyContent = 'space-between';
        pagContainer.style.flexWrap = 'wrap';
        pagContainer.style.gap = '12px';
        
        let pageItems = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pageItems.push(i);
        } else if (pageVar <= 3) {
            pageItems = [1, 2, 3, 4, 5, '...', totalPages];
        } else if (pageVar >= totalPages - 2) {
            pageItems = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
            pageItems = [1, '...', pageVar - 1, pageVar, pageVar + 1, '...', totalPages];
        }

        let pageNumbersHtml = '';
        pageItems.forEach(item => {
            if (item === '...') {
                pageNumbersHtml += `<span class="pagination-ellipsis">&hellip;</span>`;
            } else if (item === pageVar) {
                pageNumbersHtml += `<button type="button" class="pagination-btn pagination-num active" disabled>${item}</button>`;
            } else {
                let isNeighbor = false;
                if (totalPages > 7) {
                    if (pageVar <= 3) {
                        isNeighbor = (item === 4 || item === 5);
                    } else if (pageVar >= totalPages - 2) {
                        isNeighbor = (item === totalPages - 4 || item === totalPages - 3);
                    } else {
                        isNeighbor = (item === pageVar - 1 || item === pageVar + 1);
                    }
                }
                const neighborClass = isNeighbor ? ' pagination-neighbor' : '';
                pageNumbersHtml += `<button type="button" class="pagination-btn pagination-num${neighborClass}" onclick="event.preventDefault(); ${setPageFunc}(${item}); return false;">${item}</button>`;
            }
        });

        pagContainer.innerHTML = `
            <div class="pagination-info-pill">Showing ${allRows.length === 0 ? 0 : start + 1} to ${Math.min(end, allRows.length)} of ${allRows.length} entries</div>
            <div class="pagination-btn-group flex items-center gap-1 sm:gap-1.5">
                <button type="button" class="pagination-btn pagination-nav" ${pageVar === 1 ? 'disabled' : ''} onclick="event.preventDefault(); ${setPageFunc}(${pageVar - 1}); return false;" aria-label="Previous page" title="Previous page">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    <span class="pagination-nav-text">Previous</span>
                </button>
                ${pageNumbersHtml}
                <button type="button" class="pagination-btn pagination-nav" ${pageVar === totalPages ? 'disabled' : ''} onclick="event.preventDefault(); ${setPageFunc}(${pageVar + 1}); return false;" aria-label="Next page" title="Next page">
                    <span class="pagination-nav-text">Next</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>
        `;
    }
}

let categoryPage = 1, trendsPage = 1, supplierPage = 1, expiryPage = 1, forecastPage = 1, seasonalModalPage = 1;
window.setCategoryPage = (p) => { categoryPage = p; renderPagination('categoryTableBody', 'categoryPagination', categoryPage, 'setCategoryPage'); };
window.setTrendsPage = (p) => { trendsPage = p; renderPagination('trendsTableBody', 'trendsPagination', trendsPage, 'setTrendsPage'); };
window.setSupplierPage = (p) => { supplierPage = p; renderPagination('supplierTableBody', 'supplierPagination', supplierPage, 'setSupplierPage'); };
window.setExpiryPage = (p) => { expiryPage = p; renderPagination('expiryTableBody', 'expiryPagination', expiryPage, 'setExpiryPage'); };
window.setForecastPage = (p) => { forecastPage = p; renderPagination('forecastTableBody', 'forecastPagination', forecastPage, 'setForecastPage', 5); };

let currentSeasonalModalItems = [];
let currentSeasonalPeriodName = '';

function formatSafeSeasonalHtml(str) {
    if (!str) return '';
    let normalized = String(str)
        .replace(/&lt;strong&gt;/gi, '<strong>')
        .replace(/&lt;\/strong&gt;/gi, '</strong>')
        .replace(/&lt;b&gt;/gi, '<b>')
        .replace(/&lt;\/b&gt;/gi, '</b>');
    return escapeHtml(normalized)
        .replace(/&lt;strong&gt;/gi, '<strong>')
        .replace(/&lt;\/strong&gt;/gi, '</strong>')
        .replace(/&lt;b&gt;/gi, '<b>')
        .replace(/&lt;\/b&gt;/gi, '</b>');
}

function renderSeasonalModalRow(item, periodDisplayName) {
    const typeStr = item.type || item.status || '';
    const statusType = (item.status_type || '').toLowerCase();
    const actionNeeded = (item.action_needed || '').toLowerCase();

    let statusPillHtml = '';
    if (statusType === 'critical' || statusType === 'high_risk' || typeStr.includes('Critical') || typeStr.includes('Out of Stock')) {
        statusPillHtml = `<span class="table-status-pill status-critical">${escapeHtml(typeStr || 'Critical Understock')}</span>`;
    } else if (statusType === 'moderate' || actionNeeded === 'restock' || typeStr.includes('Risk') || typeStr.includes('Low')) {
        statusPillHtml = `<span class="table-status-pill status-warning">${escapeHtml(typeStr || 'Reorder Needed')}</span>`;
    } else if (statusType === 'opportunity' || typeStr.includes('Opportunity')) {
        statusPillHtml = `<span class="table-status-pill status-opportunity">${escapeHtml(typeStr || 'Market Opportunity')}</span>`;
    }

    let surgeHtml = '';
    if (item.demand_lift) {
        surgeHtml = `<span class="meta-sep">&middot;</span><span class="surge-tag">${escapeHtml(item.demand_lift)} Surge</span>`;
    }

    const desc = (item.descriptive || item.reason || '').trim();
    const pred = (item.predictive || '').trim();
    let intelSentence = '';
    if (desc && pred) {
        const cleanDesc = desc.endsWith('.') ? desc : desc + '.';
        intelSentence = `${cleanDesc} ${pred}`;
    } else if (pred) {
        intelSentence = pred;
    } else if (desc) {
        intelSentence = desc;
    } else {
        const units = Number(item.projected_demand || 0).toLocaleString();
        intelSentence = `Anticipated seasonal demand surge during ${periodDisplayName} with projected volume of ~${units} units.`;
    }

    const actionText = (item.prescriptive || item.action_text || '').trim();
    let actionCalloutHtml = '';
    if (actionText) {
        if (actionNeeded === 'urgent' || statusType === 'critical' || statusType === 'high_risk') {
            actionCalloutHtml = `
                <div class="table-action-callout callout-urgent">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span>${formatSafeSeasonalHtml(actionText)}</span>
                </div>
            `;
        } else if (actionNeeded === 'restock' || actionNeeded === 'monitor' || statusType === 'moderate') {
            actionCalloutHtml = `
                <div class="table-action-callout callout-warning">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span>${formatSafeSeasonalHtml(actionText)}</span>
                </div>
            `;
        } else if (statusType === 'opportunity' || actionNeeded === 'opportunity') {
            actionCalloutHtml = `
                <div class="table-action-callout callout-opportunity">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:1px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    <span>${formatSafeSeasonalHtml(actionText)}</span>
                </div>
            `;
        }
    }

    const hasStock = (item.current_stock !== undefined && item.current_stock !== null && (item.matched_in_stock ?? true));
    const stockDisplay = hasStock 
        ? `${Number(item.current_stock).toLocaleString()} <span class="table-stock-label">units</span>`
        : `<span class="table-stock-label">Not in catalog</span>`;

    const imgUrl = item.image_url || (item.product_id ? `uploads/products/${item.product_id}.png` : ((item.product_name && item.product_name.includes('Graham')) ? 'uploads/products/graham_gelatin.png' : ''));
    const thumbHtml = imgUrl ? `
        <div class="seasonal-product-thumb" title="${escapeHtml(item.product_name || 'Product')}">
            <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(item.product_name || 'Product')}" class="seasonal-product-img" loading="lazy" onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';">
            <div class="seasonal-thumb-fallback" style="display:none;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
        </div>
    ` : `
        <div class="seasonal-product-thumb" title="${escapeHtml(item.product_name || 'Product')}">
            <div class="seasonal-thumb-fallback">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
        </div>
    `;

    return `
        <tr>
            <td style="width: 34%;">
                <div class="seasonal-product-cell">
                    ${thumbHtml}
                    <div class="seasonal-product-info">
                        <div class="table-product-name">${escapeHtml(item.product_name || 'Product')}</div>
                        <div class="table-product-meta">
                            <span>${escapeHtml(item.category_name || '')}</span>
                            ${surgeHtml}
                        </div>
                        ${statusPillHtml ? `<div style="margin-top: 4px;">${statusPillHtml}</div>` : ''}
                    </div>
                </div>
            </td>
            <td style="width: 42%; min-width: 220px;">
                <div class="table-intel-sentence">${formatSafeSeasonalHtml(intelSentence)}</div>
                ${actionCalloutHtml}
            </td>
            <td style="width: 24%; min-width: 135px; text-align: right;">
                <div class="table-stock-wrap">
                    <div class="table-stock-num">${stockDisplay}</div>
                    <button type="button" class="btn-table-check check-stock-btn action-btn"
                        data-product="${escapeHtml(item.product_name || '')}"
                        data-category="${escapeHtml(item.category_name || '')}"
                        data-id="${escapeHtml(item.product_id || '')}"
                        onclick="handleCheckStockClick(event, this)">
                        Check stock &rarr;
                    </button>
                </div>
            </td>
        </tr>
    `;
}

window.setSeasonalModalPage = function(page) {
    const tableBody = document.getElementById('modalSeasonalTableBody');
    const pagContainer = document.getElementById('modalSeasonalPagination');
    if (!tableBody) return;

    const items = currentSeasonalModalItems || [];
    if (items.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding: 28px; color: var(--insights-text-muted);">No products found matching the seasonal profile for this period.</td></tr>`;
        if (pagContainer) {
            pagContainer.innerHTML = '';
            pagContainer.style.display = 'none';
        }
        return;
    }

    const perPage = 5;
    const totalItems = items.length;
    const totalPages = Math.ceil(totalItems / perPage) || 1;
    let validPage = parseInt(page, 10) || 1;
    if (validPage > totalPages) validPage = totalPages;
    if (validPage < 1) validPage = 1;
    seasonalModalPage = validPage;

    const start = (validPage - 1) * perPage;
    const end = Math.min(start + perPage, totalItems);
    const pageItems = items.slice(start, end);

    tableBody.innerHTML = pageItems.map(item => renderSeasonalModalRow(item, currentSeasonalPeriodName)).join('');

    if (pagContainer) {
        pagContainer.style.display = 'flex';
        let pageItems = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pageItems.push(i);
        } else if (validPage <= 3) {
            pageItems = [1, 2, 3, 4, 5, '...', totalPages];
        } else if (validPage >= totalPages - 2) {
            pageItems = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
            pageItems = [1, '...', validPage - 1, validPage, validPage + 1, '...', totalPages];
        }

        let pageNumbersHtml = '';
        pageItems.forEach(item => {
            if (item === '...') {
                pageNumbersHtml += `<span class="pagination-ellipsis">&hellip;</span>`;
            } else if (item === validPage) {
                pageNumbersHtml += `<button type="button" class="pagination-btn pagination-num active" disabled>${item}</button>`;
            } else {
                let isNeighbor = false;
                if (totalPages > 7) {
                    if (validPage <= 3) {
                        isNeighbor = (item === 4 || item === 5);
                    } else if (validPage >= totalPages - 2) {
                        isNeighbor = (item === totalPages - 4 || item === totalPages - 3);
                    } else {
                        isNeighbor = (item === validPage - 1 || item === validPage + 1);
                    }
                }
                const neighborClass = isNeighbor ? ' pagination-neighbor' : '';
                pageNumbersHtml += `<button type="button" class="pagination-btn pagination-num${neighborClass}" onclick="setSeasonalModalPage(${item})">${item}</button>`;
            }
        });

        pagContainer.innerHTML = `
            <div class="pagination-info-pill">Showing ${totalItems === 0 ? 0 : start + 1} to ${end} of ${totalItems} entries</div>
            <div class="pagination-btn-group flex items-center gap-1 sm:gap-1.5">
                <button type="button" class="pagination-btn pagination-nav" ${validPage === 1 ? 'disabled' : ''} onclick="setSeasonalModalPage(${validPage - 1})" aria-label="Previous page" title="Previous page">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    <span class="pagination-nav-text">Previous</span>
                </button>
                ${pageNumbersHtml}
                <button type="button" class="pagination-btn pagination-nav" ${validPage === totalPages ? 'disabled' : ''} onclick="setSeasonalModalPage(${validPage + 1})" aria-label="Next page" title="Next page">
                    <span class="pagination-nav-text">Next</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>
        `;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    setCategoryPage(1); setTrendsPage(1); setSupplierPage(1); setExpiryPage(1); setForecastPage(1);
    const initData = (allSeasonalMonthsData && (allSeasonalMonthsData[currentModalMonth] || allSeasonalMonthsData[String(currentModalMonth)])) || {};
    currentSeasonalModalItems = Array.isArray(initData) ? initData : (initData.items || []);
    currentSeasonalPeriodName = (philippineProfiles && philippineProfiles[currentModalMonth]) ? philippineProfiles[currentModalMonth].month_name : '';
    setSeasonalModalPage(1);
});

// ------------------------

const chartTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
if (typeof Chart !== 'undefined') {
    Chart.defaults.color = chartTheme === 'light' ? '#64748b' : '#9aa6d1';
    Chart.defaults.borderColor = chartTheme === 'light' ? 'rgba(148, 163, 184, 0.35)' : 'rgba(42, 68, 132, 0.3)';
    Chart.defaults.font.family = "'Onest', sans-serif";
    Chart.defaults.font.size = 12;
}

const categoriesLabels = <?php echo json_encode(array_column($categoryPerformance, 'category_name')); ?>;
const monthlySalesData = <?php echo json_encode(array_column($categoryPerformance, 'monthly_sales')); ?>;
const salesTrendsData = <?php echo json_encode(array_reverse($salesTrends)); ?>;
const topProductsData = <?php echo json_encode(array_slice($productMovement, 0, 8)); ?>;
const daysLookback = <?php echo $daysLookback; ?>;
const hasMlModel = <?php echo $hasMlModel ? 'true' : 'false'; ?>;
const seasonalChartData = <?php echo json_encode($seasonalChartData); ?>;
const segmentAccuracy = <?php echo json_encode($segmentAccuracy); ?>;
const salesForecastChartData = <?php echo json_encode($salesForecastChart); ?>;
const heatmapForecastData = <?php echo json_encode($heatmapForecast); ?>;
const depletionTrendData = <?php echo json_encode($depletionTrend); ?>;
const reorderFrequencyData = <?php echo json_encode($reorderFrequencyTrend); ?>;
const allSeasonalMonthsData = <?php echo json_encode($allSeasonalMonthsData); ?>;
const allSeasonalQuartersData = <?php echo json_encode($allSeasonalQuartersData); ?>;
const allSeasonalSemestersData = <?php echo json_encode($allSeasonalSemestersData); ?>;
const philippineProfiles = <?php echo json_encode($philippineProfiles); ?>;
const philippineQuarterProfiles = <?php echo json_encode($philippineQuarterProfiles); ?>;
const philippineSemesterProfiles = <?php echo json_encode($philippineSemesterProfiles); ?>;

let currentHorizon = 'monthly';
let currentModalMonth = <?php echo $selectedMonth; ?>;
let currentModalQuarter = <?php echo $selectedQuarter; ?>;
let currentModalSemester = <?php echo $selectedSemester; ?>;

window.openPhilippineSeasonalModal = function(horizon, key) {
    if (horizon) currentHorizon = horizon;
    if (horizon === 'quarterly' && key) currentModalQuarter = parseInt(key, 10);
    else if (horizon === 'semi-annual' && key) currentModalSemester = parseInt(key, 10);
    else if (key) currentModalMonth = parseInt(key, 10);

    const modal = document.getElementById('philippineSeasonalModal');
    if (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        seasonalModalPage = 1;
        window.switchSeasonalHorizon(currentHorizon);
        modal.classList.add('open');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
};

window.closePhilippineSeasonalModal = function() {
    const modal = document.getElementById('philippineSeasonalModal');
    if (modal) {
        modal.classList.remove('open');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
};

window.handleSeasonalModalBackdrop = function(e) {
    if (e.target.id === 'philippineSeasonalModal' || e.target.classList.contains('ph-seasonal-modal-overlay')) {
        window.closePhilippineSeasonalModal();
    }
};

function getWeatherSvgIcon(weatherStr) {
    const s = (weatherStr || '').toLowerCase();
    if (s.includes('amihan') || s.includes('cool') || s.includes('wind')) {
        return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"/></svg>`;
    } else if (s.includes('habagat') || s.includes('rain') || s.includes('monsoon') || s.includes('typhoon')) {
        return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16" y1="13" x2="16" y2="21"></line><line x1="8" y1="13" x2="8" y2="21"></line><line x1="12" y1="15" x2="12" y2="23"></line><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25"></path></svg>`;
    } else if (s.includes('tag-araw') || s.includes('dry') || s.includes('hot') || s.includes('summer')) {
        return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>`;
    }
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>`;
}

function syncSeasonalDropdowns() {
    const horizonSelect = document.getElementById('seasonalHorizonSelect');
    const periodSelect = document.getElementById('seasonalPeriodSelect');
    const periodLabel = document.getElementById('seasonalPeriodFieldLabel');
    if (!horizonSelect || !periodSelect) return;

    if (horizonSelect.value !== currentHorizon) {
        horizonSelect.value = currentHorizon;
    }

    const now = new Date();
    const currMonthNum = now.getMonth() + 1;
    const currQuarterNum = Math.ceil(currMonthNum / 3);
    const currSemesterNum = currMonthNum <= 6 ? 1 : 2;

    let optionsHtml = '';
    if (currentHorizon === 'monthly') {
        if (periodLabel) periodLabel.textContent = 'Target Month';
        for (let m = 1; m <= 12; m++) {
            const prof = (philippineProfiles && (philippineProfiles[m] || philippineProfiles[String(m)])) || {};
            const name = prof.month_name || `Month ${m}`;
            const badge = prof.season_badge ? ` (${prof.season_badge})` : '';
            const isCurr = (m === currMonthNum) ? ' • Current Month' : '';
            const selected = (m === currentModalMonth) ? 'selected' : '';
            optionsHtml += `<option value="${m}" ${selected}>${escapeHtml(name)}${escapeHtml(badge)}${isCurr}</option>`;
        }
    } else if (currentHorizon === 'quarterly') {
        if (periodLabel) periodLabel.textContent = 'Target Quarter';
        for (let q = 1; q <= 4; q++) {
            const prof = (philippineQuarterProfiles && (philippineQuarterProfiles[q] || philippineQuarterProfiles[String(q)])) || {};
            const shortName = prof.short_name || `Q${q}`;
            const name = prof.quarter_name || `Quarter ${q}`;
            const isCurr = (q === currQuarterNum) ? ' • Current Quarter' : '';
            const selected = (q === currentModalQuarter) ? 'selected' : '';
            optionsHtml += `<option value="${q}" ${selected}>${escapeHtml(shortName)}: ${escapeHtml(name)}${isCurr}</option>`;
        }
    } else if (currentHorizon === 'semi-annual') {
        if (periodLabel) periodLabel.textContent = 'Target Semester';
        for (let s = 1; s <= 2; s++) {
            const prof = (philippineSemesterProfiles && (philippineSemesterProfiles[s] || philippineSemesterProfiles[String(s)])) || {};
            const shortName = prof.short_name || `H${s}`;
            const name = prof.semester_name || `Semester ${s}`;
            const isCurr = (s === currSemesterNum) ? ' • Current Semester' : '';
            const selected = (s === currentModalSemester) ? 'selected' : '';
            optionsHtml += `<option value="${s}" ${selected}>${escapeHtml(shortName)}: ${escapeHtml(name)}${isCurr}</option>`;
        }
    }

    periodSelect.innerHTML = optionsHtml;
}

window.handleSeasonalHorizonDropdownChange = function(horizon) {
    currentHorizon = horizon || 'monthly';
    seasonalModalPage = 1;
    syncSeasonalDropdowns();
    renderActiveHorizonIntelligence();
};

window.handleSeasonalPeriodDropdownChange = function(val) {
    const parsedVal = parseInt(val, 10);
    if (currentHorizon === 'monthly') {
        currentModalMonth = parsedVal;
    } else if (currentHorizon === 'quarterly') {
        currentModalQuarter = parsedVal;
    } else if (currentHorizon === 'semi-annual') {
        currentModalSemester = parsedVal;
    }
    seasonalModalPage = 1;
    renderActiveHorizonIntelligence();
};

window.switchSeasonalHorizon = function(horizon) {
    currentHorizon = horizon || 'monthly';
    seasonalModalPage = 1;
    syncSeasonalDropdowns();
    renderActiveHorizonIntelligence();
};

window.switchSeasonalModalMonth = function(m) {
    currentHorizon = 'monthly';
    currentModalMonth = parseInt(m, 10);
    seasonalModalPage = 1;
    syncSeasonalDropdowns();
    renderActiveHorizonIntelligence();
};

window.switchSeasonalModalQuarter = function(q) {
    currentHorizon = 'quarterly';
    currentModalQuarter = parseInt(q, 10);
    seasonalModalPage = 1;
    syncSeasonalDropdowns();
    renderActiveHorizonIntelligence();
};

window.switchSeasonalModalSemester = function(s) {
    currentHorizon = 'semi-annual';
    currentModalSemester = parseInt(s, 10);
    seasonalModalPage = 1;
    syncSeasonalDropdowns();
    renderActiveHorizonIntelligence();
};

function renderActiveHorizonIntelligence() {
    let profile = null;
    let rawData = {};
    let periodDisplayName = '';

    if (currentHorizon === 'monthly') {
        profile = (philippineProfiles && (philippineProfiles[currentModalMonth] || philippineProfiles[String(currentModalMonth)])) || null;
        rawData = (allSeasonalMonthsData && (allSeasonalMonthsData[currentModalMonth] || allSeasonalMonthsData[String(currentModalMonth)])) || {};
        periodDisplayName = profile ? profile.month_name : 'Selected Month';
    } else if (currentHorizon === 'quarterly') {
        profile = (philippineQuarterProfiles && (philippineQuarterProfiles[currentModalQuarter] || philippineQuarterProfiles[String(currentModalQuarter)])) || null;
        rawData = (allSeasonalQuartersData && (allSeasonalQuartersData[currentModalQuarter] || allSeasonalQuartersData[String(currentModalQuarter)])) || {};
        periodDisplayName = profile ? profile.quarter_name : 'Selected Quarter';
    } else if (currentHorizon === 'semi-annual') {
        profile = (philippineSemesterProfiles && (philippineSemesterProfiles[currentModalSemester] || philippineSemesterProfiles[String(currentModalSemester)])) || null;
        rawData = (allSeasonalSemestersData && (allSeasonalSemestersData[currentModalSemester] || allSeasonalSemestersData[String(currentModalSemester)])) || {};
        periodDisplayName = profile ? profile.semester_name : 'Selected Semester';
    }

    const monthData = Array.isArray(rawData) ? rawData : (rawData.items || []);

    // 1. Update Intelligence Overview Banner
    const bannerEl = document.getElementById('modalSeasonalBanner');
    if (bannerEl && profile) {
        const driversHtml = (profile.cultural_drivers || []).map(d => `
            <div class="trigger-card">${escapeHtml(d)}</div>
        `).join('');

        bannerEl.innerHTML = `
            <div class="ph-header-meta">
                <span class="ph-season-pill">${escapeHtml(profile.season_badge || 'Seasonal Cycle')}</span>
                <span class="ph-weather-subtitle">${escapeHtml(profile.weather_ph || '')}</span>
            </div>
            <h3 class="ph-main-heading">
                ${escapeHtml(periodDisplayName)}: ${escapeHtml(profile.tagline || '')}
            </h3>
            <p class="ph-main-subtext">
                Philippine macroeconomic drivers, cultural events, and inventory stock alignment.
            </p>

            <div class="ph-section-label">CONSUMPTION TRIGGERS</div>
            <div class="consumption-triggers-grid">
                ${driversHtml}
            </div>
        `;
    }

    // 2. Update Table Title & Body
    const tableTitle = document.getElementById('modalTableTitle');
    if (tableTitle) {
        tableTitle.textContent = `Seasonal Demand & Stock Alignment: ${periodDisplayName}`;
    }

    const tableBody = document.getElementById('modalSeasonalTableBody');
    if (tableBody) {
        currentSeasonalModalItems = monthData;
        currentSeasonalPeriodName = periodDisplayName;
        setSeasonalModalPage(1);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('click', function(e) {
    const seasonalBtn = e.target.closest('.btn-seasonal-guide, [data-action="open-ph-seasonal"]');
    if (seasonalBtn) {
        e.preventDefault();
        window.openPhilippineSeasonalModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('philippineSeasonalModal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    if (typeof window.switchSeasonalModalMonth === 'function') {
        window.switchSeasonalModalMonth(currentModalMonth);
    }
});

<?php if ($highlightProductId): ?>
document.addEventListener('DOMContentLoaded', function() {
    const row = document.getElementById('highlighted-product-row');
    if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
<?php endif; ?>

window.checkStock = function(productName, categoryName, productId) {
    var params = new URLSearchParams();
    if (productId) params.set('product_id', productId);
    if (categoryName) params.set('category', categoryName);
    if (productName) params.set('q', productName);
    params.set('ref', 'insights');
    window.location.href = 'transactions.php?' + params.toString();
};

window.handleCheckStockClick = function(e, btn) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    var button = btn || (e ? e.target.closest('.check-stock-btn, .btn-table-check') : null);
    if (!button) return;
    var pName = button.getAttribute('data-product') || button.dataset.product || '';
    var cName = button.getAttribute('data-category') || button.dataset.category || '';
    var pId   = button.getAttribute('data-id') || button.dataset.id || '';
    window.checkStock(pName, cName, pId);
};

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.check-stock-btn, .btn-table-check');
    if (!btn) return;
    window.handleCheckStockClick(e, btn);
});

const currentForecastPeriod = <?php echo json_encode($forecastPeriod ?? 'monthly'); ?>;

window.renderForecastTableRows = function(items) {
    const tbody = document.getElementById('forecastTableBody');
    if (!tbody) return;

    if (!Array.isArray(items) || items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" style="text-align: center; padding: 32px 16px; color: var(--insights-text-muted); font-size: 13.5px;">
                    No restock recommendations or demand alerts for the selected period.
                </td>
            </tr>
        `;
        if (typeof setForecastPage === 'function') setForecastPage(1);
        return;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function sanitizeSnippet(str) {
        if (!str) return '';
        return String(str)
            .replace(/&lt;strong&gt;/gi, '<strong>')
            .replace(/&lt;\/strong&gt;/gi, '</strong>')
            .replace(/&lt;em&gt;/gi, '<em>')
            .replace(/&lt;\/em&gt;/gi, '</em>')
            .replace(/&lt;b&gt;/gi, '<b>')
            .replace(/&lt;\/b&gt;/gi, '</b>');
    }

    let rowsHtml = '';
    items.forEach(item => {
        const pName = escapeHtml(item.product_name || 'Unnamed Product');
        const cName = escapeHtml(item.category_name || 'General');
        const isHighlight = Boolean(item.highlight);
        const rowClass = isHighlight ? 'forecast-row--highlighted' : '';
        const rowId = isHighlight ? ' id="highlighted-product-row"' : '';

        // Status badge calculation
        const typeStr = item.type || item.status || 'Normal';
        let badgeColor = '#9aa6d1';
        let badgeBg = 'rgba(154, 166, 209, 0.15)';
        if (/Critical|Out of Stock/i.test(typeStr)) {
            badgeColor = '#ef4444';
            badgeBg = 'rgba(239, 68, 68, 0.15)';
        } else if (/Risk|Low/i.test(typeStr)) {
            badgeColor = '#f59e0b';
            badgeBg = 'rgba(245, 158, 11, 0.15)';
        } else if (/Well Stocked|Optimal/i.test(typeStr)) {
            badgeColor = '#10b981';
            badgeBg = 'rgba(16, 185, 129, 0.15)';
        } else if (/Opportunity/i.test(typeStr)) {
            badgeColor = '#3b82f6';
            badgeBg = 'rgba(59, 130, 246, 0.15)';
        } else if (/Moderate|Cover/i.test(typeStr)) {
            badgeColor = '#eab308';
            badgeBg = 'rgba(234, 179, 8, 0.15)';
        } else if (/Slow/i.test(typeStr)) {
            badgeColor = '#a855f7';
            badgeBg = 'rgba(168, 85, 247, 0.15)';
        }

        const stockNum = Number(item.current_stock ?? 0);
        const stockFormatted = stockNum.toLocaleString();

        let demandLiftHtml = '';
        if (item.demand_lift) {
            demandLiftHtml = `
                <span class="forecast-stock-pill" style="border-color: rgba(81, 213, 90, 0.4); color: #51D55A;">
                    <strong>${escapeHtml(item.demand_lift)} Demand</strong>
                </span>
            `;
        }

        let descColHtml = '';
        if (item.descriptive && item.predictive && item.prescriptive) {
            let actionClass = 'action-optimal';
            let actionBadgeClass = 'insight-badge--action';
            if (item.action_needed === 'urgent') {
                actionClass = 'action-urgent';
                actionBadgeClass = 'insight-badge--urgent';
            } else if (item.action_needed === 'pause') {
                actionClass = 'action-pause';
                actionBadgeClass = 'insight-badge--pause';
            } else if (item.action_needed === 'restock' || item.action_needed === 'monitor') {
                actionClass = 'action-monitor';
                actionBadgeClass = 'insight-badge--monitor';
            }

            descColHtml = `
                <div class="insight-hierarchy">
                    <div class="insight-row insight-row--situation">
                        <span class="insight-badge insight-badge--situation">Context</span>
                        <div class="insight-desc">${sanitizeSnippet(item.descriptive)}</div>
                    </div>
                    <div class="insight-row insight-row--forecast">
                        <span class="insight-badge insight-badge--forecast">Forecast</span>
                        <div class="insight-desc">${sanitizeSnippet(item.predictive)}</div>
                    </div>
                    <div class="insight-row insight-row--action ${actionClass}">
                        <span class="insight-badge ${actionBadgeClass}">Action</span>
                        <div class="insight-desc insight-desc--action">${sanitizeSnippet(item.prescriptive)}</div>
                    </div>
                </div>
            `;
        } else {
            descColHtml = `<div class="insight-text">${sanitizeSnippet(item.insight || '')}</div>`;
        }

        const pIdAttr = item.product_id ? ` data-id="${item.product_id}"` : '';

        rowsHtml += `
            <tr class="${rowClass}"${rowId}>
                <td>
                    <div class="product-title">${pName}</div>
                    <div class="product-sub">${cName}</div>
                    <div style="display: flex; gap: 6px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
                        <span class="forecast-status-badge" style="background-color: ${badgeBg}; color: ${badgeColor}; border: 1px solid ${badgeColor}40;">
                            ${escapeHtml(typeStr)}
                        </span>
                        <span class="forecast-stock-pill">
                            Stock: <strong>${stockFormatted}</strong>
                        </span>
                        ${demandLiftHtml}
                    </div>
                </td>
                <td>
                    ${descColHtml}
                </td>
                <td>
                    <button type="button" class="action-btn check-stock-btn"
                        data-product="${pName}"
                        data-category="${cName}"${pIdAttr}>
                        Check Stock
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;
    if (typeof setForecastPage === 'function') {
        setForecastPage(1);
    }
};

window.refreshSmartRestockForecast = function(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    const btn = document.getElementById('btnRefreshSmartRestock');
    const label = document.getElementById('refreshBtnLabel');
    const pill = document.getElementById('forecastSyncStatusPill');
    const pillText = document.getElementById('forecastSyncStatusText');
    const tbody = document.getElementById('forecastTableBody');

    if (btn && btn.classList.contains('is-loading')) {
        return;
    }

    // Enter loading state
    if (btn) {
        btn.classList.remove('is-success');
        btn.classList.add('is-loading');
    }
    if (label) label.textContent = 'Syncing Live Stock...';
    if (pill) pill.classList.add('is-loading');
    if (pillText) pillText.textContent = 'Reconciling warehouse inventory...';
    if (tbody) tbody.classList.add('is-refreshing');

    const period = (typeof currentForecastPeriod !== 'undefined' && currentForecastPeriod) ? currentForecastPeriod : 'monthly';
    const url = 'api/refresh_smart_restock.php?period=' + encodeURIComponent(period);

    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Failed to refresh forecast');
        }

        // Render fresh table rows with verified live stock
        window.renderForecastTableRows(data.items || []);

        // Success state
        if (btn) {
            btn.classList.remove('is-loading');
            btn.classList.add('is-success');
        }
        if (label) label.textContent = 'Live Stock Synced!';
        if (pill) pill.classList.remove('is-loading');
        if (pillText) pillText.textContent = 'Live inventory synced: Just now';

        setTimeout(() => {
            if (btn) {
                btn.classList.remove('is-success');
            }
            if (label) label.textContent = 'Refresh Forecast';
        }, 2500);
    })
    .catch(err => {
        console.error('[SmartRestock] Refresh error:', err);
        if (btn) {
            btn.classList.remove('is-loading', 'is-success');
        }
        if (label) label.textContent = 'Retry Sync';
        if (pill) pill.classList.remove('is-loading');
        if (pillText) pillText.textContent = 'Sync failed. Click to retry';
    })
    .finally(() => {
        if (tbody) tbody.classList.remove('is-refreshing');
    });
};

function loadForecastContent(e, url) {
    if(e) e.preventDefault();
    
    const widget = document.getElementById('forecastWidget');
    widget.classList.add('loading');
    
    fetch(url)
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        const newWidgetContent = doc.getElementById('forecastWidget').innerHTML;
        
        widget.innerHTML = newWidgetContent;
        if (typeof setForecastPage === 'function') {
            setForecastPage(1);
        }
        
        window.history.pushState({}, '', url);
        
        setTimeout(() => {
            widget.classList.remove('loading');
        }, 300); 
    })
    .catch(err => {
        console.error('Error fetching forecast:', err);
        widget.classList.remove('loading');
        alert('Failed to load forecast data. Please try again.');
    });
}

function renderCategoryChart() {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    if (window.categoryChartInstance) window.categoryChartInstance.destroy();

    window.categoryChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: categoriesLabels,
            datasets: [{
                label: 'Monthly Sales',
                data: monthlySalesData,
                backgroundColor: 'rgba(81, 213, 90, 0.5)',
                borderColor: '#51D55A',
                borderWidth: 1,
                hoverBackgroundColor: '#51D55A'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(42, 68, 132, 0.3)' }, ticks: { color: '#E3E3E3' } },
                x: { grid: { display: false }, ticks: { color: '#E3E3E3' } }
            },
            plugins: { legend: { display: false } }
        }
    });
}

function renderSalesTrendChart() {
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    if (window.trendChartInstance) window.trendChartInstance.destroy();

    const dates = salesTrendsData.map(trend => trend.sale_date);
    const quantities = salesTrendsData.map(trend => trend.total_quantity_sold);

    window.trendChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Daily Sales Quantity',
                data: quantities,
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(81, 213, 90, 0.4)');
                    gradient.addColorStop(1, 'rgba(81, 213, 90, 0.0)');
                    return gradient;
                },
                borderColor: '#51D55A',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#0B1120',
                pointBorderColor: '#51D55A'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(42, 68, 132, 0.3)' }, ticks: { color: '#E3E3E3' } },
                x: { grid: { display: false }, ticks: { color: '#9aa6d1' } }
            },
            plugins: { legend: { display: false } }
        }
    });
}

function renderSalesForecastChart() {
    const ctx = document.getElementById('salesForecastChart').getContext('2d');

    let labels, historicalVals, forecastVals;

    if (hasMlModel && salesForecastChartData.has_predictions) {
        labels = salesForecastChartData.labels.map((d, i) => d || `Day ${i + 1}`);
        historicalVals = salesForecastChartData.historical;
        forecastVals = salesForecastChartData.predicted;
    } else {
        labels = salesTrendsData.map(t => t.sale_date);
        historicalVals = salesTrendsData.map(t => t.total_quantity_sold);
        forecastVals = historicalVals.map(() => null);
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Historical',
                    data: historicalVals,
                    borderColor: '#51D55A',
                    backgroundColor: 'rgba(81, 213, 90, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    spanGaps: false
                },
                {
                    label: 'Prediction',
                    data: forecastVals,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: true,
                    spanGaps: false
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(42, 68, 132, 0.3)' }, ticks: { color: '#E3E3E3' } },
                x: { grid: { display: false }, ticks: { display: false, maxTicksLimit: 10 } }
            },
            plugins: { legend: { labels: { color: '#E3E3E3' } } }
        }
    });
}

function renderDemandHeatmapChart() {
    const canvas = document.getElementById('demandHeatmapChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    let productNames = [], forecastDemand = [];

    if (hasMlModel && heatmapForecastData && heatmapForecastData.available && heatmapForecastData.labels.length > 0) {
        productNames = heatmapForecastData.labels.map(n => n.length > 15 ? n.substring(0, 15) + '...' : n);
        forecastDemand = heatmapForecastData.demand.map(d => Number(d) || 0);
    } else if (topProductsData && topProductsData.length > 0) {
        productNames = topProductsData.map(p => (p.product_name || 'Item').length > 15 ? (p.product_name || 'Item').substring(0, 15) + '...' : (p.product_name || 'Item'));
        forecastDemand = topProductsData.map(p => Number(p.monthly_sales) || 0);
    }

    if (productNames.length === 0) return;

    // Calculate dynamic demand thresholds based on actual distribution in the dataset
    const maxDemand = Math.max(...forecastDemand, 1);
    const highThreshold = maxDemand * 0.75;
    const medThreshold  = maxDemand * 0.45;
    const lowThreshold  = maxDemand * 0.20;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: productNames,
            datasets: [{
                label: 'Forecasted Demand',
                data: forecastDemand,
                backgroundColor: forecastDemand.map(d => {
                    if (d <= 0) return '#51D55A';
                    if (d >= highThreshold) return '#ef4444';
                    if (d >= medThreshold) return '#f59e0b';
                    if (d >= lowThreshold) return '#3b82f6';
                    return '#51D55A';
                }),
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(42, 68, 132, 0.3)' }, ticks: { color: '#E3E3E3' } },
                y: { grid: { display: false }, ticks: { color: '#E3E3E3' } }
            },
            plugins: { legend: { display: false } }
        }
    });
}

function renderDepletionTrendChart() {
    const canvas = document.getElementById('depletionTrendChart');
    if (!canvas || !depletionTrendData || !depletionTrendData.available) return;

    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: depletionTrendData.labels,
            datasets: [
                {
                    label: 'Stock Outs',
                    data: depletionTrendData.depletion,
                    backgroundColor: 'rgba(248, 113, 113, 0.7)',
                    borderColor: '#f87171',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'Restocks',
                    data: depletionTrendData.restocks,
                    backgroundColor: 'rgba(81, 213, 90, 0.65)',
                    borderColor: '#51D55A',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(42, 68, 132, 0.3)' }, ticks: { color: '#E3E3E3' } },
                x: { grid: { display: false }, ticks: { color: '#E3E3E3' } }
            },
            plugins: { legend: { labels: { color: '#E3E3E3' } } }
        }
    });
}

function renderReorderFrequencyChart() {
    const canvas = document.getElementById('reorderFrequencyChart');
    if (!canvas || !reorderFrequencyData.available) return;

    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: reorderFrequencyData.labels,
            datasets: [
                {
                    label: 'Restock Events',
                    data: reorderFrequencyData.events,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.15)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Units Restocked',
                    data: reorderFrequencyData.units,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    borderDash: [4, 4],
                    tension: 0.35,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(42, 68, 132, 0.3)' }, ticks: { color: '#E3E3E3' } },
                x: { grid: { display: false }, ticks: { color: '#E3E3E3' } }
            },
            plugins: { legend: { labels: { color: '#E3E3E3' } } }
        }
    });
}

function renderSeasonalChart() {
    if (!hasMlModel || !seasonalChartData.available) return;

    const ctx = document.getElementById('seasonalChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: seasonalChartData.labels,
            datasets: [
                {
                    label: 'Historical Sales',
                    data: seasonalChartData.historical,
                    borderColor: '#51D55A',
                    backgroundColor: 'rgba(81, 213, 90, 0.15)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    spanGaps: false
                },
                {
                    label: 'Forecasted Sales',
                    data: seasonalChartData.forecasted,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.15)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: true,
                    spanGaps: false
                },
                {
                    label: 'Trend',
                    data: seasonalChartData.trend,
                    borderColor: '#8b5cf6',
                    borderWidth: 2,
                    borderDash: [2, 2],
                    tension: 0.4,
                    fill: false,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(42, 68, 132, 0.3)' }, ticks: { color: '#E3E3E3' } },
                x: { grid: { display: false }, ticks: { color: '#E3E3E3', font: { size: 9 }, maxRotation: 45 } }
            },
            plugins: { legend: { labels: { color: '#E3E3E3', font: { size: 10 } } } }
        }
    });
}

function renderAccuracyChart() {
    if (!hasMlModel) return;

    const ctx = document.getElementById('accuracyChart').getContext('2d');
    const segLabels = Object.keys(segmentAccuracy);
    const segValues = Object.values(segmentAccuracy);

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: segLabels,
            datasets: [{
                label: 'Accuracy %',
                data: segValues,
                backgroundColor: 'rgba(81, 213, 90, 0.2)',
                borderColor: '#51D55A',
                borderWidth: 2,
                pointBackgroundColor: '#51D55A'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                r: {
                    angleLines: { color: 'rgba(42, 68, 132, 0.3)' },
                    grid: { color: 'rgba(42, 68, 132, 0.3)' },
                    pointLabels: { color: '#E3E3E3', font: { size: 11 } },
                    ticks: { display: false, backdropColor: 'transparent' },
                    min: 0, max: 100
                }
            },
            plugins: { legend: { display: false } }
        }
    });
}

document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
        if (tab.getAttribute('data-tab') === 'category-performance') { renderCategoryChart(); } 
        else if (tab.getAttribute('data-tab') === 'sales-trends') { renderSalesTrendChart(); }
    });
});

let currentProductPage = 1;
const productsPerPage = 8;

function filterProductTable() {
    const searchEl = document.getElementById('productSearch');
    const categoryEl = document.getElementById('categoryFilter');
    const movementEl = document.getElementById('movementFilter');
    const stockEl = document.getElementById('stockFilter');

    const searchTerm = (searchEl ? searchEl.value : '').toLowerCase().trim();
    const categoryFilter = categoryEl ? categoryEl.value : '';
    const movementFilter = movementEl ? movementEl.value : '';
    const stockFilter = stockEl ? stockEl.value : '';
    
    const allRows = document.querySelectorAll('#productTableBody tr');
    let matchingRows = [];
    
    allRows.forEach(row => {
        if (row.id === 'noProductMatchRow') return;
        if (!row.cells || row.cells.length < 8) return;

        const productName = (row.cells[0].textContent || '').toLowerCase().trim();
        const category = (row.getAttribute('data-category') || row.dataset.category || '').trim();
        const movement = (row.getAttribute('data-movement') || row.dataset.movement || '').trim();
        const stock = (row.getAttribute('data-stock') || row.dataset.stock || '').trim();
        
        const matchesSearch = !searchTerm || productName.includes(searchTerm) || category.toLowerCase().includes(searchTerm) || (row.textContent || '').toLowerCase().includes(searchTerm);
        const matchesCategory = !categoryFilter || category.toLowerCase() === categoryFilter.toLowerCase();
        const matchesMovement = !movementFilter || movement.toLowerCase() === movementFilter.toLowerCase();
        const matchesStock = !stockFilter || (stockFilter === 'attention_needed' && (stock.toLowerCase() === 'low_stock' || stock.toLowerCase() === 'out_of_stock')) || stock.toLowerCase() === stockFilter.toLowerCase();
        
        if (matchesSearch && matchesCategory && matchesMovement && matchesStock) {
            row.setAttribute('data-matched', 'true');
            matchingRows.push(row);
        } else {
            row.setAttribute('data-matched', 'false');
            row.style.setProperty('display', 'none', 'important');
            row.classList.add('hidden');
        }
    });
    
    const totalMatches = matchingRows.length;
    const totalPages = Math.ceil(totalMatches / productsPerPage) || 1;
    
    if (currentProductPage > totalPages) {
        currentProductPage = totalPages;
    }
    if (currentProductPage < 1) {
        currentProductPage = 1;
    }
    
    const startIndex = (currentProductPage - 1) * productsPerPage;
    const endIndex = Math.min(startIndex + productsPerPage, totalMatches);
    
    matchingRows.forEach((row, index) => {
        if (index >= startIndex && index < endIndex) {
            row.style.removeProperty('display');
            row.classList.remove('hidden');
        } else {
            row.style.setProperty('display', 'none', 'important');
            row.classList.add('hidden');
        }
    });

    // Empty state handling
    let noMatchRow = document.getElementById('noProductMatchRow');
    if (totalMatches === 0) {
        if (!noMatchRow) {
            noMatchRow = document.createElement('tr');
            noMatchRow.id = 'noProductMatchRow';
            noMatchRow.innerHTML = '<td colspan="8" style="text-align:center; padding:32px 16px; color:#9aa6d1; font-size:14px; position:static !important; background:transparent !important; box-shadow:none !important;">No products match the selected criteria.</td>';
            const tbody = document.getElementById('productTableBody');
            if (tbody) tbody.appendChild(noMatchRow);
        } else {
            noMatchRow.style.removeProperty('display');
            noMatchRow.classList.remove('hidden');
        }
    } else if (noMatchRow) {
        noMatchRow.style.setProperty('display', 'none', 'important');
        noMatchRow.classList.add('hidden');
    }
    
    // Update pagination controls UI
    const infoStart = document.getElementById('paginationInfoStart');
    const infoEnd = document.getElementById('paginationInfoEnd');
    const infoTotal = document.getElementById('paginationInfoTotal');
    if (infoStart) infoStart.textContent = totalMatches > 0 ? startIndex + 1 : 0;
    if (infoEnd) infoEnd.textContent = endIndex;
    if (infoTotal) infoTotal.textContent = totalMatches;
    
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    if (prevBtn) {
        prevBtn.disabled = currentProductPage <= 1;
        if (currentProductPage <= 1) prevBtn.classList.add('disabled'); else prevBtn.classList.remove('disabled');
    }
    if (nextBtn) {
        nextBtn.disabled = currentProductPage >= totalPages;
        if (currentProductPage >= totalPages) nextBtn.classList.add('disabled'); else nextBtn.classList.remove('disabled');
    }

    const pageNumbersEl = document.getElementById('productPageNumbers');
    if (pageNumbersEl) {
        let pageItems = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pageItems.push(i);
        } else if (currentProductPage <= 3) {
            pageItems = [1, 2, 3, 4, 5, '...', totalPages];
        } else if (currentProductPage >= totalPages - 2) {
            pageItems = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
            pageItems = [1, '...', currentProductPage - 1, currentProductPage, currentProductPage + 1, '...', totalPages];
        }

        let pagesHtml = '';
        pageItems.forEach(item => {
            if (item === '...') {
                pagesHtml += `<span class="pagination-ellipsis">&hellip;</span>`;
            } else if (item === currentProductPage) {
                pagesHtml += `<button type="button" class="pagination-btn pagination-num active" disabled>${item}</button>`;
            } else {
                let isNeighbor = false;
                if (totalPages > 7) {
                    if (currentProductPage <= 3) {
                        isNeighbor = (item === 4 || item === 5);
                    } else if (currentProductPage >= totalPages - 2) {
                        isNeighbor = (item === totalPages - 4 || item === totalPages - 3);
                    } else {
                        isNeighbor = (item === currentProductPage - 1 || item === currentProductPage + 1);
                    }
                }
                const neighborClass = isNeighbor ? ' pagination-neighbor' : '';
                pagesHtml += `<button type="button" class="pagination-btn pagination-num${neighborClass}" onclick="setProductPage(${item})">${item}</button>`;
            }
        });
        pageNumbersEl.innerHTML = pagesHtml;
    }
}

window.filterProductTable = filterProductTable;

window.setProductPage = function(page) {
    currentProductPage = page;
    filterProductTable();
};

window.resetProductFilters = function() {
    var searchEl = document.getElementById('productSearch');
    var catEl = document.getElementById('categoryFilter');
    var movEl = document.getElementById('movementFilter');
    var stockEl = document.getElementById('stockFilter');
    if (searchEl) searchEl.value = '';
    if (catEl) catEl.value = '';
    if (movEl) movEl.value = '';
    if (stockEl) stockEl.value = '';
    document.querySelectorAll('.metric-card').forEach(function(card) { card.classList.remove('active'); });
    currentProductPage = 1;
    filterProductTable();
};

function initProductFilters() {
    var productSearch   = document.getElementById('productSearch');
    var categoryFilter  = document.getElementById('categoryFilter');
    var movementFilter  = document.getElementById('movementFilter');
    var stockFilter     = document.getElementById('stockFilter');
    var prevPageBtn     = document.getElementById('prevPageBtn');
    var nextPageBtn     = document.getElementById('nextPageBtn');

    if (productSearch) {
        productSearch.oninput = function () { currentProductPage = 1; filterProductTable(); };
    }

    function bindSelectFilter(el) {
        if (!el) return;
        el.onchange = function () { currentProductPage = 1; filterProductTable(); };
        el.oninput = function () { currentProductPage = 1; filterProductTable(); };
    }

    bindSelectFilter(categoryFilter);
    bindSelectFilter(movementFilter);
    bindSelectFilter(stockFilter);

    if (prevPageBtn) {
        prevPageBtn.onclick = function () {
            if (currentProductPage > 1) {
                currentProductPage--;
                filterProductTable();
            }
        };
    }

    if (nextPageBtn) {
        nextPageBtn.onclick = function () {
            currentProductPage++;
            filterProductTable();
        };
    }

    filterProductTable();
}

window.initProductFilters = initProductFilters;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductFilters);
} else {
    initProductFilters();
}


function filterData(filterType) {
    document.querySelectorAll('.metric-card').forEach(card => card.classList.remove('active'));
    const targetCard = document.querySelector(`.metric-card[data-filter="${filterType}"]`);
    if (targetCard) targetCard.classList.add('active');
    
    currentProductPage = 1;
    
    const searchEl = document.getElementById('productSearch');
    const catEl = document.getElementById('categoryFilter');
    const movEl = document.getElementById('movementFilter');
    const stockEl = document.getElementById('stockFilter');

    switch(filterType) {
        case 'attention_needed': 
            document.querySelector('.tab[data-tab="product-analysis"]').click();
            if (searchEl) searchEl.value = '';
            if (catEl) catEl.value = '';
            if (movEl) movEl.value = '';
            if (stockEl) stockEl.value = 'attention_needed';
            filterProductTable();
            break;
        case 'fast_moving':
            document.querySelector('.tab[data-tab="product-analysis"]').click();
            if (searchEl) searchEl.value = '';
            if (catEl) catEl.value = '';
            if (movEl) movEl.value = 'fast_moving';
            if (stockEl) stockEl.value = '';
            filterProductTable();
            break;
        case 'expiring':
            document.querySelector('.tab[data-tab="expiry-risk"]').click();
            break;
        default:
            document.querySelector('.tab[data-tab="product-analysis"]').click();
            if (searchEl) searchEl.value = '';
            if (catEl) catEl.value = '';
            if (movEl) movEl.value = '';
            if (stockEl) stockEl.value = '';
            filterProductTable();
    }
}

function refreshAnalysis() { location.reload(); }
function generateReport() { alert('PDF report generation feature coming soon!'); }
function exportData() {
    const activeTab = document.querySelector('.tab-content.active').id;
    let csv = '';
    let filename = '';
    switch(activeTab) {
        case 'product-analysis':
            csv = 'Product,Category,Current Stock,Monthly Sales,Daily Sales Velocity,Days of Supply,Movement Class,Stock Status\n';
            document.querySelectorAll('#productTableBody tr[data-matched="true"]').forEach(row => {
                const cells = row.querySelectorAll('td');
                csv += `"${cells[0].textContent}","${cells[1].textContent}",${cells[2].textContent},${cells[3].textContent},${cells[4].textContent},${cells[5].textContent},"${cells[6].textContent}","${cells[7].textContent}"\n`;
            });
            filename = 'product_analysis_' + new Date().toISOString().split('T')[0] + '.csv';
            break;
    }
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}


document.addEventListener('DOMContentLoaded', function() {
    renderSalesForecastChart();
    renderDemandHeatmapChart();
    renderDepletionTrendChart();
    renderReorderFrequencyChart();
    renderSeasonalChart();
    renderAccuracyChart();
    
    if (document.querySelector('.tab[data-tab="category-performance"]').classList.contains('active')) { renderCategoryChart(); } 
    else if (document.querySelector('.tab[data-tab="sales-trends"]').classList.contains('active')) { renderSalesTrendChart(); }
    
    const infoIcons = document.querySelectorAll('.info-icon');
    let activeTooltip = null;
    const overlay = document.createElement('div');
    overlay.className = 'tooltip-overlay';
    document.body.appendChild(overlay);
    
    infoIcons.forEach(icon => {
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
            const tooltipId = this.getAttribute('data-tooltip');
            const tooltip = document.getElementById(tooltipId);
            if (activeTooltip) { activeTooltip.classList.remove('show'); overlay.classList.remove('show'); }
            if (activeTooltip === tooltip) { activeTooltip = null; return; }
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.bottom + window.scrollY + 10) + 'px';
            tooltip.style.left = '50%';
            tooltip.style.transform = 'translateX(-50%)';
            tooltip.classList.add('show');
            overlay.classList.add('show');
            activeTooltip = tooltip;
        });
    });
    
    overlay.addEventListener('click', function() {
        if (activeTooltip) { activeTooltip.classList.remove('show'); overlay.classList.remove('show'); activeTooltip = null; }
    });
    
    document.addEventListener('click', function(e) {
        if (activeTooltip && !activeTooltip.contains(e.target) && !e.target.classList.contains('info-icon')) {
            activeTooltip.classList.remove('show'); overlay.classList.remove('show'); activeTooltip = null;
        }
    });
    
    document.querySelectorAll('.info-tooltip').forEach(tooltip => {
        tooltip.addEventListener('click', function(e) { e.stopPropagation(); });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (activeTooltip) { activeTooltip.classList.remove('show'); overlay.classList.remove('show'); activeTooltip = null; }
            closePhilippineSeasonalModal();
        }
    });
});
</script>

<?php require_once 'src/partials/ph_seasonal_modal.php'; ?>

<?php
renderLayoutEnd();
?>