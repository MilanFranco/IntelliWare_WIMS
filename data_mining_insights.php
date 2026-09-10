<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

require_once 'config/db.php';
require_once 'src/ml/ProductRecommendationManager.php';
require_once 'src/ml/MLForecastManager.php';

// Initialize our ML managers
$recommendationManager = new ProductRecommendationManager();
$forecastManager = new MLForecastManager();

// Get current month's recommendations and forecasts
$monthlyRecommendations = $recommendationManager->getCurrentMonthRecommendations();
$restockRecommendations = $recommendationManager->getRestockRecommendations();
$recentForecasts = $forecastManager->getLatestForecasts(null, 10);
$recentAnomalies = $forecastManager->getRecentAnomalies(7);

// Get last forecast run status
$lastRunStatus = $forecastManager->getLastRunStatus();

// Include the data mining engine
require_once 'data_mining_engine.php';

// Perform data mining analysis
$miner = new InventoryDataMiner($conn);
$miningResults = $miner->performDataMining();
$detailedAnalysis = $miner->getDetailedAnalysis();


// --- FIX: Recalculate summary counts directly from the detailed analysis ---
// This ensures the metric cards ALWAYS match the table data.
$all_classifications = array_column($detailedAnalysis, 'classification');
$classificationSummary = array_count_values($all_classifications);
// --- End of Fix ---

// Get additional insights
$insights = [
    'total_products' => count($detailedAnalysis),
    'out_of_stock' => $classificationSummary['out_of_stock'] ?? 0,
    'high_demand' => $classificationSummary['high_demand'] ?? 0,
    'low_demand' => $classificationSummary['low_demand'] ?? 0,
    'low_stock' => $classificationSummary['low_stock'] ?? 0,
    'overstocked' => $classificationSummary['overstocked'] ?? 0,
    'inactive' => $classificationSummary['inactive'] ?? 0,
    'normal' => $classificationSummary['normal'] ?? 0,
    'urgent_reorders' => ($classificationSummary['out_of_stock'] ?? 0) + ($classificationSummary['low_stock'] ?? 0) + ($classificationSummary['high_demand'] ?? 0),
    'anomalies' => []
];

// Detect anomalies (items that need attention)
foreach ($detailedAnalysis as $item) {
    if ($item['classification'] === 'out_of_stock') {
        $insights['anomalies'][] = [
            'product' => $item['product_name'],
            'issue' => 'Out of Stock Alert (0 on hand)',
            'severity' => 'critical',
            'days_since_restock' => $item['days_since_restock']
        ];
    } elseif ($item['classification'] === 'low_stock' ||
        ($item['classification'] === 'high_demand' && $item['days_since_restock'] > 30)) {
        $insights['anomalies'][] = [
            'product' => $item['product_name'],
            'issue' => $item['classification'] === 'low_stock' ? 'Low Stock Alert' : 'High Demand - Needs Restock',
            'severity' => 'high',
            'days_since_restock' => $item['days_since_restock']
        ];
    }
}

$conn->close();

require_once 'src/layout.php';
renderLayoutStart('Data Mining Insights', $role, $_SESSION['Username']);
?>

<style>
.card, .premium-card {
    background: var(--iw-bg-card);
    border: 1px solid var(--iw-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--iw-shadow-card);
    margin-bottom: 20px;
}
.metric-card {
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: var(--iw-bg-card);
    border: 1px solid var(--iw-border);
    border-radius: 14px;
    position: relative;
    overflow: hidden;
}
.metric-card:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35); }
.metric-card.active { border-color: #51D55A !important; box-shadow: 0 0 0 1px #51D55A, 0 8px 24px rgba(81, 213, 90, 0.3) !important; }
.filter-tag { display: inline-block; background: #51D55A; color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 8px; margin-bottom: 8px; }
.filter-tag .close { margin-left: 8px; cursor: pointer; font-weight: bold; }
.pagination-btn { background: var(--iw-bg-surface); border: 1px solid var(--iw-border); color: #E3E3E3; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
.pagination-btn:hover:not(:disabled) { border-color: #51D55A; color: #51D55A; }
.pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.pagination-info { font-size: 13px; color: #9aa6d1; }

/* Visual Hierarchy Badge Styles */
.badge.out-of-stock, .badge.out_of_stock, .badge.critical {
    background: rgba(239, 68, 68, 0.2) !important;
    color: #f87171 !important;
    border: 1px solid rgba(239, 68, 68, 0.5) !important;
}
.badge.low-stock, .badge.low_stock {
    background: rgba(245, 158, 11, 0.2) !important;
    color: #fbbf24 !important;
    border: 1px solid rgba(245, 158, 11, 0.45) !important;
}
.badge.high-demand, .badge.high_demand {
    background: rgba(16, 185, 129, 0.2) !important;
    color: #34d399 !important;
    border: 1px solid rgba(16, 185, 129, 0.45) !important;
}
.badge.overstocked {
    background: rgba(168, 85, 247, 0.2) !important;
    color: #c084fc !important;
    border: 1px solid rgba(168, 85, 247, 0.45) !important;
}
.badge.inactive {
    background: rgba(107, 114, 128, 0.25) !important;
    color: #9ca3af !important;
    border: 1px solid rgba(107, 114, 128, 0.4) !important;
}
.badge.normal {
    background: rgba(56, 189, 248, 0.18) !important;
    color: #38bdf8 !important;
    border: 1px solid rgba(56, 189, 248, 0.4) !important;
}
</style>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:20px; margin-bottom:8px;">Data Mining Insights Dashboard</div>
    <div style="color:#9aa6d1; font-family:'Onest'; font-size:14px;">
        Advanced analytics powered by rule-based classification algorithms to optimize your inventory management. Click on any metric to filter products.
    </div>
    <div id="filterDisplay" style="margin-top:12px; min-height:24px;"></div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <div class="premium-card metric-card p-5 flex items-center justify-between group hover:border-[#51D55A]/50 cursor-pointer transition-all active" onclick="filterProducts('all')" data-filter="all">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total Products</div>
            <div class="premium-metric">
                <span class="num text-2xl font-bold font-onest" style="background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($insights['total_products']); ?></span>
            </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-[#51D55A]/10 border border-[#51D55A]/20 flex items-center justify-center text-[#51D55A] group-hover:scale-110 transition-transform flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-5 flex items-center justify-between group hover:border-red-500/50 cursor-pointer transition-all" onclick="filterProducts('out_of_stock')" data-filter="out_of_stock">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Out of Stock</div>
            <div class="premium-metric">
                <span class="num text-2xl font-bold font-onest" style="background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($insights['out_of_stock']); ?></span>
            </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-5 flex items-center justify-between group hover:border-amber-500/50 cursor-pointer transition-all" onclick="filterProducts('low_stock')" data-filter="low_stock">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Low Stock</div>
            <div class="premium-metric">
                <span class="num text-2xl font-bold font-onest" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($insights['low_stock']); ?></span>
            </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-5 flex items-center justify-between group hover:border-emerald-500/50 cursor-pointer transition-all" onclick="filterProducts('high_demand')" data-filter="high_demand">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">High Demand</div>
            <div class="premium-metric">
                <span class="num text-2xl font-bold font-onest" style="background: linear-gradient(135deg, #34d399 0%, #059669 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($insights['high_demand']); ?></span>
            </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition-transform flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-5 flex items-center justify-between group hover:border-purple-500/50 cursor-pointer transition-all" onclick="filterProducts('overstocked')" data-filter="overstocked">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Overstocked</div>
            <div class="premium-metric">
                <span class="num text-2xl font-bold font-onest" style="background: linear-gradient(135deg, #c084fc 0%, #9333ea 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($insights['overstocked']); ?></span>
            </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 group-hover:scale-110 transition-transform flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
    </div>

    <div class="premium-card metric-card p-5 flex items-center justify-between group hover:border-red-500/50 cursor-pointer transition-all" onclick="filterProducts('urgent')" data-filter="urgent">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Urgent Actions</div>
            <div class="premium-metric">
                <span class="num text-2xl font-bold font-onest" style="background: linear-gradient(135deg, #fb7185 0%, #e11d48 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($insights['urgent_reorders']); ?></span>
            </div>
        </div>
        <div class="w-11 h-11 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">Inventory Classifications</div>
        <div style="overflow:auto; max-height:420px;">
            <?php if (empty($detailedAnalysis)): ?>
                <div style="color:#9aa6d1; text-align:center; padding:40px; font-size:14px;">
                    <div class="iw-empty-state-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 9.75 19.875V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg></div>
                    <div>No inventory data available</div>
                    <div style="font-size:12px; margin-top:8px; opacity:0.7;">Add products to see classifications</div>
                </div>
            <?php else: ?>
                <table class="table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-right">Stock</th>
                            <th class="text-right">Sold</th>
                            <th class="text-center">Classification</th>
                            <th class="text-right">Days Since Restock</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <?php foreach ($detailedAnalysis as $item): ?>
                        <tr data-classification="<?php echo $item['classification']; ?>">
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td class="text-right"><?php echo $item['quantity']; ?></td>
                            <td class="text-right"><?php echo $item['sold_quantity']; ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo str_replace('_', '-', $item['classification']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $item['classification'])); ?>
                                </span>
                            </td>
                            <td class="text-right"><?php echo $item['days_since_restock']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="inventoryPagination" class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 10px;"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">Anomaly Detection</div>
        <div style="overflow:auto; max-height:420px;">
            <?php if (empty($insights['anomalies'])): ?>
                <div style="color:#9aa6d1; text-align:center; padding:40px; font-size:14px;">
                    <div class="iw-empty-state-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div>
                    <div>No anomalies detected</div>
                    <div style="font-size:12px; margin-top:8px; opacity:0.7;">All inventory levels appear normal</div>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Issue</th>
                            <th>Severity</th>
                            <th>Days Since Restock</th>
                        </tr>
                    </thead>
                    <tbody id="anomalyTableBody">
                        <?php foreach ($insights['anomalies'] as $anomaly): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($anomaly['product']); ?></td>
                            <td><?php echo htmlspecialchars($anomaly['issue']); ?></td>
                            <td>
                                <span class="badge <?php echo strtolower($anomaly['severity']); ?>">
                                    <?php echo ucfirst($anomaly['severity']); ?>
                                </span>
                            </td>
                            <td><?php echo $anomaly['days_since_restock']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="anomalyPagination" class="pagination-container" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding:0 10px;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">Classification Rules Applied</div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #ef4444;">
            <div style="color:#ef4444; font-weight:600; font-size:14px; margin-bottom:8px;">Out of Stock</div>
            <div style="color:#E3E3E3; font-size:12px;">Stock ≤ 0 units (Critical Outage)</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #f59e0b;">
            <div style="color:#f59e0b; font-weight:600; font-size:14px; margin-bottom:8px;">Low Stock</div>
            <div style="color:#E3E3E3; font-size:12px;">Stock ≤ Reorder Point</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #10b981;">
            <div style="color:#10b981; font-weight:600; font-size:14px; margin-bottom:8px;">High Demand</div>
            <div style="color:#E3E3E3; font-size:12px;">Sold ≥ 80% of stock (Past 30 Days)</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #a855f7;">
            <div style="color:#a855f7; font-weight:600; font-size:14px; margin-bottom:8px;">Overstocked</div>
            <div style="color:#E3E3E3; font-size:12px;">Stock > 200 units</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#9ca3af; font-weight:600; font-size:14px; margin-bottom:8px;">Inactive</div>
            <div style="color:#E3E3E3; font-size:12px;">Days since restock > 90</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#38bdf8; font-weight:600; font-size:14px; margin-bottom:8px;">Normal</div>
            <div style="color:#E3E3E3; font-size:12px;">Healthy stock & normal sales velocity</div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">AI Recommendations</div>
    <div style="display:grid; grid-template-columns: repeat(1, 1fr); gap:20px;">
        <?php if (!empty($monthlyRecommendations)): ?>
            <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484;">
                <div style="color:#51D55A; font-weight:600; font-size:16px; margin-bottom:12px;"> Top Products for <?php echo date('F'); ?></div>
                <div style="overflow:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Avg. Monthly Demand</th>
                                <th>Recommended Stock</th>
                                <th>Action Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyRecommendations as $rec): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($rec['category']); ?></td>
                                <td><?php echo number_format($rec['current_stock']); ?> <?php echo htmlspecialchars($rec['uom']); ?></td>
                                <td><?php echo number_format($rec['avg_monthly_demand'], 1); ?></td>
                                <td><?php echo number_format($rec['recommended_stock']); ?></td>
                                <td>
                                    <?php if ($rec['restock_recommended']): ?>
                                        <span class="badge high">Restock Needed</span>
                                    <?php else: ?>
                                        <span class="badge normal">Stock OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($recentAnomalies)): ?>
            <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484;">
                <div style="color:#ef4444; font-weight:600; font-size:16px; margin-bottom:12px;"> AI Detected Anomalies</div>
                <div style="overflow:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Date</th>
                                <th>Anomaly Score</th>
                                <th>Severity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAnomalies as $anomaly): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($anomaly['product_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($anomaly['detection_date'])); ?></td>
                                <td><?php echo number_format($anomaly['anomaly_score'], 3); ?></td>
                                <td>
                                    <span class="badge <?php echo $anomaly['anomaly_score'] < -0.8 ? 'high' : ($anomaly['anomaly_score'] < -0.5 ? 'medium' : 'normal'); ?>">
                                        <?php echo $anomaly['anomaly_score'] < -0.8 ? 'High' : ($anomaly['anomaly_score'] < -0.5 ? 'Medium' : 'Low'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">Analysis Tools</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
        <button class="btn" onclick="refreshAnalysis()">Refresh Analysis</button>
        <button class="btn" onclick="runAIForecast()">Run AI Forecast</button>
        <button class="btn btn-secondary" onclick="exportData()">Export Data</button>
        <button class="btn btn-secondary" onclick="generateReport()">Generate Report</button>
        <button class="btn btn-secondary" onclick="scheduleAnalysis()">Schedule Analysis</button>
    </div>
    <div style="color:#9aa6d1; font-family:'Onest'; font-size:12px; padding:12px; background:#13275B; border-radius:8px; border:1px solid #2A4484;">
        <strong>Pro Tip:</strong> Run AI forecasting daily for the most accurate predictions. The system learns from historical data to provide better recommendations.
    </div>

    <?php if ($lastRunStatus && isset($lastRunStatus['status'])): ?>
    <div style="margin-top:12px; color:#9aa6d1; font-family:'Onest'; font-size:12px; padding:12px; background:#13275B; border-radius:8px; border:1px solid #2A4484;">
        Last AI forecast run: <?php
            $timestamp = !empty($lastRunStatus['finished_at']) ? $lastRunStatus['finished_at'] : (!empty($lastRunStatus['started_at']) ? $lastRunStatus['started_at'] : date('Y-m-d H:i:s'));
            echo date('Y-m-d H:i:s', strtotime($timestamp));
        ?> -
        Status: <span style="color: <?php
            $status = $lastRunStatus['status'] ?? 'unknown';
            echo ($status === 'completed') ? '#10b981' : (($status === 'running') ? '#f59e0b' : '#ef4444');
        ?>; font-weight:600;">
            <?php echo ucfirst($status); ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<script>
let currentFilter = 'all';

function filterProducts(filterType) {
    currentFilter = filterType;
    const tableBody = document.getElementById('inventoryTableBody');
    const rows = tableBody.getElementsByTagName('tr');
    const metricCards = document.querySelectorAll('.metric-card');
    const filterDisplay = document.getElementById('filterDisplay');
    
    // Remove active class from all cards
    metricCards.forEach(card => card.classList.remove('active'));
    
    // Add active class to clicked card
    const activeCard = document.querySelector(`.metric-card[data-filter="${filterType}"]`);
    if (activeCard) {
        activeCard.classList.add('active');
    }
    
    // Update filter display
    let filterText = '';
    let filterLabel = '';
    
    switch(filterType) {
        case 'all':
            filterLabel = 'Showing All Products';
            break;
        case 'out_of_stock':
            filterLabel = 'Out of Stock Products';
            break;
        case 'low_stock':
            filterLabel = 'Low Stock Products';
            break;
        case 'high_demand':
            filterLabel = 'High Demand Products';
            break;
        case 'overstocked':
            filterLabel = 'Overstocked Products';
            break;
        case 'urgent':
            filterLabel = 'Urgent Action Required';
            break;
    }
    
    if (filterType !== 'all') {
        filterDisplay.innerHTML = `<span class="filter-tag">${filterLabel} <span class="close" onclick="filterProducts('all')">&times;</span></span>`;
    } else {
        filterDisplay.innerHTML = '';
    }
    
    let visibleCount = 0;
    
    // Filter table rows
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const classification = row.getAttribute('data-classification');
        
        if (filterType === 'all') {
            row.classList.remove('filtered-out');
            visibleCount++;
        } else if (filterType === 'urgent') {
            // Show out_of_stock, low_stock and high_demand for urgent
            if (classification === 'out_of_stock' || classification === 'low_stock' || classification === 'high_demand') {
                row.classList.remove('filtered-out');
                visibleCount++;
            } else {
                row.classList.add('filtered-out');
            }
        } else if (classification === filterType) {
            row.classList.remove('filtered-out');
            visibleCount++;
        } else {
            row.classList.add('filtered-out');
        }
    }
    
    // Show message if no products match filter
    // Remove existing empty row if present first
    const existingEmpty = document.getElementById('emptyFilterRow');
    if (existingEmpty) existingEmpty.remove();

    if (visibleCount === 0 && filterType !== 'all') {
        const emptyRow = document.createElement('tr');
        emptyRow.id = 'emptyFilterRow';
        emptyRow.innerHTML = `
            <td colspan="6" style="text-align:center; padding:40px; color:#9aa6d1;">
                <div class="iw-empty-state-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg></div>
                <div>No products found for this filter</div>
                <div style="font-size:12px; margin-top:8px; opacity:0.7;">Try a different classification</div>
            </td>
        `;
        tableBody.appendChild(emptyRow);
    }
    
    // Scroll to inventory table
    document.getElementById('inventoryTable').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setInventoryPage(1);
}

let inventoryPage = 1;
let anomalyPage = 1;
const itemsPerPage = 10;

function renderPagination(tableBodyId, paginationId, pageVar, setPageFunc) {
    const tableBody = document.getElementById(tableBodyId);
    if (!tableBody) return;
    
    // get all visible rows that match filter (ignore empty state rows)
    const allRows = Array.from(tableBody.getElementsByTagName('tr')).filter(r => r.id !== 'emptyFilterRow' && !r.classList.contains('filtered-out'));
    
    const totalPages = Math.ceil(allRows.length / itemsPerPage) || 1;
    if (pageVar > totalPages) pageVar = totalPages;
    if (pageVar < 1) pageVar = 1;
    
    const start = (pageVar - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    
    // Hide all, then show only the current page
    allRows.forEach((row, index) => {
        if (index >= start && index < end) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    const pagContainer = document.getElementById(paginationId);
    if (pagContainer) {
        pagContainer.innerHTML = `
            <div class="pagination-info">Showing ${allRows.length === 0 ? 0 : start + 1} to ${Math.min(end, allRows.length)} of ${allRows.length} entries</div>
            <div style="display:flex; gap:8px;">
                <button class="pagination-btn" ${pageVar === 1 ? 'disabled' : ''} onclick="${setPageFunc}(${pageVar - 1})">Previous</button>
                <button class="pagination-btn" ${pageVar === totalPages ? 'disabled' : ''} onclick="${setPageFunc}(${pageVar + 1})">Next</button>
            </div>
        `;
    }
}

function setInventoryPage(page) {
    inventoryPage = page;
    renderPagination('inventoryTableBody', 'inventoryPagination', inventoryPage, 'setInventoryPage');
}

function setAnomalyPage(page) {
    anomalyPage = page;
    renderPagination('anomalyTableBody', 'anomalyPagination', anomalyPage, 'setAnomalyPage');
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    setInventoryPage(1);
    setAnomalyPage(1);
});

function refreshAnalysis() {
    location.reload();
}

function exportData() {
    const filterSuffix = currentFilter !== 'all' ? `_${currentFilter}` : '';
    let csv = 'Product,Category,Stock,Sold,Classification,Days Since Restock\n'; // Removed Sold Percentage as it's not in the visible table
    
    const tableBody = document.getElementById('inventoryTableBody');
    const rows = tableBody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (row.style.display !== 'none' && row.id !== 'emptyFilterRow') {
            const cells = row.getElementsByTagName('td');
            if (cells.length > 0) {
                const rowData = [];
                for (let j = 0; j < cells.length; j++) { // Iterate over all cells
                    let cellText = cells[j].textContent.trim();
                    // Remove badge styling from classification column
                    if (j === 4) { // Classification column
                        cellText = cellText.replace(/\s+/g, ' '); // Clean up whitespace
                    }
                    rowData.push(`"${cellText}"`);
                }
                csv += rowData.join(',') + '\n';
            }
        }
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `inventory_analysis${filterSuffix}_` + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function generateReport() {
    // Note: Replaced alert() with a custom modal if you have one, or console.log
    console.log('Report generation feature coming soon! This will create a comprehensive PDF report with charts and recommendations.');
    // You could show a small, non-blocking notification here instead of alert
}

function scheduleAnalysis() {
    // Note: Replaced alert() with a custom modal if you have one, or console.log
    console.log('Automated scheduling feature coming soon! Set up daily/weekly analysis runs.');
}

async function runAIForecast() {
    const btn = document.querySelector('button[onclick="runAIForecast()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Running AI Forecast...';
    btn.disabled = true;
    
    try {
        const response = await fetch('src/ml/run_forecast.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'run_forecast'
            })
        });
        
        const responseText = await response.text();
        console.log('Response status:', response.status);
        console.log('Response text:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was:', responseText);
            throw new Error('Invalid response from server. The server may have encountered an error. Check browser console for details.');
        }
        
        if (!response.ok) {
            throw new Error(data.message || `Server error (${response.status}): ${responseText.substring(0, 200)}`);
        }
        
        if (data.success) {
            // Use a less intrusive notification instead of alert
            console.log('AI forecast completed successfully! Refreshing page to show latest predictions...');
            alert('AI forecast completed successfully! Refreshing page...'); // Alert is OK for now, but consider a modal
            location.reload();
        } else {
            throw new Error(data.message || 'Failed to run forecast');
        }
    } catch (error) {
        console.error('Error details:', error);
        // Use a less intrusive notification instead of alert
        alert('Failed to run AI forecast: ' + error.message + '\n\nCheck the browser console (F12) for more details.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>

<?php
renderLayoutEnd();
?>