<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }

require_once 'src/layout.php';
require_once 'config/db.php';

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

// 4. Recent Transactions
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

// 6. Heatmap Data
$heatmapData = ['labels' => [], 'data' => []];
$heatQuery = "SELECT p.product_name, SUM(t.quantity) as demand_qty FROM transactions t 
              JOIN products p ON t.product_id = p.product_id WHERE t.transaction_type = 'stock_out' 
              GROUP BY p.product_id ORDER BY demand_qty DESC LIMIT 7";
$heatResult = $conn->query($heatQuery);
while($row = $heatResult->fetch_assoc()) {
    $heatmapData['labels'][] = $row['product_name'];
    $heatmapData['data'][] = (int)$row['demand_qty'];
}
$conn->close();

renderLayoutStart('System Dashboard', $_SESSION['Role'], $userInfo['full_name'] ?? $_SESSION['Username'], $lowStockCount);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-6">

    <div class="premium-card flex flex-col h-[400px]">
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

    <div class="premium-card flex flex-col h-[400px]">
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
                    <div class="flex items-center justify-between p-3 rounded-lg bg-[#13275B]/50 border border-[#2A4484] hover:border-red-500/30 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                            <div>
                                <div class="text-[#E3E3E3] text-sm font-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="text-[#9aa6d1] text-xs">Reorder Point: <?php echo $item['reorder_point']; ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-red-400 font-bold font-mono text-lg"><?php echo (int)$item['qty']; ?></div>
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
            <h3 class="font-onest font-semibold text-lg text-white">Movement Overview</h3>
            <span class="text-xs text-[#9aa6d1]">Last 7 Days</span>
        </div>
        <div class="p-4 flex-1 relative">
            <canvas id="overviewChart"></canvas>
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

<script>
if (typeof Chart === 'undefined') {
    console.error("Chart.js missing.");
} else {
    // 1. Transaction Bar Chart
    (function(){
        const chartData = <?php echo json_encode($chartData); ?>;
        const ctx = document.getElementById('overviewChart');
        if(ctx){
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        { 
                            label: 'Stock In', 
                            data: chartData.stock_in, 
                            backgroundColor: 'rgba(52, 211, 153, 0.8)', // Emerald
                            hoverBackgroundColor: 'rgba(52, 211, 153, 1)',
                            borderRadius: 4,
                            barPercentage: 0.6
                        },
                        { 
                            label: 'Stock Out', 
                            data: chartData.stock_out, 
                            backgroundColor: 'rgba(248, 113, 113, 0.8)', // Red
                            hoverBackgroundColor: 'rgba(248, 113, 113, 1)',
                            borderRadius: 4,
                            barPercentage: 0.6
                        }
                    ]
                },
                options: { 
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins:{ 
                        legend:{ labels:{ color:'#E3E3E3', font: { family: 'Onest' } } } 
                    }, 
                    scales:{ 
                        x:{ ticks:{ color:'#9aa6d1' }, grid:{ display: false } }, 
                        y:{ beginAtZero: true, ticks:{ color:'#9aa6d1' }, grid:{ color:'rgba(255,255,255,0.05)' } } 
                    }
                }
            });
        }
    })();

    // 2. Heatmap (Horizontal Bar)
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
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { display: false, grid: { display: false } },
                        y: {
                            ticks: { color: '#E3E3E3', font: { family: 'Onest', size: 11 } },
                            grid: { display: false },
                            border: { display: false }
                        }
                    }
                }
            });
        }
    })();
}
</script>

<?php renderLayoutEnd(); ?>