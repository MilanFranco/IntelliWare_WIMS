<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if ($role !== 'admin') { header('Location: dash_staff.php'); exit(); }

// Session-based items array
$items = $_SESSION['items'] ?? [];

// CRUD via POST (reuse from inventory_home but scoped here)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $nextId = 1; foreach ($items as $it) { $nextId = max($nextId, $it['id'] + 1); }
        $items[] = [
            'id' => $nextId,
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'qty' => (int)($_POST['qty'] ?? 0),
            'uom' => trim($_POST['uom'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
        ];
        $_SESSION['items'] = $items; header('Location: products.php'); exit();
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        foreach ($items as &$it) {
            if ($it['id'] === $id) {
                $it['sku'] = trim($_POST['sku'] ?? $it['sku']);
                $it['name'] = trim($_POST['name'] ?? $it['name']);
                $it['category'] = trim($_POST['category'] ?? $it['category']);
                $it['qty'] = (int)($_POST['qty'] ?? $it['qty']);
                $it['uom'] = trim($_POST['uom'] ?? $it['uom']);
                $it['location'] = trim($_POST['location'] ?? $it['location']);
                break;
            }
        }
        unset($it);
        $_SESSION['items'] = $items; header('Location: products.php'); exit();
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $items = array_values(array_filter($items, function($it) use ($id) { return $it['id'] !== $id; }));
        $_SESSION['items'] = $items; header('Location: products.php'); exit();
    }
}

// Simple search
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q !== '') {
    $qry = mb_strtolower($q);
    $items = array_values(array_filter($items, function($it) use ($qry){
        return str_contains(mb_strtolower($it['sku'].' '.$it['name'].' '.$it['category'].' '.$it['location']), $qry);
    }));
}

require_once 'src/layout.php';
renderLayoutStart('Inventory Management', $role, $_SESSION['Username']);
?>



<style>
.card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
.btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
.btn:hover { background:#3d9743; }
.btn-outline { background:transparent; border:1px solid #2A4484; color:#E3E3E3; }
.btn-outline:hover { background:#1D387B; }
.input { background:#13275B; color:#fff; border:1px solid #304374; padding:8px; border-radius:6px; width:100%; }
.label { color:#E3E3E3; font-family:'Onest'; font-size:12px; margin-bottom:6px; display:block; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { padding:10px; border-bottom:1px solid #2A4484; color:#E3E3E3; font-family:'Onest'; font-size:12px; }
.badge { padding:2px 8px; border-radius:999px; background:#13275B; color:#E3E3E3; font-size:11px; }
.badge.low { background:#ef4444; color:#fff; }
.badge.normal { background:#10b981; color:#fff; }
</style>



<div class="card">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">📦 Add New Product</div>
    <form method="POST" style="margin-top:8px;">
        <input type="hidden" name="action" value="add" />
        <div style="display:grid; grid-template-columns: repeat(6,1fr); gap:8px;">
            <div><label class="label">SKU</label><input class="input" name="sku" required placeholder="e.g. PRD-001" /></div>
            <div><label class="label">Name</label><input class="input" name="name" required placeholder="Product Name" /></div>
            <div><label class="label">Category</label><input class="input" name="category" placeholder="Category" /></div>
            <div><label class="label">Qty</label><input type="number" class="input" name="qty" value="0" min="0" /></div>
            <div><label class="label">UoM</label><input class="input" name="uom" placeholder="pcs, box, roll" /></div>
            <div><label class="label">Location</label><input class="input" name="location" placeholder="A1" /></div>
        </div>
        <div style="margin-top:12px;"><button class="btn" type="submit">➕ Add Product</button></div>
    </form>
</div>

<div class="card" style="  margin-top:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px;">📋 Product Inventory</div>
        <form method="GET" style="display:flex; gap:8px;">
            <input class="input" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($q); ?>" style="width:280px;" />
            <button class="btn" type="submit">🔍 Search</button>
        </form>
    </div>
    
    <div style="overflow:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>UoM</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:20px;">No products found. Add your first product above.</td>
                </tr>
                <?php else: ?>
                    <?php foreach($items as $it): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($it['sku']); ?></td>
                        <td><?php echo htmlspecialchars($it['name']); ?></td>
                        <td><?php echo htmlspecialchars($it['category']); ?></td>
                        <td><?php echo (int)$it['qty']; ?></td>
                        <td><?php echo htmlspecialchars($it['uom']); ?></td>
                        <td><?php echo htmlspecialchars($it['location']); ?></td>
                        <td>
                            <span class="badge <?php echo (int)$it['qty'] < 50 ? 'low' : 'normal'; ?>">
                                <?php echo (int)$it['qty'] < 50 ? 'Low Stock' : 'In Stock'; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="action" value="edit" />
                                <input type="hidden" name="id" value="<?php echo $it['id']; ?>" />
                                <input type="hidden" name="sku" value="<?php echo htmlspecialchars($it['sku'], ENT_QUOTES); ?>" />
                                <input type="hidden" name="name" value="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>" />
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($it['category'], ENT_QUOTES); ?>" />
                                <input type="hidden" name="qty" value="<?php echo (int)$it['qty']; ?>" />
                                <input type="hidden" name="uom" value="<?php echo htmlspecialchars($it['uom'], ENT_QUOTES); ?>" />
                                <input type="hidden" name="location" value="<?php echo htmlspecialchars($it['location'], ENT_QUOTES); ?>" />
                                <button class="btn btn-outline" onclick="return editInline(this.form);">✏️ Edit</button>
                            </form>
                            <form method="POST" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="action" value="delete" />
                                <input type="hidden" name="id" value="<?php echo $it['id']; ?>" />
                                <button class="btn btn-outline" type="submit">🗑️ Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editInline(form){
    const fields = ['sku','name','category','qty','uom','location'];
    for(const f of fields){
        const v = prompt('Edit '+f.toUpperCase(), form.elements[f].value);
        if (v === null) return false;
        form.elements[f].value = v;
    }
    form.submit();
    return false;
}
</script>

<?php
renderLayoutEnd();
?>


