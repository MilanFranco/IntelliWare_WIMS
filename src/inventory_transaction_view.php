<?php
/**
 * Shared inventory transaction page (Receive / Release) — POS-style interface.
 * Expects $TXN_MODE = 'in'|'out' and $TXN_PAGE_TITLE.
 */
if (!isset($TXN_MODE) || !in_array($TXN_MODE, ['in', 'out'], true)) {
    $TXN_MODE = 'in';
}
if (!isset($TXN_PAGE_TITLE)) {
    $TXN_PAGE_TITLE = $TXN_MODE === 'in' ? 'Receive Inventory' : 'Release Inventory';
}

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/services/StockMovementService.php';

if (!function_exists('setInventoryFlash')) {
    function setInventoryFlash(string $type, string $message): void
    {
        $_SESSION['toast'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('getStockStatus')) {
    function getStockStatus(int $qty, int $reorderPoint): string
    {
        if ($qty <= 0) {
            return 'out_of_stock';
        }
        if ($qty <= $reorderPoint) {
            return 'low_stock';
        }
        return 'in_stock';
    }
}

if (!function_exists('getStockStatusLabel')) {
    function getStockStatusLabel(string $status): string
    {
        switch ($status) {
            case 'out_of_stock':
                return 'Out of Stock';
            case 'low_stock':
                return 'Low Stock';
            default:
                return 'In Stock';
        }
    }
}

require_once __DIR__ . '/helpers/product_image_url.php';

$redirectPage = $TXN_MODE === 'in' ? 'receive.php' : 'release.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, (string)$_POST['csrf_token'])) {
        setInventoryFlash('error', 'Security token mismatch. Transaction rejected.');
        header("Location: $redirectPage");
        exit();
    }
    $remarks = trim($_POST['remarks'] ?? '');
    $remarksVal = $remarks !== '' ? $remarks : null;

    try {
        if (!empty($_POST['items_json'])) {
            $items = json_decode($_POST['items_json'], true);
            if (!is_array($items) || empty($items)) {
                throw new Exception('No items in transaction.');
            }

            $normalized = [];
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['qty'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    throw new Exception('Invalid item in transaction.');
                }

                $entry = ['product_id' => $productId, 'qty' => $qty];

                if ($TXN_MODE === 'in') {
                    $metadata = array_filter([
                        'batch_number' => trim((string)($item['batch_number'] ?? '')),
                        'expiry_date' => trim((string)($item['expiry_date'] ?? '')),
                        'manufacturing_date' => trim((string)($item['manufacturing_date'] ?? '')),
                        'location' => trim((string)($item['location'] ?? '')),
                        'category' => trim((string)($item['category'] ?? '')),
                    ], static function ($value) {
                        return $value !== '';
                    });
                    if (!empty($metadata)) {
                        $entry['metadata'] = $metadata;
                    }
                }

                $normalized[] = $entry;
            }

            $entrySource = (!empty($_POST['entry_source']) && in_array($_POST['entry_source'], ['ai_scan', 'manual'], true)) ? $_POST['entry_source'] : 'manual';
            $results = StockMovementService::executeBatchMovement(
                $conn,
                $normalized,
                $TXN_MODE,
                $_SESSION['Username'],
                $remarksVal,
                $entrySource
            );

            $totalQty = array_sum(array_column($normalized, 'qty'));
            $label = $TXN_MODE === 'in' ? 'received' : 'released';
            setInventoryFlash(
                'success',
                sprintf(
                    '%d product(s) %s — %d total unit(s). Stock updated successfully.',
                    count($results),
                    $label,
                    $totalQty
                )
            );
        } else {
            $productId = (int)($_POST['product_id'] ?? 0);
            $qty = (int)($_POST['qty'] ?? 0);
            $entrySource = (!empty($_POST['entry_source']) && in_array($_POST['entry_source'], ['ai_scan', 'manual'], true)) ? $_POST['entry_source'] : 'manual';

            $result = StockMovementService::executeMovement(
                $conn,
                $productId,
                $qty,
                $TXN_MODE,
                $_SESSION['Username'],
                $remarksVal,
                null,
                $entrySource
            );
            $productName = $result['product']['product_name'] ?? 'Product';
            $label = $TXN_MODE === 'in' ? 'received' : 'released';
            setInventoryFlash(
                'success',
                sprintf(
                    '%s: %d %s. Stock updated %d → %d.',
                    $productName,
                    $qty,
                    $label,
                    $result['previous_stock'],
                    $result['new_stock']
                )
            );
        }
    } catch (Exception $e) {
        setInventoryFlash('error', $e->getMessage());
    }

    header('Location: ' . $redirectPage);
    exit();
}

$autoloadProduct = null;
$paramProductId = (int)($_GET['product_id'] ?? ($_GET['id'] ?? ($_GET['view_id'] ?? 0)));
$paramSku       = trim($_GET['sku'] ?? '');
$paramQ         = trim($_GET['q'] ?? '');

if ($paramProductId > 0 || $paramSku !== '' || $paramQ !== '') {
    $autoSql = "
        SELECT p.product_id, p.product_name, p.category, p.supplier, p.location,
               p.quantity, p.reorder_point, p.unit, p.price, p.shelf_life,
               CONCAT('P-', LPAD(p.product_id, 4, '0')) AS sku,
               (SELECT MIN(b.expiry_date) FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0) AS expiry_date,
               (SELECT b.batch_number FROM inventory_batches b WHERE b.product_id = p.product_id AND b.quantity > 0 ORDER BY b.expiry_date IS NULL ASC, b.expiry_date ASC LIMIT 1) AS batch_number
        FROM products p
        WHERE 1=1
    ";
    $autoParams = [];
    $autoTypes = "";
    if ($paramProductId > 0) {
        $autoSql .= " AND p.product_id = ?";
        $autoParams[] = $paramProductId;
        $autoTypes .= "i";
    } elseif ($paramSku !== '') {
        $autoSql .= " AND CONCAT('P-', LPAD(p.product_id, 4, '0')) = ?";
        $autoParams[] = $paramSku;
        $autoTypes .= "s";
    } elseif ($paramQ !== '') {
        $autoSql .= " AND (p.product_name LIKE ? OR p.product_name = ?)";
        $autoParams[] = '%' . $paramQ . '%';
        $autoParams[] = $paramQ;
        $autoTypes .= "ss";
    }
    $autoSql .= " LIMIT 1";

    $autoStmt = $conn->prepare($autoSql);
    if ($autoStmt) {
        if (!empty($autoParams)) {
            $autoStmt->bind_param($autoTypes, ...$autoParams);
        }
        $autoStmt->execute();
        $autoRes = $autoStmt->get_result();
        if ($autoRow = $autoRes->fetch_assoc()) {
            $aQty = (int)$autoRow['quantity'];
            $aRp  = (int)($autoRow['reorder_point'] ?? 10);
            $stockStatus = getStockStatus($aQty, $aRp);
            $stockStatusLabel = getStockStatusLabel($stockStatus);
            $img = getProductImageUrl((int)$autoRow['product_id']);

            $autoloadProduct = [
                'product_id'         => (int)$autoRow['product_id'],
                'id'                 => (int)$autoRow['product_id'],
                'product_name'       => $autoRow['product_name'],
                'name'               => $autoRow['product_name'],
                'category'           => $autoRow['category'] ?? '',
                'supplier'           => $autoRow['supplier'] ?? '',
                'location'           => $autoRow['location'] ?? '',
                'quantity'           => $aQty,
                'currentStock'       => $aQty,
                'reorder_point'      => $aRp,
                'reorderPoint'       => $aRp,
                'unit'               => $autoRow['unit'] ?? '',
                'uom'                => $autoRow['unit'] ?? '',
                'price'              => (float)($autoRow['price'] ?? 0),
                'shelf_life'         => $autoRow['shelf_life'] !== null ? (int)$autoRow['shelf_life'] : null,
                'shelfLife'          => $autoRow['shelf_life'] !== null ? (int)$autoRow['shelf_life'] : null,
                'sku'                => $autoRow['sku'] ?? '',
                'expiry_date'        => $autoRow['expiry_date'] ?? null,
                'batch_number'       => $autoRow['batch_number'] ?? '',
                'stock_status'       => $stockStatus,
                'stock_status_label' => $stockStatusLabel,
                'image_url'          => $img,
                'imageUrl'           => $img
            ];
        }
        $autoStmt->close();
    }
}

$conn->close();

$txnIcon = $TXN_MODE === 'in'
    ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12M12 16.5V3"/></svg>'
    : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 12 12 7.5m0 0 4.5 4.5M12 7.5V21"/></svg>';
$txnLabel = $TXN_MODE === 'in' ? 'Receive Stock' : 'Release Stock';
$confirmLabel = $TXN_MODE === 'in' ? 'Confirm Receive' : 'Confirm Release';
$accentClass = $TXN_MODE === 'in' ? 'pos-accent-receive' : 'pos-accent-release';

require_once __DIR__ . '/layout.php';
renderLayoutStart($TXN_PAGE_TITLE, $role, $_SESSION['Username']);
?>

<style>
    /* ── Layout ──────────────────────────────────────────────────────────── */
    .pos-layout {
        display: grid;
        grid-template-columns: minmax(400px, 1.4fr) minmax(320px, 1fr);
        gap: 16px;
        align-items: stretch;
        height: calc(100vh - 145px);
        max-height: calc(100vh - 145px);
        overflow: hidden;
    }
    @media (max-width: 1100px) {
        .pos-layout { grid-template-columns: 1fr; height: auto; max-height: none; overflow: visible; }
    }

    .pos-panel {
        display: flex;
        flex-direction: column;
        min-height: 0;
        background: var(--iw-bg-card);
        border: 1px solid var(--iw-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--iw-shadow-card), inset 0 1px 0 rgba(255, 255, 255, 0.08);
        transform: translateZ(0);
    }
    .pos-panel:hover { transform: none; }

    .pos-panel-header {
        padding: 12px 16px;
        border-bottom: 1px solid var(--iw-border);
        background: var(--iw-bg-panel-header);
        flex-shrink: 0;
    }

    .pos-panel-body {
        padding: 16px;
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        gap: 14px;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        transform: translateZ(0);
        will-change: scroll-position;
        scrollbar-width: thin;
        scrollbar-color: rgba(81, 213, 90, 0.35) transparent;
    }
    .pos-accent-release .pos-panel-body {
        scrollbar-color: rgba(239, 68, 68, 0.35) transparent;
    }
    .pos-panel-body::-webkit-scrollbar {
        width: 6px;
    }
    .pos-panel-body::-webkit-scrollbar-track {
        background: transparent;
    }
    .pos-panel-body::-webkit-scrollbar-thumb {
        background: rgba(81, 213, 90, 0.35);
        border-radius: 6px;
    }
    .pos-accent-release .pos-panel-body::-webkit-scrollbar-thumb {
        background: rgba(239, 68, 68, 0.35);
    }
    .pos-panel-body::-webkit-scrollbar-thumb:hover {
        background: rgba(81, 213, 90, 0.65);
    }
    .pos-accent-release .pos-panel-body::-webkit-scrollbar-thumb:hover {
        background: rgba(239, 68, 68, 0.65);
    }
    [data-theme="light"] .pos-panel-body {
        scrollbar-color: #CBD5E1 transparent !important;
    }
    [data-theme="light"] .pos-panel-body::-webkit-scrollbar-thumb {
        background: #CBD5E1 !important;
    }
    [data-theme="light"] .pos-panel-body::-webkit-scrollbar-thumb:hover {
        background: #94A3B8 !important;
    }

    /* Prevent backdrop-filter repaints on inputs inside scrolling panels */
    .pos-panel-body .premium-input,
    .pos-panel-body input {
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        transform: translateZ(0);
    }

    #inv-txn-form {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        gap: 16px;
        height: 100%;
    }

    /* ── Search Bar & Embedded Camera Scanner ───────────────────────────── */
    .pos-search-wrap {
        position: relative;
        flex-shrink: 0;
    }
    .pos-search-input {
        width: 100%;
        height: 46px;
        padding: 0 74px 0 42px;
        background: var(--iw-bg-surface);
        border: 1px solid var(--iw-border-input);
        border-radius: 12px;
        color: var(--iw-text);
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .pos-search-input:focus {
        border-color: #51D55A;
        box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.15);
    }
    .pos-accent-release .pos-search-input:focus {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }
    .pos-search-input::placeholder { color: #6a749a; }
    .pos-search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #6a749a;
        pointer-events: none;
        width: 17px;
        height: 17px;
    }
    .pos-search-clear {
        position: absolute;
        right: 44px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6a749a;
        cursor: pointer;
        padding: 4px;
        display: none;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: color 0.15s;
        z-index: 5;
    }
    .pos-search-clear:hover { color: var(--iw-text); }
    .pos-search-clear.visible { display: flex; }

    /* Secondary integrated scanner icon inside search bar */
    .pos-search-camera-btn {
        position: absolute;
        right: 7px;
        top: 50%;
        transform: translateY(-50%);
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: rgba(42, 68, 132, 0.25);
        border: 1px solid rgba(42, 68, 132, 0.45);
        color: #9aa6d1;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        padding: 0;
        z-index: 5;
    }
    .pos-search-camera-btn:hover {
        color: #51D55A;
        border-color: #51D55A;
        background: rgba(81, 213, 90, 0.12);
    }
    .pos-accent-release .pos-search-camera-btn:hover {
        color: #f87171;
        border-color: #f87171;
        background: rgba(239, 68, 68, 0.12);
    }
    .pos-search-camera-btn.pos-camera-active {
        color: #51D55A;
        border-color: #51D55A;
        background: rgba(81, 213, 90, 0.25);
        box-shadow: 0 0 10px rgba(81, 213, 90, 0.35);
    }
    .pos-accent-release .pos-search-camera-btn.pos-camera-active {
        color: #f87171;
        border-color: #f87171;
        background: rgba(239, 68, 68, 0.25);
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.35);
    }
    [data-theme="light"] .pos-search-camera-btn {
        background: #F1F5F9;
        border-color: #CBD5E1;
        color: #64748B;
    }
    [data-theme="light"] .pos-search-camera-btn:hover {
        background: #ECFDF5;
        border-color: #10B981;
        color: #059669;
    }
    [data-theme="light"].pos-accent-release .pos-search-camera-btn:hover,
    [data-theme="light"] .pos-accent-release .pos-search-camera-btn:hover {
        background: #FEF2F2;
        border-color: #EF4444;
        color: #DC2626;
    }

    /* Camera reticle overlay */
    .pos-scanner-reticle-box {
        width: 170px;
        height: 130px;
        border: 2px dashed #51D55A;
        border-radius: 12px;
        box-shadow: 0 0 0 9999px rgba(4, 7, 20, 0.5);
        position: relative;
    }
    .pos-scanner-reticle-label {
        position: absolute;
        top: -22px;
        left: 0;
        right: 0;
        text-align: center;
        color: #51D55A;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .pos-accent-release .pos-scanner-reticle-box {
        border-color: #ef4444;
    }
    .pos-accent-release .pos-scanner-reticle-label {
        color: #f87171;
    }
    .pos-accent-release #inv-camera-snap-manual {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        border-color: #ef4444 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.35) !important;
    }

    /* ── Search Dropdown ─────────────────────────────────────────────────── */
    .pos-search-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: var(--iw-bg-card);
        border: 1px solid var(--iw-border);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
        z-index: 100;
        overflow: hidden;
        display: none;
        max-height: 280px;
        overflow-y: auto;
    }
    .pos-search-dropdown.open { display: block; }

    .pos-search-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.12s;
    }
    .pos-search-item:last-child { border-bottom: none; }
    .pos-search-item:hover { background: rgba(81, 213, 90, 0.08); }
    .pos-accent-release .pos-search-item:hover { background: rgba(239, 68, 68, 0.08); }
    .pos-search-item-thumb {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        object-fit: cover;
        background: var(--iw-bg-surface);
        border: 1px solid var(--iw-border);
        flex-shrink: 0;
    }
    .pos-search-item-placeholder {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: var(--iw-bg-surface);
        border: 1px solid var(--iw-border);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: #9aa6d1;
    }
    .pos-search-item-info { flex: 1; min-width: 0; }
    .pos-search-item-name {
        font-size: 13px;
        font-weight: 600;
        color: #e3e3e3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pos-search-item-meta {
        font-size: 11px;
        color: #9aa6d1;
        margin-top: 1px;
    }
    .pos-search-item-stock-badge {
        font-size: 10px;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 99px;
        flex-shrink: 0;
    }
    .pos-search-no-results {
        padding: 16px 14px;
        color: #9aa6d1;
        font-size: 13px;
        text-align: center;
    }

    /* ── Sections & Visual Grouping ──────────────────────────────────────── */
    .pos-zone {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .pos-zone-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2px;
    }
    .pos-zone-label {
        font-family: 'Onest', sans-serif;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--iw-text-muted, #9aa6d1);
    }
    [data-theme="light"] .pos-zone-label {
        color: #64748B;
    }

    .pos-cart-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        font-size: 11px;
        font-weight: 700;
        border-radius: 999px;
        background: rgba(81, 213, 90, 0.15);
        color: #51D55A;
        border: 1px solid rgba(81, 213, 90, 0.3);
    }
    .pos-accent-release .pos-cart-badge {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.3);
    }
    [data-theme="light"] .pos-cart-badge {
        background: #ECFDF5;
        color: #059669;
        border-color: #A7F3D0;
    }
    [data-theme="light"].pos-accent-release .pos-cart-badge,
    [data-theme="light"] .pos-accent-release .pos-cart-badge {
        background: #FEF2F2;
        color: #DC2626;
        border-color: #FECACA;
    }

    /* Demoted Clear Cart ghost button */
    .pos-ghost-clear-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11.5px;
        font-weight: 600;
        color: #9aa6d1;
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 3px 8px;
        border-radius: 6px;
        transition: all 0.15s ease;
    }
    .pos-ghost-clear-btn:hover {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
    }
    [data-theme="light"] .pos-ghost-clear-btn {
        color: #64748B;
    }
    [data-theme="light"] .pos-ghost-clear-btn:hover {
        color: #dc2626;
        background: #fee2e2;
    }

    /* ── Cart List & Product Cards (Desktop + Mobile) ────────────────────── */
    .pos-cart-wrap {
        flex: 1;
        min-height: 120px;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        transform: translateZ(0);
        will-change: scroll-position;
        border: none;
        background: transparent;
        padding: 0;
        scrollbar-width: thin;
        scrollbar-color: rgba(81, 213, 90, 0.35) transparent;
    }
    .pos-accent-release .pos-cart-wrap {
        scrollbar-color: rgba(239, 68, 68, 0.35) transparent;
    }
    .pos-cart-wrap::-webkit-scrollbar {
        width: 6px;
    }
    .pos-cart-wrap::-webkit-scrollbar-track {
        background: transparent;
    }
    .pos-cart-wrap::-webkit-scrollbar-thumb {
        background: rgba(81, 213, 90, 0.35);
        border-radius: 6px;
    }
    .pos-accent-release .pos-cart-wrap::-webkit-scrollbar-thumb {
        background: rgba(239, 68, 68, 0.35);
    }
    .pos-cart-wrap::-webkit-scrollbar-thumb:hover {
        background: rgba(81, 213, 90, 0.65);
    }
    .pos-accent-release .pos-cart-wrap::-webkit-scrollbar-thumb:hover {
        background: rgba(239, 68, 68, 0.65);
    }
    [data-theme="light"] .pos-cart-wrap {
        scrollbar-color: #CBD5E1 transparent !important;
    }
    [data-theme="light"] .pos-cart-wrap::-webkit-scrollbar-thumb {
        background: #CBD5E1 !important;
    }
    [data-theme="light"] .pos-cart-wrap::-webkit-scrollbar-thumb:hover {
        background: #94A3B8 !important;
    }

    .pos-cart-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        min-height: 140px;
        color: var(--iw-text-muted, #9aa6d1);
        font-size: 13px;
        text-align: center;
        padding: 24px;
        border: 1px dashed var(--iw-border, rgba(42, 68, 132, 0.45));
        border-radius: 14px;
        background: var(--iw-bg-surface, #0B1438);
    }
    [data-theme="light"] .pos-cart-empty {
        background: #F8FAFC;
        border-color: #E2E8F0;
        color: #64748B;
    }

    .pos-cart-table {
        display: block;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
        font-family: 'Onest', sans-serif;
    }
    .pos-cart-table thead {
        display: none;
    }
    .pos-cart-table tbody {
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }
    .pos-cart-table tr {
        display: grid;
        grid-template-columns: 48px 1fr 36px;
        grid-template-areas:
            "thumb  title  remove"
            "thumb  meta   remove"
            "qty    qty    total";
        column-gap: 12px;
        row-gap: 4px;
        align-items: center;
        padding: 12px 14px;
        border-radius: 14px;
        background: var(--iw-bg-card, #13275B);
        border: 1px solid var(--iw-border, rgba(42, 68, 132, 0.5));
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        box-sizing: border-box;
        position: relative;
        width: 100%;
        cursor: pointer;
        transition: border-color 0.18s, box-shadow 0.18s;
    }
    .pos-cart-table tr:hover {
        border-color: rgba(81, 213, 90, 0.35);
    }
    .pos-accent-release .pos-cart-table tr:hover {
        border-color: rgba(239, 68, 68, 0.35);
    }
    [data-theme="light"] .pos-cart-table tr {
        background: #FFFFFF;
        border-color: #E2E8F0;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }
    [data-theme="light"] .pos-cart-table tr:hover {
        border-color: #CBD5E1;
    }

    .pos-cart-table tr.pos-cart-row-selected {
        border-color: rgba(81, 213, 90, 0.55);
        box-shadow: 0 0 0 1px rgba(81, 213, 90, 0.25);
    }
    .pos-accent-release .pos-cart-table tr.pos-cart-row-selected {
        border-color: rgba(239, 68, 68, 0.55);
        box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.25);
    }

    .pos-cart-table tr.pos-cart-row-flash {
        animation: posRowFlashReceive 0.6s ease;
    }
    .pos-accent-release .pos-cart-table tr.pos-cart-row-flash {
        animation: posRowFlashRelease 0.6s ease;
    }
    @keyframes posRowFlashReceive {
        0%   { background: rgba(81, 213, 90, 0.22); }
        100% { background: var(--iw-bg-card, #13275B); }
    }
    @keyframes posRowFlashRelease {
        0%   { background: rgba(239, 68, 68, 0.22); }
        100% { background: var(--iw-bg-card, #13275B); }
    }

    /* Remove any stray mint/tinted blocks from table cells */
    .pos-cart-table td {
        display: block;
        padding: 0;
        border: none;
        background: transparent !important;
    }

    /* 1. Thumbnail */
    .pos-cart-thumb-cell {
        grid-area: thumb;
        width: 48px;
        height: 48px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pos-cart-thumb,
    .pos-cart-thumb-ph {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid var(--iw-border, rgba(42, 68, 132, 0.45));
        background: var(--iw-bg-surface, #0B1438);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .pos-cart-thumb:hover {
        transform: scale(1.06);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
        z-index: 10;
        position: relative;
    }
    [data-theme="light"] .pos-cart-thumb,
    [data-theme="light"] .pos-cart-thumb-ph {
        background: #F8FAFC;
        border-color: #E2E8F0;
    }

    /* 2. Product Title & Typography */
    .pos-cart-table tr > td:nth-child(2) {
        grid-area: title;
        min-width: 0;
        padding-right: 4px;
        align-self: start;
    }
    .pos-cart-name {
        font-size: 16px;
        font-weight: 600;
        line-height: 1.35;
        color: var(--iw-text, #E3E3E3);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    [data-theme="light"] .pos-cart-name {
        color: #0F172A;
    }

    .pos-cart-unit {
        font-size: 13px;
        color: var(--iw-text-muted, #9aa6d1);
        margin-top: 2px;
        line-height: 1.3;
    }
    [data-theme="light"] .pos-cart-unit {
        color: #64748B;
    }

    /* 3. Stock Chip */
    .pos-cart-table tr > td:nth-child(3) {
        grid-area: meta;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
    }
    .pos-cart-stock-chip {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        background: rgba(42, 68, 132, 0.3);
        color: #9aa6d1;
        border: 1px solid rgba(42, 68, 132, 0.45);
        line-height: 1.2;
    }
    [data-theme="light"] .pos-cart-stock-chip {
        background: #F1F5F9;
        color: #475569;
        border-color: #E2E8F0;
    }

    /* 7. Remove Button — Top-Right Corner of Card, Ghost Icon */
    .pos-cart-table tr > td:nth-child(7) {
        grid-area: remove;
        display: flex;
        align-items: flex-start;
        justify-content: flex-end;
        align-self: start;
    }
    .pos-remove-btn {
        width: 32px;
        height: 32px;
        min-width: 32px;
        min-height: 32px;
        border-radius: 8px;
        background: transparent;
        border: none;
        color: #9aa6d1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        transition: all 0.15s ease;
    }
    .pos-remove-btn:hover,
    .pos-remove-btn:active {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.12);
    }
    [data-theme="light"] .pos-remove-btn {
        color: #94A3B8;
    }
    [data-theme="light"] .pos-remove-btn:hover,
    [data-theme="light"] .pos-remove-btn:active {
        color: #dc2626;
        background: #FEE2E2;
    }

    /* 5. Unit price (hidden in card layout) */
    .pos-cart-table tr > td:nth-child(5) {
        display: none;
    }

    /* 4. Quantity Stepper — Min 44px Tap Targets */
    .pos-cart-table tr > td:nth-child(4) {
        grid-area: qty;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--iw-border, rgba(42, 68, 132, 0.35));
        width: auto;
        position: static;
    }
    [data-theme="light"] .pos-cart-table tr > td:nth-child(4) {
        border-top-color: #E2E8F0;
    }

    .pos-qty-controls {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 2px;
        width: auto;
        max-width: fit-content;
        background: var(--iw-bg-surface, #0B1438);
        padding: 2px;
        border-radius: 12px;
        border: 1px solid var(--iw-border-input, #2A4484);
        box-sizing: border-box;
    }
    [data-theme="light"] .pos-qty-controls {
        background: #F8FAFC;
        border-color: #CBD5E1;
    }
    .pos-qty-btn {
        width: 44px;
        height: 44px;
        min-width: 44px;
        min-height: 44px;
        font-size: 20px;
        font-weight: 700;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        background: transparent;
        cursor: pointer;
        color: var(--iw-text, #E3E3E3);
        flex-shrink: 0;
        touch-action: manipulation;
        user-select: none;
        transition: all 0.15s ease;
    }
    .pos-qty-btn:active {
        background: rgba(81, 213, 90, 0.15);
    }
    .pos-accent-release .pos-qty-btn:active {
        background: rgba(239, 68, 68, 0.15);
    }
    [data-theme="light"] .pos-qty-btn {
        color: #0F172A;
    }
    [data-theme="light"] .pos-qty-btn:active {
        background: #E2E8F0;
    }

    .pos-qty-input {
        width: 48px;
        min-width: 48px;
        max-width: 48px;
        height: 44px;
        min-height: 44px;
        font-size: 16px;
        font-weight: 700;
        border: none;
        background: transparent;
        padding: 0;
        text-align: center;
        color: var(--iw-text, #E3E3E3);
        flex: 0 0 48px;
        box-sizing: border-box;
        -moz-appearance: textfield;
    }
    .pos-qty-input::-webkit-outer-spin-button,
    .pos-qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    [data-theme="light"] .pos-qty-input {
        color: #0F172A;
    }

    /* 6. Total Price — 18px / 700, Right-aligned, Primary Color */
    .pos-cart-table tr > td:nth-child(6) {
        grid-area: total;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        justify-content: center;
        position: static;
        width: auto;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--iw-border, rgba(42, 68, 132, 0.35));
        text-align: right;
    }
    [data-theme="light"] .pos-cart-table tr > td:nth-child(6) {
        border-top-color: #E2E8F0;
    }

    .pos-cart-total-cell {
        font-size: 18px;
        font-weight: 700;
        color: #51D55A;
        font-family: 'Onest', sans-serif;
        line-height: 1.2;
        white-space: nowrap;
        text-align: right;
    }
    .pos-accent-release .pos-cart-total-cell {
        color: #f87171;
    }
    .pos-cart-total-cell::before {
        content: "LINE TOTAL";
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9aa6d1;
        display: block;
        text-align: right;
        margin-bottom: 2px;
    }
    [data-theme="light"] .pos-cart-total-cell::before {
        color: #64748B;
    }

    /* ── Summary Card & Grand Total ──────────────────────────────────────── */
    .pos-summary-card {
        padding: 14px;
        border-radius: 14px;
        background: var(--iw-bg-surface, #0B1438);
        border: 1px solid var(--iw-border, rgba(42, 68, 132, 0.45));
    }
    [data-theme="light"] .pos-summary-card {
        background: #F8FAFC;
        border-color: #E2E8F0;
    }

    .pos-summary-metric-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--iw-text-muted, #9aa6d1);
        display: block;
        margin-bottom: 2px;
    }
    [data-theme="light"] .pos-summary-metric-label {
        color: #64748B;
    }

    .pos-summary-metric-val {
        font-size: 20px;
        font-weight: 700;
        color: var(--iw-text, #E3E3E3);
        font-family: 'Onest', sans-serif;
        line-height: 1.2;
    }
    [data-theme="light"] .pos-summary-metric-val {
        color: #0F172A;
    }

    .pos-summary-divider {
        height: 1px;
        background: var(--iw-border, rgba(42, 68, 132, 0.35));
        margin: 10px 0;
    }
    [data-theme="light"] .pos-summary-divider {
        background: #E2E8F0;
    }

    .pos-grand-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--iw-text-muted, #9aa6d1);
    }
    [data-theme="light"] .pos-grand-label {
        color: #475569;
    }

    .pos-grand-val {
        font-size: 24px;
        font-weight: 800;
        color: #51D55A;
        font-family: 'Onest', sans-serif;
        line-height: 1.1;
    }
    .pos-accent-release .pos-grand-val {
        color: #f87171;
    }

    /* ── Collapsible Remarks ──────────────────────────────────────────────── */
    .pos-remarks-container {
        margin-top: 8px;
    }
    .pos-remarks-toggle-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12.5px;
        font-weight: 600;
        color: #9aa6d1;
        background: transparent;
        border: 1px dashed var(--iw-border, rgba(42, 68, 132, 0.45));
        border-radius: 8px;
        padding: 6px 12px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .pos-remarks-toggle-btn:hover {
        color: var(--iw-text, #E3E3E3);
        border-color: var(--iw-accent, #51D55A);
        background: rgba(81, 213, 90, 0.05);
    }
    .pos-accent-release .pos-remarks-toggle-btn:hover {
        border-color: #f87171;
        background: rgba(239, 68, 68, 0.05);
    }
    [data-theme="light"] .pos-remarks-toggle-btn {
        color: #64748B;
        border-color: #CBD5E1;
    }
    [data-theme="light"] .pos-remarks-toggle-btn:hover {
        color: #0F172A;
        border-color: #10B981;
        background: #ECFDF5;
    }
    [data-theme="light"].pos-accent-release .pos-remarks-toggle-btn:hover,
    [data-theme="light"] .pos-accent-release .pos-remarks-toggle-btn:hover {
        border-color: #EF4444;
        background: #FEF2F2;
    }
    .pos-remarks-drawer {
        margin-top: 8px;
    }

    /* ── Hero Confirm CTA in Sticky Bottom Bar ────────────────────────────── */
    .pos-sticky-bottom-bar {
        position: sticky;
        bottom: 0;
        z-index: 25;
        background: var(--iw-bg-card, #13275B);
        border-top: 1px solid var(--iw-border, rgba(42, 68, 132, 0.45));
        padding: 12px 0 0 0;
        margin-top: auto;
        flex-shrink: 0;
        box-shadow: 0 -8px 20px rgba(0, 0, 0, 0.25);
    }
    [data-theme="light"] .pos-sticky-bottom-bar {
        background: #FFFFFF;
        border-top-color: #E2E8F0;
        box-shadow: 0 -4px 14px rgba(15, 23, 42, 0.05);
    }

    .pos-confirm-btn {
        width: 100%;
        height: 52px;
        min-height: 52px;
        font-size: 15px;
        font-weight: 700;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        letter-spacing: 0.01em;
        transition: all 0.2s ease;
    }
    .pos-accent-release .pos-confirm-btn {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
    }
    .pos-accent-release .pos-confirm-btn:hover {
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.45);
    }
    .pos-confirm-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        box-shadow: none !important;
    }

    /* ── Product Info Panel ──────────────────────────────────────────────── */
    .pos-info-empty {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--iw-text-muted);
        font-size: 13px;
        padding: 32px 24px;
        min-height: 280px;
    }

    .pos-info-content { flex: 1; display: flex; flex-direction: column; gap: 12px; min-height: 0; }

    .pos-info-name {
        font-family: 'Onest', sans-serif;
        font-size: 18px;
        font-weight: 700;
        color: var(--iw-text);
        line-height: 1.3;
        text-align: left;
    }

    .pos-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    @media (max-width: 480px) { .pos-info-grid { grid-template-columns: 1fr; } }

    .pos-info-field {
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid var(--iw-border);
        background: var(--iw-bg-surface);
    }
    .pos-info-field.full-width { grid-column: 1 / -1; }
    .pos-info-field dt {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9aa6d1;
        margin-bottom: 2px;
    }
    .pos-info-field dd { font-size: 13px; font-weight: 600; color: #E3E3E3; margin: 0; word-break: break-word; }

    .pos-status-badge {
        display: inline-block;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 2px 6px;
        border-radius: 4px;
        margin-top: 6px;
    }
    .pos-status-in_stock    { background: rgba(81,213,90,0.15);  color: #51D55A; border: 1px solid rgba(81,213,90,0.25); }
    .pos-status-low_stock   { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.25); }
    .pos-status-out_of_stock{ background: rgba(239,68,68,0.12);  color: #f87171; border: 1px solid rgba(239,68,68,0.25); }

    /* ── Reference image in info panel ──────────────────────────────────── */
    .pos-info-image-section { display: flex; flex-direction: column; gap: 6px; }
    .pos-info-image-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #9aa6d1;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .pos-info-image-expand-btn {
        display: none;
        background: none;
        border: none;
        color: #9aa6d1;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        transition: color 0.15s;
    }
    .pos-info-image-expand-btn:hover { color: #E3E3E3; }
    .pos-info-image-wrap {
        width: 100%;
        height: 260px;
        min-height: unset;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--iw-border);
        background: var(--iw-bg-surface);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9aa6d1;
        font-size: 12px;
        position: relative;
        transform: translateZ(0);
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    .pos-info-image-wrap.has-image {
        border-color: rgba(81, 213, 90, 0.40);
        box-shadow: 0 0 0 1px rgba(81, 213, 90, 0.10), 0 6px 24px rgba(0, 0, 0, 0.25);
    }
    .pos-accent-release .pos-info-image-wrap.has-image {
        border-color: rgba(239, 68, 68, 0.40);
        box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.10), 0 6px 24px rgba(0, 0, 0, 0.25);
    }
    .pos-info-image-wrap img {
        width: 100%;
        height: 100%;
        min-height: unset;
        object-fit: contain;
        display: none;
        cursor: zoom-in;
        transition: transform 0.25s;
    }
    .pos-info-image-wrap img:hover { transform: scale(1.03); }
    .pos-info-image-wrap.has-image img { display: block; }
    .pos-info-image-placeholder { position: absolute; display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
    .pos-info-image-wrap.has-image .pos-info-image-placeholder { display: none; }

    /* Lightbox for full-size product image */
    .pos-img-lightbox {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(0,0,0,0.88);
        backdrop-filter: blur(6px);
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.22s;
    }
    .pos-img-lightbox.open { display: flex; opacity: 1; pointer-events: auto; }
    .pos-img-lightbox img {
        max-width: min(90vw, 900px);
        max-height: 88vh;
        object-fit: contain;
        border-radius: 12px;
        box-shadow: 0 24px 64px rgba(0,0,0,0.7);
        border: 1px solid rgba(255,255,255,0.07);
    }
    .pos-img-lightbox-close {
        position: absolute;
        top: 20px; right: 24px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.14);
        color: #fff;
        border-radius: 50%;
        width: 38px; height: 38px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        font-size: 20px;
        line-height: 1;
        transition: background 0.15s;
    }
    .pos-img-lightbox-close:hover { background: rgba(255,255,255,0.18); }

    /* ── Toast ───────────────────────────────────────────────────────────── */
    #toast-container {
        visibility: hidden;
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        padding: 13px 24px;
        border-radius: 12px;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        font-weight: 500;
        z-index: 100005;
        opacity: 0;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    #toast-container.show { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast-container.success { background-color: rgba(6,44,28,0.96); border: 1.5px solid #51D55A; color: #d1fae5; box-shadow: 0 10px 30px rgba(81, 213, 90, 0.25); }
    #toast-container.error   { background-color: rgba(45,12,18,0.96); border: 1.5px solid #ef4444; color: #fee2e2; box-shadow: 0 10px 30px rgba(239, 68, 68, 0.25); }
    [data-theme="light"] #toast-container.error { background: #fef2f2 !important; border-color: #ef4444 !important; color: #991b1b !important; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.15) !important; }
    [data-theme="light"] #toast-container.success { background: #f0fdf4 !important; border-color: #22c55e !important; color: #166534 !important; box-shadow: 0 10px 25px rgba(34, 197, 94, 0.15) !important; }

    /* Light mode typography contrast */
    [data-theme="light"] .pos-panel-header h3 { color: var(--iw-text-header, #0B1526) !important; }
    [data-theme="light"] .pos-panel-header p { color: var(--iw-text-muted, #45526B) !important; }
    [data-theme="light"] .pos-info-field dt { color: var(--iw-text-muted, #45526B) !important; }
    [data-theme="light"] .pos-info-field dd { color: var(--iw-text, #0B1526) !important; }

    /* ── Mobile Responsive POS Optimization (max-width: 767px) ────────────── */
    @media (max-width: 767px) {
        .pos-layout {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
        }

        .pos-panel {
            border-radius: 16px !important;
            overflow: visible !important;
        }

        .pos-panel-header {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 6px !important;
            padding: 14px 16px !important;
        }

        .pos-panel-header > div:first-child {
            width: 100% !important;
        }

        .pos-panel-header h3 {
            font-size: 16px !important;
            line-height: 1.3 !important;
        }

        .pos-panel-header p {
            font-size: 11.5px !important;
            line-height: 1.4 !important;
            margin-top: 2px !important;
        }

        .pos-panel-body {
            padding: 12px 14px 16px !important;
            gap: 12px !important;
            overflow-y: visible !important;
            overflow-x: hidden !important;
        }

        /* Mobile Sticky Hero Bottom Bar */
        .pos-sticky-bottom-bar {
            position: sticky !important;
            bottom: 0 !important;
            z-index: 90 !important;
            background: var(--iw-bg-surface, #0B1438) !important;
            border-top: 1px solid var(--iw-border, rgba(42, 68, 132, 0.45)) !important;
            padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0px)) !important;
            margin: 14px -14px -16px -14px !important;
            box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.35) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
        }

        [data-theme="light"] .pos-sticky-bottom-bar {
            background: rgba(255, 255, 255, 0.96) !important;
            border-top-color: #CBD5E1 !important;
            box-shadow: 0 -4px 18px rgba(15, 23, 42, 0.08) !important;
        }

        .pos-info-empty {
            min-height: 100px !important;
            padding: 20px 16px !important;
        }

        .pos-info-image-wrap {
            height: 190px !important;
        }
    }
</style>

<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>



<!-- ── Main POS Layout ───────────────────────────────────────────────────── -->
<div class="pos-layout <?php echo htmlspecialchars($accentClass); ?>">

    <!-- LEFT: Search, Camera & Transaction -->
    <div class="pos-panel">
        <div class="pos-panel-header">
            <div>
                <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                    <?php echo $txnIcon; ?> <?php echo htmlspecialchars($txnLabel); ?>
                </h3>
                <p class="text-xs text-[#9aa6d1] mt-1">Search or scan with camera to add products to the transaction.</p>
            </div>
        </div>

        <div class="pos-panel-body">
            <form method="POST" id="inv-txn-form" data-txn-mode="<?php echo htmlspecialchars($TXN_MODE); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="items_json" id="inv-items-json" value="">

                <!-- ── ZONE 1: ADD PRODUCTS (search + scanner) ───────────── -->
                <section class="pos-zone pos-zone-add" aria-label="Add Products">
                    <div class="pos-zone-header">
                        <span class="pos-zone-label">Add Products</span>
                    </div>

                    <!-- Search bar with integrated camera icon button -->
                    <div class="pos-search-wrap" id="inv-search-wrap">
                        <svg class="pos-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                        <input type="text"
                               id="inv-product-search"
                               class="pos-search-input"
                               placeholder="Search product by name or category…"
                               autocomplete="off"
                               spellcheck="false">
                        <button type="button" class="pos-search-clear" id="inv-search-clear" title="Clear search">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <button type="button" class="pos-search-camera-btn" id="inv-camera-btn" title="Toggle camera scanner" aria-label="Open camera scanner">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                            </svg>
                        </button>
                        <div class="pos-search-dropdown" id="inv-search-dropdown"></div>
                    </div>

                    <!-- Camera Scanner Viewfinder (collapsible) -->
                    <div id="inv-camera-scanner-wrap" style="display:none; border-radius:14px; overflow:hidden; border:1px solid #2A4484; background:#0B1438; position:relative; margin-top:8px;">
                        <div style="position:relative; width:100%; height:210px; background:#040714; overflow:hidden;">
                            <video id="inv-camera-video" playsinline muted autoplay style="width:100%; height:100%; object-fit:cover; display:block;"></video>
                            <canvas id="inv-camera-canvas" style="display:none;"></canvas>
                            
                            <!-- Scanning Reticle Overlay -->
                            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none;">
                                <div class="pos-scanner-reticle-box">
                                    <div class="pos-scanner-reticle-label">Point at product</div>
                                </div>
                            </div>

                            <!-- Close button on top-right -->
                            <button type="button" id="inv-camera-close-btn" title="Close scanner" style="position:absolute; top:8px; right:8px; width:28px; height:28px; border-radius:50%; background:rgba(15,23,42,0.85); border:1px solid rgba(255,255,255,0.25); color:#cbd5e1; font-size:18px; line-height:1; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:10;">&times;</button>
                        </div>

                        <!-- Status Bar -->
                        <div style="padding:10px 14px; display:flex; align-items:center; justify-content:space-between; gap:10px; background:rgba(13,22,56,0.95); font-size:12px; border-top:1px solid rgba(42,68,132,0.5);">
                            <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                                <div id="inv-camera-dot" style="width:8px; height:8px; border-radius:50%; background:#fbbf24; flex-shrink:0;"></div>
                                <span id="inv-camera-status-text" style="color:#9aa6d1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Scanning… point camera at product packaging</span>
                            </div>
                            <button type="button" id="inv-camera-snap-manual" class="tm-start-btn" style="padding:4px 12px; font-size:11px; height:28px; border-radius:6px; margin:0; flex-shrink:0;">Snap &amp; Add</button>
                        </div>
                    </div>
                </section>

                <!-- ── ZONE 2: ITEMS TO RECEIVE / RELEASE (Cart list) ────── -->
                <section class="pos-zone pos-zone-cart" aria-label="Items List">
                    <div class="pos-zone-header">
                        <div class="flex items-center gap-2">
                            <span class="pos-zone-label"><?php echo $TXN_MODE === 'in' ? 'Items to Receive' : 'Items to Release'; ?></span>
                            <span class="pos-cart-badge" id="inv-cart-badge">0</span>
                        </div>
                        <button type="button" class="pos-ghost-clear-btn" id="inv-clear-btn" style="display:none;" title="Clear all items in cart">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                            Clear Cart
                        </button>
                    </div>

                    <div class="pos-cart-wrap" id="inv-cart-wrap">
                        <div class="pos-cart-empty" id="inv-cart-empty">
                            <svg class="w-10 h-10 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                            </svg>
                            Search or scan a product to add it to this transaction
                        </div>
                        <table class="pos-cart-table" id="inv-cart-table" style="display:none;">
                            <thead>
                                <tr>
                                    <th class="pos-cart-thumb-cell"></th>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total Price</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="inv-cart-body"></tbody>
                        </table>
                    </div>
                </section>

                <!-- ── ZONE 3: SUMMARY + REMARKS ──────────────────────────── -->
                <section class="pos-zone pos-zone-summary" aria-label="Summary and Remarks">
                    <div class="pos-zone-header">
                        <span class="pos-zone-label">Transaction Summary</span>
                    </div>

                    <div class="pos-summary-card">
                        <!-- Secondary stats -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="pos-summary-metric-label">Total Products</span>
                                <span class="pos-summary-metric-val" id="inv-summary-products">0</span>
                            </div>
                            <div>
                                <span class="pos-summary-metric-label">Total Quantity</span>
                                <span class="pos-summary-metric-val" id="inv-summary-qty">0</span>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="pos-summary-divider"></div>

                        <!-- Grand Total (₱) — largest hero number in block -->
                        <div class="flex items-baseline justify-between">
                            <span class="pos-grand-label">Grand Total</span>
                            <span class="pos-grand-val" id="inv-summary-total">₱0.00</span>
                        </div>
                    </div>

                    <!-- Collapsible remarks -->
                    <div class="pos-remarks-container">
                        <button type="button" class="pos-remarks-toggle-btn" id="inv-remarks-toggle">
                            <span id="inv-remarks-toggle-icon">＋</span>
                            <span id="inv-remarks-toggle-text">Add remarks</span>
                        </button>
                        <div class="pos-remarks-drawer" id="inv-remarks-drawer" style="display:none;">
                            <input type="text"
                                   id="inv-remarks-input"
                                   name="remarks"
                                   class="premium-input w-full"
                                   placeholder="Add transaction remarks or notes (optional)…"
                                   style="height:40px; font-size:13px; border-radius:10px;">
                        </div>
                    </div>
                </section>

                <!-- ── STICKY HERO BOTTOM BAR ─────────────────────────────── -->
                <div class="pos-sticky-bottom-bar">
                    <button type="submit" class="premium-button pos-confirm-btn" id="inv-confirm-btn" data-base-label="<?php echo htmlspecialchars($confirmLabel); ?>" disabled>
                        <?php echo htmlspecialchars($confirmLabel); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Product Information -->
    <div class="pos-panel">
        <div class="pos-panel-header">
            <h3 class="font-onest font-semibold text-lg text-white">Product Information</h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Updates when a product is scanned or selected.</p>
        </div>

        <div class="pos-panel-body">
            <div class="pos-info-empty" id="inv-info-empty">
                <svg class="w-12 h-12 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                </svg>
                Search or scan a product to see its details here.
            </div>

            <div class="pos-info-content" id="inv-info-content" style="display:none;">

                <div class="flex justify-between items-start gap-4">
                    <h4 class="pos-info-name" id="inv-info-name" style="text-align:left; margin:0; flex:1;"></h4>
                    <button type="button"
                            class="text-red-500 hover:text-red-400 p-1.5 bg-red-500/10 hover:bg-red-500/20 rounded-lg cursor-pointer transition-all border border-red-500/20 flex-shrink-0"
                            id="inv-info-remove" title="Remove from Transaction"
                            style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                        </svg>
                    </button>
                </div>

                <!-- Reference image -->
                <div class="pos-info-image-section">
                    <div class="pos-info-image-label">
                        <span>Reference Photo</span>
                        <button type="button" class="pos-info-image-expand-btn" id="inv-info-image-expand" title="View full size">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
                            </svg>
                        </button>
                    </div>
                    <div class="pos-info-image-wrap" id="inv-info-image-wrap">
                        <img id="inv-info-image" src="" alt="Product reference image">
                        <div class="pos-info-image-placeholder">
                            <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2" opacity="0.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                            </svg>
                            <span style="font-size:12px; color:#9aa6d1;">No reference image</span>
                            <span style="font-size:10px; color:rgba(154,166,209,0.5);">Upload one from the Products page</span>
                        </div>
                    </div>
                </div>

                <dl class="pos-info-grid">
                    <div class="pos-info-field">
                        <dt>Category</dt>
                        <dd id="inv-info-category">—</dd>
                    </div>
                    <div class="pos-info-field">
                        <dt>Location</dt>
                        <dd id="inv-info-location">—</dd>
                    </div>
                    <div class="pos-info-field">
                        <dt>Current Stock</dt>
                        <dd id="inv-info-stock">—</dd>
                    </div>
                    <div class="pos-info-field">
                        <dt>Stock Status</dt>
                        <dd id="inv-info-status">—</dd>
                    </div>
                    <div class="pos-info-field">
                        <dt>Reorder Point</dt>
                        <dd id="inv-info-reorder">—</dd>
                    </div>
                    <div class="pos-info-field">
                        <dt>Expiration Date</dt>
                        <dd id="inv-info-expiry">—</dd>
                    </div>
                    <div class="pos-info-field">
                        <dt>Batch Number</dt>
                        <dd id="inv-info-batch">—</dd>
                    </div>
                    <?php if ($TXN_MODE === 'in'): ?>
                    <div class="pos-info-field full-width">
                        <dt>Batch Number (this receive)</dt>
                        <dd>
                            <input type="text" id="inv-receive-batch" class="premium-input w-full" style="height:38px; font-size:13px;" placeholder="Optional batch / lot number">
                        </dd>
                    </div>
                    <div class="pos-info-field full-width">
                        <dt>Manufacturing Date</dt>
                        <dd>
                            <input type="date" id="inv-receive-mfg" class="premium-input w-full" style="height:38px; font-size:13px;">
                        </dd>
                    </div>
                    <div class="pos-info-field full-width">
                        <dt>Receive Expiry Date</dt>
                        <dd>
                            <input type="date" id="inv-receive-expiry" class="premium-input w-full" style="height:38px; font-size:13px;">
                        </dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php renderLayoutContentEnd(); ?>

<!-- Image Lightbox -->
<div class="pos-img-lightbox" id="pos-img-lightbox" role="dialog" aria-modal="true" aria-label="Product image full view">
    <button class="pos-img-lightbox-close" id="pos-img-lightbox-close" title="Close">&times;</button>
    <img id="pos-img-lightbox-img" src="" alt="Product reference image full size">
</div>

<!-- IntelliWare scripts -->
<script src="src/scripts/tesseract.min.js"></script>
<script src="src/scripts/product-camera-scanner.js?v=<?php echo time(); ?>"></script>
<script src="src/scripts/inventory-transaction.js?v=<?php echo time(); ?>"></script>

<!-- TensorFlow.js + Image Similarity Scanner (MobileNetV3-Large, 1280-dim) -->
<script src="assets/vendor/tf.min.js"></script>
<script src="src/scripts/image-scanner.js?v=<?php echo time(); ?>"></script>
<script>
(function () {
    'use strict';
    var _imgScannerReady   = false;
    var _imgScannerLoading = false;

    function ensureImageScannerReady() {
        if (_imgScannerReady || _imgScannerLoading) return;
        if (typeof ImageScanner === 'undefined' || typeof tf === 'undefined') return;
        _imgScannerLoading = true;

        ImageScanner.init({
            onStatusChange: function (state, msg) {
                if (state === 'ready')  { _imgScannerReady = true; _imgScannerLoading = false; }
                if (state === 'error')  { _imgScannerLoading = false; }
            },
            onEmbeddingsReady: function (count) {
                console.info('[ImageScanner] Inventory view: fingerprints ready for', count, 'products');
            }
        });

        ImageScanner.loadModel().catch(function (e) {
            console.warn('[ImageScanner] Model load failed:', e.message || e);
            _imgScannerLoading = false;
        });
    }

    // Expose global hook so inventory-transaction.js can record successes
    window.notifyImageScannerSuccess = function (productId, score) {
        if (typeof ImageScanner !== 'undefined') {
            ImageScanner.recordSuccess(productId, score || 1.0);
        }
    };

    // Defer load by 4s — page interaction comes first
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(ensureImageScannerReady, 4000);
    });
}());
</script>


<script>
window.AUTOLOAD_PRODUCT = <?php echo json_encode($autoloadProduct); ?>;
<?php if (isset($_SESSION['toast'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    InventoryTransaction.showToast(
        <?php echo json_encode($_SESSION['toast']['message']); ?>,
        <?php echo json_encode($_SESSION['toast']['type']); ?>
    );
});
<?php unset($_SESSION['toast']); endif; ?>
</script>

<script>
/* ── Reference-image lightbox ───────────────────────────────────────── */
(function () {
    var lightbox    = document.getElementById('pos-img-lightbox');
    var lightboxImg = document.getElementById('pos-img-lightbox-img');
    var closeBtn    = document.getElementById('pos-img-lightbox-close');
    var expandBtn   = document.getElementById('inv-info-image-expand');
    var infoImg     = document.getElementById('inv-info-image');
    var imageWrap   = document.getElementById('inv-info-image-wrap');

    function openLightbox(src) {
        if (!lightbox || !src) return;
        lightboxImg.src = src;
        lightbox.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        if (!lightbox) return;
        lightbox.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* Click the image itself to open lightbox */
    if (infoImg) {
        infoImg.addEventListener('click', function () {
            if (infoImg.src && imageWrap && imageWrap.classList.contains('has-image')) {
                openLightbox(infoImg.src);
            }
        });
    }

    /* Expand button in the label row */
    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            if (infoImg && infoImg.src) openLightbox(infoImg.src);
        });
    }

    /* Close on button or backdrop click */
    if (closeBtn)  closeBtn.addEventListener('click', closeLightbox);
    if (lightbox)  lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) closeLightbox();
    });

    /* Close on Escape */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLightbox();
    });

    /* Show/hide the expand button whenever the info panel updates */
    var expandInterval = setInterval(function () {
        if (!expandBtn || !imageWrap) return;
        expandBtn.style.display = imageWrap.classList.contains('has-image') ? 'inline-flex' : 'none';
    }, 200);
}());
</script>

<?php renderLayoutEnd(); ?>
