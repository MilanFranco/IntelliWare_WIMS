<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

require_once 'src/layout.php';
require_once 'config/db.php';

// --- 1. DATA FETCHING LOGIC ---

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? ''; // New Filter

$mainWhereConditions = [];
$mainParams = [];
$mainTypes = '';

// Optimize Date Filter (Avoid DATE() function for index performance)
if ($dateFrom) {
    $mainWhereConditions[] = "created_at >= ?";
    $mainParams[] = $dateFrom . " 00:00:00";
    $mainTypes .= 's';
}
if ($dateTo) {
    $mainWhereConditions[] = "created_at <= ?";
    $mainParams[] = $dateTo . " 23:59:59";
    $mainTypes .= 's';
}

if ($categoryFilter) {
    $mainWhereConditions[] = "category = ?";
    $mainParams[] = $categoryFilter;
    $mainTypes .= 's';
}

// Logic for Stock Status Filter
if ($statusFilter) {
    if ($statusFilter === 'out') {
        $mainWhereConditions[] = "quantity <= 0";
    } elseif ($statusFilter === 'low') {
        // Low stock but not zero
        $mainWhereConditions[] = "quantity <= reorder_point AND quantity > 0";
    } elseif ($statusFilter === 'attention') {
        // Both low and out
        $mainWhereConditions[] = "quantity <= reorder_point";
    } elseif ($statusFilter === 'good') {
        $mainWhereConditions[] = "quantity > reorder_point";
    }
}

$mainWhereClause = '';
if (count($mainWhereConditions) > 0) {
    $mainWhereClause = 'WHERE ' . implode(' AND ', $mainWhereConditions);
}

// Fetch products
$items = [];
$productQuery = "SELECT 
    product_id as id, 
    CONCAT('P-', LPAD(product_id, 4, '0')) as sku, 
    product_name as name, 
    category, 
    quantity as qty, 
    unit as uom, 
    reorder_point,
    created_at
FROM products 
$mainWhereClause 
ORDER BY category, product_name";

if ($stmt = $conn->prepare($productQuery)) {
    if (count($mainParams) > 0) {
        $stmt->bind_param($mainTypes, ...$mainParams); 
    }
    $stmt->execute();
    $productResult = $stmt->get_result();
    if ($productResult) {
        while ($row = $productResult->fetch_assoc()) {
            $items[] = $row;
        }
    }
    $stmt->close();
}

// Fetch unique categories
$categoriesQuery = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category";
$allCategories = [];
$resultCat = $conn->query($categoriesQuery);
if ($resultCat) {
    while ($row = $resultCat->fetch_assoc()) {
        $allCategories[] = $row['category'];
    }
}

// --- 2. DATA PROCESSING ---

$categoryGroups = [];
$lowStockCount = 0;
$outOfStockCount = 0;
$goodStockCount = 0;
$totalUnits = 0;
$totalItemsFiltered = count($items);

foreach ($items as &$item) {
    $cat = $item['category'] ?: 'Uncategorized';
    
    if (!isset($categoryGroups[$cat])) {
        $categoryGroups[$cat] = [
            'items' => [],
            'total_qty' => 0
        ];
    }
    $qty = (int)($item['qty'] ?? 0);
    $reorder = (int)($item['reorder_point'] ?? 10);
    
    if ($qty <= 0) {
        $item['status'] = 'out';
        $item['status_label'] = 'OUT OF STOCK';
        $outOfStockCount++;
    } elseif ($qty <= $reorder) {
        $item['status'] = 'low';
        $item['status_label'] = 'LOW STOCK';
        $lowStockCount++;
    } else {
        $item['status'] = 'good';
        $item['status_label'] = 'IN STOCK';
        $goodStockCount++;
    }

    $categoryGroups[$cat]['items'][] = $item;
    $categoryGroups[$cat]['total_qty'] += $qty;
    $totalUnits += $qty;
}
unset($item);

$generatedBy = $_SESSION['Username'] ?? 'Staff';
$generatedRole = ucfirst($role);
$generatedAt = date('F j, Y · g:i A');

$logoSvgPath = __DIR__ . '/img/systemlogo.svg';
$logoSvgDataUri = '';
if (file_exists($logoSvgPath)) {
    $logoSvgDataUri = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logoSvgPath));
}

$conn->close();

renderLayoutStart('Reports', $role, $_SESSION['Username'], ($lowStockCount + $outOfStockCount));
?>

<script src="assets/vendor/xlsx.full.min.js"></script>
<script src="assets/vendor/chart.umd.min.js"></script>
<script src="assets/vendor/jspdf.umd.min.js"></script>
<script src="assets/vendor/jspdf.plugin.autotable.min.js"></script>

<style>
    /* Button Styles */
    .browser-btn {
        text-align: left;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .browser-btn.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: #51D55A;
    }
    .browser-btn:hover, .browser-btn.active {
        background: rgba(81, 213, 90, 0.1);
        border-color: rgba(81, 213, 90, 0.4);
    }
    .browser-btn.active .btn-title { color: #51D55A; }
    
    /* Scroll Behavior */
    .premium-scrollbar::-webkit-scrollbar { width: 6px; }
    .premium-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
    .premium-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .premium-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    .premium-input {
        background: rgba(19, 39, 91, 0.8);
        color: #E3E3E3;
        border: 1px solid rgba(48, 67, 116, 0.6);
        border-radius: 12px;
        padding: 0 16px;
        font-size: 14px;
        font-family: 'Onest', sans-serif;
        transition: all 0.3s;
    }
    .premium-input:focus {
        border-color: #51D55A;
        outline: none;
        box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.1);
    }
    
    /* Status Badge Styles */
    .status-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 4px;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        letter-spacing: 0.02em;
    }
    .status-badge.low { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .status-badge.out { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); }
    .status-badge.good { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }

    /* Stock Explorer Rows (Dark Mode Default) */
    .stock-row {
        background: rgba(29, 56, 123, 0.4);
        border: 1px solid rgba(42, 68, 132, 0.3);
        border-radius: 10px;
        transition: all 0.18s ease;
    }
    .stock-row:hover,
    .stock-row.row-hovered {
        background: rgba(29, 56, 123, 0.7) !important;
        border-color: rgba(81, 213, 90, 0.45) !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }
    .stock-row .product-name,
    .stock-row .stock-health-name {
        color: #E3E3E3;
    }
    .stock-row:hover .product-name,
    .stock-row.row-hovered .product-name {
        color: #FFFFFF;
    }
    .stock-row .product-sku {
        color: #9aa6d1;
    }
    .stock-row .product-qty {
        color: #51D55A;
    }

    /* Light Theme Styling for Stock Explorer (White Items on Blue Background) */
    [data-theme="light"] #itemList,
    [data-theme="light"] #statusList {
        background: #DBEAFE !important;
        border-color: #93C5FD !important;
    }

    [data-theme="light"] .stock-row {
        background: #FFFFFF !important;
        border: 1px solid #D0DFF2 !important;
        box-shadow: 0 1px 3px rgba(16, 24, 43, 0.05), 0 1px 2px rgba(16, 24, 43, 0.03) !important;
    }
    [data-theme="light"] .stock-row:hover,
    [data-theme="light"] .stock-row.row-hovered {
        background: #FFFFFF !important;
        border-color: #51D55A !important;
        box-shadow: 0 6px 16px rgba(16, 24, 43, 0.08), 0 0 0 1px rgba(81, 213, 90, 0.35) !important;
        transform: translateY(-1px);
    }
    [data-theme="light"] .stock-row .product-name,
    [data-theme="light"] .stock-row .stock-health-name {
        color: #0F172A !important;
    }
    [data-theme="light"] .stock-row:hover .product-name,
    [data-theme="light"] .stock-row.row-hovered .product-name {
        color: #0F172A !important;
    }
    [data-theme="light"] .stock-row .product-sku {
        color: #64748B !important;
    }
    [data-theme="light"] .stock-row .product-qty {
        color: #15803D !important;
    }

    /* Light Mode Categories Column */
    [data-theme="light"] .browser-btn {
        background: #FFFFFF !important;
        border: 1px solid #D0DFF2 !important;
        box-shadow: 0 1px 3px rgba(16, 24, 43, 0.04) !important;
    }
    [data-theme="light"] .browser-btn:hover {
        background: #F8FAFC !important;
        border-color: #93C5FD !important;
    }
    [data-theme="light"] .browser-btn.active {
        background: #F0FDF4 !important;
        border-color: #51D55A !important;
        box-shadow: 0 2px 8px rgba(81, 213, 90, 0.15) !important;
    }
    [data-theme="light"] .browser-btn .btn-title {
        color: #0F172A !important;
    }
    [data-theme="light"] .browser-btn.active .btn-title {
        color: #15803D !important;
    }
    [data-theme="light"] .browser-btn div.text-\[\#9aa6d1\],
    [data-theme="light"] .browser-btn span:not(.btn-title) {
        color: #64748B !important;
    }
    [data-theme="light"] .browser-btn.active span:not(.btn-title) {
        color: #15803D !important;
    }

    /* Light Mode Status Badges */
    [data-theme="light"] .status-badge.good {
        background: #DCFCE7 !important;
        color: #15803D !important;
        border: 1px solid #86EFAC !important;
    }
    [data-theme="light"] .status-badge.low {
        background: #FEF3C7 !important;
        color: #B45309 !important;
        border: 1px solid #FCD34D !important;
    }
    [data-theme="light"] .status-badge.out {
        background: #FEE2E2 !important;
        color: #B91C1C !important;
        border: 1px solid #FCA5A5 !important;
    }

    /* Light Mode Meta Details */
    [data-theme="light"] #itemPanelMeta {
        color: #15803D !important;
    }
    [data-theme="light"] #statusPanelMeta {
        color: #64748B !important;
    }

    /* Light Mode Scrollbars */
    [data-theme="light"] .premium-scrollbar::-webkit-scrollbar-track {
        background: rgba(147, 197, 253, 0.25);
    }
    [data-theme="light"] .premium-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(30, 58, 138, 0.2);
        border-radius: 10px;
    }
    [data-theme="light"] .premium-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(30, 58, 138, 0.35);
    }

    /* Light Mode Reset Button */
    [data-theme="light"] a[title="Clear Filters"] {
        background: #EFF6FF !important;
        border-color: #BFDBFE !important;
        color: #475569 !important;
    }
    [data-theme="light"] a[title="Clear Filters"]:hover {
        background: #DBEAFE !important;
        color: #0F172A !important;
    }



    .print-only { display: none !important; }

    @media print {
        header, nav, aside, .sidebar, #sidebar, .iw-sidebar,
        .premium-sidebar, #toast-container, form,
        .no-print, button, .action-buttons,
        .browser-btn, #itemList, #statusList, #reportCatChart,
        #itemPanelMeta, #statusPanelMeta, .premium-card:has(form),
        .premium-card:has(#reportCatChart) {
            display: none !important;
        }
        .print-only { display: block !important; }
        
        body, .main-content, .layout-container, main {
            background: #ffffff !important;
            color: #0f172a !important;
            padding: 0 !important;
            margin: 0 !important;
            font-family: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        .printable-report-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 15px !important;
            font-size: 10px !important;
        }
        .printable-report-table th {
            background-color: #13275B !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            text-align: left !important;
            padding: 6px 8px !important;
            border: 1px solid #0f172a !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .printable-report-table td {
            padding: 5px 8px !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        .printable-report-table tr:nth-child(even) td {
            background-color: #f8fafc !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .print-badge {
            display: inline-block !important;
            padding: 2px 6px !important;
            border-radius: 4px !important;
            font-size: 8.5px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .print-badge.out { background: #fee2e2 !important; color: #b91c1c !important; border: 1px solid #f87171 !important; }
        .print-badge.low { background: #fef3c7 !important; color: #b45309 !important; border: 1px solid #fbbf24 !important; }
        .print-badge.good { background: #d1fae5 !important; color: #047857 !important; border: 1px solid #34d399 !important; }
    }

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
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"></path></svg>
                </div>
                Inventory Reports &amp; Analytics Export
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Generate custom inventory statements, export stock datasets to Excel and PDF formats, and inspect real-time valuation and movement metrics.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export: <strong class="text-white">Excel &bull; PDF &bull; Print</strong>
            </span>
        </div>
    </div>
</div>

<div class="premium-card mb-6" style="overflow:visible;">
    <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
        <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Generate Report
        </h3>
    </div>
    
    <div class="p-5">
        <form method="GET" action="reports.php" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-4 items-end">
            <div class="xl:col-span-2">
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Date Added (From)</label>
                <input type="date" name="date_from" class="premium-input w-full h-[46px]" value="<?php echo htmlspecialchars($dateFrom); ?>" />
            </div>
            <div class="xl:col-span-2">
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Date Added (To)</label>
                <input type="date" name="date_to" class="premium-input w-full h-[46px]" value="<?php echo htmlspecialchars($dateTo); ?>" />
            </div>
            <div class="xl:col-span-2">
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Category</label>
                <select name="category" class="premium-input inventory-select w-full h-[46px] cursor-pointer">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="xl:col-span-2">
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Stock Status</label>
                <select name="status" class="premium-input inventory-select w-full h-[46px] cursor-pointer">
                    <option value="">All Status</option>
                    <option value="good" <?php echo $statusFilter === 'good' ? 'selected' : ''; ?>>Good Stock</option>
                    <option value="attention" <?php echo $statusFilter === 'attention' ? 'selected' : ''; ?>>Attention Needed (Low/Out)</option>
                    <option value="low" <?php echo $statusFilter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out" <?php echo $statusFilter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>

            <div class="md:col-span-2 xl:col-span-4 flex items-center gap-2">
                <button class="btn-secondary h-[46px] w-[46px] min-w-[46px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" type="submit" title="Search Reports" aria-label="Search">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
                <button type="button" onclick="downloadPDF()" class="premium-button h-[46px] px-4 text-xs font-semibold flex items-center justify-center gap-1.5 whitespace-nowrap" title="Generate &amp; Download PDF Report">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>PDF</span>
                </button>
                <button type="button" onclick="downloadExcel()" class="premium-button h-[46px] px-4 text-xs font-semibold flex items-center justify-center gap-1.5 whitespace-nowrap" title="Generate &amp; Download Excel Report">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Excel</span>
                </button>
                <?php if ($dateFrom || $dateTo || $categoryFilter || $statusFilter): ?>
                    <a href="reports.php" title="Clear Filters" class="h-[46px] px-3.5 rounded-xl border border-[#2A4484] bg-[#13275B] text-[#9aa6d1] hover:text-white flex items-center justify-center transition-colors whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l5 5 5 15 5-5 5-15H4z"></path></svg>
                        <span class="ml-1 text-xs">Reset</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6 no-print">
    <div class="premium-card p-6 flex items-center justify-between group hover:border-blue-500/50 cursor-pointer transition-all <?php echo empty($statusFilter) ? 'border-blue-500/40' : ''; ?>" onclick="window.location.href='reports.php<?php echo $categoryFilter ? ('?category=' . urlencode($categoryFilter)) : ''; ?>'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total Products</div>
            <div class="premium-metric">
                <span class="num text-3xl"><?php echo number_format($totalItemsFiltered); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-[#51D55A]/50 cursor-default transition-all">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total Units</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($totalUnits); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-[#51D55A]/10 flex items-center justify-center text-[#51D55A] group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-amber-500/50 cursor-pointer transition-all <?php echo $statusFilter === 'low' ? 'border-amber-500/50' : ''; ?>" onclick="window.location.href='reports.php?status=low<?php echo $categoryFilter ? ('&category=' . urlencode($categoryFilter)) : ''; ?>'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Low Stock Alert</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($lowStockCount); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-red-500/50 cursor-pointer transition-all <?php echo $statusFilter === 'out' ? 'border-red-500/50' : ''; ?>" onclick="window.location.href='reports.php?status=out<?php echo $categoryFilter ? ('&category=' . urlencode($categoryFilter)) : ''; ?>'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Out of Stock</div>
            <div class="premium-metric">
                <span class="num text-3xl" style="background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($outOfStockCount); ?></span>
            </div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
        </div>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="premium-card p-12 text-center flex flex-col items-center justify-center">
        <div class="w-16 h-16 bg-[#13275B] rounded-full flex items-center justify-center mb-4 border border-[#2A4484]">
            <svg class="w-8 h-8 text-[#9aa6d1]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h3 class="text-xl font-semibold text-white mb-2 font-onest">No Data Found</h3>
        <p class="text-[#9aa6d1] max-w-sm">No items matched your filters. Try clearing the date filters to see older stock.</p>
        <?php if ($dateFrom || $dateTo): ?>
            <a href="reports.php" class="mt-4 text-[#51D55A] text-sm hover:underline">Clear Date Filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>

    <!-- Printable Report (Active Only During Window.print()) -->
    <div id="printableReportContainer" class="print-only" style="padding: 20px 0;">
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #13275B; padding-bottom: 12px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <img src="img/systemlogo.svg" alt="Logo" style="width: 52px; height: 52px;" />
                <div>
                    <div style="font-size: 19px; font-weight: 800; color: #13275B; letter-spacing: 0.5px;">INTELLIWARE</div>
                    <div style="font-size: 10px; color: #64748b;">Intelligent Warehouse &amp; Inventory Management System</div>
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px;">OFFICIAL INVENTORY &amp; AUDIT REPORT</div>
                </div>
            </div>
            <div style="text-align: right; font-size: 10px; color: #64748b;">
                <div style="display: inline-block; background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; font-weight: 700; font-size: 8.5px; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; margin-bottom: 4px;">Active Audit</div>
                <div><strong>REF:</strong> IW-RPT-<?php echo date('Ymd-His'); ?></div>
                <div>Classification: Internal Warehouse Audit</div>
            </div>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 11px;">
            <div>
                <div style="color: #64748b; font-size: 8.5px; font-weight: bold; text-transform: uppercase;">Audit Generated By</div>
                <div style="font-weight: bold; color: #0f172a; margin-top: 2px;"><?php echo htmlspecialchars($generatedBy); ?> (<?php echo htmlspecialchars($generatedRole); ?>)</div>
                <div style="color: #64748b; font-size: 10px; margin-top: 2px;"><?php echo htmlspecialchars($generatedAt); ?></div>
            </div>
            <div>
                <div style="color: #64748b; font-size: 8.5px; font-weight: bold; text-transform: uppercase;">Report Scope &amp; Parameters</div>
                <div style="margin-top: 2px;"><strong>Category:</strong> <?php echo $categoryFilter ? htmlspecialchars($categoryFilter) : 'All Categories'; ?></div>
                <div style="margin-top: 2px;"><strong>Status:</strong> <?php echo $statusFilter ? ucfirst(htmlspecialchars($statusFilter)) : 'All Statuses'; ?></div>
            </div>
            <div>
                <div style="color: #64748b; font-size: 8.5px; font-weight: bold; text-transform: uppercase;">Audit Reconciliation</div>
                <div style="margin-top: 2px;"><strong>Products:</strong> <?php echo number_format($totalItemsFiltered); ?> (<?php echo number_format($totalUnits); ?> Total Units)</div>
                <div style="margin-top: 2px;">Good: <?php echo $goodStockCount; ?> | Low: <?php echo $lowStockCount; ?> | Out: <?php echo $outOfStockCount; ?></div>
            </div>
        </div>

        <!-- 5 KPI Tiles on Paper -->
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 14px;">
            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; text-align: center;">
                <div style="font-size: 8px; font-weight: bold; color: #475569; text-transform: uppercase;">Total Products</div>
                <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px;"><?php echo number_format($totalItemsFiltered); ?></div>
                <div style="font-size: 7.5px; color: #64748b;">Unique SKUs</div>
            </div>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 8px; text-align: center;">
                <div style="font-size: 8px; font-weight: bold; color: #059669; text-transform: uppercase;">Total Units</div>
                <div style="font-size: 14px; font-weight: 800; color: #059669; margin-top: 2px;"><?php echo number_format($totalUnits); ?></div>
                <div style="font-size: 7.5px; color: #047857;">In Warehouse</div>
            </div>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px; text-align: center;">
                <div style="font-size: 8px; font-weight: bold; color: #16a34a; text-transform: uppercase;">Good Stock</div>
                <div style="font-size: 14px; font-weight: 800; color: #16a34a; margin-top: 2px;"><?php echo number_format($goodStockCount); ?></div>
                <div style="font-size: 7.5px; color: #15803d;">Optimal Level</div>
            </div>
            <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; padding: 8px; text-align: center;">
                <div style="font-size: 8px; font-weight: bold; color: #b45309; text-transform: uppercase;">Low Stock</div>
                <div style="font-size: 14px; font-weight: 800; color: #d97706; margin-top: 2px;"><?php echo number_format($lowStockCount); ?></div>
                <div style="font-size: 7.5px; color: #b45309;">Reorder Soon</div>
            </div>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 8px; text-align: center;">
                <div style="font-size: 8px; font-weight: bold; color: #dc2626; text-transform: uppercase;">Out of Stock</div>
                <div style="font-size: 14px; font-weight: 800; color: #dc2626; margin-top: 2px;"><?php echo number_format($outOfStockCount); ?></div>
                <div style="font-size: 7.5px; color: #b91c1c;">Depleted</div>
            </div>
        </div>

        <table class="printable-report-table">
            <thead>
                <tr>
                    <th style="width: 12%;">SKU</th>
                    <th style="width: 32%;">Product Name</th>
                    <th style="width: 20%;">Category</th>
                    <th style="width: 10%; text-align: right;">Stock Qty</th>
                    <th style="width: 8%; text-align: center;">Unit</th>
                    <th style="width: 8%; text-align: right;">Reorder</th>
                    <th style="width: 10%; text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $pi): ?>
                <tr>
                    <td style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($pi['sku']); ?></td>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($pi['name']); ?></td>
                    <td><?php echo htmlspecialchars($pi['category'] ?: 'Uncategorized'); ?></td>
                    <td style="text-align: right; font-weight: bold;"><?php echo number_format($pi['qty']); ?></td>
                    <td style="text-align: center; color: #64748b;"><?php echo htmlspecialchars($pi['uom'] ?: 'pcs'); ?></td>
                    <td style="text-align: right; color: #64748b;"><?php echo number_format($pi['reorder_point'] ?: 10); ?></td>
                    <td style="text-align: center;">
                        <span class="print-badge <?php echo $pi['status']; ?>">
                            <?php echo $pi['status_label']; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Sign-off / Signature section -->
        <div style="margin-top: 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; page-break-inside: avoid;">
            <div>
                <div style="font-size: 8.5px; font-weight: bold; color: #64748b; text-transform: uppercase;">Audit Prepared By</div>
                <div style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px;"><?php echo htmlspecialchars($generatedBy); ?> (<?php echo htmlspecialchars($generatedRole); ?>)</div>
                <div style="margin-top: 28px; border-bottom: 1px solid #cbd5e1; width: 85%;"></div>
                <div style="font-size: 8px; color: #94a3b8; margin-top: 3px;">Authorized Signature &amp; Stamp</div>
            </div>
            <div>
                <div style="font-size: 8.5px; font-weight: bold; color: #64748b; text-transform: uppercase;">Verified &amp; Approved By</div>
                <div style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px;">Warehouse Supervisor / Operations Control</div>
                <div style="margin-top: 28px; border-bottom: 1px solid #cbd5e1; width: 85%;"></div>
                <div style="font-size: 8px; color: #94a3b8; margin-top: 3px;">Authorized Signature &amp; Date</div>
            </div>
        </div>

        <div style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 8px; display: flex; justify-content: space-between; font-size: 8.5px; color: #94a3b8;">
            <div>IntelliWare WIMS • Confidential Inventory Audit Document</div>
            <div>Generated on <?php echo date('Y-m-d H:i:s'); ?></div>
        </div>
    </div>

    <!-- Stock Explorer Card -->
    <div class="premium-card mb-6">
        <div class="p-5 border-b border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-4 bg-[#13275B]/30">
            <div>
                <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Stock Explorer
                </h3>
                <p class="text-xs text-[#9aa6d1] mt-1">Showing <?php echo number_format($totalItemsFiltered); ?> items based on current filters.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/60 border border-[#2A4484]/40 text-xs text-[#9aa6d1]">
                    <span class="w-2 h-2 rounded-full bg-[#51D55A]"></span>
                    <span>Interactive Explorer</span>
                </span>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 h-auto lg:h-[600px]">
            
            <div class="lg:col-span-3 flex flex-col lg:h-full lg:border-r border-[#2A4484]/30 lg:pr-4">
                <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider mb-3 px-1">Categories</div>
                
                <div class="overflow-y-auto premium-scrollbar pr-2 space-y-2 h-[350px] lg:h-[520px] min-h-0">
                    <?php foreach ($categoryGroups as $categoryName => $group):
                        $productCount = count($group['items']);
                        $totalQty = $group['total_qty'];
                        $catKeyAttr = htmlspecialchars(json_encode($categoryName), ENT_QUOTES);
                    ?>
                    <button class="browser-btn w-full p-3 rounded-xl border border-transparent bg-white/5 hover:bg-white/10 group flex-shrink-0" onclick="toggleCategory(<?php echo $catKeyAttr; ?>, this)">
                        <div class="flex justify-between items-center mb-1">
                            <span class="btn-title font-semibold text-[#E3E3E3] text-sm group-hover:text-white text-left truncate w-[85%]"><?php echo htmlspecialchars($categoryName); ?></span>
                            <svg class="w-4 h-4 text-[#9aa6d1] opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                        <div class="flex justify-between text-[10px] text-[#9aa6d1]">
                            <span><?php echo $productCount; ?> Items</span>
                            <span><?php echo number_format($totalQty); ?> Units</span>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="lg:col-span-5 flex flex-col lg:h-full lg:border-r border-[#2A4484]/30 lg:px-4 mt-6 lg:mt-0">
                <div class="flex justify-between items-center mb-3 px-1">
                    <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider">Products</div>
                    <div id="itemPanelMeta" class="text-[10px] text-[#51D55A]">Select a category</div>
                </div>
                
                <div class="overflow-y-auto premium-scrollbar pr-2 bg-[#0d1638]/50 rounded-xl border border-[#2A4484]/30 p-2 h-[350px] lg:h-[520px] min-h-0" id="itemList">
                    <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60 min-h-[150px]">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        <span class="text-xs">Select a category on the left</span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 flex flex-col lg:h-full lg:pl-2 mt-6 lg:mt-0">
                <div class="flex justify-between items-center mb-3 px-1">
                    <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider">Stock Health</div>
                    <div id="statusPanelMeta" class="text-[10px] text-[#9aa6d1]">Overview</div>
                </div>
                
                <div class="overflow-y-auto premium-scrollbar pr-2 bg-[#0d1638]/50 rounded-xl border border-[#2A4484]/30 p-2 h-[350px] lg:h-[520px] min-h-0" id="statusList">
                    <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60 min-h-[150px]">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-xs">Status details will appear here</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="premium-card">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white">Inventory Distribution</h3>
        </div>
        <div class="p-5 h-[300px] relative">
            <canvas id="reportCatChart"></canvas>
        </div>
    </div>

<?php endif; ?>

<script>
  const systemLogoDataUri = <?php echo json_encode($logoSvgDataUri); ?>;
  const items = <?php echo json_encode($items); ?>;
  const categoryData = <?php echo json_encode($categoryGroups); ?>;
  const reportUser = <?php echo json_encode($generatedBy); ?>;
  const reportRole = <?php echo json_encode($generatedRole); ?>;
  const filterCategory = <?php echo json_encode($categoryFilter ?: 'All Categories'); ?>;
  const filterStatusLabel = <?php 
    if ($statusFilter === 'out') echo json_encode('Out of Stock');
    elseif ($statusFilter === 'low') echo json_encode('Low Stock');
    elseif ($statusFilter === 'attention') echo json_encode('Attention Needed (Low/Out)');
    elseif ($statusFilter === 'good') echo json_encode('Good Stock');
    else echo json_encode('All Statuses');
  ?>;
  const filterDateRangeStr = <?php echo json_encode(($dateFrom || $dateTo) ? (($dateFrom ?: 'Beginning') . ' to ' . ($dateTo ?: 'Today')) : 'All Time'); ?>;
  const totalUnitsCount = <?php echo (int)$totalUnits; ?>;
  const goodCount = <?php echo (int)$goodStockCount; ?>;
  const lowCount = <?php echo (int)$lowStockCount; ?>;
  const outCount = <?php echo (int)$outOfStockCount; ?>;
  
  // Chart Init
  (function(){
    if (items.length === 0) return;
    const catTotals = {};
    items.forEach(i=>{ 
      const c=(i.category||'Uncategorized'); 
      catTotals[c]=(catTotals[c]||0)+parseInt(i.qty||0,10);
    });
    
    const ctx = document.getElementById('reportCatChart');
    if(ctx){
      new Chart(ctx, {
        type: 'bar',
        data: { 
          labels: Object.keys(catTotals), 
          datasets:[{ 
            label:'Quantity', 
            data: Object.values(catTotals), 
            backgroundColor: '#51D55A',
            hoverBackgroundColor: '#4BC052',
            borderRadius: 6,
            barPercentage: 0.6
          }] 
        },
        options: { 
          responsive: true,
          maintainAspectRatio: false,
          plugins:{ legend:{display:false} },
          scales:{
            x: { ticks: { color: '#9aa6d1', font: { family: 'Onest' } }, grid: { display:false } },
            y: { ticks: { color: '#9aa6d1', font: { family: 'Onest' } }, grid: { color: 'rgba(255,255,255,0.05)' } }
          }
        }
      });
    }
  })();

  // Interaction Logic
  let activeCategory = null;

  function toggleCategory(categoryKey, buttonEl) {
    const itemList = document.getElementById('itemList');
    const statusList = document.getElementById('statusList');
    const itemMeta = document.getElementById('itemPanelMeta');
    const statusMeta = document.getElementById('statusPanelMeta');

    document.querySelectorAll('.browser-btn').forEach(b => b.classList.remove('active'));

    if (activeCategory === categoryKey) {
        activeCategory = null;
        const placeholder = `
            <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60">
                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="text-xs">Select a category</span>
            </div>`;
        itemList.innerHTML = placeholder;
        statusList.innerHTML = placeholder;
        itemMeta.innerText = 'Select a category';
        statusMeta.innerText = 'Overview';
        return;
    }

    activeCategory = categoryKey;
    buttonEl.classList.add('active');

    const group = categoryData[categoryKey];
    const products = group.items || [];
    
    itemMeta.innerText = `${products.length} Products Found`;
    statusMeta.innerText = `Total Stock: ${group.total_qty}`;

    // Helper for safe HTML rendering
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Render Lists
    itemList.innerHTML = products.map((p, index) => `
        <div class="stock-row flex justify-between items-center p-3 mb-2 rounded-xl group h-[60px] cursor-default" onmouseenter="syncHover(${index}, true)" onmouseleave="syncHover(${index}, false)" id="prod-${index}">
            <div class="w-3/4 pr-2">
                <div class="product-name font-semibold text-sm truncate" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</div>
                <div class="product-sku text-[10px] font-mono">${escapeHtml(p.sku)}</div>
            </div>
            <div class="product-qty font-mono font-bold text-sm text-right">${Number(p.qty || 0).toLocaleString()}</div>
        </div>
    `).join('');

    statusList.innerHTML = products.map((p, index) => {
        const qty = parseInt(p.qty);
        const reorder = parseInt(p.reorder_point || 10);
        let badgeClass = 'good';
        let badgeText = 'GOOD';
        
        if (qty <= 0) {
             badgeClass = 'out';
             badgeText = 'OUT OF STOCK';
        } else if (qty <= reorder) {
             badgeClass = 'low';
             badgeText = 'LOW STOCK';
        }

        return `
        <div class="stock-row flex justify-between items-center p-3 mb-2 rounded-xl h-[60px] cursor-default" onmouseenter="syncHover(${index}, true)" onmouseleave="syncHover(${index}, false)" id="stat-${index}">
            <div class="stock-health-name text-xs truncate pr-2 w-1/2 font-medium" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</div>
            <span class="status-badge ${badgeClass}">
                ${badgeText}
            </span>
        </div>
    `}).join('');
  }

  // --- SCROLL SYNCHRONIZATION ---
  const itemList = document.getElementById('itemList');
  const statusList = document.getElementById('statusList');

  if(itemList && statusList) {
      itemList.addEventListener('scroll', function() {
          statusList.scrollTop = itemList.scrollTop;
      });
      statusList.addEventListener('scroll', function() {
          itemList.scrollTop = statusList.scrollTop;
      });
  }

  // Visual Sync on Hover (theme-aware via CSS class toggle)
  function syncHover(index, isHovering) {
      const prodRow = document.getElementById(`prod-${index}`);
      const statRow = document.getElementById(`stat-${index}`);
      if(prodRow) {
          prodRow.classList.toggle('row-hovered', isHovering);
      }
      if(statRow) {
          statRow.classList.toggle('row-hovered', isHovering);
      }
  }

  // Helper to get system logo as Base64 PNG for jsPDF
  function getSystemLogoBase64() {
    return new Promise((resolve) => {
      // 1. If base64 SVG data URI is provided from PHP
      if (systemLogoDataUri) {
        const img = new Image();
        img.onload = function() {
          try {
            const canvas = document.createElement('canvas');
            canvas.width = 256;
            canvas.height = 256;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, 256, 256);
            const png = canvas.toDataURL('image/png');
            if (png && png.length > 200) {
              resolve(png);
              return;
            }
          } catch (e) {
            console.warn('Canvas rasterization error:', e);
          }
          fallbackDom();
        };
        img.onerror = function() {
          fallbackDom();
        };
        img.src = systemLogoDataUri;
        return;
      }
      fallbackDom();

      function fallbackDom() {
        const img = new Image();
        img.onload = function() {
          try {
            const canvas = document.createElement('canvas');
            canvas.width = 256;
            canvas.height = 256;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, 256, 256);
            resolve(canvas.toDataURL('image/png'));
            return;
          } catch (e) {
            console.warn('Fallback logo canvas error:', e);
          }
          resolve(null);
        };
        img.onerror = function() {
          resolve(null);
        };
        img.src = 'img/systemlogo.svg';
      }
    });
  }

  async function downloadPDF() {
    if (items.length === 0) { showToast("No data to export", "error"); return; }
    showToast("Generating official PDF report...", "info");

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4'
    });

    const pageWidth = doc.internal.pageSize.getWidth(); // 210mm
    const pageHeight = doc.internal.pageSize.getHeight(); // 297mm
    const totalPagesExp = '{total_pages_count_string}';

    // 1. Top Decorative Brand Bar
    doc.setFillColor(19, 39, 91); // IntelliWare Navy
    doc.rect(0, 0, pageWidth, 5, 'F');
    doc.setFillColor(81, 213, 90); // Green Accent Line
    doc.rect(0, 5, pageWidth, 1.5, 'F');

    // 2. Fetch System Logo & Render
    const logoBase64 = await getSystemLogoBase64();
    if (logoBase64) {
      try {
        doc.addImage(logoBase64, 'PNG', 14, 9.5, 17, 17);
      } catch (e) {
        console.warn('jsPDF addImage error:', e);
      }
    } else {
      // Styled monogram fallback
      doc.setFillColor(19, 39, 91);
      doc.roundedRect(14, 9.5, 17, 17, 3, 3, 'F');
      doc.setDrawColor(81, 213, 90);
      doc.setLineWidth(0.6);
      doc.roundedRect(14, 9.5, 17, 17, 3, 3, 'D');
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(9);
      doc.setTextColor(81, 213, 90);
      doc.text("IW", 22.5, 20.5, { align: 'center' });
    }

    // 3. Header Titles & Official Branding
    const textStartX = 35;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(16);
    doc.setTextColor(19, 39, 91);
    doc.text("INTELLIWARE", textStartX, 15.5);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(100, 116, 139);
    doc.text("Intelligent Warehouse & Inventory Management System", textStartX, 20);

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10.5);
    doc.setTextColor(15, 23, 42);
    doc.text("OFFICIAL INVENTORY & AUDIT REPORT", textStartX, 25.5);

    // Right-aligned report header details
    const now = new Date();
    const reportCode = 'REF: IW-RPT-' + now.toISOString().slice(0,10).replace(/-/g,'') + '-' + Math.floor(1000 + Math.random() * 9000);
    
    // Active Audit Badge Pill
    doc.setFillColor(240, 253, 244);
    doc.setDrawColor(187, 247, 208);
    doc.setLineWidth(0.3);
    doc.roundedRect(pageWidth - 46, 10, 32, 5.5, 1, 1, 'FD');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(6.5);
    doc.setTextColor(22, 163, 74);
    doc.text("ACTIVE INVENTORY AUDIT", pageWidth - 30, 13.8, { align: 'center' });

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.5);
    doc.setTextColor(71, 85, 105);
    doc.text(reportCode, pageWidth - 14, 20, { align: 'right' });
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(148, 163, 184);
    doc.text("Classification: Internal Warehouse Audit", pageWidth - 14, 24.5, { align: 'right' });

    // 4. Executive Metadata Box
    const metaY = 29;
    const metaHeight = 18;
    doc.setFillColor(248, 250, 252);
    doc.setDrawColor(226, 232, 240);
    doc.setLineWidth(0.3);
    doc.roundedRect(14, metaY, pageWidth - 28, metaHeight, 2, 2, 'FD');

    // Dynamic Date and Time (No hardcoded dates)
    const dynamicDateTime = now.toLocaleDateString(undefined, { 
      year: 'numeric', month: 'short', day: 'numeric' 
    }) + ' · ' + now.toLocaleTimeString(undefined, { 
      hour: '2-digit', minute: '2-digit', second: '2-digit' 
    });

    // Column 1: Generator
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(6.5);
    doc.setTextColor(100, 116, 139);
    doc.text("AUDIT GENERATED BY", 18, metaY + 5);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8.5);
    doc.setTextColor(15, 23, 42);
    doc.text(`${reportUser} (${reportRole})`, 18, metaY + 10);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(100, 116, 139);
    doc.text(dynamicDateTime, 18, metaY + 14.5);

    // Column 2: Scope & Parameters
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(6.5);
    doc.setTextColor(100, 116, 139);
    doc.text("REPORT SCOPE & PARAMETERS", 78, metaY + 5);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(30, 41, 59);
    doc.text("Category: " + filterCategory, 78, metaY + 10);
    doc.text("Status: " + filterStatusLabel + "   |   Date: " + filterDateRangeStr, 78, metaY + 14.5);

    // Column 3: Totals & Reconciliation
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(6.5);
    doc.setTextColor(100, 116, 139);
    doc.text("AUDIT RECONCILIATION", 144, metaY + 5);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(15, 23, 42);
    doc.text(`${items.length} SKUs (${Number(totalUnitsCount).toLocaleString()} Total Units)`, 144, metaY + 10);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(100, 116, 139);
    doc.text(`In Stock: ${goodCount}   |   Low: ${lowCount}   |   Out: ${outCount}`, 144, metaY + 14.5);

    // 5. Visual KPI Metric Tiles Directly on PDF Paper
    const kpiY = 50;
    const kpiH = 14;
    const totalAvailW = pageWidth - 28; // 182mm
    const gap = 3.5;
    const boxW = (totalAvailW - (4 * gap)) / 5; // 33.6mm

    const kpiCards = [
      { label: 'TOTAL PRODUCTS', val: Number(items.length).toLocaleString(), sub: 'Unique SKUs', bg: [241, 245, 249], border: [203, 213, 225], text: [15, 23, 42] },
      { label: 'TOTAL UNITS', val: Number(totalUnitsCount).toLocaleString(), sub: 'In Warehouse', bg: [236, 253, 245], border: [167, 243, 208], text: [5, 150, 105] },
      { label: 'GOOD STOCK', val: Number(goodCount).toLocaleString(), sub: 'Optimal Level', bg: [240, 253, 244], border: [187, 247, 208], text: [22, 163, 74] },
      { label: 'LOW STOCK', val: Number(lowCount).toLocaleString(), sub: 'Reorder Soon', bg: [254, 243, 199], border: [253, 230, 138], text: [217, 119, 6] },
      { label: 'OUT OF STOCK', val: Number(outCount).toLocaleString(), sub: 'Critical Depleted', bg: [254, 242, 242], border: [254, 202, 202], text: [220, 38, 38] }
    ];

    kpiCards.forEach((c, idx) => {
      const bx = 14 + idx * (boxW + gap);
      doc.setFillColor(c.bg[0], c.bg[1], c.bg[2]);
      doc.setDrawColor(c.border[0], c.border[1], c.border[2]);
      doc.setLineWidth(0.3);
      doc.roundedRect(bx, kpiY, boxW, kpiH, 1.5, 1.5, 'FD');

      doc.setFont('helvetica', 'bold');
      doc.setFontSize(5.8);
      doc.setTextColor(c.text[0], c.text[1], c.text[2]);
      doc.text(c.label, bx + boxW / 2, kpiY + 3.8, { align: 'center' });

      doc.setFont('helvetica', 'bold');
      doc.setFontSize(10);
      doc.setTextColor(c.text[0], c.text[1], c.text[2]);
      doc.text(c.val, bx + boxW / 2, kpiY + 8.8, { align: 'center' });

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(5.5);
      doc.setTextColor(100, 116, 139);
      doc.text(c.sub, bx + boxW / 2, kpiY + 12.2, { align: 'center' });
    });

    // 6. Table Data Generation
    const tableData = items.map(item => {
      const q = parseInt(item.qty || 0);
      const rp = parseInt(item.reorder_point || 10);
      let statusStr = 'IN STOCK';
      if (q <= 0) statusStr = 'OUT OF STOCK';
      else if (q <= rp) statusStr = 'LOW STOCK';

      return [
        item.sku,
        item.name,
        item.category || 'Uncategorized',
        Number(q).toLocaleString(),
        item.uom || 'pcs',
        Number(rp).toLocaleString(),
        statusStr
      ];
    });

    doc.autoTable({
        head: [['SKU', 'Product Name', 'Category', 'Stock Qty', 'Unit', 'Reorder Point', 'Health Status']],
        body: tableData,
        startY: kpiY + kpiH + 4, // 68mm
        margin: { left: 14, right: 14, bottom: 16 },
        theme: 'grid',
        headStyles: {
            fillColor: [19, 39, 91],
            textColor: [255, 255, 255],
            fontSize: 7.8,
            fontStyle: 'bold',
            halign: 'left',
            valign: 'middle',
            cellPadding: { top: 2.8, bottom: 2.8, left: 3, right: 3 }
        },
        bodyStyles: {
            fontSize: 7.3,
            textColor: [30, 41, 59],
            cellPadding: { top: 2.4, bottom: 2.4, left: 3, right: 3 },
            valign: 'middle',
            lineColor: [226, 232, 240],
            lineWidth: 0.2
        },
        alternateRowStyles: {
            fillColor: [248, 250, 252]
        },
        columnStyles: {
            0: { cellWidth: 20, fontStyle: 'bold', textColor: [51, 65, 85] }, // SKU
            1: { cellWidth: 52, fontStyle: 'bold', textColor: [15, 23, 42] }, // Name
            2: { cellWidth: 32, textColor: [51, 65, 85] }, // Category
            3: { cellWidth: 16, halign: 'right', fontStyle: 'bold', textColor: [15, 23, 42] }, // Stock
            4: { cellWidth: 14, halign: 'center', textColor: [100, 116, 139] }, // Unit
            5: { cellWidth: 18, halign: 'right', textColor: [100, 116, 139] }, // Reorder
            6: { cellWidth: 30, halign: 'center', fontStyle: 'bold' }  // Status
        },
        didParseCell: function(data) {
            if (data.section === 'body' && data.column.index === 6) {
                const val = String(data.cell.raw).toUpperCase();
                if (val.includes('OUT')) {
                    data.cell.styles.textColor = [220, 38, 38];
                    data.cell.styles.fillColor = [254, 242, 242];
                } else if (val.includes('LOW')) {
                    data.cell.styles.textColor = [217, 119, 6];
                    data.cell.styles.fillColor = [254, 243, 199];
                } else {
                    data.cell.styles.textColor = [22, 163, 74];
                    data.cell.styles.fillColor = [240, 253, 244];
                }
            }
        },
        didDrawPage: function(data) {
            // Running header on pages > 1
            if (data.pageNumber > 1) {
                doc.setFillColor(19, 39, 91);
                doc.rect(0, 0, pageWidth, 3, 'F');
                doc.setFillColor(81, 213, 90);
                doc.rect(0, 3, pageWidth, 1, 'F');

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(7.5);
                doc.setTextColor(100, 116, 139);
                doc.text("INTELLIWARE WIMS • OFFICIAL INVENTORY & AUDIT REPORT", 14, 8);

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7);
                doc.setTextColor(148, 163, 184);
                doc.text(reportCode, pageWidth - 14, 8, { align: 'right' });
            }

            // Running footer on all pages
            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(0.3);
            doc.line(14, pageHeight - 11, pageWidth - 14, pageHeight - 11);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(6.8);
            doc.setTextColor(148, 163, 184);
            doc.text("IntelliWare Intelligent Warehouse & Inventory Management System • Confidential Audit Document", 14, pageHeight - 6.5);

            const pageStr = `Page ${data.pageNumber} of ${totalPagesExp}`;
            doc.text(pageStr, pageWidth - 14, pageHeight - 6.5, { align: 'right' });
        }
    });

    // 7. Official Audit Sign-off Block on the last page
    let finalY = doc.lastAutoTable ? doc.lastAutoTable.finalY : (kpiY + kpiH + 30);
    if (finalY + 32 > pageHeight - 15) {
        doc.addPage();
        finalY = 20;
    } else {
        finalY += 8;
    }

    doc.setFillColor(248, 250, 252);
    doc.setDrawColor(226, 232, 240);
    doc.setLineWidth(0.3);
    doc.roundedRect(14, finalY, pageWidth - 28, 22, 1.5, 1.5, 'FD');

    // Left sign-off
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7);
    doc.setTextColor(100, 116, 139);
    doc.text("AUDIT PREPARED BY", 18, finalY + 5);
    doc.setFontSize(8);
    doc.setTextColor(15, 23, 42);
    doc.text(`${reportUser} (${reportRole})`, 18, finalY + 9.5);
    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(18, finalY + 15.5, 85, finalY + 15.5);
    doc.setFontSize(6);
    doc.setTextColor(148, 163, 184);
    doc.text("Authorized Signature & Stamp", 18, finalY + 19);

    // Right sign-off
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7);
    doc.setTextColor(100, 116, 139);
    doc.text("VERIFIED & APPROVED BY", 110, finalY + 5);
    doc.setFontSize(8);
    doc.setTextColor(15, 23, 42);
    doc.text("Warehouse Supervisor / Operations Control", 110, finalY + 9.5);
    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(110, finalY + 15.5, 185, finalY + 15.5);
    doc.setFontSize(6);
    doc.setTextColor(148, 163, 184);
    doc.text("Authorized Signature & Date", 110, finalY + 19);

    // Replace total pages placeholder
    if (typeof doc.putTotalPages === 'function') {
        doc.putTotalPages(totalPagesExp);
    }

    const fileTimestamp = now.toISOString().slice(0,19).replace(/[:T]/g, '-');
    doc.save(`IntelliWare_Inventory_Report_${fileTimestamp}.pdf`);
    showToast("PDF report successfully downloaded!", "success");
  }

  function downloadExcel() {
    if (items.length === 0) { showToast("No data to export", "error"); return; }
    showToast("Exporting Excel report...", "info");
    const dataToExport = items.map(item => {
      const q = parseInt(item.qty || 0);
      const rp = parseInt(item.reorder_point || 10);
      let statusStr = 'IN STOCK';
      if (q <= 0) statusStr = 'OUT OF STOCK';
      else if (q <= rp) statusStr = 'LOW STOCK';

      return {
        'SKU': item.sku,
        'Product Name': item.name,
        'Category': item.category || 'Uncategorized',
        'Stock Quantity': q,
        'Unit of Measure': item.uom || 'pcs',
        'Reorder Point': rp,
        'Health Status': statusStr
      };
    });

    const ws = XLSX.utils.json_to_sheet(dataToExport);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Inventory Audit');
    const fileTimestamp = new Date().toISOString().slice(0,10);
    XLSX.writeFile(wb, `IntelliWare_Inventory_Report_${fileTimestamp}.xlsx`);
    showToast("Excel report successfully downloaded!", "success");
  }

  // Filter Feedback Toast for Reports
  document.addEventListener('DOMContentLoaded', function() {
      if (window.__iwFlashPending) return;

      const urlParams = new URLSearchParams(window.location.search);
      const dateFrom = (urlParams.get('date_from') || '').trim();
      const dateTo = (urlParams.get('date_to') || '').trim();
      const cat = (urlParams.get('category') || '').trim();
      const status = (urlParams.get('status') || '').trim();

      if (!dateFrom && !dateTo && !cat && !status) return;

      const count = items.length;
      const parts = [];

      if (dateFrom && dateTo) {
          parts.push(`${dateFrom} to ${dateTo}`);
      } else if (dateFrom) {
          parts.push(`from ${dateFrom}`);
      } else if (dateTo) {
          parts.push(`until ${dateTo}`);
      }

      if (cat) parts.push(cat);

      if (status) {
          const statusNames = {
              'good': 'Good Stock',
              'attention': 'Attention Needed',
              'low': 'Low Stock',
              'out': 'Out of Stock'
          };
          parts.push(statusNames[status] || status);
      }

      const desc = parts.join(' • ');

      if (count === 0) {
          showToast(`No records found for ${desc}`, 'warning');
      } else {
          const itemWord = count === 1 ? 'item' : 'items';
          showToast(`Report generated: ${count.toLocaleString()} ${itemWord} (${desc})`, 'info');
      }
  });
</script>

<?php renderLayoutEnd(); ?>