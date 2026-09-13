<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header('Location: signin.php');
    exit();
}

$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager', 'staff'], true)) {
    header('Location: signin.php');
    exit();
}

require_once 'config/db.php';

$search = trim($_GET['q'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterProduct = trim($_GET['product'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$hasAudit = false;
$auditCheck = $conn->query("SHOW COLUMNS FROM transactions LIKE 'previous_stock'");
if ($auditCheck && $auditCheck->num_rows > 0) {
    $hasAudit = true;
}

$sql = "SELECT t.transaction_id, t.transaction_type, t.quantity, t.transaction_date, t.remarks,
               t.barcode AS txn_barcode,
               " . ($hasAudit ? "t.previous_stock, t.new_stock," : "") . "
               p.product_id, p.product_name, p.barcode AS product_barcode,
               u.username
        FROM transactions t
        LEFT JOIN products p ON t.product_id = p.product_id
        LEFT JOIN users u ON t.user_id = u.user_id
        WHERE 1=1";

$countSql = "SELECT COUNT(*) AS total
             FROM transactions t
             LEFT JOIN products p ON t.product_id = p.product_id
             LEFT JOIN users u ON t.user_id = u.user_id
             WHERE 1=1";

$params = [];
$types = '';
$filterClause = '';

if ($filterType === 'in') {
    $filterClause .= " AND t.transaction_type = 'stock_in'";
} elseif ($filterType === 'out') {
    $filterClause .= " AND t.transaction_type = 'stock_out'";
}

if ($search !== '') {
    $filterClause .= " AND (p.product_name LIKE ? OR t.barcode LIKE ? OR p.barcode LIKE ? OR CAST(t.transaction_id AS CHAR) LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'ssss';
}

if ($filterProduct !== '') {
    $filterClause .= " AND p.product_name LIKE ?";
    $params[] = '%' . $filterProduct . '%';
    $types .= 's';
}

if ($dateFrom !== '') {
    $filterClause .= " AND DATE(t.transaction_date) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $filterClause .= " AND DATE(t.transaction_date) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$sql .= $filterClause;
$countSql .= $filterClause;

$countParams = $params;
$countTypes = $types;

$countStmt = $conn->prepare($countSql);
if ($countTypes !== '') {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));

$sql .= " ORDER BY t.transaction_date DESC LIMIT ? OFFSET ?";
$listParams = $params;
$listTypes = $types;
$listParams[] = $limit;
$listParams[] = $offset;
$listTypes .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();
$conn->close();

require_once 'src/layout.php';
renderLayoutStart('Transaction History', $role, $_SESSION['Username']);
?>

<style>
/* ── Responsive layout ─────────────────────────────────────────── */
.ih-desktop { display: block; }
.ih-mobile  { display: none;  }
@media (max-width: 767px) {
    .ih-desktop { display: none;  }
    .ih-mobile  { display: block; }
}

/* ── Accordion (reuses .txnacc-* from transaction_logs.php) ──── */
.txnacc-list { display: flex; flex-direction: column; gap: 10px; padding: 14px; }
.txnacc-item {
    background: linear-gradient(160deg, #1b2f6e 0%, #111e4a 100%);
    border: 1px solid rgba(42,68,132,0.55);
    border-radius: 14px; overflow: hidden; transition: border-color 0.2s;
}
.txnacc-item.is-open { border-color: rgba(81,213,90,0.25); }
.txnacc-header {
    display: flex; align-items: stretch; padding: 13px 14px;
    cursor: pointer; gap: 10px; user-select: none;
}
.txnacc-left  { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 0; }
.txnacc-product {
    font-family: 'Onest', sans-serif; font-size: 14px; font-weight: 700; color: #e3e3e3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.txnacc-badge-row { display: flex; align-items: center; gap: 8px; }
.txnacc-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-family: 'Onest', sans-serif; font-size: 10px; font-weight: 800;
    letter-spacing: 0.06em; padding: 3px 7px; border-radius: 5px; white-space: nowrap; flex-shrink: 0;
}
.txnacc-badge svg { width: 11px; height: 11px; }
.badge-in  { background: rgba(52,211,153,0.14); color: #34d399; border: 1px solid rgba(52,211,153,0.3); }
.badge-out { background: rgba(248,113,113,0.14); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }
.txnacc-date { font-family: 'Onest', sans-serif; font-size: 11px; color: #9aa6d1; white-space: nowrap; }
.txnacc-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.txnacc-qty   { font-family: 'Onest', sans-serif; font-size: 18px; font-weight: 800; line-height: 1; }
.qty-in  { color: #34d399; }
.qty-out { color: #f87171; }
.txnacc-chevron { color: #9aa6d1; line-height: 1; transition: transform 0.25s ease; }
.txnacc-item.is-open .txnacc-chevron { transform: rotate(180deg); }
.txnacc-body {
    display: none; border-top: 1px solid rgba(42,68,132,0.4); padding: 12px 14px;
}
.txnacc-item.is-open .txnacc-body { display: block; }
.txnacc-row {
    display: flex; justify-content: space-between; align-items: baseline;
    padding: 5px 0; border-bottom: 1px solid rgba(42,68,132,0.2);
}
.txnacc-row:last-child { border-bottom: none; }
.txnacc-row-label { font-family: 'Onest', sans-serif; font-size: 12px; color: #6b7aab; }
.txnacc-row-value {
    font-family: 'Onest', sans-serif; font-size: 13px; font-weight: 600; color: #e3e3e3;
    text-align: right; max-width: 65%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.txnacc-row-value.mono { font-family: monospace; }
.txnacc-remarks-row {
    margin-top: 8px; font-family: 'Onest', sans-serif;
    font-size: 12px; color: #9aa6d1; font-style: italic;
}
/* Light theme */
[data-theme="light"] .txnacc-item      { background: var(--iw-bg-modal) !important; border-color: var(--iw-border-solid) !important; }
[data-theme="light"] .txnacc-product   { color: var(--iw-text-header) !important; }
[data-theme="light"] .txnacc-date      { color: var(--iw-text-muted) !important; }
[data-theme="light"] .txnacc-row-label { color: var(--iw-text-muted) !important; }
[data-theme="light"] .txnacc-row-value { color: var(--iw-text-header) !important; }
[data-theme="light"] .txnacc-body,
[data-theme="light"] .txnacc-row       { border-color: var(--iw-border) !important; }
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
                <span class="text-[#51D55A]">Audit Trail</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                Transaction History &amp; Records
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Review chronological inbound and outbound inventory movements, previous and new stock levels, operator remarks, and timestamps.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Records: <strong class="text-white"><?php echo number_format($totalRows); ?> Logged</strong>
            </span>
        </div>
    </div>
</div>

<div class="premium-card mb-8" style="padding:0;overflow:visible;">
    <form method="GET" class="p-6 border-b border-[#2A4484]/50 bg-[#13275B]/30">
        <div class="flex flex-col lg:flex-row lg:items-end gap-4">
            <div class="flex-1">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">Search</label>
                <input type="text" name="q" class="premium-input w-full" placeholder="ID, product, barcode..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="w-full lg:w-40">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">Type</label>
                <select name="type" class="premium-input inventory-select w-full cursor-pointer">
                    <option value="">All Types</option>
                    <option value="in" <?php echo $filterType === 'in' ? 'selected' : ''; ?>>IN (Receive)</option>
                    <option value="out" <?php echo $filterType === 'out' ? 'selected' : ''; ?>>OUT (Release)</option>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">Product</label>
                <input type="text" name="product" class="premium-input w-full" placeholder="Filter by product name..."
                       value="<?php echo htmlspecialchars($filterProduct); ?>">
            </div>
            <div class="w-full lg:w-36">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">From</label>
                <input type="date" name="date_from" class="premium-input w-full"
                       value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="w-full lg:w-36">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">To</label>
                <input type="date" name="date_to" class="premium-input w-full"
                       value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="premium-button" style="height:46px;">Filter</button>
                <a href="inventory_history.php" class="btn-secondary flex items-center" style="height:46px;">Reset</a>
            </div>
        </div>
    </form>

    <div class="p-4 border-b border-[#2A4484]/30 text-xs text-[#9aa6d1]">
        Showing <?php echo count($transactions); ?> of <?php echo $totalRows; ?> transactions
        (page <?php echo $page; ?> of <?php echo $totalPages; ?>)
    </div>

    <!-- Desktop / Tablet: Table (≥768px) -->
    <div class="ih-desktop">
        <div class="overflow-x-auto">
            <table class="premium-table w-full text-left">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date &amp; Time</th>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th>Type</th>
                        <th class="text-center">Qty</th>
                        <?php if ($hasAudit): ?>
                            <th class="text-center">Prev Stock</th>
                            <th class="text-center">New Stock</th>
                        <?php endif; ?>
                        <th>User</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="<?php echo $hasAudit ? 10 : 8; ?>" class="text-center p-8 text-[#9aa6d1]">
                                No transactions found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $txn): ?>
                            <?php
                            $isIn    = $txn['transaction_type'] === 'stock_in';
                            $barcode = $txn['txn_barcode'] ?? $txn['product_barcode'] ?? '—';
                            ?>
                            <tr class="hover:bg-[#1D387B]/50 transition-colors">
                                <td class="font-mono text-xs text-[#9aa6d1]">#<?php echo (int)$txn['transaction_id']; ?></td>
                                <td class="text-sm text-[#E3E3E3] whitespace-nowrap"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($txn['transaction_date']))); ?></td>
                                <td class="font-medium text-[#E3E3E3]"><?php echo htmlspecialchars($txn['product_name'] ?? 'Unknown'); ?></td>
                                <td class="font-mono text-xs text-[#9aa6d1]"><?php echo htmlspecialchars($barcode ?: '—'); ?></td>
                                <td>
                                    <?php if ($isIn): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">IN</span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-mono font-semibold text-[#E3E3E3]"><?php echo (int)$txn['quantity']; ?></td>
                                <?php if ($hasAudit): ?>
                                    <td class="text-center font-mono text-sm text-[#9aa6d1]"><?php echo $txn['previous_stock'] !== null ? (int)$txn['previous_stock'] : '—'; ?></td>
                                    <td class="text-center font-mono text-sm text-[#E3E3E3]"><?php echo $txn['new_stock'] !== null ? (int)$txn['new_stock'] : '—'; ?></td>
                                <?php endif; ?>
                                <td class="text-sm text-[#9aa6d1]"><?php echo htmlspecialchars($txn['username'] ?? '—'); ?></td>
                                <td class="text-sm text-[#6b7280] max-w-[180px] truncate" title="<?php echo htmlspecialchars($txn['remarks'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($txn['remarks'] ?? '—'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /.ih-desktop -->

    <!-- Mobile: Accordion (<768px) -->
    <div class="ih-mobile">
        <?php if (empty($transactions)): ?>
            <div class="text-center text-[#9aa6d1] py-6">No transactions found.</div>
        <?php else: ?>
        <div class="txnacc-list">
            <?php foreach ($transactions as $txn): ?>
            <?php
                $isIn     = $txn['transaction_type'] === 'stock_in';
                $qtySign  = $isIn ? '+' : '−';
                $qtyClass = $isIn ? 'qty-in' : 'qty-out';
                $badgeClass = $isIn ? 'badge-in' : 'badge-out';
                $badgeText  = $isIn ? 'IN' : 'OUT';
                $barcode  = $txn['txn_barcode'] ?? $txn['product_barcode'] ?? '—';
                $remarks  = trim($txn['remarks'] ?? '');
            ?>
            <div class="txnacc-item">
                <!-- Header -->
                <div class="txnacc-header" onclick="this.closest('.txnacc-item').classList.toggle('is-open')">
                    <div class="txnacc-left">
                        <div class="txnacc-product"><?php echo htmlspecialchars($txn['product_name'] ?? 'Unknown'); ?></div>
                        <div class="txnacc-badge-row">
                            <span class="txnacc-badge <?php echo $badgeClass; ?>">
                                <?php if ($isIn): ?>
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                <?php else: ?>
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                <?php endif; ?>
                                STOCK <?php echo $badgeText; ?>
                            </span>
                            <span class="txnacc-date"><?php echo date('M j, Y g:i A', strtotime($txn['transaction_date'])); ?></span>
                        </div>
                    </div>
                    <div class="txnacc-right">
                        <span class="txnacc-qty <?php echo $qtyClass; ?>"><?php echo $qtySign . (int)$txn['quantity']; ?></span>
                        <span class="txnacc-chevron">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </div>
                </div>
                <!-- Expanded body -->
                <div class="txnacc-body">
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">ID</span>
                        <span class="txnacc-row-value mono">#<?php echo (int)$txn['transaction_id']; ?></span>
                    </div>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">Barcode</span>
                        <span class="txnacc-row-value mono"><?php echo htmlspecialchars($barcode ?: '—'); ?></span>
                    </div>
                    <?php if ($hasAudit): ?>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">Prev Stock</span>
                        <span class="txnacc-row-value mono"><?php echo $txn['previous_stock'] !== null ? (int)$txn['previous_stock'] : '—'; ?></span>
                    </div>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">New Stock</span>
                        <span class="txnacc-row-value mono"><?php echo $txn['new_stock'] !== null ? (int)$txn['new_stock'] : '—'; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">User</span>
                        <span class="txnacc-row-value"><?php echo htmlspecialchars($txn['username'] ?? '—'); ?></span>
                    </div>
                    <?php if ($remarks !== '' && $remarks !== '—' && $remarks !== '-'): ?>
                    <div class="txnacc-remarks-row">"<?php echo htmlspecialchars($remarks); ?>"</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div><!-- /.ih-mobile -->

    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-4 flex-wrap">
            <?php
            $qsParams = $_GET;
            unset($qsParams['page']);
            $qs = http_build_query($qsParams);
            $qsPrefix = $qs ? $qs . '&' : '';
            $showing_start = ($totalRows > 0) ? (($page - 1) * $limit + 1) : 0;
            $showing_end = min($page * $limit, $totalRows);
            ?>
            <div class="pagination-info-pill">
                Showing <?php echo $showing_start; ?> to <?php echo $showing_end; ?> of <?php echo $totalRows; ?> entries
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo $qsPrefix; ?>page=<?php echo $page - 1; ?>" class="pagination-btn pagination-nav">
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
                        echo '<a href="?' . $qsPrefix . 'page=1" class="pagination-btn pagination-num">1</a>';
                        if ($startPage > 2) {
                            echo '<span style="color:#6b7aab; padding:0 4px; display:inline-flex; align-items:center;">&hellip;</span>';
                        }
                    }
                    for ($p = $startPage; $p <= $endPage; $p++) {
                        if ($p === $page) {
                            echo '<button type="button" class="pagination-btn pagination-num active" disabled>' . $p . '</button>';
                        } else {
                            echo '<a href="?' . $qsPrefix . 'page=' . $p . '" class="pagination-btn pagination-num">' . $p . '</a>';
                        }
                    }
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span style="color:#6b7aab; padding:0 4px; display:inline-flex; align-items:center;">&hellip;</span>';
                        }
                        echo '<a href="?' . $qsPrefix . 'page=' . $totalPages . '" class="pagination-btn pagination-num">' . $totalPages . '</a>';
                    }
                ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo $qsPrefix; ?>page=<?php echo $page + 1; ?>" class="pagination-btn pagination-nav">
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
    <?php endif; ?>
</div>

<?php renderLayoutContentEnd(); ?>
<?php renderLayoutEnd(); ?>
