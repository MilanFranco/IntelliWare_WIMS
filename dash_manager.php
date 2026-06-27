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

// Metrics Calculation
$lowStockItems = array_filter($items, function($i){ 
    return (int)$i['qty'] <= (int)($i['reorder_point'] ?? 10); 
});
$lowStockCount = count($lowStockItems);
$totalSKUs = count($items);
$totalQty = array_sum(array_map(function($i){return (int)$i['qty'];}, $items));
$totalCategories = count(array_unique(array_map(function($i){return $i['category'];}, $items)));

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

<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='products.php'">
    <div>Total Stock-keeping Unit</div>
    <div class="num"><?php echo $totalSKUs; ?></div>
  </div>
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='products.php'">
    <div>Total Quantity</div>
    <div class="num"><?php echo number_format($totalQty); ?></div>
  </div>
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='reports.php'">
    <div>Low Stock Items</div>
    <div class="num" style="<?php echo $lowStockCount > 0 ? 'color:#f87171; -webkit-text-fill-color:#f87171;' : ''; ?>">
        <?php echo $lowStockCount; ?>
    </div>
  </div>
  <div class="premium-metric-card premium-metric clickable" onclick="window.location.href='products.php'">
    <div>Categories</div>
    <div class="num"><?php echo $totalCategories; ?></div>
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
                      <div class="low-stock-item">
                          <span class="low-stock-name"><?php echo htmlspecialchars($item['name']); ?></span>
                          <span class="low-stock-qty"><?php echo (int)$item['qty']; ?> left</span>
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