<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

require_once 'src/layout.php';
require_once 'config/db.php';
require_once 'includes/report_document.php';

// --- 1. DATA FETCHING LOGIC ---

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? ''; // New Filter

$mainWhereConditions = ['archived = 0'];
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
    p.product_id as id, 
    CONCAT('P-', LPAD(p.product_id, 4, '0')) as sku, 
    p.product_name as name, 
    p.category, 
    p.quantity as qty, 
    p.unit as uom, 
    p.reorder_point,
    COALESCE(NULLIF(TRIM(p.supplier),''), 'No supplier') as supplier,
    COALESCE(p.location,'') as location,
    COALESCE(p.price, 0) as price,
    (SELECT COUNT(*) FROM product_batches b WHERE b.product_id = p.product_id AND b.qty > 0) as batch_count,
    (SELECT MIN(b.expiry_date) FROM product_batches b WHERE b.product_id = p.product_id AND b.qty > 0) as nearest_expiry,
    p.created_at
FROM products p 
$mainWhereClause 
ORDER BY p.category, p.product_name";

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
$categoriesQuery = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND archived = 0 ORDER BY category";
$allCategories = [];
$resultCat = $conn->query($categoriesQuery);
if ($resultCat) {
    while ($row = $resultCat->fetch_assoc()) {
        $allCategories[] = $row['category'];
    }
}

// Fetch unique suppliers (for Reorder Report filter)
$allSuppliers = [];
$suppQuery = "SELECT DISTINCT supplier FROM products WHERE supplier IS NOT NULL AND supplier != '' AND archived = 0 ORDER BY supplier";
$suppResult = $conn->query($suppQuery);
if ($suppResult) {
    while ($row = $suppResult->fetch_assoc()) {
        $allSuppliers[] = trim($row['supplier']);
    }
}

// --- REORDER REPORT DATASET & FILTERS ---
$rrViewActive = (isset($_GET['view']) && $_GET['view'] === 'reorder');
$rrCritical   = isset($_GET['rr_critical']) ? (int)$_GET['rr_critical'] : 1;
$rrReorder    = isset($_GET['rr_reorder'])  ? (int)$_GET['rr_reorder']  : 1;
if (isset($_GET['rr_submitted'])) {
    $rrCritical = !empty($_GET['rr_critical']) ? 1 : 0;
    $rrReorder  = !empty($_GET['rr_reorder'])  ? 1 : 0;
}
$rrSupplier   = isset($_GET['rr_supplier']) ? trim($_GET['rr_supplier']) : '';
$rrCategory   = isset($_GET['rr_category']) ? trim($_GET['rr_category']) : '';
$rrSort       = isset($_GET['rr_sort'])     ? trim($_GET['rr_sort'])     : 'lowest_stock';

// Build prepared statement for Reorder items
// For direct CSV export, apply GET filters if specified; for HTML page rendering, fetch all low stock items so client live filtering has the complete dataset.
$isReorderExport = (isset($_GET['export']) && $_GET['export'] === 'reorder_csv');

$rrWhereConditions = ['p.archived = 0', 'p.quantity <= p.reorder_point'];
$rrParams = [];
$rrTypes  = '';

if ($isReorderExport) {
    if ($rrSupplier !== '') {
        if ($rrSupplier === 'No supplier') {
            $rrWhereConditions[] = "(p.supplier IS NULL OR TRIM(p.supplier) = '')";
        } else {
            $rrWhereConditions[] = "p.supplier = ?";
            $rrParams[] = $rrSupplier;
            $rrTypes .= 's';
        }
    }

    if ($rrCategory !== '') {
        $rrWhereConditions[] = "p.category = ?";
        $rrParams[] = $rrCategory;
        $rrTypes .= 's';
    }
}

$rrSortClause = ($rrSort === 'name_asc')
    ? "COALESCE(NULLIF(TRIM(p.supplier),''), 'No supplier') ASC, p.product_name ASC"
    : "COALESCE(NULLIF(TRIM(p.supplier),''), 'No supplier') ASC, p.quantity ASC, p.product_name ASC";

$rrQuery = "SELECT
    p.product_id as id,
    CONCAT('P-', LPAD(p.product_id, 4, '0')) as sku,
    p.product_name as name,
    p.category,
    p.quantity as qty,
    p.unit as uom,
    p.reorder_point,
    COALESCE(NULLIF(TRIM(p.supplier),''), 'No supplier') as supplier,
    COALESCE(p.location,'') as location,
    COALESCE(p.price, 0) as price,
    (SELECT COUNT(*) FROM product_batches b WHERE b.product_id = p.product_id AND b.qty > 0) as batch_count,
    (SELECT MIN(b.expiry_date) FROM product_batches b WHERE b.product_id = p.product_id AND b.qty > 0) as nearest_expiry
FROM products p
WHERE " . implode(' AND ', $rrWhereConditions) . "
ORDER BY $rrSortClause";

$rawReorderItems = [];
if ($stmt = $conn->prepare($rrQuery)) {
    if (!empty($rrParams)) {
        $stmt->bind_param($rrTypes, ...$rrParams);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $qty = (float)$row['qty'];
            $rp  = (float)$row['reorder_point'];
            // Critical: total stock = 0 OR total stock <= 50% of reorder point
            $isCritical = ($qty <= 0 || ($rp > 0 && $qty <= (0.5 * $rp)) || ($rp == 0 && $qty <= 0));
            $row['reorder_status'] = $isCritical ? 'critical' : 'reorder';
            $rawReorderItems[] = $row;
        }
    }
    $stmt->close();
}

// Filter dataset according to selected status chips
$reorderItems = [];
$reorderCriticalCount = 0;
$reorderLowCount = 0;
foreach ($rawReorderItems as $ri) {
    if ($ri['reorder_status'] === 'critical') {
        $reorderCriticalCount++;
    } else {
        $reorderLowCount++;
    }

    if ($isReorderExport) {
        if ($ri['reorder_status'] === 'critical' && $rrCritical) $reorderItems[] = $ri;
        if ($ri['reorder_status'] !== 'critical' && $rrReorder)  $reorderItems[] = $ri;
    } else {
        $reorderItems[] = $ri;
    }
}

// Group in PHP by supplier
$reorderBySupplier = [];
foreach ($reorderItems as $ri) {
    $sup = $ri['supplier'];
    if (!isset($reorderBySupplier[$sup])) {
        $reorderBySupplier[$sup] = [
            'supplier' => $sup,
            'items'    => [],
            'critical' => 0,
            'low'      => 0
        ];
    }
    $reorderBySupplier[$sup]['items'][] = $ri;
    if ($ri['reorder_status'] === 'critical') {
        $reorderBySupplier[$sup]['critical']++;
    } else {
        $reorderBySupplier[$sup]['low']++;
    }
}

// Summary cards metrics for Reorder Report
$rrTotalItemsToRestock = count($reorderItems);
$rrTotalCriticalItems  = $reorderCriticalCount;
$rrTotalReorderItems   = $reorderLowCount;
$rrSuppliersAffectedCount = count($reorderBySupplier);

// Handle server-side CSV export if requested
if (isset($_GET['export']) && $_GET['export'] === 'reorder_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=IntelliWare_Reorder_Report_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SKU', 'Name', 'Category', 'Supplier', 'Location', 'Total Stock', 'Unit', 'Batches', 'Nearest Expiry', 'Price', 'Reorder Point', 'Status']);
    foreach ($reorderItems as $item) {
        $bCount = (int)$item['batch_count'];
        $bText  = $bCount > 0 ? $bCount . ' batch' . ($bCount > 1 ? 'es' : '') : 'No batches';
        $expText = $item['nearest_expiry'] ? date('M d, Y', strtotime($item['nearest_expiry'])) : 'No Expiry';
        fputcsv($out, [
            $item['sku'],
            $item['name'],
            $item['category'],
            $item['supplier'],
            $item['location'],
            $item['qty'],
            $item['uom'] ?: 'pcs',
            $bText,
            $expText,
            number_format((float)$item['price'], 2),
            $item['reorder_point'],
            strtoupper($item['reorder_status'])
        ]);
    }
    fclose($out);
    exit();
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

    /* Stock Explorer Scrollable Grid & Columns */
    .stock-explorer-scroll-wrap {
        width: 100%;
        max-width: 100%;
    }
    .stock-explorer-grid {
        display: grid;
        grid-template-columns: 3fr 5fr 4fr;
        gap: 1.5rem;
        height: 560px;
        min-height: 560px;
    }
    .stock-explorer-col {
        min-height: 0;
        height: 100%;
    }
    .stock-explorer-scroll-wrap.iw-table-scroll {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }

    /* View Mode Switcher */
    .report-view-toggle-wrap {
        display: inline-flex;
        align-items: center;
        padding: 3px;
        border-radius: 12px;
        background: rgba(13, 22, 56, 0.7);
        border: 1px solid rgba(42, 68, 132, 0.5);
    }
    .report-view-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 9px;
        font-size: 12px;
        font-weight: 600;
        font-family: 'Onest', sans-serif;
        color: #9aa6d1;
        background: transparent;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .report-view-tab:hover {
        color: #E3E3E3;
        background: rgba(255, 255, 255, 0.05);
    }
    .report-view-tab.active {
        color: #51D55A;
        background: rgba(81, 213, 90, 0.15);
        border-color: rgba(81, 213, 90, 0.4);
        box-shadow: 0 2px 8px rgba(81, 213, 90, 0.15);
    }

    /* Light Mode View Switcher */
    [data-theme="light"] .report-view-toggle-wrap {
        background: #EFF6FF !important;
        border-color: #BFDBFE !important;
    }
    [data-theme="light"] .report-view-tab {
        color: #64748B !important;
    }
    [data-theme="light"] .report-view-tab:hover {
        color: #0F172A !important;
        background: #DBEAFE !important;
    }
    [data-theme="light"] .report-view-tab.active {
        color: #15803D !important;
        background: #FFFFFF !important;
        border-color: #86EFAC !important;
        box-shadow: 0 1px 3px rgba(16, 24, 43, 0.08) !important;
    }

    /* ── Reorder Report Section ─────────────────────────────────── */
    .rr-filter-grid {
        display: grid;
        grid-template-columns: minmax(190px, 1.2fr) minmax(140px, 1fr) minmax(140px, 1fr) minmax(160px, 1.1fr) auto;
        gap: 16px;
        align-items: end;
    }
    @media (max-width: 1024px) {
        .rr-filter-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 640px) {
        .rr-filter-grid {
            grid-template-columns: 1fr;
        }
    }
    .rr-filter-col {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .rr-filter-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        line-height: 16px;
        height: 16px;
        margin-bottom: 8px;
        color: #9aa6d1;
        font-family: 'Onest', sans-serif;
        white-space: nowrap;
    }
    [data-theme="light"] .rr-filter-label {
        color: #475569 !important;
    }
    .rr-filter-btn-col {
        display: flex;
        align-items: flex-end;
    }
    .rr-btn-generate {
        white-space: nowrap;
        cursor: pointer;
    }

    /* Status Toggles */
    .reorder-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 38px;
        padding: 0 14px;
        border-radius: 9px;
        font-size: 11px;
        font-weight: 700;
        font-family: 'Onest', sans-serif;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        cursor: pointer;
        border: 1.5px solid transparent;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        white-space: nowrap;
    }
    .reorder-chip .chip-check {
        display: none;
    }
    .reorder-chip.active .chip-check {
        display: inline-block;
    }

    /* Dark mode active */
    .reorder-chip.critical.active {
        background: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: #ef4444;
        box-shadow: 0 0 12px rgba(239, 68, 68, 0.25);
    }
    .reorder-chip.reorder.active {
        background: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
        border-color: #f59e0b;
        box-shadow: 0 0 12px rgba(245, 158, 11, 0.25);
    }

    /* Dark mode off (outlined / muted) */
    .reorder-chip:not(.active) {
        background: transparent !important;
        color: #64748b !important;
        border-color: rgba(71, 85, 105, 0.5) !important;
        box-shadow: none !important;
        opacity: 0.75;
    }
    .reorder-chip:not(.active):hover {
        border-color: rgba(148, 163, 184, 0.6) !important;
        color: #94a3b8 !important;
        opacity: 1;
    }

    /* Light mode active */
    [data-theme="light"] .reorder-chip.critical.active {
        background: #FEE2E2 !important;
        color: #991B1B !important;
        border-color: #EF4444 !important;
        box-shadow: 0 1px 3px rgba(185, 28, 28, 0.15) !important;
    }
    [data-theme="light"] .reorder-chip.reorder.active {
        background: #FEF3C7 !important;
        color: #92400E !important;
        border-color: #F59E0B !important;
        box-shadow: 0 1px 3px rgba(180, 83, 9, 0.15) !important;
    }

    /* Light mode off (outlined / muted) */
    [data-theme="light"] .reorder-chip:not(.active) {
        background: #FFFFFF !important;
        color: #94A3B8 !important;
        border-color: #CBD5E1 !important;
        box-shadow: none !important;
        opacity: 0.8 !important;
    }
    [data-theme="light"] .reorder-chip:not(.active):hover {
        border-color: #94A3B8 !important;
        color: #64748B !important;
        opacity: 1 !important;
    }

    /* Results Header Strip */
    .rr-results-header {
        border-bottom: 1px solid rgba(42, 68, 132, 0.4);
        background: rgba(19, 39, 91, 0.25);
    }
    [data-theme="light"] .rr-results-header {
        background: #FFFFFF !important;
        border-bottom-color: #E2E8F0 !important;
    }
    [data-theme="light"] #rrResultsMeta {
        color: #64748B !important;
    }
    [data-theme="light"] #rrResultsMeta strong {
        color: #0F172A !important;
    }

    /* Action Buttons in Results Header (Print & Export CSV) */
    .rr-hdr-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 36px;
        padding: 0 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        font-family: 'Onest', sans-serif;
        cursor: pointer;
        background: rgba(81, 213, 90, 0.1);
        color: #51D55A;
        border: 1px solid rgba(81, 213, 90, 0.35);
        transition: all 0.2s ease;
        user-select: none;
        white-space: nowrap;
    }
    .rr-hdr-btn:hover {
        background: rgba(81, 213, 90, 0.2);
        border-color: #51D55A;
        transform: translateY(-1px);
    }
    [data-theme="light"] .rr-hdr-btn {
        background: #F0FDF4 !important;
        color: #15803D !important;
        border: 1px solid #86EFAC !important;
        box-shadow: 0 1px 2px rgba(21, 128, 61, 0.05) !important;
    }
    [data-theme="light"] .rr-hdr-btn:hover {
        background: #DCFCE7 !important;
        border-color: #4ADE80 !important;
        color: #166534 !important;
        box-shadow: 0 2px 4px rgba(21, 128, 61, 0.12) !important;
    }

    /* Supplier Group Header Row */
    tr.rr-group-header td {
        background: rgba(19, 39, 91, 0.65) !important;
        padding: 11px 16px;
        border-top: 1px solid rgba(42, 68, 132, 0.5) !important;
        border-bottom: 1px solid rgba(42, 68, 132, 0.5) !important;
    }
    .rr-supplier-name {
        font-weight: 700;
        font-size: 14px;
        color: #93c5fd;
        font-family: 'Onest', sans-serif;
    }
    .rr-supplier-meta {
        font-size: 12px;
        font-family: 'Onest', sans-serif;
    }
    [data-theme="light"] #reportReorderView tr.rr-group-header td {
        background: #EFF6FF !important;
        border-top-color: #DBEAFE !important;
        border-bottom-color: #DBEAFE !important;
    }
    [data-theme="light"] #reportReorderView .rr-supplier-name {
        color: #1E3A8A !important;
    }

    /* Total Stock Progress Bar */
    .rr-stock-bar-track {
        width: 56px;
        height: 4px;
        border-radius: 9999px;
        background: rgba(148, 163, 184, 0.25);
        overflow: hidden;
        margin-top: 4px;
    }
    [data-theme="light"] .rr-stock-bar-track {
        background: #E2E8F0 !important;
    }
    .rr-stock-bar {
        height: 100%;
        border-radius: 9999px;
        transition: width 0.3s ease;
    }

    /* Table Column Wrap / Nowrap Control (Audit & Reorder Tables) */
    #rrMainTable th:not(.rr-col-name),
    #rrMainTable td:not(.rr-col-name),
    #reportTableView table.premium-table th:not(.rr-col-name),
    #reportTableView table.premium-table td:not(.rr-col-name) {
        white-space: nowrap !important;
    }
    #rrMainTable th.rr-col-name,
    #rrMainTable td.rr-col-name,
    #reportTableView table.premium-table th.rr-col-name,
    #reportTableView table.premium-table td.rr-col-name {
        min-width: 200px;
        max-width: 320px;
        white-space: normal !important;
        word-break: break-word;
    }

    /* Row Hover Highlight */
    #rrMainTable tr.rr-item-row td,
    #reportTableView table.premium-table tbody tr td {
        transition: background-color 0.15s ease-in-out;
    }
    #rrMainTable tr.rr-item-row:hover td,
    #reportTableView table.premium-table tbody tr:hover td {
        background-color: rgba(30, 58, 138, 0.22) !important;
    }
    [data-theme="light"] #rrMainTable tr.rr-item-row:hover td,
    [data-theme="light"] #reportTableView table.premium-table tbody tr:hover td {
        background-color: #F8FAFC !important;
    }

    /* Light Mode Summary Cards Icon Tiles */
    [data-theme="light"] #reorderSummaryCards .tile-restock {
        background: #EFF6FF !important;
        border-color: #BFDBFE !important;
        color: #2563EB !important;
    }
    [data-theme="light"] #reorderSummaryCards .tile-critical {
        background: #FEF2F2 !important;
        border-color: #FECACA !important;
        color: #DC2626 !important;
    }
    [data-theme="light"] #reorderSummaryCards .tile-reorder {
        background: #FFFBEB !important;
        border-color: #FDE68A !important;
        color: #D97706 !important;
    }
    [data-theme="light"] #reorderSummaryCards .tile-suppliers {
        background: #ECFDF5 !important;
        border-color: #A7F3D0 !important;
        color: #059669 !important;
    }

    /* Light Mode Column Borders */
    [data-theme="light"] .stock-explorer-col.border-r {
        border-right-color: #D0DFF2 !important;
    }

    /* Light Mode Table Elements (Detailed Audit & Reorder Tables) */
    .stock-num {
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        color: #F1F5F9;
        transition: color 0.15s ease;
    }
    .stock-unit {
        color: #9aa6d1;
        font-size: 12px;
        font-weight: 400;
        font-family: 'Onest', sans-serif;
    }
    [data-theme="light"] .stock-num,
    [data-theme="light"] #reportTableView .stock-num,
    [data-theme="light"] #rrMainTable .stock-num {
        color: #0F172A !important;
    }
    [data-theme="light"] .stock-unit,
    [data-theme="light"] #reportTableView .stock-unit,
    [data-theme="light"] #rrMainTable .stock-unit {
        color: #475569 !important;
    }
    [data-theme="light"] #reportTableView .product-title-text,
    [data-theme="light"] #reportReorderView .product-title-text {
        color: #0F172A !important;
    }
    [data-theme="light"] #reportTableView .product-sku-text,
    [data-theme="light"] #reportReorderView .product-sku-text {
        color: #64748B !important;
    }
    [data-theme="light"] #reportTableView .table-cat-badge,
    [data-theme="light"] #reportReorderView .table-cat-badge {
        background: #EFF6FF !important;
        border-color: #BFDBFE !important;
        color: #1E3A8A !important;
    }
    [data-theme="light"] #reportTablePagination {
        border-top-color: #D6E2F2 !important;
    }
    [data-theme="light"] #reorderEmptyState .text-white {
        color: #0F172A !important;
    }
    [data-theme="light"] #reorderEmptyState .text-\[\#9aa6d1\] {
        color: #64748B !important;
    }
    [data-theme="light"] #rrFilterForm label,
    [data-theme="light"] #rrFilterForm span.text-\[\#9aa6d1\] {
        color: #475569 !important;
    }
    [data-theme="light"] #rrFilterSummary {
        color: #64748B !important;
    }

    /* Mobile / Tablet Horizontal Scroll for Explorer (< 1024px) */
    @media (max-width: 1023px) {
        .stock-explorer-grid {
            display: grid !important;
            grid-template-columns: 240px 340px 280px !important;
            min-width: 860px !important;
            height: 540px !important;
            min-height: 540px !important;
            gap: 16px !important;
            padding: 16px !important;
        }
        .stock-explorer-grid .col-categories > div:last-child,
        .stock-explorer-grid #itemList,
        .stock-explorer-grid #statusList {
            height: 470px !important;
        }
    }

    /* Mobile swipe hint */
    .mobile-scroll-hint {
        user-select: none;
    }
    [data-theme="light"] .mobile-scroll-hint {
        color: #475569 !important;
    }

    .print-only { display: none !important; }

    /* ── Page Hero Card ────────────────────────────────────────── */
    .page-hero-card {
        background: linear-gradient(135deg, rgba(29, 56, 123, 0.92) 0%, rgba(18, 39, 91, 0.96) 100%);
        border: 1px solid rgba(42, 68, 132, 0.6);
        border-radius: 18px;
        padding: 24px 28px;
        margin-bottom: 24px;
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
</style>

<?php renderReportDocumentStyles(false); ?>

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

<div id="topGenerateReportCard" class="premium-card mb-6 <?php echo $rrViewActive ? 'hidden' : ''; ?>" style="overflow:visible;">
    <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
        <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Generate Report
        </h3>
    </div>
    
    <div class="p-5">
        <form method="GET" action="reports.php" class="flex flex-col lg:flex-row lg:items-end gap-3 w-full flex-wrap xl:flex-nowrap">
            <div class="w-full sm:flex-1 min-w-0">
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Date Added (From)</label>
                <input type="date" name="date_from" class="premium-input w-full h-[46px]" value="<?php echo htmlspecialchars($dateFrom); ?>" />
            </div>
            <div class="w-full sm:flex-1 min-w-0">
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Date Added (To)</label>
                <input type="date" name="date_to" class="premium-input w-full h-[46px]" value="<?php echo htmlspecialchars($dateTo); ?>" />
            </div>
            <div class="w-full sm:flex-1 min-w-0">
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
            <div class="w-full sm:flex-1 min-w-0">
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Stock Status</label>
                <select name="status" class="premium-input inventory-select w-full h-[46px] cursor-pointer">
                    <option value="">All Status</option>
                    <option value="good" <?php echo $statusFilter === 'good' ? 'selected' : ''; ?>>Good Stock</option>
                    <option value="attention" <?php echo $statusFilter === 'attention' ? 'selected' : ''; ?>>Attention Needed (Low/Out)</option>
                    <option value="low" <?php echo $statusFilter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out" <?php echo $statusFilter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>

            <div class="reports-filter-actions flex items-center gap-2 w-full lg:w-auto shrink-0 mt-1 lg:mt-0">
                <button class="reports-btn-icon btn-secondary h-[42px] lg:h-[46px] w-[42px] lg:w-[46px] min-w-[42px] lg:min-w-[46px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" type="submit" title="Search Reports" aria-label="Search">
                    <svg class="w-4.5 h-4.5 lg:w-5 lg:h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
                <a href="reports.php" class="reports-btn-icon btn-secondary h-[42px] lg:h-[46px] w-[42px] lg:w-[46px] min-w-[42px] lg:min-w-[46px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Reset Filters" aria-label="Reset Filters">
                    <svg class="w-4.5 h-4.5 lg:w-5 lg:h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </a>
                <button type="button" onclick="downloadPDF()" class="reports-btn-export premium-button h-[42px] lg:h-[46px] px-3 sm:px-4 text-xs font-semibold flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl flex-1 lg:flex-initial" title="Generate &amp; Download PDF Report">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>PDF</span>
                </button>
                <button type="button" onclick="downloadExcel()" class="reports-btn-export premium-button h-[42px] lg:h-[46px] px-3 sm:px-4 text-xs font-semibold flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl flex-1 lg:flex-initial" title="Generate &amp; Download Excel Report">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Excel</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="defaultSummaryCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6 no-print <?php echo $rrViewActive ? 'hidden' : ''; ?>">
    <div class="premium-card p-6 flex items-center justify-between group hover:border-blue-500/50 cursor-pointer transition-all <?php echo empty($statusFilter) ? 'border-blue-500/40' : ''; ?>" onclick="window.location.href='reports.php<?php echo $categoryFilter ? ('?category=' . urlencode($categoryFilter)) : ''; ?>'">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Total Products</div>
            <div class="premium-metric">
                <span class="num text-3xl"><?php echo number_format($totalItemsFiltered); ?></span>
            </div>
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Active catalog SKUs</div>
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
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Current warehouse inventory</div>
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
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Items requiring restock</div>
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
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Fully depleted items</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
        </div>
    </div>
</div>

<!-- Reorder Report Summary Cards (4 Cards: Items to restock, Critical, Reorder, Suppliers affected) -->
<div id="reorderSummaryCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6 no-print <?php echo $rrViewActive ? '' : 'hidden'; ?>">
    <div class="premium-card p-6 flex items-center justify-between group hover:border-blue-500/50 transition-all">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Items to Restock</div>
            <div class="premium-metric">
                <span id="cardValRestock" class="num text-3xl"><?php echo number_format($rrTotalItemsToRestock); ?></span>
            </div>
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Products requiring replenishment</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 dark:bg-blue-500/15 border border-blue-500/20 text-blue-500 dark:text-blue-400 tile-restock flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-red-500/50 transition-all">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Critical</div>
            <div class="premium-metric">
                <span id="cardValCritical" class="num text-3xl" style="background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($rrTotalCriticalItems); ?></span>
            </div>
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Stock &le; 50% reorder point or 0</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 dark:bg-red-500/15 border border-red-500/20 text-red-500 dark:text-red-400 tile-critical flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-amber-500/50 transition-all">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Reorder</div>
            <div class="premium-metric">
                <span id="cardValReorder" class="num text-3xl" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($rrTotalReorderItems); ?></span>
            </div>
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Stock &le; reorder threshold</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 dark:bg-amber-500/15 border border-amber-500/20 text-amber-500 dark:text-amber-400 tile-reorder flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
    </div>

    <div class="premium-card p-6 flex items-center justify-between group hover:border-emerald-500/50 transition-all">
        <div>
            <div class="text-[#9aa6d1] text-xs font-onest uppercase tracking-wider mb-1">Suppliers Affected</div>
            <div class="premium-metric">
                <span id="cardValSuppliers" class="num text-3xl" style="background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($rrSuppliersAffectedCount); ?></span>
            </div>
            <div class="stat-card-sub text-xs text-[#9aa6d1] mt-1 font-onest">Vendors to place orders with</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/15 border border-emerald-500/20 text-emerald-500 dark:text-emerald-400 tile-suppliers flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
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

    <!-- Printable Report (Universal Document Layout - Active Only During Window.print()) -->
    <?php
    renderReportDocument([
        'title' => 'OFFICIAL INVENTORY & AUDIT REPORT',
        'subtitle' => 'Intelligent Warehouse & Inventory Management System',
        'tag' => 'IMS',
        'badge' => 'Active Audit',
        'badge_bg' => '#f0fdf4',
        'badge_border' => '#bbf7d0',
        'badge_color' => '#16a34a',
        'ref' => 'IW-RPT-' . date('Ymd-His'),
        'classification' => 'Classification: Internal Warehouse Audit',
        'generated_by' => $generatedBy,
        'generated_role' => $generatedRole,
        'generated_at' => $generatedAt,
        'prep_head' => 'Audit Generated By',
        'scope_line1' => '<strong>Category:</strong> ' . ($categoryFilter ? htmlspecialchars($categoryFilter) : 'All Categories'),
        'scope_line2' => '<strong>Status:</strong> ' . ($statusFilter ? ucfirst(htmlspecialchars($statusFilter)) : 'All Statuses'),
        'recon_head' => 'Audit Reconciliation',
        'recon_line1' => '<strong>Products:</strong> ' . number_format($totalItemsFiltered) . ' (' . number_format($totalUnits) . ' Total Units)',
        'recon_line2' => 'Good: ' . $goodStockCount . ' | Low: ' . $lowStockCount . ' | Out: ' . $outOfStockCount,
        'kpi_tiles' => [
            ['label' => 'Total Products', 'value' => number_format($totalItemsFiltered), 'sub' => 'Unique SKUs', 'bg' => '#f1f5f9', 'border' => '#cbd5e1', 'val_color' => '#0f172a', 'lbl_color' => '#475569', 'sub_color' => '#64748b'],
            ['label' => 'Total Units', 'value' => number_format($totalUnits), 'sub' => 'In Warehouse', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'val_color' => '#059669', 'lbl_color' => '#059669', 'sub_color' => '#047857'],
            ['label' => 'Good Stock', 'value' => number_format($goodStockCount), 'sub' => 'Optimal Level', 'bg' => '#f0fdf4', 'border' => '#bbf7d0', 'val_color' => '#16a34a', 'lbl_color' => '#16a34a', 'sub_color' => '#15803d'],
            ['label' => 'Low Stock', 'value' => number_format($lowStockCount), 'sub' => 'Reorder Soon', 'bg' => '#fef3c7', 'border' => '#fde68a', 'val_color' => '#d97706', 'lbl_color' => '#b45309', 'sub_color' => '#b45309'],
            ['label' => 'Out of Stock', 'value' => number_format($outOfStockCount), 'sub' => 'Depleted', 'bg' => '#fef2f2', 'border' => '#fecaca', 'val_color' => '#dc2626', 'lbl_color' => '#dc2626', 'sub_color' => '#b91c1c'],
        ],
        'items' => $items,
        'table_type' => 'reports_audit',
        'is_print_only' => true,
        'footer_text' => 'IntelliWare IMS • Confidential Inventory Audit Document',
        'show_signatures' => true
    ]);
    ?>

    <!-- Stock Explorer Card -->
    <div class="premium-card mb-6">
        <div class="p-5 border-b border-[#2A4484]/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-[#13275B]/30 stock-explorer-header text-left">
            <div class="text-left w-full sm:w-auto">
                <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2 text-left justify-start">
                    <span id="stockExplorerTitle">Stock Explorer</span>
                </h3>
                <p id="stockExplorerSubtitle" class="text-xs text-[#9aa6d1] mt-1 text-left">
                    <?php echo $rrViewActive ? 'Generate a restock report from current stock levels.' : 'Showing ' . number_format($totalItemsFiltered) . ' items based on current filters.'; ?>
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto w-full sm:w-auto justify-start sm:justify-end">
                <div class="report-view-toggle-wrap">
                    <button type="button" id="tabExplorerBtn" onclick="switchReportView('explorer')" class="report-view-tab <?php echo !$rrViewActive ? 'active' : ''; ?>" title="Interactive 3-Column Explorer">
                        <span>Interactive Explorer</span>
                    </button>
                    <button type="button" id="tabTableBtn" onclick="switchReportView('table')" class="report-view-tab" title="Detailed Audit Table View">
                        <span>Detailed Audit Table</span>
                    </button>
                    <button type="button" id="tabReorderBtn" onclick="switchReportView('reorder')" class="report-view-tab <?php echo $rrViewActive ? 'active' : ''; ?>" title="Reorder Report — Critical &amp; Low Stock">
                        <span>Reorder Report</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Explorer View Container -->
        <div id="reportExplorerView">
            <div class="mobile-scroll-hint lg:hidden px-6 pt-3 pb-0 text-xs text-[#9aa6d1] flex items-center gap-1.5">
                <svg class="w-4 h-4 text-[#51D55A] animate-pulse shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Swipe horizontally to navigate Categories, Products &amp; Stock Health &rarr;</span>
            </div>
            
            <div class="stock-explorer-scroll-wrap iw-table-scroll">
                <div class="stock-explorer-grid p-6">
                    
                    <!-- Column 1: Categories -->
                    <div class="stock-explorer-col col-categories flex flex-col h-full border-r border-[#2A4484]/30 pr-4">
                        <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider mb-3 px-1">Categories</div>
                        
                        <div class="overflow-y-auto premium-scrollbar pr-2 space-y-2 h-[520px] min-h-0">
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

                    <!-- Column 2: Products -->
                    <div class="stock-explorer-col col-products flex flex-col h-full border-r border-[#2A4484]/30 px-4">
                        <div class="flex justify-between items-center mb-3 px-1">
                            <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider">Products</div>
                            <div id="itemPanelMeta" class="text-[10px] text-[#51D55A]">Select a category</div>
                        </div>
                        
                        <div class="overflow-y-auto premium-scrollbar pr-2 bg-[#0d1638]/50 rounded-xl border border-[#2A4484]/30 p-2 h-[520px] min-h-0" id="itemList">
                            <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60 min-h-[150px]">
                                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                <span class="text-xs">Select a category on the left</span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Stock Health -->
                    <div class="stock-explorer-col col-health flex flex-col h-full pl-2">
                        <div class="flex justify-between items-center mb-3 px-1">
                            <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider">Stock Health</div>
                            <div id="statusPanelMeta" class="text-[10px] text-[#9aa6d1]">Overview</div>
                        </div>
                        
                        <div class="overflow-y-auto premium-scrollbar pr-2 bg-[#0d1638]/50 rounded-xl border border-[#2A4484]/30 p-2 h-[520px] min-h-0" id="statusList">
                            <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60 min-h-[150px]">
                                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span class="text-xs">Status details will appear here</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <?php
        // Universal Table Header (Used across Detailed Audit & Reorder Report tables)
        if (!function_exists('renderUniversalReportTableHeader')) {
            function renderUniversalReportTableHeader() {
        ?>
            <thead>
                <tr>
                    <th style="width:70px;">SKU</th>
                    <th class="rr-col-name" style="min-width:200px;">Name</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th>Location</th>
                    <th>Total Stock</th>
                    <th style="min-width:140px;">Batches / Expiry</th>
                    <th>Price</th>
                    <th>Unit</th>
                    <th style="text-align:right;">Reorder</th>
                    <th style="text-align:center;">Status</th>
                </tr>
            </thead>
        <?php
            }
        }
        ?>

        <!-- Detailed Audit Table Container -->
        <div id="reportTableView" class="hidden">
            <div class="mobile-scroll-hint lg:hidden px-6 pt-3 pb-0 text-xs text-[#9aa6d1] flex items-center gap-1.5">
                <svg class="w-4 h-4 text-[#51D55A] animate-pulse shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Swipe horizontally to inspect full audit ledger &rarr;</span>
            </div>
            <div class="p-4 sm:p-6">
                <div class="iw-table-scroll" style="overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; width: 100% !important; max-width: 100% !important; display: block !important;">
                    <table class="premium-table w-full text-left" id="auditMainTable" style="min-width: 1050px;">
                        <?php renderUniversalReportTableHeader(); ?>
                        <tbody id="reportTableBody">
                            <?php 
                            foreach ($items as $it): 
                                $itQty        = (int)($it['qty'] ?? 0);
                                $itReorder    = (int)($it['reorder_point'] ?? 10);
                                $itPrice      = number_format((float)($it['price'] ?? 0), 2);
                                $itBatchCount = (int)($it['batch_count'] ?? 0);
                                $itNearestExp = $it['nearest_expiry'] ?? null;
                                $itBatchText  = $itBatchCount > 0 ? $itBatchCount . ' batch' . ($itBatchCount > 1 ? 'es' : '') : 'No batches';
                                $itExpText    = $itNearestExp ? 'Exp: ' . date('M j, Y', strtotime($itNearestExp)) : 'No Expiry';
                                
                                $badgeCls = 'good';
                                $badgeTxt = 'IN STOCK';
                                if ($itQty <= 0) {
                                    $badgeCls = 'out';
                                    $badgeTxt = 'OUT OF STOCK';
                                } elseif ($itQty <= $itReorder) {
                                    $badgeCls = 'low';
                                    $badgeTxt = 'LOW STOCK';
                                }

                                if ($itQty <= 0) {
                                    $itBarWidth = 0;
                                    $itBarColor = '#EF4444';
                                } elseif ($itReorder > 0) {
                                    $itBarWidth = min(100, max(0, round(($itQty / $itReorder) * 100)));
                                    $itBarColor = ($itQty <= ($itReorder * 0.5)) ? '#EF4444' : '#F59E0B';
                                } else {
                                    $itBarWidth = 100;
                                    $itBarColor = '#10B981';
                                }
                            ?>
                            <tr class="hover:bg-[#1D387B]/40 dark:hover:bg-[#1D387B]/40 transition-colors">
                                <td>
                                    <span class="product-sku-text font-mono text-xs"><?php echo htmlspecialchars($it['sku']); ?></span>
                                </td>
                                <td class="rr-col-name">
                                    <div class="product-title-text font-semibold text-sm leading-snug"><?php echo htmlspecialchars($it['name']); ?></div>
                                </td>
                                <td>
                                    <span class="table-cat-badge text-xs px-2.5 py-1 rounded-md bg-[#13275B]/60 border border-[#2A4484]/40 text-[#9aa6d1] whitespace-nowrap">
                                        <?php echo htmlspecialchars($it['category'] ?: 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td><span class="text-xs"><?php echo htmlspecialchars($it['supplier'] ?: 'No supplier'); ?></span></td>
                                <td><span class="text-xs"><?php echo htmlspecialchars($it['location'] ?: '—'); ?></span></td>
                                <td>
                                    <div class="flex items-baseline gap-1 font-mono font-bold text-sm">
                                        <span class="stock-num"><?php echo number_format($itQty); ?></span>
                                        <span class="stock-unit text-xs font-normal font-sans"><?php echo htmlspecialchars($it['uom'] ?: 'piece'); ?></span>
                                    </div>
                                    <div class="rr-stock-bar-track" style="width:56px; height:4px;">
                                        <div class="rr-stock-bar" style="width: <?php echo $itBarWidth; ?>%; background-color: <?php echo $itBarColor; ?>;"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-xs font-medium"><?php echo $itBatchText; ?></div>
                                    <div class="text-[11px] opacity-75 font-onest"><?php echo $itExpText; ?></div>
                                </td>
                                <td><span class="font-mono text-xs">&#8369;<?php echo $itPrice; ?></span></td>
                                <td><span class="text-xs"><?php echo htmlspecialchars($it['uom'] ?: 'piece'); ?></span></td>
                                <td style="text-align:right;"><span class="font-mono text-xs"><?php echo number_format($itReorder); ?></span></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?php echo $badgeCls; ?>"><?php echo $badgeTxt; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="pagination-container p-3 sm:p-4 border-t border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-3 sm:gap-4 flex-wrap no-print" id="reportTablePagination"></div>
        </div>

        <!-- ══════════════════════════════════════════════════════════
             REORDER REPORT VIEW (INSIDE PREMIUM-CARD)
        ══════════════════════════════════════════════════════════ -->
        <div id="reportReorderView" class="<?php echo $rrViewActive ? '' : 'hidden'; ?>">

            <!-- Filter Bar Form (Single Row Grid) -->
            <form id="rrFilterForm" method="GET" action="reports.php" onsubmit="generateReorderReport(event); return false;" class="p-5 border-b border-[#2A4484]/50 bg-[#13275B]/30 no-print">
                <input type="hidden" name="view" value="reorder">
                <input type="hidden" name="rr_submitted" value="1">
                <input type="hidden" name="rr_critical" id="input_rr_critical" value="<?php echo $rrCritical; ?>">
                <input type="hidden" name="rr_reorder" id="input_rr_reorder" value="<?php echo $rrReorder; ?>">

                <div class="rr-filter-grid">
                    <!-- 1. Stock Status Toggles -->
                    <div class="rr-filter-col">
                        <label class="rr-filter-label">Stock status</label>
                        <div class="rr-filter-control h-[42px] flex items-center gap-2">
                            <button type="button" id="chipCritical" class="reorder-chip critical <?php echo $rrCritical ? 'active' : ''; ?>" onclick="toggleReorderChip('critical')">
                                <svg class="chip-check w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>CRITICAL</span>
                            </button>
                            <button type="button" id="chipReorder" class="reorder-chip reorder <?php echo $rrReorder ? 'active' : ''; ?>" onclick="toggleReorderChip('reorder')">
                                <svg class="chip-check w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>REORDER</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Supplier Dropdown -->
                    <div class="rr-filter-col">
                        <label class="rr-filter-label" for="rr_supplier">Supplier</label>
                        <div class="rr-filter-control">
                            <select id="rr_supplier" name="rr_supplier" class="premium-input inventory-select h-[42px] w-full text-xs cursor-pointer" onchange="applyReorderFiltersClient()">
                                <option value="">All suppliers</option>
                                <?php foreach ($allSuppliers as $sup): ?>
                                    <option value="<?php echo htmlspecialchars($sup); ?>" <?php echo $rrSupplier === $sup ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sup); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="No supplier" <?php echo $rrSupplier === 'No supplier' ? 'selected' : ''; ?>>No supplier</option>
                            </select>
                        </div>
                    </div>

                    <!-- 3. Category Dropdown -->
                    <div class="rr-filter-col">
                        <label class="rr-filter-label" for="rr_category">Category</label>
                        <div class="rr-filter-control">
                            <select id="rr_category" name="rr_category" class="premium-input inventory-select h-[42px] w-full text-xs cursor-pointer" onchange="applyReorderFiltersClient()">
                                <option value="">All Categories</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $rrCategory === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Sort within Supplier Dropdown -->
                    <div class="rr-filter-col">
                        <label class="rr-filter-label" for="rr_sort">Sort within supplier</label>
                        <div class="rr-filter-control">
                            <select id="rr_sort" name="rr_sort" class="premium-input inventory-select h-[42px] w-full text-xs cursor-pointer" onchange="applyReorderFiltersClient()">
                                <option value="lowest_stock" <?php echo $rrSort === 'lowest_stock' ? 'selected' : ''; ?>>Lowest stock first</option>
                                <option value="name_asc" <?php echo $rrSort === 'name_asc' ? 'selected' : ''; ?>>Name A–Z</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. Generate Report Button (Auto Width) -->
                    <div class="rr-filter-btn-col">
                        <button type="button" id="btnGenerateReorderReport" onclick="generateReorderReport(event)" class="premium-button rr-btn-generate h-[42px] px-5 text-xs font-semibold rounded-xl inline-flex items-center justify-center gap-2 whitespace-nowrap transition-all duration-200">
                            <svg id="btnGenerateIcon" class="w-3.5 h-3.5 shrink-0 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span id="btnGenerateText">Generate report</span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Results Header Strip -->
            <div class="rr-results-header flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-3.5 border-b border-[#2A4484]/40 bg-[#13275B]/20">
                <div id="rrResultsMeta" class="text-xs sm:text-sm text-[#9aa6d1] font-onest flex items-center flex-wrap gap-1 transition-all duration-300">
                    <strong id="rrMetaCount" class="font-bold text-white dark:text-white"><?php echo $rrTotalItemsToRestock; ?> items across <?php echo $rrSuppliersAffectedCount; ?> suppliers</strong>
                    <span id="rrMetaTime">&nbsp;&middot;&nbsp;Generated <?php echo date('M j, Y, g:i A'); ?> &middot; grouped by supplier</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="printReorderReport()" class="rr-hdr-btn" title="Print Reorder Report">
                        <span>Print</span>
                    </button>
                    <button type="button" onclick="exportReorderCSV()" class="rr-hdr-btn" title="Export CSV">
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>

            <!-- Table Container (Universal .premium-table) -->
            <div class="p-4 sm:p-6">
                <div class="iw-table-scroll" id="rrTableScroll" style="overflow-x:auto;-webkit-overflow-scrolling:touch; width:100% !important; max-width:100% !important; display:block !important;">
                    <table class="premium-table w-full text-left" id="rrMainTable" style="min-width:1050px;">
                        <?php renderUniversalReportTableHeader(); ?>
                        <tbody id="rrTableBody">
                        <?php
                        foreach ($reorderBySupplier as $supName => $supGroup):
                            $supCrit  = $supGroup['critical'];
                            $supLow   = $supGroup['low'];
                            $supTotal = count($supGroup['items']);
                            $supDataAttrs = 'data-supplier="' . htmlspecialchars($supName, ENT_QUOTES) . '"';
                        ?>
                            <!-- Supplier Group Header -->
                            <tr class="rr-group-header" <?php echo $supDataAttrs; ?>>
                                <td colspan="11">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><path d="M9 22v-4h6v4"></path><path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M12 6h.01"></path><path d="M12 10h.01"></path><path d="M12 14h.01"></path><path d="M16 10h.01"></path><path d="M16 14h.01"></path><path d="M8 10h.01"></path><path d="M8 14h.01"></path></svg>
                                            <span class="rr-supplier-name"><?php echo htmlspecialchars($supName); ?></span>
                                        </div>
                                        <div class="rr-supplier-meta flex items-center gap-2 font-onest">
                                            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium"><?php echo $supTotal; ?> item<?php echo $supTotal !== 1 ? 's' : ''; ?></span>
                                            <?php if ($supCrit > 0): ?>
                                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"><?php echo $supCrit; ?> critical</span>
                                            <?php endif; ?>
                                            <?php if ($supLow > 0): ?>
                                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"><?php echo $supLow; ?> reorder</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php foreach ($supGroup['items'] as $ri):
                                $rQty   = (int)$ri['qty'];
                                $rRp    = (int)($ri['reorder_point'] ?? 0);
                                $rCrit  = ($ri['reorder_status'] === 'critical');
                                $badgeCls = $rCrit ? 'out' : 'low';
                                $badgeTxt = $rCrit ? 'CRITICAL' : 'REORDER';
                                $batchCount = (int)($ri['batch_count'] ?? 0);
                                $nearestExp = $ri['nearest_expiry'] ?? null;
                                $batchText  = $batchCount > 0 ? $batchCount . ' batch' . ($batchCount > 1 ? 'es' : '') : 'No batches';
                                $expText    = $nearestExp ? 'Exp: ' . date('M j, Y', strtotime($nearestExp)) : 'No Expiry';
                                $price      = number_format((float)($ri['price'] ?? 0), 2);
                                
                                if ($rQty <= 0) {
                                    $stockBarWidth = 0;
                                } else if ($rRp > 0) {
                                    $stockBarWidth = min(100, max(0, round(($rQty / $rRp) * 100)));
                                } else {
                                    $stockBarWidth = 0;
                                }
                                $barColor = $rCrit ? '#EF4444' : '#F59E0B';

                                $dataAttrs  = 'data-supplier="' . htmlspecialchars($ri['supplier'], ENT_QUOTES) . '"'
                                            . ' data-category="' . htmlspecialchars($ri['category'] ?? '', ENT_QUOTES) . '"'
                                            . ' data-status="' . ($rCrit ? 'critical' : 'reorder') . '"'
                                            . ' data-qty="' . $rQty . '"'
                                            . ' data-rp="' . $rRp . '"'
                                            . ' data-name="' . htmlspecialchars($ri['name'], ENT_QUOTES) . '"';
                            ?>
                            <tr class="rr-item-row" <?php echo $dataAttrs; ?>>
                                <td>
                                    <span class="product-sku-text font-mono text-xs"><?php echo htmlspecialchars($ri['sku']); ?></span>
                                </td>
                                <td class="rr-col-name">
                                    <div class="product-title-text font-semibold text-sm leading-snug"><?php echo htmlspecialchars($ri['name']); ?></div>
                                </td>
                                <td>
                                    <span class="table-cat-badge text-xs px-2.5 py-1 rounded-md bg-[#13275B]/60 border border-[#2A4484]/40 text-[#9aa6d1] whitespace-nowrap">
                                        <?php echo htmlspecialchars($ri['category'] ?: 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td><span class="text-xs"><?php echo htmlspecialchars($ri['supplier']); ?></span></td>
                                <td><span class="text-xs"><?php echo htmlspecialchars($ri['location'] ?: '—'); ?></span></td>
                                <td>
                                    <div class="flex items-baseline gap-1 font-mono font-bold text-sm">
                                        <span class="stock-num"><?php echo number_format($rQty); ?></span>
                                        <span class="stock-unit text-xs font-normal font-sans"><?php echo htmlspecialchars($ri['uom'] ?: 'piece'); ?></span>
                                    </div>
                                    <div class="rr-stock-bar-track" style="width:56px; height:4px;">
                                        <div class="rr-stock-bar" style="width: <?php echo $stockBarWidth; ?>%; background-color: <?php echo $barColor; ?>;"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-xs font-medium"><?php echo $batchText; ?></div>
                                    <div class="text-[11px] opacity-75 font-onest"><?php echo $expText; ?></div>
                                </td>
                                <td><span class="font-mono text-xs">&#8369;<?php echo $price; ?></span></td>
                                <td><span class="text-xs"><?php echo htmlspecialchars($ri['uom'] ?: 'piece'); ?></span></td>
                                <td style="text-align:right;"><span class="font-mono text-xs"><?php echo number_format($rRp); ?></span></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?php echo $badgeCls; ?>"><?php echo $badgeTxt; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div id="reorderEmptyState" class="flex-col items-center justify-center py-12 text-center <?php echo empty($reorderItems) ? 'flex' : 'hidden'; ?>">
                        <div class="w-12 h-12 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mx-auto mb-3 text-emerald-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="font-semibold text-base text-white mb-1">Nothing to restock</div>
                        <div class="text-xs text-[#9aa6d1]">All items have adequate inventory or no products match the current filters.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="premium-card">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                <span>Inventory Distribution</span>
            </h3>
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
  const reorderItems = <?php echo json_encode($reorderItems); ?>;
  
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

  // Detailed Audit Table Pagination Logic (15 entries per page)
  let auditPage = 1;
  const auditPerPage = 15;

  function renderAuditPagination(page) {
    const tableBody = document.getElementById('reportTableBody');
    const pagContainer = document.getElementById('reportTablePagination');
    if (!tableBody || !pagContainer) return;

    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    if (allRows.length === 0) {
      pagContainer.innerHTML = '';
      pagContainer.style.display = 'none';
      return;
    }

    const totalPages = Math.ceil(allRows.length / auditPerPage) || 1;
    if (page > totalPages) page = totalPages;
    if (page < 1) page = 1;
    auditPage = page;

    const start = (page - 1) * auditPerPage;
    const end = Math.min(start + auditPerPage, allRows.length);

    allRows.forEach((row, index) => {
      row.style.display = (index >= start && index < end) ? '' : 'none';
    });

    pagContainer.style.display = 'flex';

    let pageItems = [];
    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) pageItems.push(i);
    } else if (auditPage <= 3) {
      pageItems = [1, 2, 3, 4, 5, '...', totalPages];
    } else if (auditPage >= totalPages - 2) {
      pageItems = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
    } else {
      pageItems = [1, '...', auditPage - 1, auditPage, auditPage + 1, '...', totalPages];
    }

    let pageNumbersHtml = '';
    pageItems.forEach(item => {
      if (item === '...') {
        pageNumbersHtml += `<span class="pagination-ellipsis">&hellip;</span>`;
      } else if (item === auditPage) {
        pageNumbersHtml += `<button type="button" class="pagination-btn pagination-num active" disabled>${item}</button>`;
      } else {
        let isNeighbor = false;
        if (totalPages > 7) {
          if (auditPage <= 3) {
            isNeighbor = (item === 4 || item === 5);
          } else if (auditPage >= totalPages - 2) {
            isNeighbor = (item === totalPages - 4 || item === totalPages - 3);
          } else {
            isNeighbor = (item === auditPage - 1 || item === auditPage + 1);
          }
        }
        const neighborClass = isNeighbor ? ' pagination-neighbor' : '';
        pageNumbersHtml += `<button type="button" class="pagination-btn pagination-num${neighborClass}" onclick="changeAuditPage(${item})">${item}</button>`;
      }
    });

    pagContainer.innerHTML = `
      <div class="pagination-info-pill">Showing ${allRows.length === 0 ? 0 : start + 1} to ${end} of ${allRows.length} entries</div>
      <div class="pagination-btn-group flex items-center gap-1 sm:gap-1.5">
        <button type="button" class="pagination-btn pagination-nav" ${auditPage === 1 ? 'disabled' : ''} onclick="changeAuditPage(${auditPage - 1})" aria-label="Previous page" title="Previous page">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
          <span class="pagination-nav-text">Previous</span>
        </button>
        ${pageNumbersHtml}
        <button type="button" class="pagination-btn pagination-nav" ${auditPage === totalPages ? 'disabled' : ''} onclick="changeAuditPage(${auditPage + 1})" aria-label="Next page" title="Next page">
          <span class="pagination-nav-text">Next</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
      </div>
    `;
  }

  function changeAuditPage(newPage) {
    renderAuditPagination(newPage);
    const tableWrap = document.querySelector('#reportTableView .iw-table-scroll');
    if (tableWrap) {
      tableWrap.scrollTop = 0;
    }
  }

  // View Mode Switching (Explorer / Audit Table / Reorder Report)
  function switchReportView(mode) {
    const explorerView   = document.getElementById('reportExplorerView');
    const tableView      = document.getElementById('reportTableView');
    const reorderView    = document.getElementById('reportReorderView');
    const tabExplorerBtn = document.getElementById('tabExplorerBtn');
    const tabTableBtn    = document.getElementById('tabTableBtn');
    const tabReorderBtn  = document.getElementById('tabReorderBtn');
    const defCards       = document.getElementById('defaultSummaryCards');
    const rrCards        = document.getElementById('reorderSummaryCards');
    const topFilterCard  = document.getElementById('topGenerateReportCard');
    const mainTitle      = document.getElementById('stockExplorerTitle');
    const subTitle       = document.getElementById('stockExplorerSubtitle');

    // Hide all, deactivate all tabs
    [explorerView, tableView, reorderView].forEach(v => v && v.classList.add('hidden'));
    [tabExplorerBtn, tabTableBtn, tabReorderBtn].forEach(b => b && b.classList.remove('active'));

    if (mode === 'table') {
      tableView && tableView.classList.remove('hidden');
      tabTableBtn && tabTableBtn.classList.add('active');
      defCards && defCards.classList.remove('hidden');
      rrCards && rrCards.classList.add('hidden');
      topFilterCard && topFilterCard.classList.remove('hidden');
      if (mainTitle) mainTitle.textContent = 'Detailed Audit Table';
      if (subTitle) subTitle.textContent = 'Showing ' + items.length + ' items based on current filters.';
      renderAuditPagination(auditPage);
    } else if (mode === 'reorder') {
      reorderView && reorderView.classList.remove('hidden');
      tabReorderBtn && tabReorderBtn.classList.add('active');
      defCards && defCards.classList.add('hidden');
      rrCards && rrCards.classList.remove('hidden');
      topFilterCard && topFilterCard.classList.add('hidden');
      if (mainTitle) mainTitle.textContent = 'Reorder Report';
      if (subTitle) subTitle.textContent = 'Generate a restock report from current stock levels.';
      applyReorderFiltersClient();
    } else {
      explorerView && explorerView.classList.remove('hidden');
      tabExplorerBtn && tabExplorerBtn.classList.add('active');
      defCards && defCards.classList.remove('hidden');
      rrCards && rrCards.classList.add('hidden');
      topFilterCard && topFilterCard.classList.remove('hidden');
      if (mainTitle) mainTitle.textContent = 'Stock Explorer';
      if (subTitle) subTitle.textContent = 'Showing ' + items.length + ' items based on current filters.';
    }
  }

  // ── Reorder Report Client-Side Filter & Live Update ──────────────
  const reorderChipState = {
    critical: <?php echo $rrCritical ? 'true' : 'false'; ?>,
    reorder: <?php echo $rrReorder ? 'true' : 'false'; ?>
  };

  function toggleReorderChip(type) {
    reorderChipState[type] = !reorderChipState[type];
    const chip = document.getElementById(type === 'critical' ? 'chipCritical' : 'chipReorder');
    if (chip) chip.classList.toggle('active', reorderChipState[type]);
    const input = document.getElementById(type === 'critical' ? 'input_rr_critical' : 'input_rr_reorder');
    if (input) input.value = reorderChipState[type] ? '1' : '0';
    applyReorderFiltersClient();
  }

  function applyReorderFiltersClient() {
    const supplierVal = (document.getElementById('rr_supplier') || {}).value || '';
    const categoryVal = (document.getElementById('rr_category') || {}).value || '';
    const sortVal     = (document.getElementById('rr_sort')     || {}).value || 'lowest_stock';

    const tbody   = document.getElementById('rrTableBody');
    const empty   = document.getElementById('reorderEmptyState');
    if (!tbody) return { totalVisible: 0, totalSuppliers: 0, critVisible: 0, reorderVisible: 0 };

    const allGroupHeaders = Array.from(tbody.querySelectorAll('tr.rr-group-header'));
    const allItemRows     = Array.from(tbody.querySelectorAll('tr.rr-item-row'));

    // Hide all rows initially
    allGroupHeaders.forEach(h => h.style.display = 'none');
    allItemRows.forEach(r => r.style.display = 'none');

    const visibleBySupplier = {};
    let totalCritVisible = 0;
    let totalReorderVisible = 0;

    allItemRows.forEach(row => {
      const rowStatus   = row.dataset.status;
      const rowSupplier = row.dataset.supplier || 'No supplier';
      const rowCategory = row.dataset.category || '';
      const rowQty      = parseInt(row.dataset.qty) || 0;
      const rowName     = row.dataset.name || '';

      if (rowStatus === 'critical' && !reorderChipState.critical) return;
      if (rowStatus === 'reorder'  && !reorderChipState.reorder)  return;
      if (supplierVal && rowSupplier !== supplierVal) return;
      if (categoryVal && rowCategory !== categoryVal) return;

      if (!visibleBySupplier[rowSupplier]) visibleBySupplier[rowSupplier] = [];
      visibleBySupplier[rowSupplier].push({ row, qty: rowQty, name: rowName, status: rowStatus });

      if (rowStatus === 'critical') totalCritVisible++;
      else totalReorderVisible++;
    });

    let totalVisible = 0;
    const sortedSuppliers = Object.keys(visibleBySupplier).sort();
    sortedSuppliers.forEach(sup => {
      const group = visibleBySupplier[sup];
      group.sort((a, b) => {
        if (sortVal === 'name_asc') return a.name.localeCompare(b.name);
        return a.qty - b.qty;
      });

      let supCrit = 0;
      let supLow  = 0;
      group.forEach(g => {
        g.row.style.display = '';
        tbody.appendChild(g.row);
        if (g.status === 'critical') supCrit++;
        else supLow++;
      });
      totalVisible += group.length;

      const header = allGroupHeaders.find(h => h.dataset.supplier === sup);
      if (header) {
        tbody.insertBefore(header, group[0].row);
        header.style.display = '';
        const metaSpan = header.querySelector('.rr-supplier-meta');
        if (metaSpan) {
          let pillsHtml = `<span class="text-xs text-slate-500 dark:text-slate-400 font-medium">${group.length} item${group.length !== 1 ? 's' : ''}</span>`;
          if (supCrit > 0) {
            pillsHtml += `<span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">${supCrit} critical</span>`;
          }
          if (supLow > 0) {
            pillsHtml += `<span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">${supLow} reorder</span>`;
          }
          metaSpan.innerHTML = pillsHtml;
        }
      }
    });

    // Update 4 summary cards
    const cardRestock   = document.getElementById('cardValRestock');
    const cardCrit      = document.getElementById('cardValCritical');
    const cardReorder   = document.getElementById('cardValReorder');
    const cardSuppliers = document.getElementById('cardValSuppliers');
    if (cardRestock)   cardRestock.textContent   = totalVisible;
    if (cardCrit)      cardCrit.textContent      = totalCritVisible;
    if (cardReorder)   cardReorder.textContent   = totalReorderVisible;
    if (cardSuppliers) cardSuppliers.textContent = sortedSuppliers.length;

    // Update results meta header
    const metaCount = document.getElementById('rrMetaCount');
    if (metaCount) {
      metaCount.textContent = `${totalVisible} item${totalVisible !== 1 ? 's' : ''} across ${sortedSuppliers.length} supplier${sortedSuppliers.length !== 1 ? 's' : ''}`;
    }

    // Empty state
    if (empty) {
      if (totalVisible === 0) {
        empty.classList.remove('hidden');
        empty.classList.add('flex');
      } else {
        empty.classList.add('hidden');
        empty.classList.remove('flex');
      }
    }

    return {
      totalVisible,
      totalSuppliers: sortedSuppliers.length,
      critVisible: totalCritVisible,
      reorderVisible: totalReorderVisible
    };
  }

  // ── Generate Reorder Report ───────────────────────────────────────
  function generateReorderReport(e) {
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
    }

    const btn = document.getElementById('btnGenerateReorderReport');
    const btnIcon = document.getElementById('btnGenerateIcon');
    const btnText = document.getElementById('btnGenerateText');
    const metaTime = document.getElementById('rrMetaTime');
    const metaWrap = document.getElementById('rrResultsMeta');
    const resultsHeader = document.querySelector('.rr-results-header');

    // Button loading feedback
    if (btn) {
      btn.disabled = true;
      btn.classList.add('opacity-80');
    }
    if (btnIcon) {
      btnIcon.classList.add('animate-spin');
    }
    if (btnText) {
      btnText.textContent = 'Generating...';
    }

    // Apply client filters and get visible counts
    const res = applyReorderFiltersClient();

    // Format current timestamp (e.g. "Oct 27, 2026, 6:30 PM")
    const now = new Date();
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const month = months[now.getMonth()];
    const day = now.getDate();
    const year = now.getFullYear();
    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const timeFormatted = `${month} ${day}, ${year}, ${hours}:${minutes} ${ampm}`;

    if (metaTime) {
      metaTime.innerHTML = `&nbsp;&middot;&nbsp;Generated ${timeFormatted} &middot; grouped by supplier`;
    }

    // Gentle visual feedback pulse on results metadata
    if (metaWrap) {
      metaWrap.classList.add('ring-2', 'ring-blue-500/50', 'rounded-lg', 'px-2', 'py-1', 'bg-blue-500/10');
      setTimeout(() => {
        metaWrap.classList.remove('ring-2', 'ring-blue-500/50', 'rounded-lg', 'px-2', 'py-1', 'bg-blue-500/10');
      }, 1200);
    }

    // Sync browser URL parameters without reloading
    try {
      const supplierVal = (document.getElementById('rr_supplier') || {}).value || '';
      const categoryVal = (document.getElementById('rr_category') || {}).value || '';
      const sortVal     = (document.getElementById('rr_sort')     || {}).value || 'lowest_stock';

      const url = new URL(window.location.href);
      url.searchParams.set('view', 'reorder');
      url.searchParams.set('rr_submitted', '1');
      url.searchParams.set('rr_critical', reorderChipState.critical ? '1' : '0');
      url.searchParams.set('rr_reorder', reorderChipState.reorder ? '1' : '0');
      if (supplierVal) url.searchParams.set('rr_supplier', supplierVal);
      else url.searchParams.delete('rr_supplier');
      if (categoryVal) url.searchParams.set('rr_category', categoryVal);
      else url.searchParams.delete('rr_category');
      if (sortVal) url.searchParams.set('rr_sort', sortVal);
      else url.searchParams.delete('rr_sort');

      window.history.replaceState({ path: url.toString() }, '', url.toString());
    } catch (err) {
      // URL sync fallback
    }

    // Smooth scroll into results if needed
    if (resultsHeader) {
      const rect = resultsHeader.getBoundingClientRect();
      if (rect.top < 0 || rect.top > window.innerHeight) {
        resultsHeader.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    // Restore button after brief delay and show toast notification
    setTimeout(() => {
      if (btn) {
        btn.disabled = false;
        btn.classList.remove('opacity-80');
      }
      if (btnIcon) {
        btnIcon.classList.remove('animate-spin');
      }
      if (btnText) {
        btnText.textContent = 'Generate report';
      }

      const totalItems = res ? res.totalVisible : 0;
      const totalSups  = res ? res.totalSuppliers : 0;
      const msg = `Reorder report generated: ${totalItems} item${totalItems !== 1 ? 's' : ''} across ${totalSups} supplier${totalSups !== 1 ? 's' : ''}`;
      if (typeof showToast === 'function') {
        showToast(msg, 'success');
      }
    }, 250);
  }

  // ── Print Reorder Report (Reuses Universal Document #printableReportContainer) ──
  function printReorderReport() {
    function esc(s) {
      if (!s) return '';
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    const elTitle    = document.getElementById('printDocTitle');
    const elBadge    = document.getElementById('printDocBadge');
    const elRef      = document.getElementById('printDocRef');
    const elClass    = document.getElementById('printDocClass');
    const elPrepHead = document.getElementById('printPrepHead');
    const elScope1   = document.getElementById('printScopeLine1');
    const elScope2   = document.getElementById('printScopeLine2');
    const elReconHead= document.getElementById('printReconHead');
    const elRecon1   = document.getElementById('printReconLine1');
    const elRecon2   = document.getElementById('printReconLine2');
    const elKpi      = document.getElementById('printKpiTiles');
    const elThead    = document.getElementById('printableTableHead');
    const elTbody    = document.getElementById('printableTableBody');
    const elPrepBy   = document.getElementById('printPrepByTitle');
    const elApprBy   = document.getElementById('printApprByRole');
    const elFooter   = document.getElementById('printFooterDocText');

    // Save general audit snapshot to restore after printing
    const origSnapshot = {
      title: elTitle?.innerHTML,
      badge: elBadge?.outerHTML,
      ref: elRef?.innerHTML,
      docClass: elClass?.innerHTML,
      prepHead: elPrepHead?.innerHTML,
      scope1: elScope1?.innerHTML,
      scope2: elScope2?.innerHTML,
      reconHead: elReconHead?.innerHTML,
      recon1: elRecon1?.innerHTML,
      recon2: elRecon2?.innerHTML,
      kpi: elKpi?.innerHTML,
      kpiGrid: elKpi?.style.gridTemplateColumns,
      thead: elThead?.innerHTML,
      tbody: elTbody?.innerHTML,
      prepBy: elPrepBy?.innerHTML,
      apprBy: elApprBy?.innerHTML,
      footer: elFooter?.innerHTML
    };

    // Gather visible items from active client filters
    const tbody = document.getElementById('rrTableBody');
    const allItemRows = tbody ? Array.from(tbody.querySelectorAll('tr.rr-item-row')).filter(r => r.style.display !== 'none') : [];
    const grouped = {};
    let totalCrit = 0;
    let totalLow = 0;
    allItemRows.forEach(row => {
      const sup = row.dataset.supplier || 'No supplier';
      if (!grouped[sup]) grouped[sup] = [];
      grouped[sup].push(row);
      if (row.dataset.status === 'critical') totalCrit++;
      else totalLow++;
    });

    const supplierVal = (document.getElementById('rr_supplier') || {}).value || 'All Suppliers';
    const categoryVal = (document.getElementById('rr_category') || {}).value || 'All Categories';
    const statusText = (reorderChipState.critical && reorderChipState.reorder)
      ? 'Critical & Reorder'
      : (reorderChipState.critical ? 'Critical Only' : (reorderChipState.reorder ? 'Reorder Only' : 'None'));

    const now = new Date();
    const refCode = 'IW-RO-' + now.toISOString().slice(0,10).replace(/-/g,'') + '-' + Math.floor(1000 + Math.random() * 9000);

    // 1. Header Branding & Badge
    if (elTitle) elTitle.textContent = 'OFFICIAL REORDER & RESTOCK ADVISORY REPORT';
    if (elBadge) {
      elBadge.textContent = 'Restock Advisory';
      elBadge.style.background = '#fef2f2';
      elBadge.style.borderColor = '#fecaca';
      elBadge.style.color = '#dc2626';
    }
    if (elRef) elRef.textContent = refCode;
    if (elClass) elClass.textContent = 'Classification: Warehouse Stock Replenishment';

    // 2. Parameters & Reconciliation Box
    if (elPrepHead) elPrepHead.textContent = 'Advisory Generated By';
    if (elScope1) elScope1.innerHTML = `<strong>Supplier:</strong> ${esc(supplierVal)}`;
    if (elScope2) elScope2.innerHTML = `<strong>Category:</strong> ${esc(categoryVal)} &nbsp;|&nbsp; <strong>Status:</strong> ${esc(statusText)}`;
    if (elReconHead) elReconHead.textContent = 'Restock Reconciliation';
    if (elRecon1) elRecon1.innerHTML = `<strong>Items to Restock:</strong> ${allItemRows.length.toLocaleString()} items`;
    if (elRecon2) elRecon2.innerHTML = `Critical: ${totalCrit} | Reorder: ${totalLow} | Suppliers: ${Object.keys(grouped).length}`;

    // 3. 4 KPI Tiles
    if (elKpi) {
      elKpi.style.gridTemplateColumns = 'repeat(4, 1fr)';
      elKpi.innerHTML = `
        <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; text-align: center;">
            <div style="font-size: 8px; font-weight: bold; color: #475569; text-transform: uppercase;">Items to Restock</div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px;">${allItemRows.length.toLocaleString()}</div>
            <div style="font-size: 7.5px; color: #64748b;">Replenishment Required</div>
        </div>
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 8px; text-align: center;">
            <div style="font-size: 8px; font-weight: bold; color: #dc2626; text-transform: uppercase;">Critical Stock</div>
            <div style="font-size: 14px; font-weight: 800; color: #dc2626; margin-top: 2px;">${totalCrit.toLocaleString()}</div>
            <div style="font-size: 7.5px; color: #b91c1c;">Stock &le; 50% Reorder Pt</div>
        </div>
        <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; padding: 8px; text-align: center;">
            <div style="font-size: 8px; font-weight: bold; color: #b45309; text-transform: uppercase;">Reorder Stock</div>
            <div style="font-size: 14px; font-weight: 800; color: #d97706; margin-top: 2px;">${totalLow.toLocaleString()}</div>
            <div style="font-size: 7.5px; color: #b45309;">Stock &le; Reorder Threshold</div>
        </div>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px; text-align: center;">
            <div style="font-size: 8px; font-weight: bold; color: #16a34a; text-transform: uppercase;">Suppliers Affected</div>
            <div style="font-size: 14px; font-weight: 800; color: #16a34a; margin-top: 2px;">${Object.keys(grouped).length.toLocaleString()}</div>
            <div style="font-size: 7.5px; color: #15803d;">Vendors to Contact</div>
        </div>
      `;
    }

    // 4. Table Headers & Rows
    if (elThead) {
      elThead.innerHTML = `
        <tr>
            <th style="width: 10%;">SKU</th>
            <th style="width: 25%;">Product Name</th>
            <th style="width: 13%;">Category</th>
            <th style="width: 15%;">Supplier</th>
            <th style="width: 9%; text-align: right;">Total Stock</th>
            <th style="width: 6%; text-align: center;">Unit</th>
            <th style="width: 13%;">Batches / Expiry</th>
            <th style="width: 9%; text-align: right;">Reorder</th>
            <th style="width: 10%; text-align: center;">Status</th>
        </tr>
      `;
    }
    if (elTbody) {
      let tbodyHtml = '';
      const sortedSups = Object.keys(grouped).sort();
      sortedSups.forEach(sup => {
        const rows = grouped[sup];
        const sCrit = rows.filter(r => r.dataset.status === 'critical').length;
        const sLow  = rows.length - sCrit;
        tbodyHtml += `
          <tr style="background: #f0f7ff; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
            <td colspan="9" style="font-weight: 700; background: #f0f7ff; color: #1e40af; border: 1px solid #cbd5e1; padding: 6px 8px;">
              ${esc(sup)} &bull; ${rows.length} item${rows.length !== 1 ? 's' : ''} (${sCrit} critical, ${sLow} reorder)
            </td>
          </tr>
        `;
        rows.forEach(r => {
          const cells = Array.from(r.querySelectorAll('td'));
          const sku    = cells[0]?.textContent.trim() || '';
          const name   = cells[1]?.querySelector('.product-title-text')?.textContent.trim() || cells[1]?.textContent.trim() || '';
          const cat    = cells[2]?.querySelector('.table-cat-badge')?.textContent.trim() || cells[2]?.textContent.trim() || '';
          const supName= cells[3]?.textContent.trim() || '';
          const qty    = cells[5]?.querySelector('.stock-num')?.textContent.trim() || cells[5]?.querySelector('.font-mono')?.textContent.trim() || '';
          const unit   = cells[8]?.textContent.trim() || 'piece';
          const batchExp = cells[6]?.textContent.trim().replace(/\\s+/g, ' ') || '';
          const rp     = cells[9]?.textContent.trim() || '';
          const isCrit = r.dataset.status === 'critical';
          const badgeClass = isCrit ? 'out' : 'low';
          const badgeLabel = isCrit ? 'CRITICAL' : 'REORDER';

          tbodyHtml += `
            <tr>
              <td style="font-family: monospace; font-weight: bold;">${esc(sku)}</td>
              <td style="font-weight: 600;">${esc(name)}</td>
              <td>${esc(cat)}</td>
              <td>${esc(supName)}</td>
              <td style="text-align: right; font-weight: bold;">${esc(qty)}</td>
              <td style="text-align: center; color: #64748b;">${esc(unit)}</td>
              <td style="font-size: 9px;">${esc(batchExp)}</td>
              <td style="text-align: right; color: #64748b;">${esc(rp)}</td>
              <td style="text-align: center;">
                <span class="print-badge ${badgeClass}">${badgeLabel}</span>
              </td>
            </tr>
          `;
        });
      });
      if (allItemRows.length === 0) {
        tbodyHtml = `<tr><td colspan="9" style="text-align:center; padding:20px; color:#64748b;">No items match the active filters.</td></tr>`;
      }
      elTbody.innerHTML = tbodyHtml;
    }

    // 5. Sign-off & Footer
    if (elPrepBy) elPrepBy.textContent = 'Advisory Prepared By';
    if (elApprBy) elApprBy.textContent = 'Warehouse Supervisor / Procurement Control';
    if (elFooter) elFooter.textContent = 'IntelliWare IMS • Confidential Stock Replenishment Advisory Document';

    // 6. Print Document
    window.print();

    // 7. Restore original General Inventory Audit snapshot
    const restoreOriginal = () => {
      if (elTitle && origSnapshot.title !== undefined) elTitle.innerHTML = origSnapshot.title;
      if (elBadge && origSnapshot.badge !== undefined) elBadge.outerHTML = origSnapshot.badge;
      if (elRef && origSnapshot.ref !== undefined) elRef.innerHTML = origSnapshot.ref;
      if (elClass && origSnapshot.docClass !== undefined) elClass.innerHTML = origSnapshot.docClass;
      if (elPrepHead && origSnapshot.prepHead !== undefined) elPrepHead.innerHTML = origSnapshot.prepHead;
      if (elScope1 && origSnapshot.scope1 !== undefined) elScope1.innerHTML = origSnapshot.scope1;
      if (elScope2 && origSnapshot.scope2 !== undefined) elScope2.innerHTML = origSnapshot.scope2;
      if (elReconHead && origSnapshot.reconHead !== undefined) elReconHead.innerHTML = origSnapshot.reconHead;
      if (elRecon1 && origSnapshot.recon1 !== undefined) elRecon1.innerHTML = origSnapshot.recon1;
      if (elRecon2 && origSnapshot.recon2 !== undefined) elRecon2.innerHTML = origSnapshot.recon2;
      if (elKpi && origSnapshot.kpi !== undefined) {
        elKpi.innerHTML = origSnapshot.kpi;
        elKpi.style.gridTemplateColumns = origSnapshot.kpiGrid || 'repeat(5, 1fr)';
      }
      if (elThead && origSnapshot.thead !== undefined) elThead.innerHTML = origSnapshot.thead;
      if (elTbody && origSnapshot.tbody !== undefined) elTbody.innerHTML = origSnapshot.tbody;
      if (elPrepBy && origSnapshot.prepBy !== undefined) elPrepBy.innerHTML = origSnapshot.prepBy;
      if (elApprBy && origSnapshot.apprBy !== undefined) elApprBy.innerHTML = origSnapshot.apprBy;
      if (elFooter && origSnapshot.footer !== undefined) elFooter.innerHTML = origSnapshot.footer;
    };

    window.addEventListener('afterprint', restoreOriginal, { once: true });
    setTimeout(restoreOriginal, 2000);
  }

  // ── Export CSV ────────────────────────────────────────────────────
  function exportReorderCSV() {
    const rows = Array.from(document.querySelectorAll('#rrTableBody tr.rr-item-row')).filter(r => r.style.display !== 'none');
    if (rows.length === 0) {
      if (typeof showToast === 'function') showToast('No data to export', 'warning');
      else alert('No data to export');
      return;
    }
    const headers = ['SKU','Name','Category','Supplier','Location','Total Stock','Unit','Batches','Nearest Expiry','Price','Reorder Point','Status'];
    const csvRows = [headers.join(',')];
    rows.forEach(row => {
      const cells = Array.from(row.querySelectorAll('td'));
      const sku    = cells[0]?.textContent.trim()   || '';
      const name   = cells[1]?.querySelector('.product-title-text')?.textContent.trim() || cells[1]?.textContent.trim() || '';
      const cat    = cells[2]?.textContent.trim()   || '';
      const sup    = cells[3]?.textContent.trim()   || '';
      const loc    = cells[4]?.textContent.trim()   || '';
      const qty    = cells[5]?.querySelector('.stock-num')?.textContent.trim() || cells[5]?.querySelector('.font-bold')?.textContent.trim() || cells[5]?.textContent.trim() || '';
      const unit   = cells[8]?.textContent.trim()   || '';
      const batch  = cells[6]?.querySelector('div')?.textContent.trim() || '';
      const exp    = cells[6]?.querySelectorAll('div')[1]?.textContent.trim().replace('Exp: ','') || '';
      const price  = cells[7]?.textContent.trim().replace('₱','').replace(/,/g,'') || '';
      const rp     = cells[9]?.textContent.trim().replace(/,/g,'') || '';
      const status = cells[10]?.querySelector('.status-badge')?.textContent.trim() || cells[10]?.textContent.trim() || '';
      csvRows.push([sku,name,cat,sup,loc,qty,unit,batch,exp,price,rp,status].map(v => `"${v.replace(/"/g,'""')}"`).join(','));
    });
    const blob = new Blob([csvRows.join('\n')], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `IntelliWare_Reorder_Report_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    if (typeof showToast === 'function') showToast('Reorder CSV exported!', 'success');
  }

  // Initialize pagination & tab on load
  document.addEventListener('DOMContentLoaded', function() {
    renderAuditPagination(1);
    <?php if ($rrViewActive): ?>
    switchReportView('reorder');
    <?php endif; ?>
  });
  if (document.readyState !== 'loading') {
    renderAuditPagination(1);
    <?php if ($rrViewActive): ?>
    switchReportView('reorder');
    <?php endif; ?>
  }

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
                doc.text("INTELLIWARE IMS • OFFICIAL INVENTORY & AUDIT REPORT", 14, 8);

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