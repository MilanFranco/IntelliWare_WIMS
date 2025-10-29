<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

require_once 'config/db.php';

// Include the data mining engine
require_once 'data_mining_engine.php';

// Perform data mining analysis
$miner = new InventoryDataMiner($conn);
$miningResults = $miner->performDataMining();
$detailedAnalysis = $miner->getDetailedAnalysis();
$classificationSummary = $miner->getAnalysisSummary();

// Get additional insights
$insights = [
    'total_products' => count($detailedAnalysis),
    'fast_moving' => $classificationSummary['fast_moving'] ?? 0,
    'low_stock' => $classificationSummary['low_stock'] ?? 0,
    'overstocked' => $classificationSummary['overstocked'] ?? 0,
    'inactive' => $classificationSummary['inactive'] ?? 0,
    'normal' => $classificationSummary['normal'] ?? 0,
    'urgent_reorders' => 0,
    'anomalies' => []
];

// Calculate urgent reorders (low stock + fast moving)
$insights['urgent_reorders'] = $insights['low_stock'] + $insights['fast_moving'];

// Detect anomalies (items that need attention)
foreach ($detailedAnalysis as $item) {
    if ($item['classification'] === 'low_stock' || 
        ($item['classification'] === 'fast_moving' && $item['days_since_restock'] > 30)) {
        $insights['anomalies'][] = [
            'product' => $item['product_name'],
            'issue' => $item['classification'] === 'low_stock' ? 'Low Stock Alert' : 'Fast Moving - Needs Restock',
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
.card { background:#1D387B; border:1px solid #2A4484; border-radius:12px; padding:20px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); margin-bottom:20px; }
.btn { background:#51D55A; color:#fff; border-radius:8px; padding:10px 16px; font-size:13px; text-decoration:none; border:1px solid transparent; cursor:pointer; font-weight:500; transition:all 0.2s ease; }
.btn:hover { background:#3d9743; transform:translateY(-1px); }
.btn-secondary { background:#13275B; color:#E3E3E3; border:1px solid #2A4484; }
.btn-secondary:hover { background:#1D387B; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { padding:12px; border-bottom:1px solid #2A4484; color:#E3E3E3; font-family:'Onest'; font-size:13px; }
.table th { background:#13275B; font-weight:600; }
.badge { padding:4px 10px; border-radius:12px; background:#13275B; color:#E3E3E3; font-size:11px; font-weight:500; }
.badge.fast-moving { background:#10b981; color:#fff; }
.badge.low-stock { background:#ef4444; color:#fff; }
.badge.overstocked { background:#f59e0b; color:#fff; }
.badge.inactive { background:#6b7280; color:#fff; }
.badge.normal { background:#3b82f6; color:#fff; }
.badge.high { background:#ef4444; color:#fff; }
.badge.medium { background:#f59e0b; color:#fff; }
</style>

<div class="card" style="margin-bottom:24px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:18px; margin-bottom:12px;">📊 Data Mining Insights Dashboard</div>
    <div style="color:#E3E3E3; font-family:'Onest'; font-size:14px; margin-bottom:20px; opacity:0.8;">
        Advanced analytics powered by rule-based classification algorithms to optimize your inventory management.
    </div>
    
    <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:20px;">
        <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484; text-align:center;">
            <div style="color:#51D55A; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['total_products']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Total Products</div>
        </div>
        <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484; text-align:center;">
            <div style="color:#10b981; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['fast_moving']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Fast Moving</div>
        </div>
        <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484; text-align:center;">
            <div style="color:#ef4444; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['low_stock']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Low Stock</div>
        </div>
        <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484; text-align:center;">
            <div style="color:#f59e0b; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['overstocked']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Overstocked</div>
        </div>
        <div style="background:#13275B; padding:20px; border-radius:12px; border:1px solid #2A4484; text-align:center;">
            <div style="color:#ef4444; font-weight:700; font-size:28px; margin-bottom:4px;"><?php echo $insights['urgent_reorders']; ?></div>
            <div style="color:#E3E3E3; font-size:13px; font-weight:500;">Urgent Actions</div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">🏷️ Inventory Classifications</div>
        <div style="overflow:auto; max-height:420px;">
            <?php if (empty($detailedAnalysis)): ?>
                <div style="color:#9aa6d1; text-align:center; padding:40px; font-size:14px;">
                    <div style="font-size:48px; margin-bottom:16px;">📊</div>
                    <div>No inventory data available</div>
                    <div style="font-size:12px; margin-top:8px; opacity:0.7;">Add products to see classifications</div>
                </div>
            <?php else: ?>
                <table class="table">
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
                    <tbody>
                        <?php foreach ($detailedAnalysis as $item): ?>
                        <tr>
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
        <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">⚠️ Anomaly Detection</div>
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
                    <tbody>
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
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">📈 Classification Rules Applied</div>
    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#10b981; font-weight:600; font-size:14px; margin-bottom:8px;">🚀 Fast Moving</div>
            <div style="color:#E3E3E3; font-size:12px;">Sold ≥ 80% of stock</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#ef4444; font-weight:600; font-size:14px; margin-bottom:8px;">📉 Low Stock</div>
            <div style="color:#E3E3E3; font-size:12px;">Stock < 10 units</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#f59e0b; font-weight:600; font-size:14px; margin-bottom:8px;">📦 Overstocked</div>
            <div style="color:#E3E3E3; font-size:12px;">Stock > 200 units</div>
        </div>
        <div style="background:#13275B; padding:16px; border-radius:8px; border:1px solid #2A4484;">
            <div style="color:#6b7280; font-weight:600; font-size:14px; margin-bottom:8px;">😴 Inactive</div>
            <div style="color:#E3E3E3; font-size:12px;">Days since restock > 90</div>
        </div>
    </div>
</div>

<div class="card">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">🔧 Analysis Tools</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
        <button class="btn" onclick="refreshAnalysis()">🔄 Refresh Analysis</button>
        <button class="btn btn-secondary" onclick="exportData()">📊 Export Data</button>
        <button class="btn btn-secondary" onclick="generateReport()">📋 Generate Report</button>
        <button class="btn btn-secondary" onclick="scheduleAnalysis()">⏰ Schedule Analysis</button>
    </div>
    <div style="color:#9aa6d1; font-family:'Onest'; font-size:12px; padding:12px; background:#13275B; border-radius:8px; border:1px solid #2A4484;">
        💡 <strong>Pro Tip:</strong> Run analysis daily for optimal inventory management. Set up automated alerts for urgent reorders.
    </div>
</div>

<script>
function refreshAnalysis() {
    location.reload();
}

function exportData() {
    // Create CSV data
    let csv = 'Product,Category,Stock,Sold,Classification,Days Since Restock,Sold Percentage\n';
    
    <?php foreach ($detailedAnalysis as $item): ?>
    csv += '<?php echo addslashes($item['product_name']); ?>,<?php echo addslashes($item['category']); ?>,<?php echo $item['quantity']; ?>,<?php echo $item['sold_quantity']; ?>,<?php echo $item['classification']; ?>,<?php echo $item['days_since_restock']; ?>,<?php echo $item['sold_percentage']; ?>%\n';
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'inventory_analysis_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function generateReport() {
    alert('Report generation feature coming soon! This will create a comprehensive PDF report with charts and recommendations.');
}

function scheduleAnalysis() {
    alert('Automated scheduling feature coming soon! Set up daily/weekly analysis runs.');
}
</script>

<?php
renderLayoutEnd();
?>