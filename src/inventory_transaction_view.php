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

function setInventoryFlash(string $type, string $message): void
{
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
}

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
            $path = $baseDir . $name . '.' . $ext;
            if (is_file($path)) {
                return $webBase . $name . '.' . $ext;
            }
        }
    }

    return null;
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

    /* ── Camera Scanner Zone ─────────────────────────────────────────────── */
    .pos-camera-zone { flex-shrink: 0; }

    .pos-camera-toggle-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .pos-camera-btn {
        height: 42px;
        flex: 1;
        padding: 0 14px;
        background: var(--iw-bg-btn-secondary);
        border: 1px solid var(--iw-border-input);
        border-radius: 12px;
        color: var(--iw-text);
        cursor: pointer;
        font-size: 13px;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        transition: border-color 0.2s, color 0.2s, background 0.2s;
    }
    .pos-camera-btn:hover:not(:disabled) {
        border-color: var(--iw-accent);
        color: var(--iw-accent);
        background: rgba(81, 213, 90, 0.08);
    }
    .pos-camera-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .pos-camera-btn.is-active {
        border-color: #f87171;
        color: #f87171;
        background: rgba(239, 68, 68, 0.08);
    }

    .pos-scanner-status-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 5px 11px;
        border-radius: 999px;
        white-space: nowrap;
        flex-shrink: 0;
        transition: all 0.3s;
    }
    .pos-scanner-status-badge.idle    { background: rgba(155,161,200,0.12); color: #9aa6d1; border: 1px solid rgba(155,161,200,0.24); }
    .pos-scanner-status-badge.loading { background: rgba(251,191,36,0.12); color: #fbbf24; border: 1px solid rgba(251,191,36,0.28); }
    .pos-scanner-status-badge.scanning {
        background: rgba(81,213,90,0.12);
        color: #51D55A;
        border: 1px solid rgba(81,213,90,0.28);
        animation: scanBadgePulse 1.5s ease-in-out infinite;
    }
    .pos-scanner-status-badge.error  { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.24); }
    @keyframes scanBadgePulse {
        0%,100% { opacity: 1; }
        50%      { opacity: 0.55; }
    }

    .pos-camera-wrap {
        position: relative;
        border: 1px solid var(--iw-border);
        border-radius: 12px;
        overflow: hidden;
        background: #060816;
        display: none;
    }
    .pos-camera-wrap.visible { display: block; }
    .pos-camera-wrap video {
        width: 100%;
        max-height: 210px;
        object-fit: cover;
        display: block;
    }

    /* Viewfinder overlay */
    .pos-viewfinder-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        pointer-events: none;
        padding: 10px;
        background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 55%);
    }
    .pos-viewfinder-ring {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -58%);
        width: 150px;
        height: 150px;
        border: 2px solid rgba(81, 213, 90, 0.65);
        border-radius: 18px;
        pointer-events: none;
        animation: viewRingPulse 2.2s ease-in-out infinite;
    }
    @keyframes viewRingPulse {
        0%, 100% { border-color: rgba(81,213,90,0.5); box-shadow: 0 0 0 0 rgba(81,213,90,0); }
        50%       { border-color: rgba(81,213,90,1);   box-shadow: 0 0 0 6px rgba(81,213,90,0.1); }
    }
    .pos-conf-bar-wrap { width: 100%; pointer-events: none; }
    .pos-conf-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        color: #e3e3e3;
        margin-bottom: 4px;
    }
    .pos-conf-pct { font-size: 13px; color: #51D55A; }
    .pos-conf-track {
        height: 5px;
        background: rgba(255,255,255,0.1);
        border-radius: 999px;
        overflow: hidden;
    }
    .pos-conf-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #51D55A, #a3f0a8);
        transition: width 0.3s ease, background 0.3s;
        width: 0%;
    }
    .pos-conf-fill.low    { background: linear-gradient(90deg, #f87171, #fca5a5); }
    .pos-conf-fill.medium { background: linear-gradient(90deg, #fbbf24, #fde68a); }

    .pos-camera-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 12px;
        border-top: 1px solid var(--iw-border);
        background: var(--iw-bg-panel-header);
    }
    .pos-camera-status-text {
        font-size: 12px;
        color: #9aa6d1;
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pos-camera-stop-btn {
        flex-shrink: 0;
        height: 30px;
        padding: 0 12px;
        border-radius: 7px;
        border: 1px solid rgba(239,68,68,0.4);
        background: rgba(239,68,68,0.1);
        color: #f87171;
        font-family: 'Onest', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s;
    }
    .pos-camera-stop-btn:hover { background: rgba(239,68,68,0.2); border-color: #f87171; }

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
    .pos-info-image-wrap {
        width: 100%;
        aspect-ratio: 16/9;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid var(--iw-border);
        background: var(--iw-bg-surface);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9aa6d1;
        font-size: 12px;
        position: relative;
    }
    .pos-info-image-wrap img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: none;
    }
    .pos-info-image-wrap.has-image img { display: block; }
    .pos-info-image-placeholder { position: absolute; display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .pos-info-image-wrap.has-image .pos-info-image-placeholder { display: none; }

    /* ── Scan Confirmation Modal ─────────────────────────────────────────── */
    .scan-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
        z-index: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s;
    }
    .scan-modal-backdrop.open { opacity: 1; pointer-events: all; }

    .scan-modal {
        background: var(--iw-bg-card);
        border: 1px solid var(--iw-border);
        border-radius: 20px;
        box-shadow: 0 24px 60px rgba(0,0,0,0.5);
        padding: 24px;
        width: 90%;
        max-width: 420px;
        transform: translateY(16px) scale(0.97);
        transition: transform 0.25s;
    }
    .scan-modal-backdrop.open .scan-modal { transform: translateY(0) scale(1); }

    .scan-modal-title {
        font-family: 'Onest', sans-serif;
        font-size: 16px;
        font-weight: 700;
        color: var(--iw-text);
        margin: 0 0 4px;
    }
    .scan-modal-subtitle { font-size: 12px; color: #9aa6d1; margin: 0 0 16px; }

    .scan-modal-product-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 12px;
        background: var(--iw-bg-surface);
        border: 1px solid var(--iw-border);
        margin-bottom: 12px;
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
    }
    .scan-modal-product-row:hover { border-color: #51D55A; background: rgba(81,213,90,0.06); }
    .scan-modal-product-row.selected { border-color: #51D55A; background: rgba(81,213,90,0.1); }

    .scan-modal-thumb {
        width: 52px; height: 52px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid var(--iw-border);
        background: var(--iw-bg-card);
        flex-shrink: 0;
    }
    .scan-modal-thumb-ph {
        width: 52px; height: 52px;
        border-radius: 10px;
        background: var(--iw-bg-card);
        border: 1px solid var(--iw-border);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    .scan-modal-info { flex: 1; min-width: 0; }
    .scan-modal-pname { font-size: 14px; font-weight: 700; color: #e3e3e3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .scan-modal-pcat  { font-size: 11px; color: #9aa6d1; margin-top: 2px; }
    .scan-modal-pconf { font-size: 12px; font-weight: 600; color: #51D55A; margin-top: 4px; }

    .scan-modal-actions { display: flex; gap: 8px; margin-top: 16px; }
    .scan-modal-cancel-btn {
        flex: 1;
        height: 44px;
        border-radius: 10px;
        border: 1px solid var(--iw-border-input);
        background: transparent;
        color: var(--iw-text-muted);
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: border-color 0.15s, color 0.15s;
    }
    .scan-modal-cancel-btn:hover { border-color: #f87171; color: #f87171; }

    /* ── Toast ───────────────────────────────────────────────────────────── */
    #toast-container {
        visibility: hidden;
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        padding: 12px 24px;
        border-radius: 12px;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        z-index: 2000;
        opacity: 0;
        transition: all 0.4s;
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #toast-container.show { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast-container.success { background-color: rgba(6,95,70,0.9); border-color: #34d399; color: #d1fae5; }
    #toast-container.error   { background-color: rgba(127,29,29,0.9); border-color: #f87171; color: #fee2e2; }
</style>

<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>

<!-- ── Scan Confirmation Modal ───────────────────────────────────────────── -->
<div class="scan-modal-backdrop" id="scan-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="scan-modal-title">
    <div class="scan-modal">
        <p class="scan-modal-title" id="scan-modal-title">Product Match Found</p>
        <p class="scan-modal-subtitle">Select the correct product or dismiss to search manually.</p>
        <div id="scan-modal-candidates"></div>
        <div class="scan-modal-actions">
            <button type="button" class="scan-modal-cancel-btn" id="scan-modal-cancel">Dismiss &amp; Search</button>
        </div>
    </div>
</div>

<!-- ── Main POS Layout ───────────────────────────────────────────────────── -->
<div class="pos-layout <?php echo htmlspecialchars($accentClass); ?>">

    <!-- LEFT: Search, Camera & Transaction -->
    <div class="pos-panel">
        <div class="pos-panel-header flex justify-between items-center gap-3">
            <div>
                <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                    <?php echo $txnIcon; ?> <?php echo htmlspecialchars($txnLabel); ?>
                </h3>
                <p class="text-xs text-[#9aa6d1] mt-1">Search by name, or use the AI camera to identify products automatically.</p>
            </div>
            <span class="pos-scanner-status-badge idle" id="inv-scanner-badge">AI Scanner</span>
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

                <!-- ── AI Camera Scanner ─────────────────────────────────── -->
                <div class="pos-camera-zone" id="inv-camera-zone">
                    <div class="pos-camera-toggle-row">
                        <button type="button" id="inv-camera-toggle-btn" class="pos-camera-btn" title="Start AI camera scan">
                            <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                            </svg>
                            Start AI Scanner
                        </button>
                    </div>

                    <!-- Camera feed (hidden until started) -->
                    <div id="inv-camera-wrap" class="pos-camera-wrap">
                        <video id="inv-camera-video" playsinline muted autoplay></video>
                        <div class="pos-viewfinder-overlay">
                            <div class="pos-viewfinder-ring" id="inv-viewfinder-ring"></div>
                            <div class="pos-conf-bar-wrap">
                                <div class="pos-conf-label">
                                    <span id="inv-conf-label-text">Waiting…</span>
                                    <span class="pos-conf-pct" id="inv-conf-pct">—</span>
                                </div>
                                <div class="pos-conf-track">
                                    <div class="pos-conf-fill" id="inv-conf-fill"></div>
                                </div>
                            </div>
                        </div>
                        <div class="pos-camera-footer">
                            <span class="pos-camera-status-text" id="inv-camera-status">Initializing…</span>
                            <button type="button" class="pos-camera-stop-btn" id="inv-camera-stop-btn">Stop Camera</button>
                        </div>
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
                <div class="pos-info-image-wrap" id="inv-info-image-wrap">
                    <img id="inv-info-image" src="" alt="Product reference image">
                    <div class="pos-info-image-placeholder">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2" opacity="0.4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                        </svg>
                        <span style="font-size:11px; color:#9aa6d1;">No reference image</span>
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

<!-- TensorFlow.js (required for image embeddings) -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.21.0/dist/tf.min.js"></script>

<!-- IntelliWare scripts -->
<script src="src/scripts/image-scanner.js?v=<?php echo time(); ?>"></script>
<script src="src/scripts/inventory-transaction.js?v=<?php echo time(); ?>"></script>

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

<?php renderLayoutEnd(); ?>
