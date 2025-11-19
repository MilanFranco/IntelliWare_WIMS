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

// --- ENHANCEMENT: Add calculated fields to the analysis for better display ---
foreach ($detailedAnalysis as &$item) {
    // Calculate Sold Percentage
    $total_available = $item['quantity'] + $item['sold_quantity'];
    $item['sold_percentage'] = ($total_available > 0) ? round(($item['sold_quantity'] / $total_available) * 100, 1) : 0;
}
unset($item); // Break the reference

// --- FIX & RECALCULATION: Ensure metric cards ALWAYS match the table data. ---
$all_classifications = array_column($detailedAnalysis, 'classification');
$classificationSummary = array_count_values($all_classifications);
// --- End of Fix ---

// Get additional insights
$insights = [
    'total_products' => count($detailedAnalysis),
    'high_demand' => $classificationSummary['high_demand'] ?? 0,
    'low_demand' => $classificationSummary['low_demand'] ?? 0,
    'low_stock' => $classificationSummary['low_stock'] ?? 0,
    'overstocked' => $classificationSummary['overstocked'] ?? 0,
    'inactive' => $classificationSummary['inactive'] ?? 0,
    'normal' => $classificationSummary['normal'] ?? 0,
    'urgent_reorders' => 0,
    'anomalies' => []
];

// --- ENHANCEMENT: Urgent Reorders = Items that are Low Stock OR High Demand AND Low Stock ---
$insights['urgent_reorders'] = 0;
foreach ($detailedAnalysis as $item) {
    if ($item['classification'] === 'low_stock' || $item['classification'] === 'high_demand') {
        $insights['urgent_reorders']++;
    }
    
    // Detect anomalies (items that need attention) - Updated logic to be more specific
    if ($item['classification'] === 'low_stock' || 
        ($item['classification'] === 'high_demand' && $item['quantity'] < 20)) { // High Demand + Low Quantity (Threshold changed to 20 for better actionability)
        $insights['anomalies'][] = [
            'product' => $item['product_name'],
            'issue' => $item['classification'] === 'low_stock' ? 'Critical Stock Alert' : 'High Demand - Low Supply',
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
/* Base Styles - Kept mostly same */
.card { background:#1D387B; border:1px solid #2A4484; border-radius:12px; padding:20px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); margin-bottom:20px; }
.btn { background:#51D55A; color:#fff; border-radius:8px; padding:10px 16px; font-size:13px; text-decoration:none; border:1px solid transparent; cursor:pointer; font-weight:500; transition:all 0.2s ease; }
.btn:hover { background:#3d9743; transform:translateY(-1px); }
.btn-secondary { background:#13275B; color:#E3E3E3; border:1px solid #2A4484; }
.btn-secondary:hover { background:#1D387B; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { 
    padding:12px; 
    border-bottom:1px solid #2A4484; 
    color:#E3E3E3; 
    font-family:'Onest'; 
    font-size:13px;
    text-align: left;
    font-weight: 500; /* Adjusted for better readability */
}
.table th { 
    background:#13275B; 
    font-weight: 700;
}
/* ENHANCEMENT: Add sortable style */
.table th.sortable {
    cursor: pointer;
    position: relative;
}
.table th.sortable:hover {
    background: #1a326b;
}
.table th.sortable::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    opacity: 0.4;
}
.table th.sortable.asc::after {
    content: '▲'; /* Up arrow for ascending */
    opacity: 1;
    font-size: 10px;
    color: #51D55A;
}
.table th.sortable.desc::after {
    content: '▼'; /* Down arrow for descending */
    opacity: 1;
    font-size: 10px;
    color: #51D55A;
}


/* Badge Styles - Kept same */
.badge { display: inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:500; white-space: nowrap; }
.badge.low-stock, .badge.high { background:#ef4444; color:#fff; } /* Red */
.badge.high-demand, .badge.fast-moving { background:#10b981; color:#fff; } /* Green */
.badge.overstocked, .badge.medium { background:#f59e0b; color:#fff; } /* Yellow/Orange */
.badge.inactive { background:#6b7280; color:#fff; } /* Gray */
.badge.normal { background:#3b82f6; color:#fff; } /* Blue */

/* Metric Card Styles - Kept same */
.metric-card { cursor: pointer; transition: all 0.3s ease; }
.metric-card:hover { transform: translateY(-4px); box-shadow: 0 6px 24px rgba(81, 213, 90, 0.3); border-color: #51D55A; }
.metric-card.active { border-color: #51D55A; background: linear-gradient(135deg, #13275B 0%, #1D387B 100%); box-shadow: 0 6px 24px rgba(81, 213, 90, 0.4); }

/* ENHANCEMENT: Sold Percentage Bar */
.sold-bar-container { width: 80px; height: 8px; background: #2A4484; border-radius: 4px; overflow: hidden; margin: auto; }
.sold-bar { height: 100%; transition: width 0.3s ease-out; border-radius: 4px; }
.sold-text { font-size: 11px; margin-top: 4px; font-weight: 700;}
</style>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:18px; margin-bottom:12px;">📊 Data Mining Insights Dashboard</div>
    <div style="color:#E3E3E3; font-family:'Onest'; font-size:14px; margin-bottom:20px; opacity:0.8;">
        Advanced analytics powered by rule-based classification algorithms to optimize your inventory management. **Click on any metric to filter products.**
    </div>
    
    <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:20px;">
        <div class="metric-card" style="background:#13275B; padding:20px; border-radius:12px; border:2px solid #2A4484; text-align:center;" onclick="filterProducts('all')" data-filter="all">
            <div style="color:#51D55A; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['total_products']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Total Products</div>
        </div>
        <div class="metric-card" style="background:#13275B; padding:20px; border-radius:12px; border:2px solid #2A4484; text-align:center;" onclick="filterProducts('high_demand')" data-filter="high_demand">
            <div style="color:#10b981; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['high_demand']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">High Demand</div>
        </div>
        <div class="metric-card" style="background:#13275B; padding:20px; border-radius:12px; border:2px solid #2A4484; text-align:center;" onclick="filterProducts('low_stock')" data-filter="low_stock">
            <div style="color:#ef4444; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['low_stock']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Low Stock</div>
        </div>
        <div class="metric-card" style="background:#13275B; padding:20px; border-radius:12px; border:2px solid #2A4484; text-align:center;" onclick="filterProducts('overstocked')" data-filter="overstocked">
            <div style="color:#f59e0b; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['overstocked']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Overstocked</div>
        </div>
        <div class="metric-card" style="background:#13275B; padding:20px; border-radius:12px; border:2px solid #2A4484; text-align:center;" onclick="filterProducts('urgent')" data-filter="urgent">
            <div style="color:#ef4444; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['urgent_reorders']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Urgent Actions</div>
        </div>
    </div>
    
    <div id="filterDisplay" style="margin-top:16px; min-height:24px;"></div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">🏷️ Inventory Classifications</div>
        <div style="overflow:auto; max-height:420px;">
            <?php if (empty($detailedAnalysis)): ?>
                <div style="color:#9aa6d1; text-align:center; padding:40px; font-size:14px;">
                    <div style="font-size:48px; margin-bottom:16px;">📊</div>
                    <div>No inventory data available</div>
                    <div style="font-size:12px; margin-top:8px; opacity:0.7;">Add products to see classifications</div>
                </div>
            <?php else: ?>
                <table class="table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort-key="product_name">Product</th>
                            <th class="sortable" data-sort-key="category">Category</th>
                            <th class="sortable" data-sort-key="quantity">Stock</th>
                            <th class="sortable" data-sort-key="sold_percentage" style="text-align:center;">Sold %</th>
                            <th>Classification</th>
                            <th class="sortable" data-sort-key="days_since_restock">Days Since Restock</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <?php foreach ($detailedAnalysis as $item): ?>
                        <tr data-classification="<?php echo $item['classification']; ?>" 
                            data-product_name="<?php echo htmlspecialchars($item['product_name']); ?>"
                            data-category="<?php echo htmlspecialchars($item['category']); ?>"
                            data-quantity="<?php echo $item['quantity']; ?>"
                            data-sold_percentage="<?php echo $item['sold_percentage']; ?>"
                            data-days_since_restock="<?php echo $item['days_since_restock']; ?>">
                            
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td style="text-align:center;">
                                <div class="sold-bar-container">
                                    <div class="sold-bar" style="width: <?php echo $item['sold_percentage']; ?>%; background: <?php 
                                        if ($item['sold_percentage'] > 80) echo '#10b981'; // High
                                        else if ($item['sold_percentage'] > 50) echo '#f59e0b'; // Medium
                                        else echo '#3b82f6'; // Low
                                    ?>;"></div>
                                </div>
                                <div class="sold-text"><?php echo number_format($item['sold_percentage'], 1); ?>%</div>
                            </td>
                            <td>
                                <span class="badge <?php echo str_replace('_', '-', $item['classification']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $item['classification'])); ?>
                                </span>
                            </td>
                            <td><?php echo $item['days_since_restock']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">⚠️ Rule-Based Anomalies (Action Required)</div>
        <div style="overflow:auto; max-height:420px;">
            <?php if (empty($insights['anomalies'])): ?>
                <div style="color:#9aa6d1; text-align:center; padding:40px; font-size:14px;">
                    <div style="font-size:48px; margin-bottom:16px;">✅</div>
                    <div>No critical anomalies detected</div>
                    <div style="font-size:12px; margin-top:8px; opacity:0.7;">Check inventory table for normal items.</div>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">📈 Data Mining Classification Rules</div>
    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#10b981; font-weight:600; font-size:14px; margin-bottom:8px;">High Demand (Fast Moving)</div>
            <div style="color:#E3E3E3; font-size:12px;">**Rule:** Sold $\ge$ **80%** of total availability.</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#ef4444; font-weight:600; font-size:14px; margin-bottom:8px;">Low Stock (Restock Alert)</div>
            <div style="color:#E3E3E3; font-size:12px;">**Rule:** Current Stock $\lt$ **10** units.</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#f59e0b; font-weight:600; font-size:14px; margin-bottom:8px;">Overstocked (Capital Tie-Up)</div>
            <div style="color:#E3E3E3; font-size:12px;">**Rule:** Current Stock $\gt$ **200** units.</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#6b7280; font-weight:600; font-size:14px; margin-bottom:8px;">Inactive (Review/Clearance)</div>
            <div style="color:#E3E3E3; font-size:12px;">**Rule:** Days since last restock $\gt$ **90** days.</div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">🤖 AI & ML Recommendations</div>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <?php if (!empty($monthlyRecommendations)): ?>
            <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484;">
                <div style="color:#51D55A; font-weight:600; font-size:16px; margin-bottom:12px;"> Top Products for <?php echo date('F'); ?> (Forecasted)</div>
                <div style="overflow:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Rec. Stock</th>
                                <th>Action Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyRecommendations as $rec): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($rec['category']); ?></td>
                                <td><?php echo number_format($rec['current_stock']); ?></td>
                                <td><?php echo number_format($rec['recommended_stock']); ?></td>
                                <td>
                                    <?php if ($rec['restock_recommended']): ?>
                                        <span class="badge high">Restock: $\uparrow$ <?php echo number_format($rec['recommended_stock'] - $rec['current_stock']); ?></span>
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
                <div style="color:#ef4444; font-weight:600; font-size:16px; margin-bottom:12px;"> AI Time-Series Anomalies</div>
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
                                    <span class="badge <?php 
                                        $score = $anomaly['anomaly_score'];
                                        if ($score < -0.8 || $score > 0.8) echo 'high';
                                        else if ($score < -0.5 || $score > 0.5) echo 'medium';
                                        else echo 'normal';
                                    ?>">
                                        <?php 
                                        $score = $anomaly['anomaly_score'];
                                        if ($score < -0.8) echo 'Critically Low'; 
                                        else if ($score > 0.8) echo 'Critically High';
                                        else if ($score < -0.5) echo 'Medium Low';
                                        else if ($score > 0.5) echo 'Medium High';
                                        else echo 'Low';
                                        ?>
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
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">🔧 Analysis Tools</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
        <button class="btn" onclick="refreshAnalysis()">Refresh Data</button>
        <button class="btn" onclick="runAIForecast()">Run AI Forecast</button>
        <button class="btn btn-secondary" onclick="exportData()">Export Filtered Data</button>
        <button class="btn btn-secondary" onclick="generateReport()">Generate PDF Report</button>
        <button class="btn btn-secondary" onclick="scheduleAnalysis()">Schedule Analysis</button>
    </div>
    <div style="color:#9aa6d1; font-family:'Onest'; font-size:12px; padding:12px; background:#13275B; border-radius:8px; border:1px solid #2A4484;">
        <strong>Pro Tip:</strong> **Run AI forecasting daily** for the most accurate predictions. The time-series model provides forward-looking insights beyond simple rule-based classification.
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
    
    let visibleCount = 0;
    
    // Filter table rows
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const classification = row.getAttribute('data-classification');
        
        if (filterType === 'all') {
            row.style.display = '';
            visibleCount++;
        } else if (filterType === 'urgent') {
            // Show Low Stock AND High Demand for urgent
            if (classification === 'high_demand' || classification === 'low_stock') {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        } else if (classification === filterType) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Show message if no products match filter
    const existingEmpty = document.getElementById('emptyFilterRow');
    if (existingEmpty) existingEmpty.remove();

    if (visibleCount === 0 && rows.length > 0) { // Only show if there is data but it's filtered out
        const emptyRow = document.createElement('tr');
        emptyRow.id = 'emptyFilterRow';
        emptyRow.innerHTML = `
            <td colspan="6" style="text-align:center; padding:40px; color:#9aa6d1;">
                <div style="font-size:48px; margin-bottom:16px;">🔍</div>
                <div>No products found for this classification</div>
                <div style="font-size:12px; margin-top:8px; opacity:0.7;">Try selecting another metric or 'Total Products'</div>
            </td>
        `;
        
        tableBody.appendChild(emptyRow);
    }
    
    // Scroll to inventory table
    document.getElementById('inventoryTable').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function refreshAnalysis() {
    location.reload();
}

function exportData() {
    const filterSuffix = currentFilter !== 'all' ? `_${currentFilter}` : '';
    let csv = 'Product,Category,Stock,Sold,Classification,Days Since Restock,Sold Percentage\n';
    
    const tableBody = document.getElementById('inventoryTableBody');
    const rows = tableBody.getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (row.style.display !== 'none' && row.id !== 'emptyFilterRow') {
            const cells = row.getElementsByTagName('td');
            // Extract data attributes for accurate export
            const productName = row.getAttribute('data-product_name') || cells[0].textContent.trim();
            const category = row.getAttribute('data-category') || cells[1].textContent.trim();
            const quantity = row.getAttribute('data-quantity') || cells[2].textContent.trim();
            const classification = row.getAttribute('data-classification');
            const daysSinceRestock = row.getAttribute('data-days_since_restock');
            const soldPercentage = row.getAttribute('data-sold_percentage');
            
            // Note: The original sold_quantity column was removed in favor of sold_percentage, 
            // so we'll export the calculated percentage instead.
            
            const rowData = [
                `"${productName}"`,
                `"${category}"`,
                quantity,
                classification,
                daysSinceRestock,
                soldPercentage + '%'
            ];
            
            csv += rowData.join(',') + '\n';
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

// ENHANCEMENT: Add Table Sorting Logic
function sortTable(columnIndex) {
    const table = document.getElementById('inventoryTable');
    const tbody = document.getElementById('inventoryTableBody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(#emptyFilterRow)'));
    const header = table.querySelectorAll('th.sortable')[columnIndex];
    const key = header.getAttribute('data-sort-key');

    let direction = header.classList.contains('asc') ? 'desc' : 'asc';

    // Clear previous sorting classes
    table.querySelectorAll('th.sortable').forEach(th => {
        th.classList.remove('asc', 'desc');
    });

    // Add current sorting class
    header.classList.add(direction);

    rows.sort((a, b) => {
        let aVal = a.getAttribute(`data-${key}`);
        let bVal = b.getAttribute(`data-${key}`);

        // Handle numeric and string keys
        let comparison = 0;
        if (!isNaN(parseFloat(aVal)) && isFinite(aVal)) {
            comparison = parseFloat(aVal) - parseFloat(bVal);
        } else {
            comparison = aVal.localeCompare(bVal);
        }

        return direction === 'asc' ? comparison : -comparison;
    });

    // Reappend sorted rows
    rows.forEach(row => tbody.appendChild(row));
}

// Attach event listeners to sortable headers
document.addEventListener('DOMContentLoaded', () => {
    const sortableHeaders = document.querySelectorAll('#inventoryTable th.sortable');
    sortableHeaders.forEach((header, index) => {
        header.addEventListener('click', () => {
            sortTable(index);
        });
    });
});
// End of Table Sorting Logic

function generateReport() {
    alert('Report generation feature coming soon! This will create a comprehensive PDF report with charts and recommendations.');
}

function scheduleAnalysis() {
    alert('Automated scheduling feature coming soon! Set up daily/weekly analysis runs.');
}

async function runAIForecast() {
    const btn = document.querySelector('button[onclick="runAIForecast()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Running AI Forecast...';
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
            alert('AI forecast completed successfully! Refreshing page to show latest predictions...');
            location.reload();
        } else {
            throw new Error(data.message || 'Failed to run forecast');
        }
    } catch (error) {
        console.error('Error details:', error);
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