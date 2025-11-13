<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','staff'])) { header('Location: dash_manager.php'); exit(); }

// 1. CONNECT TO DATABASE (moved to top)
require_once 'config/db.php';

$action = $_GET['action'] ?? '';

// 2. HANDLE TRANSACTION SUBMISSION (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'in';
    $id = (int)($_POST['id'] ?? 0);
    $qty = max(0, (int)($_POST['qty'] ?? 0));
    $transactionType = $type === 'in' ? 'stock_in' : 'stock_out';

    // Use a database transaction for safety
    $conn->begin_transaction();

    try {
        // 1. Get user ID
        $userQuery = "SELECT user_id FROM users WHERE username = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("s", $_SESSION['Username']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        $userId = $user['user_id'] ?? 1; // Fallback to 1 (admin) if user somehow not found
        $userStmt->close();
        
        // 2. UPDATE THE MASTER PRODUCT QUANTITY
        if ($type === 'in') {
            $updateSql = "UPDATE products SET quantity = quantity + ? WHERE product_id = ?";
        } else {
            // Use GREATEST(0, ...) to prevent stock from ever going negative
            $updateSql = "UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE product_id = ?";
        }
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $qty, $id);
        $updateStmt->execute();
        $updateStmt->close();

        // 3. Insert transaction log
        $transactionQuery = "INSERT INTO transactions (product_id, user_id, transaction_type, quantity, remarks) VALUES (?, ?, ?, ?, ?)";
        $transactionStmt = $conn->prepare($transactionQuery);
        $remarks = $type === 'in' ? 'Stock received' : 'Stock issued';
        $transactionStmt->bind_param("iisis", $id, $userId, $transactionType, $qty, $remarks);
        $transactionStmt->execute();
        $transactionStmt->close();
        
        // 4. Log the user activity
        $logQuery = "INSERT INTO logs (user_id, action) VALUES (?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $actionLog = "Processed " . $transactionType . " (Qty: $qty) for product ID " . $id;
        $logStmt->bind_param("is", $userId, $actionLog);
        $logStmt->execute();
        $logStmt->close();

        // If all queries were successful, commit the changes
        $conn->commit();
        
    } catch (Exception $e) {
        // If any query failed, roll back all changes
        $conn->rollback();
        $_SESSION['error'] = "Transaction failed: " . $e->getMessage();
    }
    
    header('Location: transactions.php');
    exit();
}

// 3. READ ALL PRODUCTS FOR DISPLAY (GET)
$items = [];
$productQuery = "SELECT 
                    product_id as id, 
                    CONCAT('P-', LPAD(product_id, 4, '0')) as sku, 
                    product_name as name, 
                    quantity as qty, 
                    unit as uom,
                    reorder_point 
                 FROM products 
                 ORDER BY product_name";
$productResult = $conn->query($productQuery);
if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $items[] = $row;
    }
}
$conn->close();

// 4. RENDER THE PAGE
require_once 'src/layout.php';
renderLayoutStart('Stock Transactions', $role, $_SESSION['Username']);
?>

<style>
/* Your existing styles are perfect */
.card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
.btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
.btn:hover { background:#3d9743; }
.btn-secondary { background:#13275B; color:#E3E3E3; border:1px solid #2A4484; }
.btn-secondary:hover { background:#1D387B; }
.input { background:#13275B; color:#fff; border:1px solid #304374; padding:8px; border-radius:6px; width:100%; }
.label { color:#E3E3E3; font-family:'Onest'; font-size:12px; margin-bottom:6px; display:block; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { padding:10px; border-bottom:1px solid #2A4484; color:#E3E3E3; font-family:'Onest'; font-size:12px; }
.badge { padding:2px 8px; border-radius:999px; background:#13275B; color:#E3E3E3; font-size:11px; }
.badge.low { background:#ef4444; color:#fff; }
.badge.normal { background:#10b981; color:#fff; }
</style>

<div class="card">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">📦 Create Stock Transaction</div>
    <div style="color:#E3E3E3; font-family:'Onest'; font-size:14px; margin-bottom:16px;">
        Record stock movements (in/out) for inventory tracking and management.
    </div>
    
    <form method="POST" style="margin-top:8px; display:grid; grid-template-columns: repeat(5,1fr); gap:8px; align-items:end;">
        <div>
            <label class="label">Transaction Type</label>
            <select class="input" name="type">
                <option value="in" <?php echo $action==='in'?'selected':''; ?>>📥 Stock In</option>
                <option value="out" <?php echo $action==='out'?'selected':''; ?>>📤 Stock Out</option>
            </select>
        </div>
        <div>
            <label class="label">Product</label>
            <select class="input" name="id" required>
                <option value="">Select Product</option>
                <?php foreach($items as $it): ?>
                    <option value="<?php echo $it['id']; ?>">
                        <?php echo htmlspecialchars($it['sku'].' — '.$it['name']); ?> (Stock: <?php echo $it['qty']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label">Quantity</label>
            <input type="number" class="input" name="qty" value="1" min="1" required placeholder="Enter quantity" />
        </div>
        <div>
            <button class="btn" type="submit">✅ Process Transaction</button>
        </div>
    </form>
    
    <div style="margin-top:16px; padding:12px; background:#13275B; border-radius:8px; border:1px solid #2A4484;">
        <div style="color:#51D55A; font-weight:600; font-size:12px; margin-bottom:8px;">ℹ️ Transaction Types:</div>
        <div style="color:#E3E3E3; font-family:'Onest'; font-size:11px; line-height:1.4;">
            • <strong>Stock In:</strong> Add inventory (receiving, returns, adjustments)<br/>
            • <strong>Stock Out:</strong> Remove inventory (sales, transfers, waste)
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">📋 Current Inventory Status</div>
    <div style="overflow:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Current Qty</th>
                    <th>UoM</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $it): ?>
                <tr>
                    <td><?php echo htmlspecialchars($it['sku']); ?></td>
                    <td><?Gg; echo htmlspecialchars($it['name']); ?></td>
                    <td><?php echo (int)$it['qty']; ?></td>
                    <td><?php echo htmlspecialchars($it['uom']); ?></td>
                    <td>
                        <?php $isLowStock = (int)$it['qty'] <= (int)($it['reorder_point'] ?? 10); ?>
                        <span class="badge <?php echo $isLowStock ? 'low' : 'normal'; ?>">
                            <?php echo $isLowStock ? 'Low Stock' : 'In Stock'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" style="color:#9aa6d1; text-align:center; padding:20px;">No products found. Add products first on the 'Products' page.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
renderLayoutEnd();
?>