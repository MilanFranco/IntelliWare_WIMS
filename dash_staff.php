<?php
session_start();

// 1. Security Check
if (!isset($_SESSION['Username'])) { 
    header('Location: signin.php'); 
    exit(); 
}

// STRICT ROLE CHECK: Only allow Staff
if (($_SESSION['Role'] ?? '') !== 'staff') { 
    if (($_SESSION['Role'] ?? '') === 'admin') {
        header('Location: dash_admin.php');
    } elseif (($_SESSION['Role'] ?? '') === 'manager') {
        header('Location: dash_manager.php');
    }
    exit(); 
}

// 2. Include Layout & Database
require_once 'src/layout.php';
require_once 'config/db.php';

// --- DATA FETCHING ---

$items = [];
// Get Products for stats
$productQuery = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, category, quantity as qty, unit as uom, reorder_point FROM products";
$productResult = $conn->query($productQuery);

if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $items[] = $row;
    }
}

// Get User Info
$userQuery = "SELECT username, role, full_name FROM users WHERE username = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $_SESSION['Username']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
$userStmt->close();

// --- CALCULATE METRICS ---
$totalProducts = count($items);
$totalQty = array_sum(array_column($items, 'qty'));
$lowStockItems = array_filter($items, function($i){ 
    return (int)$i['qty'] <= (int)($i['reorder_point'] ?? 10); 
});
$lowStockCount = count($lowStockItems);

// Prepare Chart Data
$categoryData = [];
foreach ($items as $item) {
    $cat = $item['category'] ?? 'Uncategorized';
    if (!isset($categoryData[$cat])) $categoryData[$cat] = 0;
    $categoryData[$cat] += $item['qty'];
}

$conn->close();

// 3. RENDER LAYOUT
renderLayoutStart('Staff Dashboard', $_SESSION['Role'], $userInfo['full_name'] ?? $_SESSION['Username'], $lowStockCount);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
    /* --- PREMIUM METRIC CARDS --- */
    .premium-metric-card {
        background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
        border: 1px solid rgba(42, 68, 132, 0.6);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .premium-metric-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(81, 213, 90, 0.5), transparent);
    }

    .premium-metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        border-color: rgba(81, 213, 90, 0.3);
    }

    /* Typography for Metrics */
    .metric-label {
        color: #9aa6d1;
        font-family: 'Onest', sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    /* UPDATED: GREEN TEXT */
    .metric-value {
        font-size: 32px;
        font-weight: 700;
        font-family: 'Overpass', sans-serif;
        /* Green Gradient */
        background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 4px 12px rgba(81, 213, 90, 0.2);
    }

    /* Icon Container Styles */
    .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: transform 0.3s ease;
        /* Default Green Theme for all icons */
        background: rgba(81, 213, 90, 0.15);
        color: #51D55A;
        border: 1px solid rgba(81, 213, 90, 0.3);
    }

    .premium-metric-card:hover .icon-box {
        transform: scale(1.1) rotate(5deg);
    }

    /* Other Components */
    .card-title {
        color: #fff;
        font-weight: 700;
        font-family: 'Onest', sans-serif;
        font-size: 18px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-action {
        background: rgba(13, 22, 56, 0.6);
        border: 1px solid rgba(42, 68, 132, 0.6);
        color: #E3E3E3;
        padding: 16px;
        border-radius: 12px;
        text-align: center;
        text-decoration: none;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 100%;
    }
    .btn-action:hover {
        background: rgba(81, 213, 90, 0.1);
        border-color: #51D55A;
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2);
    }
    .btn-action svg { width: 24px; height: 24px; color: #51D55A; }
    .btn-action.red:hover { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); }
    .btn-action.red svg { color: #ef4444; }
    .btn-action.blue:hover { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
    .btn-action.blue svg { color: #3b82f6; }

    /* List Styles */
    .low-stock-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 320px;
        overflow-y: auto;
        padding-right: 4px;
    }
    .low-stock-list::-webkit-scrollbar { width: 6px; }
    .low-stock-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
    .low-stock-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

    .low-stock-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(13, 22, 56, 0.4);
        border: 1px solid rgba(42, 68, 132, 0.4);
        padding: 12px 16px;
        border-radius: 10px;
        transition: all 0.2s;
    }
    .low-stock-item:hover {
        background: rgba(239, 68, 68, 0.05);
        border-color: rgba(239, 68, 68, 0.3);
    }
    .low-stock-name { color: #E3E3E3; font-family: 'Onest', sans-serif; font-size: 14px; font-weight: 500; }
    .low-stock-qty {
        color: #f87171; font-family: 'Onest', sans-serif; font-size: 14px; font-weight: 700;
        background: rgba(239, 68, 68, 0.1); padding: 2px 8px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2);
    }
</style>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    
    <div class="premium-metric-card">
        <div>
            <div class="metric-label">Total Products</div>
            <div class="metric-value"><?php echo number_format($totalProducts); ?></div>
        </div>
        <div class="icon-box">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
    </div>

    <div class="premium-metric-card">
        <div>
            <div class="metric-label">Total Quantity</div>
            <div class="metric-value"><?php echo number_format($totalQty); ?></div>
        </div>
        <div class="icon-box">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </div>
    </div>

    <div class="premium-metric-card">
        <div>
            <div class="metric-label">My Status</div>
            <div class="metric-value" style="font-size:24px;">Active Staff</div>
        </div>
        <div class="icon-box">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="flex flex-col gap-6">
        
        <div class="premium-metric-card" style="display:block;">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="#51D55A" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Quick Actions
            </div>
            <div class="grid grid-cols-2 gap-4">
                <a href="transactions.php?action=in" class="btn-action">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    Stock In
                </a>
                <a href="transactions.php?action=out" class="btn-action red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path></svg>
                    Stock Out
                </a>
                <a href="products.php" class="btn-action blue col-span-2">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    View Full Inventory
                </a>
            </div>
        </div>

        <div class="premium-metric-card flex-1" style="display:block;">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                Low Stock Alerts
            </div>
            <div class="low-stock-list">
                <?php if(empty($lowStockItems)): ?>
                    <div style="text-align:center; color:#9aa6d1; padding:40px 0;">
                        <svg class="w-12 h-12 mx-auto mb-2 text-emerald-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        All stock levels are good!
                    </div>
                <?php else: ?>
                    <?php foreach(array_slice($lowStockItems, 0, 6) as $item): ?>
                        <?php $isOut = (int)$item['qty'] <= 0; ?>
                        <div class="low-stock-item" style="border-left: 3px solid <?php echo $isOut ? '#ef4444' : '#fbbf24'; ?>; padding-left: 8px;">
                            <span class="low-stock-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="low-stock-qty" style="color: <?php echo $isOut ? '#ef4444' : '#fbbf24'; ?>;"><?php echo (int)$item['qty']; ?> left</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if(count($lowStockItems) > 6): ?>
                        <a href="products.php?status=low" class="text-xs text-[#51D55A] hover:underline text-center mt-2 block">
                            View all <?php echo count($lowStockItems); ?> low items &rarr;
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="lg:col-span-2">
        <div class="premium-metric-card h-full flex flex-col" style="display:block;">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Inventory Distribution
            </div>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="staffChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script>
// Chart Logic
(function(){
    const ctx = document.getElementById('staffChart');
    if(!ctx) return;

    // Prepare Data from PHP
    const labels = <?php echo json_encode(array_keys($categoryData)); ?>;
    const data = <?php echo json_encode(array_values($categoryData)); ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Stock Quantity',
                data: data,
                backgroundColor: '#51D55A',
                hoverBackgroundColor: '#4BC052',
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(13, 22, 56, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(81, 213, 90, 0.3)',
                    borderWidth: 1,
                    padding: 10,
                    titleFont: { family: 'Onest', size: 13 },
                    bodyFont: { family: 'Onest', size: 13 }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#9aa6d1', font: { family: 'Onest' } },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#9aa6d1', font: { family: 'Onest' } },
                    grid: { color: 'rgba(255, 255, 255, 0.05)' }
                }
            }
        }
    });
})();
</script>

<?php renderLayoutEnd(); ?>