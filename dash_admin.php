<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }

require_once 'src/layout.php';
require_once 'config/db.php';

// Fetch categories and UoMs for the Add Product modal
$dash_categories = [];
$cat_q = $conn->query("SELECT DISTINCT category FROM products WHERE category != '' ORDER BY category");
while ($row = $cat_q->fetch_assoc()) { $dash_categories[] = $row['category']; }
sort($dash_categories);

$dash_uoms = [];
$uom_q = $conn->query("SELECT DISTINCT unit FROM products WHERE unit != '' ORDER BY unit");
while ($row = $uom_q->fetch_assoc()) { $dash_uoms[] = $row['unit']; }
$defaultUoms = ['pieces', 'packs', 'boxes', 'bottles', 'cans', 'bags', 'jars', 'tubes', 'bars', 'sets', 'units', 'kg', 'g', 'L', 'ml'];
$dash_uoms = array_values(array_unique(array_merge($dash_uoms, $defaultUoms)));
sort($dash_uoms, SORT_NATURAL | SORT_FLAG_CASE);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$dash_csrf_token = $_SESSION['csrf_token'];


// 1. Fetch Products
$items = [];
$productQuery = "SELECT product_id as id, product_name as name, category, quantity as qty, reorder_point FROM products";
$productResult = $conn->query($productQuery);

if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $items[] = $row;
    }
}

// 2. User Info
$userQuery = "SELECT username, role, full_name FROM users WHERE username = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $_SESSION['Username']);
$userStmt->execute();
$userInfo = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// 3. Calculate Metrics
$lowStockItems = array_filter($items, function($i){ 
    return (int)$i['qty'] <= (int)($i['reorder_point'] ?? 10); 
});
$lowStockCount = count($lowStockItems);
$totalSKUs = count($items);
$totalQty = array_sum(array_map(function($i){return (int)$i['qty'];}, $items));
$totalCategories = count(array_unique(array_map(function($i){return $i['category'];}, $items)));

// 4. Recent Transactions — use index on transaction_date
$recentTransactions = [];
$transQuery = "SELECT t.transaction_type, t.quantity, t.transaction_date, p.product_name, COALESCE(u.username, 'System') as username
               FROM transactions t
               STRAIGHT_JOIN products p ON t.product_id = p.product_id
               LEFT JOIN users u ON t.user_id = u.user_id
               ORDER BY t.transaction_date DESC LIMIT 5";
$transResult = $conn->query($transQuery);
if ($transResult && $transResult->num_rows > 0) {
    while ($row = $transResult->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}

// 5. Chart Data
$chartData = ['labels' => [], 'stock_in' => [], 'stock_out' => []];
$tempData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartData['labels'][] = date('M j', strtotime("-$i days"));
    $tempData[$date] = ['stock_in' => 0, 'stock_out' => 0];
}

$sevenDaysAgo = date('Y-m-d', strtotime('-6 days')); 
$chartQuery = $conn->prepare("SELECT DATE(transaction_date) as tx_date, transaction_type, COALESCE(SUM(quantity), 0) as total_quantity 
                              FROM transactions WHERE transaction_date >= ? GROUP BY tx_date, transaction_type");
$chartQuery->bind_param("s", $sevenDaysAgo);
$chartQuery->execute();
$chartResult = $chartQuery->get_result();

while ($row = $chartResult->fetch_assoc()) {
    if (isset($tempData[$row['tx_date']])) {
        $tempData[$row['tx_date']][$row['transaction_type']] = (int)$row['total_quantity'];
    }
}
$chartQuery->close();

foreach ($tempData as $dateData) {
    $chartData['stock_in'][] = $dateData['stock_in'];
    $chartData['stock_out'][] = $dateData['stock_out'];
}

// 6. Heatmap Data — limit to last 90 days, use covering index
$heatmapData = ['labels' => [], 'data' => [], 'ids' => []];
$heatNinetyDaysAgo = date('Y-m-d', strtotime('-90 days'));
$heatQuery = "SELECT p.product_id, p.product_name, SUM(t.quantity) as demand_qty
              FROM transactions t USE INDEX (idx_tx_covering_out)
              JOIN products p ON t.product_id = p.product_id
              WHERE t.transaction_type = 'stock_out' AND t.transaction_date >= '$heatNinetyDaysAgo'
              GROUP BY t.product_id ORDER BY demand_qty DESC LIMIT 7";
$heatResult = $conn->query($heatQuery);
while($row = $heatResult->fetch_assoc()) {
    $heatmapData['labels'][] = $row['product_name'];
    $heatmapData['data'][] = (int)$row['demand_qty'];
    $heatmapData['ids'][] = (int)$row['product_id'];
}

// 7. Hero Banner Data
$displayName = trim($userInfo['full_name'] ?? '') ?: $_SESSION['Username'];
$userRoleLabel = ucfirst($userInfo['role'] ?? $_SESSION['Role'] ?? 'admin');
$hour = (int)date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

if ($lowStockCount === 0) {
    $statusSummary = "All {$totalSKUs} SKUs across {$totalCategories} " . ($totalCategories === 1 ? 'category are' : 'categories are') . " well stocked.";
} else {
    $statusSummary = "You have {$lowStockCount} " . ($lowStockCount === 1 ? 'item' : 'items') . " low on stock across {$totalSKUs} SKUs in {$totalCategories} " . ($totalCategories === 1 ? 'category' : 'categories') . ".";
}

$priorityItem = null;
$priorityInsight = null;
$priorityType = null;
$priorityProductId = null;

$nextMonthNum = (int)date('n', strtotime('+1 month'));
$nextMonthYear = (int)date('Y', strtotime('+1 month'));
// Use date range instead of MONTH() so the index on transaction_date is used
$forecastDateStart = sprintf('%04d-%02d-01', $nextMonthYear, $nextMonthNum);
$forecastDateEnd   = date('Y-m-t', strtotime($forecastDateStart)); // last day of that month
$forecastPrioQuery = "
    SELECT product_id, product_name, current_stock, category_name, total_historical_sales
    FROM (
        SELECT p.product_id, p.product_name, p.quantity AS current_stock, p.category AS category_name,
               SUM(t.quantity) AS total_historical_sales
        FROM transactions t USE INDEX (idx_tx_covering_out)
        JOIN products p ON t.product_id = p.product_id
        WHERE t.transaction_type = 'stock_out'
          AND t.transaction_date >= DATE_SUB('$forecastDateStart', INTERVAL 1 YEAR)
          AND t.transaction_date <= '$forecastDateEnd'
          AND MONTH(t.transaction_date) = {$nextMonthNum}
        GROUP BY t.product_id
        HAVING SUM(t.quantity) > 0
    ) AS forecast_prio
    ORDER BY (current_stock / total_historical_sales) ASC
    LIMIT 1
";
$forecastPrioResult = $conn->query($forecastPrioQuery);

if ($forecastPrioResult && $forecastPrioResult->num_rows > 0) {
    $row = $forecastPrioResult->fetch_assoc();
    $stock = (int)$row['current_stock'];
    $demand = (int)$row['total_historical_sales'];
    $coverage = ($demand > 0) ? ($stock / $demand) * 100 : 100;

    $priorityItem = $row['product_name'];
    $priorityProductId = (int)$row['product_id'];

    if ($stock === 0 && $demand > 0) {
        $priorityType = 'Critical Outage';
        $priorityInsight = "I noticed you have <strong>0 stock</strong> against a demand of {$demand} units. I strongly advise you to restock to avoid missed sales.";
    } elseif ($coverage < 50) {
        $priorityType = 'High Risk';
        $priorityInsight = "I calculated that your stock ({$stock}) only covers " . round($coverage) . "% of expected demand ({$demand}). I recommend ordering more.";
    } elseif ($coverage < 100) {
        $priorityType = 'Low Cover';
        $priorityInsight = "I'm tracking tight inventory here. Demand ({$demand}) slightly exceeds your stock ({$stock}). I suggest planning replenishment soon.";
    } elseif ($coverage > 300) {
        $priorityType = 'Slow Moving';
        $priorityInsight = "I see " . round($coverage) . "% coverage. Your stock ({$stock}) far exceeds demand ({$demand}). I advise pausing orders.";
    } else {
        $priorityType = 'Optimal';
        $priorityInsight = "I confirm your inventory ({$stock}) aligns perfectly with my demand projection ({$demand}). Great job.";
    }
}

if (!$priorityItem && !empty($lowStockItems)) {
    $worstRatio = PHP_FLOAT_MAX;
    $worstItem = null;
    foreach ($lowStockItems as $item) {
        $reorderPoint = max((int)($item['reorder_point'] ?? 10), 1);
        $ratio = (int)$item['qty'] / $reorderPoint;
        if ($ratio < $worstRatio) {
            $worstRatio = $ratio;
            $worstItem = $item;
        }
    }
    if ($worstItem) {
        $priorityItem = $worstItem['name'];
        $priorityProductId = (int)$worstItem['id'];
        $qty = (int)$worstItem['qty'];
        $rp = (int)($worstItem['reorder_point'] ?? 10);
        $pct = round($worstRatio * 100);
        $priorityType = 'Low Stock Alert';
        $safeName = htmlspecialchars($worstItem['name'], ENT_QUOTES, 'UTF-8');
        $priorityInsight = "<strong>{$safeName}</strong> is critically low — only {$qty} units remaining ({$pct}% of reorder point {$rp}). Restock immediately.";
    }
}

$conn->close();

renderLayoutStart('System Dashboard', $_SESSION['Role'], $userInfo['full_name'] ?? $_SESSION['Username'], $lowStockCount);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="premium-card p-6 md:p-8 mb-6 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-[#1D387B]/40 via-transparent to-[#51D55A]/5 pointer-events-none"></div>
    <div class="relative z-10 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
        <div class="flex-1 min-w-0 space-y-4">
            <div>
                <div class="flex flex-wrap items-center gap-3 mb-1">
                    <h2 class="font-onest text-xl md:text-2xl font-semibold text-white">
                        <?php echo $greeting; ?>, <?php echo htmlspecialchars($displayName); ?>
                    </h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold font-onest uppercase tracking-wider bg-blue-500/15 text-blue-300 border border-blue-500/30">
                        <?php echo htmlspecialchars($userRoleLabel); ?>
                    </span>
                </div>
                <p class="text-[#9aa6d1] text-sm md:text-base font-onest"><?php echo htmlspecialchars($statusSummary); ?></p>
            </div>

            <?php if ($priorityItem && $priorityInsight && $priorityProductId): ?>
            <a href="insights.php?product_id=<?php echo (int)$priorityProductId; ?>"
               class="hero-product-card flex items-start gap-3 p-4 rounded-xl no-underline"
               style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35);"
               title="View insights for <?php echo htmlspecialchars($priorityItem); ?>">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5"
                     style="background: rgba(245, 158, 11, 0.2); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.35);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-[#E3E3E3] font-onest font-semibold text-sm group-hover:text-emerald-400 transition-colors"><?php echo htmlspecialchars($priorityItem); ?></span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold"
                              style="background: rgba(245, 158, 11, 0.2); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.35);">
                            <?php echo htmlspecialchars($priorityType); ?>
                        </span>
                        <svg class="w-3.5 h-3.5 text-[#9aa6d1] hero-product-arrow flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                    <p class="text-[#9aa6d1] text-sm leading-relaxed font-onest"><?php echo $priorityInsight; ?></p>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <div class="flex flex-col sm:flex-row lg:flex-col gap-3 flex-shrink-0">
            <a href="insights.php" class="premium-button whitespace-nowrap !py-3 !px-5 text-sm">
                View Full Forecast
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
            <a href="products.php?add=1" class="btn-secondary whitespace-nowrap !py-3 !px-5 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
                Add Product
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="premium-card p-6 flex items-center justify-between group hover:border-blue-500/50 cursor-pointer transition-all" onclick="window.location.href='products.php'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total SKUs</div>
            <div class="premium-metric">
                <span class="num text-3xl"><?php echo number_format($totalSKUs); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-blue-500/50 cursor-pointer transition-all" onclick="window.location.href='products.php'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total Items</div>
            <div class="premium-metric">
                <span class="num text-3xl"><?php echo number_format($totalQty); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-red-500/50 cursor-pointer transition-all" onclick="window.location.href='insights.php'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Low Stock Alert</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $lowStockCount; ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-amber-500/50 cursor-pointer transition-all" onclick="window.location.href='products.php'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Active Categories</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $totalCategories; ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-6 iw-dashboard-grid-swap">

    <div class="premium-card flex flex-col h-[400px] iw-quick-access">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Quick Access
            </h3>
        </div>
        <div class="flex-1 p-5 flex flex-col gap-4">
            <a href="receive.php" class="group flex items-center gap-4 p-4 rounded-xl bg-[#13275B]/50 border border-[#2A4484] hover:border-emerald-500/40 hover:bg-emerald-500/5 transition-all flex-1">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 group-hover:scale-105 transition-transform flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
                </div>
                <div class="min-w-0">
                    <div class="font-onest font-semibold text-[#E3E3E3] text-base group-hover:text-emerald-400 transition-colors">Receive Inventory</div>
                    <div class="text-[#9aa6d1] text-xs mt-1">Add incoming stock</div>
                </div>
                <svg class="w-5 h-5 text-[#9aa6d1] ml-auto group-hover:text-emerald-400 group-hover:translate-x-0.5 transition-all flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
            <a href="release.php" class="group flex items-center gap-4 p-4 rounded-xl bg-[#13275B]/50 border border-[#2A4484] hover:border-red-500/40 hover:bg-red-500/5 transition-all flex-1">
                <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-400 border border-red-500/20 group-hover:scale-105 transition-transform flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 12h-15"></path></svg>
                </div>
                <div class="min-w-0">
                    <div class="font-onest font-semibold text-[#E3E3E3] text-base group-hover:text-red-400 transition-colors">Release Inventory</div>
                    <div class="text-[#9aa6d1] text-xs mt-1">Record outgoing stock</div>
                </div>
                <svg class="w-5 h-5 text-[#9aa6d1] ml-auto group-hover:text-red-400 group-hover:translate-x-0.5 transition-all flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
    </div>

    <div class="premium-card flex flex-col h-[400px] iw-recent-activity">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Recent Activity
            </h3>
            <a href="transactions.php" class="text-xs text-[#51D55A] hover:text-[#4bc052] font-onest">View All &rarr;</a>
        </div>
        <div class="overflow-y-auto premium-scrollbar flex-1 p-2">
            <?php if (empty($recentTransactions)): ?>
                <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1]">
                    <span>No recent activity</span>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <tbody class="text-sm">
                        <?php foreach($recentTransactions as $tx): ?>
                        <tr class="border-b border-[#2A4484]/30 hover:bg-[#2A4484]/20 transition-colors">
                            <td class="p-3 w-10">
                                <?php if($tx['transaction_type'] === 'stock_in'): ?>
                                    <div class="w-8 h-8 rounded bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded bg-red-500/10 text-red-400 flex items-center justify-center border border-red-500/20">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <div class="text-[#E3E3E3] font-medium truncate max-w-[150px]"><?php echo htmlspecialchars($tx['product_name']); ?></div>
                                <div class="text-[#9aa6d1] text-xs">
                                    <?php echo $tx['transaction_type'] === 'stock_in' ? 'Restocked' : 'Dispensed'; ?> 
                                    <span class="text-white font-mono font-bold"><?php echo (int)$tx['quantity']; ?></span> units
                                </div>
                            </td>
                            <td class="p-3 text-right">
                                <div class="text-[#E3E3E3] text-xs"><?php echo htmlspecialchars($tx['username']); ?></div>
                                <div class="text-[#6b7280] text-[10px]"><?php echo date('M j, g:ia', strtotime($tx['transaction_date'])); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="premium-card flex flex-col h-[350px]">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                Critical Stock Levels
            </h3>
            <a href="products.php?filter=low_stock" class="text-xs text-[#51D55A] hover:text-[#4bc052] font-onest">Manage &rarr;</a>
        </div>
        <div class="overflow-y-auto premium-scrollbar flex-1 p-2">
            <?php if (empty($lowStockItems)): ?>
                <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1]">
                    <svg class="w-12 h-12 mb-2 text-emerald-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>All items well stocked!</span>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach(array_slice($lowStockItems, 0, 7) as $item): ?>
                    <?php $isOut = (int)$item['qty'] <= 0; ?>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-[#13275B]/50 border border-[#2A4484] <?php echo $isOut ? 'hover:border-red-500/30' : 'hover:border-amber-500/30'; ?> transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full <?php echo $isOut ? 'bg-red-500' : 'bg-amber-500'; ?> animate-pulse"></div>
                            <div>
                                <div class="text-[#E3E3E3] text-sm font-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="text-[#9aa6d1] text-xs">Reorder Point: <?php echo $item['reorder_point']; ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="<?php echo $isOut ? 'text-red-400' : 'text-amber-400'; ?> font-bold font-mono text-lg"><?php echo (int)$item['qty']; ?></div>
                            <div class="text-[#9aa6d1] text-[10px]">Remaining</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($lowStockCount > 7): ?>
                    <div class="text-center py-2 text-xs text-[#9aa6d1]">
                        ...and <?php echo $lowStockCount - 7; ?> more items
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="premium-card flex flex-col h-[350px]">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white">Top Demand</h3>
            <a href="insights.php" class="text-xs text-[#51D55A] hover:text-[#4bc052] font-onest">Full Report &rarr;</a>
        </div>
        <div class="p-4 flex-1 relative">
            <?php if (empty($heatmapData['data'])): ?>
                <div class="flex items-center justify-center h-full text-[#9aa6d1]">No demand data yet</div>
            <?php else: ?>
                <canvas id="demandChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php renderLayoutContentEnd(); renderLayoutShellEnd(); ?>


<script>
if (typeof Chart === 'undefined') {
    console.error("Chart.js missing.");
} else {
    // Top Demand Chart
    (function(){
        const heatData = <?php echo json_encode($heatmapData); ?>;
        if(heatData.labels.length === 0) return;

        const ctx = document.getElementById('demandChart');
        if(ctx){
            new Chart(ctx, {
                type: 'bar', 
                indexAxis: 'y',
                data: {
                    labels: heatData.labels,
                    datasets: [{
                        label: 'Units Sold',
                        data: heatData.data,
                        backgroundColor: [
                            'rgba(248, 113, 113, 0.8)',
                            'rgba(251, 146, 60, 0.8)',
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(163, 230, 53, 0.8)',
                            'rgba(52, 211, 153, 0.8)',
                        ],
                        borderRadius: 4,
                        barThickness: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: function(evt, elements) {
                        if (!elements.length) return;
                        const idx = elements[0].index;
                        const productId = heatData.ids[idx];
                        if (productId) {
                            window.location.href = 'insights.php?product_id=' + productId;
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterLabel: function() { return 'Click to view insights'; }
                            }
                        }
                    },
                    scales: {
                        x: { display: false, grid: { display: false } },
                        y: {
                            ticks: { color: (window.IWTheme ? IWTheme.chartColors().text : '#E3E3E3'), font: { family: 'Onest', size: 11 } },
                            grid: { display: false },
                            border: { display: false }
                        }
                    }
                }
            });
        }
    })();
}
document.addEventListener('themechange', function() { if (window.IWTheme) IWTheme.refreshAllCharts(); });
</script>

<?php renderLayoutFooter(); ?>
