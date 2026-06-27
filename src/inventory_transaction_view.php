<?php
/**
 * Shared inventory transaction page (Receive / Release).
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

$redirectPage = $TXN_MODE === 'in' ? 'receive.php' : 'release.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $result = StockMovementService::executeMovement(
            $conn,
            $productId,
            $qty,
            $TXN_MODE,
            $_SESSION['Username'],
            $remarks !== '' ? $remarks : null
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
    } catch (Exception $e) {
        setInventoryFlash('error', $e->getMessage());
    }

    header('Location: ' . $redirectPage);
    exit();
}

$dropdownItems = [];
$dropQuery = "SELECT product_id AS id, CONCAT('P-', LPAD(product_id, 4, '0')) AS sku,
                     product_name AS name, barcode, quantity AS qty, unit AS uom
              FROM products ORDER BY product_name";
$dropRes = $conn->query($dropQuery);
while ($r = $dropRes->fetch_assoc()) {
    $dropdownItems[] = $r;
}
$conn->close();

$txnIcon = $TXN_MODE === 'in' ? '📥' : '📤';
$txnLabel = $TXN_MODE === 'in' ? 'Receive Stock' : 'Release Stock';
$confirmLabel = $TXN_MODE === 'in' ? 'Confirm Receive' : 'Confirm Release';
$qtyLabel = $TXN_MODE === 'in' ? 'Quantity Received' : 'Quantity to Release';

require_once __DIR__ . '/layout.php';
renderLayoutStart($TXN_PAGE_TITLE, $role, $_SESSION['Username']);
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<style>
    .select2-container { width: 100% !important; max-width: 100%; }
    .select2-container--default .select2-selection--single {
        background-color: rgba(19, 39, 91, 0.8) !important;
        border: 1px solid rgba(48, 67, 116, 0.6) !important;
        border-radius: 12px !important;
        height: 46px !important;
        display: flex !important;
        align-items: center !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #E3E3E3 !important;
        padding-left: 16px !important;
        line-height: 44px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
    }
    .select2-dropdown {
        background-color: #1D387B !important;
        border: 1px solid rgba(48, 67, 116, 0.8) !important;
        border-radius: 12px !important;
        z-index: 100010 !important;
    }
    .select2-results__option { color: #E3E3E3 !important; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: rgba(81, 213, 90, 0.2) !important;
        color: #51D55A !important;
    }

    .inv-txn-card { padding: 0 !important; overflow: visible !important; }
    .inv-txn-card:hover { transform: none !important; }

    .inv-product-panel {
        display: none;
        margin-top: 16px;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid rgba(81, 213, 90, 0.25);
        background: rgba(81, 213, 90, 0.06);
    }
    .inv-product-panel.visible { display: block; }
    .inv-product-panel .inv-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .inv-product-panel .inv-detail-label {
        font-size: 11px;
        color: #9aa6d1;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }
    .inv-product-panel .inv-detail-value {
        font-size: 15px;
        color: #E3E3E3;
        font-weight: 600;
    }

    .inv-form-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        align-items: end;
    }
    @media (min-width: 768px) {
        .inv-form-row { grid-template-columns: 1fr 120px 1fr auto; }
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
        flex-shrink: 0;
    }
    .btn-scan:hover { border-color: #51D55A; color: #51D55A; }

    .inv-product-search-row { display: flex; gap: 8px; align-items: stretch; }
    .inv-select-wrap { flex: 1; min-width: 0; }

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
    }
    #toast-container.show { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast-container.success { background-color: rgba(6, 95, 70, 0.9); border-color: #34d399; color: #d1fae5; }
    #toast-container.error { background-color: rgba(127, 29, 29, 0.9); border-color: #f87171; color: #fee2e2; }
</style>

<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>

<div class="premium-card inv-txn-card mb-8">
    <div class="p-6 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
                <?php echo $txnIcon; ?> <?php echo htmlspecialchars($txnLabel); ?>
            </h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Scan barcode or search product, enter quantity, then confirm.</p>
        </div>
        <span class="text-[10px] text-[#51D55A] bg-[#51D55A]/10 border border-[#51D55A]/20 px-2 py-1 rounded hidden md:inline">
            USB / Bluetooth scanner ready
        </span>
    </div>

    <div class="p-6">
        <form method="POST" id="inv-txn-form" data-txn-mode="<?php echo htmlspecialchars($TXN_MODE); ?>">
            <input type="hidden" name="product_id" id="inv-product-id" value="">

            <div class="inv-form-row mb-4">
                <div>
                    <label class="block text-[#9aa6d1] text-xs font-semibold mb-2" for="inv-product-select">Product</label>
                    <div class="inv-product-search-row">
                        <div class="inv-select-wrap">
                            <select class="w-full" id="inv-product-select">
                                <option value="">-- Scan barcode or search product --</option>
                                <?php foreach ($dropdownItems as $it): ?>
                                    <option value="<?php echo (int)$it['id']; ?>"
                                            data-barcode="<?php echo htmlspecialchars($it['barcode'] ?? ''); ?>"
                                            data-qty="<?php echo (int)$it['qty']; ?>"
                                            data-uom="<?php echo htmlspecialchars($it['uom'] ?? ''); ?>"
                                            data-name="<?php echo htmlspecialchars($it['name']); ?>">
                                        <?php echo htmlspecialchars($it['sku'] . ' - ' . $it['name']); ?>
                                        <?php if (!empty($it['barcode'])): ?>
                                            [<?php echo htmlspecialchars($it['barcode']); ?>]
                                        <?php endif; ?>
                                        (Qty: <?php echo (int)$it['qty']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn-scan" id="inv-scan-btn" title="Scan with camera">📷</button>
                    </div>
                    <p class="barcode-scan-hint mt-2" id="inv-hid-focus-hint">Click here or scan with USB/Bluetooth scanner</p>
                </div>
            </div>

            <div class="inv-product-panel" id="inv-product-panel">
                <div class="inv-detail-grid">
                    <div>
                        <div class="inv-detail-label">Product Name</div>
                        <div class="inv-detail-value" id="inv-display-name">—</div>
                    </div>
                    <div>
                        <div class="inv-detail-label">Barcode</div>
                        <div class="inv-detail-value font-mono text-sm" id="inv-display-barcode">—</div>
                    </div>
                    <div>
                        <div class="inv-detail-label">Current Stock</div>
                        <div class="inv-detail-value" id="inv-display-stock">—</div>
                    </div>
                </div>

                <div class="inv-form-row">
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-semibold mb-2" for="inv-qty-input"><?php echo htmlspecialchars($qtyLabel); ?></label>
                        <input type="number" id="inv-qty-input" name="qty" class="premium-input w-full" value="1" min="1" required style="height:46px;">
                    </div>
                    <div>
                        <label class="block text-[#9aa6d1] text-xs font-semibold mb-2" for="inv-remarks-input">Remarks (optional)</label>
                        <input type="text" id="inv-remarks-input" name="remarks" class="premium-input w-full" placeholder="Notes..." style="height:46px;">
                    </div>
                    <div>
                        <label class="hidden md:block text-xs mb-2" style="visibility:hidden;">Action</label>
                        <button type="submit" class="premium-button w-full" style="height:46px;" id="inv-confirm-btn">
                            <?php echo htmlspecialchars($confirmLabel); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<input type="text" id="inv-hid-barcode-input" class="hid-barcode-capture" aria-hidden="true" tabindex="-1" autocomplete="off">

<?php renderLayoutContentEnd(); ?>

<?php require_once __DIR__ . '/partials/barcode_scanner_modal.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="src/scripts/barcode-scanner.js"></script>
<script src="src/scripts/inventory-transaction.js"></script>
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
