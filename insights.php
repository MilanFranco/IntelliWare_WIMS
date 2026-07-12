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

$weeklyDefaults = [
    'Jan' => [
        1 => ['name' => 'Dona Maria Rice 5kg', 'cat' => 'Dry Goods', 'reason' => 'Households refill staples after New Year feasts.'],
        2 => ['name' => 'Century Tuna Flakes', 'cat' => 'Canned Goods', 'reason' => 'Families tighten budgets after holiday overspending.'],
        3 => ['name' => 'Nescafé Classic', 'cat' => 'Beverages', 'reason' => 'Cooler January mornings keep hot coffee demand strong.'],
        4 => ['name' => 'Gardenia Bread Loaf', 'cat' => 'Bakery Products', 'reason' => 'Payday weekend pushes breakfast restocking.'],
    ],
    'Feb' => [
        1 => ['name' => 'Purefoods Chicken Nuggets', 'cat' => 'Frozen Foods', 'reason' => 'Shift to convenient freezer meals after holiday cooking fatigue.'],
        2 => ['name' => 'Oatside Oat Milk', 'cat' => 'Dairy Products', 'reason' => 'Health-conscious buying trends often spike in Feb/Valentine season.'],
        3 => ['name' => 'Dove Soap Bar', 'cat' => 'Personal Care', 'reason' => 'Mid-month hygiene restocking cycle.'],
        4 => ['name' => 'Gardenia Cakes 6pcs', 'cat' => 'Bakery Products', 'reason' => 'School activities return; high demand for "baon" snacks.'],
    ],
    'Mar' => [ 1 => ['name' => 'Coca-Cola 330ml', 'cat' => 'Beverages', 'reason' => 'Temperatures rising.'], 2 => ['name' => 'Frozen Fish', 'cat' => 'Frozen', 'reason' => 'Lent season.'], 3 => ['name' => 'Royal Pasta', 'cat' => 'Dry', 'reason' => 'Exam weeks.'], 4 => ['name' => 'Pepsi', 'cat' => 'Bev', 'reason' => 'Peak heat.'] ],
    'Apr' => [ 1 => ['name' => 'Tuna', 'cat' => 'Canned', 'reason' => 'Holy Week.'], 2 => ['name' => 'Nuggets', 'cat' => 'Frozen', 'reason' => 'Summer break.'], 3 => ['name' => 'Soap', 'cat' => 'Care', 'reason' => 'Summer heat.'], 4 => ['name' => 'Rice', 'cat' => 'Dry', 'reason' => 'Payday.'] ],
    'May' => [ 1 => ['name' => 'Pasta', 'cat' => 'Dry', 'reason' => 'Fiestas.'], 2 => ['name' => 'Pens', 'cat' => 'Stationery', 'reason' => 'School prep.'], 3 => ['name' => 'Corned Beef', 'cat' => 'Canned', 'reason' => 'Tuition saving.'], 4 => ['name' => 'Coke', 'cat' => 'Bev', 'reason' => 'End of summer.'] ],
    'Jun' => [ 1 => ['name' => 'Notebooks', 'cat' => 'Stationery', 'reason' => 'Back to school.'], 2 => ['name' => 'Cakes', 'cat' => 'Bakery', 'reason' => 'Baon.'], 3 => ['name' => 'Detergent', 'cat' => 'Household', 'reason' => 'Rainy season.'], 4 => ['name' => 'Coffee', 'cat' => 'Bev', 'reason' => 'Rainy mornings.'] ],
    'Jul' => [ 1 => ['name' => 'Tuna', 'cat' => 'Canned', 'reason' => 'Typhoons.'], 2 => ['name' => 'Milk', 'cat' => 'Dairy Products', 'reason' => 'Flu season.'], 3 => ['name' => 'Cleaner', 'cat' => 'Household', 'reason' => 'Mud/Rain.'], 4 => ['name' => 'Pasta', 'cat' => 'Dry', 'reason' => 'Comfort food.'] ],
    'Aug' => [ 1 => ['name' => 'Bread', 'cat' => 'Bakery', 'reason' => 'Rain/Power outages.'], 2 => ['name' => 'Toothpaste', 'cat' => 'Care', 'reason' => 'Hygiene.'], 3 => ['name' => 'Corned Beef', 'cat' => 'Canned', 'reason' => 'Storms.'], 4 => ['name' => 'Coffee', 'cat' => 'Bev', 'reason' => 'Cold weather.'] ],
    'Sep' => [ 1 => ['name' => 'Nuggets', 'cat' => 'Frozen', 'reason' => 'Ber months.'], 2 => ['name' => 'Milk', 'cat' => 'Dairy Products', 'reason' => 'Cool evenings.'], 3 => ['name' => 'Pasta', 'cat' => 'Dry', 'reason' => 'Holiday hoarding.'], 4 => ['name' => 'Evap', 'cat' => 'Dairy Products', 'reason' => 'Dessert trials.'] ],
    'Oct' => [ 1 => ['name' => 'Cleaner', 'cat' => 'Household', 'reason' => 'Guest prep.'], 2 => ['name' => 'Bread', 'cat' => 'Bakery', 'reason' => 'Undas prep.'], 3 => ['name' => 'Coke', 'cat' => 'Bev', 'reason' => 'Gatherings.'], 4 => ['name' => 'Fish Fillet', 'cat' => 'Frozen', 'reason' => 'Health options.'] ],
    'Nov' => [ 1 => ['name' => 'Cakes', 'cat' => 'Bakery', 'reason' => 'Undas.'], 2 => ['name' => 'Rice', 'cat' => 'Dry', 'reason' => 'Stockpiling.'], 3 => ['name' => 'Pasta', 'cat' => 'Dry', 'reason' => 'Company parties.'], 4 => ['name' => 'Evap', 'cat' => 'Dairy Products', 'reason' => 'Fruit salads.'] ],
    'Dec' => [ 1 => ['name' => 'Pasta', 'cat' => 'Dry', 'reason' => 'Christmas Spaghetti.'], 2 => ['name' => 'Nuggets', 'cat' => 'Frozen', 'reason' => 'Kids parties.'], 3 => ['name' => 'Butter', 'cat' => 'Condiments & Sauces','reason' => 'Noche Buena.'], 4 => ['name' => 'Tuna', 'cat' => 'Canned', 'reason' => 'New Year closure.'] ],
];

$monthlyDefaults = [
    1 => ['name' => 'Oats and Cereals', 'cat' => 'Dry Goods', 'reason' => 'Consumers move to low cost health foods after holiday expenses.'],
    2 => ['name' => 'Chocolates and Wine', 'cat' => 'Snacks and Biscuits', 'reason' => 'Valentine sales push impulse buying.'],
    3 => ['name' => 'Beer and Cold Drinks', 'cat' => 'Alcohol and Spirits', 'reason' => 'Heat rises early in the Philippines. Beverage demand spikes.'],
    4 => ['name' => 'Soft Drinks and Water', 'cat' => 'Beverages', 'reason' => 'Peak heat brings strong hydration demand.'],
    5 => ['name' => 'Frozen Meat and Fish', 'cat' => 'Frozen Foods', 'reason' => 'Fiesta gatherings drive bulk viand purchases.'],
    6 => ['name' => 'Biscuits and Crackers', 'cat' => 'Snacks and Biscuits', 'reason' => 'Back to school snacks surge in turnover.'],
    7 => ['name' => 'Instant Noodles', 'cat' => 'Noodles and Instant Food', 'reason' => 'Rainy weather pushes quick cook meals.'],
    8 => ['name' => 'Canned Goods and Rice', 'cat' => 'Staple Foods', 'reason' => 'Typhoon alerts raise preparedness buying.'],
    9 => ['name' => 'Spaghetti and Sauce', 'cat' => 'Condiments and Sauces', 'reason' => 'Households begin early Ber month planning.'],
    10 => ['name' => 'Candies and Snacks', 'cat' => 'Snacks and Biscuits', 'reason' => 'Halloween drives small pack sales.'],
    11 => ['name' => 'All Purpose Cream', 'cat' => 'Dairy Products', 'reason' => 'Holiday dessert planning increases demand.'],
    12 => ['name' => 'Soy Sauce, Oil and Ham', 'cat' => 'Condiments and Sauces', 'reason' => 'Christmas cooking peaks across all regions.'],
];

$quarterlyDefaults = [
    1 => ['name' => 'Century Tuna Flakes', 'cat' => 'Canned Goods', 'reason' => 'Households tighten budgets after holidays.'],
    2 => ['name' => 'Coca-Cola 330ml', 'cat' => 'Beverages', 'reason' => 'Peak summer temperatures drive turnover.'],
    3 => ['name' => 'Nescafé Classic', 'cat' => 'Beverages', 'reason' => 'Rainy season onset increases coffee consumption.'],
    4 => ['name' => 'Royal Pasta 500g', 'cat' => 'Dry Goods', 'reason' => 'Critical demand spike for Noche Buena.'],
];

$semiAnnualDefaults = [
    1 => ['name' => 'Canned Goods and Rice', 'cat' => 'Staples', 'reason' => 'First half spending leans toward budgeting.'],
    2 => ['name' => 'Holiday Goods and Pasta', 'cat' => 'Dry Goods', 'reason' => 'Second half moves toward holiday buildup.'],
];

$annualDefaults = [
    1 => ['name' => 'Rice, Canned Goods', 'cat' => 'Staples', 'reason' => 'These remain year round essentials.'],
];

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
        AVG(p.quantity) AS avg_stock, COUNT(CASE WHEN p.quantity < p.reorder_point THEN 1 END) AS low_stock_count,
        (SELECT COUNT(*) FROM transactions t JOIN products p2 ON t.product_id = p2.product_id
        WHERE p2.category = p.category AND t.transaction_type = 'stock_out') AS monthly_sales
        FROM products p
        WHERE p.category IS NOT NULL AND p.category != ''
        GROUP BY p.category ORDER BY monthly_sales DESC";
    $result = $conn->query($query);
    $categories = [];
    while ($row = $result->fetch_assoc()) { $categories[] = $row; }
    return $categories;
}

function getProductMovementAnalysis($conn) {
    $query = "SELECT p.product_id, p.product_name, p.category AS category_name, p.quantity AS current_stock, p.reorder_point,
        (SELECT SUM(quantity) FROM transactions WHERE product_id = p.product_id AND transaction_type = 'stock_out') AS monthly_sales
        FROM products p ORDER BY monthly_sales DESC";
    
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
        COUNT(CASE WHEN p.quantity < p.reorder_point THEN 1 END) as low_stock_count
        FROM products p WHERE p.supplier IS NOT NULL AND p.supplier != ''
        GROUP BY p.supplier ORDER BY monthly_sales DESC";
    $result = $conn->query($query);
    $suppliers = [];
    while ($row = $result->fetch_assoc()) { $suppliers[] = $row; }
    return $suppliers;
}

function getExpiryRiskAnalysis($conn) {
    $query = "SELECT p.product_id, p.product_name, p.category AS category_name, p.quantity, p.expiry_date,
        DATEDIFF(p.expiry_date, CURDATE()) AS days_until_expiry,
        (SELECT SUM(quantity) FROM transactions WHERE product_id = p.product_id AND transaction_type = 'stock_out') AS monthly_sales
        FROM products p
        WHERE p.expiry_date IS NOT NULL AND p.expiry_date > CURDATE()
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

$categoryPerformance = getCategoryPerformance($conn);
$productMovement = getProductMovementAnalysis($conn);
$salesTrends = getSalesTrends($conn, $daysLookback);
$supplierPerformance = getSupplierPerformance($conn);
$expiryRiskAnalysis = getExpiryRiskAnalysis($conn);

$totalProducts = count($productMovement);
$attentionNeededCount = count(array_filter($productMovement, function($p) { 
    return $p['stock_status'] === 'low_stock' || $p['stock_status'] === 'out_of_stock'; 
}));
$fastMovingCount = count(array_filter($productMovement, function($p) { 
    return $p['movement_class'] === 'fast_moving'; 
}));
$expiringSoonCount = count(array_filter($expiryRiskAnalysis, function($e) { 
    return $e['expiry_risk'] === 'high'; 
}));

$forecastPeriod = $_GET['forecast_period'] ?? 'monthly';
$selectedQuarter = isset($_GET['selected_quarter']) ? intval($_GET['selected_quarter']) : ceil(date('n')/3);
$selectedSemester = isset($_GET['selected_semester']) ? intval($_GET['selected_semester']) : (date('n') <= 6 ? 1 : 2);

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

if (isset($conn)) {
    $query = "
        SELECT 
            p.product_id,
            p.product_name,
            p.quantity as current_stock,
            p.category AS category_name,
            SUM(t.quantity) AS total_historical_sales
        FROM transactions t
        JOIN products p ON t.product_id = p.product_id
        WHERE $sqlTimeFilter
        AND t.transaction_type = 'stock_out'
        GROUP BY p.product_id
        ORDER BY total_historical_sales DESC
        LIMIT 5
    ";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            
            $stock = $row['current_stock'];
            $demand = $row['total_historical_sales'];
            $coverage = ($demand > 0) ? ($stock / $demand) * 100 : 100;

            if ($stock == 0 && $demand > 0) {
                $row['type'] = 'Critical Outage';
                $row['insight'] = "I noticed you have <strong>0 stock</strong> against a demand of $demand units. I strongly advise you to restock to avoid missed sales.";
                $row['action_needed'] = 'urgent';
            } elseif ($coverage < 50) {
                $row['type'] = 'High Risk';
                $row['insight'] = "I calculated that your stock ($stock) only covers " . round($coverage) . "% of expected demand ($demand). I recommend ordering more.";
                $row['action_needed'] = 'restock';
            } elseif ($coverage < 100) {
                $row['type'] = 'Low Cover';
                $row['insight'] = "I'm tracking tight inventory here. Demand ($demand) slightly exceeds your stock ($stock). I suggest planning replenishment soon.";
                $row['action_needed'] = 'monitor';
            } elseif ($coverage > 300) {
                $row['type'] = 'Slow Moving';
                $row['insight'] = "I see " . round($coverage) . "% coverage. Your stock ($stock) far exceeds demand ($demand). I advise pausing orders.";
                $row['action_needed'] = 'pause';
            } else {
                $row['type'] = 'Optimal';
                $row['insight'] = "I confirm your inventory ($stock) aligns perfectly with my demand projection ($demand). Great job.";
                $row['action_needed'] = 'none';
            }
            
            $forecastData[] = $row;
        }
    }
}

if (empty($forecastData)) {
    $defaultItem = null;
    
    switch ($forecastPeriod) {
        case 'weekly': 
            $m = $currentMonthStr ?? 'Jan';
            $w = $currentKey ?? 1;
            $defaultItem = $weeklyDefaults[$m][$w] ?? $weeklyDefaults['Jan'][1]; 
            break;
        case 'monthly': 
            $defaultItem = $monthlyDefaults[$currentKey] ?? $monthlyDefaults[1]; 
            break;
        case 'quarterly': 
            $defaultItem = $quarterlyDefaults[$currentKey] ?? $quarterlyDefaults[1]; 
            break;
        case 'semi-annual': 
            $defaultItem = $semiAnnualDefaults[$currentKey] ?? $semiAnnualDefaults[1]; 
            break;
        case 'annual': 
            $defaultItem = $annualDefaults[$currentKey] ?? $annualDefaults[1]; 
            break;
        default: 
            $defaultItem = $monthlyDefaults[$currentKey] ?? $monthlyDefaults[1];
    }

    if ($defaultItem) {
        $forecastData[] = [
            'product_name' => $defaultItem['name'],
            'category_name' => $defaultItem['cat'],
            'insight' => "Based on my market trend data, I predict a surge because: " . strtolower($defaultItem['reason']),
            'type' => 'System Advisory',
            'action_needed' => 'advisory'
        ];
    }
}

$highlightProductId = null;
if (isset($_GET['product_id']) && (int)$_GET['product_id'] > 0) {
    $highlightProductId = (int)$_GET['product_id'];
} elseif (!empty($_GET['barcode']) && isset($conn)) {
    $barcodeLookup = trim($_GET['barcode']);
    $barcodeStmt = $conn->prepare("SELECT product_id FROM products WHERE barcode = ? LIMIT 1");
    if ($barcodeStmt) {
        $barcodeStmt->bind_param("s", $barcodeLookup);
        $barcodeStmt->execute();
        $barcodeResult = $barcodeStmt->get_result();
        if ($barcodeRow = $barcodeResult->fetch_assoc()) {
            $highlightProductId = (int)$barcodeRow['product_id'];
        }
        $barcodeStmt->close();
    }
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
            SELECT product_id, product_name, quantity AS current_stock, category AS category_name
            FROM products WHERE product_id = ?
        ");
        if ($productStmt) {
            $productStmt->bind_param("i", $highlightProductId);
            $productStmt->execute();
            $productResult = $productStmt->get_result();
            if ($productRow = $productResult->fetch_assoc()) {
                $stock = (int)$productRow['current_stock'];
                $productRow['total_historical_sales'] = 0;
                $productRow['type'] = $stock <= 0 ? 'Critical Outage' : 'Product Focus';
                $productRow['insight'] = 'Viewing product selected from dashboard. Review stock levels and demand forecast below.';
                $productRow['action_needed'] = $stock <= 0 ? 'urgent' : 'monitor';
                $productRow['highlight'] = true;
                array_unshift($forecastData, $productRow);
            }
            $productStmt->close();
        }
    }
}

// --- ML Forecast Data (read-only; never trains on page load) ---
require_once 'src/ml/ForecastDataService.php';
require_once 'src/ml/MLForecastManager.php';

$forecastService = new ForecastDataService($conn);
$mlManager = new MLForecastManager();

$hasMlModel = $forecastService->hasTrainedModel();
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

$conn->close();

require_once 'src/layout.php';
renderLayoutStart('Data Mining & Analytics', $role, $_SESSION['Username']);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@100..900&display=swap" rel="stylesheet">

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
    --insights-bg: #C4D0DE;
    --insights-surface: linear-gradient(160deg, #E6EDF5 0%, #D6E2EE 100%);
    --insights-surface-alt: #D2DCE8;
    --insights-border: #7A92A8;
    --insights-border-soft: #92A5B8;
    --insights-text: #1E293B;
    --insights-text-muted: #4A5D72;
    --insights-heading: #0F172A;
    --insights-input: #D8E2ED;
    --insights-input-border: #7A92A8;
    --insights-table-head: #C8D5E3;
    --insights-row-hover: rgba(59, 130, 246, 0.09);
    --insights-shadow: 0 2px 6px rgba(55, 75, 99, 0.08), 0 6px 18px rgba(55, 75, 99, 0.1), 0 0 0 1px rgba(107, 131, 153, 0.35);
    --insights-banner: linear-gradient(135deg, #D2DCE8 0%, #C4D0DE 100%);
    --insights-accent: #16a34a;
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
    font-family: 'Onest', sans-serif;
    font-size: 14px;
    outline: none;
    transition: all 0.3s;
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
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: var(--insights-shadow);
}
.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 34px rgba(81, 213, 90, 0.12);
    border-color: rgba(81, 213, 90, 0.4);
}
[data-theme="light"] .metric-card:hover {
    box-shadow: 0 8px 22px rgba(55, 75, 99, 0.12), 0 0 0 1px var(--insights-border);
    border-color: var(--insights-border);
}
[data-theme="light"] .metric-card.active {
    border-color: var(--insights-accent);
    box-shadow: 0 8px 22px rgba(22, 163, 74, 0.12), 0 0 0 1px rgba(22, 163, 74, 0.25);
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
.badge.fast_moving, .badge.normal, .badge.low { background: rgba(22, 163, 74, 0.14); color: #15803d; border: 1px solid rgba(22, 163, 74, 0.2); }
.badge.medium_moving, .badge.low_stock, .badge.medium { background: rgba(245, 158, 11, 0.16); color: #c2410c; border: 1px solid rgba(245, 158, 11, 0.22); }
.badge.slow_moving, .badge.overstocked { background: rgba(59, 130, 246, 0.14); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.2); }
.badge.non_moving, .badge.out_of_stock, .badge.high { background: rgba(248, 113, 113, 0.16); color: #dc2626; border: 1px solid rgba(248, 113, 113, 0.22); }

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
    background: var(--insights-surface);
    color: var(--insights-text);
    border: 1px solid var(--insights-border);
    box-shadow: none;
}
.btn-secondary:hover {
    background: var(--insights-surface-alt);
    color: var(--insights-text);
    box-shadow: none;
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
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(6px);
    display: flex; justify-content: center; align-items: center;
    border-radius: 16px; z-index: 10; opacity: 0; pointer-events: none; transition: opacity 0.3s;
}
.forecast-card.loading .loading-overlay { opacity: 1; pointer-events: all; }
.spinner {
    width: 40px; height: 40px; border: 3px solid rgba(148, 163, 184, 0.25);
    border-top: 3px solid var(--insights-accent); border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

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

.forecast-nav-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--insights-border); padding-bottom: 12px; align-items: center; }
.forecast-nav-btn {
    color: var(--insights-text-muted); text-decoration: none; font-size: 13px; padding: 8px 16px; border-radius: 999px;
    transition: all 0.2s; background: var(--insights-surface-alt); border: 1px solid transparent; cursor: pointer;
}
.forecast-nav-btn:hover { background: var(--insights-surface); color: var(--insights-heading); border-color: var(--insights-border); }
.forecast-nav-btn.active {
    background: rgba(81, 213, 90, 0.12);
    color: var(--insights-accent); border: 1px solid rgba(81, 213, 90, 0.28); font-weight: 700;
}

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
.action-btn {
    display: inline-block; padding: 8px 16px; color: var(--insights-accent); border: 1px solid rgba(81, 213, 90, 0.28);
    border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: 600;
    transition: 0.2s; white-space: nowrap; cursor: pointer; background: rgba(81, 213, 90, 0.08);
}
.action-btn:hover { background: var(--insights-accent); color: #ffffff; box-shadow: 0 10px 20px rgba(81, 213, 90, 0.18); }
.forecast-row--highlighted { background: rgba(81, 213, 90, 0.1) !important; }
.forecast-row--highlighted td { border-bottom-color: rgba(81, 213, 90, 0.25) !important; }
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
</style>

<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 10px;">
        <div>
            <div style="color: #E3E3E3; font-weight: 600; font-size: 26px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                <span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg></span>
                Data Mining & Analytics
            </div>
            <div style="color: #9aa6d1; font-size: 14px; margin-bottom: 20px;">
                Advanced analytics and insights powered by data mining algorithms to optimize your warehouse operations.
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
             <button class="btn" onclick="refreshAnalysis()">Refresh Analysis</button>
             <button class="btn btn-secondary" onclick="exportData()">Export View</button>
        </div>
    </div>
    
    <div class="grid-4">
        <div class="metric-card" onclick="filterData('all')" data-filter="all">
            <div style="color: #51D55A; font-weight: 700; font-size: 32px; margin-bottom: 4px;"><?php echo $totalProducts; ?></div>
            <div style="color: #9aa6d1; font-size: 13px; font-weight: 600; text-transform: uppercase;">Total Products</div>
        </div>
        <div class="metric-card" onclick="filterData('attention_needed')" data-filter="attention_needed">
            <div style="color: #f59e0b; font-weight: 700; font-size: 32px; margin-bottom: 4px;"><?php echo $attentionNeededCount; ?></div>
            <div style="color: #9aa6d1; font-size: 13px; font-weight: 600; text-transform: uppercase;">Low & Out of Stock</div>
        </div>
        <div class="metric-card" onclick="filterData('fast_moving')" data-filter="fast_moving">
            <div style="color: #10b981; font-weight: 700; font-size: 32px; margin-bottom: 4px;"><?php echo $fastMovingCount; ?></div>
            <div style="color: #9aa6d1; font-size: 13px; font-weight: 600; text-transform: uppercase;">Fast Moving</div>
        </div>
        <div class="metric-card" onclick="filterData('expiring')" data-filter="expiring">
            <div style="color: #ef4444; font-weight: 700; font-size: 32px; margin-bottom: 4px;"><?php echo $expiringSoonCount; ?></div>
            <div style="color: #9aa6d1; font-size: 13px; font-weight: 600; text-transform: uppercase;">Expiring Soon</div>
        </div>
    </div>
</div>

<div class="tab-container">
    <div class="tab active" data-tab="product-analysis">Product Analysis</div>
    <div class="tab" data-tab="category-performance">Category Performance</div>
    <div class="tab" data-tab="sales-trends">Sales Trends</div>
    <div class="tab" data-tab="supplier-analysis">Supplier Analysis</div>
    <div class="tab" data-tab="expiry-risk">Expiry Risk</div>
</div>

<div class="tab-content active" id="product-analysis">
    <div class="card">
        <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Product Movement Analysis</div>
        <div class="search-filter-container">
            <input type="text" id="productSearch" class="search-box" placeholder="Search products...">
            <select id="categoryFilter" class="filter-select">
                <option value="">All Categories</option>
                <?php
                $categories = array_unique(array_column($productMovement, 'category_name'));
                foreach ($categories as $category) {
                    if (!empty($category)) { echo "<option value=\"$category\">$category</option>"; }
                }
                ?>
            </select>
            <select id="movementFilter" class="filter-select">
                <option value="">All Movement Types</option>
                <option value="fast_moving">Fast Moving</option>
                <option value="medium_moving">Medium Moving</option>
                <option value="slow_moving">Slow Moving</option>
                <option value="non_moving">Non Moving</option>
            </select>
            <select id="stockFilter" class="filter-select">
                <option value="">All Stock Status</option>
                <option value="attention_needed">Low & Out of Stock</option>
                <option value="normal">Normal</option>
                <option value="low_stock">Low Stock</option>
                <option value="out_of_stock">Out of Stock</option>
                <option value="overstocked">Overstocked</option>
            </select>
        </div>
        <div style="overflow: auto; max-height: 500px;">
            <table class="table" id="productTable">
                <thead>
                    <tr>
                        <th>Product</th><th>Category</th><th>Current Stock</th><th>Monthly Sales</th>
                        <th>Daily Velocity</th><th>Days Supply</th><th>Movement</th><th>Status</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php foreach ($productMovement as $product): ?>
                    <tr data-category="<?php echo htmlspecialchars($product['category_name']); ?>"
                        data-movement="<?php echo $product['movement_class']; ?>"
                        data-stock="<?php echo $product['stock_status']; ?>">
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
    </div>
</div>

<div class="tab-content" id="category-performance">
    <div class="grid-2">
        <div class="card">
            <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Category Performance</div>
            <div style="overflow: auto; max-height: 500px;">
                <table class="table">
                    <thead><tr><th>Category</th><th>Products</th><th>Total Stock</th><th>Avg Stock</th><th>Low Stock</th><th>Sales</th></tr></thead>
                    <tbody>
                        <?php foreach ($categoryPerformance as $category): ?>
                        <tr>
                            <td style="color: #fff; font-weight: 500;"><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td><?php echo $category['product_count']; ?></td>
                            <td><?php echo $category['total_stock']; ?></td>
                            <td><?php echo number_format($category['avg_stock'], 1); ?></td>
                            <td><span class="badge <?php echo $category['low_stock_count'] > 0 ? 'medium' : 'low'; ?>"><?php echo $category['low_stock_count']; ?></span></td>
                            <td><?php echo $category['monthly_sales'] ?? 0; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                <table class="table">
                    <thead><tr><th>Date</th><th>Day</th><th>Products Sold</th><th>Total Qty</th><th>Avg/Trans</th></tr></thead>
                    <tbody>
                        <?php foreach ($salesTrends as $trend): ?>
                        <tr>
                            <td style="color: #fff;"><?php echo $trend['sale_date']; ?></td>
                            <td style="color: #9aa6d1;"><?php echo $trend['day_name']; ?></td>
                            <td><?php echo $trend['unique_products_sold']; ?></td>
                            <td><?php echo $trend['total_quantity_sold']; ?></td>
                            <td><?php echo number_format($trend['avg_quantity_per_transaction'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
            <table class="table">
                <thead><tr><th>Supplier</th><th>Products</th><th>Total Stock</th><th>Avg Stock</th><th>Monthly Sales</th><th>Low Stock</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($supplierPerformance as $supplier): ?>
                    <tr>
                        <td style="color:#fff; font-weight:500;"><?php echo htmlspecialchars($supplier['supplier']); ?></td>
                        <td><?php echo $supplier['product_count']; ?></td>
                        <td><?php echo $supplier['total_stock_value']; ?></td>
                        <td><?php echo number_format($supplier['avg_stock_per_product'], 1); ?></td>
                        <td><?php echo $supplier['monthly_sales'] ?? 0; ?></td>
                        <td><span class="badge <?php echo $supplier['low_stock_count'] > 0 ? 'medium' : 'low'; ?>"><?php echo $supplier['low_stock_count']; ?></span></td>
                        <td>
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
        </div>
    </div>
</div>

<div class="tab-content" id="expiry-risk">
    <div class="card">
        <div style="color: #E3E3E3; font-weight: 600; font-size: 18px; margin-bottom: 16px;">Expiry Risk Analysis</div>
        <div style="overflow: auto; max-height: 500px;">
            <table class="table">
                <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Expiry Date</th><th>Days Left</th><th>Sales</th><th>Sell Through</th><th>Risk</th></tr></thead>
                <tbody>
                    <?php foreach ($expiryRiskAnalysis as $item): ?>
                    <tr>
                        <td style="color:#fff; font-weight:500;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td style="color:#9aa6d1;"><?php echo htmlspecialchars($item['category_name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo $item['expiry_date']; ?></td>
                        <td style="font-weight:700; color: <?php echo ($item['days_until_expiry'] < 30 ? '#f87171' : '#E3E3E3'); ?>;"><?php echo $item['days_until_expiry']; ?></td>
                        <td><?php echo $item['monthly_sales'] ?? 0; ?></td>
                        <td><?php echo $item['sell_through_days']; ?></td>
                        <td><span class="badge <?php echo $item['expiry_risk']; ?>"><?php echo ucfirst($item['expiry_risk']); ?> Risk</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="forecast-card" id="forecastWidget">
    <div class="loading-overlay"><div class="spinner"></div></div>
    
    <div style="color: #E3E3E3; font-weight: 600; font-size: 20px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.318 11.318 0 0 1 5.814 5.814L21.75 18M2.25 18V15a2.25 2.25 0 0 1 2.25-2.25h1.5M2.25 18h16.5"/></svg></span>
        Demand Forecasting
    </div>

    <div class="forecast-nav-bar">
        <?php 
        $periods = [
            'weekly' => 'Weekly', 
            'monthly' => 'Monthly', 
            'quarterly' => 'Quarterly', 
            'semi-annual' => 'Semi-Annual', 
            'annual' => 'Annual'
        ];
        foreach($periods as $key => $label): 
            $isActive = ($forecastPeriod === $key) ? 'active' : '';
            $url = "?forecast_period=$key"; 
        ?>
            <a href="<?php echo $url; ?>" class="forecast-nav-btn <?php echo $isActive; ?>" onclick="loadForecastContent(event, this.href)"><?php echo $label; ?></a>
        <?php endforeach; ?>

        <?php if ($forecastPeriod === 'quarterly'): ?>
            <form action="" method="GET" style="display:inline;">
                <input type="hidden" name="forecast_period" value="quarterly">
                <select name="selected_quarter" class="dropdown-select" onchange="loadForecastContent(event, '?forecast_period=quarterly&selected_quarter='+this.value)">
                    <option value="1" <?php echo $selectedQuarter == 1 ? 'selected' : ''; ?>>Quarter 1 (Jan-Mar)</option>
                    <option value="2" <?php echo $selectedQuarter == 2 ? 'selected' : ''; ?>>Quarter 2 (Apr-Jun)</option>
                    <option value="3" <?php echo $selectedQuarter == 3 ? 'selected' : ''; ?>>Quarter 3 (Jul-Sep)</option>
                    <option value="4" <?php echo $selectedQuarter == 4 ? 'selected' : ''; ?>>Quarter 4 (Oct-Dec)</option>
                </select>
            </form>
        <?php endif; ?>

        <?php if ($forecastPeriod === 'semi-annual'): ?>
            <form action="" method="GET" style="display:inline;">
                <input type="hidden" name="forecast_period" value="semi-annual">
                <select name="selected_semester" class="dropdown-select" onchange="loadForecastContent(event, '?forecast_period=semi-annual&selected_semester='+this.value)">
                    <option value="1" <?php echo $selectedSemester == 1 ? 'selected' : ''; ?>>1st Semester (Jan-Jun)</option>
                    <option value="2" <?php echo $selectedSemester == 2 ? 'selected' : ''; ?>>2nd Semester (Jul-Dec)</option>
                </select>
            </form>
        <?php endif; ?>
    </div>

    <div class="smart-restock-banner">
        <div class="bg-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg></div>
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; position: relative; z-index: 2;">
            <div>
                <div style="color: #51D55A; font-weight: 700; font-size: 22px; display: flex; align-items: center; gap: 8px;">
                    <span class="section-heading-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg></span>
                    Smart Restock: <?php echo $periodTitle; ?>
                </div>
                <div style="color: #9aa6d1; font-size: 13px; margin-top: 6px;">
                      Forecast insights suggestions for next period based on 
                <strong style="color: #fff; border-bottom: 1px dotted #fff;"><?php echo strtolower($castData[0]['type'] ?? 'market trends'); ?></strong>.
                </div>
            </div>
            
            <div class="tooltip-container">
                <div class="info-circle">?</div>
                <div class="tooltip-text">
                    <strong>How this works:</strong><br><br>
                    I analyze transaction data from the <strong>same time period in previous years</strong> to predict upcoming demand spikes.
                </div>
            </div>
        </div>

        <div style="overflow-x: auto; position: relative; z-index: 2;">
            <table class="forecast-table">
                <thead>
                    <tr>
                        <th width="30%">Top Product / Category</th>
                        <th width="50%">Smart Insight</th>
                        <th width="20%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forecastData as $item): ?>
                    <tr class="<?php echo !empty($item['highlight']) ? 'forecast-row--highlighted' : ''; ?>"<?php echo !empty($item['highlight']) ? ' id="highlighted-product-row"' : ''; ?>>
                        <td>
                            <div class="product-title"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="product-sub"><?php echo htmlspecialchars($item['category_name']); ?></div>
                            <?php 
                                $badgeColor = '#9aa6d1'; 
                                if(strpos($item['type'], 'Critical') !== false) $badgeColor = '#ef4444';
                                elseif(strpos($item['type'], 'Risk') !== false) $badgeColor = '#f59e0b';
                                elseif(strpos($item['type'], 'Optimal') !== false) $badgeColor = '#10b981';
                            ?>
                            <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600; margin-top:4px; background-color: <?php echo $badgeColor; ?>20; color: <?php echo $badgeColor; ?>;">
                                <?php echo htmlspecialchars($item['type']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="insight-text"><?php echo $item['insight']; ?></div>
                        </td>
                        <td>
                            <button type="button" class="action-btn" 
                                onclick="checkStock('<?php echo htmlspecialchars($item['product_name']); ?>', '<?php echo htmlspecialchars($item['category_name']); ?>')">
                                Check Stock
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                $highPriority = array_filter($productMovement, function($p) {
                    return $p['movement_class'] === 'fast_moving' && $p['stock_status'] === 'low_stock' && $p['days_of_supply'] < 7;
                });
                echo count($highPriority) . ' products need immediate attention';
                ?>
            </div>
        </div>
        <div class="recommendation-card warning">
            <div class="card-label">Seasonal Stock Build-up</div>
            <div class="card-copy">Prepare for increased demand in Beverages and Dairy categories</div>
        </div>
        <div class="recommendation-card info">
            <div class="card-label">Optimize Inventory</div>
            <div class="card-copy">
                <?php
                $overstocked = array_filter($productMovement, function($p) { return $p['stock_status'] === 'overstocked'; });
                echo count($overstocked) . ' overstocked items identified';
                ?>
            </div>
        </div>
        <div class="recommendation-card purple">
            <div class="card-label">Promotional Opportunities</div>
            <div class="card-copy">
                <?php
                $slowMoving = array_filter($productMovement, function($p) { return $p['movement_class'] === 'slow_moving' && $p['current_stock'] > 20; });
                echo count($slowMoving) . ' slow-moving products for promotions';
                ?>
            </div>
        </div>
    </div>
</div>

<form id="stockCheckForm" action="transactions.php" method="POST" style="display:none;">
    <input type="hidden" name="filter_product_name" id="filter_product_name">
    <input type="hidden" name="filter_category" id="filter_category">
    <input type="hidden" name="smart_insight_referral" value="true"> </form>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
Chart.defaults.color = chartTheme === 'light' ? '#64748b' : '#9aa6d1';
Chart.defaults.borderColor = chartTheme === 'light' ? 'rgba(148, 163, 184, 0.35)' : 'rgba(42, 68, 132, 0.3)';
Chart.defaults.font.family = "'Onest', sans-serif";
Chart.defaults.font.size = 12;

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

<?php if ($highlightProductId): ?>
document.addEventListener('DOMContentLoaded', function() {
    const row = document.getElementById('highlighted-product-row');
    if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
<?php endif; ?>

function checkStock(productName, categoryName) {
    document.getElementById('filter_product_name').value = productName;
    document.getElementById('filter_category').value = categoryName;
    document.getElementById('stockCheckForm').submit();
}

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
    const ctx = document.getElementById('demandHeatmapChart').getContext('2d');

    let productNames, forecastDemand;

    if (hasMlModel && heatmapForecastData.available) {
        productNames = heatmapForecastData.labels.map(n => n.length > 15 ? n.substring(0, 15) + '...' : n);
        forecastDemand = heatmapForecastData.demand;
    } else {
        productNames = topProductsData.map(p => p.product_name.length > 15 ? p.product_name.substring(0, 15) + '...' : p.product_name);
        forecastDemand = topProductsData.map(p => p.monthly_sales || 0);
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: productNames,
            datasets: [{
                label: 'Forecasted Demand',
                data: forecastDemand,
                backgroundColor: forecastDemand.map(d => d > 50 ? '#ef4444' : d > 25 ? '#f59e0b' : d > 10 ? '#3b82f6' : '#51D55A'),
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
    if (!canvas || !depletionTrendData.available) return;

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

document.getElementById('productSearch').addEventListener('input', filterProductTable);
document.getElementById('categoryFilter').addEventListener('change', filterProductTable);
document.getElementById('movementFilter').addEventListener('change', filterProductTable);
document.getElementById('stockFilter').addEventListener('change', filterProductTable);

function filterProductTable() {
    const searchTerm = document.getElementById('productSearch').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const movementFilter = document.getElementById('movementFilter').value;
    const stockFilter = document.getElementById('stockFilter').value;
    
    document.querySelectorAll('#productTableBody tr').forEach(row => {
        const productName = row.cells[0].textContent.toLowerCase();
        const category = row.getAttribute('data-category');
        const movement = row.getAttribute('data-movement');
        const stock = row.getAttribute('data-stock');
        
        const matchesSearch = productName.includes(searchTerm);
        const matchesCategory = !categoryFilter || category === categoryFilter;
        const matchesMovement = !movementFilter || movement === movementFilter;
        const matchesStock = !stockFilter || (stockFilter === 'attention_needed' && (stock === 'low_stock' || stock === 'out_of_stock')) || stock === stockFilter;
        
        row.style.display = (matchesSearch && matchesCategory && matchesMovement && matchesStock) ? '' : 'none';
    });
}

function filterData(filterType) {
    document.querySelectorAll('.metric-card').forEach(card => card.classList.remove('active'));
    document.querySelector(`.metric-card[data-filter="${filterType}"]`).classList.add('active');
    
    switch(filterType) {
        case 'attention_needed': 
            document.querySelector('.tab[data-tab="product-analysis"]').click();
            document.getElementById('stockFilter').value = 'attention_needed';
            filterProductTable();
            break;
        case 'fast_moving':
            document.querySelector('.tab[data-tab="product-analysis"]').click();
            document.getElementById('movementFilter').value = 'fast_moving';
            filterProductTable();
            break;
        case 'expiring':
            document.querySelector('.tab[data-tab="expiry-risk"]').click();
            break;
        default:
            document.querySelector('.tab[data-tab="product-analysis"]').click();
            document.getElementById('stockFilter').value = '';
            document.getElementById('movementFilter').value = '';
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
            document.querySelectorAll('#productTableBody tr:not([style*="display: none"])').forEach(row => {
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
        if (e.key === 'Escape' && activeTooltip) { activeTooltip.classList.remove('show'); overlay.classList.remove('show'); activeTooltip = null; }
    });
});
</script>

<?php
renderLayoutEnd();
?>