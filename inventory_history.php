<?php
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

require_once 'config/db.php';

$search = trim($_GET['q'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterProduct = trim($_GET['product'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$hasAudit = false;
$auditCheck = $conn->query("SHOW COLUMNS FROM transactions LIKE 'previous_stock'");
if ($auditCheck && $auditCheck->num_rows > 0) {
    $hasAudit = true;
}

$sql = "SELECT t.transaction_id, t.transaction_type, t.quantity, t.transaction_date, t.remarks,
               t.barcode AS txn_barcode,
               " . ($hasAudit ? "t.previous_stock, t.new_stock," : "") . "
               p.product_id, p.product_name, p.barcode AS product_barcode,
               u.username
        FROM transactions t
        LEFT JOIN products p ON t.product_id = p.product_id
        LEFT JOIN users u ON t.user_id = u.user_id
        WHERE 1=1";

$countSql = "SELECT COUNT(*) AS total
             FROM transactions t
             LEFT JOIN products p ON t.product_id = p.product_id
             LEFT JOIN users u ON t.user_id = u.user_id
             WHERE 1=1";

$params = [];
$types = '';
$filterClause = '';

if ($filterType === 'in') {
    $filterClause .= " AND t.transaction_type = 'stock_in'";
} elseif ($filterType === 'out') {
    $filterClause .= " AND t.transaction_type = 'stock_out'";
}

if ($search !== '') {
    $filterClause .= " AND (p.product_name LIKE ? OR t.barcode LIKE ? OR p.barcode LIKE ? OR CAST(t.transaction_id AS CHAR) LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'ssss';
}

if ($filterProduct !== '') {
    $filterClause .= " AND p.product_name LIKE ?";
    $params[] = '%' . $filterProduct . '%';
    $types .= 's';
}

if ($dateFrom !== '') {
    $filterClause .= " AND DATE(t.transaction_date) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $filterClause .= " AND DATE(t.transaction_date) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$sql .= $filterClause;
$countSql .= $filterClause;

$countParams = $params;
$countTypes = $types;

$countStmt = $conn->prepare($countSql);
if ($countTypes !== '') {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));

$sql .= " ORDER BY t.transaction_date DESC LIMIT ? OFFSET ?";
$listParams = $params;
$listTypes = $types;
$listParams[] = $limit;
$listParams[] = $offset;
$listTypes .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();
$conn->close();

require_once 'src/layout.php';
renderLayoutStart('Transaction History', $role, $_SESSION['Username']);
?>

<div class="premium-card mb-8" style="padding:0;overflow:visible;">
    <form method="GET" class="p-6 border-b border-[#2A4484]/50 bg-[#13275B]/30">
        <div class="flex flex-col lg:flex-row lg:items-end gap-4">
            <div class="flex-1">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">Search</label>
                <input type="text" name="q" class="premium-input w-full" placeholder="ID, product, barcode..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="w-full lg:w-40">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">Type</label>
                <select name="type" class="premium-input w-full cursor-pointer">
                    <option value="">All Types</option>
                    <option value="in" <?php echo $filterType === 'in' ? 'selected' : ''; ?>>IN (Receive)</option>
                    <option value="out" <?php echo $filterType === 'out' ? 'selected' : ''; ?>>OUT (Release)</option>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">Product</label>
                <input type="text" name="product" class="premium-input w-full" placeholder="Filter by product name..."
                       value="<?php echo htmlspecialchars($filterProduct); ?>">
            </div>
            <div class="w-full lg:w-36">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">From</label>
                <input type="date" name="date_from" class="premium-input w-full"
                       value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="w-full lg:w-36">
                <label class="block text-[#9aa6d1] text-xs font-semibold mb-2">To</label>
                <input type="date" name="date_to" class="premium-input w-full"
                       value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="premium-button" style="height:46px;">Filter</button>
                <a href="inventory_history.php" class="px-4 py-2 border border-[#2A4484] rounded-xl text-[#9aa6d1] text-sm hover:bg-[#2A4484]/30 flex items-center" style="height:46px;">Reset</a>
            </div>
        </div>
    </form>

    <div class="p-4 border-b border-[#2A4484]/30 text-xs text-[#9aa6d1]">
        Showing <?php echo count($transactions); ?> of <?php echo $totalRows; ?> transactions
        (page <?php echo $page; ?> of <?php echo $totalPages; ?>)
    </div>

    <div class="overflow-x-auto">
        <table class="premium-table w-full text-left">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date &amp; Time</th>
                    <th>Product</th>
                    <th>Barcode</th>
                    <th>Type</th>
                    <th class="text-center">Qty</th>
                    <?php if ($hasAudit): ?>
                        <th class="text-center">Prev Stock</th>
                        <th class="text-center">New Stock</th>
                    <?php endif; ?>
                    <th>User</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="<?php echo $hasAudit ? 10 : 8; ?>" class="text-center p-8 text-[#9aa6d1]">
                            No transactions found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $txn): ?>
                        <?php
                        $isIn = $txn['transaction_type'] === 'stock_in';
                        $barcode = $txn['txn_barcode'] ?? $txn['product_barcode'] ?? '—';
                        ?>
                        <tr class="hover:bg-[#1D387B]/50 transition-colors">
                            <td class="font-mono text-xs text-[#9aa6d1]">#<?php echo (int)$txn['transaction_id']; ?></td>
                            <td class="text-sm text-[#E3E3E3] whitespace-nowrap"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($txn['transaction_date']))); ?></td>
                            <td class="font-medium text-[#E3E3E3]"><?php echo htmlspecialchars($txn['product_name'] ?? 'Unknown'); ?></td>
                            <td class="font-mono text-xs text-[#9aa6d1]"><?php echo htmlspecialchars($barcode ?: '—'); ?></td>
                            <td>
                                <?php if ($isIn): ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">IN</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">OUT</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center font-mono font-semibold text-[#E3E3E3]"><?php echo (int)$txn['quantity']; ?></td>
                            <?php if ($hasAudit): ?>
                                <td class="text-center font-mono text-sm text-[#9aa6d1]"><?php echo $txn['previous_stock'] !== null ? (int)$txn['previous_stock'] : '—'; ?></td>
                                <td class="text-center font-mono text-sm text-[#E3E3E3]"><?php echo $txn['new_stock'] !== null ? (int)$txn['new_stock'] : '—'; ?></td>
                            <?php endif; ?>
                            <td class="text-sm text-[#9aa6d1]"><?php echo htmlspecialchars($txn['username'] ?? '—'); ?></td>
                            <td class="text-sm text-[#6b7280] max-w-[180px] truncate" title="<?php echo htmlspecialchars($txn['remarks'] ?? ''); ?>">
                                <?php echo htmlspecialchars($txn['remarks'] ?? '—'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-[#2A4484]/50 flex justify-center gap-2">
            <?php
            $qsParams = $_GET;
            unset($qsParams['page']);
            $qs = http_build_query($qsParams);
            ?>
            <?php if ($page > 1): ?>
                <a href="?<?php echo $qs; ?>&page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-[#13275B] border border-[#2A4484] rounded text-[#E3E3E3] text-sm hover:bg-[#2A4484]">Previous</a>
            <?php endif; ?>
            <span class="px-3 py-1 text-[#9aa6d1] text-sm flex items-center">Page <?php echo $page; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?<?php echo $qs; ?>&page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-[#13275B] border border-[#2A4484] rounded text-[#E3E3E3] text-sm hover:bg-[#2A4484]">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php renderLayoutContentEnd(); ?>
<?php renderLayoutEnd(); ?>
