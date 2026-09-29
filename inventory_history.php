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
               " . ($hasAudit ? "t.previous_stock, t.new_stock," : "") . "
               p.product_id, p.product_name,
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
    $filterClause .= " AND (p.product_name LIKE ? OR CAST(t.transaction_id AS CHAR) LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
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
    .ih-mobile  { display: none; }
    @media (max-width: 767px) {
        .ih-desktop { display: none !important; }
        .ih-mobile  { display: block !important; }
    }

    .txn-page-card { padding: 0 !important; overflow: visible !important; }
    .txn-page-card:hover { transform: none !important; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important; }

    /* Date picker dark/light scheme */
    input[type="date"] {
        color-scheme: dark;
    }
    [data-theme="light"] input[type="date"] {
        color-scheme: light;
    }

    /* ── Desktop Audit Table (matches transactions.php) ──────────── */
    .ih-table {
        width: 100%;
        min-width: 960px;
        border-collapse: separate;
        border-spacing: 0;
    }
    .ih-table th,
    .ih-table td {
        padding: 12px 10px;
        vertical-align: middle;
        box-sizing: border-box;
    }
    .ih-table th:first-child,
    .ih-table td:first-child {
        padding-left: 18px;
    }
    .ih-table th:last-child,
    .ih-table td:last-child {
        padding-right: 18px;
    }
    .ih-table thead tr {
        background: rgba(19, 39, 91, 0.85);
        border-bottom: 2px solid rgba(42, 68, 132, 0.7);
    }
    .ih-table th {
        font-family: 'Onest', sans-serif;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9aa6d1 !important;
        background: transparent !important;
        border-bottom: 2px solid rgba(42, 68, 132, 0.6) !important;
        white-space: nowrap;
    }
    .ih-table td {
        border-bottom: 1px solid rgba(42, 68, 132, 0.35) !important;
    }
    .ih-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .ih-table tbody tr:hover td {
        background: rgba(29, 56, 123, 0.45) !important;
    }

    /* Column Widths & Alignments */
    .ih-table .col-id { width: 75px; text-align: left; }
    .ih-table .col-datetime { width: 160px; text-align: left; }
    .ih-table .col-product { min-width: 170px; text-align: left; }
    .ih-table .col-type { width: 85px; text-align: center; }
    .ih-table .col-qty { width: 80px; text-align: center; }
    .ih-table .col-prev { width: 80px; text-align: center; }
    .ih-table .col-new { width: 80px; text-align: center; }
    .ih-table .col-user { width: 115px; text-align: left; }
    .ih-table .col-remarks { max-width: 150px; text-align: left; }

    /* SKU & ID Badge */
    .txn-sku-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 7px;
        font-family: 'Onest', monospace;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.04em;
        background: rgba(19, 39, 91, 0.7);
        color: #9aa6d1;
        border: 1px solid rgba(42, 68, 132, 0.6);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    /* Tabular numbers */
    .txn-qty-num {
        font-family: 'Onest', sans-serif;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        font-size: 14px;
        letter-spacing: -0.01em;
        display: inline-block;
    }

    /* Glowing Status Badges */
    .txn-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 11px;
        border-radius: 9999px;
        font-family: 'Onest', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    .txn-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 9999px;
        flex-shrink: 0;
    }
    .txn-status-good {
        background: rgba(16, 185, 129, 0.12);
        color: #34d399;
        border: 1px solid rgba(52, 211, 153, 0.32);
        box-shadow: 0 0 12px rgba(52, 211, 153, 0.08);
    }
    .txn-status-good .txn-status-dot {
        background: #34d399;
        box-shadow: 0 0 8px rgba(52, 211, 153, 0.9);
    }
    .txn-status-out {
        background: rgba(239, 68, 68, 0.12);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.35);
        box-shadow: 0 0 12px rgba(239, 68, 68, 0.08);
    }
    .txn-status-out .txn-status-dot {
        background: #f87171;
        box-shadow: 0 0 8px rgba(239, 68, 68, 0.9);
    }

    /* User Operator Badge */
    .ih-user-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border-radius: 7px;
        background: rgba(19, 39, 91, 0.65);
        border: 1px solid rgba(42, 68, 132, 0.5);
        color: #c5d1f7;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        max-width: 130px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ── Mobile Accordion (Enhanced) ────────────────────────────── */
    .txnacc-list { display: flex; flex-direction: column; gap: 10px; padding: 16px; }
    .txnacc-item {
        background: linear-gradient(160deg, #1b2f6e 0%, #111e4a 100%);
        border: 1px solid rgba(42, 68, 132, 0.55);
        border-radius: 14px;
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .txnacc-item.is-open {
        border-color: rgba(81, 213, 90, 0.35);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
    }
    .txnacc-header {
        display: flex;
        align-items: stretch;
        padding: 14px 16px;
        cursor: pointer;
        gap: 12px;
        user-select: none;
    }
    .txnacc-left  { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 0; }
    .txnacc-product {
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #e3e3e3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }
    .txnacc-badge-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .txnacc-date { font-family: 'Onest', sans-serif; font-size: 11px; color: #9aa6d1; white-space: nowrap; }
    .txnacc-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
    .txnacc-qty   { font-family: 'Onest', sans-serif; font-size: 18px; font-weight: 800; line-height: 1; }
    .txnacc-chevron { color: #9aa6d1; line-height: 1; transition: transform 0.25s ease; }
    .txnacc-item.is-open .txnacc-chevron { transform: rotate(180deg); }
    .txnacc-body {
        display: none;
        border-top: 1px solid rgba(42, 68, 132, 0.4);
        padding: 14px 16px;
        background: rgba(13, 22, 56, 0.5);
    }
    .txnacc-item.is-open .txnacc-body { display: block; }
    .txnacc-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding: 6px 0;
        border-bottom: 1px solid rgba(42, 68, 132, 0.25);
    }
    .txnacc-row:last-child { border-bottom: none; }
    .txnacc-row-label { font-family: 'Onest', sans-serif; font-size: 12px; color: #6b7aab; }
    .txnacc-row-value {
        font-family: 'Onest', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #e3e3e3;
        text-align: right;
        max-width: 65%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .txnacc-row-value.mono { font-family: monospace; }
    .txnacc-remarks-row {
        margin-top: 8px;
        font-family: 'Onest', sans-serif;
        font-size: 12px;
        color: #9aa6d1;
        font-style: italic;
    }

    /* ── Light theme overrides ───────────────────────────────────── */
    [data-theme="light"] .page-hero-card {
        background: #FFFFFF !important;
        border: 1px solid #D6E2F2 !important;
        box-shadow: 0 1px 2px rgba(16,24,43,0.05), 0 16px 32px -16px rgba(20,80,158,0.22) !important;
    }
    [data-theme="light"] .page-hero-card::after {
        display: none !important;
    }
    [data-theme="light"] .page-hero-card .text-\[\#9aa6d1\] { color: #45526B !important; }
    [data-theme="light"] .page-hero-card .text-white { color: #0B1526 !important; }
    [data-theme="light"] .page-hero-card .bg-\[\#13275B\]\/80 { background: rgba(220,234,252,0.7) !important; border-color: #D6E2F2 !important; color: #45526B !important; }
    [data-theme="light"] .page-hero-card .bg-\[\#1D387B\]\/60 { background: rgba(220,234,252,0.7) !important; border-color: #D6E2F2 !important; color: #45526B !important; }
    [data-theme="light"] .page-hero-card strong { color: #0B1526 !important; }

    [data-theme="light"] .txn-page-card { background: #ffffff !important; border-color: #cbd5e1 !important; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06) !important; }
    [data-theme="light"] .txn-page-card h3.text-white { color: #0f172a !important; }
    [data-theme="light"] .ih-table thead tr { background: #f8fafc !important; border-bottom-color: #cbd5e1 !important; }
    [data-theme="light"] .ih-table th { color: #475569 !important; border-bottom-color: #cbd5e1 !important; }
    [data-theme="light"] .ih-table td { border-bottom-color: #e2e8f0 !important; color: #1e293b !important; }
    [data-theme="light"] .ih-table tbody tr:hover td { background: #f1f5f9 !important; }
    [data-theme="light"] .ih-table td .text-white { color: #0f172a !important; }
    [data-theme="light"] .ih-table td .text-\[\#6b7aab\] { color: #64748b !important; }
    [data-theme="light"] .ih-table td .text-\[\#9aa6d1\] { color: #475569 !important; }
    [data-theme="light"] .txn-sku-badge { background: #f1f5f9 !important; color: #475569 !important; border-color: #cbd5e1 !important; }
    [data-theme="light"] .ih-user-badge { background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #334155 !important; }
    [data-theme="light"] .txn-status-good { background: #ecfdf5 !important; color: #059669 !important; border-color: #a7f3d0 !important; }
    [data-theme="light"] .txn-status-out { background: #fef2f2 !important; color: #dc2626 !important; border-color: #fecaca !important; }
    [data-theme="light"] .txn-qty-num.text-emerald-400 { color: #059669 !important; }
    [data-theme="light"] .txn-qty-num.text-rose-400 { color: #dc2626 !important; }

    [data-theme="light"] .txnacc-item { background: #ffffff !important; border-color: #cbd5e1 !important; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05); }
    [data-theme="light"] .txnacc-item.is-open { border-color: #3b82f6 !important; }
    [data-theme="light"] .txnacc-product { color: #0f172a !important; }
    [data-theme="light"] .txnacc-date { color: #64748b !important; }
    [data-theme="light"] .txnacc-row-label { color: #64748b !important; }
    [data-theme="light"] .txnacc-row-value { color: #0f172a !important; }
    [data-theme="light"] .txnacc-body { background: #f8fafc !important; border-color: #e2e8f0 !important; }
    [data-theme="light"] .txnacc-row { border-color: #e2e8f0 !important; }
    [data-theme="light"] .txnacc-remarks-row { color: #64748b !important; }
    [data-theme="light"] .ih-filter-bg { background: rgba(248, 250, 252, 0.8) !important; border-color: #cbd5e1 !important; }
</style>

<!-- Hero Page Header Banner -->
<div class="page-hero-card mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
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

<div class="premium-card txn-page-card mb-8">
    <!-- Header Strip matching transactions.php -->
    <div class="p-6 border-b border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-[#13275B]/30">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2.5">
                <span>Inventory Audit Trail</span>
                <span class="text-[11px] font-semibold text-[#51D55A] bg-[#51D55A]/10 border border-[#51D55A]/25 px-2.5 py-0.5 rounded-full">
                    <?php echo number_format($totalRows); ?> records
                </span>
            </h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Real-time ledger. Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (!empty($search) || !empty($filterType) || !empty($filterProduct) || !empty($dateFrom) || !empty($dateTo)): ?>
                <a href="inventory_history.php" class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-semibold transition-colors" title="Clear all filters">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Reset Filters
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enhanced Filter Form -->
    <form method="GET" class="p-5 border-b border-[#2A4484]/40 bg-[#0d1638]/40 ih-filter-bg">
        <div class="flex flex-col lg:flex-row lg:items-end gap-3 w-full flex-wrap xl:flex-nowrap">
            <div class="w-full sm:flex-1 lg:flex-[1.4] min-w-0 sm:min-w-[200px]">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-1.5">Search</label>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 min-w-0">
                        <input type="text" name="q" class="premium-input w-full px-4" placeholder="ID, product name..."
                               value="<?php echo htmlspecialchars($search); ?>" style="height:42px; font-size:13px;">
                    </div>
                    <button type="submit" class="btn-secondary h-[42px] w-[42px] min-w-[42px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Search Audit Trail" aria-label="Search">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <a href="inventory_history.php" class="btn-secondary h-[42px] w-[42px] min-w-[42px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Reset Filters" aria-label="Reset Filters">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </a>
                </div>
            </div>
            <div class="w-full sm:flex-1 min-w-0 sm:min-w-[130px]">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-1.5">Movement Type</label>
                <select name="type" class="premium-input inventory-select w-full cursor-pointer" style="height:42px; font-size:13px;">
                    <option value="">All Types</option>
                    <option value="in" <?php echo $filterType === 'in' ? 'selected' : ''; ?>>IN (Receive)</option>
                    <option value="out" <?php echo $filterType === 'out' ? 'selected' : ''; ?>>OUT (Release)</option>
                </select>
            </div>
            <div class="w-full sm:flex-1 min-w-0 sm:min-w-[130px]">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-1.5">From Date</label>
                <input type="date" name="date_from" class="premium-input w-full px-3"
                       value="<?php echo htmlspecialchars($dateFrom); ?>" style="height:42px; font-size:13px;">
            </div>
            <div class="w-full sm:flex-1 min-w-0 sm:min-w-[130px]">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-1.5">To Date</label>
                <input type="date" name="date_to" class="premium-input w-full px-3"
                       value="<?php echo htmlspecialchars($dateTo); ?>" style="height:42px; font-size:13px;">
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="submit" class="premium-button" style="height:42px; padding:0 16px; font-size:13px;">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter
                </button>
            </div>
        </div>
    </form>

    <!-- Desktop / Tablet: Table (≥768px) -->
    <div class="ih-desktop">
        <div class="overflow-x-auto">
            <table class="premium-table ih-table w-full">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-datetime">Date &amp; Time</th>
                        <th class="col-product">Product</th>
                        <th class="col-type">Type</th>
                        <th class="col-qty">Quantity</th>
                        <?php if ($hasAudit): ?>
                            <th class="col-prev">Prev Stock</th>
                            <th class="col-new">New Stock</th>
                        <?php endif; ?>
                        <th class="col-user">Operator</th>
                        <th class="col-remarks">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="<?php echo $hasAudit ? 9 : 7; ?>" class="text-center p-12 text-[#9aa6d1]">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-[#6b7aab]/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span class="font-medium">No transactions found matching your criteria.</span>
                                    <?php if (!empty($search) || !empty($filterType) || !empty($filterProduct) || !empty($dateFrom) || !empty($dateTo)): ?>
                                        <a href="inventory_history.php" class="text-xs text-[#51D55A] hover:underline mt-1">Clear all filters</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $txn): ?>
                            <?php
                            $isIn    = $txn['transaction_type'] === 'stock_in';
                            $qty     = (int)$txn['quantity'];
                            ?>
                            <tr>
                                <td class="col-id">
                                    <span class="txn-sku-badge">#<?php echo (int)$txn['transaction_id']; ?></span>
                                </td>
                                <td class="col-datetime">
                                    <span class="font-medium text-white text-[13px] whitespace-nowrap">
                                        <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($txn['transaction_date']))); ?>
                                    </span>
                                </td>
                                <td class="col-product">
                                    <div class="font-semibold text-white text-[13.5px] leading-snug">
                                        <?php echo htmlspecialchars($txn['product_name'] ?? 'Unknown'); ?>
                                    </div>
                                </td>
                                <td class="col-type text-center">
                                    <?php if ($isIn): ?>
                                        <span class="txn-status-pill txn-status-good">
                                            <span class="txn-status-dot"></span>
                                            <span>IN</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="txn-status-pill txn-status-out">
                                            <span class="txn-status-dot"></span>
                                            <span>OUT</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-qty text-center">
                                    <span class="txn-qty-num <?php echo $isIn ? 'text-emerald-400' : 'text-rose-400'; ?>">
                                        <?php echo ($isIn ? '+' : '−') . number_format($qty); ?>
                                    </span>
                                </td>
                                <?php if ($hasAudit): ?>
                                    <td class="col-prev text-center">
                                        <span class="font-mono text-[13px] text-[#9aa6d1]" style="font-variant-numeric: tabular-nums;">
                                            <?php echo $txn['previous_stock'] !== null ? number_format((int)$txn['previous_stock']) : '—'; ?>
                                        </span>
                                    </td>
                                    <td class="col-new text-center">
                                        <span class="font-mono text-[13px] font-semibold text-white" style="font-variant-numeric: tabular-nums;">
                                            <?php echo $txn['new_stock'] !== null ? number_format((int)$txn['new_stock']) : '—'; ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="col-user">
                                    <span class="ih-user-badge" title="<?php echo htmlspecialchars($txn['username'] ?? '—'); ?>">
                                        <svg class="w-3 h-3 text-[#9aa6d1] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        <span><?php echo htmlspecialchars($txn['username'] ?? '—'); ?></span>
                                    </span>
                                </td>
                                <td class="col-remarks">
                                    <?php if (!empty($txn['remarks']) && $txn['remarks'] !== '—' && $txn['remarks'] !== '-'): ?>
                                        <span class="text-xs text-[#9aa6d1] max-w-[160px] inline-block truncate" title="<?php echo htmlspecialchars($txn['remarks']); ?>">
                                            <?php echo htmlspecialchars($txn['remarks']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-[#6b7aab]">—</span>
                                    <?php endif; ?>
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
            <div class="text-center text-[#9aa6d1] py-8">No transactions found matching your criteria.</div>
        <?php else: ?>
        <div class="txnacc-list">
            <?php foreach ($transactions as $txn): ?>
            <?php
                $isIn     = $txn['transaction_type'] === 'stock_in';
                $qtySign  = $isIn ? '+' : '−';
                $qtyClass = $isIn ? 'text-emerald-400' : 'text-rose-400';
                $remarks  = trim($txn['remarks'] ?? '');
            ?>
            <div class="txnacc-item">
                <!-- Header -->
                <div class="txnacc-header" onclick="this.closest('.txnacc-item').classList.toggle('is-open')">
                    <div class="txnacc-left">
                        <div class="txnacc-product"><?php echo htmlspecialchars($txn['product_name'] ?? 'Unknown'); ?></div>
                        <div class="txnacc-badge-row">
                            <?php if ($isIn): ?>
                                <span class="txn-status-pill txn-status-good" style="font-size:10px; padding:2px 8px;">
                                    <span class="txn-status-dot"></span>
                                    <span>STOCK IN</span>
                                </span>
                            <?php else: ?>
                                <span class="txn-status-pill txn-status-out" style="font-size:10px; padding:2px 8px;">
                                    <span class="txn-status-dot"></span>
                                    <span>STOCK OUT</span>
                                </span>
                            <?php endif; ?>
                            <span class="txnacc-date"><?php echo date('M j, Y g:i A', strtotime($txn['transaction_date'])); ?></span>
                        </div>
                    </div>
                    <div class="txnacc-right">
                        <span class="txnacc-qty <?php echo $qtyClass; ?> font-mono"><?php echo $qtySign . number_format((int)$txn['quantity']); ?></span>
                        <span class="txnacc-chevron">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </div>
                </div>
                <!-- Expanded body -->
                <div class="txnacc-body">
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">Transaction ID</span>
                        <span class="txnacc-row-value mono">#<?php echo (int)$txn['transaction_id']; ?></span>
                    </div>
                    <?php if ($hasAudit): ?>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">Previous Stock</span>
                        <span class="txnacc-row-value mono"><?php echo $txn['previous_stock'] !== null ? number_format((int)$txn['previous_stock']) : '—'; ?></span>
                    </div>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">New Stock</span>
                        <span class="txnacc-row-value mono"><?php echo $txn['new_stock'] !== null ? number_format((int)$txn['new_stock']) : '—'; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="txnacc-row">
                        <span class="txnacc-row-label">Operator</span>
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
        <div class="pagination-container p-2.5 sm:p-4 border-t border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-3 sm:gap-4 flex-wrap">
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
            <div class="pagination-btn-group flex items-center gap-1 sm:gap-2">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo $qsPrefix; ?>page=<?php echo $page - 1; ?>" class="pagination-btn pagination-nav" aria-label="Previous page" title="Previous page">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                        <span class="pagination-nav-text">Previous</span>
                    </a>
                <?php else: ?>
                    <button type="button" class="pagination-btn pagination-nav" disabled aria-label="Previous page" title="Previous page">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                        <span class="pagination-nav-text">Previous</span>
                    </button>
                <?php endif; ?>

                <?php
                    if ($totalPages <= 7) {
                        $pageItems = range(1, $totalPages);
                    } elseif ($page <= 3) {
                        $pageItems = [1, 2, 3, 4, 5, '...', $totalPages];
                    } elseif ($page >= $totalPages - 2) {
                        $pageItems = [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages];
                    } else {
                        $pageItems = [1, '...', $page - 1, $page, $page + 1, '...', $totalPages];
                    }

                    foreach ($pageItems as $item) {
                        if ($item === '...') {
                            echo '<span class="pagination-ellipsis">&hellip;</span>';
                        } elseif ($item === $page) {
                            echo '<button type="button" class="pagination-btn pagination-num active" disabled>' . $item . '</button>';
                        } else {
                            $isNeighbor = false;
                            if ($totalPages > 7) {
                                if ($page <= 3) {
                                    $isNeighbor = ($item === 4 || $item === 5);
                                } elseif ($page >= $totalPages - 2) {
                                    $isNeighbor = ($item === $totalPages - 4 || $item === $totalPages - 3);
                                } else {
                                    $isNeighbor = ($item === $page - 1 || $item === $page + 1);
                                }
                            }
                            $neighborClass = $isNeighbor ? ' pagination-neighbor' : '';
                            echo '<a href="?' . $qsPrefix . 'page=' . $item . '" class="pagination-btn pagination-num' . $neighborClass . '">' . $item . '</a>';
                        }
                    }
                ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo $qsPrefix; ?>page=<?php echo $page + 1; ?>" class="pagination-btn pagination-nav" aria-label="Next page" title="Next page">
                        <span class="pagination-nav-text">Next</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                <?php else: ?>
                    <button type="button" class="pagination-btn pagination-nav" disabled aria-label="Next page" title="Next page">
                        <span class="pagination-nav-text">Next</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php renderLayoutContentEnd(); ?>
<?php renderLayoutEnd(); ?>
