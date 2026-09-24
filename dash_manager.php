<?php
session_start();

// 1. Security Check
if (!isset($_SESSION['Username'])) { 
    header('Location: signin.php'); 
    exit(); 
}

// STRICT ROLE CHECK: Only allow Managers
// If Admin tries to access, send to Admin Dash. If Staff, send to Staff Dash.
if (($_SESSION['Role'] ?? '') !== 'manager') { 
    if (($_SESSION['Role'] ?? '') === 'admin') {
        header('Location: dash_admin.php');
    } else {
        header('Location: dash_staff.php');
    }
    exit(); 
}

// 2. Include Layout & Database
require_once 'src/layout.php';
require_once 'config/db.php';

// --- DATA FETCHING ---

$items = [];
// Get Products for calculations
$productQuery = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, category, quantity as qty, reorder_point FROM products";
$productResult = $conn->query($productQuery);

if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $items[] = $row;
    }
}

// Get User Info for Header
$userQuery = "SELECT username, role, full_name FROM users WHERE username = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $_SESSION['Username']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
$userStmt->close();

// Metrics Calculation & Inventory Aggregates (matching products.php)
$statQuery = "SELECT 
    COUNT(*) AS total_items,
    COALESCE(SUM(quantity), 0) AS total_units,
    COALESCE(SUM(quantity * price), 0) AS total_stock_value,
    COALESCE(SUM(CASE WHEN quantity > 0 AND quantity <= reorder_point THEN 1 ELSE 0 END), 0) AS low_stock_count,
    COALESCE(SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count,
    COUNT(DISTINCT category) AS total_categories
FROM products 
WHERE archived = 0";
$statRes = $conn->query($statQuery);
$statRow = ($statRes && $statRes->num_rows > 0) ? $statRes->fetch_assoc() : [];

$totalItems       = (int)($statRow['total_items'] ?? count($items));
$totalUnits       = (int)($statRow['total_units'] ?? 0);
$totalStockValue   = (float)($statRow['total_stock_value'] ?? 0.0);
$lowStockCount     = (int)($statRow['low_stock_count'] ?? 0);
$outOfStockCount   = (int)($statRow['out_of_stock_count'] ?? 0);
$totalCategories   = (int)($statRow['total_categories'] ?? 0);
$totalSKUs         = $totalItems;
$totalQty          = $totalUnits;

$lowStockItems = array_filter($items, function($i){ 
    return (int)$i['qty'] <= (int)($i['reorder_point'] ?? 10); 
});

if (!function_exists('formatStockValue')) {
    function formatStockValue(float $value): string {
        if ($value >= 1000000000) {
            return '&#8369;' . number_format($value / 1000000000, 2) . 'B';
        }
        if ($value >= 1000000) {
            return '&#8369;' . number_format($value / 1000000, 2) . 'M';
        }
        if ($value >= 1000) {
            return '&#8369;' . number_format($value / 1000, 2) . 'K';
        }
        return '&#8369;' . number_format($value, 2);
    }
}

// Recent Transactions (Last 5)
$recentTransactions = [];
$transQuery = "SELECT t.transaction_type, t.quantity, t.transaction_date, p.product_name, u.username
                FROM transactions t
                JOIN products p ON t.product_id = p.product_id
                JOIN users u ON t.user_id = u.user_id
                ORDER BY t.transaction_date DESC LIMIT 5";
$transResult = $conn->query($transQuery);
if ($transResult && $transResult->num_rows > 0) {
    while ($row = $transResult->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}

// Chart Data (Last 7 Days)
$chartData = ['labels' => [], 'stock_in' => [], 'stock_out' => []];
$tempData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartData['labels'][] = date('M j', strtotime("-$i days"));
    $tempData[$date] = ['stock_in' => 0, 'stock_out' => 0];
}

$sevenDaysAgo = date('Y-m-d', strtotime('-6 days')); 
$chartQuery = $conn->prepare("SELECT DATE(transaction_date) as tx_date, transaction_type, COALESCE(SUM(quantity), 0) as total_quantity FROM transactions WHERE transaction_date >= ? GROUP BY tx_date, transaction_type ORDER BY tx_date");
$chartQuery->bind_param("s", $sevenDaysAgo);
$chartQuery->execute();
$chartResult = $chartQuery->get_result();

if ($chartResult) {
    while ($row = $chartResult->fetch_assoc()) {
        if (isset($tempData[$row['tx_date']])) {
            $type = htmlspecialchars($row['transaction_type']);
            if (array_key_exists($type, $tempData[$row['tx_date']])) {
                 $tempData[$row['tx_date']][$type] = (int)$row['total_quantity'];
            }
        }
    }
}
$chartQuery->close();

foreach ($tempData as $dateData) {
    $chartData['stock_in'][] = $dateData['stock_in'];
    $chartData['stock_out'][] = $dateData['stock_out'];
}

// Heatmap Data (Top 7 Stock-Outs)
$heatmapData = ['labels' => [], 'data' => []];
$heatQuery = "SELECT p.product_name, SUM(t.quantity) as demand_qty FROM transactions t JOIN products p ON t.product_id = p.product_id WHERE t.transaction_type = 'stock_out' GROUP BY p.product_id ORDER BY demand_qty DESC LIMIT 7";
$heatResult = $conn->query($heatQuery);
if ($heatResult) {
    while($row = $heatResult->fetch_assoc()) {
        $heatmapData['labels'][] = $row['product_name'];
        $heatmapData['data'][] = (int)$row['demand_qty'];
    }
}
$conn->close();

// --- RENDER START ---
renderLayoutStart('Manager Dashboard', $_SESSION['Role'], $userInfo['full_name'] ?? $_SESSION['Username'], $lowStockCount);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
    /* Glassmorphism Card Effect */
    .premium-metric-card {
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 24px;
        backdrop-filter: blur(10px);
        color: white;
        font-family: 'Onest', sans-serif;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    /* Card Titles & Numbers */
    .premium-metric-card .card-title {
        font-size: 16px;
        font-weight: 600;
        color: #9aa6d1;
        margin-bottom: 16px;
    }

    .premium-metric .num {
        font-size: 32px;
        font-weight: 700;
        margin-top: 8px;
        background: linear-gradient(to right, #fff, #a5b4fc);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Clickable Interactions */
    .premium-metric-card.clickable {
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }

    .premium-metric-card.clickable:hover {
        transform: translateY(-4px);
        border-color: rgba(81, 213, 90, 0.3);
        box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
    }

    /* Tables in Dashboard */
    .activity-table { width: 100%; border-collapse: collapse; }
    .activity-table td { padding: 12px 8px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; color: #e3e3e3; }
    .activity-table tr:last-child td { border-bottom: none; }

    /* Status Badges */
    .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-in { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .badge-out { background: rgba(248, 113, 113, 0.15); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }

    /* Low Stock List */
    .low-stock-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .low-stock-name { color: #f87171; font-weight: 500; font-size: 14px; }
    .low-stock-qty { color: #9aa6d1; font-size: 13px; background: rgba(248, 113, 113, 0.1); padding: 2px 8px; border-radius: 4px; }

    /* Canvas Containers */
    .heatmap-canvas-container { height: 250px; width: 100%; position: relative; }
</style>

<style>
    /* ── Page Hero Card ────────────────────────────────────────── */
    .page-hero-card {
        background: linear-gradient(135deg, rgba(29, 56, 123, 0.92) 0%, rgba(18, 39, 91, 0.96) 100%);
        border: 1px solid rgba(42, 68, 132, 0.6);
        border-radius: 18px;
        padding: 24px 28px;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35);
        position: relative;
        overflow: hidden;
    }
    .page-hero-card::after {
        content: '';
        position: absolute;
        top: -60px;
        right: -40px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(81, 213, 90, 0.08) 0%, rgba(0, 0, 0, 0) 70%);
        pointer-events: none;
    }
    [data-theme="light"] .page-hero-card {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #93c5fd;
        box-shadow: 0 8px 24px rgba(59, 130, 246, 0.12);
    }
    [data-theme="light"] .page-hero-card .text-\[\#9aa6d1\] { color: #1e3a5f !important; }
    [data-theme="light"] .page-hero-card .text-white { color: #020617 !important; }
    [data-theme="light"] .page-hero-card .bg-\[\#13275B\]\/80 { background: rgba(219,234,254,0.8) !important; border-color: #93c5fd !important; color: #1e3a5f !important; }
    [data-theme="light"] .page-hero-card .bg-\[\#1D387B\]\/60 { background: rgba(191,219,254,0.6) !important; border-color: #93c5fd !important; color: #1e3a5f !important; }
    [data-theme="light"] .page-hero-card strong { color: #020617 !important; }
</style>

<!-- Hero Page Header Banner -->
<div class="page-hero-card mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                </div>
                Manager Dashboard
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Warehouse overview for managers — track inventory levels, recent activity, low-stock alerts, and category distribution at a glance.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Role: <strong class="text-white">Manager</strong>
            </span>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='products.php'">
    <div class="card-title" style="margin-bottom:0; font-size:14px;">Stock value</div>
    <div class="num"><?php echo formatStockValue($totalStockValue); ?></div>
    <div style="font-size:12px; color:#9aa6d1; margin-top:6px; font-weight:500;"><?php echo number_format($totalUnits); ?> units on hand</div>
  </div>
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='products.php'">
    <div class="card-title" style="margin-bottom:0; font-size:14px;">Total Products</div>
    <div class="num"><?php echo number_format($totalItems); ?></div>
    <div style="font-size:12px; color:#9aa6d1; margin-top:6px; font-weight:500;">Active catalog SKUs</div>
  </div>
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='products.php?filter=low_stock'">
    <div class="card-title" style="margin-bottom:0; font-size:14px;">Low Stock</div>
    <div class="num" style="<?php echo $lowStockCount > 0 ? 'color:#f59e0b; -webkit-text-fill-color:#f59e0b;' : ''; ?>">
        <?php echo number_format($lowStockCount); ?>
    </div>
    <div style="font-size:12px; color:#9aa6d1; margin-top:6px; font-weight:500;"><?php echo $lowStockCount === 1 ? '1 item needs reorder' : number_format($lowStockCount) . ' items need reorder'; ?></div>
  </div>
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='products.php?filter=out_of_stock'">
    <div class="card-title" style="margin-bottom:0; font-size:14px;">Out of Stock</div>
    <div class="num" style="<?php echo $outOfStockCount > 0 ? 'color:#ef4444; -webkit-text-fill-color:#ef4444;' : ''; ?>">
        <?php echo number_format($outOfStockCount); ?>
    </div>
    <div style="font-size:12px; color:#9aa6d1; margin-top:6px; font-weight:500;"><?php echo $outOfStockCount === 1 ? '1 item fully depleted' : number_format($outOfStockCount) . ' items fully depleted'; ?></div>
  </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-top:16px;">
  
  <div style="display:flex; flex-direction:column; gap:16px;">
      
      <div class="premium-metric-card">
          <div style="display:flex; justify-content:space-between; align-items:center;">
             <div class="card-title" style="margin-bottom:0;">Recent Transactions</div>
             <a href="transactions.php" style="color:#51D55A; font-size:13px; text-decoration:none;">View All</a>
          </div>
          <div style="margin-top:16px;">
            <table class="activity-table">
                <?php if (empty($recentTransactions)): ?>
                    <tr><td style="text-align:center; color:#9aa6d1; padding:20px;">No transactions recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach($recentTransactions as $tx): ?>
                        <tr>
                            <td>
                                <?php if($tx['transaction_type'] === 'stock_in'): ?>
                                    <span class="badge badge-in">IN</span>
                                <?php else: ?>
                                    <span class="badge badge-out">OUT</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($tx['product_name']); ?></strong></td>
                            <td>Qty: <strong><?php echo (int)$tx['quantity']; ?></strong></td>
                            <td style="color:#9aa6d1;"><?php echo htmlspecialchars($tx['username']); ?></td>
                            <td style="color:#9aa6d1; text-align:right;"><?php echo date('M j, g:ia', strtotime($tx['transaction_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
          </div>
      </div>

      <div class="premium-metric-card">
          <div class="card-title">Daily Transaction Volume (Last 7 Days)</div>
          <div style="height: 150px;">
            <canvas id="overviewChart"></canvas>
          </div>
      </div>
  </div>

  <div style="display:flex; flex-direction:column; gap:16px;">
      
      <div class="premium-metric-card">
          <div class="card-title">Actionable Low Stock</div>
          <div class="low-stock-list">
              <?php if (empty($lowStockItems)): ?>
                  <div style="text-align:center; color:#9aa6d1; padding:20px 0;">All items are well-stocked!</div>
              <?php else: ?>
                  <?php foreach(array_slice($lowStockItems, 0, 5) as $item): ?>
                      <?php $isOut = (int)$item['qty'] <= 0; ?>
                      <div class="low-stock-item" style="border-left: 3px solid <?php echo $isOut ? '#ef4444' : '#fbbf24'; ?>; padding-left: 8px;">
                          <span class="low-stock-name"><?php echo htmlspecialchars($item['name']); ?></span>
                          <span class="low-stock-qty" style="color: <?php echo $isOut ? '#ef4444' : '#fbbf24'; ?>;"><?php echo (int)$item['qty']; ?> left</span>
                      </div>
                  <?php endforeach; ?>
                  <?php if ($lowStockCount > 5): ?>
                      <div style="color:#9aa6d1; font-size: 13px; text-align: center; margin-top: 5px; cursor:pointer;" onclick="window.location.href='reports.php'">
                          ...and <?php echo $lowStockCount - 5; ?> more.
                      </div>
                  <?php endif; ?>
              <?php endif; ?>
          </div>
      </div>

      <div class="premium-metric-card">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
             <div class="card-title" style="margin-bottom:0;">Product Demand</div>
             <a href="insights.php" style="color:#51D55A; text-decoration:none; font-size:18px;">&rarr;</a>
          </div>
          
          <?php if (empty($heatmapData['data'])): ?>
            <div style="text-align:center; color:#9aa6d1; padding:20px;">No demand data available yet.</div>
          <?php else: ?>
            <div class="heatmap-canvas-container">
                <canvas id="demandChart"></canvas>
            </div>
          <?php endif; ?>
      </div>
  </div>
</div>

<script>
if (typeof Chart === 'undefined') {
    console.error("Chart.js is not loaded.");
} else {

    // 1. Transaction Volume Chart
    (function(){
        const chartData = <?php echo json_encode($chartData); ?>;
        const ctx = document.getElementById('overviewChart');
        if(ctx){
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        { label: 'Stock In', data: chartData.stock_in, backgroundColor: 'rgba(52, 211, 153, 0.7)', borderRadius: 4, barPercentage: 0.6 },
                        { label: 'Stock Out', data: chartData.stock_out, backgroundColor: 'rgba(248, 113, 113, 0.7)', borderRadius: 4, barPercentage: 0.6 }
                    ]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false,
                    plugins:{ legend:{ labels:{ color:'#E3E3E3', font: { family: 'Onest' } } } }, 
                    scales:{ x:{ ticks:{ color:'#E3E3E3' }, grid:{ display: false } }, y:{ beginAtZero: true, ticks:{ color:'#E3E3E3' }, grid:{ color:'rgba(255,255,255,0.05)' } } }
                }
            });
        }
    })();

    // 2. Product Demand Heatmap
    (function(){
        const heatData = <?php echo json_encode($heatmapData); ?>;
        if(heatData.labels.length === 0) return;
        const ctx = document.getElementById('demandChart');
        if(ctx){
            new Chart(ctx, {
                type: 'bar', indexAxis: 'y',
                data: {
                    labels: heatData.labels,
                    datasets: [{
                        label: 'Units Sold', data: heatData.data,
                        backgroundColor: ['rgba(248, 113, 113, 0.85)', 'rgba(167, 139, 250, 0.85)', 'rgba(96, 165, 250, 0.85)', 'rgba(52, 211, 153, 0.85)', 'rgba(251, 191, 36, 0.85)', 'rgba(147, 197, 253, 0.85)', 'rgba(209, 213, 219, 0.85)'],
                        borderRadius: 6, barThickness: 25
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: { backgroundColor: 'rgba(29, 56, 123, 0.9)', titleColor: '#fff', bodyColor: '#fff' }
                    },
                    scales: { x: { display: false, grid: { display: false } }, y: { ticks: { color: '#E3E3E3', font: { size: 12, family: 'Onest', weight: 600 }, crossAlign: 'near', padding: 10 }, grid: { display: false }, border: { display: false } } }
                }
            });
        }
    })();
}
</script>

<?php renderLayoutEnd(); ?>