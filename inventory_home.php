<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header('Location: signin.php');
    exit();
}
// Handle CRUD actions (session-based)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $items = $_SESSION['items'] ?? [];
        $nextId = 1;
        foreach ($items as $it) { $nextId = max($nextId, $it['id'] + 1); }
        $items[] = [
            'id' => $nextId,
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'qty' => (int)($_POST['qty'] ?? 0),
            'uom' => trim($_POST['uom'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
        ];
        $_SESSION['items'] = $items;
        header('Location: inventory_home.php');
        exit();
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $items = $_SESSION['items'] ?? [];
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
        $_SESSION['items'] = $items;
        header('Location: inventory_home.php');
        exit();
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $items = $_SESSION['items'] ?? [];
        $items = array_values(array_filter($items, function($it) use ($id) { return $it['id'] !== $id; }));
        $_SESSION['items'] = $items;
        header('Location: inventory_home.php');
        exit();
    }
}

$username = $_SESSION['Username'];
$items = $_SESSION['items'] ?? [];

require_once 'src/layout.php';
renderLayoutStart('Inventory Dashboard', $_SESSION['Role'], $username);
?>


<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">📦 Add New Item</div>
        <form method="POST">
            <input type="hidden" name="action" value="add" />
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <div>
                    <label class="label">SKU</label>
                    <input class="input" name="sku" required placeholder="e.g. ITM-001" />
                </div>
                <div>
                    <label class="label">Name</label>
                    <input class="input" name="name" required placeholder="Item Name" />
                </div>
                <div>
                    <label class="label">Category</label>
                    <input class="input" name="category" placeholder="Category" />
                </div>
                <div>
                    <label class="label">Quantity</label>
                    <input type="number" class="input" name="qty" value="0" min="0" />
                </div>
                <div>
                    <label class="label">UoM</label>
                    <input class="input" name="uom" placeholder="pcs, box, roll" />
                </div>
                <div>
                    <label class="label">Location</label>
                    <input class="input" name="location" placeholder="A1" />
                </div>
            </div>
            <div style="margin-top:12px;">
                <button class="btn btn-primary" type="submit">➕ Add Item</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">📊 Inventory Summary</div>
        <div style="color:#E3E3E3; font-family:'Onest'; font-size:14px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span>Total SKUs:</span>
                <span class="badge"><?php echo count($items); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span>Total Quantity:</span>
                <span class="badge"><?php echo array_sum(array_map(function($i){return (int)$i['qty'];}, $items)); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span>Low Stock Items:</span>
                <span class="badge low"><?php echo count(array_filter($items,function($i){return (int)$i['qty']<50;})); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span>Categories:</span>
                <span class="badge"><?php echo count(array_unique(array_map(function($i){return $i['category'];}, $items))); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">📋 Inventory Items</div>
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
                <?php foreach ($items as $it): ?>
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
                        <button class="btn btn-secondary" onclick="fillEdit(<?php echo $it['id']; ?>,'<?php echo htmlspecialchars($it['sku'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($it['category'], ENT_QUOTES); ?>',<?php echo (int)$it['qty']; ?>,'<?php echo htmlspecialchars($it['uom'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($it['location'], ENT_QUOTES); ?>')">✏️ Edit</button>
                        <form method="POST" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Delete this item?');">
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?php echo $it['id']; ?>" />
                            <button class="btn btn-secondary" type="submit">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="8" style="color:#9aa6d1; text-align:center; padding:20px;">No items yet. Add your first item above.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); align-items:center; justify-content:center; z-index:1000;">
    <div class="card" style="width:600px; margin:40px auto;">
        <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">✏️ Edit Item</div>
        <form method="POST">
            <input type="hidden" name="action" value="edit" />
            <input type="hidden" name="id" id="edit-id" />
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <div>
                    <label class="label">SKU</label>
                    <input id="edit-sku" class="input" name="sku" />
                </div>
                <div>
                    <label class="label">Name</label>
                    <input id="edit-name" class="input" name="name" />
                </div>
                <div>
                    <label class="label">Category</label>
                    <input id="edit-category" class="input" name="category" />
                </div>
                <div>
                    <label class="label">Quantity</label>
                    <input type="number" id="edit-qty" class="input" name="qty" />
                </div>
                <div>
                    <label class="label">UoM</label>
                    <input id="edit-uom" class="input" name="uom" />
                </div>
                <div>
                    <label class="label">Location</label>
                    <input id="edit-location" class="input" name="location" />
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button class="btn" type="submit">💾 Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEdit()">❌ Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function fillEdit(id, sku, name, category, qty, uom, location) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-sku').value = sku;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-category').value = category;
    document.getElementById('edit-qty').value = qty;
    document.getElementById('edit-uom').value = uom;
    document.getElementById('edit-location').value = location;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEdit(){ 
    document.getElementById('edit-modal').style.display = 'none'; 
}
</script>

<?php
renderLayoutEnd();
?>


