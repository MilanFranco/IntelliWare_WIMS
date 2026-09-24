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

    /* ── MOBILE-ONLY OVERRIDES (max-width: 767px) ── */
    @media (max-width: 767px) {

        /* 1. STAT CARDS — 3-column single row */
        .stat-cards-row {
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 8px !important;
        }
        /* Each stat card: stack content vertically, centre-aligned */
        .stat-cards-row .premium-metric-card {
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 10px 6px;
            gap: 6px;
        }
        /* Text block: full width so it centres properly */
        .stat-cards-row .premium-metric-card > div:first-child {
            width: 100%;
        }
        .stat-cards-row .metric-label {
            font-size: 9px;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
            line-height: 1.2;
            white-space: normal;
            word-break: break-word;
        }
        .stat-cards-row .metric-value {
            font-size: 20px;
            line-height: 1.1;
        }
        /* "My Status" card has inline font-size:24px — override it */
        .stat-cards-row .metric-value[style*="font-size:24px"],
        .stat-cards-row .metric-value[style*="font-size: 24px"] {
            font-size: 11px !important;
            white-space: normal;
            line-height: 1.2;
        }
        /* Smaller icon on mobile stat cards */
        .stat-cards-row .icon-box {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .stat-cards-row .icon-box svg {
            width: 15px;
            height: 15px;
        }

        /* 2. QUICK ACTIONS — equal-width Stock In / Stock Out buttons */
        .quick-actions-grid {
            grid-template-columns: 1fr 1fr !important;
        }
        /* Make sure "View Full Inventory" still spans full width */
        .quick-actions-grid .col-span-2 {
            grid-column: span 2 !important;
        }

        /* 3. Low Stock Alerts — remove internal scroll, list flows naturally */
        .low-stock-list {
            max-height: none;
            overflow-y: visible;
            padding-right: 0;
        }

        /* 4. Hide items beyond position 5 on mobile */
        .mobile-hidden {
            display: none;
        }
    }
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
                Staff Dashboard
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Your personal workspace for day-to-day warehouse tasks — view inventory status, log stock movements, and track recent activity.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Role: <strong class="text-white">Staff</strong>
            </span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stat-cards-row">
    
    <div class="premium-metric-card">
        <div>
            <div class="metric-label">Total Products</div>
            <div class="metric-value"><?php echo number_format($totalProducts); ?></div>
            <div style="font-size:12px; color:#9aa6d1; margin-top:2px;">Active catalog SKUs</div>
        </div>
        <div class="icon-box">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
    </div>

    <div class="premium-metric-card">
        <div>
            <div class="metric-label">Total Quantity</div>
            <div class="metric-value"><?php echo number_format($totalQty); ?></div>
            <div style="font-size:12px; color:#9aa6d1; margin-top:2px;">Units on hand</div>
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
            <div class="grid grid-cols-2 gap-4 quick-actions-grid">
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
                    <?php $idx = 0; foreach(array_slice($lowStockItems, 0, 6) as $item): $idx++; ?>
                        <?php $isOut = (int)$item['qty'] <= 0; ?>
                        <div class="low-stock-item<?php echo $idx > 5 ? ' mobile-hidden' : ''; ?>" style="border-left: 3px solid <?php echo $isOut ? '#ef4444' : '#fbbf24'; ?>; padding-left: 8px;">
                            <span class="low-stock-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="low-stock-qty" style="color: <?php echo $isOut ? '#ef4444' : '#fbbf24'; ?>;"><?php echo (int)$item['qty']; ?> left</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if(!empty($lowStockItems)): ?>
                <a href="products.php?status=low" class="text-xs text-[#51D55A] hover:underline text-center mt-3 block">
                    View all <?php echo count($lowStockItems); ?> low items &rarr;
                </a>
            <?php endif; ?>
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