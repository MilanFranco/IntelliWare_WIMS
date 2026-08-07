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
            $barcode      = trim($_POST['barcode'] ?? '');
            $category     = trim($_POST['category'] ?? '');
            $supplier     = trim($_POST['supplier'] ?? '');
            $location     = trim($_POST['location'] ?? '');
            $unit         = trim($_POST['unit'] ?? '');
            $quantity     = parseNonNegativeInt($_POST['quantity'] ?? null);
            $reorder_point= parseNonNegativeInt($_POST['reorder_point'] ?? '10', 10);
            $price        = parseNonNegativeDecimal($_POST['price'] ?? '0', 0.00);
            $shelf_life   = empty($_POST['shelf_life']) ? null : parseNonNegativeInt($_POST['shelf_life']);
            $ai_class     = trim($_POST['ai_class_label'] ?? '');
            $product_image_b64 = trim($_POST['product_image_b64'] ?? '');

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
            if ($shelf_life === false) {
                throw new Exception('Shelf life must be a positive number of days or empty.');
            }

            $changedField = ($_POST['sync_driver'] ?? 'category') === 'location' ? 'location' : 'category';
            $synced   = LocationCategoryService::synchronize($category, $location, $changedField, $conn);
            $category = $synced['category'];
            $location = $synced['location'];

            // Use AI class label as barcode if no manual barcode provided
            if ($barcode === '' && $ai_class !== '') {
                $barcode = $ai_class;
            }

            $openingQty = $quantity;

            // Check barcode duplicate only if barcode provided
            if ($barcode !== '') {
                $dupStmt = $conn->prepare("SELECT product_id, product_name FROM products WHERE barcode = ?");
                $dupStmt->bind_param("s", $barcode);
                $dupStmt->execute();
                $existing = $dupStmt->get_result()->fetch_assoc();
                $dupStmt->close();
                if ($existing) {
                    throw new Exception("This barcode is already registered to: " . $existing['product_name'] . " (ID " . $existing['product_id'] . ").");
                }
            }

            $barcodeVal = $barcode !== '' ? $barcode : null;
            $stmt = $conn->prepare("INSERT INTO products (product_name, barcode, category, supplier, location, quantity, reorder_point, unit, price, shelf_life) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)");
            $stmt->bind_param("sssssisdi", $product_name, $barcodeVal, $category, $supplier, $location, $reorder_point, $unit, $price, $shelf_life);
            if ($stmt->execute()) {
                $newProductId = (int)$conn->insert_id;
                $stmt->close();

                // Save captured product image if provided
                if ($product_image_b64 !== '') {
                    $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $product_image_b64));
                    if ($imgData !== false) {
                        $imgDir = __DIR__ . '/uploads/products/';
                        if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
                        file_put_contents($imgDir . $newProductId . '.jpg', $imgData);
                    }
                }

                if ($openingQty > 0) {
                    StockMovementService::executeMovement(
                        $conn, $newProductId, $openingQty, 'in',
                        $_SESSION['Username'], 'Opening stock on product create'
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
            $shelf_life = empty($_POST['shelf_life']) ? null : parseNonNegativeInt($_POST['shelf_life']);
            $id = (int)$_POST['id'];
            $barcode = trim($_POST['barcode'] ?? '');
            $barcodeVal = $barcode !== '' ? $barcode : null;
            $category = trim($_POST['category'] ?? '');
            $location = trim($_POST['location'] ?? '');

            if ($price === false) {
                throw new Exception('Price must be a number of 0 or greater with up to 2 decimal places.');
            }
            if ($shelf_life === false) {
                throw new Exception('Shelf life must be a positive number of days or empty.');
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

            $stmt = $conn->prepare("UPDATE products SET product_name=?, barcode=?, category=?, supplier=?, location=?, reorder_point=?, unit=?, price=?, shelf_life=? WHERE product_id=?");
            $stmt->bind_param("sssssisdii", $_POST['name'], $barcodeVal, $category, $_POST['supplier'], $location, $reorder_point, $_POST['uom'], $price, $shelf_life, $id);
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
$stmt = $conn->prepare("SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, barcode, category, quantity as qty, unit as uom, supplier, location, reorder_point, price, shelf_life FROM products $where_sql ORDER BY product_id DESC LIMIT ? OFFSET ?");

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
        'shelf_life' => "<input type='number' id='input-shelf_life-{$id}' class='edit-field' value='".htmlspecialchars($it['shelf_life'] ?? '')."' min='0' step='1' inputmode='numeric' placeholder='e.g. 30' />",
    ];
}

require_once 'src/layout.php';
renderLayoutStart('Inventory', $role, $_SESSION['Username']);
?>

<style>
    /* ── AI Scanner classes — same as inventory_transaction_view.php ─── */
    .pos-scanner-zone { flex-shrink: 0; }
    .tm-category-row { display: flex; gap: 8px; align-items: stretch; }
    .tm-start-btn {
        height: 46px; min-width: 46px; padding: 0 14px;
        background: var(--iw-bg-btn-secondary, rgba(255,255,255,0.05));
        border: 1px solid var(--iw-border-input, #2A4484);
        border-radius: 12px; color: var(--iw-text, #c8d4f5);
        cursor: pointer; font-size: 14px; font-family: 'Onest', sans-serif;
        font-weight: 600; display: inline-flex; align-items: center;
        justify-content: center; gap: 6px; flex-shrink: 0;
        transition: border-color 0.2s, color 0.2s, background 0.2s;
    }
    .tm-start-btn:hover:not(:disabled) { border-color: #51D55A; color: #51D55A; background: rgba(81,213,90,0.08); }
    .tm-start-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .tm-camera-wrap {
        position: relative; border: 1px solid var(--iw-border, #1E2D5B);
        border-radius: 12px; overflow: hidden; background: #0a0c1a;
    }
    .tm-camera-wrap video { width: 100%; max-height: 220px; object-fit: cover; display: block; }
    .tm-camera-overlay {
        position: absolute; inset: 0; display: flex; flex-direction: column;
        align-items: center; justify-content: flex-end; pointer-events: none;
        padding: 10px; background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 60%);
    }
    .tm-viewfinder-ring {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -60%);
        width: 160px; height: 160px; border: 2px solid rgba(81,213,90,0.6);
        border-radius: 16px; animation: tmRingPulse 2s ease-in-out infinite;
        pointer-events: none;
    }
    @keyframes tmRingPulse {
        0%, 100% { border-color: rgba(81,213,90,0.5); }
        50%       { border-color: rgba(81,213,90,0.9); }
    }
    .tm-conf-bar-wrap { width: 100%; pointer-events: none; }
    .tm-conf-label {
        display: flex; justify-content: space-between; align-items: center;
        font-size: 11px; font-family: 'Onest', sans-serif; font-weight: 600;
        color: #e3e3e3; margin-bottom: 4px;
    }
    .tm-conf-pct { font-size: 13px; color: #51D55A; }
    .tm-conf-track { height: 6px; background: rgba(255,255,255,0.1); border-radius: 999px; overflow: hidden; }
    .tm-conf-fill {
        height: 100%; border-radius: 999px;
        background: linear-gradient(90deg, #51D55A, #a3f0a8);
        transition: width 0.25s ease, background 0.25s; width: 0%;
    }
    .tm-conf-fill.is-low    { background: linear-gradient(90deg, #f87171, #fca5a5); }
    .tm-conf-fill.is-medium { background: linear-gradient(90deg, #fbbf24, #fde68a); }
    .tm-stop-btn {
        flex-shrink: 0; height: 32px; padding: 0 14px; border-radius: 8px;
        border: 1px solid rgba(239,68,68,0.4); background: rgba(239,68,68,0.1);
        color: #f87171; font-family: 'Onest', sans-serif; font-size: 12px;
        font-weight: 600; cursor: pointer; transition: background 0.15s, border-color 0.15s;
    }
    .tm-stop-btn:hover { background: rgba(239,68,68,0.2); border-color: #f87171; }

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
                    <th width="7%">SKU</th> <th width="10%">Barcode</th> <th width="13%">Name</th> <th width="8%">Category</th>
                    <th width="8%">Supplier</th> <th width="8%">Location</th> <th width="5%">Qty</th>
                    <th width="6%">Price</th> <th width="5%">Unit</th> <th width="6%">Reorder</th> <th width="8%">Shelf Life</th> <th width="6%">Status</th>
                    <th width="10%" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="13" class="text-center p-12 text-[#9aa6d1]">No items found matching the current criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                    <?php $low = $it['qty'] <= $it['reorder_point']; ?>
                    <tr id="row-<?php echo $it['id']; ?>"
                        class="hover:bg-[#1D387B]/50 transition-colors"
                        data-id="<?php echo $it['id']; ?>"
                        data-name="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>"
                        data-barcode="<?php echo htmlspecialchars($it['barcode'] ?? '', ENT_QUOTES); ?>"
                        data-category="<?php echo htmlspecialchars($it['category'], ENT_QUOTES); ?>"
                        data-supplier="<?php echo htmlspecialchars($it['supplier'], ENT_QUOTES); ?>"
                        data-location="<?php echo htmlspecialchars($it['location'], ENT_QUOTES); ?>"
                        data-qty="<?php echo (int)$it['qty']; ?>"
                        data-price="<?php echo number_format((float)($it['price'] ?? 0), 2, '.', ''); ?>"
                        data-uom="<?php echo htmlspecialchars($it['uom'], ENT_QUOTES); ?>"
                        data-reorder="<?php echo (int)$it['reorder_point']; ?>"
                        data-shelf="<?php echo htmlspecialchars($it['shelf_life'] ?? '', ENT_QUOTES); ?>"
                        data-sku="<?php echo htmlspecialchars($it['sku'], ENT_QUOTES); ?>">
                        <td><span style="font-family:monospace; color:#9aa6d1;"><?php echo htmlspecialchars($it['sku']); ?></span></td>
                        <td><span style="font-family:monospace; font-size:12px; color:#9aa6d1;"><?php echo htmlspecialchars($it['barcode'] ?? '—'); ?></span></td>
                        <td><span style="font-weight:600;"><?php echo htmlspecialchars($it['name']); ?></span></td>
                        <td><?php echo htmlspecialchars($it['category']); ?></td>
                        <td><?php echo htmlspecialchars($it['supplier']); ?></td>
                        <td><?php echo htmlspecialchars($it['location']); ?></td>
                        <td><span style="font-weight:700;"><?php echo htmlspecialchars($it['qty']); ?></span></td>
                        <td><span style="font-family:monospace;"><?php echo number_format((float)($it['price'] ?? 0), 2); ?></span></td>
                        <td><?php echo htmlspecialchars($it['uom']); ?></td>
                        <td><?php echo htmlspecialchars($it['reorder_point']); ?></td>
                        <td><?php echo $it['shelf_life'] !== null ? htmlspecialchars($it['shelf_life']) . ' days' : '—'; ?></td>
                        <td>
                            <?php if ((int)$it['qty'] <= 0): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Out</span>
                            <?php elseif ((int)$it['qty'] <= (int)$it['reorder_point']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">Low</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Good</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <div style="display:flex; gap:8px; justify-content:flex-end;">
                                <button type="button" class="inventory-action-btn edit-btn" onclick="openEditModal(this.closest('tr').dataset)" title="Edit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                <?php if($role!=='staff'): ?>
                                <button type="button" class="inventory-action-btn delete-btn" onclick="promptDelete('<?php echo $it['id']; ?>')" title="Delete">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                                <?php endif; ?>
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
                    <!-- Hidden fields for AI result -->
                    <input type="hidden" name="ai_class_label" id="add-ai-class-label" value="">
                    <input type="hidden" name="product_image_b64" id="add-product-image-b64" value="">
                    <input type="hidden" name="barcode" id="add-barcode-hidden" value="">

                    <!-- AI Camera Identification — uses system scanner classes for consistency -->
                    <div class="pos-scanner-zone" id="ai-capture-block">

                        <!-- Status bar + Scan button -->
                        <div class="tm-category-row" style="align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                            <div style="display:flex; align-items:center; gap:8px; min-width:0; flex:1;">
                                <div id="ai-status-dot" style="width:9px; height:9px; border-radius:50%; background:#475569; flex-shrink:0; transition:background 0.3s, box-shadow 0.3s;"></div>
                                <span id="ai-status-text" class="tm-camera-status" style="font-size:12px; font-weight:600; color:#9aa6d1; white-space:normal;">Click &ldquo;Scan Product&rdquo; to identify with AI camera</span>
                            </div>
                            <div style="display:flex; gap:6px; flex-shrink:0;">
                                <button type="button" id="ai-retake-btn" class="tm-stop-btn" style="display:none;" onclick="aiRetake()">&#x21BA; Retake</button>
                                <button type="button" id="ai-scan-btn" class="tm-start-btn" style="height:38px;" onclick="aiStartScan()">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                                    </svg>
                                    Scan Product
                                </button>
                            </div>
                        </div>

                        <!-- Camera feed (hidden until started) -->
                        <div id="ai-camera-area" class="tm-camera-wrap" style="display:none; margin-top:8px;">
                            <video id="ai-video" playsinline muted autoplay></video>
                            <canvas id="ai-snapshot-canvas" style="display:none; width:100%; max-height:220px; object-fit:cover; display:none;"></canvas>
                            <div class="tm-camera-overlay">
                                <div class="tm-viewfinder-ring" id="ai-scan-ring"></div>
                                <div class="tm-conf-bar-wrap">
                                    <div class="tm-conf-label">
                                        <span id="ai-status-scanning">Scanning&hellip;</span>
                                        <span class="tm-conf-pct" id="ai-conf-pct">&mdash;</span>
                                    </div>
                                    <div class="tm-conf-track">
                                        <div class="tm-conf-fill" id="ai-conf-bar"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Identified success overlay -->
                            <div id="ai-confirmed-overlay" style="display:none; position:absolute; inset:0; background:rgba(6,95,70,0.85); align-items:center; justify-content:center; flex-direction:column; gap:6px; z-index:5;">
                                <svg width="48" height="48" fill="none" stroke="#34d399" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                <span id="ai-confirmed-label" style="font-size:13px; font-weight:700; font-family:'Onest',sans-serif; color:#d1fae5;">Product identified!</span>
                            </div>
                        </div>
                    </div>
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
                <div>
                    <label class="form-label">Shelf Life (Days)</label>
                    <input type="number" name="shelf_life" id="add-shelf-life" class="premium-input w-full add-form-field" style="height:46px;" min="0" step="1" inputmode="numeric" placeholder="e.g. 30" disabled>
                    <p class="add-field-error" id="add-shelf-life-error" hidden></p>
                </div>
            </div>
            <div style="padding: 24px 30px; border-top: 1px solid #2A4484; display: flex; justify-content: space-between; align-items: center; gap: 12px; background: rgba(13, 22, 56, 0.3); flex-shrink: 0; flex-wrap: wrap;">
                <p class="form-keyboard-hint" style="margin:0;">Enter to save &middot; Shift+Enter for next field</p>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="premium-button" id="add-product-submit" disabled>Save Product</button>
                    <button type="button" class="premium-button !bg-transparent !shadow-none border border-[#64748b] hover:!bg-[#2A4484]/50" onclick="closeAddModal()">Cancel</button>
                </div>
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

<div id="edit-modal-overlay" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <h3>Edit Product</h3>
                <div class="modal-subtitle" id="edit-modal-sku"></div>
            </div>
            <button onclick="closeEditModal()" style="background:none; border:none; color:#9aa6d1; font-size:28px; cursor:pointer; line-height:1; padding:0;">&times;</button>
        </div>
        <form method="POST" id="edit-product-form" autocomplete="off">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="sync_driver" id="edit-sync-driver" value="category">
            <div class="modal-body-scroll" style="padding: 30px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div style="grid-column: span 2;">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" id="edit-name" class="premium-input w-full" style="height:46px;" required maxlength="255">
                </div>
                <div style="grid-column: span 2;">
                    <label class="form-label">Barcode</label>
                    <input type="text" name="barcode" id="edit-barcode" class="premium-input w-full" style="height:46px;" maxlength="50" placeholder="Leave blank to keep existing">
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <select name="category" id="edit-category" class="premium-input inventory-select w-full cursor-pointer" style="height:46px;">
                        <?php echo renderOptions($categories, '', 'Select Category'); ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Supplier</label>
                    <input type="text" name="supplier" id="edit-supplier" class="premium-input w-full" style="height:46px;" placeholder="Supplier Name">
                </div>
                <div>
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="qty" id="edit-qty" class="premium-input w-full" style="height:46px;" min="0" step="1" inputmode="numeric" required>
                </div>
                <div>
                    <label class="form-label">Price *</label>
                    <input type="number" name="price" id="edit-price" class="premium-input w-full" style="height:46px;" min="0" step="0.01" inputmode="decimal" required>
                </div>
                <div>
                    <label class="form-label">Location</label>
                    <input type="text" name="location" id="edit-location" class="premium-input w-full" style="height:46px;" placeholder="e.g. Shelf A1" list="location-options">
                </div>
                <div>
                    <label class="form-label">Unit (UoM)</label>
                    <select name="uom" id="edit-uom" class="premium-input inventory-select w-full cursor-pointer" style="height:46px;">
                        <?php echo renderOptions($uoms, '', 'Select Unit'); ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Reorder Point</label>
                    <input type="number" name="reorder_point" id="edit-reorder-point" class="premium-input w-full" style="height:46px;" min="0" step="1" inputmode="numeric">
                </div>
                <div>
                    <label class="form-label">Shelf Life (Days)</label>
                    <input type="number" name="shelf_life" id="edit-shelf-life" class="premium-input w-full" style="height:46px;" min="0" step="1" inputmode="numeric" placeholder="e.g. 30">
                </div>
            </div>
            <div style="padding: 24px 30px; border-top: 1px solid #2A4484; display: flex; justify-content: space-between; align-items: center; gap: 12px; background: rgba(13, 22, 56, 0.3); flex-shrink: 0; flex-wrap: wrap;">
                <p class="form-keyboard-hint" style="margin:0;">Enter to save · Tab to move between fields</p>
                <div style="display:flex; gap:12px;">
                    <button type="button" class="premium-button !bg-transparent !shadow-none border border-[#64748b] hover:!bg-[#2A4484]/50" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="premium-button" id="edit-product-submit">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>




<script>
const editInputs = <?php echo json_encode($edit_inputs); ?>;
const locationCategoryMap = <?php echo json_encode($locationCategoryMap); ?>;

const editableFields = ['name', 'barcode', 'category', 'supplier', 'location', 'qty', 'price', 'uom', 'reorder_point', 'shelf_life'];

const ADD_FORM_FIELD_ORDER = [
    'add-product-name',
    'add-category',
    'add-supplier',
    'add-quantity',
    'add-price',
    'add-location',
    'add-unit',
    'add-reorder-point',
    'add-shelf-life'
];

const EDIT_FIELD_ORDER = ['name', 'barcode', 'category', 'supplier', 'location', 'qty', 'price', 'uom', 'reorder_point', 'shelf_life'];

var addBarcodeLocked = false;      // kept for legacy compatibility (unused)
var addBarcodeValidating = false;  // kept for legacy compatibility (unused)
var aiProductIdentified = false;   // AI gate: true once camera identifies a product
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

function setAiStatus(text, state) {
    var dot   = document.getElementById('ai-status-dot');
    var label = document.getElementById('ai-status-text');
    if (label) label.textContent = text;
    if (dot) {
        var styles = {
            scanning:   { bg: '#fbbf24', shadow: '0 0 8px #fbbf2488' },
            identified: { bg: '#34d399', shadow: '0 0 8px #34d39988' },
            error:      { bg: '#f87171', shadow: '0 0 8px #f8717188' },
            idle:       { bg: '#64748b', shadow: 'none' }
        };
        var s = styles[state] || styles.idle;
        dot.style.background  = s.bg;
        dot.style.boxShadow   = s.shadow;
    }
}

function aiCaptureFrame() {
    var video    = document.getElementById('ai-video');
    var canvas   = document.getElementById('ai-snapshot-canvas');
    var overlay  = document.getElementById('ai-confirmed-overlay');
    var scanOverlay = document.getElementById('ai-scan-overlay');
    if (!video || !canvas) return;
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    var b64 = canvas.toDataURL('image/jpeg', 0.85);
    var imgField = document.getElementById('add-product-image-b64');
    if (imgField) imgField.value = b64;
    // Show snapshot, hide video
    video.style.display  = 'none';
    canvas.style.display = 'block';
    if (scanOverlay) scanOverlay.style.display = 'none';
    if (overlay)    { overlay.style.display = 'flex'; }
}

function aiOnIdentified(productId, confidence, classLabel) {
    if (aiProductIdentified) return;
    aiProductIdentified = true;

    // Store AI class as internal barcode ref
    var classField = document.getElementById('add-ai-class-label');
    var barcodeField = document.getElementById('add-barcode-hidden');
    if (classField) classField.value = classLabel;
    if (barcodeField) barcodeField.value = classLabel;

    // Capture the video frame
    aiCaptureFrame();

    // Stop the camera
    if (window.TMScanner && TMScanner.isRunning()) TMScanner.stopCamera();

    // Show confirmed label
    var confirmLabel = document.getElementById('ai-confirmed-label');
    if (confirmLabel) confirmLabel.textContent = 'Identified! Fill in the details below.';

    // Update status bar
    setAiStatus('Product identified — fill in the details below', 'identified');
    var retakeBtn = document.getElementById('ai-retake-btn');
    if (retakeBtn) retakeBtn.style.display = 'inline-block';

    // Update confidence bar to green/full
    var bar = document.getElementById('ai-conf-bar');
    if (bar) { bar.style.width = '100%'; bar.style.backgroundColor = '#34d399'; }

    // Unlock form fields
    setAddFormFieldsEnabled(true);
    var nameInput = document.getElementById('add-product-name');
    if (nameInput) setTimeout(function() { nameInput.focus(); }, 100);
}

function aiRetake() {
    aiProductIdentified = false;

    // Reset captured image
    var imgField = document.getElementById('add-product-image-b64');
    var classField = document.getElementById('add-ai-class-label');
    var barcodeField = document.getElementById('add-barcode-hidden');
    if (imgField) imgField.value = '';
    if (classField) classField.value = '';
    if (barcodeField) barcodeField.value = '';

    // Show video, hide snapshot
    var video = document.getElementById('ai-video');
    var canvas = document.getElementById('ai-snapshot-canvas');
    var overlay = document.getElementById('ai-confirmed-overlay');
    var scanOverlay = document.getElementById('ai-scan-overlay');
    if (video)  video.style.display = 'block';
    if (canvas) canvas.style.display = 'none';
    if (overlay) overlay.style.display = 'none';
    if (scanOverlay) scanOverlay.style.display = 'flex';

    // Reset bar
    var bar = document.getElementById('ai-conf-bar');
    if (bar) { bar.style.width = '0%'; bar.style.backgroundColor = '#fbbf24'; }

    // Hide retake btn
    var retakeBtn = document.getElementById('ai-retake-btn');
    if (retakeBtn) retakeBtn.style.display = 'none';

    // Re-lock form
    setAddFormFieldsEnabled(false);
    setAiStatus('Point camera at product — AI will identify it', 'scanning');

    // Restart camera
    if (window.TMScanner) {
        TMScanner.loadModel().then(function() {
            return TMScanner.startCamera(document.getElementById('ai-video'));
        }).catch(function(e) { setAiStatus('Camera error: ' + e.message, 'error'); });
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
    submitBtn.disabled = !aiProductIdentified;
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

// validateAddProductForm — barcode gate removed
function validateAddProductForm() {
    clearAddFormErrors();
    var valid = true;
    var firstInvalid = null;

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

    var slRaw = document.getElementById('add-shelf-life')?.value ?? '';
    if (slRaw !== '' && !isValidWholeNumber(slRaw)) {
        setAddFieldError('add-shelf-life', 'add-shelf-life-error', 'Shelf life must be a whole number of 0 or greater.');
        valid = false;
        firstInvalid = firstInvalid || document.getElementById('add-shelf-life');
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
    aiProductIdentified = false;
    addFormSubmitting = false;
    clearAddFormErrors();
    setAddFormFieldsEnabled(false);
    setAddSubmitState(false);

    // Reset AI camera UI
    var video      = document.getElementById('ai-video');
    var canvas     = document.getElementById('ai-snapshot-canvas');
    var overlay    = document.getElementById('ai-confirmed-overlay');
    var scanOverlay= document.getElementById('ai-scan-overlay');
    var retakeBtn  = document.getElementById('ai-retake-btn');
    var scanBtn    = document.getElementById('ai-scan-btn');
    var cameraArea = document.getElementById('ai-camera-area');
    var bar        = document.getElementById('ai-conf-bar');
    var imgField   = document.getElementById('add-product-image-b64');
    var classField = document.getElementById('add-ai-class-label');
    var barcodeField = document.getElementById('add-barcode-hidden');

    if (video)      video.style.display = 'block';
    if (canvas)     canvas.style.display = 'none';
    if (overlay)    overlay.style.display = 'none';
    if (scanOverlay)scanOverlay.style.display = 'flex';
    if (retakeBtn)  retakeBtn.style.display = 'none';
    if (scanBtn)  { scanBtn.disabled = false; }
    if (cameraArea) cameraArea.style.display = 'none';
    if (bar)  { bar.style.width = '0%'; bar.style.backgroundColor = '#fbbf24'; }
    if (imgField)   imgField.value = '';
    if (classField) classField.value = '';
    if (barcodeField) barcodeField.value = '';

    setAiStatus('Click “Scan Product” to identify with AI camera', 'idle');
}

function openAddModal() {
    resetAddProductForm();
    document.body.style.overflow = 'hidden';
    document.getElementById('add-modal-overlay').classList.add('active');
    // Camera does NOT auto-start — user clicks "Scan Product" button
}

function closeAddModal() {
    if (window.TMScanner && TMScanner.isRunning()) TMScanner.stopCamera();
    document.getElementById('add-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
    resetAddProductForm();
}

function aiStartScan() {
    if (!window.TMScanner) {
        setAiStatus('AI Scanner not available — check script tags', 'error');
        return;
    }
    var scanBtn = document.getElementById('ai-scan-btn');
    var cameraArea = document.getElementById('ai-camera-area');
    if (scanBtn) scanBtn.disabled = true;
    if (cameraArea) cameraArea.style.display = 'block';
    setAiStatus('Loading AI model…', 'scanning');

    TMScanner.loadModel()
        .then(function() {
            return TMScanner.startCamera(document.getElementById('ai-video'));
        })
        .catch(function(err) {
            if (cameraArea) cameraArea.style.display = 'none';
            if (scanBtn) scanBtn.disabled = false;
            setAiStatus('Error: ' + (err.message || err), 'error');
        });
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

// ── Edit Product Modal ──────────────────────────────────────
let editModalSubmitting = false;

function openEditModal(data) {
    // data is the row's dataset (DOMStringMap)
    document.getElementById('edit-id').value          = data.id;
    document.getElementById('edit-name').value        = data.name || '';
    document.getElementById('edit-barcode').value     = data.barcode || '';
    document.getElementById('edit-supplier').value    = data.supplier || '';
    document.getElementById('edit-location').value    = data.location || '';
    document.getElementById('edit-qty').value         = data.qty || '0';
    document.getElementById('edit-price').value       = data.price || '0.00';
    document.getElementById('edit-reorder-point').value = data.reorder || '10';
    document.getElementById('edit-shelf-life').value  = data.shelf || '';
    document.getElementById('edit-sync-driver').value = 'category';
    document.getElementById('edit-modal-sku').textContent = data.sku || '';

    // Set category select
    const catSel = document.getElementById('edit-category');
    catSel.value = data.category || '';
    if (catSel.value !== (data.category || '')) {
        const opt = document.createElement('option');
        opt.value = opt.textContent = data.category;
        catSel.appendChild(opt);
        catSel.value = data.category;
    }

    // Set UoM select
    const uomSel = document.getElementById('edit-uom');
    uomSel.value = data.uom || '';
    if (uomSel.value !== (data.uom || '')) {
        const opt = document.createElement('option');
        opt.value = opt.textContent = data.uom;
        uomSel.appendChild(opt);
        uomSel.value = data.uom;
    }

    editModalSubmitting = false;
    document.getElementById('edit-product-submit').disabled = false;
    document.getElementById('edit-product-submit').textContent = 'Save Changes';
    document.body.style.overflow = 'hidden';
    document.getElementById('edit-modal-overlay').classList.add('active');
    setTimeout(() => document.getElementById('edit-name').focus(), 60);
}

function closeEditModal() {
    document.getElementById('edit-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
    editModalSubmitting = false;
}

// Category ↔ Location sync for edit modal
document.addEventListener('DOMContentLoaded', function() {
    const editCat = document.getElementById('edit-category');
    const editLoc = document.getElementById('edit-location');
    const editSync = document.getElementById('edit-sync-driver');

    editCat?.addEventListener('change', function() {
        syncLocationFromCategory(this.value, editLoc, editSync);
    });
    editLoc?.addEventListener('change', function() {
        syncCategoryFromLocation(this.value, editCat, editSync);
    });
    editLoc?.addEventListener('blur', function() {
        syncCategoryFromLocation(this.value, editCat, editSync);
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const overlay = document.getElementById('edit-modal-overlay');
            if (overlay && overlay.classList.contains('active')) closeEditModal();
        }
    });

    // Close on backdrop click
    document.getElementById('edit-modal-overlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    // Prevent double-submit
    document.getElementById('edit-product-form')?.addEventListener('submit', function(e) {
        if (editModalSubmitting) { e.preventDefault(); return; }
        const name = document.getElementById('edit-name').value.trim();
        const qty  = document.getElementById('edit-qty').value;
        const price = document.getElementById('edit-price').value;
        if (!name) { e.preventDefault(); document.getElementById('edit-name').focus(); return; }
        if (qty === '' || isNaN(qty) || parseInt(qty) < 0) { e.preventDefault(); document.getElementById('edit-qty').focus(); return; }
        if (price !== '' && !isValidPrice(price)) { e.preventDefault(); document.getElementById('edit-price').focus(); return; }
        editModalSubmitting = true;
        const btn = document.getElementById('edit-product-submit');
        btn.disabled = true;
        btn.textContent = 'Saving…';
    });
});

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

<?php if (($_GET['add'] ?? '') === '1'): ?>
openAddModal();
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




document.getElementById('add-product-form')?.addEventListener('submit', function (e) {
    if (addFormSubmitting) {
        e.preventDefault();
        return;
    }

    if (!aiProductIdentified) {
        e.preventDefault();
        setAiStatus('Please scan a product first to unlock the form.', 'error');
        var scanBtn = document.getElementById('ai-scan-btn');
        if (scanBtn) scanBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
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

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.21.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.5/dist/teachablemachine-image.min.js"></script>
<script src="src/scripts/tm-scanner.js?v=<?php echo time(); ?>"></script>
<script>
(function() {
    if (!window.TMScanner) return;

    TMScanner.init({
        onPredicting: function(topLabel, topConf) {
            var pct = Math.round(topConf * 100);
            var bar = document.getElementById('ai-conf-bar');
            var pctEl = document.getElementById('ai-conf-pct');
            var statusEl = document.getElementById('ai-status-scanning');
            if (bar) {
                bar.style.width = pct + '%';
                bar.classList.remove('is-low', 'is-medium');
                if (pct < 40) bar.classList.add('is-low');
                else if (pct < 70) bar.classList.add('is-medium');
            }
            if (pctEl) pctEl.textContent = pct + '%';
            if (statusEl) statusEl.textContent = topLabel || 'Scanning\u2026';
        },
        onAutoConfirm: function(productId, confidence, classLabel) {
            aiOnIdentified(productId, confidence, classLabel);
        },
        onCameraStarted: function() {
            setAiStatus('Scanning\u2014 hold product steady', 'scanning');
        },
        onModelError: function(err) {
            setAiStatus('Model error: place model files in uploads/tm_models/global/', 'error');
        },
        onCameraError: function(err) {
            setAiStatus('Camera error: ' + err, 'error');
        },
        onStopped: function() {
            // no-op: state managed by aiOnIdentified / aiRetake
        }
    });

    // Legacy QR cleanup override
    if (window.initProductBarcodeInputs) {
        var originalInit = window.initProductBarcodeInputs;
        window.initProductBarcodeInputs = function() {
            document.querySelectorAll('.btn-scan-qr').forEach(function(btn) {
                btn.style.display = 'none';
            });
            var searchScanBtn = document.getElementById('search-scan-barcode-btn');
            if (searchScanBtn) searchScanBtn.style.display = 'none';
            originalInit();
        };
        window.initProductBarcodeInputs();
    }
}());
</script>
<?php renderLayoutFooter(); ?>