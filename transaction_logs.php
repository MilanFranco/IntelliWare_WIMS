<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

require_once 'config/db.php';

// --- SYSTEM EVENTS (LOGS) ---
// Optimization Note: Ensure 'timestamp' column in 'logs' table is INDEXED for speed.
$logs = [];
$logQuery = "SELECT l.log_id, l.action, l.timestamp, u.username 
             FROM logs l 
             LEFT JOIN users u ON l.user_id = u.user_id 
             ORDER BY l.timestamp DESC LIMIT 100";
$logResult = $conn->query($logQuery);

if ($logResult && $logResult->num_rows > 0) {
    while ($row = $logResult->fetch_assoc()) {
        $logs[] = $row;
    }
}

// --- STOCK MOVEMENTS (TRANSACTIONS) ---
// Used for the stat cards above and the "Recent Stock Moves" widget.
// Optimization Note: Ensure 'transaction_date' in 'transactions' table is INDEXED.
$transactions = [];
$transactionQuery = "SELECT t.*, p.product_name, p.category, u.username 
                     FROM transactions t 
                     LEFT JOIN products p ON t.product_id = p.product_id 
                     LEFT JOIN users u ON t.user_id = u.user_id 
                     ORDER BY t.transaction_date DESC LIMIT 100";
$transactionResult = $conn->query($transactionQuery);

if ($transactionResult && $transactionResult->num_rows > 0) {
    while ($row = $transactionResult->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// --- FULL TRANSACTION HISTORY (PAGINATED) ---
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }

$countResult = $conn->query("SELECT COUNT(*) as total FROM transactions");
$totalRows = $countResult ? (int)$countResult->fetch_assoc()['total'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$historyTransactions = [];
$historyQuery = "SELECT t.*, p.product_name, p.category, u.username 
                 FROM transactions t 
                 LEFT JOIN products p ON t.product_id = p.product_id 
                 LEFT JOIN users u ON t.user_id = u.user_id 
                 ORDER BY t.transaction_date DESC 
                 LIMIT $perPage OFFSET $offset";
$historyResult = $conn->query($historyQuery);
if ($historyResult && $historyResult->num_rows > 0) {
    while ($row = $historyResult->fetch_assoc()) {
        $historyTransactions[] = $row;
    }
}

$conn->close();

require_once 'src/layout.php';
renderLayoutStart('Transaction Logs', $role, $_SESSION['Username']);
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="premium-card p-6 flex items-center justify-between group hover:border-[#51D55A]/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">System Events</div>
            <div class="premium-metric">
                <span class="num text-3xl"><?php echo count($logs); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-[#51D55A]/10 flex items-center justify-center text-[#51D55A] group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-amber-500/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Movements</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo count($transactions); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"></path><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"></polygon></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-red-500/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Stock Out</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <?php echo count(array_filter($transactions, function($t) { return $t['transaction_type'] === 'stock_out'; })); ?>
                </span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-emerald-500/50 transition-all cursor-default">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Stock In</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #34d399 0%, #059669 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <?php echo count(array_filter($transactions, function($t) { return $t['transaction_type'] === 'stock_in'; })); ?>
                </span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <div class="premium-card flex flex-col h-[500px]">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                System Activity
            </h3>
            <span class="text-xs text-[#9aa6d1] font-onest bg-[#1D387B] px-3 py-1 rounded-full border border-[#2A4484]">Last 100 Events</span>
        </div>
        
        <div class="overflow-y-auto premium-scrollbar flex-1 p-2">
            <?php if (empty($logs)): ?>
                <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1]">
                    <svg class="w-12 h-12 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span>No logs found</span>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="sticky top-0 bg-[#1D387B] z-10 text-xs text-[#9aa6d1] font-onest text-left">
                        <tr>
                            <th class="p-3">User</th>
                            <th class="p-3">Action</th>
                            <th class="p-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach ($logs as $log): ?>
                        <tr class="border-b border-[#2A4484]/30 hover:bg-[#2A4484]/20 transition-colors">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs font-bold">
                                        <?php echo strtoupper(substr($log['username'] ?? '?', 0, 1)); ?>
                                    </div>
                                    <span class="text-[#E3E3E3] font-medium"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="text-[#9aa6d1]"><?php echo htmlspecialchars($log['action']); ?></span>
                            </td>
                            <td class="p-3 text-right text-xs text-[#6b7280]">
                                <?php echo date('M j, H:i', strtotime($log['timestamp'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="premium-card flex flex-col h-[500px]">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Recent Stock Moves
            </h3>
            <span class="text-xs text-[#9aa6d1] font-onest bg-[#1D387B] px-3 py-1 rounded-full border border-[#2A4484]">Realtime</span>
        </div>

        <div class="overflow-y-auto premium-scrollbar flex-1 p-2">
            <?php if (empty($transactions)): ?>
                <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1]">
                    <span>No transactions found</span>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="sticky top-0 bg-[#1D387B] z-10 text-xs text-[#9aa6d1] font-onest text-left">
                        <tr>
                            <th class="p-3">Product</th>
                            <th class="p-3 text-center">Type</th>
                            <th class="p-3 text-center">Qty</th>
                            <th class="p-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach ($transactions as $trans): ?>
                        <tr class="border-b border-[#2A4484]/30 hover:bg-[#2A4484]/20 transition-colors">
                            <td class="p-3">
                                <span class="text-[#E3E3E3] block"><?php echo htmlspecialchars($trans['product_name'] ?? 'Unknown'); ?></span>
                                <span class="text-[10px] text-[#9aa6d1]"><?php echo htmlspecialchars($trans['category'] ?? ''); ?></span>
                            </td>
                            <td class="p-3 text-center">
                                <?php if($trans['transaction_type'] === 'stock_in'): ?>
                                    <span class="px-2 py-1 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">IN</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 rounded text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20">OUT</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center font-mono text-[#E3E3E3]">
                                <?php echo $trans['quantity']; ?>
                            </td>
                            <td class="p-3 text-right text-xs text-[#6b7280]">
                                <?php echo date('H:i', strtotime($trans['transaction_date'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="premium-card mb-8">
    <div class="p-5 border-b border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-[#13275B]/30">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white">Full Transaction History</h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Detailed breakdown of all inventory movements</p>
        </div>
        
        <div class="flex gap-2 w-full md:w-auto">
            <div class="relative w-full md:w-64">
                <input type="text" id="logSearch" onkeyup="filterTable()" placeholder="Search logs..." 
                    class="w-full bg-[#1D387B] border border-[#2A4484] text-[#E3E3E3] text-sm rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:border-[#51D55A] focus:ring-1 focus:ring-[#51D55A] transition-all placeholder-[#9aa6d1]">
                <svg class="absolute left-3 top-2.5 w-4 h-4 text-[#9aa6d1]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            
            <button onclick="exportLogs()" class="premium-button text-xs py-2 px-4 whitespace-nowrap flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="premium-table w-full text-left" id="mainLogTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>User</th>
                    <th>Date</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyTransactions)): ?>
                <tr>
                    <td colspan="8" class="text-center text-[#9aa6d1] py-6">No transactions found</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($historyTransactions as $trans): ?>
                <tr class="hover:bg-[#1D387B]/50 transition-colors">
                    <td class="font-mono text-xs text-[#9aa6d1]">#<?php echo $trans['transaction_id']; ?></td>
                    <td class="font-medium text-[#E3E3E3]"><?php echo htmlspecialchars($trans['product_name'] ?? 'Unknown'); ?></td>
                    <td class="text-sm text-[#9aa6d1]"><?php echo htmlspecialchars($trans['category'] ?? '-'); ?></td>
                    <td>
                        <?php if($trans['transaction_type'] === 'stock_in'): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                STOCK IN
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                                STOCK OUT
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="font-mono font-semibold text-[#E3E3E3]"><?php echo $trans['quantity']; ?></td>
                    <td class="text-sm">
                        <div class="flex items-center gap-2">
                            <?php renderUserAvatar($trans['username'] ?? 'U', 20, 'iw-avatar-initials--sm'); ?>
                            <?php echo htmlspecialchars($trans['username'] ?? 'Unknown'); ?>
                        </div>
                    </td>
                    <td class="text-xs text-[#9aa6d1] whitespace-nowrap"><?php echo date('M j, Y H:i', strtotime($trans['transaction_date'])); ?></td>
                    <td class="text-sm text-[#9aa6d1] italic max-w-[200px] truncate"><?php echo htmlspecialchars($trans['remarks'] ?? '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-between items-center p-4 border-t border-[#2A4484]/50 bg-[#13275B]/30">
        <span class="text-xs text-[#9aa6d1] font-onest">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; <?php echo $totalRows; ?> records
        </span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary text-xs py-2 px-4 whitespace-nowrap">Previous</a>
            <?php else: ?>
                <span class="btn btn-secondary text-xs py-2 px-4 whitespace-nowrap opacity-40 cursor-not-allowed pointer-events-none">Previous</span>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary text-xs py-2 px-4 whitespace-nowrap">Next</a>
            <?php else: ?>
                <span class="btn btn-secondary text-xs py-2 px-4 whitespace-nowrap opacity-40 cursor-not-allowed pointer-events-none">Next</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Search only filters within the current page (10 rows) since pagination is server-side
function filterTable() {
    var input, filter, table, tr, i;
    input = document.getElementById("logSearch");
    filter = input.value.toUpperCase();
    table = document.getElementById("mainLogTable");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) { // Start at 1 to skip header
        var tdProduct = tr[i].getElementsByTagName("td")[1];
        var tdRemarks = tr[i].getElementsByTagName("td")[7];

        if (tdProduct || tdRemarks) {
            var txtProduct = tdProduct ? (tdProduct.textContent || tdProduct.innerText) : "";
            var txtRemarks = tdRemarks ? (tdRemarks.textContent || tdRemarks.innerText) : "";

            if (txtProduct.toUpperCase().indexOf(filter) > -1 || txtRemarks.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

function exportLogs() {
    let csv = 'Transaction ID,Product,Category,Type,Quantity,User,Date,Remarks\n';
    
    <?php foreach ($historyTransactions as $trans): ?>
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
</script>

<?php
renderLayoutEnd();
?>