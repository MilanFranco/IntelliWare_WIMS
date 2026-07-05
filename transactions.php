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
$tableItems = [];   
$categories = []; 

// Function to store flash messages (success/error) in the session
function setFlashMessage($type, $message) {
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
}


// Fetch unique categories for the filter dropdown
$catResult = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
while ($row = $catResult->fetch_assoc()) { $categories[] = $row['category']; }

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

<style>
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
    <div id="txn-scanner-indicator" class="hidden border border-[#2A4484] rounded-2xl bg-[#13275B]/50 p-4 mb-4 text-[#9aa6d1]">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5 text-emerald-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-[#51D55A]">Physical Scanner Detected</div>
                <div class="text-sm text-[#d1d9f0] mt-1">Handheld barcode scanner input is ready. Camera scanning remains available as well.</div>
            </div>
        </div>
    </div>
    <div class="p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <a href="receive.php" class="group flex items-center gap-4 p-4 rounded-xl bg-[#13275B]/50 border border-[#2A4484] hover:border-emerald-500/40 hover:bg-emerald-500/5 transition-all">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 group-hover:scale-105 transition-transform flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="font-onest font-semibold text-base text-white group-hover:text-emerald-400 transition-colors">Receive Inventory</div>
                    <div class="text-[#9aa6d1] text-xs mt-1">Add incoming stock</div>
                </div>
                <svg class="w-5 h-5 text-[#9aa6d1] ml-auto group-hover:text-emerald-400 transition-all flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="release.php" class="group flex items-center gap-4 p-4 rounded-xl bg-[#13275B]/50 border border-[#2A4484] hover:border-red-500/40 hover:bg-red-500/5 transition-all">
                <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-400 border border-red-500/20 group-hover:scale-105 transition-transform flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 12h-15"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="font-onest font-semibold text-base text-white group-hover:text-red-400 transition-colors">Release Inventory</div>
                    <div class="text-[#9aa6d1] text-xs mt-1">Record outgoing stock</div>
                </div>
                <svg class="w-5 h-5 text-[#9aa6d1] ml-auto group-hover:text-red-400 transition-all flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</div>

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
            <a href="?<?php echo $qs; ?>&page=<?php echo $page-1; ?>" class="btn btn-secondary px-3 py-1 text-sm">Previous</a>
        <?php endif; ?>
        
        <span class="px-3 py-1 text-[#9aa6d1] text-sm flex items-center">Page <?php echo $page; ?></span>
        
        <?php if ($page < $totalPages): ?>
            <a href="?<?php echo $qs; ?>&page=<?php echo $page+1; ?>" class="btn btn-secondary px-3 py-1 text-sm">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php renderLayoutContentEnd(); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="src/scripts/barcode-scanner.js"></script>
<script>
function showScannerIndicator() {
    var banner = document.getElementById('txn-scanner-indicator');
    if (banner) banner.classList.remove('hidden');
}

function initHidScannerIndicator() {
    var input = document.getElementById('txn-search-input');
    if (!window.BarcodeScanner || !input || typeof window.BarcodeScanner.attachHidInput !== 'function') return;

    BarcodeScanner.attachHidInput(input, function(code, inputEl) {
        showScannerIndicator();
    }, { minLength: 3, maxScanMs: 700, skipDuplicate: true });
}

$(document).ready(function() {
    initHidScannerIndicator();

    <?php if (isset($_SESSION['toast'])): ?>
    showToast(<?php echo json_encode($_SESSION['toast']['message']); ?>, <?php echo json_encode($_SESSION['toast']['type']); ?>);
    <?php unset($_SESSION['toast']); endif; ?>

    <?php if ($smartInsightActive): ?>
    showToast(<?php echo json_encode("Filtering Category '" . $filter_cat . "'"); ?>, 'success');
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
</script>

<?php renderLayoutEnd(); ?>