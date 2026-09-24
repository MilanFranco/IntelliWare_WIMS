<?php
session_start();

if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager', 'staff'])) { header('Location: signin.php'); exit(); }

require_once 'config/db.php';
require_once 'src/services/StockMovementService.php';
require_once 'src/services/LocationCategoryService.php';
require_once 'src/services/ApiClient.php';

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
$q = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
$category_param = trim($_GET['category'] ?? '');
$filter_param = strtolower(trim($_GET['filter'] ?? ($_GET['status'] ?? '')));

if (!function_exists('setFlashMessage')) {
    function setFlashMessage($type, $message) {
        $_SESSION['toast'] = ['type' => $type, 'message' => $message];
    }
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

function ensureArchivedColumn($conn) {
    static $checked = false;
    if ($checked) return;
    $result = $conn->query("SHOW COLUMNS FROM products LIKE 'archived'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `products` ADD COLUMN `archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `price`");
    }
    $checked = true;
}

ensurePriceColumn($conn);
ensureArchivedColumn($conn);

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

$brands = [];
$brand_query = $conn->query("SELECT DISTINCT supplier FROM products WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier");
if ($brand_query) {
    while ($bRow = $brand_query->fetch_assoc()) {
        $bTrim = trim($bRow['supplier']);
        if ($bTrim !== '') $brands[] = $bTrim;
    }
}
$brands = array_values(array_unique($brands));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        setFlashMessage('error', 'Security token mismatch. Operation blocked.');
        $filter_qs = $filter_param !== '' ? '&filter=' . urlencode($filter_param) : '';
        header("Location: products.php?page=$page&q=" . urlencode($q) . "&category=" . urlencode($category_param) . "$filter_qs");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $product_name = trim($_POST['product_name'] ?? '');
            $category     = trim($_POST['category'] ?? '');
            $supplier     = trim($_POST['supplier'] ?? '');
            $location     = trim($_POST['location'] ?? '');
            $unit         = trim($_POST['unit'] ?? '');
            $quantity     = parseNonNegativeInt($_POST['quantity'] ?? null);
            $reorder_point= parseNonNegativeInt($_POST['reorder_point'] ?? '10', 10);
            $price        = parseNonNegativeDecimal($_POST['price'] ?? '0', 0.00);
            $shelf_life   = empty($_POST['shelf_life']) ? null : parseNonNegativeInt($_POST['shelf_life']);
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

            $openingQty = $quantity;
            $categoryId = LocationCategoryService::resolveCategoryId($category, $conn);

            $stmt = $conn->prepare("INSERT INTO products (product_name, category, category_id, supplier, location, quantity, reorder_point, unit, price, shelf_life) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)");
            $stmt->bind_param("ssissisdi", $product_name, $category, $categoryId, $supplier, $location, $reorder_point, $unit, $price, $shelf_life);
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
                    ApiClient::receiveInventory(
                        $newProductId, $openingQty,
                        $_SESSION['Username'], 'Opening stock on product create'
                    );
                }

                setFlashMessage('success', 'Product added successfully.');
            } else {
                if ($conn->errno === 1062) {
                    throw new Exception('A product with this information already exists.');
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
            $categoryId = LocationCategoryService::resolveCategoryId($category, $conn);

            $stmt = $conn->prepare("UPDATE products SET product_name=?, category=?, category_id=?, supplier=?, location=?, reorder_point=?, unit=?, price=?, shelf_life=? WHERE product_id=?");
            $stmt->bind_param("ssissisdii", $_POST['name'], $category, $categoryId, $_POST['supplier'], $location, $reorder_point, $_POST['uom'], $price, $shelf_life, $id);
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();

            if ($qtyDelta !== 0) {
                if ($qtyDelta > 0) {
                    ApiClient::receiveInventory(
                        $id, abs($qtyDelta), $_SESSION['Username'], 'Manual stock adjustment from inventory edit'
                    );
                } else {
                    ApiClient::releaseInventory(
                        $id, abs($qtyDelta), $_SESSION['Username'], 'Manual stock adjustment from inventory edit'
                    );
                }
            }


            // ── Image handling ──────────────────────────────────────────────────
            $imgDir     = __DIR__ . '/uploads/products/';
            $imgPath    = $imgDir . $id . '.jpg';
            $imgAction  = trim($_POST['edit_image_action'] ?? 'keep');
            $imgB64     = trim($_POST['edit_image_b64'] ?? '');

            if ($imgAction === 'remove') {
                // Delete stored image if it exists
                if (file_exists($imgPath)) {
                    @unlink($imgPath);
                }
            } elseif ($imgAction === 'replace' && $imgB64 !== '') {
                // Decode and overwrite
                $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imgB64));
                if ($imgData !== false) {
                    if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
                    file_put_contents($imgPath, $imgData);
                }
            }
            // 'keep' → no change

            setFlashMessage('success', 'Product updated.');
        }
        if ($action === 'archive') {
            if ($role === 'staff') throw new Exception("Access Denied. Only Managers and Admins can archive products.");
            $stmt = $conn->prepare("UPDATE products SET archived = 1 WHERE product_id = ?");
            $stmt->bind_param("i", $_POST['id']);
            if ($stmt->execute()) setFlashMessage('success', 'Product archived successfully.');
        }
        if ($action === 'restore') {
            if ($role === 'staff') throw new Exception("Access Denied. Only Managers and Admins can restore products.");
            $stmt = $conn->prepare("UPDATE products SET archived = 0 WHERE product_id = ?");
            $stmt->bind_param("i", $_POST['id']);
            if ($stmt->execute()) setFlashMessage('success', 'Product restored successfully.');
        }
    } catch (InvalidArgumentException $e) {
        setFlashMessage('error', $e->getMessage());
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    $archived_param = isset($_GET['archived']) ? '&archived=' . (int)$_GET['archived'] : '';
    $filter_qs = $filter_param !== '' ? '&filter=' . urlencode($filter_param) : '';
    header("Location: products.php?page=$page&q=" . urlencode($q) . "&category=" . urlencode($category_param) . "$archived_param$filter_qs");
    exit();
}

$show_archived = isset($_GET['archived']) && $_GET['archived'] === '1';

$where = [];
$params = [];
$types = '';

// Always filter by archived status
$where[] = 'archived = ?';
$params[] = $show_archived ? 1 : 0;
$types .= 'i';

if ($q) {
    $where[] = "(product_name LIKE ? OR supplier LIKE ?)";
    $term = "%$q%";
    array_push($params, $term, $term);
    $types .= 'ss';
}
if ($category_param) {
    $where[] = "category = ?";
    $params[] = $category_param;
    $types .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// --- 5. MAIN DATA FETCH (Via Inventory Microservice) ---
$inventoryData = ApiClient::getInventoryProducts([
    'page'     => $page,
    'limit'    => $items_per_page,
    'q'        => $q,
    'category' => $category_param,
    'archived' => $show_archived ? '1' : '0',
    'status'   => $filter_param
]);

$total_items       = (int)($inventoryData['total'] ?? 0);
$total_units       = (int)($inventoryData['total_units'] ?? 0);
$total_stock_value = (float)($inventoryData['total_stock_value'] ?? 0.0);
$low_stock_count   = (int)($inventoryData['low_stock_count'] ?? 0);
$out_of_stock_count= (int)($inventoryData['out_of_stock_count'] ?? 0);
$total_pages       = (int)($inventoryData['total_pages'] ?? ceil($total_items / $items_per_page));
$items             = $inventoryData['items'] ?? [];

if (!function_exists('formatStockValue')) {
    function formatStockValue(float $value): string {
        if ($value >= 1000000000) {
            return '&#8369;' . number_format($value / 1000000000, 2) . 'B';
        }
        if ($value >= 1000000) {
            return '&#8369;' . number_format($value / 1000000, 2) . 'M';
        }
        if ($value >= 1000) {
            return '&#8369;' . number_format($value / 1000, 2) . 'K';
        }
        return '&#8369;' . number_format($value, 2);
    }
}

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
    /* Visual Hierarchy: Tier 2 Table Row Action Buttons */
    .inventory-action-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 1px solid rgba(81, 213, 90, 0.4);
        background: rgba(81, 213, 90, 0.1);
        color: #51D55A;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .inventory-action-btn:hover {
        transform: translateY(-2px);
        background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
        border-color: #51D55A;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4);
    }
    .inventory-action-btn:hover svg { stroke: #ffffff; color: #ffffff; }

    /* Visual Hierarchy: Tier 3 Pagination & Navigation Buttons */
    .pagination-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 38px;
        min-width: 38px;
        padding: 0 12px;
        background: rgba(19, 39, 91, 0.95) !important;
        border: 1.5px solid rgba(81, 213, 90, 0.5) !important;
        border-radius: 10px;
        color: #51D55A !important;
        font-family: 'Onest', sans-serif;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08);
        text-decoration: none;
        user-select: none;
        white-space: nowrap;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .pagination-btn.pagination-num {
        min-width: 38px !important;
        width: 38px !important;
        height: 38px !important;
        padding: 0 !important;
    }
    .pagination-btn.pagination-nav {
        height: 38px !important;
        min-width: 86px !important;
        padding: 0 16px !important;
    }
    .pagination-btn:hover:not(:disabled):not(.disabled) {
        background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important;
        color: #ffffff !important;
        border-color: #51D55A !important;
        box-shadow: 0 4px 16px rgba(81, 213, 90, 0.45);
        transform: translateY(-1.5px);
    }
    .pagination-btn:hover:not(:disabled):not(.disabled) svg { stroke: #ffffff !important; }
    .pagination-btn:active:not(:disabled):not(.disabled) {
        transform: translateY(1px);
        box-shadow: 0 1px 4px rgba(81, 213, 90, 0.2);
    }
    .pagination-btn.active,
    .pagination-btn.active:disabled {
        background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important;
        color: #ffffff !important;
        border-color: #51D55A !important;
        box-shadow: 0 4px 14px rgba(81, 213, 90, 0.45) !important;
        opacity: 1 !important;
        pointer-events: none !important;
        cursor: default !important;
        font-weight: 800 !important;
    }
    .pagination-btn:disabled:not(.active),
    .pagination-btn.disabled:not(.active) {
        opacity: 0.35 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        border-color: rgba(42, 68, 132, 0.35) !important;
        color: #6b7aab !important;
        background: rgba(19, 39, 91, 0.4) !important;
        box-shadow: none !important;
    }
    .pagination-info-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 14px;
        border-radius: 8px;
        background: rgba(19, 39, 91, 0.5);
        border: 1px solid rgba(42, 68, 132, 0.45);
        color: #9aa6d1;
        font-size: 13px;
        font-weight: 500;
        font-family: 'Onest', sans-serif;
    }

    /* ── Inventory Summary Metric Cards ─────────────────────────── */
    .inv-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 1024px) {
        .inv-summary-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
    }
    .inv-summary-card {
        background: #ffffff;
        border: 1.5px solid #D6E8F7;
        border-radius: 16px;
        padding: 18px 22px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 8px rgba(20, 80, 158, 0.07);
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        text-decoration: none;
        position: relative;
        overflow: hidden;
    }
    .inv-summary-card:hover {
        transform: translateY(-2px);
        border-color: #A8C8F0;
        box-shadow: 0 6px 22px rgba(20, 80, 158, 0.13);
    }
    .inv-summary-card-label {
        font-family: 'Onest', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #5B6B86;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .inv-summary-card-value {
        font-family: 'Onest', sans-serif;
        font-size: 26px;
        font-weight: 800;
        color: #0B1526;
        line-height: 1.2;
        margin-bottom: 4px;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }
    .inv-summary-card-sub {
        font-family: 'Onest', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: #7A90B0;
    }
    @media (max-width: 767px) {
        .inv-summary-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .inv-summary-card {
            padding: 14px 15px;
            min-height: 96px;
            border-radius: 14px;
        }
        .inv-summary-card-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }
        .inv-summary-card-label svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }
        .inv-summary-card-value {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 4px;
            letter-spacing: -0.02em;
        }
        .inv-summary-card-sub {
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    @media (max-width: 360px) {
        .inv-summary-card {
            padding: 12px 11px;
            min-height: 90px;
        }
        .inv-summary-card-label {
            font-size: 10px;
        }
        .inv-summary-card-value {
            font-size: 21px;
        }
        .inv-summary-card-sub {
            font-size: 10px;
        }
    }

    /* ── Inventory Layout View (Cards default: 2-3 per row on desktop, Table toggleable) ── */
    .inv-card-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
        padding: 24px;
    }
    @media (max-width: 1260px) {
        .inv-card-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            padding: 20px;
        }
    }
    @media (max-width: 640px) {
        .inv-card-grid {
            grid-template-columns: 1fr;
            gap: 16px;
            padding: 16px;
        }
    }

    /* View mode toggling */
    .view-mode-grid #inventory-desktop-table { display: none !important; }
    .view-mode-grid #inventory-card-grid { display: grid !important; }
    .view-mode-table #inventory-desktop-table { display: block !important; }
    .view-mode-table #inventory-card-grid { display: none !important; }

    @media (max-width: 767px) {
        #inventory-desktop-table { display: none !important; }
        #inventory-card-grid { display: grid !important; }
    }

    /* ── View Switcher Buttons (Hidden on mobile) ─────────────────────── */
    .inv-view-toggle {
        display: none !important;
        align-items: center;
        background: #EEF5FD;
        border: 1.5px solid #D6E8F7;
        border-radius: 10px;
        padding: 2px;
        gap: 2px;
        height: 42px;
    }
    @media (min-width: 768px) {
        .inv-view-toggle {
            display: inline-flex !important;
        }
    }
    .inv-view-toggle-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 7px;
        border: none;
        background: transparent;
        color: #7A90B0;
        cursor: pointer;
        transition: all 0.18s ease;
    }
    .inv-view-toggle-btn:hover {
        color: #14509E;
        background: rgba(20, 80, 158, 0.08);
    }
    .inv-view-toggle-btn.active {
        background: #ffffff;
        color: #14509E;
        box-shadow: 0 1px 4px rgba(20, 80, 158, 0.15);
    }

    [data-theme="dark"] .inv-view-toggle {
        background: rgba(13, 22, 56, 0.6);
        border-color: rgba(42, 68, 132, 0.5);
    }
    [data-theme="dark"] .inv-view-toggle-btn {
        color: #9aa6d1;
    }
    [data-theme="dark"] .inv-view-toggle-btn:hover {
        color: #ffffff;
        background: rgba(81, 213, 90, 0.15);
    }
    [data-theme="dark"] .inv-view-toggle-btn.active {
        background: #1e3678;
        color: #51D55A;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
    }

    /* ── Product Card (Compact & Modern Grid Card) ─────────────────────── */
    .inv-card {
        background: #ffffff;
        border: 1.5px solid #D9E8F6;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(20, 80, 158, 0.05);
        transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        cursor: default;
        position: relative;
    }
    .inv-card:hover {
        border-color: #A8C8F0;
        box-shadow: 0 8px 24px rgba(20, 80, 158, 0.12);
        transform: translateY(-2px);
    }

    /* Top section: thumb | info | badge */
    .inv-card-top {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 14px 10px 14px;
    }
    .inv-card-thumb {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #EEF5FD;
        border: 1px solid #D0E5F7;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #14509E;
        overflow: hidden;
    }
    .inv-card-thumb-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .inv-card-info {
        flex: 1;
        min-width: 0;
    }
    .inv-card-name {
        font-family: 'Onest', sans-serif;
        font-size: 13.5px;
        font-weight: 700;
        color: #0B1526;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 36px;
        word-break: break-word;
    }
    .inv-card-meta {
        font-family: 'Onest', sans-serif;
        font-size: 11.5px;
        color: #556987;
        margin-top: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .inv-card-sku {
        font-family: monospace;
        font-size: 10.5px;
        color: #7A90B0;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Status badge */
    .inv-card-badge {
        flex-shrink: 0;
        font-family: 'Onest', sans-serif;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.04em;
        padding: 2.5px 8px;
        border-radius: 6px;
        white-space: nowrap;
    }
    .badge-out  { background: rgba(239,68,68,0.1); color: #DC2626; border: 1px solid rgba(239,68,68,0.3); }
    .badge-low  { background: rgba(245,158,11,0.1); color: #D97706; border: 1px solid rgba(245,158,11,0.3); }
    .badge-good { background: rgba(16,185,129,0.1); color: #059669; border: 1px solid rgba(16,185,129,0.3); }

    /* Stats section */
    .inv-card-stats {
        display: flex;
        background: #F8FAFD;
        border-top: 1px solid #E4EFF9;
        border-bottom: 1px solid #E4EFF9;
        padding: 9px 12px;
        margin-top: 4px;
    }
    .inv-card-stat {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .inv-card-stat + .inv-card-stat {
        border-left: 1px solid #E4EFF9;
        padding-left: 10px;
    }
    .inv-stat-label {
        font-family: 'Onest', sans-serif;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 0.07em;
        color: #7A90B0;
        text-transform: uppercase;
    }
    .inv-stat-value {
        font-family: 'Onest', sans-serif;
        font-size: 15px;
        font-weight: 800;
        color: #0B1526;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .inv-stat-value.stat-out { color: #DC2626; }
    .inv-stat-value.stat-low { color: #D97706; }
    .inv-stat-unit {
        font-size: 10px;
        font-weight: 500;
        color: #7A90B0;
        margin-left: 2px;
    }

    /* Action buttons */
    .inv-card-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 10px 14px 12px 14px;
    }
    .inv-card-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 34px;
        border-radius: 8px;
        font-family: 'Onest', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border: 1.5px solid #D6E8F7;
        background: #F3F8FD;
        color: #1E3A5F;
        transition: all 0.18s ease;
    }
    .inv-card-btn:hover {
        background: #E2EDF8;
        border-color: #A8C8F0;
        color: #14509E;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(20, 80, 158, 0.1);
    }
    .inv-card-btn:hover svg { stroke: #14509E; }
    .inv-card-btn--archive:hover,
    .inv-card-btn--restore:hover {
        background: #FFF5F5;
        border-color: #FCA5A5;
        color: #DC2626;
    }
    .inv-card-btn--archive:hover svg,
    .inv-card-btn--restore:hover svg { stroke: #DC2626; }

    /* Dark-mode overrides for summary cards & product cards */
    [data-theme="dark"] .inv-summary-card {
        background: linear-gradient(160deg, #1b2f6e 0%, #111e4a 100%);
        border-color: rgba(42, 68, 132, 0.55);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    }
    [data-theme="dark"] .inv-summary-card:hover {
        border-color: rgba(81, 213, 90, 0.35);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    }
    [data-theme="dark"] .inv-summary-card-label { color: #9aa6d1; }
    [data-theme="dark"] .inv-summary-card-value { color: #ffffff; }
    [data-theme="dark"] .inv-summary-card-sub   { color: #6b7aab; }

    [data-theme="dark"] .inv-card {
        background: linear-gradient(160deg, #1b2f6e 0%, #111e4a 100%);
        border-color: rgba(42, 68, 132, 0.55);
        box-shadow: 0 4px 16px rgba(0,0,0,0.3);
    }
    [data-theme="dark"] .inv-card:hover {
        border-color: rgba(81, 213, 90, 0.35);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    }
    [data-theme="dark"] .inv-card-thumb {
        background: rgba(13, 22, 56, 0.7);
        border-color: rgba(42,68,132,0.4);
        color: #9aa6d1;
    }
    [data-theme="dark"] .inv-card-name  { color: #f1f5f9; }
    [data-theme="dark"] .inv-card-meta  { color: #9aa6d1; }
    [data-theme="dark"] .inv-card-sku   { color: #6b7aab; }
    [data-theme="dark"] .inv-card-divider,
    [data-theme="dark"] .inv-card-stats {
        background: rgba(13, 22, 56, 0.35);
        border-color: rgba(42,68,132,0.45);
    }
    [data-theme="dark"] .inv-card-stat + .inv-card-stat { border-color: rgba(42,68,132,0.45); }
    [data-theme="dark"] .inv-stat-label { color: #6b7aab; }
    [data-theme="dark"] .inv-stat-value { color: #f1f5f9; }
    [data-theme="dark"] .inv-stat-unit  { color: #6b7aab; }
    [data-theme="dark"] .inv-card-actions { border-top: none; }
    [data-theme="dark"] .inv-card-btn {
        background: rgba(81,213,90,0.08);
        border-color: rgba(81,213,90,0.3);
        color: #51D55A;
    }
    [data-theme="dark"] .inv-card-btn:hover {
        background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
        border-color: #51D55A;
        color: #fff;
        box-shadow: 0 4px 14px rgba(81,213,90,0.35);
    }
    [data-theme="dark"] .inv-card-btn:hover svg { stroke: #fff; }
    [data-theme="dark"] .inv-card-btn--archive:hover,
    [data-theme="dark"] .inv-card-btn--restore:hover {
        background: rgba(248,113,113,0.15);
        border-color: rgba(248,113,113,0.5);
        color: #f87171;
    }
    [data-theme="dark"] .inv-card-btn--archive:hover svg,
    [data-theme="dark"] .inv-card-btn--restore:hover svg { stroke: #f87171; }
    [data-theme="dark"] .badge-out  { background: rgba(239,68,68,0.15); color: #f87171; border-color: rgba(239,68,68,0.3); }
    [data-theme="dark"] .badge-low  { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }
    [data-theme="dark"] .badge-good { background: rgba(52,211,153,0.12); color: #34d399; border-color: rgba(52,211,153,0.3); }

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
    .modal-body-scroll {
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1;
        max-height: min(560px, calc(100vh - 220px));
        scrollbar-color: #51D55A rgba(19, 39, 91, 0.3);
        scrollbar-width: thin;
    }
    .modal-body-scroll::-webkit-scrollbar { width: 6px; }
    .modal-body-scroll::-webkit-scrollbar-track { background: rgba(19, 39, 91, 0.3); border-radius: 4px; }
    .modal-body-scroll::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%); border-radius: 4px; }
    .modal-body-scroll::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #4BC052 0%, #3EA147 100%); }

    .modal-footer {
        padding: 20px 30px;
        border-top: 1px solid rgba(42, 68, 132, 0.5);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        background: rgba(13, 22, 56, 0.4);
        flex-shrink: 0;
        flex-wrap: wrap;
    }


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

    #action-confirm-overlay { z-index: 100060 !important; }
    #action-confirm-ok-btn {
        transition: transform 0.18s ease, filter 0.18s ease, box-shadow 0.18s ease;
    }
    #action-confirm-ok-btn:hover {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }
    #action-confirm-ok-btn:active {
        transform: translateY(0);
    }

    [data-theme="light"] .modal-overlay { background: var(--iw-bg-overlay) !important; }
    [data-theme="light"] .modal-box {
        background: #FFFFFF !important;
        border-color: #D6E8F7 !important;
        box-shadow: 0 20px 40px -8px rgba(20, 80, 158, 0.18), 0 0 0 1px rgba(20, 80, 158, 0.08) !important;
    }
    [data-theme="light"] .modal-header {
        background: #F8FAFC !important;
        border-bottom-color: #E2E8F0 !important;
    }
    [data-theme="light"] .modal-title h3 { color: #0F172A !important; }
    [data-theme="light"] .modal-subtitle,
    [data-theme="light"] .form-label { color: #475569 !important; }
    [data-theme="light"] .modal-close-btn {
        background: #F1F5F9 !important;
        border: 1.5px solid #CBD5E1 !important;
        color: #64748B !important;
        box-shadow: none !important;
    }
    [data-theme="light"] .modal-close-btn:hover {
        background: #E2E8F0 !important;
        color: #0F172A !important;
    }
    [data-theme="light"] .modal-footer {
        background: #F8FAFC !important;
        border-top-color: #E2E8F0 !important;
    }
    [data-theme="light"] .modal-body-scroll {
        background: #FFFFFF !important;
        scrollbar-color: #CBD5E1 transparent !important;
    }
    [data-theme="light"] .modal-body-scroll::-webkit-scrollbar {
        width: 6px;
    }
    [data-theme="light"] .modal-body-scroll::-webkit-scrollbar-track {
        background: transparent !important;
    }
    [data-theme="light"] .modal-body-scroll::-webkit-scrollbar-thumb {
        background: #CBD5E1 !important;
        border-radius: 4px;
    }
    [data-theme="light"] .modal-body-scroll::-webkit-scrollbar-thumb:hover {
        background: #94A3B8 !important;
    }
    [data-theme="light"] .modal-footer {
        background: #F8FAFC !important;
        border-top-color: #E2E8F0 !important;
    }
    [data-theme="light"] .form-keyboard-hint {
        color: #64748B !important;
    }
    [data-theme="light"] .btn-scan {
        background: var(--iw-bg-btn-secondary) !important;
        border-color: var(--iw-border) !important;
        color: var(--iw-text) !important;
    }
    [data-theme="light"] #edit-image-empty-wrap {
        background: #F8FAFC !important;
        border-color: #CBD5E1 !important;
    }
    [data-theme="light"] #edit-image-current-wrap {
        background: #F8FAFC !important;
    }
    [data-theme="light"] #ai-capture-block {
        background: #F1F5F9 !important;
        border-color: #CBD5E1 !important;
    }
    [data-theme="light"] #ai-status-text {
        color: #475569 !important;
    }
    [data-theme="light"] .add-field-locked,
    [data-theme="light"] input:disabled,
    [data-theme="light"] select:disabled {
        background: #F1F5F9 !important;
        color: #94A3B8 !important;
        border-color: #E2E8F0 !important;
    }
    [data-theme="light"] #archive-modal-overlay h3,
    [data-theme="light"] #restore-modal-overlay h3,
    [data-theme="light"] #action-confirm-overlay h3 {
        color: #0F172A !important;
    }
    [data-theme="light"] #archive-modal-overlay p,
    [data-theme="light"] #restore-modal-overlay p,
    [data-theme="light"] #action-confirm-overlay p {
        color: #475569 !important;
    }
    [data-theme="light"] #archive-product-name,
    [data-theme="light"] #restore-product-name {
        color: #0F172A !important;
    }

    /* ── Mobile: Full-height modal sheet (clean touch UX) ────────── */
    @media (max-width: 767px) {
        #add-modal-overlay,
        #edit-modal-overlay {
            padding: 0 !important;
            align-items: stretch !important;
        }
        #add-modal-overlay .modal-box,
        #edit-modal-overlay .modal-box {
            max-width: 100% !important;
            width: 100% !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            border-radius: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            margin: 0 !important;
        }
        #add-product-form,
        #edit-product-form {
            display: flex !important;
            flex-direction: column !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow: hidden !important;
        }
        .modal-header {
            padding: 16px 18px !important;
            flex-shrink: 0 !important;
            border-bottom: 1px solid rgba(42, 68, 132, 0.4) !important;
        }
        [data-theme="light"] .modal-header {
            background: #FFFFFF !important;
            border-bottom: 1px solid #D6E8F7 !important;
        }
        .modal-title h3 {
            font-size: 18px !important;
            line-height: 1.3 !important;
        }
        .modal-title .modal-subtitle {
            font-size: 12px !important;
            line-height: 1.4 !important;
            margin-top: 2px !important;
        }
        .modal-close-btn {
            width: 36px !important;
            height: 36px !important;
            font-size: 22px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 10px !important;
            flex-shrink: 0 !important;
        }
        .modal-body-scroll {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
            padding: 16px 16px 24px 16px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
            max-height: none !important;
        }
        .modal-body-scroll > div {
            grid-column: span 1 !important;
            width: 100% !important;
        }
        .form-label {
            font-size: 13px !important;
            margin-bottom: 6px !important;
            display: block !important;
        }
        .premium-input {
            font-size: 14px !important;
            height: 46px !important;
            width: 100% !important;
        }

        /* AI Scanner Header & Buttons on Mobile: Clean vertical stack */
        #ai-capture-block {
            padding: 14px !important;
            border-radius: 14px !important;
            margin-bottom: 4px !important;
        }
        .ai-scanner-header-row,
        #ai-capture-block .tm-category-row {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 12px !important;
        }
        .ai-scanner-status-col,
        #ai-capture-block .tm-category-row > div:first-child {
            width: 100% !important;
            flex: none !important;
            display: flex !important;
            align-items: flex-start !important;
            gap: 8px !important;
        }
        #ai-status-dot {
            margin-top: 4px !important;
            flex-shrink: 0 !important;
        }
        #ai-status-text {
            font-size: 12.5px !important;
            line-height: 1.45 !important;
            display: block !important;
            white-space: normal !important;
            word-break: normal !important;
            flex: 1 !important;
        }
        .ai-scanner-btn-col,
        #ai-capture-block .tm-category-row > div:last-child {
            width: 100% !important;
            display: flex !important;
            gap: 8px !important;
            flex-shrink: 0 !important;
        }
        .ai-scanner-btn-col > button,
        #ai-upload-btn,
        #ai-capture-btn,
        #ai-retake-btn {
            flex: 1 1 0 !important;
            width: 50% !important;
            height: 42px !important;
            padding: 0 10px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            justify-content: center !important;
            white-space: nowrap !important;
            border-radius: 10px !important;
        }

        /* Modal Footer on Mobile: Full-width sticky action bar */
        .modal-footer {
            flex-shrink: 0 !important;
            padding: 12px 16px max(14px, env(safe-area-inset-bottom, 14px)) 16px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
            background: rgba(13, 22, 56, 0.98) !important;
            border-top: 1px solid rgba(42, 68, 132, 0.4) !important;
            z-index: 10 !important;
        }
        [data-theme="light"] .modal-footer {
            background: #FFFFFF !important;
            border-top: 1px solid #D6E8F7 !important;
            box-shadow: 0 -4px 16px rgba(20, 80, 158, 0.08) !important;
        }
        .modal-footer .form-keyboard-hint {
            display: none !important;
        }
        .modal-footer > div {
            width: 100% !important;
            display: flex !important;
            gap: 10px !important;
        }
        .modal-footer button {
            flex: 1 1 0 !important;
            width: 50% !important;
            height: 46px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            justify-content: center !important;
            border-radius: 12px !important;
        }
    }
</style>

<!-- Hero Page Header Banner -->
<div class="page-hero-card mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold font-onest text-white tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#51D55A]/15 border border-[#51D55A]/30 flex items-center justify-center text-[#51D55A] shadow-lg shadow-[#51D55A]/10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"></path></svg>
                </div>
                Inventory Management
            </h1>
            <p class="text-sm text-[#9aa6d1] mt-1 max-w-2xl font-onest">
                Manage products, stock levels, and categories. Add, edit, or archive inventory items with AI-powered scanning support.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#13275B]/80 border border-[#2A4484] text-xs text-[#E3E3E3]">
                <span class="w-2 h-2 rounded-full bg-[#51D55A] animate-pulse"></span>
                Active: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1D387B]/60 border border-[#2A4484] text-xs text-[#9aa6d1]">
                <svg class="w-3.5 h-3.5 text-[#51D55A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Items: <strong class="text-white"><?php echo number_format($total_items); ?> Products</strong>
            </span>
        </div>
    </div>
</div>

<!-- ── Inventory Summary Metric Cards ────────────────────────────── -->
<div class="inv-summary-grid" id="inventory-summary-cards">
    <!-- Stock Value Card (matches user reference design) -->
    <div class="inv-summary-card" title="Total inventory value: ₱<?php echo number_format($total_stock_value, 2); ?>">
        <div>
            <div class="inv-summary-card-label">
                <span>Stock value</span>
                <svg width="18" height="18" fill="none" stroke="#51D55A" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <div class="inv-summary-card-value"><?php echo formatStockValue($total_stock_value); ?></div>
        </div>
        <div class="inv-summary-card-sub"><?php echo number_format($total_units); ?> units on hand</div>
    </div>

    <!-- Total Products -->
    <div class="inv-summary-card">
        <div>
            <div class="inv-summary-card-label">
                <span>Total Products</span>
                <svg width="18" height="18" fill="none" stroke="#3B82F6" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
            </div>
            <div class="inv-summary-card-value"><?php echo number_format($total_items); ?></div>
        </div>
        <div class="inv-summary-card-sub"><?php echo $show_archived ? 'Archived catalog items' : 'Active catalog SKUs'; ?></div>
    </div>

    <!-- Low Stock -->
    <a href="products.php?filter=low_stock<?php echo $q ? '&q='.urlencode($q) : ''; ?><?php echo $category_param ? '&category='.urlencode($category_param) : ''; ?>" 
       class="inv-summary-card" style="cursor: pointer;" title="Click to view low stock items">
        <div>
            <div class="inv-summary-card-label">
                <span>Low Stock</span>
                <svg width="18" height="18" fill="none" stroke="#F59E0B" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            </div>
            <div class="inv-summary-card-value" style="color: #D97706;"><?php echo number_format($low_stock_count); ?></div>
        </div>
        <div class="inv-summary-card-sub"><?php echo $low_stock_count === 1 ? '1 item needs reorder' : $low_stock_count . ' items need reorder'; ?></div>
    </a>

    <!-- Out of Stock -->
    <a href="products.php?filter=out_of_stock<?php echo $q ? '&q='.urlencode($q) : ''; ?><?php echo $category_param ? '&category='.urlencode($category_param) : ''; ?>" 
       class="inv-summary-card" style="cursor: pointer;" title="Click to view out of stock items">
        <div>
            <div class="inv-summary-card-label">
                <span>Out of Stock</span>
                <svg width="18" height="18" fill="none" stroke="#EF4444" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
            <div class="inv-summary-card-value" style="color: #DC2626;"><?php echo number_format($out_of_stock_count); ?></div>
        </div>
        <div class="inv-summary-card-sub"><?php echo $out_of_stock_count === 1 ? '1 item fully depleted' : $out_of_stock_count . ' items fully depleted'; ?></div>
    </a>
</div>

<div class="premium-card">
    <div class="p-4 sm:p-6 border-b border-[#2A4484]/50 flex flex-col gap-4 bg-[#13275B]/30">
        <div>
            <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2 flex-wrap">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"></path></svg>
                Inventory List
                <span class="text-[10px] text-[#51D55A] bg-[#51D55A]/10 border border-[#51D55A]/20 px-2 py-1 rounded font-normal"><?php echo $total_items; ?> Items</span>
            </h3>
            <p class="text-xs text-[#9aa6d1] mt-1">Manage products and stock levels.</p>
        </div>

        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 w-full">
            <form method="GET" class="flex flex-col sm:flex-row gap-2 flex-1 items-stretch sm:items-center min-w-0" id="inv-search-form">
                <input type="hidden" name="page" value="1">
                <?php if ($show_archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
                <div class="flex gap-2 flex-1 min-w-0 items-center">
                    <div class="relative flex-1 min-w-0 sm:min-w-[180px] lg:min-w-[220px]">
                        <input name="q" id="product-search-input" class="premium-input w-full px-4 text-sm" style="height:42px;" placeholder="Search by name, category, supplier..." value="<?php echo htmlspecialchars($q); ?>">
                    </div>
                    <button type="submit" class="btn-secondary h-[42px] w-[42px] min-w-[42px] flex items-center justify-center shrink-0 rounded-xl" style="padding:0;" title="Search Inventory" aria-label="Search">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                </div>
                <select name="category" class="premium-input inventory-select cursor-pointer text-sm w-full sm:w-36 md:w-40 shrink-0" style="height:42px;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat) echo "<option value='".htmlspecialchars($cat)."' ".($category_param==$cat?'selected':'').">" .htmlspecialchars($cat)."</option>"; ?>
                </select>
                <select name="filter" class="premium-input inventory-select cursor-pointer text-sm w-full sm:w-36 md:w-40 shrink-0" style="height:42px;" onchange="this.form.submit()">
                    <option value="">All Stock</option>
                    <option value="low_stock" <?php echo in_array($filter_param, ['low', 'low_stock']) ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out_of_stock" <?php echo in_array($filter_param, ['out', 'out_of_stock']) ? 'selected' : ''; ?>>Out of Stock</option>
                    <option value="in_stock" <?php echo in_array($filter_param, ['good', 'in_stock']) ? 'selected' : ''; ?>>In Stock</option>
                </select>
                <?php if($q||$category_param||$filter_param): ?>
                    <a href="products.php<?php echo $show_archived ? '?archived=1' : ''; ?>" class="btn-secondary whitespace-nowrap h-[42px] flex items-center justify-center px-3 shrink-0" title="Clear filters"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></a>
                <?php endif; ?>
            </form>
            <div id="inv-action-btns" class="flex flex-row items-center gap-2 w-full md:w-auto shrink-0">
                <div class="inv-view-toggle items-center" title="Toggle view">
                    <button type="button" class="inv-view-toggle-btn active" id="btn-view-grid" onclick="setInventoryView('grid')" title="Card Grid View">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect></svg>
                    </button>
                    <button type="button" class="inv-view-toggle-btn" id="btn-view-table" onclick="setInventoryView('table')" title="Table View">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    </button>
                </div>
                <?php if (!$show_archived): ?>
                <button type="button" class="premium-button flex-1 md:flex-initial whitespace-nowrap h-[42px] flex items-center justify-center gap-1.5 shrink-0 px-3.5 sm:px-4 text-xs sm:text-sm font-semibold rounded-xl min-w-0" onclick="openAddModal()">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span class="truncate">Add Product</span>
                </button>
                <?php endif; ?>
                <a href="products.php?archived=<?php echo $show_archived ? '0' : '1'; ?>&q=<?php echo urlencode($q); ?>&category=<?php echo urlencode($category_param); ?><?php echo $filter_param !== '' ? '&filter=' . urlencode($filter_param) : ''; ?>"
                   class="btn-secondary flex-1 md:flex-initial whitespace-nowrap h-[42px] flex items-center justify-center gap-1.5 shrink-0 px-3 sm:px-3.5 text-xs sm:text-sm font-semibold rounded-xl min-w-0">
                    <?php if ($show_archived): ?>
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span class="truncate">Active Products</span>
                    <?php else: ?>
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                    <span class="truncate">View Archived</span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- ── Archived banner (shared) ──────────────────────────────── -->
    <?php if ($show_archived): ?>
    <div style="background:linear-gradient(90deg,rgba(245,158,11,0.12),rgba(245,158,11,0.06)); border-bottom:1px solid rgba(245,158,11,0.25); padding:10px 20px; display:flex; align-items:center; gap:10px;">
        <svg width="16" height="16" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
        <span style="font-size:13px; font-weight:600; color:#f59e0b; font-family:'Onest',sans-serif;">Viewing Archived Products</span>
        <span style="font-size:12px; color:#9aa6d1; margin-left:4px;">&mdash; <?php echo $total_items; ?> product<?php echo $total_items !== 1 ? 's' : ''; ?> archived</span>
    </div>
    <?php endif; ?>
    <?php if ($filter_param && in_array($filter_param, ['low', 'low_stock', 'out', 'out_of_stock', 'in_stock', 'good', 'attention'])): ?>
    <div style="background:linear-gradient(90deg,rgba(81,213,90,0.12),rgba(81,213,90,0.04)); border-bottom:1px solid rgba(81,213,90,0.25); padding:10px 20px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <svg width="16" height="16" fill="none" stroke="#51D55A" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            <span style="font-size:13px; font-weight:600; color:#51D55A; font-family:'Onest',sans-serif;">
                Filtered by Stock Status: <?php echo in_array($filter_param, ['low', 'low_stock']) ? 'Low Stock' : (in_array($filter_param, ['out', 'out_of_stock']) ? 'Out of Stock' : (in_array($filter_param, ['in_stock', 'good']) ? 'In Stock' : 'Attention Needed')); ?>
            </span>
            <span style="font-size:12px; color:#9aa6d1;">&mdash; <?php echo $total_items; ?> product<?php echo $total_items !== 1 ? 's' : ''; ?> found</span>
        </div>
        <a href="products.php?<?php echo http_build_query(array_filter(['q' => $q, 'category' => $category_param, 'archived' => $show_archived ? '1' : null])); ?>" class="text-xs text-[#51D55A] hover:underline flex items-center gap-1 font-semibold">Clear Filter &times;</a>
    </div>
    <?php endif; ?>

    <div id="inventory-products-content" class="view-mode-grid">
    <?php if (!$items): ?>
        <div class="text-center p-12 text-[#9aa6d1]">No items found matching the current criteria.</div>
    <?php else: ?>

    <!-- ── Product Card Grid View (Default) ────────────────────────── -->
    <div class="inv-card-grid" id="inventory-card-grid">
        <?php foreach ($items as $it): ?>
        <?php
            $qty         = (int)$it['qty'];
            $reorder     = (int)$it['reorder_point'];
            $isOut       = $qty <= 0;
            $isLow       = !$isOut && $qty <= $reorder;
            $statusClass = $isOut ? 'badge-out' : ($isLow ? 'badge-low' : 'badge-good');
            $statusLabel = $isOut ? 'OUT' : ($isLow ? 'LOW' : 'GOOD');
            $imgFile     = __DIR__ . '/uploads/products/' . (int)$it['id'] . '.jpg';
            $hasThumb    = file_exists($imgFile);
        ?>
        <div class="inv-card"
             data-id="<?php echo $it['id']; ?>"
             data-name="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>"
             data-category="<?php echo htmlspecialchars($it['category'], ENT_QUOTES); ?>"
             data-supplier="<?php echo htmlspecialchars($it['supplier'], ENT_QUOTES); ?>"
             data-location="<?php echo htmlspecialchars($it['location'], ENT_QUOTES); ?>"
             data-qty="<?php echo $qty; ?>"
             data-price="<?php echo number_format((float)($it['price'] ?? 0), 2, '.', ''); ?>"
             data-uom="<?php echo htmlspecialchars($it['uom'], ENT_QUOTES); ?>"
             data-reorder="<?php echo $reorder; ?>"
             data-shelf="<?php echo htmlspecialchars($it['shelf_life'] ?? '', ENT_QUOTES); ?>"
             data-sku="<?php echo htmlspecialchars($it['sku'], ENT_QUOTES); ?>">

            <!-- Top: thumb + info + badge -->
            <div class="inv-card-top">
                <div class="inv-card-thumb">
                    <?php if ($hasThumb): ?>
                        <img src="uploads/products/<?php echo (int)$it['id']; ?>.jpg" alt="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>" class="inv-card-thumb-img" loading="lazy">
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="inv-card-info">
                    <div class="inv-card-name" title="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($it['name']); ?></div>
                    <div class="inv-card-meta"><?php
                        $meta = [];
                        if ($it['category'] !== '') $meta[] = htmlspecialchars($it['category']);
                        if ($it['location'] !== '')  $meta[] = htmlspecialchars($it['location']);
                        echo $meta ? implode(' · ', $meta) : '&mdash;';
                    ?></div>
                    <div class="inv-card-sku">SKU <?php echo htmlspecialchars($it['sku']); ?></div>
                </div>
                <div class="inv-card-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></div>
            </div>

            <!-- Stats -->
            <div class="inv-card-stats">
                <div class="inv-card-stat">
                    <span class="inv-stat-label">STOCK</span>
                    <span class="inv-stat-value <?php echo $isOut ? 'stat-out' : ($isLow ? 'stat-low' : ''); ?>"><?php echo $qty; ?><?php if ($it['uom']): ?><span class="inv-stat-unit"><?php echo htmlspecialchars($it['uom']); ?></span><?php endif; ?></span>
                </div>
                <div class="inv-card-stat">
                    <span class="inv-stat-label">PRICE</span>
                    <span class="inv-stat-value">&#8369;<?php echo number_format((float)($it['price'] ?? 0), 2); ?></span>
                </div>
                <div class="inv-card-stat">
                    <span class="inv-stat-label">REORDER</span>
                    <span class="inv-stat-value"><?php echo $reorder; ?></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="inv-card-actions"<?php echo ($role === 'staff') ? ' style="grid-template-columns: 1fr;"' : ''; ?>>
                <button type="button" class="inv-card-btn inv-card-btn--edit" onclick="openEditModal(this.closest('.inv-card').dataset)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </button>
                <?php if ($role !== 'staff'): ?>
                <?php if (!$show_archived): ?>
                <button type="button" class="inv-card-btn inv-card-btn--archive" onclick="promptArchive('<?php echo $it['id']; ?>', '<?php echo htmlspecialchars(addslashes($it['name'])); ?>')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                    Archive
                </button>
                <?php else: ?>
                <button type="button" class="inv-card-btn inv-card-btn--restore" onclick="promptRestore('<?php echo $it['id']; ?>', '<?php echo htmlspecialchars(addslashes($it['name'])); ?>')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
                    Restore
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div><!-- /.inv-card-grid -->

    <!-- ── Table View (Optional Toggle) ─────────────────────────── -->
    <div class="inv-desktop-table" id="inventory-desktop-table" style="display: none;">
        <div class="overflow-x-auto">
            <table class="premium-table w-full text-left min-w-[1100px]">
                <thead>
                    <tr>
                        <th width="8%">SKU</th> <th width="18%">Name</th> <th width="10%">Category</th>
                        <th width="8%">Supplier</th> <th width="8%">Location</th> <th width="5%">Qty</th>
                        <th width="6%">Price</th> <th width="5%">Unit</th> <th width="6%">Reorder</th> <th width="8%">Shelf Life</th> <th width="6%">Status</th>
                        <th width="10%" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr id="row-<?php echo $it['id']; ?>"
                        class="hover:bg-[#1D387B]/50 transition-colors"
                        data-id="<?php echo $it['id']; ?>"
                        data-name="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>"
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
                        <td><span style="font-weight:600;"><?php echo htmlspecialchars($it['name']); ?></span></td>
                        <td><?php echo htmlspecialchars($it['category']); ?></td>
                        <td><?php echo htmlspecialchars($it['supplier']); ?></td>
                        <td><?php echo htmlspecialchars($it['location']); ?></td>
                        <td><span style="font-weight:700;"><?php echo htmlspecialchars($it['qty']); ?></span></td>
                        <td><span style="font-family:monospace;">&#8369;<?php echo number_format((float)($it['price'] ?? 0), 2); ?></span></td>
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
                                <?php if ($role !== 'staff'): ?>
                                <?php if (!$show_archived): ?>
                                <button type="button" class="inventory-action-btn delete-btn" onclick="promptArchive('<?php echo $it['id']; ?>', '<?php echo htmlspecialchars(addslashes($it['name'])); ?>')" title="Archive">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                                </button>
                                <?php else: ?>
                                <button type="button" class="inventory-action-btn restore-btn" onclick="promptRestore('<?php echo $it['id']; ?>', '<?php echo htmlspecialchars(addslashes($it['name'])); ?>')" title="Restore">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 .49-3.51"></path></svg>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /.inv-desktop-table -->

    <?php endif; ?>
    </div><!-- /#inventory-products-content -->

    <div id="inventory-pagination-container">
    <?php if ($total_pages > 1): ?>
    <div class="p-4 border-t border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-4 flex-wrap">
        <?php
            $params = ['q' => $q, 'category' => $category_param, 'filter' => $filter_param ?: null, 'archived' => $show_archived ? '1' : null];
            $qs = http_build_query(array_filter($params, function($v) { return $v !== '' && $v !== null; }));
            $qsPrefix = $qs ? $qs . '&' : '';
            $showing_start = ($total_items > 0) ? (($page - 1) * $items_per_page + 1) : 0;
            $showing_end = min($page * $items_per_page, $total_items);
        ?>
        <div class="pagination-info-pill">
            Showing <?php echo $showing_start; ?> to <?php echo $showing_end; ?> of <?php echo $total_items; ?> entries
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <?php if ($page > 1): ?>
                <button type="button" class="pagination-btn pagination-nav pagination-link" data-url="products.php?<?php echo $qsPrefix; ?>page=<?php echo $page - 1; ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    Previous
                </button>
            <?php else: ?>
                <button type="button" class="pagination-btn pagination-nav" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    Previous
                </button>
            <?php endif; ?>

            <?php
                $startPage = max(1, $page - 2);
                $endPage = min($total_pages, $page + 2);
                if ($startPage > 1) {
                    echo '<button type="button" class="pagination-btn pagination-num pagination-link" data-url="products.php?' . $qsPrefix . 'page=1">1</button>';
                    if ($startPage > 2) {
                        echo '<span style="color:#6b7aab; padding:0 4px; display:inline-flex; align-items:center;">&hellip;</span>';
                    }
                }
                for ($p = $startPage; $p <= $endPage; $p++) {
                    if ($p === $page) {
                        echo '<button type="button" class="pagination-btn pagination-num active" disabled>' . $p . '</button>';
                    } else {
                        echo '<button type="button" class="pagination-btn pagination-num pagination-link" data-url="products.php?' . $qsPrefix . 'page=' . $p . '">' . $p . '</button>';
                    }
                }
                if ($endPage < $total_pages) {
                    if ($endPage < $total_pages - 1) {
                        echo '<span style="color:#6b7aab; padding:0 4px; display:inline-flex; align-items:center;">&hellip;</span>';
                    }
                    echo '<button type="button" class="pagination-btn pagination-num pagination-link" data-url="products.php?' . $qsPrefix . 'page=' . $total_pages . '">' . $total_pages . '</button>';
                }
            ?>

            <?php if ($page < $total_pages): ?>
                <button type="button" class="pagination-btn pagination-nav pagination-link" data-url="products.php?<?php echo $qsPrefix; ?>page=<?php echo $page + 1; ?>">
                    Next
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            <?php else: ?>
                <button type="button" class="pagination-btn pagination-nav" disabled>
                    Next
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    </div>
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
            <button type="button" onclick="closeAddModal()" class="modal-close-btn" title="Close">&times;</button>
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

                    <!-- AI Camera Identification — with bottom capture button and clean visual hierarchy -->
                    <div class="pos-scanner-zone" id="ai-capture-block" style="background:linear-gradient(175deg, rgba(19, 39, 91, 0.4) 0%, rgba(11, 20, 56, 0.6) 100%); border:1px solid rgba(42, 68, 132, 0.5); border-radius:14px; padding:16px;">

                        <!-- Status bar + Upload / Take Photo buttons -->
                        <div class="tm-category-row ai-scanner-header-row" style="align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                            <div class="ai-scanner-status-col" style="display:flex; align-items:center; gap:8px; min-width:0; flex:1;">
                                <div id="ai-status-dot" style="width:9px; height:9px; border-radius:50%; background:#475569; flex-shrink:0; transition:background 0.3s, box-shadow 0.3s;"></div>
                                <span id="ai-status-text" class="tm-camera-status" style="font-size:12px; font-weight:600; color:#9aa6d1; white-space:normal;">Take photo or upload packaging &mdash; auto-fills product details</span>
                            </div>
                            <div class="ai-scanner-btn-col" style="display:flex; gap:8px; flex-shrink:0;">
                                <button type="button" id="ai-retake-btn" class="btn-secondary" style="display:none; height:38px; padding:0 14px; align-items:center; gap:6px;" onclick="aiRetake()">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" style="display:inline-block;vertical-align:middle;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                    </svg>
                                    Retake
                                </button>
                                <!-- Upload from file -->
                                <input type="file" id="ai-upload-input" accept="image/*" style="display:none;" onchange="aiUploadImage(this)">
                                <input type="file" id="ai-mobile-cam-input" accept="image/*" capture="environment" style="display:none;" onchange="aiUploadImage(this)">
                                <button type="button" id="ai-upload-btn" class="btn-secondary" style="height:38px; padding:0 14px; display:inline-flex; align-items:center; gap:6px;" onclick="document.getElementById('ai-upload-input').click()">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                    </svg>
                                    Upload Image
                                </button>
                                <!-- Camera capture toggle -->
                                <button type="button" id="ai-capture-btn" class="btn-secondary" style="height:38px; padding:0 14px; display:inline-flex; align-items:center; gap:6px;" onclick="aiStartScan()">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                                    </svg>
                                    Take Photo
                                </button>
                            </div>
                        </div>

                        <!-- Uploaded image preview (shown instead of camera area when file is uploaded) -->
                        <div id="ai-upload-preview-wrap" style="display:none; margin-top:12px; border-radius:12px; overflow:hidden; border:1px solid var(--iw-border); background:#060816; position:relative;">
                            <img id="ai-upload-preview-img" src="" alt="Uploaded reference image" style="width:100%; max-height:220px; object-fit:contain; display:block;">
                            <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 50%); pointer-events:none;"></div>
                            <div style="position:absolute; bottom:10px; left:12px; display:flex; align-items:center; gap:6px;">
                                <svg width="16" height="16" fill="none" stroke="#34d399" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                <span style="font-size:12px; font-weight:600; font-family:'Onest',sans-serif; color:#d1fae5;">Image selected</span>
                            </div>
                            <!-- Remove image button -->
                            <button type="button" onclick="aiRemoveUploadedImage()" title="Remove image" style="position:absolute; top:8px; right:8px; width:28px; height:28px; border-radius:50%; background:rgba(15,23,42,0.80); border:1px solid rgba(148,163,184,0.35); color:#cbd5e1; font-size:16px; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s, color 0.2s; z-index:10;" onmouseover="this.style.background='rgba(239,68,68,0.75)';this.style.color='#fff';" onmouseout="this.style.background='rgba(15,23,42,0.80)';this.style.color='#cbd5e1';">&times;</button>
                        </div>

                        <!-- Camera Viewfinder with Capture Button at the Bottom -->
                        <div id="ai-camera-area" class="tm-camera-wrap" style="display:none; margin-top:12px; border-radius:12px; overflow:hidden; border:1px solid #2A4484; position:relative; background:#040714;">
                            <div style="position:relative; width:100%; height:220px; overflow:hidden;">
                                <video id="ai-video" playsinline muted autoplay style="width:100%; height:100%; object-fit:cover; display:block;"></video>
                                <canvas id="ai-snapshot-canvas" style="width:100%; height:100%; object-fit:cover; display:none;"></canvas>
                                <div class="tm-camera-overlay" id="ai-scan-overlay">
                                    <div class="tm-viewfinder-ring" id="ai-scan-ring"></div>
                                </div>
                                <!-- Captured success overlay -->
                                <div id="ai-confirmed-overlay" style="display:none; position:absolute; inset:0; background:rgba(6,95,70,0.85); align-items:center; justify-content:center; flex-direction:column; gap:6px; z-index:5;">
                                    <svg width="48" height="48" fill="none" stroke="#34d399" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                    <span id="ai-confirmed-label" style="font-size:13px; font-weight:700; font-family:'Onest',sans-serif; color:#d1fae5;">Photo captured!</span>
                                </div>
                            </div>

                            <!-- BOTTOM SHUTTER / CAPTURE BUTTON BAR -->
                            <div id="ai-camera-bottom-bar" style="display:flex; align-items:center; justify-content:center; gap:12px; padding:10px 16px; background:rgba(13,22,56,0.95); border-top:1px solid rgba(42,68,132,0.5);">
                                <button type="button" id="ai-camera-cancel-btn" class="btn-secondary" style="height:40px; padding:0 18px; font-size:13px;" onclick="_cancelAddProductCamera()">
                                    Cancel
                                </button>
                                <button type="button" id="ai-capture-snap-btn" class="premium-button" style="height:40px; padding:0 24px; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 0 15px rgba(81,213,90,0.35);" onclick="aiCaptureFrame()">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                                    </svg>
                                    📷 Capture &amp; Auto-fill
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── VISUAL HIERARCHY: 1. GENERAL PRODUCT DETAILS ── -->
                <div style="grid-column: span 2; display:flex; align-items:center; gap:10px; margin-top:4px;">
                    <span style="font-size:12px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#51D55A; font-family:'Onest',sans-serif; display:flex; align-items:center; gap:6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                        1. General Details
                    </span>
                    <div style="flex:1; height:1px; background:rgba(42,68,132,0.4);"></div>
                </div>

                <div style="grid-column: span 2;">
                    <label class="form-label">Product Name *</label>
                    <input name="product_name" id="add-product-name" class="premium-input w-full add-form-field" style="height:46px;" placeholder="e.g. Barefaced Matte Sunscreen 50g" required maxlength="255">
                    <p class="add-field-error" id="add-product-name-error" hidden></p>
                    <!-- Quick-Select Suggestion Chips -->
                    <div id="add-product-name-suggestions" style="display:none; margin-top:8px; align-items:center; gap:6px; flex-wrap:wrap;">
                        <span style="font-size:11px; font-weight:700; color:#51D55A; font-family:'Onest',sans-serif; text-transform:uppercase; letter-spacing:0.04em;">✨ Suggestions:</span>
                        <div id="add-product-name-chips" style="display:inline-flex; gap:6px; flex-wrap:wrap;"></div>
                    </div>
                    <!-- View detected text from packaging -->
                    <div id="add-ocr-raw-wrap" style="display:none; margin-top:6px;">
                        <button type="button" id="add-ocr-toggle-btn" onclick="toggleOcrRawText()" class="btn-secondary" style="height:30px; padding:0 14px; font-size:12px;">
                            Show text read from packaging &darr;
                        </button>
                        <div id="add-ocr-raw-box" style="display:none; margin-top:6px; max-height:100px; overflow-y:auto; font-size:11px; background:#070b1e; border:1px solid #2A4484; border-radius:8px; padding:8px 12px; color:#cbd5e1; line-height:1.6;"></div>
                    </div>
                </div>

                <div>
                    <label class="form-label">Category</label>
                    <select name="category" id="add-category" class="premium-input inventory-select w-full cursor-pointer add-form-field" style="height:46px;" disabled>
                        <?php echo renderOptions($categories, '', 'Select Category'); ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Supplier / Brand</label>
                    <input name="supplier" id="add-supplier" class="premium-input w-full add-form-field" style="height:46px;" placeholder="e.g. Barefaced" disabled>
                </div>

                <!-- ── VISUAL HIERARCHY: 2. STOCK & STORAGE ── -->
                <div style="grid-column: span 2; display:flex; align-items:center; gap:10px; margin-top:8px;">
                    <span style="font-size:12px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#51D55A; font-family:'Onest',sans-serif; display:flex; align-items:center; gap:6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                        2. Stock &amp; Storage
                    </span>
                    <div style="flex:1; height:1px; background:rgba(42,68,132,0.4);"></div>
                </div>

                <div>
                    <label class="form-label">Opening Stock Quantity *</label>
                    <input type="number" name="quantity" id="add-quantity" class="premium-input w-full add-form-field" style="height:46px;" min="0" step="1" inputmode="numeric" placeholder="0" required disabled>
                    <p class="add-field-error" id="add-quantity-error" hidden></p>
                </div>

                <div>
                    <label class="form-label">Unit of Measure (UoM)</label>
                    <select name="unit" id="add-unit" class="premium-input inventory-select w-full cursor-pointer add-form-field" style="height:46px;" disabled>
                        <?php echo renderOptions($uoms, '', 'Select Unit'); ?>
                    </select>
                </div>

                <div style="grid-column: span 2;">
                    <label class="form-label">Storage Location / Shelf</label>
                    <input name="location" id="add-location" class="premium-input w-full add-form-field" style="height:46px;" placeholder="e.g. Shelf A1 or Warehouse Rack 2" list="location-options" disabled>
                </div>

                <!-- ── VISUAL HIERARCHY: 3. PRICING & SHELF LIFE ── -->
                <div style="grid-column: span 2; display:flex; align-items:center; gap:10px; margin-top:8px;">
                    <span style="font-size:12px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#51D55A; font-family:'Onest',sans-serif; display:flex; align-items:center; gap:6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        3. Pricing &amp; Inventory Control
                    </span>
                    <div style="flex:1; height:1px; background:rgba(42,68,132,0.4);"></div>
                </div>

                <div>
                    <label class="form-label">Unit Price (₱) *</label>
                    <input type="number" name="price" id="add-price" class="premium-input w-full add-form-field" style="height:46px;" min="0" step="0.01" inputmode="decimal" value="0.00" required disabled>
                    <p class="add-field-error" id="add-price-error" hidden></p>
                </div>

                <div>
                    <label class="form-label">Reorder Point (Low Stock Alert)</label>
                    <input type="number" name="reorder_point" id="add-reorder-point" class="premium-input w-full add-form-field" style="height:46px;" value="10" min="0" step="1" inputmode="numeric" disabled>
                    <p class="add-field-error" id="add-reorder-point-error" hidden></p>
                </div>

                <div style="grid-column: span 2;">
                    <label class="form-label">Shelf Life (Days)</label>
                    <input type="number" name="shelf_life" id="add-shelf-life" class="premium-input w-full add-form-field" style="height:46px;" min="0" step="1" inputmode="numeric" placeholder="e.g. 365 (leave blank if non-perishable)" disabled>
                    <p class="add-field-error" id="add-shelf-life-error" hidden></p>
                </div>
            </div>
            <div class="modal-footer">
                <p class="form-keyboard-hint" style="margin:0;">Enter to save &middot; Shift+Enter for next field</p>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="premium-button" id="add-product-submit" disabled>Save Product</button>
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
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

<!-- Archive Confirmation Modal -->
<div id="archive-modal-overlay" class="modal-overlay">
    <div class="modal-box" style="max-width: 420px; text-align: center; padding: 40px;">
        <div style="margin-bottom: 16px; display: flex; justify-content: center;">
            <div style="width: 80px; height: 80px; background: rgba(81,213,90,0.12); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(81,213,90,0.25);">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#51D55A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="21 8 21 21 3 21 3 8"></polyline>
                    <rect x="1" y="3" width="22" height="5"></rect>
                    <line x1="10" y1="12" x2="14" y2="12"></line>
                </svg>
            </div>
        </div>
        <h3 style="color:#fff; margin-bottom:8px; font-family:'Onest',sans-serif;">Archive Product?</h3>
        <p style="color:#9aa6d1; margin-bottom:6px; font-size:14px;"><strong id="archive-product-name" style="color:#e3e3e3;"></strong></p>
        <p style="color:#9aa6d1; margin-bottom:28px; font-size:13px;">This product will be hidden from the inventory list. You can restore it at any time from the Archived view.</p>
        <div style="display:flex; justify-content:center; gap:12px;">
            <button type="button" class="btn-secondary" onclick="closeArchiveModal()">Cancel</button>
            <form method="POST" id="modal-archive-form" style="display:inline;">
                <input type="hidden" name="action" value="archive">
                <input type="hidden" name="id" id="modal-archive-id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" class="premium-button">
                    Archive Product
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restore-modal-overlay" class="modal-overlay">
    <div class="modal-box" style="max-width: 420px; text-align: center; padding: 40px;">
        <div style="margin-bottom: 16px; display: flex; justify-content: center;">
            <div style="width: 80px; height: 80px; background: rgba(81,213,90,0.12); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(81,213,90,0.25);">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#51D55A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="1 4 1 10 7 10"></polyline>
                    <path d="M3.51 15a9 9 0 1 0 .49-3.51"></path>
                </svg>
            </div>
        </div>
        <h3 style="color:#fff; margin-bottom:8px; font-family:'Onest',sans-serif;">Restore Product?</h3>
        <p style="color:#9aa6d1; margin-bottom:6px; font-size:14px;"><strong id="restore-product-name" style="color:#e3e3e3;"></strong></p>
        <p style="color:#9aa6d1; margin-bottom:28px; font-size:13px;">This product will be returned to the active inventory list.</p>
        <div style="display:flex; justify-content:center; gap:12px;">
            <button type="button" class="btn-secondary" onclick="closeRestoreModal()">Cancel</button>
            <form method="POST" id="modal-restore-form" style="display:inline;">
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="id" id="modal-restore-id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" class="premium-button" style="background: linear-gradient(135deg,#059669,#047857) !important; box-shadow: 0 4px 14px rgba(5,150,105,0.35) !important;">
                    Restore Product
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Action Confirmation Modal (for Discard Changes, Remove Image, etc.) -->
<div id="action-confirm-overlay" class="modal-overlay" style="z-index: 100060;">
    <div class="modal-box" style="max-width: 420px; text-align: center; padding: 36px 28px; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75);">
        <div style="margin-bottom: 18px; display: flex; justify-content: center;">
            <div id="action-confirm-icon-wrap" style="width: 68px; height: 68px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"></div>
        </div>
        <h3 id="action-confirm-title" style="color:#fff; margin-bottom:10px; font-family:'Onest',sans-serif; font-size:18px; font-weight:700;"></h3>
        <p id="action-confirm-msg" style="color:#9aa6d1; margin-bottom:26px; font-size:13.5px; line-height:1.55; font-family:'Onest',sans-serif;"></p>
        <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
            <button type="button" class="btn-secondary" id="action-confirm-cancel-btn" style="min-width:115px; height:42px; font-size:13px; font-weight:600;">Keep Editing</button>
            <button type="button" id="action-confirm-ok-btn" style="min-width:125px; height:42px; font-size:13px; font-weight:700; border-radius:12px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; padding:0 20px;"></button>
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
            <button type="button" onclick="closeEditModal()" class="modal-close-btn" title="Close">&times;</button>
        </div>
        <form method="POST" id="edit-product-form" autocomplete="off">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="sync_driver" id="edit-sync-driver" value="category">
            <input type="hidden" name="edit_image_action" id="edit-image-action" value="keep">
            <input type="hidden" name="edit_image_b64" id="edit-image-b64" value="">
            <div class="modal-body-scroll" style="padding: 30px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <!-- Product Image Section -->
                <div style="grid-column: span 2;">
                    <label class="form-label">Product Image</label>
                    <!-- File input (hidden) -->
                    <input type="file" id="edit-image-file-input" accept="image/*" style="display:none;" onchange="editHandleImageUpload(this)">
                    <!-- Current / preview image -->
                    <div id="edit-image-current-wrap" style="border-radius:12px; overflow:hidden; border:1px solid var(--iw-border); background:#060816; position:relative; display:none;">
                        <img id="edit-image-preview-img" src="" alt="Product image" style="width:100%; max-height:200px; object-fit:contain; display:block;">
                        <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 50%); pointer-events:none;"></div>
                        <div style="position:absolute; bottom:10px; left:12px; display:flex; align-items:center; gap:6px;">
                            <svg width="16" height="16" fill="none" stroke="#34d399" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                            <span id="edit-image-status-label" style="font-size:12px; font-weight:600; font-family:'Onest',sans-serif; color:#d1fae5;">Current image</span>
                        </div>
                        <!-- Remove image button (×) -->
                        <button type="button" onclick="editRemoveImage()" title="Remove image" style="position:absolute; top:8px; right:8px; width:28px; height:28px; border-radius:50%; background:rgba(15,23,42,0.80); border:1px solid rgba(148,163,184,0.35); color:#cbd5e1; font-size:16px; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s, color 0.2s; z-index:10;" onmouseover="this.style.background='rgba(239,68,68,0.75)';this.style.color='#fff';" onmouseout="this.style.background='rgba(15,23,42,0.80)';this.style.color='#cbd5e1';">&times;</button>
                    </div>
                    <!-- No image / upload prompt -->
                    <div id="edit-image-empty-wrap" style="display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:10px; border:1px dashed rgba(100,116,139,0.4); background:rgba(13,22,56,0.25);">
                        <svg width="20" height="20" fill="none" stroke="#64748b" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75 7.5 10.5l3.75 3.75 3-3 4.5 4.5M3.75 21h16.5M21 3.75H3A.75.75 0 0 0 2.25 4.5v15A.75.75 0 0 0 3 20.25h18a.75.75 0 0 0 .75-.75V4.5A.75.75 0 0 0 21 3.75z"/></svg>
                        <span style="font-size:13px; color:#64748b; font-family:'Onest',sans-serif; flex:1;">No image</span>
                        <button type="button" class="btn-secondary" style="height:34px; padding:0 14px; display:inline-flex; align-items:center; gap:5px; font-size:12px;" onclick="document.getElementById('edit-image-file-input').click()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            Upload Image
                        </button>
                    </div>
                    <!-- Replace button shown below when image exists -->
                    <div id="edit-image-replace-bar" style="display:none; margin-top:8px; align-items:center; gap:8px; justify-content:flex-end;">
                        <button type="button" class="btn-secondary" style="height:32px; padding:0 12px; display:inline-flex; align-items:center; gap:5px; font-size:12px;" onclick="document.getElementById('edit-image-file-input').click()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                            Replace Image
                        </button>
                    </div>
                </div>
                <div style="grid-column: span 2;">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" id="edit-name" class="premium-input w-full" style="height:46px;" required maxlength="255">
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
            <div class="modal-footer">
                <p class="form-keyboard-hint" style="margin:0;">Enter to save · Tab to move between fields</p>
                <div style="display:flex; gap:12px;">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="premium-button" id="edit-product-submit">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>




<script>
const editInputs = <?php echo json_encode($edit_inputs); ?>;
const locationCategoryMap = <?php echo json_encode($locationCategoryMap); ?>;
const DB_BRANDS = <?php echo json_encode($brands); ?>;
const DB_CATEGORIES = <?php echo json_encode($categories); ?>;

const editableFields = ['name', 'category', 'supplier', 'location', 'qty', 'price', 'uom', 'reorder_point', 'shelf_life'];

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

const EDIT_FIELD_ORDER = ['name', 'category', 'supplier', 'location', 'qty', 'price', 'uom', 'reorder_point', 'shelf_life'];

var aiProductIdentified = false;   // AI gate: true once camera identifies a product
var addFormSubmitting = false;
var editRowSubmitting = {};
var locationCategorySyncLock = false;
var ADD_SUBMIT_LABEL = 'Save Product';

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
    // Stop the camera stream
    _stopAddProductCamera();
    // Trigger post-capture UI update
    aiOnIdentified();
    // Run OCR label reading to auto-fill product name, category, and unit
    aiProcessOcr(b64, true);
}

function aiProcessOcr(imageSource, isCameraSnapshot) {
    if (typeof ProductCameraScanner === 'undefined') {
        console.warn('[AI OCR] ProductCameraScanner library not ready yet');
        setAiStatus('Photo saved — please enter product details below', 'identified');
        return;
    }

    var nameInput     = document.getElementById('add-product-name');
    var catInput      = document.getElementById('add-category');
    var supplierInput = document.getElementById('add-supplier');
    var unitInput     = document.getElementById('add-unit');
    var priceInput    = document.getElementById('add-price');

    // ── Helper: fill a select input by value/text ─────────────────────────
    function fillSelect(selectEl, value) {
        if (!selectEl || !value) return false;
        var lv = value.toLowerCase();
        for (var s = 0; s < selectEl.options.length; s++) {
            if (selectEl.options[s].value.toLowerCase() === lv ||
                selectEl.options[s].text.toLowerCase()  === lv) {
                selectEl.selectedIndex = s;
                selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }
        }
        return false;
    }

    // ── Priority 1: Cloud Multimodal Vision AI (Google Gemini) ───────────
    setAiStatus('✨ Google Gemini Vision analyzing packaging…', 'scanning');

    ProductCameraScanner.extractProductWithAiVision(imageSource).then(function(res) {
        var item = (res && res.data) ? res.data : null;
        if (!item || !item.product_name) {
            throw new Error('No product name returned from AI Vision');
        }

        console.log('[AI Vision] Extracted product details:', item);
        var autoFilledCount = 0;

        if (nameInput && item.product_name) {
            nameInput.value = item.product_name;
            clearAddFieldError('add-product-name', 'add-product-name-error');
            nameInput.dispatchEvent(new Event('input', { bubbles: true }));
            renderNameSuggestions([item.product_name], item.product_name);
            autoFilledCount++;
        }
        if (supplierInput && item.brand) {
            supplierInput.value = item.brand;
            supplierInput.dispatchEvent(new Event('input', { bubbles: true }));
            autoFilledCount++;
        }
        if (catInput && item.category) {
            if (fillSelect(catInput, item.category)) autoFilledCount++;
        }
        if (unitInput && item.unit) {
            if (fillSelect(unitInput, item.unit)) autoFilledCount++;
        }

        var conf = Math.round((item.confidence || 0.95) * 100);
        ProductCameraScanner.playBeep(true);

        var confirmLabel = document.getElementById('ai-confirmed-label');
        if (confirmLabel) confirmLabel.textContent = '🟢 ' + item.product_name;

        setAiStatus(
            '🟢 AI Vision Extracted (' + conf + '%): ' + item.product_name +
            (item.brand ? ' · ' + item.brand : '') +
            (item.category ? ' · ' + item.category : '') +
            ' — verified & ready to save!',
            'identified'
        );

        // Check if item already exists in store inventory to auto-populate price
        ProductCameraScanner.lookupByOcrText(item.product_name).then(function(matches) {
            if (matches && matches.length > 0) {
                var top = matches[0];
                if (top.score >= 0.80) {
                    if (top.price && priceInput && !priceInput.value) {
                        priceInput.value = top.price;
                    }
                    var altNames = matches.slice(0, 4).map(function(m) { return m.product_name; });
                    renderNameSuggestions(altNames, item.product_name);
                }
            }
        });

    }).catch(function(err) {
        console.warn('[AI Vision] Gemini unavailable or fell back, using local OCR engine:', err);
        runLocalOcrPipeline();
    });

    // ── Priority 2: Local Tesseract OCR + DB Fuzzy Search Fallback ────────
    function runLocalOcrPipeline() {
        setAiStatus('🔍 Reading packaging with local OCR…', 'scanning');

        var catList = (typeof DB_CATEGORIES !== 'undefined' && DB_CATEGORIES.length > 0) ? DB_CATEGORIES : [];
        if (catList.length === 0 && catSelect) {
            for (var i = 0; i < catSelect.options.length; i++) {
                if (catSelect.options[i].value) catList.push(catSelect.options[i].value);
            }
        }
        var brandList = (typeof DB_BRANDS !== 'undefined') ? DB_BRANDS : [];

        ProductCameraScanner.runOcr(imageSource, function(pct, phase) {
            if (pct != null && pct > 0) {
                setAiStatus('🔍 Reading packaging (' + pct + '%)…', 'scanning');
            } else if (phase) {
                setAiStatus('🔍 ' + phase + '…', 'scanning');
            }
        }, !!isCameraSnapshot).then(function(result) {
            var rawText = (typeof result === 'object' && result.text != null) ? result.text : (result || '');
            console.log('[AI OCR] Detected raw text:', rawText);

            if (!rawText || !rawText.trim()) {
                setAiStatus('Photo saved — please enter product name below', 'identified');
                return;
            }

            setAiStatus('🔍 Searching inventory database…', 'scanning');

            var dbPromise  = ProductCameraScanner.lookupByOcrText(rawText);
            var parsed     = ProductCameraScanner.parseProductLabel(rawText, catList, brandList);
            console.log('[AI OCR] Parsed label result:', parsed);

            dbPromise.then(function(dbMatches) {
                console.log('[AI OCR] DB fuzzy matches:', dbMatches);
                var autoFilledCount = 0;

                // Case A: Strong DB match (score >= 0.65)
                var topMatch = dbMatches && dbMatches.length > 0 ? dbMatches[0] : null;
                if (topMatch && topMatch.score >= 0.65) {
                    var confidenceEmoji = topMatch.score >= 0.90 ? '🟢' : '🟡';
                    var confidencePct   = Math.round(topMatch.score * 100);

                    if (nameInput) {
                        nameInput.value = topMatch.product_name;
                        clearAddFieldError('add-product-name', 'add-product-name-error');
                        nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                        renderNameSuggestions([topMatch.product_name], topMatch.product_name);
                        autoFilledCount++;
                    }
                    if (fillSelect(catInput, topMatch.category)) autoFilledCount++;
                    if (topMatch.supplier && supplierInput) {
                        supplierInput.value = topMatch.supplier;
                        supplierInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (fillSelect(unitInput, topMatch.unit)) autoFilledCount++;
                    if (topMatch.price && priceInput && !priceInput.value) {
                        priceInput.value = topMatch.price;
                    }

                    var altNames = dbMatches.slice(0, 4).map(function(m) { return m.product_name; });
                    renderNameSuggestions(altNames, topMatch.product_name);

                    ProductCameraScanner.playBeep(true);
                    var confirmLabel = document.getElementById('ai-confirmed-label');
                    if (confirmLabel) confirmLabel.textContent = confidenceEmoji + ' ' + topMatch.product_name;
                    setAiStatus(
                        confidenceEmoji + ' Found in inventory (' + confidencePct + '% match): ' +
                        topMatch.product_name +
                        (topMatch.category ? ' · ' + topMatch.category : '') +
                        ' — review or save!',
                        'identified'
                    );
                    return;
                }

                // Case B: Weak DB match (0.35 <= score < 0.65)
                if (topMatch && topMatch.score >= 0.35) {
                    var weakNames = dbMatches.slice(0, 4).map(function(m) { return m.product_name; });
                    renderNameSuggestions(weakNames, parsed.name || '');

                    if (parsed.name && !ProductCameraScanner.isGibberishOrNoise(parsed.name) && nameInput) {
                        nameInput.value = parsed.name;
                        clearAddFieldError('add-product-name', 'add-product-name-error');
                        nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                        autoFilledCount++;
                    }
                    if (parsed.brand && supplierInput) {
                        supplierInput.value = parsed.brand;
                        supplierInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (parsed.category) { if (fillSelect(catInput, parsed.category)) autoFilledCount++; }
                    if (parsed.unit)     fillSelect(unitInput, parsed.unit);

                    renderRawOcrLines(parsed.rawLines);
                    var confirmLabel2 = document.getElementById('ai-confirmed-label');
                    if (confirmLabel2) confirmLabel2.textContent = '🟡 ' + (parsed.name || 'Product captured');
                    setAiStatus('🟡 Possible match: ' + topMatch.product_name + ' (' + Math.round(topMatch.score * 100) + '%) — verify below', 'identified');
                    return;
                }

                // Case C: No DB match -> pure label parsing
                if (parsed.name && !ProductCameraScanner.isGibberishOrNoise(parsed.name) && nameInput) {
                    nameInput.value = parsed.name;
                    clearAddFieldError('add-product-name', 'add-product-name-error');
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                    autoFilledCount++;
                }
                if (parsed.brand && supplierInput) {
                    supplierInput.value = parsed.brand;
                    supplierInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (parsed.category) { if (fillSelect(catInput, parsed.category)) autoFilledCount++; }
                if (parsed.unit)     fillSelect(unitInput, parsed.unit);

                renderNameSuggestions(parsed.suggestions, parsed.name);
                renderRawOcrLines(parsed.rawLines);

                var confirmLabel3 = document.getElementById('ai-confirmed-label');
                if (autoFilledCount > 0 && parsed.name) {
                    ProductCameraScanner.playBeep(true);
                    if (confirmLabel3) confirmLabel3.textContent = '🔴 ' + parsed.name;
                    setAiStatus('🔴 New product detected: ' + parsed.name + (parsed.category ? ' (' + parsed.category + ')' : '') + ' — review or save!', 'identified');
                } else {
                    if (confirmLabel3) confirmLabel3.textContent = 'Photo captured!';
                    setAiStatus('Photo saved — please enter product name below', 'identified');
                    if (nameInput) nameInput.focus();
                }
            }).catch(function(err) {
                console.warn('[AI OCR] DB lookup error, falling back to label parse:', err);
                if (parsed.name && !ProductCameraScanner.isGibberishOrNoise(parsed.name) && nameInput) {
                    nameInput.value = parsed.name;
                    nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                renderNameSuggestions(parsed.suggestions, parsed.name);
                renderRawOcrLines(parsed.rawLines);
                setAiStatus(parsed.name ? '✨ ' + parsed.name + ' — review or save!' : 'Photo saved — enter product name below', 'identified');
            });

        }).catch(function(err) {
            console.warn('[AI OCR] scan error:', err);
            setAiStatus('Photo saved — please enter product name below', 'identified');
        });
    }
}

function renderNameSuggestions(suggestions, activeName) {
    var container = document.getElementById('add-product-name-suggestions');
    var chipsWrap = document.getElementById('add-product-name-chips');
    if (!container || !chipsWrap) return;

    chipsWrap.innerHTML = '';
    if (!suggestions || suggestions.length === 0) {
        container.style.display = 'none';
        return;
    }

    suggestions.forEach(function(sug) {
        if (!sug) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ai-suggestion-chip';
        btn.textContent = sug;
        btn.style.cssText = 'background:#13275B; border:1px solid #2A4484; color:#E3E3E3; border-radius:16px; padding:4px 12px; font-size:11px; cursor:pointer; font-family:"Onest",sans-serif; transition:all 0.15s; line-height:1.3; text-align:left;';
        if (sug === activeName) {
            btn.style.borderColor = '#51D55A';
            btn.style.color = '#d1fae5';
            btn.style.background = 'rgba(6,95,70,0.45)';
        }
        btn.onmouseover = function() { this.style.borderColor = '#51D55A'; this.style.color = '#fff'; };
        btn.onmouseout = function() {
            var isCurrent = (document.getElementById('add-product-name')?.value === sug);
            this.style.borderColor = isCurrent ? '#51D55A' : '#2A4484';
            this.style.color = isCurrent ? '#d1fae5' : '#E3E3E3';
            this.style.background = isCurrent ? 'rgba(6,95,70,0.45)' : '#13275B';
        };
        btn.onclick = function() {
            applySuggestedName(sug);
        };
        chipsWrap.appendChild(btn);
    });

    container.style.display = 'flex';
}

function applySuggestedName(name) {
    var input = document.getElementById('add-product-name');
    if (input) {
        input.value = name;
        clearAddFieldError('add-product-name', 'add-product-name-error');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
    }
    // Update chip styling
    var chips = document.querySelectorAll('#add-product-name-chips .ai-suggestion-chip');
    chips.forEach(function(chip) {
        var isMatch = chip.textContent === name;
        chip.style.borderColor = isMatch ? '#51D55A' : '#2A4484';
        chip.style.color = isMatch ? '#d1fae5' : '#E3E3E3';
        chip.style.background = isMatch ? 'rgba(6,95,70,0.45)' : '#13275B';
    });
}

function renderRawOcrLines(lines) {
    var wrap = document.getElementById('add-ocr-raw-wrap');
    var box  = document.getElementById('add-ocr-raw-box');
    if (!wrap || !box) return;

    box.innerHTML = '';
    if (!lines || lines.length === 0) {
        wrap.style.display = 'none';
        return;
    }

    var hint = document.createElement('div');
    hint.style.cssText = 'color:#94a3b8; margin-bottom:6px; font-size:10px;';
    hint.textContent = 'Click any line below to paste it into Product Name:';
    box.appendChild(hint);

    lines.forEach(function(line) {
        var item = document.createElement('div');
        item.style.cssText = 'padding:3px 6px; border-radius:4px; cursor:pointer; transition:background 0.15s; font-family:"Onest",sans-serif;';
        item.textContent = '• ' + line;
        item.onmouseover = function() { this.style.background = 'rgba(42,68,132,0.45)'; };
        item.onmouseout  = function() { this.style.background = 'transparent'; };
        item.onclick = function() {
            applySuggestedName(line);
        };
        box.appendChild(item);
    });

    wrap.style.display = 'block';
}

function toggleOcrRawText() {
    var box = document.getElementById('add-ocr-raw-box');
    var btn = document.getElementById('add-ocr-toggle-btn');
    if (!box || !btn) return;
    if (box.style.display === 'none') {
        box.style.display = 'block';
        btn.innerHTML = 'Hide text read from packaging &uarr;';
    } else {
        box.style.display = 'none';
        btn.innerHTML = 'Show text read from packaging &darr;';
    }
}

function aiOnIdentified() {
    // Kept for compatibility — now called directly by aiCaptureFrame after photo is taken
    var confirmLabel = document.getElementById('ai-confirmed-label');
    if (confirmLabel) confirmLabel.textContent = 'Photo captured!';
    setAiStatus('Reference photo saved — scanning product label...', 'scanning');
    var retakeBtn = document.getElementById('ai-retake-btn');
    if (retakeBtn) retakeBtn.style.display = 'inline-block';
    var snapBtn = document.getElementById('ai-capture-snap-btn');
    if (snapBtn) snapBtn.style.display = 'none';
    var bottomBar = document.getElementById('ai-camera-bottom-bar');
    if (bottomBar) bottomBar.style.display = 'none';
}

function aiRetake() {
    // Reset captured image
    var imgField = document.getElementById('add-product-image-b64');
    if (imgField) imgField.value = '';

    // Hide retake btn, show capture snap btn again
    var retakeBtn = document.getElementById('ai-retake-btn');
    if (retakeBtn) retakeBtn.style.display = 'none';

    var bottomBar = document.getElementById('ai-camera-bottom-bar');
    if (bottomBar) bottomBar.style.display = 'flex';

    // Restart camera
    aiStartScan();
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
    submitBtn.disabled = false;
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

// validateAddProductForm
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


function resetAddProductForm() {
    var form = document.getElementById('add-product-form');
    if (!form) return;

    form.reset();
    aiProductIdentified = false;
    addFormSubmitting = false;
    clearAddFormErrors();
    setAddSubmitState(false);
    // Stop any running camera stream
    _stopAddProductCamera();

    // Reset AI camera UI
    var video        = document.getElementById('ai-video');
    var canvas       = document.getElementById('ai-snapshot-canvas');
    var overlay      = document.getElementById('ai-confirmed-overlay');
    var scanOverlay  = document.getElementById('ai-scan-overlay');
    var retakeBtn    = document.getElementById('ai-retake-btn');
    var captureBtn   = document.getElementById('ai-capture-btn');
    var uploadBtn    = document.getElementById('ai-upload-btn');
    var snapBtn      = document.getElementById('ai-capture-snap-btn');
    var cameraArea   = document.getElementById('ai-camera-area');
    var previewWrap  = document.getElementById('ai-upload-preview-wrap');
    var previewImg   = document.getElementById('ai-upload-preview-img');
    var uploadInput  = document.getElementById('ai-upload-input');
    var mobileCamInput = document.getElementById('ai-mobile-cam-input');
    var imgField     = document.getElementById('add-product-image-b64');
    var classField   = document.getElementById('add-ai-class-label');
    var bottomBar    = document.getElementById('ai-camera-bottom-bar');

    if (video)       video.style.display       = 'block';
    if (canvas)      canvas.style.display      = 'none';
    if (overlay)     overlay.style.display     = 'none';
    if (scanOverlay) scanOverlay.style.display = 'flex';
    if (retakeBtn)   retakeBtn.style.display   = 'none';
    if (captureBtn)  { captureBtn.style.display = 'inline-flex'; captureBtn.disabled = false; }
    if (uploadBtn)   uploadBtn.style.display   = 'inline-flex';
    if (snapBtn)     snapBtn.style.display     = 'none';
    if (cameraArea)  cameraArea.style.display  = 'none';
    if (bottomBar)   bottomBar.style.display   = 'none';
    if (previewWrap) previewWrap.style.display = 'none';
    if (previewImg)  previewImg.src            = '';
    if (uploadInput) uploadInput.value         = '';
    if (mobileCamInput) mobileCamInput.value   = '';
    if (imgField)    imgField.value            = '';
    if (classField)  classField.value          = '';

    var suggestionsContainer = document.getElementById('add-product-name-suggestions');
    var chipsWrap            = document.getElementById('add-product-name-chips');
    var rawWrap              = document.getElementById('add-ocr-raw-wrap');
    var rawBox               = document.getElementById('add-ocr-raw-box');
    if (suggestionsContainer) suggestionsContainer.style.display = 'none';
    if (chipsWrap)            chipsWrap.innerHTML = '';
    if (rawWrap)              rawWrap.style.display = 'none';
    if (rawBox)             { rawBox.innerHTML = ''; rawBox.style.display = 'none'; }

    setAiStatus('Take photo or upload packaging — auto-fills product details', 'idle');
}

var _addProductStream = null;

// ── Confirmation Dialog & Form Dirty Validation ─────────────
var _activeConfirmCallback = null;
var _activeCancelCallback = null;

function showActionConfirmDialog(options) {
    var overlay   = document.getElementById('action-confirm-overlay');
    var titleEl   = document.getElementById('action-confirm-title');
    var msgEl     = document.getElementById('action-confirm-msg');
    var iconWrap  = document.getElementById('action-confirm-icon-wrap');
    var okBtn     = document.getElementById('action-confirm-ok-btn');
    var cancelBtn = document.getElementById('action-confirm-cancel-btn');

    if (!overlay) {
        if (confirm((options.title ? options.title + '\n\n' : '') + (options.message || ''))) {
            if (options.onConfirm) options.onConfirm();
        } else {
            if (options.onCancel) options.onCancel();
        }
        return;
    }

    if (titleEl) titleEl.textContent = options.title || 'Are you sure?';
    if (msgEl) msgEl.textContent = options.message || '';
    if (okBtn) okBtn.textContent = options.confirmText || 'Confirm';
    if (cancelBtn) cancelBtn.textContent = options.cancelText || 'Cancel';

    var isWarning = options.type === 'warning';
    if (iconWrap) {
        if (isWarning) {
            iconWrap.style.background = 'rgba(245, 158, 11, 0.15)';
            iconWrap.style.border = '1px solid rgba(245, 158, 11, 0.35)';
            iconWrap.innerHTML = '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        } else {
            iconWrap.style.background = 'rgba(239, 68, 68, 0.15)';
            iconWrap.style.border = '1px solid rgba(239, 68, 68, 0.35)';
            iconWrap.innerHTML = '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>';
        }
    }

    if (okBtn) {
        if (isWarning) {
            okBtn.style.background = 'linear-gradient(135deg, #F59E0B, #D97706)';
            okBtn.style.border = '1px solid #B45309';
            okBtn.style.boxShadow = '0 4px 14px rgba(245, 158, 11, 0.35)';
            okBtn.style.color = '#ffffff';
        } else {
            okBtn.style.background = 'linear-gradient(135deg, #EF4444, #DC2626)';
            okBtn.style.border = '1px solid #B91C1C';
            okBtn.style.boxShadow = '0 4px 14px rgba(239, 68, 68, 0.35)';
            okBtn.style.color = '#ffffff';
        }
    }

    _activeConfirmCallback = options.onConfirm || null;
    _activeCancelCallback = options.onCancel || null;

    overlay.classList.add('active');
}

function closeActionConfirmDialog() {
    var overlay = document.getElementById('action-confirm-overlay');
    if (overlay) overlay.classList.remove('active');
    _activeConfirmCallback = null;
    _activeCancelCallback = null;
}

function hasAddProductFormData() {
    // 1. Check if an image is uploaded or captured
    var imgB64 = document.getElementById('add-product-image-b64');
    if (imgB64 && imgB64.value && imgB64.value.trim() !== '') return true;

    var previewImg = document.getElementById('ai-upload-preview-img');
    if (previewImg && previewImg.src) {
        var src = previewImg.getAttribute('src') || '';
        if (src.trim() !== '' && !src.endsWith('products.php') && src !== window.location.href) {
            return true;
        }
    }

    var previewWrap = document.getElementById('ai-upload-preview-wrap');
    if (previewWrap && previewWrap.style.display !== 'none' && previewWrap.style.display !== '') {
        return true;
    }

    var uploadInput = document.getElementById('ai-upload-input');
    if (uploadInput && uploadInput.files && uploadInput.files.length > 0) return true;

    var mobileCam = document.getElementById('ai-mobile-cam-input');
    if (mobileCam && mobileCam.files && mobileCam.files.length > 0) return true;

    // 2. Check general product fields
    var name = document.getElementById('add-product-name');
    if (name && name.value && name.value.trim() !== '') return true;

    var cat = document.getElementById('add-category');
    if (cat && cat.value && cat.value.trim() !== '') return true;

    var sup = document.getElementById('add-supplier');
    if (sup && sup.value && sup.value.trim() !== '') return true;

    // 3. Check stock & storage fields
    var qty = document.getElementById('add-quantity');
    if (qty && qty.value !== undefined && qty.value !== null && qty.value.toString().trim() !== '') return true;

    var unit = document.getElementById('add-unit');
    if (unit && unit.value && unit.value.trim() !== '') return true;

    var loc = document.getElementById('add-location');
    if (loc && loc.value && loc.value.trim() !== '') return true;

    // 4. Check price (default is 0.00)
    var price = document.getElementById('add-price');
    if (price && price.value !== undefined && price.value !== null) {
        var pVal = price.value.toString().trim();
        if (pVal !== '' && pVal !== '0' && pVal !== '0.00') return true;
    }

    // 5. Check reorder point (default is 10)
    var reorder = document.getElementById('add-reorder-point');
    if (reorder && reorder.value !== undefined && reorder.value !== null) {
        var rVal = reorder.value.toString().trim();
        if (rVal !== '' && rVal !== '10') return true;
    }

    // 6. Check shelf life
    var shelf = document.getElementById('add-shelf-life');
    if (shelf && shelf.value && shelf.value.toString().trim() !== '') return true;

    // 7. Check camera live view active
    var camArea = document.getElementById('ai-camera-area');
    if (camArea && camArea.style.display !== 'none' && camArea.style.display !== '') return true;

    return false;
}

function openAddModal() {
    resetAddProductForm();
    // Unlock form immediately — photo capture is optional
    setAddFormFieldsEnabled(true);
    const addScroll = document.querySelector('#add-modal-overlay .modal-body-scroll');
    if (addScroll) addScroll.scrollTop = 0;
    if (window.matchMedia('(max-width: 767px)').matches) {
        document.body.style.overflow = 'hidden';
    }
    document.getElementById('add-modal-overlay').classList.add('active');
}

function closeAddModal(force) {
    if (!force && hasAddProductFormData()) {
        showActionConfirmDialog({
            title: 'Discard Unsaved Product?',
            message: 'You have entered details for this product that will be lost if you close now. Are you sure you want to discard your changes?',
            confirmText: 'Discard & Close',
            cancelText: 'Keep Editing',
            type: 'danger',
            onConfirm: function() {
                closeAddModal(true);
            }
        });
        return;
    }
    _stopAddProductCamera();
    document.getElementById('add-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
    resetAddProductForm();
}

function _stopAddProductCamera() {
    if (_addProductStream) {
        _addProductStream.getTracks().forEach(function(t) { t.stop(); });
        _addProductStream = null;
    }
}

function _cancelAddProductCamera() {
    _stopAddProductCamera();
    var cameraArea  = document.getElementById('ai-camera-area');
    var captureBtn  = document.getElementById('ai-capture-btn');
    var uploadBtn   = document.getElementById('ai-upload-btn');
    var bottomBar   = document.getElementById('ai-camera-bottom-bar');

    if (cameraArea) cameraArea.style.display = 'none';
    if (captureBtn) captureBtn.style.display = 'inline-flex';
    if (uploadBtn)  uploadBtn.style.display  = 'inline-flex';
    if (bottomBar)  bottomBar.style.display  = 'none';
    setAiStatus('Take photo or upload packaging — auto-fills product details', 'idle');
}

function aiStartScan() {
    var captureBtn   = document.getElementById('ai-capture-btn');
    var uploadBtn    = document.getElementById('ai-upload-btn');
    var snapBtn      = document.getElementById('ai-capture-snap-btn');
    var cameraArea   = document.getElementById('ai-camera-area');
    var bottomBar    = document.getElementById('ai-camera-bottom-bar');
    var previewWrap  = document.getElementById('ai-upload-preview-wrap');
    var video        = document.getElementById('ai-video');
    var overlay      = document.getElementById('ai-confirmed-overlay');
    var scanOverlay  = document.getElementById('ai-scan-overlay');
    var canvas       = document.getElementById('ai-snapshot-canvas');

    // On non-HTTPS mobile connections (e.g. phone accessing via local IP),
    // navigator.mediaDevices is blocked by the browser. Seamlessly trigger native phone camera!
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        var mobileCam = document.getElementById('ai-mobile-cam-input');
        if (mobileCam) {
            setAiStatus('Opening phone camera…', 'scanning');
            mobileCam.click();
            return;
        }
        setAiStatus('Camera not supported on this connection. Please use Upload Image.', 'error');
        alert('Camera requires HTTPS or localhost in modern browsers. Please use "Upload Image" to select or take any photo.');
        return;
    }

    if (captureBtn)  captureBtn.style.display  = 'none';
    if (uploadBtn)   uploadBtn.style.display   = 'none';
    if (previewWrap) previewWrap.style.display = 'none';
    if (cameraArea)  cameraArea.style.display  = 'block';
    if (bottomBar)   bottomBar.style.display   = 'flex';
    if (overlay)     overlay.style.display     = 'none';
    if (canvas)      canvas.style.display      = 'none';
    if (video)       video.style.display       = 'block';
    if (scanOverlay) scanOverlay.style.display = 'flex';
    setAiStatus('Opening camera…', 'scanning');

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
    }).catch(function() {
        // Fallback for laptops and desktop webcams that do not support 'environment'
        return navigator.mediaDevices.getUserMedia({ video: true });
    }).then(function(stream) {
        _addProductStream = stream;
        video.srcObject   = stream;
        video.setAttribute('playsinline', '');
        video.muted = true;
        var playPromise = video.play();
        if (playPromise !== undefined) {
            playPromise.catch(function(e) { console.warn('Video play error:', e); });
        }
        if (snapBtn)   snapBtn.style.display   = 'inline-flex';
        if (bottomBar) bottomBar.style.display = 'flex';
        setAiStatus('Camera ready — point at packaging or label, or click Capture', 'scanning');
    }).catch(function(err) {
        // If laptop/phone denied permission or threw error, offer mobile file/camera capture
        var mobileCam = document.getElementById('ai-mobile-cam-input');
        if (mobileCam && /NotAllowedError|NotFoundError|OverconstrainedError/i.test(err.name || '')) {
            mobileCam.click();
            return;
        }
        if (captureBtn) captureBtn.style.display = 'inline-flex';
        if (uploadBtn)  uploadBtn.style.display  = 'inline-flex';
        if (cameraArea) cameraArea.style.display = 'none';
        if (bottomBar)  bottomBar.style.display  = 'none';
        setAiStatus('Camera: ' + (err.message || err) + ' — please use Upload Image', 'error');
    });
}

function aiUploadImage(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];

    // Validate file type
    if (!file.type.startsWith('image/')) {
        setAiStatus('Please select a valid image file (JPG, PNG, WEBP…)', 'error');
        return;
    }
    // Warn if file is unusually large (> 8 MB)
    if (file.size > 8 * 1024 * 1024) {
        setAiStatus('Image is very large (' + Math.round(file.size / 1024 / 1024) + ' MB) — may be slow to save.', 'scanning');
    }

    var reader = new FileReader();
    reader.onload = function(e) {
        var b64 = e.target.result; // data:image/...;base64,...
        var imgField = document.getElementById('add-product-image-b64');
        if (imgField) imgField.value = b64;

        // Show preview
        var previewWrap = document.getElementById('ai-upload-preview-wrap');
        var previewImg  = document.getElementById('ai-upload-preview-img');
        var cameraArea  = document.getElementById('ai-camera-area');
        var captureBtn  = document.getElementById('ai-capture-btn');
        var uploadBtn   = document.getElementById('ai-upload-btn');
        var retakeBtn   = document.getElementById('ai-retake-btn');
        var snapBtn     = document.getElementById('ai-capture-snap-btn');

        // Stop camera if it was running
        _stopAddProductCamera();
        if (cameraArea)  cameraArea.style.display  = 'none';
        if (snapBtn)     snapBtn.style.display      = 'none';
        if (captureBtn)  captureBtn.style.display   = 'none';
        if (uploadBtn)   uploadBtn.style.display    = 'none';

        if (previewImg)  previewImg.src             = b64;
        if (previewWrap) previewWrap.style.display  = 'block';
        if (retakeBtn)   retakeBtn.style.display    = 'inline-flex';

        setAiStatus('Image uploaded — scanning label with AI OCR...', 'scanning');
        aiProcessOcr(b64, false);
    };
    reader.onerror = function() {
        setAiStatus('Failed to read the selected file. Please try again.', 'error');
    };
    reader.readAsDataURL(file);
}

function aiRemoveUploadedImage(force) {
    if (!force) {
        showActionConfirmDialog({
            title: 'Remove Product Image?',
            message: 'Are you sure you want to remove the selected product photo? The image preview and reference data will be cleared.',
            confirmText: 'Remove Image',
            cancelText: 'Keep Image',
            type: 'warning',
            onConfirm: function() {
                aiRemoveUploadedImage(true);
            }
        });
        return;
    }

    var previewWrap    = document.getElementById('ai-upload-preview-wrap');
    var previewImg     = document.getElementById('ai-upload-preview-img');
    var uploadInput    = document.getElementById('ai-upload-input');
    var mobileCamInput = document.getElementById('ai-mobile-cam-input');
    var imgField       = document.getElementById('add-product-image-b64');
    var captureBtn     = document.getElementById('ai-capture-btn');
    var uploadBtn      = document.getElementById('ai-upload-btn');
    var retakeBtn      = document.getElementById('ai-retake-btn');

    if (previewWrap) previewWrap.style.display = 'none';
    if (previewImg)  previewImg.src            = '';
    if (uploadInput) uploadInput.value         = '';
    if (mobileCamInput) mobileCamInput.value   = '';
    if (imgField)    imgField.value            = '';
    if (retakeBtn)   retakeBtn.style.display   = 'none';
    if (captureBtn)  captureBtn.style.display  = 'inline-flex';
    if (uploadBtn)   uploadBtn.style.display   = 'inline-flex';
    setAiStatus('Take photo or upload packaging — auto-fills product details', 'idle');
}

function promptArchive(id, name) {
    document.body.style.overflow = 'hidden';
    document.getElementById('modal-archive-id').value = id;
    document.getElementById('archive-product-name').textContent = name || ('Product #' + id);
    document.getElementById('archive-modal-overlay').classList.add('active');
}
function closeArchiveModal() {
    document.getElementById('archive-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
}

function promptRestore(id, name) {
    document.body.style.overflow = 'hidden';
    document.getElementById('modal-restore-id').value = id;
    document.getElementById('restore-product-name').textContent = name || ('Product #' + id);
    document.getElementById('restore-modal-overlay').classList.add('active');
}
function closeRestoreModal() {
    document.getElementById('restore-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
}

// ── Edit Product Modal ──────────────────────────────────────
let editModalSubmitting = false;

function openEditModal(data) {
    // data is the row's dataset (DOMStringMap)
    document.getElementById('edit-id').value          = data.id;
    document.getElementById('edit-name').value        = data.name || '';
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

    // ── Reset image section ──────────────────────────────────────────────────
    var actionField = document.getElementById('edit-image-action');
    var imgField    = document.getElementById('edit-image-b64');
    var fileInput   = document.getElementById('edit-image-file-input');
    var statusLabel = document.getElementById('edit-image-status-label');
    var previewImg  = document.getElementById('edit-image-preview-img');

    if (actionField) actionField.value = 'keep';
    if (imgField)    imgField.value    = '';
    if (fileInput)   fileInput.value   = '';
    if (statusLabel) statusLabel.textContent = 'Current image';

    // Probe whether a product image exists on the server
    var imgUrl = 'uploads/products/' + data.id + '.jpg?_=' + Date.now();
    var probe  = new Image();
    probe.onload  = function() {
        if (previewImg) previewImg.src = imgUrl;
        editSetImageState(true);
    };
    probe.onerror = function() {
        if (previewImg) previewImg.src = '';
        editSetImageState(false);
    };
    probe.src = imgUrl;

    const editScroll = document.querySelector('#edit-modal-overlay .modal-body-scroll');
    if (editScroll) editScroll.scrollTop = 0;
    if (window.matchMedia('(max-width: 767px)').matches) {
        document.body.style.overflow = 'hidden';
    }
    document.getElementById('edit-modal-overlay').classList.add('active');
    setTimeout(() => {
        const nameInput = document.getElementById('edit-name');
        if (nameInput) nameInput.focus({ preventScroll: true });
    }, 60);
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
            const confirmOverlay = document.getElementById('action-confirm-overlay');
            if (confirmOverlay && confirmOverlay.classList.contains('active')) {
                const cancelBtn = document.getElementById('action-confirm-cancel-btn');
                if (cancelBtn) cancelBtn.click();
                else closeActionConfirmDialog();
                return;
            }
            const addOverlay = document.getElementById('add-modal-overlay');
            if (addOverlay && addOverlay.classList.contains('active')) {
                closeAddModal();
                return;
            }
            const overlay = document.getElementById('edit-modal-overlay');
            if (overlay && overlay.classList.contains('active')) {
                closeEditModal();
                return;
            }
            const archiveOverlay = document.getElementById('archive-modal-overlay');
            if (archiveOverlay && archiveOverlay.classList.contains('active')) {
                closeArchiveModal();
                return;
            }
            const restoreOverlay = document.getElementById('restore-modal-overlay');
            if (restoreOverlay && restoreOverlay.classList.contains('active')) {
                closeRestoreModal();
                return;
            }
        }
    });

    // Close on backdrop click
    document.getElementById('edit-modal-overlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    document.getElementById('add-modal-overlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });
    document.getElementById('action-confirm-overlay')?.addEventListener('click', function(e) {
        if (e.target === this) {
            const cancelBtn = document.getElementById('action-confirm-cancel-btn');
            if (cancelBtn) cancelBtn.click();
            else closeActionConfirmDialog();
        }
    });

    // Action confirmation modal button events
    document.getElementById('action-confirm-cancel-btn')?.addEventListener('click', function() {
        var cb = _activeCancelCallback;
        closeActionConfirmDialog();
        if (cb) cb();
    });
    document.getElementById('action-confirm-ok-btn')?.addEventListener('click', function() {
        var cb = _activeConfirmCallback;
        closeActionConfirmDialog();
        if (cb) cb();
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

// Filter & Search Feedback Toast
document.addEventListener('DOMContentLoaded', function() {
    if (window.__iwFlashPending) return;

    const urlParams = new URLSearchParams(window.location.search);
    const q = (urlParams.get('q') || '').trim();
    const cat = (urlParams.get('category') || '').trim();
    const filter = (urlParams.get('filter') || '').trim();
    const archived = urlParams.get('archived') === '1';

    if (!q && !cat && !filter && !archived) return;

    const count = <?php echo (int)$total_items; ?>;
    const parts = [];

    if (q) parts.push(`"${q}"`);
    if (cat) parts.push(cat);
    if (filter) {
        const filterNames = {
            'low_stock': 'Low Stock',
            'low': 'Low Stock',
            'out_of_stock': 'Out of Stock',
            'out': 'Out of Stock',
            'in_stock': 'In Stock',
            'good': 'In Stock'
        };
        parts.push(filterNames[filter] || filter);
    }
    if (archived) parts.push('Archived');

    const desc = parts.join(' • ');

    if (count === 0) {
        showToast(`No products found for ${desc}`, 'warning');
    } else {
        const itemWord = count === 1 ? 'product' : 'products';
        showToast(`Showing ${count.toLocaleString()} ${itemWord} for ${desc}`, 'info');
    }
});

<?php if (($_GET['add'] ?? '') === '1'): ?>
openAddModal();
<?php endif; ?>





document.getElementById('add-product-form')?.addEventListener('submit', function (e) {
    if (addFormSubmitting) {
        e.preventDefault();
        return;
    }

    // Photo capture is optional — no scan gate required

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


</script>

<script>

// ── Edit Product: image helpers ─────────────────────────────────────────────
function editSetImageState(hasImage) {
    var currentWrap  = document.getElementById('edit-image-current-wrap');
    var emptyWrap    = document.getElementById('edit-image-empty-wrap');
    var replaceBar   = document.getElementById('edit-image-replace-bar');

    if (hasImage) {
        if (currentWrap) currentWrap.style.display = 'block';
        if (emptyWrap)   emptyWrap.style.display   = 'none';
        if (replaceBar)  replaceBar.style.display  = 'flex';
    } else {
        if (currentWrap) currentWrap.style.display = 'none';
        if (emptyWrap)   emptyWrap.style.display   = 'flex';
        if (replaceBar)  replaceBar.style.display  = 'none';
    }
}

function editHandleImageUpload(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (!file.type.startsWith('image/')) return;

    var reader = new FileReader();
    reader.onload = function(e) {
        var b64 = e.target.result;
        var imgField   = document.getElementById('edit-image-b64');
        var actionField = document.getElementById('edit-image-action');
        var previewImg  = document.getElementById('edit-image-preview-img');
        var statusLabel = document.getElementById('edit-image-status-label');

        if (imgField)    imgField.value    = b64;
        if (actionField) actionField.value = 'replace';
        if (previewImg)  previewImg.src    = b64;
        if (statusLabel) statusLabel.textContent = 'New image selected';

        editSetImageState(true);
    };
    reader.readAsDataURL(file);
}

function editRemoveImage(force) {
    if (!force) {
        showActionConfirmDialog({
            title: 'Remove Product Image?',
            message: 'Are you sure you want to remove the image for this product? This will take effect when you save.',
            confirmText: 'Remove Image',
            cancelText: 'Keep Image',
            type: 'warning',
            onConfirm: function() {
                editRemoveImage(true);
            }
        });
        return;
    }

    var actionField = document.getElementById('edit-image-action');
    var imgField    = document.getElementById('edit-image-b64');
    var fileInput   = document.getElementById('edit-image-file-input');
    var previewImg  = document.getElementById('edit-image-preview-img');

    if (actionField) actionField.value = 'remove';
    if (imgField)    imgField.value    = '';
    if (fileInput)   fileInput.value   = '';
    if (previewImg)  previewImg.src    = '';

    editSetImageState(false);
}

// ── Inventory View Mode Toggle (Grid vs Table) ──────────────────────────────
function syncInventoryView() {
    var view = 'grid';
    try {
        view = localStorage.getItem('inv_view_mode') || 'grid';
    } catch (e) {}
    setInventoryView(view, false);
}

function setInventoryView(view, save) {
    if (save === undefined) save = true;
    if (!view) view = 'grid';
    if (save) {
        try { localStorage.setItem('inv_view_mode', view); } catch (e) {}
    }

    var container = document.getElementById('inventory-products-content');
    var gridEl    = document.getElementById('inventory-card-grid');
    var tableEl   = document.getElementById('inventory-desktop-table');
    var btnGrid   = document.getElementById('btn-view-grid');
    var btnTable  = document.getElementById('btn-view-table');

    if (view === 'table') {
        if (container) {
            container.classList.remove('view-mode-grid');
            container.classList.add('view-mode-table');
        }
        if (gridEl)  gridEl.style.setProperty('display', 'none', 'important');
        if (tableEl) tableEl.style.setProperty('display', 'block', 'important');
        if (btnGrid) btnGrid.classList.remove('active');
        if (btnTable) btnTable.classList.add('active');
    } else {
        if (container) {
            container.classList.remove('view-mode-table');
            container.classList.add('view-mode-grid');
        }
        if (gridEl)  gridEl.style.removeProperty('display');
        if (tableEl) tableEl.style.setProperty('display', 'none', 'important');
        if (btnGrid) btnGrid.classList.add('active');
        if (btnTable) btnTable.classList.remove('active');
    }
}

// ── In-place Ajax Pagination (No Full Page Reload) ───────────────────────────
async function loadInventoryPage(url, pushState = true) {
    const contentEl = document.getElementById('inventory-products-content') || document.getElementById('inventory-card-grid');
    const pagEl     = document.getElementById('inventory-pagination-container');

    if (contentEl) contentEl.style.transition = 'opacity 0.2s ease';
    if (pagEl)     pagEl.style.transition     = 'opacity 0.2s ease';

    if (contentEl) contentEl.style.opacity = '0.4';
    if (pagEl)     pagEl.style.opacity     = '0.4';

    try {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Network response was not ok');
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newContent = doc.getElementById('inventory-products-content') || doc.getElementById('inventory-card-grid');
        const newPag     = doc.getElementById('inventory-pagination-container');

        if (contentEl && newContent) {
            contentEl.innerHTML = newContent.innerHTML;
            syncInventoryView();
        }
        if (pagEl && newPag) {
            pagEl.innerHTML = newPag.innerHTML;
        } else if (pagEl && !newPag) {
            pagEl.innerHTML = '';
        }

        // Update items count badge if present in header
        const currentCountBadge = document.querySelector('h3 span.text-\\[\\#51D55A\\]');
        const newCountBadge     = doc.querySelector('h3 span.text-\\[\\#51D55A\\]');
        if (currentCountBadge && newCountBadge) {
            currentCountBadge.innerHTML = newCountBadge.innerHTML;
        }

        // Update summary cards if present
        const currentSummary = document.getElementById('inventory-summary-cards');
        const newSummary     = doc.getElementById('inventory-summary-cards');
        if (currentSummary && newSummary) {
            currentSummary.innerHTML = newSummary.innerHTML;
        }

        if (pushState) {
            window.history.pushState({ url: url }, '', url);
        }

        // Smooth scroll to top of inventory card
        const topAnchor = document.querySelector('.premium-card');
        if (topAnchor) {
            topAnchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } catch (err) {
        console.warn('In-place pagination error, falling back to direct navigation:', err);
        window.location.href = url;
    } finally {
        if (contentEl) contentEl.style.opacity = '1';
        if (pagEl)     pagEl.style.opacity     = '1';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    syncInventoryView();

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.pagination-link');
        if (btn && btn.dataset.url) {
            e.preventDefault();
            loadInventoryPage(btn.dataset.url);
        }
    });

    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.url) {
            loadInventoryPage(e.state.url, false);
        } else {
            loadInventoryPage(window.location.href, false);
        }
    });
});
</script>
<script src="src/scripts/tesseract.min.js"></script>
<script src="src/scripts/product-camera-scanner.js?v=<?php echo time(); ?>"></script>

<!-- TensorFlow.js + Image Similarity Scanner (MobileNetV3-Large) -->
<!-- Load TF.js from CDN; defer model initialisation until the add-product panel is opened -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js"></script>
<script src="src/scripts/image-scanner.js?v=<?php echo time(); ?>"></script>
<script>
(function () {
    'use strict';

    var _imgScannerReady = false;
    var _imgScannerLoading = false;

    /**
     * Lazily load the MobileNetV3-Large model the first time the AI camera
     * section becomes visible. Avoids blocking page load with a ~6MB download.
     */
    function ensureImageScannerReady() {
        if (_imgScannerReady || _imgScannerLoading) return;
        if (typeof ImageScanner === 'undefined' || typeof tf === 'undefined') return;
        _imgScannerLoading = true;

        ImageScanner.init({
            onStatusChange: function (state, msg) {
                // Forward status to the product page AI status bar if visible
                var dot   = document.getElementById('ai-status-dot');
                var label = document.getElementById('ai-status-text');
                if (label && document.getElementById('ai-capture-block') &&
                    document.getElementById('ai-capture-block').closest('.modal-visible, .panel-open, [style*="display: block"], [style*="display:block"]')) {
                    if (label && state !== 'idle') label.textContent = msg;
                }
                if (state === 'ready') {
                    _imgScannerReady  = true;
                    _imgScannerLoading = false;
                }
                if (state === 'error') {
                    _imgScannerLoading = false;
                }
            },
            onEmbeddingsReady: function (count) {
                console.info('[ImageScanner] Fingerprints ready for', count, 'products');
            }
        });

        ImageScanner.loadModel().catch(function (e) {
            console.warn('[ImageScanner] Model load failed — visual matching unavailable:', e.message || e);
            _imgScannerLoading = false;
        });
    }

    /**
     * Call this after a product is positively identified (OCR or AI vision).
     * Feeds the match score back to ImageScanner for adaptive threshold calibration.
     *
     * @param {number|string} productId
     * @param {number}        score       raw cosine score (0–1); pass 1.0 for positive confirms
     */
    window.notifyImageScannerSuccess = function (productId, score) {
        if (typeof ImageScanner !== 'undefined') {
            ImageScanner.recordSuccess(productId, score || 1.0);
        }
    };

    // Trigger lazy model load when the add-product drawer/modal opens
    document.addEventListener('DOMContentLoaded', function () {
        // Watch for the add-product panel becoming visible
        var addPanel = document.getElementById('add-product-panel') ||
                       document.getElementById('add-product-form')  ||
                       document.getElementById('ai-capture-block');

        if (addPanel) {
            // MutationObserver watches for display/class changes that reveal the panel
            var observer = new MutationObserver(function () {
                var style = window.getComputedStyle(addPanel);
                if (style.display !== 'none' && style.visibility !== 'hidden') {
                    ensureImageScannerReady();
                }
            });
            observer.observe(addPanel, { attributes: true, attributeFilter: ['style', 'class'] });
        }

        // Also trigger on the "Add Product" button click
        var addBtns = document.querySelectorAll('[onclick*="openAddModal"], [onclick*="showAddPanel"], [data-action="add-product"], #open-add-product-btn');
        addBtns.forEach(function (btn) {
            btn.addEventListener('click', ensureImageScannerReady);
        });

        // Fallback: load after 3s idle so it's ready before the user clicks
        setTimeout(ensureImageScannerReady, 3000);
    });
}());
</script>

<?php renderLayoutFooter(); ?>