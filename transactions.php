<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','staff'])) { header('Location: dash_manager.php'); exit(); }

$items = $_SESSION['items'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'in';
    $id = (int)($_POST['id'] ?? 0);
    $qty = max(0, (int)($_POST['qty'] ?? 0));
    
    // Update session data
    foreach ($items as &$it) {
        if ($it['id'] === $id) {
            if ($type === 'in') { $it['qty'] += $qty; }
            else { $it['qty'] = max(0, $it['qty'] - $qty); }
            break;
        }
    }
    unset($it);
    $_SESSION['items'] = $items;
    
    // Save to database if available
    require_once 'config/db.php';
    try {
        // Get user ID
        $userQuery = "SELECT user_id FROM users WHERE username = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("s", $_SESSION['Username']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        $userId = $user['user_id'] ?? 1;
        
        // Insert transaction
        $transactionQuery = "INSERT INTO transactions (product_id, user_id, transaction_type, quantity, remarks) VALUES (?, ?, ?, ?, ?)";
        $transactionStmt = $conn->prepare($transactionQuery);
        $transactionType = $type === 'in' ? 'stock_in' : 'stock_out';
        $remarks = $type === 'in' ? 'Stock received' : 'Stock issued';
        $transactionStmt->bind_param("iisis", $id, $userId, $transactionType, $qty, $remarks);
        $transactionStmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO logs (user_id, action) VALUES (?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $action = "Processed " . $transactionType . " transaction for product ID " . $id;
        $logStmt->bind_param("is", $userId, $action);
        $logStmt->execute();
        
    } catch (Exception $e) {
        // Database error, but continue with session update
    }
    $conn->close();
    
    header('Location: transactions.php');
    exit();
}

$action = $_GET['action'] ?? '';

require_once 'src/layout.php';
renderLayoutStart('Stock Transactions', $role, $_SESSION['Username']);
?>

<style>
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
                <?php foreach($_SESSION['items'] ?? [] as $it): ?>
                    <option value="<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['sku'].' — '.$it['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label">Quantity</label>
            <input type="number" class="input" name="qty" value="0" min="0" required placeholder="Enter quantity" />
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
                    <th>Location</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach(($items) as $it): ?>
                <tr>
                    <td><?php echo htmlspecialchars($it['sku']); ?></td>
                    <td><?php echo htmlspecialchars($it['name']); ?></td>
                    <td><?php echo (int)$it['qty']; ?></td>
                    <td><?php echo htmlspecialchars($it['uom']); ?></td>
                    <td><?php echo htmlspecialchars($it['location']); ?></td>
                    <td>
                        <span class="badge <?php echo (int)$it['qty'] < 50 ? 'low' : 'normal'; ?>">
                            <?php echo (int)$it['qty'] < 50 ? 'Low Stock' : 'In Stock'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="6" style="color:#9aa6d1; text-align:center; padding:20px;">No products available. Add products first in the inventory management section.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
renderLayoutEnd();
?>


