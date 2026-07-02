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
$smartInsightActive = false;

// Handle special "Smart Insight" referral (likely from a dashboard widget)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smart_insight_referral'])) {
    $search_q = ''; 
    $filter_cat = $_POST['filter_category'] ?? '';
    $smartInsightActive = true;
} else {
    // Standard search and filter inputs from GET request
    $search_q = $_GET['q'] ?? '';
    $filter_cat = $_GET['category'] ?? '';
}

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
    /* Select2 dark theme — load overrides with page styles */
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
        padding-right: 36px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        line-height: 44px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #9aa6d1 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px !important;
        right: 10px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #9aa6d1 transparent transparent transparent !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #9aa6d1 !important;
        margin-right: 28px !important;
        font-size: 16px !important;
    }
    .select2-dropdown {
        background-color: #1D387B !important;
        border: 1px solid rgba(48, 67, 116, 0.8) !important;
        border-radius: 12px !important;
        z-index: 100010 !important;
    }
    .select2-search--dropdown { padding: 8px !important; }
    .select2-search__field {
        background-color: rgba(13, 22, 56, 0.95) !important;
        color: #E3E3E3 !important;
        border: 1px solid rgba(48, 67, 116, 0.6) !important;
        border-radius: 8px !important;
        padding: 8px 12px !important;
        outline: none !important;
    }
    .select2-results__option {
        color: #E3E3E3 !important;
        padding: 10px 16px !important;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: linear-gradient(135deg, rgba(81, 213, 90, 0.2) 0%, rgba(75, 192, 82, 0.1) 100%) !important;
        color: #51D55A !important;
    }
    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: rgba(81, 213, 90, 0.15) !important;
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

    /* Toast Notification CSS */
    #toast-container { visibility: hidden; position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(20px); padding: 12px 24px; border-radius: 12px; font-family: 'Onest', sans-serif; font-size: 14px; z-index: 2000; display: flex; gap: 12px; opacity: 0; transition: all 0.4s; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); }
    #toast-container.show { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast-container.success { background-color: rgba(6, 95, 70, 0.9); border-color: #34d399; color: #d1fae5; }
    #toast-container.error { background-color: rgba(127, 29, 29, 0.9); border-color: #f87171; color: #fee2e2; }
</style>

<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>

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
        <form method="POST">
            <div class="txn-form-grid">
                <div class="txn-field">
                    <label for="txn-type-select">Transaction Type</label>
                    <select id="txn-type-select" class="premium-input w-full cursor-pointer" name="type" style="height: 46px;">
                        <option value="in" <?php echo $action==='in'?'selected':''; ?>>📥 Stock In (Restock)</option>
                        <option value="out" <?php echo $action==='out'?'selected':''; ?>>📤 Stock Out (Dispense)</option>
                    </select>
                </div>

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
                        <button type="button" class="btn-scan" id="txn-scan-barcode-btn" title="Scan with camera">📷</button>
                    </div>
                </div>

                <div class="txn-field">
                    <label for="txn-qty-input">Quantity</label>
                    <input type="number" id="txn-qty-input" class="premium-input w-full" name="qty" value="1" min="1" required style="height: 46px;">
                </div>

                <div class="txn-field txn-field-action">
                    <label class="hidden lg:block" style="visibility:hidden;margin-bottom:8px;">Action</label>
                    <button class="premium-button" type="submit">
                        <span>Process</span>
                    </button>
                </div>
            </div>

        </form>
    </div>
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
                <input type="text" name="q" id="txn-search-input" class="premium-input w-full pl-10" placeholder="Search by name or barcode..." 
                       value="<?php echo htmlspecialchars($search_q); ?>" autocomplete="off">
                <button type="button" id="txn-search-scan-btn" class="absolute right-3 top-3.5 text-[#9aa6d1] hover:text-[#51D55A] cursor-pointer bg-transparent border-none text-lg" title="Scan barcode to search">📷</button>
                <svg class="absolute left-3 top-3.5 w-4 h-4 text-[#9aa6d1]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            
            <select name="category" class="premium-input cursor-pointer text-sm w-full md:w-40" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_cat === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if($action): ?><input type="hidden" name="action" value="<?php echo $action; ?>"><?php endif; ?>
            
            <button type="submit" class="hidden">Search</button>
        </div>
    </form>

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
                <?php $isLowStock = (int)$it['qty'] <= (int)($it['reorder_point'] ?? 10); ?>
                <tr class="hover:bg-[#1D387B]/50 transition-colors">
                    <td class="font-mono text-xs text-[#9aa6d1]"><?php echo htmlspecialchars($it['sku']); ?></td>
                    <td class="font-medium text-[#E3E3E3]"><?php echo htmlspecialchars($it['name']); ?></td>
                    <td class="text-sm text-[#9aa6d1]"><?php echo htmlspecialchars($it['category'] ?? '-'); ?></td>
                    <td class="text-center">
                        <span class="font-mono font-semibold <?php echo $isLowStock ? 'text-red-400' : 'text-[#E3E3E3]'; ?>">
                            <?php echo (int)$it['qty']; ?>
                        </span>
                    </td>
                    <td class="text-sm text-[#6b7280]"><?php echo htmlspecialchars($it['uom']); ?></td>
                    <td class="text-right">
                        <?php if($isLowStock): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Low</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Good</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($tableItems)): ?>
                <tr>
                    <td colspan="6" class="text-center p-8 text-[#9aa6d1]">No products found matching your criteria.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="p-4 border-t border-[#2A4484]/50 flex justify-center gap-2">
        <?php 
            // Rebuild URL parameters for pagination links to keep search filters active
            $params = $_GET; 
            unset($params['page']);
            $qs = http_build_query($params);
        ?>
        <?php if ($page > 1): ?>
            <a href="?<?php echo $qs; ?>&page=<?php echo $page-1; ?>" class="px-3 py-1 bg-[#13275B] border border-[#2A4484] rounded text-[#E3E3E3] text-sm hover:bg-[#2A4484]">Previous</a>
        <?php endif; ?>
        
        <span class="px-3 py-1 text-[#9aa6d1] text-sm flex items-center">Page <?php echo $page; ?></span>
        
        <?php if ($page < $totalPages): ?>
            <a href="?<?php echo $qs; ?>&page=<?php echo $page+1; ?>" class="px-3 py-1 bg-[#13275B] border border-[#2A4484] rounded text-[#E3E3E3] text-sm hover:bg-[#2A4484]">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php renderLayoutContentEnd(); ?>

<?php require_once 'src/partials/barcode_scanner_modal.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="src/scripts/barcode-scanner.js"></script>
<script>
$(document).ready(function() {
    $('#productSelect').select2({
        placeholder: "-- Search or Select Product --",
        allowClear: true,
        width: '100%',
        dropdownParent: $(document.body),
        language: { noResults: function() { return "No products found."; } }
    });

    if (window.BarcodeScanner) {
        BarcodeScanner.init();
        initTransactionBarcodeInputs();
    }

    <?php if (isset($_SESSION['toast'])): ?>
    showToast(<?php echo json_encode($_SESSION['toast']['message']); ?>, <?php echo json_encode($_SESSION['toast']['type']); ?>);
    <?php unset($_SESSION['toast']); endif; ?>

    <?php if ($smartInsightActive): ?>
    showToast(<?php echo json_encode("Filtering Category '" . $filter_cat . "'"); ?>, 'success');
    <?php endif; ?>
});

function selectProductByBarcode(barcode) {
    BarcodeScanner.lookup(barcode).then(function(data) {
        if (data.found && data.product) {
            var p = data.product;
            $('#productSelect').val(p.product_id).trigger('change');
            showToast('Product found: ' + p.product_name, 'success');
        } else {
            showToast('Product not found. Please register this product first.', 'error');
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