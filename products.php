<?php
session_start();

if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager', 'staff'])) { header('Location: signin.php'); exit(); }

require_once 'config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$items = [];
$categories = [];
$uoms = [];
$total_items = 0;
$items_per_page = 15; 
$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$category_param = trim($_GET['category'] ?? '');

function setFlashMessage($type, $message) {
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
}

$cat_query = $conn->query("SELECT DISTINCT category FROM products WHERE category != '' ORDER BY category");
while ($row = $cat_query->fetch_assoc()) { $categories[] = $row['category']; }

$uom_query = $conn->query("SELECT DISTINCT unit FROM products WHERE unit != '' ORDER BY unit");
while ($row = $uom_query->fetch_assoc()) { $uoms[] = $row['unit']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        setFlashMessage('error', 'Security token mismatch. Operation blocked.');
        header("Location: products.php?page=$page&q=$q&category=$category_param");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $barcode = trim($_POST['barcode'] ?? '');
            $barcodeVal = $barcode !== '' ? $barcode : null;

            // Barcode duplicate check
            if ($barcodeVal !== null) {
                $dupStmt = $conn->prepare("SELECT product_id, product_name FROM products WHERE barcode = ?");
                $dupStmt->bind_param("s", $barcodeVal);
                $dupStmt->execute();
                $existing = $dupStmt->get_result()->fetch_assoc();
                $dupStmt->close();
                if ($existing) {
                    throw new Exception("This barcode already belongs to product: " . $existing['product_name'] . " (ID " . $existing['product_id'] . ").");
                }
            }

            $stmt = $conn->prepare("INSERT INTO products (product_name, barcode, category, supplier, location, quantity, reorder_point, unit, expiry_date, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $exp = empty($_POST['expiry_date']) ? null : $_POST['expiry_date'];
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
            $stmt->bind_param("sssssiissd", $_POST['product_name'], $barcodeVal, $_POST['category'], $_POST['supplier'], $_POST['location'], $_POST['quantity'], $_POST['reorder_point'], $_POST['unit'], $exp, $price);
            if ($stmt->execute()) setFlashMessage('success', 'Product added successfully.');
            else throw new Exception($stmt->error);
        }
        if ($action === 'edit') {
            $qty = (int)$_POST['qty'];
            $reorder_point = (int)$_POST['reorder_point'];
            $id = (int)$_POST['id'];
            $barcode = trim($_POST['barcode'] ?? '');
            $barcodeVal = $barcode !== '' ? $barcode : null;

            if ($barcodeVal !== null) {
                $dupStmt = $conn->prepare("SELECT product_id, product_name FROM products WHERE barcode = ? AND product_id != ?");
                $dupStmt->bind_param("si", $barcodeVal, $id);
                $dupStmt->execute();
                $existing = $dupStmt->get_result()->fetch_assoc();
                $dupStmt->close();
                if ($existing) {
                    throw new Exception("This barcode already belongs to product: " . $existing['product_name'] . " (ID " . $existing['product_id'] . ").");
                }
            }

            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
            $stmt = $conn->prepare("UPDATE products SET product_name=?, barcode=?, category=?, supplier=?, location=?, quantity=?, reorder_point=?, unit=?, price=? WHERE product_id=?");
            $stmt->bind_param("sssssiisdi", $_POST['name'], $barcodeVal, $_POST['category'], $_POST['supplier'], $_POST['location'], $qty, $reorder_point, $_POST['uom'], $price, $id);
            if ($stmt->execute()) setFlashMessage('success', 'Product updated.');
            else throw new Exception("Update failed: " . $stmt->error);
        }
        if ($action === 'delete') {
            if ($role === 'staff') throw new Exception("Access Denied. Only Managers and Admins can delete products.");
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $_POST['id']);
            if ($stmt->execute()) setFlashMessage('success', 'Product deleted successfully.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    header("Location: products.php?page=$page&q=$q&category=$category_param");
    exit();
}

$where = []; 
$params = []; 
$types = '';

if ($q) {
    $where[] = "(product_name LIKE ? OR supplier LIKE ? OR barcode LIKE ? OR barcode = ?)";
    $term = "%$q%"; 
    array_push($params, $term, $term, $term, $q); 
    $types .= 'ssss';
}
if ($category_param) { 
    $where[] = "category = ?"; 
    $params[] = $category_param; 
    $types .= 's'; 
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products $where_sql");
$count_params = $params;
$count_types = $types;

if ($count_params) { 
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);
$offset = ($page - 1) * $items_per_page;
$count_stmt->close();

// --- 5. MAIN DATA FETCH ---
$stmt = $conn->prepare("SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, barcode, category, quantity as qty, unit as uom, supplier, location, reorder_point, price FROM products $where_sql ORDER BY product_id DESC LIMIT ? OFFSET ?");

$main_params = array_merge($params, [$items_per_page, $offset]);
$main_types = $types . 'ii';

if ($main_params) { 
    $stmt->bind_param($main_types, ...$main_params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$stmt->close();

function renderOptions($list, $currentValue) {
    $html = '';
    if (!empty($currentValue)) {
        $html .= "<option value='".htmlspecialchars($currentValue)."' selected>".htmlspecialchars($currentValue)."</option>";
    } else {
        $html .= "<option value='' selected>Select</option>";
    }
    foreach ($list as $item) {
        if ($item !== $currentValue) {
            $html .= "<option value='".htmlspecialchars($item)."'>".htmlspecialchars($item)."</option>";
        }
    }
    return $html;
}

$edit_inputs = [];
foreach ($items as $it) {
    $id = $it['id'];
    $edit_inputs[$id] = [
        'name' => "<input type='text' id='input-name-{$id}' class='edit-field' value='".htmlspecialchars($it['name'])."' />",
        'barcode' => "<input type='text' id='input-barcode-{$id}' class='edit-field' value='".htmlspecialchars($it['barcode'] ?? '')."' placeholder='Barcode' />",
        'category' => "<select id='input-category-{$id}' class='edit-field'>".renderOptions($categories, $it['category'])."</select>",
        'supplier' => "<input type='text' id='input-supplier-{$id}' class='edit-field' value='".htmlspecialchars($it['supplier'])."' />",
        'location' => "<input type='text' id='input-location-{$id}' class='edit-field' value='".htmlspecialchars($it['location'])."' />",
        'qty' => "<input type='number' id='input-qty-{$id}' class='edit-field' value='".htmlspecialchars($it['qty'])."' required />",
        'uom' => "<select id='input-uom-{$id}' class='edit-field'>".renderOptions($uoms, $it['uom'])."</select>",
        'reorder_point' => "<input type='number' id='input-reorder_point-{$id}' class='edit-field' value='".htmlspecialchars($it['reorder_point'])."' required />",
        'price' => "<input type='number' step='0.01' min='0' id='input-price-{$id}' class='edit-field' value='".htmlspecialchars($it['price'] ?? '0.00')."' placeholder='0.00' />",
    ];
}

require_once 'src/layout.php';
renderLayoutStart('Inventory', $role, $_SESSION['Username']);
?>

<style>
    /* Page-specific styles — sidebar/layout come from src/layout.php + styles.css */
    .inventory-action-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 1px solid rgba(42, 68, 132, 0.5);
        background: rgba(13, 22, 56, 0.5);
        color: #9aa6d1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .inventory-action-btn:hover { transform: translateY(-2px); }
    .inventory-action-btn.edit-btn:hover { border-color: #3b82f6; color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
    .inventory-action-btn.delete-btn:hover,
    .inventory-action-btn.cancel-btn:hover { border-color: #f87171; color: #f87171; background: rgba(248, 113, 113, 0.1); }
    .inventory-action-btn.save-btn:hover { border-color: #51D55A; color: #51D55A; background: rgba(81, 213, 90, 0.1); }

    .edit-field {
        background: #0B1438;
        border: 1px solid #51D55A;
        color: #fff;
        padding: 6px 10px;
        border-radius: 6px;
        width: 100%;
        box-sizing: border-box;
        font-family: 'Onest', sans-serif;
        font-size: 13px;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.85);
        z-index: 100001;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
        padding: 20px;
        box-sizing: border-box;
    }
    .modal-overlay.active { display: flex; }

    .modal-box {
        background: linear-gradient(170deg, #1D387B 0%, #11204a 100%);
        border: 1px solid rgba(81, 213, 90, 0.25);
        width: 100%;
        max-width: 650px;
        max-height: calc(100vh - 40px);
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin: auto;
    }
    .modal-header {
        padding: 24px 30px;
        background: rgba(13, 22, 56, 0.5);
        border-bottom: 1px solid rgba(66, 85, 135, 0.3);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .modal-title h3 {
        color: #fff;
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 6px 0;
        font-family: 'Onest', sans-serif;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal-subtitle { color: #9aa6d1; font-size: 13px; font-family: 'Onest', sans-serif; }
    .modal-body-scroll { overflow-y: auto; flex: 1; }

    .form-label {
        color: #9aa6d1;
        font-size: 12px;
        font-weight: 600;
        display: block;
        margin-bottom: 8px;
        font-family: 'Onest', sans-serif;
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
        font-size: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .btn-scan:hover { border-color: #51D55A; color: #51D55A; }

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
        z-index: 100002;
        display: flex;
        gap: 10px;
        align-items: center;
        opacity: 0;
        transition: all 0.4s;
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        background: rgba(29, 56, 123, 0.95);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    }
    #toast-container.show { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast-container.success { border-color: #34d399; }
    #toast-container.error { border-color: #f87171; background: rgba(74, 36, 61, 0.95); }

    #barcode-scanner-overlay { z-index: 100003; }
    #barcode-duplicate-overlay { z-index: 100003; }
</style>

<div id="toast-container"><span id="toast-icon"></span><span id="toast-message"></span></div>

<div class="premium-card">
    <div class="p-6 border-b border-[#2A4484]/50 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 bg-[#13275B]/30 !pb-6">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2 flex-wrap">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"></path></svg>
                Inventory List
                <span class="text-[10px] text-[#51D55A] bg-[#51D55A]/10 border border-[#51D55A]/20 px-2 py-1 rounded font-normal"><?php echo $total_items; ?> Items</span>
            </h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Manage products, barcodes, and stock levels. Handheld scanners work in search and barcode fields.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full xl:w-auto xl:items-center">
            <form method="GET" class="flex flex-col sm:flex-row gap-2 flex-1 xl:flex-initial">
                <input type="hidden" name="page" value="1">
                <div class="relative flex-1 sm:min-w-[220px]">
                    <input name="q" id="product-search-input" class="premium-input w-full pl-10 pr-10" style="height:46px;" placeholder="Search name or barcode..." value="<?php echo htmlspecialchars($q); ?>">
                    <svg class="absolute left-3 top-3.5 w-4 h-4 text-[#9aa6d1] pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <button type="button" id="search-scan-barcode-btn" class="absolute right-3 top-3 text-[#9aa6d1] hover:text-[#51D55A] cursor-pointer bg-transparent border-none text-lg leading-none" title="Scan with camera">📷</button>
                </div>
                <select name="category" class="premium-input cursor-pointer text-sm w-full sm:w-40" style="height:46px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat) echo "<option value='".htmlspecialchars($cat)."' ".($category_param==$cat?'selected':'').">".htmlspecialchars($cat)."</option>"; ?>
                </select>
                <button type="submit" class="premium-button whitespace-nowrap h-[46px] flex items-center justify-center px-5">Search</button>
                <?php if($q||$category_param): ?>
                    <a href="products.php" class="premium-button whitespace-nowrap h-[46px] flex items-center justify-center px-4 !bg-[#2A4484] !shadow-none hover:!bg-[#355296]" title="Clear filters">✕</a>
                <?php endif; ?>
            </form>
            <button type="button" class="premium-button whitespace-nowrap h-[46px] flex items-center justify-center gap-2 shrink-0" onclick="openAddModal()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Product
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="premium-table w-full text-left" style="min-width:1200px;">
            <thead>
                <tr>
                    <th width="8%">SKU</th> <th width="11%">Barcode</th> <th width="14%">Name</th> <th width="9%">Category</th>
                    <th width="9%">Supplier</th> <th width="8%">Location</th> <th width="6%">Qty</th>
                    <th width="6%">Unit</th> <th width="6%">Reorder</th> <th width="7%">Price</th> <th width="8%">Status</th>
                    <th width="8%" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="11" class="text-center p-12 text-[#9aa6d1]">No items found matching the current criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                    <?php $low = $it['qty'] <= $it['reorder_point']; ?>
                    <tr id="row-<?php echo $it['id']; ?>" class="hover:bg-[#1D387B]/50 transition-colors">
                        <td><span style="font-family:monospace; color:#9aa6d1;"><?php echo htmlspecialchars($it['sku']); ?></span></td>
                        <td data-field="barcode" data-value="<?php echo htmlspecialchars($it['barcode'] ?? ''); ?>">
                            <span id="display-barcode-<?php echo $it['id']; ?>" style="font-family:monospace; font-size:12px; color:#9aa6d1;"><?php echo htmlspecialchars($it['barcode'] ?? '—'); ?></span>
                        </td>
                        <td data-field="name" data-value="<?php echo htmlspecialchars($it['name']); ?>">
                            <span id="display-name-<?php echo $it['id']; ?>" style="font-weight:600;"><?php echo htmlspecialchars($it['name']); ?></span>
                        </td>
                        <td data-field="category" data-value="<?php echo htmlspecialchars($it['category']); ?>">
                            <span id="display-category-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['category']); ?></span>
                        </td>
                        <td data-field="supplier" data-value="<?php echo htmlspecialchars($it['supplier']); ?>">
                            <span id="display-supplier-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['supplier']); ?></span>
                        </td>
                        <td data-field="location" data-value="<?php echo htmlspecialchars($it['location']); ?>">
                            <span id="display-location-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['location']); ?></span>
                        </td>
                        <td data-field="qty" data-value="<?php echo htmlspecialchars($it['qty']); ?>">
                            <span id="display-qty-<?php echo $it['id']; ?>" style="font-weight:700;"><?php echo htmlspecialchars($it['qty']); ?></span>
                        </td>
                        <td data-field="uom" data-value="<?php echo htmlspecialchars($it['uom']); ?>">
                            <span id="display-uom-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['uom']); ?></span>
                        </td>
                        <td data-field="reorder_point" data-value="<?php echo htmlspecialchars($it['reorder_point']); ?>">
                            <span id="display-reorder_point-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['reorder_point']); ?></span>
                        </td>
                        <td data-field="price" data-value="<?php echo htmlspecialchars($it['price'] ?? '0.00'); ?>">
                            <span id="display-price-<?php echo $it['id']; ?>" style="color:#51D55A;font-weight:600;">
                                ₱<?php echo number_format((float)($it['price'] ?? 0), 2); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($low): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Low</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Good</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <form method="POST" id="edit-form-<?php echo $it['id']; ?>" style="display:none;">
                                <input type="hidden" name="action" value="edit"> 
                                <input type="hidden" name="id" value="<?php echo $it['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <?php foreach(['name','barcode','category','supplier','location','qty','uom','reorder_point','price'] as $f) echo "<input type='hidden' name='$f'>"; ?>
                            </form>

                            <div id="actions-default-<?php echo $it['id']; ?>" style="display:flex; gap:8px; justify-content:flex-end;">
                                <button type="button" class="inventory-action-btn edit-btn" onclick="toggleEdit('<?php echo $it['id']; ?>')" title="Edit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                <?php if($role!=='staff'): ?>
                                <button type="button" class="inventory-action-btn delete-btn" onclick="promptDelete('<?php echo $it['id']; ?>')" title="Delete">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div id="actions-edit-<?php echo $it['id']; ?>" style="display:none; gap:8px; justify-content:flex-end;">
                                <button type="button" class="inventory-action-btn save-btn" onclick="saveEdit('<?php echo $it['id']; ?>')" title="Save">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </button>
                                <button type="button" class="inventory-action-btn cancel-btn" onclick="cancelEdit('<?php echo $it['id']; ?>')" title="Cancel">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="p-4 border-t border-[#2A4484]/50 flex justify-center gap-2 flex-wrap">
        <?php
            $params = ['q' => $q, 'category' => $category_param];
            $qs = http_build_query(array_filter($params, function($v) { return $v !== '' && $v !== null; }));
            $qsPrefix = $qs ? $qs . '&' : '';
        ?>
        <?php if ($page > 1): ?>
            <a href="?<?php echo $qsPrefix; ?>page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-[#13275B] border border-[#2A4484] rounded text-[#E3E3E3] text-sm hover:bg-[#2A4484] no-underline">Previous</a>
        <?php endif; ?>
        <span class="px-3 py-1 text-[#9aa6d1] text-sm flex items-center">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo $qsPrefix; ?>page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-[#13275B] border border-[#2A4484] rounded text-[#E3E3E3] text-sm hover:bg-[#2A4484] no-underline">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php renderLayoutContentEnd(); ?>

<div id="add-modal-overlay" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <h3>📦 Add New Product</h3>
                <div class="modal-subtitle">Enter product details below to create a new inventory item.</div>
            </div>
            <button onclick="closeAddModal()" style="background:none; border:none; color:#9aa6d1; font-size:28px; cursor:pointer; line-height:1; padding:0;">&times;</button>
        </div>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="modal-body-scroll" style="padding: 30px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div style="grid-column: span 2;">
                    <label class="form-label">Product Name *</label>
                    <input name="product_name" class="premium-input w-full" style="height:46px;" placeholder="e.g. Wireless Mouse" required>
                </div>
                <div style="grid-column: span 2;">
                    <label class="form-label">Manufacturer Barcode (Product Identifier)</label>
                    <div class="flex gap-2">
                        <input name="barcode" id="add-barcode-input" class="premium-input flex-1" style="height:46px;" placeholder="Scan the barcode printed on product packaging or enter manually" autocomplete="off">
                        <button type="button" class="btn-scan" id="add-scan-barcode-btn" title="Scan barcode with camera">📷</button>
                    </div>
                    <p class="barcode-scan-hint" style="font-size:13px; color:#9aa6d1; margin-top:8px;">
                        <strong>How to scan:</strong> Use a USB/Bluetooth barcode scanner (just focus this field and scan), or click 📷 to scan with your webcam. The barcode uniquely identifies this product in the system.
                    </p>
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <select name="category" class="premium-input w-full cursor-pointer" style="height:46px;">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat) echo "<option value='".htmlspecialchars($cat)."'>".htmlspecialchars($cat)."</option>"; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Supplier</label>
                    <input name="supplier" class="premium-input w-full" style="height:46px;" placeholder="Supplier Name">
                </div>
                <div>
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="quantity" class="premium-input w-full" style="height:46px;" required>
                </div>
                <div>
                    <label class="form-label">Location</label>
                    <input name="location" class="premium-input w-full" style="height:46px;" placeholder="e.g. Shelf A">
                </div>
                <div>
                    <label class="form-label">Unit (UoM)</label>
                    <select name="unit" class="premium-input w-full cursor-pointer" style="height:46px;">
                        <option value="">Select Unit</option>
                        <?php foreach ($uoms as $u) echo "<option value='".htmlspecialchars($u)."'>".htmlspecialchars($u)."</option>"; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Reorder Point</label>
                    <input type="number" name="reorder_point" class="premium-input w-full" style="height:46px;" value="10">
                </div>
                <div>
                    <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Price (₱)</label>
                    <input type="number" step="0.01" min="0" name="price" class="premium-input w-full" style="height:46px;" placeholder="0.00" value="0.00">
                </div>
            </div>
            <div style="padding: 24px 30px; border-top: 1px solid #2A4484; display: flex; justify-content: flex-end; gap: 12px; background: rgba(13, 22, 56, 0.3); flex-shrink: 0;">
                <button type="button" class="premium-button !bg-transparent !shadow-none border border-[#64748b] hover:!bg-[#2A4484]/50" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="premium-button">Create Product</button>
            </div>
        </form>
    </div>
</div>

<div id="delete-modal-overlay" class="modal-overlay">
    <div class="modal-box" style="max-width: 400px; text-align: center; padding: 40px;">
        
        <div style="margin-bottom: 16px; display: flex; justify-content: center;">
            <div style="width: 80px; height: 80px; background: rgba(245, 158, 11, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
        </div>
        
        <h3 style="color:#fff; margin-bottom:10px;">Confirm Deletion</h3>
        <p style="color:#9aa6d1; margin-bottom:30px;">This action cannot be undone. Are you sure you want to delete product ID <span id="del-id-display" style="font-weight: bold; color: #f87171;"></span>?</p>
        <div style="display:flex; justify-content:center; gap:15px;">
            <button type="button" class="premium-button !bg-transparent !shadow-none border border-[#64748b]" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" id="modal-delete-form" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="modal-delete-id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" class="premium-button" id="modal-yes-btn" style="background:#ef4444 !important;">Delete</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'src/partials/barcode_scanner_modal.php'; ?>

<script>
const editInputs = <?php echo json_encode($edit_inputs); ?>;

const editableFields = ['name', 'barcode', 'category', 'supplier', 'location', 'qty', 'uom', 'reorder_point', 'price'];

function openAddModal() {
    document.body.style.overflow = 'hidden';
    document.getElementById('add-modal-overlay').classList.add('active');
    if (window.BarcodeScanner) BarcodeScanner.focusHidInput('add-barcode-input');
}
function closeAddModal() {
    document.getElementById('add-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
}

let delId = null;
function promptDelete(id) { 
    delId = id; 
    document.body.style.overflow = 'hidden';
    document.getElementById('modal-delete-id').value = id; 
    document.getElementById('del-id-display').innerText = id; 
    document.getElementById('delete-modal-overlay').classList.add('active'); 
}
function closeDeleteModal() { 
    document.getElementById('delete-modal-overlay').classList.remove('active'); 
    document.body.style.overflow = '';
    delId = null; 
}

function toggleEdit(id) {
    document.getElementById(`actions-default-${id}`).style.display = 'none';
    document.getElementById(`actions-edit-${id}`).style.display = 'flex';
    
    editableFields.forEach(field => {
        const span = document.getElementById(`display-${field}-${id}`);
        const cell = span.parentElement;
        span.style.display = 'none';
        if (!document.getElementById(`input-${field}-${id}`)) {
             cell.insertAdjacentHTML('beforeend', editInputs[id][field]);
        }
    });
}

function cancelEdit(id) {
    document.getElementById(`actions-default-${id}`).style.display = 'flex';
    document.getElementById(`actions-edit-${id}`).style.display = 'none';
    
    editableFields.forEach(f => {
        const input = document.getElementById(`input-${f}-${id}`);
        if(input) input.remove();
        
        const span = document.getElementById(`display-${f}-${id}`);
        span.style.display = '';
    });
}

function saveEdit(id) {
    const form = document.getElementById(`edit-form-${id}`);
    let isValid = true;

    editableFields.forEach(f => {
        const input = document.getElementById(`input-${f}-${id}`);
        if(input) {
            form.elements[f].value = input.value;
            if (input.required && input.value === '') {
                 alert(`The ${f} field is required.`);
                 isValid = false;
            }
        }
    });
    
    if (isValid) {
        form.submit();
    }
}

function showToast(msg, type) {
    const t = document.getElementById("toast-container");
    const icon = document.getElementById("toast-icon");
    document.getElementById("toast-message").innerText = msg;
    t.className = 'show ' + (type === 'success' ? 'success' : 'error');

    let iconSymbol = '⚠️';
    if (type === 'success') {
        iconSymbol = msg.toLowerCase().includes('deleted') ? '🗑️' : '✅';
    } else if (type === 'error') {
        iconSymbol = '❌';
    }

    icon.innerText = iconSymbol;
    setTimeout(() => t.classList.remove('show'), 4000);
}

<?php if (isset($_SESSION['toast'])): ?>
    showToast("<?php echo addslashes($_SESSION['toast']['message']); ?>", "<?php echo $_SESSION['toast']['type']; ?>");
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>

// Barcode scanner integration
function checkBarcodeDuplicate(barcode, onClear) {
    if (!barcode || !barcode.trim()) return;
    BarcodeScanner.lookup(barcode.trim()).then(function(data) {
        if (data.found && data.product) {
            BarcodeScanner.showDuplicate(data.product);
            if (typeof onClear === 'function') onClear();
        }
    });
}

document.getElementById('add-scan-barcode-btn')?.addEventListener('click', function() {
    BarcodeScanner.open(function(code) {
        var input = document.getElementById('add-barcode-input');
        input.value = code;
        checkBarcodeDuplicate(code, function() { input.value = ''; });
    });
});

document.getElementById('add-barcode-input')?.addEventListener('change', function() {
    checkBarcodeDuplicate(this.value, function() { this.value = ''; }.bind(this));
});

document.getElementById('search-scan-barcode-btn')?.addEventListener('click', function() {
    BarcodeScanner.open(function(code) {
        var input = document.getElementById('product-search-input');
        input.value = code;
        input.closest('form').submit();
    });
});

function initProductBarcodeInputs() {
    if (!window.BarcodeScanner) return;

    var searchInput = document.getElementById('product-search-input');
    if (searchInput) {
        BarcodeScanner.attachHidInput(searchInput, function(code) {
            searchInput.value = code;
            searchInput.closest('form').submit();
        });
    }

    var addInput = document.getElementById('add-barcode-input');
    if (addInput) {
        BarcodeScanner.attachHidInput(addInput, function(code) {
            addInput.value = code;
            checkBarcodeDuplicate(code, function() { addInput.value = ''; });
        });
    }
}
</script>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="src/scripts/barcode-scanner.js"></script>
<script>
if (window.BarcodeScanner) {
    BarcodeScanner.init();
    initProductBarcodeInputs();
}
</script>
<?php renderLayoutEnd(); ?>