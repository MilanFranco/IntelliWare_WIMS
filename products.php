<?php
session_start();

if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager', 'staff'])) { header('Location: signin.php'); exit(); }

require_once 'config/db.php';
require_once 'src/services/StockMovementService.php';
require_once 'src/services/LocationCategoryService.php';

StockMovementService::ensureBatchColumn($conn);

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

function parseNonNegativeInt($value, $default = null) {
    if ($value === null || $value === '') {
        return $default;
    }
    if (!is_numeric($value) || (int)$value != $value) {
        return false;
    }
    $intVal = (int)$value;
    return $intVal < 0 ? false : $intVal;
}

function parseNonNegativeDecimal($value, $default = null) {
    if ($value === null || $value === '') {
        return $default;
    }
    if (!is_numeric($value)) {
        return false;
    }
    $floatVal = round((float)$value, 2);
    return $floatVal < 0 ? false : $floatVal;
}

function ensurePriceColumn($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $result = $conn->query("SHOW COLUMNS FROM products LIKE 'price'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `products` ADD COLUMN `price` DECIMAL(10,2) DEFAULT 0.00 AFTER `expiry_date`");
    }
    $checked = true;
}

ensurePriceColumn($conn);

$locationCategoryMap = LocationCategoryService::buildMap($conn);

$cat_query = $conn->query("SELECT DISTINCT category FROM products WHERE category != '' ORDER BY category");
while ($row = $cat_query->fetch_assoc()) { $categories[] = $row['category']; }
$categories = array_values(array_unique(array_merge($locationCategoryMap['categories'], $categories)));
sort($categories);

$uom_query = $conn->query("SELECT DISTINCT unit FROM products WHERE unit != '' ORDER BY unit");
while ($row = $uom_query->fetch_assoc()) { $uoms[] = $row['unit']; }

$defaultUoms = ['pieces', 'packs', 'boxes', 'bottles', 'cans', 'bags', 'jars', 'tubes', 'bars', 'sets', 'units', 'kg', 'g', 'L', 'ml'];
$uoms = array_values(array_unique(array_merge($uoms, $defaultUoms)));
sort($uoms, SORT_NATURAL | SORT_FLAG_CASE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        setFlashMessage('error', 'Security token mismatch. Operation blocked.');
        header("Location: products.php?page=$page&q=$q&category=$category_param");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $product_name = trim($_POST['product_name'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $supplier = trim($_POST['supplier'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $unit = trim($_POST['unit'] ?? '');
            $quantity = parseNonNegativeInt($_POST['quantity'] ?? null);
            $reorder_point = parseNonNegativeInt($_POST['reorder_point'] ?? '10', 10);
            $price = parseNonNegativeDecimal($_POST['price'] ?? '0', 0.00);
            $exp = empty($_POST['expiry_date']) ? null : trim($_POST['expiry_date']);

            if ($barcode === '') {
                throw new Exception('Barcode is required.');
            }
            if (strlen($barcode) > 50) {
                throw new Exception('Barcode must be 50 characters or fewer.');
            }
            if ($product_name === '') {
                throw new Exception('Product name is required.');
            }
            if (strlen($product_name) > 255) {
                throw new Exception('Product name must be 255 characters or fewer.');
            }
            if ($quantity === false) {
                throw new Exception('Quantity must be a whole number of 0 or greater.');
            }
            if ($reorder_point === false) {
                throw new Exception('Reorder point must be a whole number of 0 or greater.');
            }
            if ($price === false) {
                throw new Exception('Price must be a number of 0 or greater with up to 2 decimal places.');
            }

            $changedField = ($_POST['sync_driver'] ?? 'category') === 'location' ? 'location' : 'category';
            $synced = LocationCategoryService::synchronize($category, $location, $changedField, $conn);
            $category = $synced['category'];
            $location = $synced['location'];

            $openingQty = $quantity;

            $dupStmt = $conn->prepare("SELECT product_id, product_name FROM products WHERE barcode = ?");
            $dupStmt->bind_param("s", $barcode);
            $dupStmt->execute();
            $existing = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();
            if ($existing) {
                throw new Exception("This barcode already belongs to product: " . $existing['product_name'] . " (ID " . $existing['product_id'] . ").");
            }

            $stmt = $conn->prepare("INSERT INTO products (product_name, barcode, category, supplier, location, quantity, reorder_point, unit, expiry_date, price) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)");
            $stmt->bind_param("sssssissd", $product_name, $barcode, $category, $supplier, $location, $reorder_point, $unit, $exp, $price);
            if ($stmt->execute()) {
                $newProductId = (int)$conn->insert_id;
                $stmt->close();

                if ($openingQty > 0) {
                    StockMovementService::executeMovement(
                        $conn,
                        $newProductId,
                        $openingQty,
                        'in',
                        $_SESSION['Username'],
                        'Opening stock on product create'
                    );
                }

                setFlashMessage('success', 'Product added successfully.');
            } else {
                if ($conn->errno === 1062) {
                    throw new Exception('This barcode is already registered to another product.');
                }
                throw new Exception('Could not save product. Please try again.');
            }
        }
        if ($action === 'edit') {
            $newQty = (int)$_POST['qty'];
            $reorder_point = (int)$_POST['reorder_point'];
            $price = parseNonNegativeDecimal($_POST['price'] ?? '0', 0.00);
            $id = (int)$_POST['id'];
            $barcode = trim($_POST['barcode'] ?? '');
            $barcodeVal = $barcode !== '' ? $barcode : null;
            $category = trim($_POST['category'] ?? '');
            $location = trim($_POST['location'] ?? '');

            if ($price === false) {
                throw new Exception('Price must be a number of 0 or greater with up to 2 decimal places.');
            }

            $changedField = ($_POST['sync_driver'] ?? 'category') === 'location' ? 'location' : 'category';
            $synced = LocationCategoryService::synchronize($category, $location, $changedField, $conn);
            $category = $synced['category'];
            $location = $synced['location'];

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

            $currentQtyStmt = $conn->prepare('SELECT quantity FROM products WHERE product_id = ?');
            $currentQtyStmt->bind_param('i', $id);
            $currentQtyStmt->execute();
            $currentRow = $currentQtyStmt->get_result()->fetch_assoc();
            $currentQtyStmt->close();

            if (!$currentRow) {
                throw new Exception('Product not found.');
            }

            $currentQty = (int)$currentRow['quantity'];
            $qtyDelta = $newQty - $currentQty;

            $stmt = $conn->prepare("UPDATE products SET product_name=?, barcode=?, category=?, supplier=?, location=?, reorder_point=?, unit=?, price=? WHERE product_id=?");
            $stmt->bind_param("sssssisdi", $_POST['name'], $barcodeVal, $category, $_POST['supplier'], $location, $reorder_point, $_POST['uom'], $price, $id);
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();

            if ($qtyDelta !== 0) {
                $movementType = $qtyDelta > 0 ? 'in' : 'out';
                StockMovementService::executeMovement(
                    $conn,
                    $id,
                    abs($qtyDelta),
                    $movementType,
                    $_SESSION['Username'],
                    'Manual stock adjustment from inventory edit'
                );
            }

            setFlashMessage('success', 'Product updated.');
        }
        if ($action === 'delete') {
            if ($role === 'staff') throw new Exception("Access Denied. Only Managers and Admins can delete products.");
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $_POST['id']);
            if ($stmt->execute()) setFlashMessage('success', 'Product deleted successfully.');
        }
    } catch (InvalidArgumentException $e) {
        setFlashMessage('error', $e->getMessage());
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

function renderOptions($list, $currentValue, $placeholder = 'Select') {
    $currentValue = trim((string)$currentValue);
    $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    $seen = [];

    foreach ($list as $item) {
        $item = trim((string)$item);
        if ($item === '' || isset($seen[$item])) {
            continue;
        }
        $seen[$item] = true;
        $selected = ($item === $currentValue) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($item) . '"' . $selected . '>' . htmlspecialchars($item) . '</option>';
    }

    if ($currentValue !== '' && !isset($seen[$currentValue])) {
        $html .= '<option value="' . htmlspecialchars($currentValue) . '" selected>' . htmlspecialchars($currentValue) . '</option>';
    }

    return $html;
}

$edit_inputs = [];
foreach ($items as $it) {
    $id = $it['id'];
    $edit_inputs[$id] = [
        'name' => "<input type='text' id='input-name-{$id}' class='edit-field' value='".htmlspecialchars($it['name'])."' />",
        'barcode' => "<input type='text' id='input-barcode-{$id}' class='edit-field' value='".htmlspecialchars($it['barcode'] ?? '')."' placeholder='Barcode' />",
        'category' => "<select id='input-category-{$id}' class='edit-field inventory-select'>".renderOptions($categories, $it['category'], 'Select Category')."</select>",
        'supplier' => "<input type='text' id='input-supplier-{$id}' class='edit-field' value='".htmlspecialchars($it['supplier'])."' />",
        'location' => "<input type='text' id='input-location-{$id}' class='edit-field' value='".htmlspecialchars($it['location'])."' list='location-options' />",
        'qty' => "<input type='number' id='input-qty-{$id}' class='edit-field' value='".htmlspecialchars($it['qty'])."' required />",
        'uom' => "<select id='input-uom-{$id}' class='edit-field inventory-select'>".renderOptions($uoms, $it['uom'], 'Select Unit')."</select>",
        'reorder_point' => "<input type='number' id='input-reorder_point-{$id}' class='edit-field' value='".htmlspecialchars($it['reorder_point'])."' required />",
        'price' => "<input type='number' id='input-price-{$id}' class='edit-field' value='".htmlspecialchars(number_format((float)($it['price'] ?? 0), 2, '.', ''))."' min='0' step='0.01' inputmode='decimal' required />",
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

    .inventory-text-btn {
        height: 34px;
        padding: 0 12px;
        border-radius: 10px;
        font-family: 'Onest', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .inventory-text-btn.save-btn {
        background: rgba(81, 213, 90, 0.15);
        border: 1px solid rgba(81, 213, 90, 0.4);
        color: #51D55A;
    }
    .inventory-text-btn.save-btn:hover {
        background: rgba(81, 213, 90, 0.25);
        transform: translateY(-1px);
    }
    .inventory-text-btn.cancel-btn {
        background: transparent;
        border: 1px solid rgba(100, 116, 139, 0.6);
        color: #9aa6d1;
    }
    .inventory-text-btn.cancel-btn:hover {
        border-color: #f87171;
        color: #f87171;
        background: rgba(248, 113, 113, 0.08);
    }

    .btn-confirm-barcode {
        height: 46px;
        padding: 0 16px;
        background: rgba(81, 213, 90, 0.15);
        border: 1px solid rgba(81, 213, 90, 0.4);
        border-radius: 12px;
        color: #51D55A;
        cursor: pointer;
        font-size: 13px;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .btn-confirm-barcode:hover:not(:disabled) {
        background: rgba(81, 213, 90, 0.25);
        transform: translateY(-1px);
    }
    .btn-confirm-barcode:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .form-keyboard-hint {
        font-size: 11px;
        color: #64748b;
        font-family: 'Onest', sans-serif;
        margin-top: 8px;
    }

    .add-save-field-col {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }

    .form-label--spacer {
        visibility: hidden;
    }

    #add-product-submit {
        width: 100%;
        height: 46px;
        justify-content: center;
    }

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

    select.edit-field,
    select.premium-input,
    select.inventory-select {
        appearance: auto;
        -webkit-appearance: menulist;
        cursor: pointer;
        padding-right: 28px;
        min-height: 38px;
    }

    select.edit-field:disabled,
    select.premium-input:disabled {
        cursor: not-allowed;
        opacity: 0.65;
    }

    select.edit-field option,
    select.premium-input option,
    select.inventory-select option {
        background: #1D387B;
        color: #E3E3E3;
    }

    [data-theme="light"] select.edit-field,
    [data-theme="light"] select.premium-input,
    [data-theme="light"] select.inventory-select {
        background: var(--iw-bg-input);
        color: var(--iw-text);
        border-color: var(--iw-border-input);
    }

    [data-theme="light"] select.edit-field option,
    [data-theme="light"] select.premium-input option,
    [data-theme="light"] select.inventory-select option {
        background: #ffffff;
        color: #1e293b;
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
        overflow: visible;
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
    .modal-body-scroll { overflow-y: auto; overflow-x: visible; flex: 1; max-height: min(560px, calc(100vh - 220px)); }

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
    .btn-scan:disabled { opacity: 0.45; cursor: not-allowed; }
    .btn-scan:disabled:hover { border-color: #355296; color: #fff; }

    .add-field-locked {
        background: rgba(13, 22, 56, 0.65) !important;
        color: #9aa6d1 !important;
        cursor: not-allowed;
    }
    .add-barcode-status {
        font-size: 12px;
        margin-top: 8px;
        font-family: 'Onest', sans-serif;
    }
    .add-barcode-status--pending { color: #fcd34d; }
    .add-barcode-status--ready { color: #51D55A; }
    .add-barcode-status--error { color: #f87171; }

    .add-field-error {
        color: #f87171;
        font-size: 11px;
        margin-top: 6px;
        font-family: 'Onest', sans-serif;
        line-height: 1.4;
    }
    .add-field-error[hidden] { display: none; }

    .add-input-invalid {
        border-color: #f87171 !important;
        box-shadow: 0 0 0 1px rgba(248, 113, 113, 0.35);
    }

    #add-product-submit:disabled {
        opacity: 0.65;
        cursor: not-allowed;
        transform: none;
    }

    .btn-clear-barcode {
        height: 46px;
        padding: 0 14px;
        background: transparent;
        border: 1px solid #64748b;
        border-radius: 12px;
        color: #9aa6d1;
        cursor: pointer;
        font-size: 12px;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .btn-clear-barcode:hover { border-color: #f87171; color: #f87171; }

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

    [data-theme="light"] .modal-overlay { background: var(--iw-bg-overlay) !important; }
    [data-theme="light"] .modal-box {
        background: var(--iw-bg-modal) !important;
        border-color: var(--iw-border-solid) !important;
        box-shadow: var(--iw-shadow-dropdown) !important;
    }
    [data-theme="light"] .modal-header {
        background: var(--iw-bg-panel-header) !important;
        border-bottom-color: var(--iw-border) !important;
    }
    [data-theme="light"] .modal-title h3 { color: var(--iw-text-header) !important; }
    [data-theme="light"] .modal-subtitle,
    [data-theme="light"] .form-label { color: var(--iw-text-muted) !important; }
    [data-theme="light"] .btn-scan {
        background: var(--iw-bg-btn-secondary) !important;
        border-color: var(--iw-border) !important;
        color: var(--iw-text) !important;
    }
    [data-theme="light"] #toast-container {
        background: var(--iw-bg-modal) !important;
        border-color: var(--iw-border) !important;
        box-shadow: var(--iw-shadow-dropdown) !important;
        color: var(--iw-text) !important;
    }
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
                    <button type="button" id="search-scan-barcode-btn" class="absolute right-3 top-3 text-[#9aa6d1] hover:text-[#51D55A] cursor-pointer bg-transparent border-none leading-none flex items-center justify-center" title="Scan with camera"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg></button>
                </div>
                <select name="category" class="premium-input inventory-select cursor-pointer text-sm w-full sm:w-40" style="height:46px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat) echo "<option value='".htmlspecialchars($cat)."' ".($category_param==$cat?'selected':'').">".htmlspecialchars($cat)."</option>"; ?>
                </select>
                <button type="submit" class="premium-button whitespace-nowrap h-[46px] flex items-center justify-center px-5">Search</button>
                <?php if($q||$category_param): ?>
                    <a href="products.php" class="premium-button whitespace-nowrap h-[46px] flex items-center justify-center px-4 !bg-[#2A4484] !shadow-none hover:!bg-[#355296]" title="Clear filters"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></a>
                <?php endif; ?>
            </form>
            <button type="button" class="premium-button whitespace-nowrap h-[46px] flex items-center justify-center gap-2 shrink-0" onclick="openAddModal()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Product
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="premium-table w-full text-left min-w-[1280px]">
            <thead>
                <tr>
                    <th width="8%">SKU</th> <th width="11%">Barcode</th> <th width="14%">Name</th> <th width="9%">Category</th>
                    <th width="9%">Supplier</th> <th width="8%">Location</th> <th width="6%">Qty</th>
                    <th width="7%">Price</th> <th width="6%">Unit</th> <th width="6%">Reorder</th> <th width="8%">Status</th>
                    <th width="10%" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="12" class="text-center p-12 text-[#9aa6d1]">No items found matching the current criteria.</td></tr>
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
                        <td data-field="price" data-value="<?php echo htmlspecialchars(number_format((float)($it['price'] ?? 0), 2, '.', '')); ?>">
                            <span id="display-price-<?php echo $it['id']; ?>" style="font-family:monospace;"><?php echo number_format((float)($it['price'] ?? 0), 2); ?></span>
                        </td>
                        <td data-field="uom" data-value="<?php echo htmlspecialchars($it['uom']); ?>">
                            <span id="display-uom-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['uom']); ?></span>
                        </td>
                        <td data-field="reorder_point" data-value="<?php echo htmlspecialchars($it['reorder_point']); ?>">
                            <span id="display-reorder_point-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['reorder_point']); ?></span>
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
                                <input type="hidden" name="sync_driver" value="category">
                                <?php foreach(['name','barcode','category','supplier','location','qty','price','uom','reorder_point'] as $f) echo "<input type='hidden' name='$f'>"; ?>
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
                            <div id="actions-edit-<?php echo $it['id']; ?>" style="display:none; gap:8px; justify-content:flex-end; align-items:center;">
                                <button type="button" class="inventory-text-btn save-btn" onclick="saveEdit('<?php echo $it['id']; ?>')" title="Save changes (Enter)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    Save
                                </button>
                                <button type="button" class="inventory-text-btn cancel-btn" onclick="cancelEdit('<?php echo $it['id']; ?>')" title="Cancel editing">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    Cancel
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
<?php renderLayoutShellEnd(); ?>

<div id="add-modal-overlay" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <h3>Add New Product</h3>
                <div class="modal-subtitle">Enter product details below to create a new inventory item.</div>
            </div>
            <button onclick="closeAddModal()" style="background:none; border:none; color:#9aa6d1; font-size:28px; cursor:pointer; line-height:1; padding:0;">&times;</button>
        </div>
        <form method="POST" id="add-product-form" autocomplete="off">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="sync_driver" id="add-sync-driver" value="category">
            <div class="modal-body-scroll" style="padding: 30px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div style="grid-column: span 2;">
                    <label class="form-label">Manufacturer Barcode (Product Identifier) *</label>
                    <div class="flex gap-2">
                        <input name="barcode" id="add-barcode-input" class="premium-input flex-1" style="height:46px;" placeholder="Scan or type barcode, then press Enter" autocomplete="off" required maxlength="50">
                        <button type="button" class="btn-scan" id="add-scan-barcode-btn" title="Scan barcode with camera"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg></button>
                        <button type="button" class="btn-confirm-barcode" id="add-confirm-barcode-btn">Confirm</button>
                        <button type="button" class="btn-clear-barcode" id="add-clear-barcode-btn" style="display:none;" title="Clear barcode and start over">Clear</button>
                    </div>
                    <p class="add-barcode-status add-barcode-status--pending" id="add-barcode-status">Scan or enter a barcode to unlock the remaining fields.</p>
                </div>
                <div style="grid-column: span 2;">
                    <label class="form-label">Product Name *</label>
                    <input name="product_name" id="add-product-name" class="premium-input w-full add-form-field" style="height:46px;" placeholder="e.g. Wireless Mouse" required maxlength="255" disabled>
                    <p class="add-field-error" id="add-product-name-error" hidden></p>
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <select name="category" id="add-category" class="premium-input inventory-select w-full cursor-pointer add-form-field" style="height:46px;" disabled>
                        <?php echo renderOptions($categories, '', 'Select Category'); ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Supplier</label>
                    <input name="supplier" id="add-supplier" class="premium-input w-full add-form-field" style="height:46px;" placeholder="Supplier Name" disabled>
                </div>
                <div>
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="quantity" id="add-quantity" class="premium-input w-full add-form-field" style="height:46px;" min="0" step="1" inputmode="numeric" required disabled>
                    <p class="add-field-error" id="add-quantity-error" hidden></p>
                </div>
                <div>
                    <label class="form-label">Price *</label>
                    <input type="number" name="price" id="add-price" class="premium-input w-full add-form-field" style="height:46px;" min="0" step="0.01" inputmode="decimal" value="0.00" required disabled>
                    <p class="add-field-error" id="add-price-error" hidden></p>
                </div>
                <div>
                    <label class="form-label">Location</label>
                    <input name="location" id="add-location" class="premium-input w-full add-form-field" style="height:46px;" placeholder="e.g. Shelf A1" list="location-options" disabled>
                </div>
                <div>
                    <label class="form-label">Unit (UoM)</label>
                    <select name="unit" id="add-unit" class="premium-input inventory-select w-full cursor-pointer add-form-field" style="height:46px;" disabled>
                        <?php echo renderOptions($uoms, '', 'Select Unit'); ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Reorder Point</label>
                    <input type="number" name="reorder_point" id="add-reorder-point" class="premium-input w-full add-form-field" style="height:46px;" value="10" min="0" step="1" inputmode="numeric" disabled>
                    <p class="add-field-error" id="add-reorder-point-error" hidden></p>
                </div>
                <div class="add-save-field-col">
                    <label class="form-label form-label--spacer" aria-hidden="true">Action</label>
                    <button type="submit" class="premium-button" id="add-product-submit" disabled>Save Product</button>
                </div>
            </div>
            <div style="padding: 24px 30px; border-top: 1px solid #2A4484; display: flex; justify-content: space-between; align-items: center; gap: 12px; background: rgba(13, 22, 56, 0.3); flex-shrink: 0; flex-wrap: wrap;">
                <p class="form-keyboard-hint" style="margin:0;">Enter to save · Shift+Enter for next field</p>
                <button type="button" class="premium-button !bg-transparent !shadow-none border border-[#64748b] hover:!bg-[#2A4484]/50" onclick="closeAddModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<datalist id="location-options">
    <?php
    $locationOptions = array_unique(array_values($locationCategoryMap['category_to_location']));
    sort($locationOptions);
    foreach ($locationOptions as $loc) {
        if ($loc !== '') {
            echo "<option value='" . htmlspecialchars($loc) . "'>";
        }
    }
    ?>
</datalist>

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
const locationCategoryMap = <?php echo json_encode($locationCategoryMap); ?>;

const editableFields = ['name', 'barcode', 'category', 'supplier', 'location', 'qty', 'price', 'uom', 'reorder_point'];

const ADD_FORM_FIELD_ORDER = [
    'add-product-name',
    'add-category',
    'add-supplier',
    'add-quantity',
    'add-price',
    'add-location',
    'add-unit',
    'add-reorder-point'
];

const EDIT_FIELD_ORDER = ['name', 'barcode', 'category', 'supplier', 'location', 'qty', 'price', 'uom', 'reorder_point'];

var addBarcodeLocked = false;
var addBarcodeValidating = false;
var addFormSubmitting = false;
var editRowSubmitting = {};
var locationCategorySyncLock = false;
var ADD_BARCODE_MIN_LENGTH = 3;
var ADD_SUBMIT_LABEL = 'Save Product';

function setBarcodeConfirmEnabled(enabled) {
    var confirmBtn = document.getElementById('add-confirm-barcode-btn');
    if (confirmBtn) confirmBtn.disabled = !enabled;
}

function extractLocationPrefix(location) {
    location = (location || '').trim();
    if (!location) return '';
    var zoneMatch = location.match(/^(Shelf [A-Z]|Freezer [A-Z])/i);
    if (zoneMatch) {
        return zoneMatch[1].replace(/^\w/, function (c) { return c.toUpperCase(); })
            .replace(/\s[a-z]/, function (m) { return m.toUpperCase(); });
    }
    var genericMatch = location.match(/^([A-Za-z]+(?:\s+[A-Za-z]+)?)/);
    return genericMatch ? genericMatch[1].trim() : location;
}

function syncLocationFromCategory(categoryValue, locationInput, syncDriverInput) {
    if (locationCategorySyncLock || !locationInput) return;
    var category = (categoryValue || '').trim();
    if (!category) return;
    var mapped = locationCategoryMap.category_to_location[category];
    if (!mapped) return;
    locationCategorySyncLock = true;
    locationInput.value = mapped;
    if (syncDriverInput) syncDriverInput.value = 'category';
    locationCategorySyncLock = false;
}

function syncCategoryFromLocation(locationValue, categoryInput, syncDriverInput) {
    if (locationCategorySyncLock || !categoryInput) return;
    var location = (locationValue || '').trim();
    if (!location) return;
    var prefix = extractLocationPrefix(location);
    var mapped = locationCategoryMap.location_to_category[prefix];
    if (!mapped) return;
    locationCategorySyncLock = true;
    if (categoryInput.tagName === 'SELECT') {
        var hasOption = Array.from(categoryInput.options).some(function (opt) {
            return opt.value === mapped;
        });
        if (!hasOption) {
            var option = document.createElement('option');
            option.value = mapped;
            option.textContent = mapped;
            categoryInput.appendChild(option);
        }
        categoryInput.value = mapped;
    } else {
        categoryInput.value = mapped;
    }
    if (syncDriverInput) syncDriverInput.value = 'location';
    locationCategorySyncLock = false;
}

function initLocationCategorySync() {
    var addCategory = document.getElementById('add-category');
    var addLocation = document.getElementById('add-location');
    var addSyncDriver = document.getElementById('add-sync-driver');

    addCategory?.addEventListener('change', function () {
        syncLocationFromCategory(this.value, addLocation, addSyncDriver);
    });
    addLocation?.addEventListener('change', function () {
        syncCategoryFromLocation(this.value, addCategory, addSyncDriver);
    });
    addLocation?.addEventListener('blur', function () {
        syncCategoryFromLocation(this.value, addCategory, addSyncDriver);
    });

    document.addEventListener('change', function (e) {
        var target = e.target;
        if (!target || !target.id) return;

        var categoryMatch = target.id.match(/^input-category-(\d+)$/);
        if (categoryMatch) {
            var rowId = categoryMatch[1];
            var locationInput = document.getElementById('input-location-' + rowId);
            var form = document.getElementById('edit-form-' + rowId);
            var syncDriver = form ? form.querySelector('input[name="sync_driver"]') : null;
            syncLocationFromCategory(target.value, locationInput, syncDriver);
            return;
        }

        var locationMatch = target.id.match(/^input-location-(\d+)$/);
        if (locationMatch) {
            var editRowId = locationMatch[1];
            var categoryInput = document.getElementById('input-category-' + editRowId);
            var editForm = document.getElementById('edit-form-' + editRowId);
            var editSyncDriver = editForm ? editForm.querySelector('input[name="sync_driver"]') : null;
            syncCategoryFromLocation(target.value, categoryInput, editSyncDriver);
        }
    });
}

document.addEventListener('DOMContentLoaded', initLocationCategorySync);

function getAddFormFields() {
    return document.querySelectorAll('#add-product-form .add-form-field');
}

function setAddBarcodeStatus(message, state) {
    var statusEl = document.getElementById('add-barcode-status');
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.className = 'add-barcode-status add-barcode-status--' + (state || 'pending');
}

function setAddFormFieldsEnabled(enabled) {
    getAddFormFields().forEach(function (field) {
        field.disabled = !enabled;
    });
    var submitBtn = document.getElementById('add-product-submit');
    if (submitBtn && !addFormSubmitting) {
        submitBtn.disabled = !enabled;
    }
}

function clearAddFormErrors() {
    document.querySelectorAll('#add-product-form .add-field-error').forEach(function (el) {
        el.textContent = '';
        el.hidden = true;
    });
    document.querySelectorAll('#add-product-form .add-input-invalid').forEach(function (el) {
        el.classList.remove('add-input-invalid');
    });
}

function setAddFieldError(inputId, errorId, message) {
    var input = document.getElementById(inputId);
    var errorEl = document.getElementById(errorId);
    if (input) input.classList.add('add-input-invalid');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.hidden = false;
    }
}

function clearAddFieldError(inputId, errorId) {
    var input = document.getElementById(inputId);
    var errorEl = document.getElementById(errorId);
    if (input) input.classList.remove('add-input-invalid');
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.hidden = true;
    }
}

function setAddSubmitState(submitting) {
    addFormSubmitting = submitting;
    var submitBtn = document.getElementById('add-product-submit');
    if (!submitBtn) return;

    if (submitting) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        return;
    }

    submitBtn.textContent = ADD_SUBMIT_LABEL;
    submitBtn.disabled = !addBarcodeLocked;
}

function isValidWholeNumber(value) {
    if (value === '' || value === null || value === undefined) return false;
    var num = Number(value);
    return Number.isInteger(num) && num >= 0;
}

function isValidPrice(value) {
    if (value === '' || value === null || value === undefined) return false;
    var trimmed = String(value).trim();
    if (!/^(\d+)(\.\d{1,2})?$/.test(trimmed)) return false;
    return Number(trimmed) >= 0;
}

function getAddFormFocusableFields() {
    return ADD_FORM_FIELD_ORDER
        .map(function (id) { return document.getElementById(id); })
        .filter(function (el) { return el && !el.disabled; });
}

function focusNextAddFormField(currentId) {
    var fields = getAddFormFocusableFields();
    var idx = fields.findIndex(function (el) { return el.id === currentId; });
    if (idx >= 0 && idx < fields.length - 1) {
        var next = fields[idx + 1];
        next.focus();
        if (typeof next.select === 'function') next.select();
    }
}

function focusNextEditField(rowId, currentInput) {
    var idx = EDIT_FIELD_ORDER.findIndex(function (field) {
        return currentInput && currentInput.id === 'input-' + field + '-' + rowId;
    });
    if (idx < 0 || idx >= EDIT_FIELD_ORDER.length - 1) return;
    var next = document.getElementById('input-' + EDIT_FIELD_ORDER[idx + 1] + '-' + rowId);
    if (next) {
        next.focus();
        if (typeof next.select === 'function') next.select();
    }
}

function submitAddProductForm() {
    var form = document.getElementById('add-product-form');
    if (!form || addFormSubmitting) return;
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
}

function validateAddProductForm() {
    clearAddFormErrors();
    var valid = true;
    var firstInvalid = null;

    if (!addBarcodeLocked) {
        setAddBarcodeStatus('Please scan or enter a valid barcode first.', 'error');
        valid = false;
        firstInvalid = document.getElementById('add-barcode-input');
    }

    var barcode = (document.getElementById('add-barcode-input')?.value || '').trim();
    if (barcode && barcode.length < ADD_BARCODE_MIN_LENGTH) {
        setAddBarcodeStatus('Barcode is too short. Please scan again.', 'error');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-barcode-input');
    }

    var name = (document.getElementById('add-product-name')?.value || '').trim();
    if (!name) {
        setAddFieldError('add-product-name', 'add-product-name-error', 'Product name is required.');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-product-name');
    }

    var qtyRaw = document.getElementById('add-quantity')?.value ?? '';
    if (qtyRaw === '') {
        setAddFieldError('add-quantity', 'add-quantity-error', 'Quantity is required.');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-quantity');
    } else if (!isValidWholeNumber(qtyRaw)) {
        setAddFieldError('add-quantity', 'add-quantity-error', 'Quantity must be a whole number of 0 or greater.');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-quantity');
    }

    var rpRaw = document.getElementById('add-reorder-point')?.value ?? '';
    if (rpRaw !== '' && !isValidWholeNumber(rpRaw)) {
        setAddFieldError('add-reorder-point', 'add-reorder-point-error', 'Reorder point must be a whole number of 0 or greater.');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-reorder-point');
    }

    var priceRaw = document.getElementById('add-price')?.value ?? '';
    if (priceRaw === '') {
        setAddFieldError('add-price', 'add-price-error', 'Price is required.');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-price');
    } else if (!isValidPrice(priceRaw)) {
        setAddFieldError('add-price', 'add-price-error', 'Price must be 0 or greater with up to 2 decimal places.');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-price');
    }

    if (firstInvalid) firstInvalid.focus();
    return valid;
}

function lockAddBarcodeField(locked) {
    var input = document.getElementById('add-barcode-input');
    var clearBtn = document.getElementById('add-clear-barcode-btn');
    if (!input) return;

    addBarcodeLocked = locked;
    input.readOnly = locked;
    input.classList.toggle('add-field-locked', locked);
    if (clearBtn) clearBtn.style.display = locked ? 'inline-flex' : 'none';
    setBarcodeConfirmEnabled(!locked && !addBarcodeValidating);
}

function resetAddProductForm() {
    var form = document.getElementById('add-product-form');
    if (!form) return;

    form.reset();
    addBarcodeLocked = false;
    addBarcodeValidating = false;
    addFormSubmitting = false;
    lockAddBarcodeField(false);
    clearAddFormErrors();
    setAddFormFieldsEnabled(false);
    setAddSubmitState(false);
    setAddBarcodeStatus('Scan or enter a barcode to unlock the remaining fields.', 'pending');
}

function focusAddBarcodeInput() {
    if (window.BarcodeScanner) {
        BarcodeScanner.focusHidInput('add-barcode-input');
    } else {
        var input = document.getElementById('add-barcode-input');
        if (input) setTimeout(function () { input.focus(); }, 50);
    }
}

function acceptAddBarcode(barcode) {
    var input = document.getElementById('add-barcode-input');
    if (!input) return;

    input.value = barcode;
    input.setAttribute('data-locked-value', barcode);
    lockAddBarcodeField(true);
    setAddFormFieldsEnabled(true);
    setAddBarcodeStatus('Barcode locked. Complete the product details below.', 'ready');

    var nameInput = document.getElementById('add-product-name');
    if (nameInput) setTimeout(function () { nameInput.focus(); }, 80);
}

function rejectAddBarcode(message) {
    var input = document.getElementById('add-barcode-input');
    if (input) {
        input.value = '';
        input.readOnly = false;
        input.classList.remove('add-field-locked');
    }
    addBarcodeLocked = false;
    setAddFormFieldsEnabled(false);
    setBarcodeConfirmEnabled(!addBarcodeValidating);
    setAddBarcodeStatus(message || 'Scan or enter a barcode to unlock the remaining fields.', message ? 'error' : 'pending');
    focusAddBarcodeInput();
}

function validateAddBarcode(barcode) {
    if (addBarcodeLocked || addBarcodeValidating) return;

    var code = (barcode || '').trim();
    if (!code) {
        rejectAddBarcode('Please scan or enter a barcode first.');
        return;
    }
    if (code.length < ADD_BARCODE_MIN_LENGTH) {
        rejectAddBarcode('Barcode is too short. Please scan again.');
        return;
    }

    if (!window.BarcodeScanner) {
        acceptAddBarcode(code);
        return;
    }

    addBarcodeValidating = true;
    setAddBarcodeStatus('Checking barcode…', 'pending');
    setBarcodeConfirmEnabled(false);

    BarcodeScanner.lookup(code).then(function (data) {
        addBarcodeValidating = false;
        if (data.found && data.product) {
            BarcodeScanner.showDuplicate(data.product);
            rejectAddBarcode('This barcode already exists. Scan a different barcode or click Clear.');
            return;
        }
        acceptAddBarcode(code);
    }).catch(function () {
        addBarcodeValidating = false;
        if (!navigator.onLine) {
            acceptAddBarcode(code);
            setAddBarcodeStatus('Offline — barcode not verified online. Uniqueness will be checked when you save.', 'pending');
            return;
        }
        rejectAddBarcode('Could not verify barcode. Check your connection and try again.');
    });
}

function openAddModal() {
    resetAddProductForm();
    document.body.style.overflow = 'hidden';
    document.getElementById('add-modal-overlay').classList.add('active');
    focusAddBarcodeInput();
}

function closeAddModal() {
    document.getElementById('add-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
    resetAddProductForm();
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
    if (editRowSubmitting[id]) return;

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
            if (f === 'price' && input.value !== '' && !isValidPrice(input.value)) {
                alert('Price must be 0 or greater with up to 2 decimal places.');
                input.focus();
                isValid = false;
            }
        }
    });
    
    if (isValid) {
        editRowSubmitting[id] = true;
        form.submit();
    }
}

function showToast(msg, type) {
    const t = document.getElementById("toast-container");
    const icon = document.getElementById("toast-icon");
    document.getElementById("toast-message").innerText = msg;
    t.className = 'show ' + (type === 'success' ? 'success' : 'error');

    const icons = {
        success: msg.toLowerCase().includes('deleted')
            ? '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>'
            : '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        error: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        warning: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>'
    };

    icon.innerHTML = icons[type === 'success' ? 'success' : (type === 'error' ? 'error' : 'warning')];
    setTimeout(() => t.classList.remove('show'), 4000);
}

<?php if (isset($_SESSION['toast'])): ?>
    showToast("<?php echo addslashes($_SESSION['toast']['message']); ?>", "<?php echo $_SESSION['toast']['type']; ?>");
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>

// Barcode scanner integration
function checkBarcodeDuplicate(barcode, onClear) {
    if (!barcode || !barcode.trim() || !window.BarcodeScanner) return;
    BarcodeScanner.lookup(barcode.trim()).then(function(data) {
        if (data.found && data.product) {
            BarcodeScanner.showDuplicate(data.product);
            if (typeof onClear === 'function') onClear();
        }
    });
}

document.getElementById('add-confirm-barcode-btn')?.addEventListener('click', function () {
    if (addBarcodeLocked || addBarcodeValidating) return;
    validateAddBarcode(document.getElementById('add-barcode-input')?.value || '');
});

document.getElementById('add-clear-barcode-btn')?.addEventListener('click', function () {
    resetAddProductForm();
    focusAddBarcodeInput();
});

document.getElementById('add-scan-barcode-btn')?.addEventListener('click', function () {
    if (addBarcodeLocked || addBarcodeValidating) return;
    BarcodeScanner.open(function(code) {
        validateAddBarcode(code);
    });
});

document.getElementById('add-barcode-input')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (!addBarcodeLocked) validateAddBarcode(this.value);
    }
});

document.getElementById('add-barcode-input')?.addEventListener('input', function () {
    if (!addBarcodeLocked) return;
    var lockedValue = this.getAttribute('data-locked-value') || '';
    if (this.value !== lockedValue) this.value = lockedValue;
});

document.getElementById('add-product-form')?.addEventListener('submit', function (e) {
    if (addFormSubmitting) {
        e.preventDefault();
        return;
    }

    if (!addBarcodeLocked) {
        e.preventDefault();
        validateAddBarcode(document.getElementById('add-barcode-input')?.value || '');
        return;
    }

    if (!validateAddProductForm()) {
        e.preventDefault();
        showToast('Please fix the highlighted fields before saving.', 'error');
        return;
    }

    setAddSubmitState(true);
});

[
    ['add-product-name', 'add-product-name-error'],
    ['add-quantity', 'add-quantity-error'],
    ['add-reorder-point', 'add-reorder-point-error'],
    ['add-price', 'add-price-error']
].forEach(function (pair) {
    document.getElementById(pair[0])?.addEventListener('input', function () {
        clearAddFieldError(pair[0], pair[1]);
    });
});

['add-quantity', 'add-reorder-point'].forEach(function (fieldId) {
    document.getElementById(fieldId)?.addEventListener('keydown', function (e) {
        if (e.key === '.' || e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
            e.preventDefault();
        }
    });
});

document.getElementById('add-price')?.addEventListener('keydown', function (e) {
    if (e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
        e.preventDefault();
    }
});

ADD_FORM_FIELD_ORDER.forEach(function (fieldId) {
    document.getElementById(fieldId)?.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || this.disabled) return;
        if (this.tagName === 'SELECT') {
            if (e.shiftKey) {
                e.preventDefault();
                focusNextAddFormField(fieldId);
            }
            return;
        }
        e.preventDefault();
        if (e.shiftKey) {
            focusNextAddFormField(fieldId);
            return;
        }
        submitAddProductForm();
    });
});

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || !e.target.classList.contains('edit-field')) return;

    var match = e.target.id.match(/^input-([a-z_]+)-(\d+)$/);
    if (!match) return;

    if (e.target.tagName === 'SELECT') {
        if (e.shiftKey) {
            e.preventDefault();
            focusNextEditField(match[2], e.target);
        }
        return;
    }

    e.preventDefault();
    var rowId = match[2];
    if (e.shiftKey) {
        focusNextEditField(rowId, e.target);
        return;
    }
    saveEdit(rowId);
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
            if (!BarcodeScanner.isUsbScannerConnected()) return;
            searchInput.value = code;
            searchInput.closest('form').submit();
        });
    }

    var addInput = document.getElementById('add-barcode-input');
    if (addInput) {
        BarcodeScanner.attachHidInput(addInput, function(code) {
            if (addBarcodeLocked || addBarcodeValidating) return;
            validateAddBarcode(code);
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
<?php renderLayoutFooter(); ?>