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
    'high_demand' => $classificationSummary['high_demand'] ?? 0, // Now correct
    'low_demand' => $classificationSummary['low_demand'] ?? 0,    // Now correct
    'low_stock' => $classificationSummary['low_stock'] ?? 0,      // Now correct
    'overstocked' => $classificationSummary['overstocked'] ?? 0, // Now correct
    'inactive' => $classificationSummary['inactive'] ?? 0,        // Now correct
    'normal' => $classificationSummary['normal'] ?? 0,            // Now correct
    // This now correctly calculates urgent reorders based on the fixed summary
    'urgent_reorders' => ($classificationSummary['low_stock'] ?? 0) + ($classificationSummary['high_demand'] ?? 0),
    'anomalies' => []
];

// Detect anomalies (items that need attention) - This logic is from your original file
foreach ($detailedAnalysis as $item) {
    if ($item['classification'] === 'low_stock' ||
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
    transition: all 0.3s ease;
    background: var(--iw-bg-card);
    border: 1px solid var(--iw-border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.metric-card:hover { transform: translateY(-4px); box-shadow: 0 6px 24px rgba(81, 213, 90, 0.3); border-color: #51D55A; }
.metric-card.active { border-color: #51D55A; background: var(--iw-bg-surface); box-shadow: 0 6px 24px rgba(81, 213, 90, 0.4); }
.filter-tag { display: inline-block; background: #51D55A; color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 8px; margin-bottom: 8px; }
.filter-tag .close { margin-left: 8px; cursor: pointer; font-weight: bold; }
</style>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:18px; margin-bottom:12px;">📊 Data Mining Insights Dashboard</div>
    <div style="color:#E3E3E3; font-family:'Onest'; font-size:14px; margin-bottom:20px; opacity:0.8;">
        Advanced analytics powered by rule-based classification algorithms to optimize your inventory management. Click on any metric to filter products.
    </div>
    
    <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:20px;">
        <div class="metric-card" style=" background: #13275B; padding: 20px; border-radius: 12px; border: 2px solid #2A4484; text-align: center;" onclick="filterProducts('all')" data-filter="all">
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
                            <th>Product</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Sold</th>
                            <th>Classification</th>
                            <th>Days Since Restock</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <?php foreach ($detailedAnalysis as $item): ?>
                        <tr data-classification="<?php echo $item['classification']; ?>">
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo $item['sold_quantity']; ?></td>
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
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">⚠️ Anomaly Detection</div>
        <div style="overflow:auto; max-height:420px;">
            <?php if (empty($insights['anomalies'])): ?>
                <div style="color:#9aa6d1; text-align:center; padding:40px; font-size:14px;">
                    <div style="font-size:48px; margin-bottom:16px;">✅</div>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">📈 Classification Rules Applied</div>
    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            
            <div style="color:#10b981; font-weight:600; font-size:14px; margin-bottom:8px;">High Demand</div>
            <div style="color:#E3E3E3; font-size:12px;">Sold ≥ 80% of stock</div>
            
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#ef4444; font-weight:600; font-size:14px; margin-bottom:8px;"> Low Stock</div>
            <div style="color:#E3E3E3; font-size:12px;">Stock &lt; Reorder Point</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#f59e0b; font-weight:600; font-size:14px; margin-bottom:8px;"> Overstocked</div>
            <div style="color:#E3E3E3; font-size:12px;">Stock > 200 units</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#6b7280; font-weight:600; font-size:14px; margin-bottom:8px;">Inactive</div>
            <div style="color:#E3E3E3; font-size:12px;">Days since restock > 90</div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">🤖 AI Recommendations</div>
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
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">🔧 Analysis Tools</div>
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
        case 'high_demand':
            filterLabel = 'High Demand Products';
            break;
        case 'low_stock':
            filterLabel = 'Low Stock Products';
            break;
        case 'overstocked':
            filterLabel = 'Overstocked Products';
            break;
        case 'urgent':
            filterLabel = 'Urgent Action Required';
            break;
    }
    
    if (filterType !== 'all') {
        filterDisplay.innerHTML = `<span class="filter-tag">${filterLabel} <span class="close" onclick="filterProducts('all')">✕</span></span>`;
    } else {
        filterDisplay.innerHTML = '';
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
            // Show both high_demand and low_stock for urgent
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
    // Remove existing empty row if present first
    const existingEmpty = document.getElementById('emptyFilterRow');
    if (existingEmpty) existingEmpty.remove();

    if (visibleCount === 0 && filterType !== 'all') {
        const emptyRow = document.createElement('tr');
        emptyRow.id = 'emptyFilterRow';
        emptyRow.innerHTML = `
            <td colspan="6" style="text-align:center; padding:40px; color:#9aa6d1;">
                <div style="font-size:48px; margin-bottom:16px;">🔍</div>
                <div>No products found for this filter</div>
                <div style="font-size:12px; margin-top:8px; opacity:0.7;">Try a different classification</div>
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