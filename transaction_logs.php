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

<style>
/* ── Transaction History responsive layout ──────────────────────── */
.txnlog-desktop { display: block; }
.txnlog-mobile  { display: none;  }
@media (max-width: 767px) {
    .txnlog-desktop { display: none;  }
    .txnlog-mobile  { display: block; }
}

/* ── Accordion list ─────────────────────────────────────────────── */
.txnacc-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px;
}

/* Each accordion item */
.txnacc-item {
    background: linear-gradient(160deg, #1b2f6e 0%, #111e4a 100%);
    border: 1px solid rgba(42, 68, 132, 0.55);
    border-radius: 14px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.txnacc-item.is-open { border-color: rgba(81, 213, 90, 0.25); }

/* Clickable header — always visible */
.txnacc-header {
    display: flex;
    align-items: stretch;
    padding: 13px 14px;
    cursor: pointer;
    gap: 10px;
    user-select: none;
}

/* Left column: badge + date */
.txnacc-left { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 0; }

/* Product name */
.txnacc-product {
    font-family: 'Onest', sans-serif;
    font-size: 14px; font-weight: 700;
    color: #e3e3e3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Badge row: badge + date side by side */
.txnacc-badge-row { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
.txnacc-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-family: 'Onest', sans-serif;
    font-size: 10px; font-weight: 800; letter-spacing: 0.06em;
    padding: 3px 7px; border-radius: 5px; white-space: nowrap; flex-shrink: 0;
}
.txnacc-badge svg { width: 11px; height: 11px; }
.badge-in  { background: rgba(52,211,153,0.14); color: #34d399; border: 1px solid rgba(52,211,153,0.3); }
.badge-out { background: rgba(248,113,113,0.14); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }
.txnacc-date {
    font-family: 'Onest', sans-serif;
    font-size: 11px; color: #9aa6d1;
    white-space: nowrap;
}

/* Right column: qty + chevron */
.txnacc-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.txnacc-qty {
    font-family: 'Onest', sans-serif;
    font-size: 18px; font-weight: 800; line-height: 1;
}
.qty-in  { color: #34d399; }
.qty-out { color: #f87171; }
.txnacc-chevron {
    color: #9aa6d1; line-height: 1; font-size: 14px; transition: transform 0.25s ease;
}
.txnacc-item.is-open .txnacc-chevron { transform: rotate(180deg); }

/* Expandable body */
.txnacc-body {
    display: none;
    border-top: 1px solid rgba(42, 68, 132, 0.4);
    padding: 12px 14px;
}
.txnacc-item.is-open .txnacc-body { display: block; }

/* Detail rows inside body */
.txnacc-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 5px 0;
    border-bottom: 1px solid rgba(42, 68, 132, 0.2);
}
.txnacc-row:last-child { border-bottom: none; }
.txnacc-row-label {
    font-family: 'Onest', sans-serif;
    font-size: 12px; color: #6b7aab;
}
.txnacc-row-value {
    font-family: 'Onest', sans-serif;
    font-size: 13px; font-weight: 600; color: #e3e3e3;
    text-align: right; max-width: 65%;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.txnacc-row-value.mono { font-family: monospace; }

/* Remarks line */
.txnacc-remarks-row {
    margin-top: 8px;
    font-family: 'Onest', sans-serif;
    font-size: 12px; color: #9aa6d1; font-style: italic;
}

/* Light theme overrides */
[data-theme="light"] .txnacc-item        { background: var(--iw-bg-modal) !important; border-color: var(--iw-border-solid) !important; }
[data-theme="light"] .txnacc-product     { color: var(--iw-text-header) !important; }
[data-theme="light"] .txnacc-date        { color: var(--iw-text-muted) !important; }
[data-theme="light"] .txnacc-row-label   { color: var(--iw-text-muted) !important; }
[data-theme="light"] .txnacc-row-value   { color: var(--iw-text-header) !important; }
[data-theme="light"] .txnacc-body        { border-color: var(--iw-border) !important; }
[data-theme="light"] .txnacc-row         { border-color: var(--iw-border) !important; }
[data-theme="light"] .txnacc-remarks-row { color: var(--iw-text-muted) !important; }

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
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#9aa6d1] mb-1">
                <span>IntelliWare Enterprise WIMS</span>
                <span class="text-[#2A4484]">•</span>
                <span class="text-[#51D55A]">System Audit</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 019 9v.375M10.125 2.25A3.375 3.375 0 016.75 5.625v1.5c0 .621.504 1.125 1.125 1.125h.375"></path></svg>
                </div>
                Transaction Logs &amp; Audit Stream
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Monitor real-time warehouse system logs, user actions, inventory status transitions, and administrative operations across all modules.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Logging: <strong class="text-white">Active Audit</strong>
            </span>
        </div>
    </div>
</div>

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

    <!-- Desktop / Tablet: Table (≥768px) -->
    <div class="txnlog-desktop">
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
    </div><!-- /.txnlog-desktop -->

    <!-- Mobile: Accordion (<768px) -->
    <div class="txnlog-mobile" id="txnlog-mobile-grid">
        <?php if (empty($historyTransactions)): ?>
            <div class="text-center text-[#9aa6d1] py-6">No transactions found</div>
        <?php else: ?>
        <div class="txnacc-list">
            <?php foreach ($historyTransactions as $trans): ?>
            <?php
                $isIn      = $trans['transaction_type'] === 'stock_in';
                $qtySign   = $isIn ? '+' : '−';
                $qtyClass  = $isIn ? 'qty-in' : 'qty-out';
                $badgeClass = $isIn ? 'badge-in' : 'badge-out';
                $badgeText  = $isIn ? 'STOCK IN' : 'STOCK OUT';
                $remarks    = trim($trans['remarks'] ?? '');
            ?>
            <div class="txnacc-item"
                 data-product="<?php echo htmlspecialchars(strtolower($trans['product_name'] ?? '')); ?>"
                 data-remarks="<?php echo htmlspecialchars(strtolower($remarks)); ?>">

                <!-- Header (always visible, click to toggle) -->
                <div class="txnacc-header" onclick="txnToggle(this)">
                    <div class="txnacc-left">
                        <div class="txnacc-product"><?php echo htmlspecialchars($trans['product_name'] ?? 'Unknown'); ?></div>
                        <div class="txnacc-badge-row">
                            <span class="txnacc-badge <?php echo $badgeClass; ?>">
                                <?php if ($isIn): ?>
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                <?php else: ?>
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                <?php endif; ?>
                                <?php echo $badgeText; ?>
                            </span>
                            <span class="txnacc-date"><?php echo date('M j, g:i A', strtotime($trans['transaction_date'])); ?></span>
                        </div>
                    </div>
                    <div class="txnacc-right">
                        <span class="txnacc-qty <?php echo $qtyClass; ?>"><?php echo $qtySign . $trans['quantity']; ?></span>
                        <span class="txnacc-chevron">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </div>
                </div>

                <!-- Expanded body -->
                <div class="txnacc-body">
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">ID</span>
                        <span class="txnacc-row-value mono">#<?php echo $trans['transaction_id']; ?></span>
                    </div>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">Category</span>
                        <span class="txnacc-row-value"><?php echo htmlspecialchars($trans['category'] ?? '—'); ?></span>
                    </div>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">User</span>
                        <span class="txnacc-row-value"><?php echo htmlspecialchars($trans['username'] ?? '—'); ?></span>
                    </div>
                    <?php if ($remarks !== '' && $remarks !== '-'): ?>
                    <div class="txnacc-remarks-row">"<?php echo htmlspecialchars($remarks); ?>"</div>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div><!-- /.txnlog-mobile -->

    <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-[#2A4484]/50 bg-[#13275B]/30 gap-4 flex-wrap">
        <?php 
            $showing_start = ($totalRows > 0) ? (($page - 1) * $perPage + 1) : 0;
            $showing_end = min($page * $perPage, $totalRows);
        ?>
        <div class="pagination-info-pill">
            Showing <?php echo $showing_start; ?> to <?php echo $showing_end; ?> of <?php echo $totalRows; ?> entries
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn pagination-nav">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    Previous
                </a>
            <?php else: ?>
                <button type="button" class="pagination-btn pagination-nav" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    Previous
                </button>
            <?php endif; ?>

            <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                if ($startPage > 1) {
                    echo '<a href="?page=1" class="pagination-btn pagination-num">1</a>';
                    if ($startPage > 2) {
                        echo '<span style="color:#6b7aab; padding:0 4px; display:inline-flex; align-items:center;">&hellip;</span>';
                    }
                }
                for ($p = $startPage; $p <= $endPage; $p++) {
                    if ($p === $page) {
                        echo '<button type="button" class="pagination-btn pagination-num active" disabled>' . $p . '</button>';
                    } else {
                        echo '<a href="?page=' . $p . '" class="pagination-btn pagination-num">' . $p . '</a>';
                    }
                }
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span style="color:#6b7aab; padding:0 4px; display:inline-flex; align-items:center;">&hellip;</span>';
                    }
                    echo '<a href="?page=' . $totalPages . '" class="pagination-btn pagination-num">' . $totalPages . '</a>';
                }
            ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn pagination-nav">
                    Next
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            <?php else: ?>
                <button type="button" class="pagination-btn pagination-nav" disabled>
                    Next
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Search filters both the desktop table and the mobile accordion
function filterTable() {
    var filter = document.getElementById("logSearch").value.toUpperCase();

    // --- Desktop table ---
    var table = document.getElementById("mainLogTable");
    if (table) {
        var tr = table.getElementsByTagName("tr");
        for (var i = 1; i < tr.length; i++) {
            var tdProduct = tr[i].getElementsByTagName("td")[1];
            var tdRemarks = tr[i].getElementsByTagName("td")[7];
            var txtProduct = tdProduct ? (tdProduct.textContent || tdProduct.innerText) : "";
            var txtRemarks = tdRemarks ? (tdRemarks.textContent || tdRemarks.innerText) : "";
            tr[i].style.display = (txtProduct.toUpperCase().indexOf(filter) > -1 || txtRemarks.toUpperCase().indexOf(filter) > -1) ? "" : "none";
        }
    }

    // --- Mobile accordion items ---
    document.querySelectorAll("#txnlog-mobile-grid .txnacc-item").forEach(function(item) {
        var product = (item.dataset.product || "").toUpperCase();
        var remarks = (item.dataset.remarks || "").toUpperCase();
        item.style.display = (product.indexOf(filter) > -1 || remarks.indexOf(filter) > -1) ? "" : "none";
    });
}

// Accordion toggle
function txnToggle(header) {
    var item = header.closest('.txnacc-item');
    item.classList.toggle('is-open');
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