<?php
session_start();

// Redirect to signin if the user is not logged in
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }

// Check user role. Default to 'staff' if not set.
// Only allow 'admin', 'manager', or 'staff' to access this page.
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager', 'staff'])) { 
    header('Location: signin.php'); 
    exit(); 
}

require_once 'config/db.php';
require_once 'src/services/StockMovementService.php';

// capture 'action' from URL (e.g., ?action=in or ?action=out) to pre-select dropdown
$action = $_GET['action'] ?? '';
// Handle special "Smart Insight" referral from Insights → Check Stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smart_insight_referral'])) {
    $redirectParams = ['ref' => 'insights'];
    $category = trim($_POST['filter_category'] ?? '');
    $product = trim($_POST['filter_product_name'] ?? '');
    if ($category !== '') {
        $redirectParams['category'] = $category;
    }
    if ($product !== '') {
        $redirectParams['q'] = $product;
    }
    header('Location: transactions.php?' . http_build_query($redirectParams));
    exit();
}

// Standard search and filter inputs from GET request
$search_q = $_GET['q'] ?? '';
$filter_cat = $_GET['category'] ?? '';
$smartInsightActive = (($_GET['ref'] ?? '') === 'insights');

// Set current page, ensuring it's at least 1
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20; // Rows per page
$offset = ($page - 1) * $limit; // Calculate SQL offset

// Initialize arrays to hold data
$dropdownItems = []; 
$tableItems = [];   
$categories = []; 

// Function to store flash messages (success/error) in the session
function setFlashMessage($type, $message) {
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['smart_insight_referral']) && !isset($_POST['search_submit'])) {
    $type = $_POST['type'] ?? 'in';
    $id = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);

    try {
        StockMovementService::executeMovement($conn, $id, $qty, $type, $_SESSION['Username']);
        setFlashMessage('success', 'Transaction processed successfully!');
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }

    header('Location: transactions.php');
    exit();
}

// Fetch unique categories for the filter dropdown
$catResult = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
while ($row = $catResult->fetch_assoc()) { $categories[] = $row['category']; }

// Fetch ALL products for the Transaction Form Dropdown (Select2)
$dropQuery = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, barcode, quantity as qty, unit as uom FROM products ORDER BY product_name";
$dropRes = $conn->query($dropQuery);
while ($r = $dropRes->fetch_assoc()) { $dropdownItems[] = $r; }

$sql = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, category, quantity as qty, unit as uom, reorder_point FROM products WHERE 1=1";
$params = [];
$types = "";

// Apply Search Filter (Name or ID)
if (!empty($search_q)) {
    $sql .= " AND (product_name LIKE ? OR product_id LIKE ? OR barcode LIKE ? OR barcode = ?)";
    $searchTerm = "%$search_q%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $search_q;
    $types .= "ssss";
}

// Apply Category Filter
if (!empty($filter_cat)) {
    $sql .= " AND category = ?";
    $params[] = $filter_cat;
    $types .= "s";
}

// Count total rows based on current filters to calculate pages
$countSql = str_replace("SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, category, quantity as qty, unit as uom, reorder_point", "SELECT COUNT(*) as total", $sql);
$stmt = $conn->prepare($countSql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$totalRows = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = ceil($totalRows / $limit);

// Add Ordering and Limits for pagination
$sql .= " ORDER BY product_name LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $tableItems[] = $row; }
$stmt->close();
$conn->close();

// ---------------------------------------------------------
// 10. LAYOUT AND RESOURCES
// ---------------------------------------------------------
require_once 'src/layout.php';
renderLayoutStart('Stock Transactions', $role, $_SESSION['Username']);
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<style>
    /* Select2 — base layout */
    .select2-container { width: 100% !important; max-width: 100%; }

    /* Select2 dark theme */
    body[data-theme="dark"] .select2-container--default .select2-selection--single {
        background-color: rgba(19, 39, 91, 0.8) !important;
        border: 1px solid rgba(48, 67, 116, 0.6) !important;
        border-radius: 12px !important;
        height: 46px !important;
        display: flex !important;
        align-items: center !important;
    }
    body[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #E3E3E3 !important;
        padding-left: 16px !important;
        padding-right: 36px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        line-height: 44px !important;
    }
    body[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #9aa6d1 !important;
    }
    body[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px !important;
        right: 10px !important;
    }
    body[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #9aa6d1 transparent transparent transparent !important;
    }
    body[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #9aa6d1 !important;
        margin-right: 28px !important;
        font-size: 16px !important;
    }
    body[data-theme="dark"] .select2-dropdown {
        background-color: #1D387B !important;
        border: 1px solid rgba(48, 67, 116, 0.8) !important;
        border-radius: 12px !important;
        z-index: 100010 !important;
    }
    body[data-theme="dark"] .select2-search--dropdown { padding: 8px !important; }
    body[data-theme="dark"] .select2-search__field {
        background-color: rgba(13, 22, 56, 0.95) !important;
        color: #E3E3E3 !important;
        border: 1px solid rgba(48, 67, 116, 0.6) !important;
        border-radius: 8px !important;
        padding: 8px 12px !important;
        outline: none !important;
    }
    body[data-theme="dark"] .select2-results__option {
        color: #E3E3E3 !important;
        padding: 10px 16px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
    }
    body[data-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: linear-gradient(135deg, rgba(81, 213, 90, 0.2) 0%, rgba(75, 192, 82, 0.1) 100%) !important;
        color: #51D55A !important;
    }
    body[data-theme="dark"] .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: rgba(81, 213, 90, 0.15) !important;
    }

    /* Select2 light theme */
    body[data-theme="light"] .select2-container--default .select2-selection--single {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 12px !important;
        height: 46px !important;
        display: flex !important;
        align-items: center !important;
    }
    body[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #1e293b !important;
        padding-left: 16px !important;
        padding-right: 36px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        line-height: 44px !important;
    }
    body[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #64748b !important;
    }
    body[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px !important;
        right: 10px !important;
    }
    body[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent !important;
    }
    body[data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #64748b !important;
        margin-right: 28px !important;
        font-size: 16px !important;
    }
    body[data-theme="light"] .select2-dropdown {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 12px !important;
        z-index: 100010 !important;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12) !important;
    }
    body[data-theme="light"] .select2-search--dropdown { padding: 8px !important; }
    body[data-theme="light"] .select2-search__field {
        background-color: #f8fafc !important;
        color: #1e293b !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 8px 12px !important;
        outline: none !important;
    }
    body[data-theme="light"] .select2-results__option {
        color: #334155 !important;
        padding: 10px 16px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
    }
    body[data-theme="light"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: rgba(81, 213, 90, 0.15) !important;
        color: #15803d !important;
    }
    body[data-theme="light"] .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: rgba(81, 213, 90, 0.1) !important;
    }

    .txn-page-card { padding: 0 !important; overflow: visible !important; }
    .txn-page-card:hover { transform: none !important; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important; }

    .txn-form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        align-items: end;
    }
    @media (min-width: 1024px) {
        .txn-form-grid {
            grid-template-columns: minmax(160px, 1.2fr) minmax(220px, 2.5fr) minmax(90px, 0.8fr) minmax(120px, 1fr);
        }
    }
    .txn-field label {
        display: block;
        color: #9aa6d1;
        font-size: 12px;
        font-weight: 600;
        font-family: 'Onest', sans-serif;
        margin-bottom: 8px;
    }
    .txn-product-row {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }
    .txn-select-wrap {
        flex: 1;
        min-width: 0;
    }
    .txn-field-action .premium-button {
        width: 100%;
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-scan {
        height: 46px;
        min-width: 46px;
        padding: 0 14px;
        background: #2A4484;
        border: 1px solid #355296;
        border-radius: 12px;
        color: #fff;
        cursor: pointer;
        font-size: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .btn-scan:hover { border-color: #51D55A; color: #51D55A; }

    /* Toast Notification CSS — Unified Color-Coded */
    #toast-container { visibility: hidden; position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(20px); padding: 13px 24px; border-radius: 12px; font-family: 'Onest', sans-serif; font-size: 14px; font-weight: 500; z-index: 100005; display: flex; gap: 10px; align-items: center; opacity: 0; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); }
    #toast-container.show { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast-container.success { background-color: rgba(6, 44, 28, 0.96); border: 1.5px solid #51D55A; color: #d1fae5; box-shadow: 0 10px 30px rgba(81, 213, 90, 0.25); }
    #toast-container.error { background-color: rgba(45, 12, 18, 0.96); border: 1.5px solid #ef4444; color: #fee2e2; box-shadow: 0 10px 30px rgba(239, 68, 68, 0.25); }
    [data-theme="light"] #toast-container.error { background: #fef2f2 !important; border-color: #ef4444 !important; color: #991b1b !important; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.15) !important; }
    [data-theme="light"] #toast-container.success { background: #f0fdf4 !important; border-color: #22c55e !important; color: #166534 !important; box-shadow: 0 10px 25px rgba(34, 197, 94, 0.15) !important; }

    /* Action card grid (Receive / Release) */
    .txn-action-grid { display:grid; grid-template-columns:1fr; gap:12px; }
    @media(min-width:768px){ .txn-action-grid { grid-template-columns: 1fr 1fr; } }
    .txn-action-card { background: var(--iw-bg-card); border:1px solid var(--iw-border); padding:18px 20px; border-radius:12px; display:flex; align-items:center; justify-content:space-between; gap:12px; cursor:pointer; transition:all .18s ease; }
    .txn-action-card:hover { transform:translateY(-4px); box-shadow:0 18px 45px rgba(15, 23, 42, 0.12); }
    .txn-action-left { display:flex; gap:14px; align-items:center; }
    .txn-action-icon { width:56px; height:56px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; font-size:22px; }
    .txn-action-title { font-weight:700; color: var(--iw-text) !important; font-size:16px; }
    .txn-action-sub { font-size:13px; color: var(--iw-text-muted) !important; }
    .txn-action-arrow { color: var(--iw-text-subtle) !important; font-size:20px; }
    .txn-action-receive .txn-action-icon { background: rgba(81,213,90,0.08); border:1px solid rgba(81,213,90,0.18); color:#51D55A; }
    .txn-action-release .txn-action-icon { background: rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.18); color:#fb7185; }
    .btn-scan {
        height: 46px;
        min-width: 46px;
        padding: 0 14px;
        background: var(--iw-bg-btn-secondary);
        border: 1px solid var(--iw-border-input);
        border-radius: 12px;
        color: var(--iw-text);
        cursor: pointer;
        font-size: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .btn-scan:hover { border-color: var(--iw-accent); color: var(--iw-accent); }

    /* ── Inventory Status responsive layout ─────────────────────── */
    .txn-inv-desktop { display: block; }
    .txn-inv-mobile  { display: none;  }
    @media (max-width: 767px) {
        .txn-inv-desktop { display: none;  }
        .txn-inv-mobile  { display: block; }
    }

    /* Card grid */
    .txn-inv-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 14px;
        padding: 16px;
    }
    .txn-inv-card {
        background: linear-gradient(160deg, #1b2f6e 0%, #111e4a 100%);
        border: 1px solid rgba(42, 68, 132, 0.55);
        border-radius: 16px;
        padding: 16px 16px 0 16px;
        display: flex;
        flex-direction: column;
        transition: box-shadow 0.2s, border-color 0.2s, transform 0.2s;
    }
    .txn-inv-card:hover {
        border-color: rgba(81, 213, 90, 0.25);
        box-shadow: 0 8px 28px rgba(0,0,0,0.35);
        transform: translateY(-2px);
    }

    /* Top row: thumb · info · badge */
    .txn-ic-top  { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
    .txn-ic-thumb {
        width: 50px; height: 50px; border-radius: 11px;
        background: rgba(13,22,56,0.7);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .txn-ic-info  { flex: 1; min-width: 0; overflow: hidden; }
    .txn-ic-name  {
        font-family: 'Onest', sans-serif; font-size: 14px; font-weight: 700;
        color: #e3e3e3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3;
    }
    .txn-ic-cat   {
        font-family: 'Onest', sans-serif; font-size: 12px; color: #9aa6d1;
        margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .txn-ic-sku   {
        font-family: monospace; font-size: 11px; color: #6b7aab;
        margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .txn-ic-badge {
        flex-shrink: 0; font-family: 'Onest', sans-serif;
        font-size: 11px; font-weight: 800; letter-spacing: 0.06em;
        padding: 3px 8px; border-radius: 6px; white-space: nowrap; margin-top: 2px;
    }
    .txn-badge-out  { background: #c0392b; color: #fff; }
    .txn-badge-low  { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.35); }
    .txn-badge-good { background: rgba(52,211,153,0.12); color: #34d399; border: 1px solid rgba(52,211,153,0.3); }

    /* Divider */
    .txn-ic-divider { height: 1px; background: rgba(42,68,132,0.5); margin: 0 -16px; }

    /* Stats row */
    .txn-ic-stats { display: flex; padding: 12px 0; }
    .txn-ic-stat  { flex: 1; display: flex; flex-direction: column; gap: 4px; }
    .txn-ic-label {
        font-family: 'Onest', sans-serif; font-size: 10px; font-weight: 700;
        letter-spacing: 0.08em; color: #6b7aab; text-transform: uppercase;
    }
    .txn-ic-value {
        font-family: 'Onest', sans-serif; font-size: 20px; font-weight: 700;
        color: #e3e3e3; line-height: 1;
    }
    .txn-ic-value.val-out { color: #f87171; }
    .txn-ic-value.val-low { color: #fbbf24; }

    /* Light theme overrides */
    [data-theme="light"] .txn-inv-card       { background: var(--iw-bg-modal) !important; border-color: var(--iw-border-solid) !important; }
    [data-theme="light"] .txn-ic-thumb       { background: var(--iw-bg-panel-header) !important; }
    [data-theme="light"] .txn-ic-name        { color: var(--iw-text-header) !important; }
    [data-theme="light"] .txn-ic-cat,
    [data-theme="light"] .txn-ic-sku         { color: var(--iw-text-muted) !important; }
    [data-theme="light"] .txn-ic-label       { color: var(--iw-text-muted) !important; }
    [data-theme="light"] .txn-ic-value       { color: var(--iw-text-header) !important; }
    [data-theme="light"] .txn-ic-divider     { background: var(--iw-border) !important; }

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

<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>

<!-- Hero Page Header Banner -->
<div class="page-hero-card mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#9aa6d1] mb-1">
                <span>IntelliWare Enterprise WIMS</span>
                <span class="text-[#2A4484]">•</span>
                <span class="text-[#51D55A]">Stock Movements</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"></path></svg>
                </div>
                Stock Transactions &amp; Movement
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Execute verified stock check-in (Receive) and check-out (Release) workflows with real-time barcode verification and audit tracking.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Mode: <strong class="text-white">Live Inventory</strong>
            </span>
        </div>
    </div>
</div>


<div class="premium-card txn-page-card mb-8">
    <div class="p-6 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Create Stock Transaction
            </h3>
        </div>
        <div class="hidden md:block text-right">
            <span class="text-[10px] text-[#51D55A] bg-[#51D55A]/10 border border-[#51D55A]/20 px-2 py-1 rounded">Write Access Enabled</span>
        </div>
    </div>
    
    <div class="p-6">
        <div class="txn-action-grid">
            <a href="receive.php" class="txn-action-card txn-action-receive">
                <div class="txn-action-left">
                    <div class="txn-action-icon">+</div>
                    <div>
                        <div class="txn-action-title">Receive Inventory</div>
                        <div class="txn-action-sub">Add incoming stock</div>
                    </div>
                </div>
                <div class="txn-action-arrow">›</div>
            </a>

            <a href="release.php" class="txn-action-card txn-action-release">
                <div class="txn-action-left">
                    <div class="txn-action-icon">−</div>
                    <div>
                        <div class="txn-action-title">Release Inventory</div>
                        <div class="txn-action-sub">Record outgoing stock</div>
                    </div>
                </div>
                <div class="txn-action-arrow">›</div>
            </a>
        </div>
    </div>

    <?php if ($action): ?>
    <div class="p-6 border-t border-[#2A4484]/30">
        <form method="POST" id="txnSingleForm">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($action); ?>">
            <div class="txn-form-grid">
                <div class="txn-field">
                    <label for="productSelect">Select Product</label>
                    <div class="txn-product-row">
                        <div class="txn-select-wrap">
                            <select class="w-full" name="id" id="productSelect" required>
                                <option value="">-- Search or Select Product --</option>
                                <?php foreach($dropdownItems as $it): ?>
                                    <option value="<?php echo $it['id']; ?>" data-barcode="<?php echo htmlspecialchars($it['barcode'] ?? ''); ?>" data-qty="<?php echo (int)$it['qty']; ?>" data-uom="<?php echo htmlspecialchars($it['uom'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($it['sku'].' - '.$it['name']); ?><?php if(!empty($it['barcode'])) echo ' ['.htmlspecialchars($it['barcode']).']'; ?> (Qty: <?php echo $it['qty']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn-secondary" id="txn-scan-barcode-btn" style="width:46px; height:46px; padding:0; border-radius:12px; flex-shrink:0;" title="Scan with camera"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg></button>
                    </div>
                </div>

                <div class="txn-field">
                    <label for="txn-qty-input">Quantity</label>
                    <input type="number" id="txn-qty-input" class="premium-input w-full" name="qty" value="1" min="1" required style="height: 46px;">
                </div>

                <div class="txn-field txn-field-action">
                    <label class="hidden lg:block" style="visibility:hidden;margin-bottom:8px;">Action</label>
                    <button class="premium-button" type="submit">
                        <span><?php echo ($action==='in') ? 'Receive' : 'Release'; ?></span>
                    </button>
                </div>
            </div>

        </form>
    </div>
    <?php endif; ?>
</div>

<input type="text" id="txn-hid-barcode-input" class="hid-barcode-capture" aria-hidden="true" tabindex="-1" autocomplete="off">

<div class="premium-card txn-page-card">
    <form method="GET" action="" class="p-6 border-b border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-[#13275B]/30">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white">Inventory Status</h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Real-time snapshot. Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>.</p>
        </div>
        
        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <div class="relative w-full md:w-64">
                <input type="text" name="q" id="txn-search-input" class="premium-input w-full pl-10 pr-10" placeholder="Search by name or barcode..." 
                       value="<?php echo htmlspecialchars($search_q); ?>" autocomplete="off" style="height:46px;">
                <button type="button" id="txn-search-scan-btn" class="btn-secondary absolute right-2 top-1.5 flex items-center justify-center cursor-pointer" style="width:34px; height:34px; padding:0; border-radius:8px;" title="Scan barcode to search"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg></button>
                <svg class="absolute left-3 top-3.5 w-4 h-4 text-[#9aa6d1]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            
            <select name="category" class="premium-input inventory-select cursor-pointer text-sm w-full md:w-40" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_cat === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($filter_cat !== '' && !in_array($filter_cat, $categories, true)): ?>
                    <option value="<?php echo htmlspecialchars($filter_cat); ?>" selected>
                        <?php echo htmlspecialchars($filter_cat); ?>
                    </option>
                <?php endif; ?>
            </select>
            
            <?php if($action): ?><input type="hidden" name="action" value="<?php echo $action; ?>"><?php endif; ?>
            <?php if($smartInsightActive): ?><input type="hidden" name="ref" value="insights"><?php endif; ?>
            
            <button type="submit" class="hidden">Search</button>
        </div>
    </form>

    <!-- Desktop / Tablet: Table (≥768px) -->
    <div class="txn-inv-desktop">
        <div class="overflow-x-auto">
            <table class="premium-table w-full text-left">
                <thead>
                    <tr>
                        <th width="15%">SKU</th>
                        <th width="25%">Product Name</th>
                        <th width="20%">Category</th>
                        <th width="15%" class="text-center">Quantity</th>
                        <th width="10%">Unit</th>
                        <th width="15%" class="text-right">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tableItems as $it): ?>
                    <?php
                        $qty    = (int)$it['qty'];
                        $reorder = (int)($it['reorder_point'] ?? 10);
                        $qtyColor = $qty <= 0 ? 'text-red-400' : ($qty <= $reorder ? 'text-amber-400' : 'text-[#E3E3E3]');
                    ?>
                    <tr class="hover:bg-[#1D387B]/50 transition-colors">
                        <td class="font-mono text-xs text-[#9aa6d1]"><?php echo htmlspecialchars($it['sku']); ?></td>
                        <td class="font-medium text-[#E3E3E3]"><?php echo htmlspecialchars($it['name']); ?></td>
                        <td class="text-sm text-[#9aa6d1]"><?php echo htmlspecialchars($it['category'] ?? '-'); ?></td>
                        <td class="text-center">
                            <span class="font-mono font-semibold <?php echo $qtyColor; ?>"><?php echo $qty; ?></span>
                        </td>
                        <td class="text-sm text-[#6b7280]"><?php echo htmlspecialchars($it['uom']); ?></td>
                        <td class="text-right">
                            <?php if ($qty <= 0): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Out</span>
                            <?php elseif ($qty <= $reorder): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">Low</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Good</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tableItems)): ?>
                    <tr><td colspan="6" class="text-center p-8 text-[#9aa6d1]">No products found matching your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /.txn-inv-desktop -->

    <!-- Mobile: Card Grid (<768px) -->
    <div class="txn-inv-mobile">
        <?php if (empty($tableItems)): ?>
            <div class="text-center p-8 text-[#9aa6d1]">No products found matching your criteria.</div>
        <?php else: ?>
        <div class="txn-inv-card-grid">
            <?php foreach($tableItems as $it): ?>
            <?php
                $qty     = (int)$it['qty'];
                $reorder = (int)($it['reorder_point'] ?? 10);
                $isOut   = $qty <= 0;
                $isLow   = !$isOut && $qty <= $reorder;
                $badgeClass = $isOut ? 'txn-badge-out' : ($isLow ? 'txn-badge-low' : 'txn-badge-good');
                $badgeText  = $isOut ? 'OUT' : ($isLow ? 'LOW' : 'GOOD');
                $valClass   = $isOut ? 'val-out' : ($isLow ? 'val-low' : '');
            ?>
            <div class="txn-inv-card">
                <!-- Top: icon · info · badge -->
                <div class="txn-ic-top">
                    <div class="txn-ic-thumb">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#9aa6d1;">
                            <path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                        </svg>
                    </div>
                    <div class="txn-ic-info">
                        <div class="txn-ic-name"><?php echo htmlspecialchars($it['name']); ?></div>
                        <div class="txn-ic-cat"><?php echo htmlspecialchars($it['category'] ?? '—'); ?></div>
                        <div class="txn-ic-sku"><?php echo htmlspecialchars($it['sku']); ?></div>
                    </div>
                    <div class="txn-ic-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></div>
                </div>

                <div class="txn-ic-divider"></div>

                <!-- Stats: Qty · Unit -->
                <div class="txn-ic-stats">
                    <div class="txn-ic-stat">
                        <span class="txn-ic-label">STOCK</span>
                        <span class="txn-ic-value <?php echo $valClass; ?>"><?php echo $qty; ?></span>
                    </div>
                    <div class="txn-ic-stat">
                        <span class="txn-ic-label">REORDER AT</span>
                        <span class="txn-ic-value"><?php echo $reorder; ?></span>
                    </div>
                    <div class="txn-ic-stat">
                        <span class="txn-ic-label">UNIT</span>
                        <span class="txn-ic-value" style="font-size:14px;padding-top:3px;"><?php echo htmlspecialchars($it['uom']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div><!-- /.txn-inv-mobile -->

    <?php if ($totalPages > 1): ?>
    <div class="p-4 border-t border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-4 flex-wrap">
        <?php 
            $params = $_GET; 
            unset($params['page']);
            $qs = http_build_query($params);
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

<?php require_once 'src/partials/barcode_scanner_modal.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="src/scripts/barcode-scanner.js?v=<?php echo time(); ?>"></script>
<script>
$(document).ready(function() {
    $('#productSelect').select2({
        placeholder: "-- Search or Select Product --",
        allowClear: true,
        width: '100%',
        dropdownParent: $(document.body),
        language: { noResults: function() { return "No products found."; } }
    });

    document.addEventListener('themechange', function () {
        $('#productSelect').select2('close');
    });

    if (window.BarcodeScanner) {
        BarcodeScanner.init();
        initTransactionBarcodeInputs();
    }

    <?php if (isset($_SESSION['toast'])): ?>
    showToast(<?php echo json_encode($_SESSION['toast']['message']); ?>, <?php echo json_encode($_SESSION['toast']['type']); ?>);
    <?php unset($_SESSION['toast']); endif; ?>

    <?php if ($smartInsightActive): ?>
    <?php
        $insightParts = [];
        if ($filter_cat !== '') {
            $insightParts[] = "category '" . $filter_cat . "'";
        }
        if ($search_q !== '') {
            $insightParts[] = "product '" . $search_q . "'";
        }
        $insightMsg = $insightParts
            ? 'Showing stock for ' . implode(' and ', $insightParts) . '.'
            : 'Opened stock view from Insights.';
    ?>
    showToast(<?php echo json_encode($insightMsg); ?>, 'success');
    <?php endif; ?>
});

function selectProductByBarcode(barcode) {
    BarcodeScanner.lookup(barcode).then(function(data) {
        if (data.found && data.product) {
            var p = data.product;
            $('#productSelect').val(p.product_id).trigger('change');
            showToast('Product found: ' + p.product_name, 'success');
        } else {
            showToast('No product found for barcode: ' + barcode, 'error');
        }
    }).catch(function() {
        showToast('Failed to look up barcode.', 'error');
    });
}

document.getElementById('txn-scan-barcode-btn')?.addEventListener('click', function() {
    BarcodeScanner.open(selectProductByBarcode);
});

document.getElementById('txn-search-scan-btn')?.addEventListener('click', function() {
    BarcodeScanner.open(function(code) {
        document.getElementById('txn-search-input').value = code;
        document.getElementById('txn-search-input').closest('form').submit();
    });
});

function showToast(message, type) {
    var toast = document.getElementById('toast-container');
    var toastMsg = document.getElementById('toast-message');
    var toastIcon = document.getElementById('toast-icon');
    if (!toast || !toastMsg) return;
    toastMsg.innerText = message;
    toast.className = 'show ' + (type === 'success' ? 'success' : 'error');

    if (type === 'success') {
        toastIcon.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    } else {
        toastIcon.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>';
    }
    setTimeout(function() { toast.classList.remove('show'); }, 3500);
}

document.getElementById('txnSingleForm')?.addEventListener('submit', function(e) {
    const sel = document.getElementById('productSelect');
    const qty = document.getElementById('txn-qty-input');
    if (sel && !sel.value) {
        e.preventDefault();
        sel.classList.add('error-border');
        sel.focus();
        showToast('Please select a product first.', 'error');
        return;
    }
    if (qty && (!qty.value || parseInt(qty.value, 10) < 1)) {
        e.preventDefault();
        qty.classList.add('error-border');
        qty.focus();
        showToast('Quantity must be at least 1.', 'error');
        return;
    }
});
document.getElementById('productSelect')?.addEventListener('change', function() { this.classList.remove('error-border'); });
document.getElementById('txn-qty-input')?.addEventListener('input', function() { this.classList.remove('error-border'); });

function initTransactionBarcodeInputs() {
    if (!window.BarcodeScanner) return;

    var searchInput = document.getElementById('txn-search-input');
    if (searchInput) {
        BarcodeScanner.attachHidInput(searchInput, function(code) {
            searchInput.value = code;
            searchInput.closest('form').submit();
        });
    }

    var wedgeInput = document.getElementById('txn-hid-barcode-input');
    if (wedgeInput) {
        BarcodeScanner.attachHidInput(wedgeInput, function(code) {
            wedgeInput.value = '';
            selectProductByBarcode(code);
        });
    }

    var scanHint = document.getElementById('txn-hid-focus-hint');
    if (scanHint && wedgeInput) {
        scanHint.addEventListener('click', function() {
            BarcodeScanner.focusHidInput(wedgeInput);
        });
    }
}
</script>

<?php renderLayoutEnd(); ?>