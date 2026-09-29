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
if (!function_exists('setFlashMessage')) {
    function setFlashMessage($type, $message) {
        $_SESSION['toast'] = ['type' => $type, 'message' => $message];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['smart_insight_referral']) && !isset($_POST['search_submit'])) {
    $type = $_POST['type'] ?? 'in';
    $id = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);

    try {
        StockMovementService::executeMovement($conn, $id, $qty, $type, $_SESSION['Username'], null, null, 'manual');
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
$dropQuery = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, quantity as qty, unit as uom FROM products ORDER BY product_name";
$dropRes = $conn->query($dropQuery);
while ($r = $dropRes->fetch_assoc()) { $dropdownItems[] = $r; }

$sql = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, category, quantity as qty, unit as uom, reorder_point FROM products WHERE 1=1";
$params = [];
$types = "";

// Apply Search Filter (Name or ID)
if (!empty($search_q)) {
    $sql .= " AND (product_name LIKE ? OR product_id LIKE ?)";
    $searchTerm = "%$search_q%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
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

<link href="assets/vendor/select2.min.css" rel="stylesheet" />

<style>
    /* Select2 — base layout */
    .select2-container { width: 100% !important; max-width: 100%; }

    /* Select2 dark theme */
    [data-theme="dark"] .select2-container--default .select2-selection--single {
        background-color: rgba(19, 39, 91, 0.8) !important;
        border: 1px solid rgba(48, 67, 116, 0.6) !important;
        border-radius: 12px !important;
        height: 46px !important;
        display: flex !important;
        align-items: center !important;
    }
    [data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #E3E3E3 !important;
        padding-left: 16px !important;
        padding-right: 36px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        line-height: 44px !important;
    }
    [data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #9aa6d1 !important;
    }
    [data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px !important;
        right: 10px !important;
    }
    [data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #9aa6d1 transparent transparent transparent !important;
    }
    [data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #9aa6d1 !important;
        margin-right: 28px !important;
        font-size: 16px !important;
    }
    [data-theme="dark"] .select2-dropdown {
        background-color: #1D387B !important;
        border: 1px solid rgba(48, 67, 116, 0.8) !important;
        border-radius: 12px !important;
        z-index: 100010 !important;
    }
    [data-theme="dark"] .select2-search--dropdown { padding: 8px !important; }
    [data-theme="dark"] .select2-search__field {
        background-color: rgba(13, 22, 56, 0.95) !important;
        color: #E3E3E3 !important;
        border: 1px solid rgba(48, 67, 116, 0.6) !important;
        border-radius: 8px !important;
        padding: 8px 12px !important;
        outline: none !important;
    }
    [data-theme="dark"] .select2-results__option {
        color: #E3E3E3 !important;
        padding: 10px 16px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
    }
    [data-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: linear-gradient(135deg, rgba(81, 213, 90, 0.2) 0%, rgba(75, 192, 82, 0.1) 100%) !important;
        color: #51D55A !important;
    }
    [data-theme="dark"] .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: rgba(81, 213, 90, 0.15) !important;
    }

    /* Select2 light theme */
    [data-theme="light"] .select2-container--default .select2-selection--single {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 12px !important;
        height: 46px !important;
        display: flex !important;
        align-items: center !important;
    }
    [data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #1e293b !important;
        padding-left: 16px !important;
        padding-right: 36px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        line-height: 44px !important;
    }
    [data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #64748b !important;
    }
    [data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px !important;
        right: 10px !important;
    }
    [data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent !important;
    }
    [data-theme="light"] .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #64748b !important;
        margin-right: 28px !important;
        font-size: 16px !important;
    }
    [data-theme="light"] .select2-dropdown {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 12px !important;
        z-index: 100010 !important;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12) !important;
    }
    [data-theme="light"] .select2-search--dropdown { padding: 8px !important; }
    [data-theme="light"] .select2-search__field {
        background-color: #f8fafc !important;
        color: #1e293b !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 8px 12px !important;
        outline: none !important;
    }
    [data-theme="light"] .select2-results__option {
        color: #334155 !important;
        padding: 10px 16px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
    }
    [data-theme="light"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: rgba(81, 213, 90, 0.15) !important;
        color: #15803d !important;
    }
    [data-theme="light"] .select2-container--default .select2-results__option[aria-selected=true] {
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
    .txn-action-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
    @media (min-width: 768px) { .txn-action-grid { grid-template-columns: 1fr 1fr; } }
    .txn-action-card {
        background: linear-gradient(135deg, rgba(19, 39, 91, 0.75) 0%, rgba(13, 22, 56, 0.85) 100%);
        border: 1.5px solid rgba(42, 68, 132, 0.6);
        padding: 20px 22px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        cursor: pointer;
        text-decoration: none;
        position: relative;
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    }
    .txn-action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
    }
    .txn-action-card.txn-action-receive:hover {
        border-color: rgba(81, 213, 90, 0.6);
        box-shadow: 0 12px 32px rgba(81, 213, 90, 0.15);
    }
    .txn-action-card.txn-action-release:hover {
        border-color: rgba(244, 63, 94, 0.6);
        box-shadow: 0 12px 32px rgba(244, 63, 94, 0.15);
    }
    .txn-action-left { display: flex; gap: 16px; align-items: center; }
    .txn-action-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.25s ease;
    }
    .txn-action-card:hover .txn-action-icon { transform: scale(1.08); }
    .txn-action-title {
        font-family: 'Onest', sans-serif;
        font-weight: 700;
        color: #ffffff !important;
        font-size: 16px;
        line-height: 1.25;
    }
    .txn-action-sub {
        font-family: 'Onest', sans-serif;
        font-size: 13px;
        color: #9aa6d1 !important;
        margin-top: 3px;
    }
    .txn-action-arrow {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(42, 68, 132, 0.35);
        border: 1px solid rgba(42, 68, 132, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9aa6d1;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .txn-action-card:hover .txn-action-arrow {
        color: #ffffff;
        background: rgba(81, 213, 90, 0.2);
        border-color: rgba(81, 213, 90, 0.4);
        transform: translateX(3px);
    }
    .txn-action-release:hover .txn-action-arrow {
        background: rgba(244, 63, 94, 0.2);
        border-color: rgba(244, 63, 94, 0.4);
    }
    .txn-action-receive .txn-action-icon {
        background: radial-gradient(circle, rgba(81, 213, 90, 0.22) 0%, rgba(81, 213, 90, 0.08) 100%);
        border: 1.5px solid rgba(81, 213, 90, 0.4);
        color: #51D55A;
        box-shadow: 0 4px 14px rgba(81, 213, 90, 0.15);
    }
    .txn-action-release .txn-action-icon {
        background: radial-gradient(circle, rgba(244, 63, 94, 0.22) 0%, rgba(244, 63, 94, 0.08) 100%);
        border: 1.5px solid rgba(244, 63, 94, 0.4);
        color: #fb7185;
        box-shadow: 0 4px 14px rgba(244, 63, 94, 0.15);
    }

    [data-theme="light"] .txn-action-card {
        background: #ffffff !important;
        border-color: #cbd5e1 !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }
    [data-theme="light"] .txn-action-card:hover { box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1); }
    [data-theme="light"] .txn-action-title { color: #0f172a !important; }
    [data-theme="light"] .txn-action-sub { color: #64748b !important; }
    [data-theme="light"] .txn-action-arrow {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #64748b;
    }

    /* ── Inventory Status Desktop Table ─────────────────────────── */
    .txn-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
    }
    .txn-table th,
    .txn-table td {
        padding: 15px 16px;
        vertical-align: middle;
        box-sizing: border-box;
    }
    .txn-table th:first-child,
    .txn-table td:first-child {
        padding-left: 24px;
    }
    .txn-table th:last-child,
    .txn-table td:last-child {
        padding-right: 24px;
    }
    .txn-table thead tr {
        background: rgba(19, 39, 91, 0.85);
        border-bottom: 2px solid rgba(42, 68, 132, 0.7);
    }
    .txn-table th {
        font-family: 'Onest', sans-serif;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9aa6d1 !important;
        background: transparent !important;
        border-bottom: 2px solid rgba(42, 68, 132, 0.6) !important;
    }
    .txn-table td {
        border-bottom: 1px solid rgba(42, 68, 132, 0.35) !important;
    }
    .txn-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .txn-table tbody tr:hover td {
        background: rgba(29, 56, 123, 0.45) !important;
    }

    /* Column Widths & Alignments (Strictly Enforced) */
    .txn-table .col-sku {
        width: 130px;
        text-align: left !important;
    }
    .txn-table .col-product {
        width: auto;
        text-align: left !important;
    }
    .txn-table .col-category {
        width: 200px;
        text-align: left !important;
    }
    .txn-table .col-qty {
        width: 120px;
        text-align: center !important;
    }
    .txn-table .col-unit {
        width: 90px;
        text-align: center !important;
    }
    .txn-table .col-status {
        width: 140px;
        text-align: right !important;
    }

    /* SKU badge */
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

    /* Category badge */
    .txn-cat-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 11px;
        border-radius: 9999px;
        font-family: 'Onest', sans-serif;
        font-size: 12px;
        font-weight: 500;
        background: rgba(29, 56, 123, 0.5);
        color: #c5d1f7;
        border: 1px solid rgba(42, 68, 132, 0.5);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 180px;
    }

    /* Unit pill */
    .txn-unit-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 3px 8px;
        border-radius: 6px;
        font-family: 'Onest', sans-serif;
        font-size: 11px;
        font-weight: 600;
        color: #8b9cc7;
        background: rgba(13, 22, 56, 0.5);
        border: 1px solid rgba(42, 68, 132, 0.35);
        text-transform: lowercase;
    }

    /* Quantity font - Tabular Figures, Crisp, Modern */
    .txn-qty-num {
        font-family: 'Onest', sans-serif;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        font-size: 15px;
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
    .txn-status-low {
        background: rgba(245, 158, 11, 0.12);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.35);
        box-shadow: 0 0 12px rgba(245, 158, 11, 0.08);
    }
    .txn-status-low .txn-status-dot {
        background: #fbbf24;
        box-shadow: 0 0 8px rgba(245, 158, 11, 0.9);
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

    /* Light Mode Overrides for Table */
    [data-theme="light"] .txn-table thead tr {
        background: #f8fafc !important;
        border-bottom-color: #cbd5e1 !important;
    }
    [data-theme="light"] .txn-table th {
        color: #475569 !important;
        border-bottom-color: #cbd5e1 !important;
    }
    [data-theme="light"] .txn-table td {
        border-bottom-color: #e2e8f0 !important;
        color: #1e293b !important;
    }
    [data-theme="light"] .txn-table tbody tr:hover td {
        background: #f1f5f9 !important;
    }
    [data-theme="light"] .txn-sku-badge {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }
    [data-theme="light"] .txn-cat-badge {
        background: #e0e7ff;
        color: #3730a3;
        border-color: #c7d2fe;
    }
    [data-theme="light"] .txn-unit-pill {
        background: #f1f5f9;
        color: #64748b;
        border-color: #cbd5e1;
    }
    [data-theme="light"] .txn-status-good {
        background: #ecfdf5;
        color: #059669;
        border-color: #a7f3d0;
    }
    [data-theme="light"] .txn-status-low {
        background: #fffbeb;
        color: #d97706;
        border-color: #fde68a;
    }
    [data-theme="light"] .txn-status-out {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    /* Light Mode Overrides for Table & Page Card */
    [data-theme="light"] .txn-page-card h3.text-white {
        color: #0f172a !important;
    }
    [data-theme="light"] .txn-table td .text-white {
        color: #0f172a !important;
    }
    [data-theme="light"] .txn-table .txn-qty-num.text-white {
        color: #0f172a !important;
    }
    [data-theme="light"] .txn-table td .text-\[\#6b7aab\] {
        color: #64748b !important;
    }

    /* ── Inventory Status Responsive Toggle ─────────────────────── */
    .txn-inv-desktop { display: block; }
    .txn-inv-mobile  { display: none; }
    @media (max-width: 767px) {
        .txn-inv-desktop { display: none !important; }
        .txn-inv-mobile  { display: block !important; }
    }

    /* ── Mobile Inventory Card Grid (<768px) ─────────────────────── */
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
        padding: 16px;
        display: flex;
        flex-direction: column;
        transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }
    .txn-inv-card:hover {
        border-color: rgba(81, 213, 90, 0.25);
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
        transform: translateY(-2px);
    }
    .txn-ic-top {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
    }
    .txn-ic-thumb {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(13, 22, 56, 0.7);
        border: 1px solid rgba(42, 68, 132, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .txn-ic-info {
        flex: 1;
        min-width: 0;
        overflow: hidden;
    }
    .txn-ic-name {
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #e3e3e3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }
    .txn-ic-cat {
        font-family: 'Onest', sans-serif;
        font-size: 12px;
        color: #9aa6d1;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .txn-ic-divider {
        height: 1px;
        background: rgba(42, 68, 132, 0.4);
        margin: 0 -16px;
    }
    .txn-ic-stats {
        display: flex;
        padding-top: 12px;
    }
    .txn-ic-stat {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .txn-ic-label {
        font-family: 'Onest', sans-serif;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        color: #6b7aab;
        text-transform: uppercase;
    }
    .txn-ic-value {
        font-family: 'Onest', sans-serif;
        font-size: 18px;
        font-weight: 700;
        color: #e3e3e3;
        line-height: 1.1;
    }
    .txn-ic-value.val-out { color: #f87171; }
    .txn-ic-value.val-low { color: #fbbf24; }

    /* Light mode overrides for cards */
    [data-theme="light"] .txn-inv-card {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06) !important;
    }
    [data-theme="light"] .txn-inv-card:hover {
        border-color: #94a3b8 !important;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1) !important;
    }
    [data-theme="light"] .txn-ic-thumb {
        background: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
    }
    [data-theme="light"] .txn-ic-thumb svg {
        color: #64748b !important;
    }
    [data-theme="light"] .txn-ic-name {
        color: #0f172a !important;
    }
    [data-theme="light"] .txn-ic-cat {
        color: #64748b !important;
    }
    [data-theme="light"] .txn-ic-label {
        color: #64748b !important;
    }
    [data-theme="light"] .txn-ic-value {
        color: #0f172a !important;
    }
    [data-theme="light"] .txn-ic-value.val-out { color: #dc2626 !important; }
    [data-theme="light"] .txn-ic-value.val-low { color: #d97706 !important; }
    [data-theme="light"] .txn-ic-divider {
        background: #e2e8f0 !important;
    }


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

<!-- Hero Page Header Banner -->
<div class="page-hero-card mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                </div>
                Stock Transactions &amp; Movement
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Execute verified stock check-in (Receive) and check-out (Release) workflows with real-time verification and audit tracking.
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
                    <div class="txn-action-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                    </div>
                    <div>
                        <div class="txn-action-title">Receive Inventory</div>
                        <div class="txn-action-sub">Check in and record incoming stock</div>
                    </div>
                </div>
                <div class="txn-action-arrow">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                </div>
            </a>

            <a href="release.php" class="txn-action-card txn-action-release">
                <div class="txn-action-left">
                    <div class="txn-action-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                    </div>
                    <div>
                        <div class="txn-action-title">Release Inventory</div>
                        <div class="txn-action-sub">Dispatch and record outgoing stock</div>
                    </div>
                </div>
                <div class="txn-action-arrow">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                </div>
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
                                    <option value="<?php echo $it['id']; ?>" data-qty="<?php echo (int)$it['qty']; ?>" data-uom="<?php echo htmlspecialchars($it['uom'] ?? ''); ?>">
                                         <?php echo htmlspecialchars($it['sku'].' - '.$it['name']); ?> (Qty: <?php echo $it['qty']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

<div class="premium-card txn-page-card">
    <form method="GET" action="" class="p-6 border-b border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-[#13275B]/30">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2.5">
                <span>Inventory Status</span>
                <span class="text-[11px] font-semibold text-[#51D55A] bg-[#51D55A]/10 border border-[#51D55A]/25 px-2.5 py-0.5 rounded-full">
                    <?php echo number_format($totalRows); ?> items
                </span>
            </h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Real-time snapshot. Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative w-full sm:w-72">
                    <input type="text" name="q" id="txn-search-input" class="premium-input w-full px-4" placeholder="Search product name or ID..." 
                           value="<?php echo htmlspecialchars($search_q); ?>" autocomplete="off" style="height:44px; font-size:13px;">
                </div>
                <button type="submit" class="btn-secondary h-[44px] w-[44px] min-w-[44px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Search Transactions" aria-label="Search">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
                <a href="transactions.php<?php echo $action ? '?action=' . urlencode($action) : ''; ?>" class="btn-secondary h-[44px] w-[44px] min-w-[44px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Reset Filters" aria-label="Reset Filters">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </a>
            </div>
            
            <select name="category" class="premium-input inventory-select cursor-pointer text-xs w-full sm:w-44" onchange="this.form.submit()" style="height:44px;">
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
        </div>
    </form>

    <!-- Desktop / Tablet: Table (≥768px) -->
    <div class="txn-inv-desktop">
        <div class="overflow-x-auto">
            <table class="premium-table txn-table w-full">
                <thead>
                    <tr>
                        <th class="col-sku">SKU</th>
                        <th class="col-product">Product Name</th>
                        <th class="col-category">Category</th>
                        <th class="col-qty">Quantity</th>
                        <th class="col-unit">Unit</th>
                        <th class="col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tableItems as $it): ?>
                    <?php
                        $qty    = (int)$it['qty'];
                        $reorder = (int)($it['reorder_point'] ?? 10);
                        $qtyColor = $qty <= 0 ? 'text-rose-400' : ($qty <= $reorder ? 'text-amber-400' : 'text-white');
                    ?>
                    <tr>
                        <td class="col-sku">
                            <span class="txn-sku-badge"><?php echo htmlspecialchars($it['sku']); ?></span>
                        </td>
                        <td class="col-product">
                            <div class="font-semibold text-white text-[14px] leading-snug">
                                <?php echo htmlspecialchars($it['name']); ?>
                            </div>
                        </td>
                        <td class="col-category">
                            <span class="txn-cat-badge" title="<?php echo htmlspecialchars($it['category'] ?? 'Uncategorized'); ?>">
                                <?php echo htmlspecialchars($it['category'] ?? 'Uncategorized'); ?>
                            </span>
                        </td>
                        <td class="col-qty">
                            <span class="txn-qty-num <?php echo $qtyColor; ?>"><?php echo number_format($qty); ?></span>
                        </td>
                        <td class="col-unit">
                            <span class="txn-unit-pill"><?php echo htmlspecialchars($it['uom'] ?: 'pcs'); ?></span>
                        </td>
                        <td class="col-status">
                            <?php if ($qty <= 0): ?>
                                <span class="txn-status-pill txn-status-out">
                                    <span class="txn-status-dot"></span>
                                    <span>Out</span>
                                </span>
                            <?php elseif ($qty <= $reorder): ?>
                                <span class="txn-status-pill txn-status-low">
                                    <span class="txn-status-dot"></span>
                                    <span>Low</span>
                                </span>
                            <?php else: ?>
                                <span class="txn-status-pill txn-status-good">
                                    <span class="txn-status-dot"></span>
                                    <span>Good</span>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tableItems)): ?>
                    <tr>
                        <td colspan="6" class="text-center p-12 text-[#9aa6d1]">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-10 h-10 text-[#6b7aab]/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                <span class="font-medium">No products found matching your search or filter.</span>
                                <?php if (!empty($search_q) || !empty($filter_cat)): ?>
                                    <a href="transactions.php" class="text-xs text-[#51D55A] hover:underline mt-1">Clear all filters</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
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
                        <div class="mt-1"><span class="txn-sku-badge"><?php echo htmlspecialchars($it['sku']); ?></span></div>
                    </div>
                    <div>
                        <?php if ($isOut): ?>
                            <span class="txn-status-pill txn-status-out">
                                <span class="txn-status-dot"></span>
                                <span>Out</span>
                            </span>
                        <?php elseif ($isLow): ?>
                            <span class="txn-status-pill txn-status-low">
                                <span class="txn-status-dot"></span>
                                <span>Low</span>
                            </span>
                        <?php else: ?>
                            <span class="txn-status-pill txn-status-good">
                                <span class="txn-status-dot"></span>
                                <span>Good</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="txn-ic-divider"></div>

                <!-- Stats: Qty · Unit -->
                <div class="txn-ic-stats">
                    <div class="txn-ic-stat">
                        <span class="txn-ic-label">STOCK</span>
                        <span class="txn-ic-value <?php echo $valClass; ?>" style="font-variant-numeric: tabular-nums;"><?php echo number_format($qty); ?></span>
                    </div>
                    <div class="txn-ic-stat">
                        <span class="txn-ic-label">REORDER AT</span>
                        <span class="txn-ic-value" style="font-variant-numeric: tabular-nums;"><?php echo number_format($reorder); ?></span>
                    </div>
                    <div class="txn-ic-stat">
                        <span class="txn-ic-label">UNIT</span>
                        <span class="txn-ic-value" style="font-size:14px;padding-top:3px;"><?php echo htmlspecialchars($it['uom'] ?: 'pcs'); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div><!-- /.txn-inv-mobile -->

    <?php if ($totalPages > 1): ?>
    <div class="pagination-container p-2.5 sm:p-4 border-t border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-3 sm:gap-4 flex-wrap">
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

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/vendor/select2.min.js"></script>
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
</script>

<?php renderLayoutEnd(); ?>