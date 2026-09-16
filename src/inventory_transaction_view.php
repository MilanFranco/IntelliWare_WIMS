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

if (!function_exists('getProductImageUrl')) {
    function getProductImageUrl(int $productId): ?string
    {
        $baseDir = __DIR__ . '/../uploads/products/';
        $webBase = 'uploads/products/';
        $patterns = [
            (string)$productId,
            'product_' . $productId,
            'P-' . str_pad((string)$productId, 4, '0', STR_PAD_LEFT),
        ];
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        foreach ($patterns as $name) {
            foreach ($extensions as $ext) {
                if (is_file($baseDir . $name . '.' . $ext)) {
                    return $webBase . $name . '.' . $ext;
                }
            }
        }

        return null;
    }
}

$redirectPage = $TXN_MODE === 'in' ? 'receive.php' : 'release.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            $results = StockMovementService::executeBatchMovement(
                $conn,
                $normalized,
                $TXN_MODE,
                $_SESSION['Username'],
                $remarksVal
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

            $result = StockMovementService::executeMovement(
                $conn,
                $productId,
                $qty,
                $TXN_MODE,
                $_SESSION['Username'],
                $remarksVal
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
    }
    .pos-panel:hover { transform: none; }

    .pos-panel-header {
        padding: 12px 16px;
        border-bottom: 1px solid var(--iw-border);
        background: var(--iw-bg-panel-header);
        flex-shrink: 0;
    }

    .pos-panel-body {
        padding: 12px 16px;
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        gap: 10px;
        overflow-y: auto;
    }

    /* ── Search Bar ──────────────────────────────────────────────────────── */
    .pos-search-wrap {
        position: relative;
        flex-shrink: 0;
    }
    .pos-search-input {
        width: 100%;
        height: 46px;
        padding: 0 44px 0 42px;
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
        border-color: var(--iw-accent);
        box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.12);
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
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6a749a;
        cursor: pointer;
        padding: 2px;
        display: none;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: color 0.15s;
    }
    .pos-search-clear:hover { color: var(--iw-text); }
    .pos-search-clear.visible { display: flex; }

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


    /* ── Cart ────────────────────────────────────────────────────────────── */
    .pos-cart-wrap {
        flex: 1;
        min-height: 120px;
        overflow-y: auto;
        border: 1px solid var(--iw-border);
        border-radius: 12px;
        background: var(--iw-bg-surface);
    }

    .pos-cart-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        min-height: 120px;
        color: #9aa6d1;
        font-size: 13px;
        text-align: center;
        padding: 24px;
    }

    .pos-cart-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Onest', sans-serif;
        font-size: 13px;
    }
    .pos-cart-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--iw-bg-table-head);
        color: var(--iw-text);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 8px 10px;
        text-align: left;
        border-bottom: 1px solid var(--iw-border);
    }
    .pos-cart-table td {
        padding: 8px 10px;
        color: var(--iw-text);
        border-bottom: 1px solid var(--iw-border);
        vertical-align: middle;
    }
    .pos-cart-table tr:last-child td { border-bottom: none; }
    .pos-cart-table tr.pos-cart-row-flash td { animation: posRowFlash 0.6s ease; }
    @keyframes posRowFlash {
        0% { background: rgba(81, 213, 90, 0.25); }
        100% { background: transparent; }
    }

    .pos-qty-controls { display: inline-flex; align-items: center; gap: 4px; }
    .pos-qty-btn {
        width: 24px; height: 24px;
        border-radius: 6px;
        border: 1px solid var(--iw-border-input);
        background: var(--iw-bg-btn-secondary);
        color: var(--iw-text);
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: border-color 0.15s, color 0.15s;
    }
    .pos-qty-btn:hover { border-color: #51D55A; color: #51D55A; }
    .pos-qty-value { min-width: 24px; text-align: center; font-weight: 600; }

    .pos-remove-btn {
        width: 24px; height: 24px;
        border-radius: 6px;
        border: 1px solid rgba(239, 68, 68, 0.35);
        background: rgba(239, 68, 68, 0.08);
        color: #f87171;
        cursor: pointer;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
        padding: 0;
    }
    .pos-remove-btn:hover { background: rgba(239, 68, 68, 0.18); }

    /* ── Summary & Footer ────────────────────────────────────────────────── */
    .pos-summary {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 10px;
        border-radius: 12px;
        border: 1px solid var(--iw-border);
        background: var(--iw-bg-surface);
    }
    .pos-summary-item label {
        display: block;
        font-size: 10px;
        color: #9aa6d1;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }
    .pos-summary-item span { font-size: 18px; font-weight: 700; color: #E3E3E3; }
    .pos-accent-receive .pos-summary-type { color: #51D55A; }
    .pos-accent-release .pos-summary-type { color: #f87171; }

    .pos-footer-actions { display: flex; flex-direction: column; gap: 10px; flex-shrink: 0; }

    .pos-clear-btn {
        background: transparent;
        border: 1px solid var(--iw-border-input);
        color: var(--iw-text-muted);
        border-radius: 12px;
        padding: 10px 16px;
        font-family: 'Onest', sans-serif;
        font-size: 13px;
        cursor: pointer;
        transition: border-color 0.15s, color 0.15s;
    }
    .pos-clear-btn:hover { border-color: #f87171; color: #f87171; }

    .pos-confirm-btn { height: 52px; font-size: 16px; font-weight: 600; letter-spacing: 0.02em; }
    .pos-accent-release .pos-confirm-btn {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
    }
    .pos-accent-release .pos-confirm-btn:hover { box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4); }

    /* ── Product Info Panel ──────────────────────────────────────────────── */
    .pos-cart-table tr.pos-cart-row-selected td { background: rgba(81, 213, 90, 0.08); }
    .pos-cart-table tbody tr { cursor: pointer; transition: background 0.15s; }
    .pos-cart-table tbody tr:hover td { background: rgba(42, 68, 132, 0.2); }

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
        height: 260px;          /* fixed height — same for every product */
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
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    .pos-info-image-wrap.has-image {
        border-color: rgba(81, 213, 90, 0.40);
        box-shadow: 0 0 0 1px rgba(81, 213, 90, 0.10), 0 6px 24px rgba(0, 0, 0, 0.25);
    }
    .pos-info-image-wrap img {
        width: 100%;
        height: 100%;          /* fills the fixed-height container */
        min-height: unset;
        object-fit: contain;   /* letterbox — no cropping of product labels */
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
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.22s;
    }
    .pos-img-lightbox.open { opacity: 1; pointer-events: auto; }
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


    /* ── Thumbnail in cart rows ───────────────────────────────────────────── */
    .pos-cart-thumb-cell { width: 56px; padding: 5px 4px 5px 10px !important; }
    .pos-cart-thumb {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid var(--iw-border);
        display: block;
        background: var(--iw-bg-surface);
        flex-shrink: 0;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .pos-cart-thumb:hover {
        transform: scale(1.08);
        box-shadow: 0 4px 14px rgba(0,0,0,0.35);
        z-index: 10;
        position: relative;
    }
    .pos-cart-thumb-ph {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        border: 1px dashed rgba(155,161,200,0.25);
        background: var(--iw-bg-surface);
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(155,161,200,0.35);
        font-size: 20px;
        flex-shrink: 0;
    }


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
</style>

<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>



<!-- ── Main POS Layout ───────────────────────────────────────────────────── -->
<div class="pos-layout <?php echo htmlspecialchars($accentClass); ?>">

    <!-- LEFT: Search, Camera & Transaction -->
    <div class="pos-panel">
        <div class="pos-panel-header flex justify-between items-center gap-3">
            <div>
                <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                    <?php echo $txnIcon; ?> <?php echo htmlspecialchars($txnLabel); ?>
                </h3>
                <p class="text-xs text-[#9aa6d1] mt-1">Search or scan with phone camera to add products to the transaction.</p>
            </div>
            <button type="button" id="inv-camera-btn" class="tm-start-btn" style="height:36px; padding:0 14px; display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:600; border-radius:10px; margin:0; flex-shrink:0;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                </svg>
                <span id="inv-camera-btn-text">Camera Scanner</span>
            </button>
        </div>

        <div class="pos-panel-body">
            <form method="POST" id="inv-txn-form" data-txn-mode="<?php echo htmlspecialchars($TXN_MODE); ?>">
                <input type="hidden" name="items_json" id="inv-items-json" value="">

                <!-- ── Product Search Bar ────────────────────────────────── -->
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
                    <div class="pos-search-dropdown" id="inv-search-dropdown"></div>
                </div>

                <!-- ── Camera Scanner Viewfinder (collapsible) ────────── -->
                <div id="inv-camera-scanner-wrap" style="display:none; margin-top:12px; border-radius:14px; overflow:hidden; border:1px solid #2A4484; background:#0B1438; position:relative;">
                    <div style="position:relative; width:100%; height:210px; background:#040714; overflow:hidden;">
                        <video id="inv-camera-video" playsinline muted autoplay style="width:100%; height:100%; object-fit:cover; display:block;"></video>
                        <canvas id="inv-camera-canvas" style="display:none;"></canvas>
                        
                        <!-- Scanning Reticle Overlay -->
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none;">
                            <div style="width:170px; height:130px; border:2px dashed #51D55A; border-radius:12px; box-shadow:0 0 0 9999px rgba(4,7,20,0.5); position:relative;">
                                <div style="position:absolute; top:-22px; left:0; right:0; text-align:center; color:#51D55A; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Point at product</div>
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

                <!-- ── Cart / Transaction List ───────────────────────────── -->
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
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total Price</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="inv-cart-body"></tbody>
                    </table>
                </div>

                <!-- ── Summary ───────────────────────────────────────────── -->
                <div class="pos-summary">
                    <div class="pos-summary-item">
                        <label>Total Products</label>
                        <span id="inv-summary-products">0</span>
                    </div>
                    <div class="pos-summary-item">
                        <label>Total Quantity</label>
                        <span id="inv-summary-qty">0</span>
                    </div>
                </div>

                <!-- ── Footer Actions ────────────────────────────────────── -->
                <div class="pos-footer-actions">
                    <input type="text" id="inv-remarks-input" name="remarks" class="premium-input w-full" placeholder="Remarks (optional)" style="height:42px; font-size:13px;">
                    <div class="flex gap-2">
                        <button type="button" class="pos-clear-btn flex-1" id="inv-clear-btn">Clear Transaction</button>
                        <button type="submit" class="premium-button pos-confirm-btn flex-[2]" id="inv-confirm-btn" disabled>
                            <?php echo htmlspecialchars($confirmLabel); ?>
                        </button>
                    </div>
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
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js"></script>
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
