<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

require_once 'config/db.php';

// Get transaction logs from database
$logs = [];
$logQuery = "SELECT l.*, u.username, u.full_name FROM logs l 
             LEFT JOIN users u ON l.user_id = u.user_id 
             ORDER BY l.timestamp DESC LIMIT 100";
$logResult = $conn->query($logQuery);

if ($logResult && $logResult->num_rows > 0) {
    while ($row = $logResult->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Get transaction history
$transactions = [];
$transactionQuery = "SELECT t.*, p.product_name, p.category, u.username FROM transactions t 
                    LEFT JOIN products p ON t.product_id = p.product_id 
                    LEFT JOIN users u ON t.user_id = u.user_id 
                    ORDER BY t.transaction_date DESC LIMIT 100";
$transactionResult = $conn->query($transactionQuery);

if ($transactionResult && $transactionResult->num_rows > 0) {
    while ($row = $transactionResult->fetch_assoc()) {
        $transactions[] = $row;
    }
}

$conn->close();

require_once 'src/layout.php';
renderLayoutStart('Transaction Logs', $role, $_SESSION['Username']);
?>

<style>
.card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
.btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
.btn:hover { background:#3d9743; }
.btn-secondary { background:#13275B; color:#E3E3E3; border:1px solid #2A4484; }
.btn-secondary:hover { background:#1D387B; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { padding:10px; border-bottom:1px solid #2A4484; color:#E3E3E3; font-family:'Onest'; font-size:12px; }
.badge { padding:2px 8px; border-radius:999px; background:#13275B; color:#E3E3E3; font-size:11px; }
.badge.in { background:#10b981; color:#fff; }
.badge.out { background:#ef4444; color:#fff; }
.badge.login { background:#3b82f6; color:#fff; }
.badge.action { background:#8b5cf6; color:#fff; }
</style>

<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:20px;">
    <div class="card">
        <div style="color:#51D55A; font-weight:600; font-size:24px;"><?php echo count($logs); ?></div>
        <div style="color:#E3E3E3; font-size:12px;">Total Log Entries</div>
    </div>
    <div class="card">
        <div style="color:#f59e0b; font-weight:600; font-size:24px;"><?php echo count($transactions); ?></div>
        <div style="color:#E3E3E3; font-size:12px;">Total Transactions</div>
    </div>
    <div class="card">
        <div style="color:#ef4444; font-weight:600; font-size:24px;"><?php echo count(array_filter($transactions, function($t) { return $t['transaction_type'] === 'stock_out'; })); ?></div>
        <div style="color:#E3E3E3; font-size:12px;">Stock Out</div>
    </div>
    <div class="card">
        <div style="color:#10b981; font-weight:600; font-size:24px;"><?php echo count(array_filter($transactions, function($t) { return $t['transaction_type'] === 'stock_in'; })); ?></div>
        <div style="color:#E3E3E3; font-size:12px;">Stock In</div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">📋 System Activity Logs</div>
        <div style="overflow:auto; max-height:400px;">
            <?php if (empty($logs)): ?>
                <div style="color:#9aa6d1; text-align:center; padding:20px;">No activity logs found</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($logs, 0, 20) as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                            <td>
                                <span class="badge action"><?php echo htmlspecialchars($log['action']); ?></span>
                            </td>
                            <td><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">📦 Recent Transactions</div>
        <div style="overflow:auto; max-height:400px;">
            <?php if (empty($transactions)): ?>
                <div style="color:#9aa6d1; text-align:center; padding:20px;">No transactions found</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>User</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($transactions, 0, 20) as $trans): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trans['product_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <span class="badge <?php echo $trans['transaction_type']; ?>">
                                    <?php echo $trans['transaction_type'] === 'stock_in' ? '📥 In' : '📤 Out'; ?>
                                </span>
                            </td>
                            <td><?php echo $trans['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($trans['username'] ?? 'Unknown'); ?></td>
                            <td><?php echo date('M j, H:i', strtotime($trans['transaction_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">📊 Transaction History</div>
    <div style="overflow:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>User</th>
                    <th>Date</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td>#<?php echo $trans['transaction_id']; ?></td>
                    <td><?php echo htmlspecialchars($trans['product_name'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($trans['category'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="badge <?php echo $trans['transaction_type']; ?>">
                            <?php echo $trans['transaction_type'] === 'stock_in' ? '📥 Stock In' : '📤 Stock Out'; ?>
                        </span>
                    </td>
                    <td><?php echo $trans['quantity']; ?></td>
                    <td><?php echo htmlspecialchars($trans['username'] ?? 'Unknown'); ?></td>
                    <td><?php echo date('M j, Y H:i', strtotime($trans['transaction_date'])); ?></td>
                    <td><?php echo htmlspecialchars($trans['remarks'] ?? 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">🔧 Log Management</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <button class="btn" onclick="refreshLogs()">🔄 Refresh Logs</button>
        <button class="btn btn-secondary" onclick="exportLogs()">📊 Export Logs</button>
        <button class="btn btn-secondary" onclick="clearOldLogs()">🗑️ Clear Old Logs</button>
        <button class="btn btn-secondary" onclick="generateReport()">📋 Generate Report</button>
    </div>
    <div style="color:#9aa6d1; font-family:'Onest'; font-size:12px; margin-top:12px;">
        💡 <strong>Pro Tip:</strong> Logs are automatically generated for all system activities. Use filters to find specific events.
    </div>
</div>

<script>
function refreshLogs() {
    location.reload();
}

function exportLogs() {
    // Create CSV data
    let csv = 'Transaction ID,Product,Category,Type,Quantity,User,Date,Remarks\n';
    
    <?php foreach ($transactions as $trans): ?>
    csv += '<?php echo $trans['transaction_id']; ?>,<?php echo addslashes($trans['product_name'] ?? 'Unknown'); ?>,<?php echo addslashes($trans['category'] ?? 'N/A'); ?>,<?php echo $trans['transaction_type']; ?>,<?php echo $trans['quantity']; ?>,<?php echo addslashes($trans['username'] ?? 'Unknown'); ?>,<?php echo date('Y-m-d H:i', strtotime($trans['transaction_date'])); ?>,<?php echo addslashes($trans['remarks'] ?? 'N/A'); ?>\n';
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'transaction_logs_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function clearOldLogs() {
    if (confirm('Are you sure you want to clear logs older than 30 days?')) {
        alert('This feature will be implemented in the next update.');
    }
}

function generateReport() {
    alert('Report generation feature coming soon! This will create a comprehensive PDF report.');
}
</script>

<?php
renderLayoutEnd();
?>