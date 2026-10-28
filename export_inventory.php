<?php
/**
 * export_inventory.php
 * Downloads the current Inventory as a CSV file, applying the same
 * filters (q, category, filter/status) as the Inventory page (products.php).
 *
 * Access control mirrors products.php:
 *   - Requires an active session with Username set.
 *   - Allowed roles: admin, manager, staff.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth guard (same as products.php) ─────────────────────────────────────────
if (!isset($_SESSION['Username'])) {
    header('Location: signin.php');
    exit();
}
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin', 'manager', 'staff'])) {
    header('Location: dash_staff.php');
    exit();
}

require_once __DIR__ . '/config/db.php';

// ── Sanitise & validate input filters ────────────────────────────────────────
$q             = trim((string)($_GET['q']        ?? ($_GET['search']   ?? '')));
$categoryParam = trim((string)($_GET['category'] ?? ''));
$statusRaw     = strtolower(trim((string)($_GET['filter'] ?? ($_GET['status'] ?? ''))));

// ── Build WHERE clause (mirrors InventoryRepository::getProducts) ─────────────
$where  = ['p.archived = 0'];
$params = [];
$types  = '';

if ($q !== '') {
    $where[]  = '(p.product_name LIKE ? OR p.supplier LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($categoryParam !== '') {
    $where[]  = 'p.category = ?';
    $params[] = $categoryParam;
    $types   .= 's';
}

if ($statusRaw !== '') {
    if (in_array($statusRaw, ['low', 'low_stock'])) {
        $where[] = 'p.quantity > 0 AND p.quantity <= p.reorder_point';
    } elseif (in_array($statusRaw, ['out', 'out_of_stock'])) {
        $where[] = 'p.quantity <= 0';
    } elseif (in_array($statusRaw, ['good', 'in_stock'])) {
        $where[] = 'p.quantity > p.reorder_point';
    } elseif ($statusRaw === 'attention') {
        $where[] = 'p.quantity <= p.reorder_point';
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// ── Main query (no LIMIT / OFFSET — export all matching rows) ─────────────────
$sql = "
    SELECT
        CONCAT('P-', LPAD(p.product_id, 4, '0'))                            AS sku,
        p.product_name                                                       AS name,
        p.category,
        p.supplier,
        p.location,
        p.quantity                                                           AS qty,
        p.unit                                                               AS uom,
        p.reorder_point,
        p.price,
        COALESCE(
            (SELECT MAX(pb.received_at)
             FROM product_batches pb
             WHERE pb.product_id = p.product_id),
            (SELECT MAX(t.transaction_date)
             FROM transactions t
             WHERE t.product_id = p.product_id AND t.transaction_type = 'stock_in'),
            p.created_at
        )                                                                    AS date_received,
        (SELECT MIN(b.expiry_date)
         FROM product_batches b
         WHERE b.product_id = p.product_id AND b.qty > 0)                   AS next_expiry,
        (SELECT COUNT(*)
         FROM product_batches b
         WHERE b.product_id = p.product_id AND b.qty > 0)                   AS batch_count
    FROM products p
    {$whereSql}
    ORDER BY p.product_id ASC
";

if ($types !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}

// ── Helper: formula-injection guard ───────────────────────────────────────────
function csvSafe(?string $value): string
{
    if ($value === null) {
        return '';
    }
    if (in_array(mb_substr($value, 0, 1), ['=', '+', '-', '@'])) {
        return "'" . $value;
    }
    return $value;
}

// ── Determine stock status label (mirrors UI logic) ───────────────────────────
function stockStatus(int $qty, int $reorderPoint): string
{
    if ($qty <= 0)             { return 'OUT OF STOCK'; }
    if ($qty <= $reorderPoint) { return 'LOW STOCK'; }
    return 'GOOD';
}

// ── Collect rows (needed for audit count before headers are sent) ─────────────
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$rowCount = count($rows);

// ── Audit log ─────────────────────────────────────────────────────────────────
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    $action  = 'Exported inventory report (' . $rowCount . ' item' . ($rowCount !== 1 ? 's' : '') . ')';
    $logStmt = $conn->prepare('INSERT INTO logs (user_id, action) VALUES (?, ?)');
    if ($logStmt) {
        $logStmt->bind_param('is', $userId, $action);
        $logStmt->execute();
        $logStmt->close();
    }
}

// ── HTTP headers ──────────────────────────────────────────────────────────────
$filename = 'IntelliWare-IMS-inventory-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ── Open output stream and write CSV ─────────────────────────────────────────
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

// Header row: Exact requested order: SKU, Name, Category, Supplier, Location, Total Stock, Batches / Expiry, Price, Unit, Reorder, Status
fputcsv($out, [
    'SKU', 'Name', 'Category', 'Supplier', 'Location',
    'Total Stock', 'Batches / Expiry', 'Price', 'Unit', 'Reorder', 'Status'
]);

// Data rows
foreach ($rows as $row) {
    $qty             = (int)($row['qty']          ?? 0);
    $reorderPoint    = (int)($row['reorder_point'] ?? 0);
    $batchCount      = (int)($row['batch_count']   ?? 0);
    $price           = number_format((float)($row['price'] ?? 0), 2, '.', '');
    $nextExpiry      = $row['next_expiry']   ?? null;
    $nearestExpiry   = ($nextExpiry && $nextExpiry !== '0000-00-00') ? date('Y-m-d', strtotime($nextExpiry)) : 'No Expiry';

    if ($batchCount > 0) {
        $batchesExpiry = $batchCount . ' batch' . ($batchCount > 1 ? 'es' : '') . ' (Nearest Exp: ' . $nearestExpiry . ')';
    } else {
        $batchesExpiry = 'No batches / No Expiry';
    }

    fputcsv($out, [
        csvSafe($row['sku']      ?? ''),
        csvSafe($row['name']     ?? ''),
        csvSafe($row['category'] ?? ''),
        csvSafe($row['supplier'] ?? ''),
        csvSafe($row['location'] ?? ''),
        $qty,
        csvSafe($batchesExpiry),
        $price,
        csvSafe($row['uom']      ?? 'piece'),
        $reorderPoint,
        stockStatus($qty, $reorderPoint),
    ]);
}

fclose($out);
exit();
