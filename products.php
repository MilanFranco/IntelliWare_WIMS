<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if ($role !== 'admin') { header('Location: dash_staff.php'); exit(); }

// 1. CONNECT TO THE DATABASE
require_once 'config/db.php'; // Your database connection file

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$items = [];

// 2. HANDLE ALL FORM SUBMISSIONS (CRUD)
// This PHP logic is already correct and does not need to change.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- C-R-U-D: CREATE ---
    if ($action === 'add') {
        // Get data from the 'Add New Product' form
        $product_name = trim($_POST['product_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $supplier = trim($_POST['supplier'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_point = (int)($_POST['reorder_point'] ?? 10);
        $unit = trim($_POST['unit'] ?? '');
        $expiry_date = empty($_POST['expiry_date']) ? null : $_POST['expiry_date'];
        
        $sql = "INSERT INTO products (product_name, category, supplier, quantity, reorder_point, unit, expiry_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiiss", $product_name, $category, $supplier, $quantity, $reorder_point, $unit, $expiry_date);
        $stmt->execute();
    }

    // --- C-R-U-D: UPDATE ---
    if ($action === 'edit') {
        // Get data from the 'edit' form (now populated by our new JavaScript)
        $id = (int)($_POST['id'] ?? 0);
        $product_name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $supplier = trim($_POST['supplier'] ?? '');
        $quantity = (int)($_POST['qty'] ?? 0);
        $reorder_point = (int)($_POST['reorder_point'] ?? 10);
        $unit = trim($_POST['uom'] ?? '');

        $sql = "UPDATE products SET 
                    product_name = ?, 
                    category = ?, 
                    supplier = ?,
                    quantity = ?, 
                    reorder_point = ?,
                    unit = ?
                WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiisi", $product_name, $category, $supplier, $quantity, $reorder_point, $unit, $id);
        $stmt->execute();
    }
    
    // --- C-R-U-D: DELETE ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $sql = "DELETE FROM products WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    // After C-U-D action, redirect to clear the POST request and prevent resubmission
    header('Location: products.php');
    exit();
}

// 3. READ DATA FROM DATABASE (FOR DISPLAYING)
// This runs *after* any POST actions, ensuring the table is up-to-date.
$sql = "SELECT 
            product_id as id, 
            CONCAT('P-', LPAD(product_id, 4, '0')) as sku, 
            product_name as name, 
            category, 
            quantity as qty, 
            unit as uom, 
            supplier,
            reorder_point
        FROM products";

$params = [];
$types = '';

if ($q !== '') {
    // Add search logic (searching name, category, and supplier)
    $sql .= " WHERE CONCAT(product_name, ' ', category, ' ', supplier) LIKE ?";
    $likeQuery = "%" . $q . "%";
    $params[] = $likeQuery;
    $types .= 's';
}

$sql .= " ORDER BY product_name";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}
$stmt->close();
$conn->close();


// 4. RENDER THE PAGE (HTML Layout)
require_once 'src/layout.php';
renderLayoutStart('Inventory Management', $role, $_SESSION['Username']);
?>



<style>
/* Your existing styles are good! */
.card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
.btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
.btn:hover { background:#3d9743; }
.btn-outline { background:transparent; border:1px solid #2A4484; color:#E3E3E3; }
.btn-outline:hover { background:#1D387B; }

/* Added new button styles for save, cancel, delete */
.btn-save { background:#3b82f6; color:#fff; } /* Blue for save */
.btn-save:hover { background:#2563eb; }
.btn-cancel { background:#6b7280; color:#fff; } /* Gray for cancel */
.btn-cancel:hover { background:#4b5563; }
.btn-delete { background:#ef4444; color:#fff; } /* Red for delete */
.btn-delete:hover { background:#dc2626; }


.input { background:#13275B; color:#fff; border:1px solid #304374; padding:8px; border-radius:6px; width:100%; }
.label { color:#E3E3E3; font-family:'Onest'; font-size:12px; margin-bottom:6px; display:block; }
.table { width:100%; border-collapse:collapse; }

.table td { 
    padding:10px; 
    border-bottom:1px solid #2A4484; 
    color:#E3E3E3; 
    font-family:'Onest'; 
    font-size:12px;
    text-align: left; /* Align data cells left */
    vertical-align: top; /* Align top for edit mode */
}
.table th {
    padding:10px; 
    border-bottom:1px solid #2A4484; 
    color:#E3E3E3; 
    font-family:'Onest'; 
    font-size:12px; 
    text-align: left; /* Align header cells left */
    font-weight: bold; /* Make headers bold */
}

/* Style for the inline edit inputs */
.table .edit-input {
    background: #0f1f4b;
    color: #fff;
    border: 1px solid #51D55A; /* Highlight active edit */
    padding: 6px;
    border-radius: 4px;
    width: 95%;
    font-family: 'Onest';
    font-size: 12px;
}


.badge { padding:2px 8px; border-radius:999px; background:#13275B; color:#E3E3E3; font-size:11px; }
.badge.low { background:#ef4444; color:#fff; }
.badge.normal { background:#10b981; color:#fff; }
</style>



<div class="card">
    <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px; margin-bottom:16px;">📦 Add New Product</div>
    
    <form method="POST" style="margin-top:8px;">
        <input type="hidden" name="action" value="add" />
        <div style="display:grid; grid-template-columns: repeat(4,1fr); gap:10px;">
            <div><label class="label">Name *</label><input class="input" name="product_name" required placeholder="Product Name" /></div>
            <div><label class="label">Category</label><input class="input" name="category" placeholder="Category" /></div>
            <div><label class="label">Supplier</label><input class="input" name="supplier" placeholder="Supplier Name" /></div>
            <div><label class="label">UoM</label><input class="input" name="unit" placeholder="pcs, box, roll" /></div>
        </div>
        <div style="display:grid; grid-template-columns: repeat(4,1fr); gap:10px; margin-top:10px;">
            <div><label class="label">Initial Qty *</label><input type="number" class="input" name="quantity" value="0" min="0" required /></div>
            <div><label class="label">Reorder Point</label><input type="number" class="input" name="reorder_point" value="10" min="0" /></div>
            <div><label class="label">Expiry Date</label><input type="date" class="input" name="expiry_date" /></div>
        </div>
        <div style="margin-top:12px;"><button class="btn" type="submit">➕ Add Product</button></div>
    </form>
</div>

<div class="card" style="  margin-top:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:16px;">📋 Product Inventory</div>
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
                    <th>Supplier</th>
                    <th>Qty</th>
                    <th>UoM</th>
                    <th>Reorder Pt.</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="9" style="text-align:center; padding:20px;">
                        <?php echo $q !== '' ? 'No products found matching your search.' : 'No products found. Add your first product above.'; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($items as $it): ?>
                    <tr id="row-<?php echo $it['id']; ?>">
                        <td><?php echo htmlspecialchars($it['sku']); ?></td>
                        
                        <!-- Editable Fields: Wrap text in a span with a unique ID -->
                        <td data-field="name">
                            <span id="display-name-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['name']); ?></span>
                        </td>
                        <td data-field="category">
                            <span id="display-category-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['category']); ?></span>
                        </td>
                        <td data-field="supplier">
                            <span id="display-supplier-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['supplier'] ?? 'N/A'); ?></span>
                        </td>
                        <td data-field="qty">
                            <span id="display-qty-<?php echo $it['id']; ?>"><?php echo (int)$it['qty']; ?></span>
                        </td>
                        <td data-field="uom">
                            <span id="display-uom-<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['uom']); ?></span>
                        </td>
                        <td data-field="reorder_point">
                            <span id="display-reorder_point-<?php echo $it['id']; ?>"><?php echo (int)$it['reorder_point']; ?></span>
                        </td>
                        
                        <!-- Status (Not editable) -->
                        <td>
                            <?php 
                                $isLowStock = (int)$it['qty'] <= (int)$it['reorder_point'];
                            ?>
                            <span class="badge <?php echo $isLowStock ? 'low' : 'normal'; ?>">
                                <?php echo $isLowStock ? 'Low Stock' : 'In Stock'; ?>
                            </span>
                        </td>

                        <!-- Actions Cell: Updated with new buttons -->
                        <td>
                            <!-- Hidden Edit Form -->
                            <form method="POST" style="display:none;" id="edit-form-<?php echo $it['id']; ?>">
                                <input type="hidden" name="action" value="edit" />
                                <input type="hidden" name="id" value="<?php echo $it['id']; ?>" />
                                
                                <input type="hidden" name="name" value="<?php echo htmlspecialchars($it['name'], ENT_QUOTES); ?>" />
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($it['category'], ENT_QUOTES); ?>" />
                                <input type="hidden" name="supplier" value="<?php echo htmlspecialchars($it['supplier'] ?? '', ENT_QUOTES); ?>" />
                                <input type="hidden" name="qty" value="<?php echo (int)$it['qty']; ?>" />
                                <input type="hidden" name="reorder_point" value="<?php echo (int)$it['reorder_point']; ?>" />
                                <input type="hidden" name="uom" value="<?php echo htmlspecialchars($it['uom'], ENT_QUOTES); ?>" />
                            </form>
                            
                            <!-- Hidden Delete Form -->
                            <form method="POST" style="display:none;" id="delete-form-<?php echo $it['id']; ?>">
                                <input type="hidden" name="action" value="delete" />
                                <input type="hidden" name="id" value="<?php echo $it['id']; ?>" />
                            </form>
                            
                            <!-- Action Buttons Container -->
                            <div id="actions-default-<?php echo $it['id']; ?>" style="display:flex; gap:6px;">
                                <button type="button" class="btn btn-outline" onclick="toggleEdit('<?php echo $it['id']; ?>')">✏️ Edit</button>
                                <button type="button" class="btn btn-outline" onclick="showDeleteConfirm('<?php echo $it['id']; ?>')">🗑️ Delete</button>
                            </div>

                            <!-- Edit Mode Buttons (Hidden by default) -->
                            <div id="actions-edit-<?php echo $it['id']; ?>" style="display:none; gap:6px;">
                                <button type="button" class="btn btn-save" onclick="saveEdit('<?php echo $it['id']; ?>')">💾 Save</button>
                                <button type="button" class="btn btn-cancel" onclick="cancelEdit('<?php echo $it['id']; ?>')">❌ Cancel</button>
                            </div>

                            <!-- Delete Confirm Buttons (Hidden by default) -->
                            <div id="actions-delete-<?php echo $it['id']; ?>" style="display:none; text-align:left;">
                                <div style="color:#ef4444; font-size:11px; margin-bottom: 5px;">Are you sure?</div>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="btn btn-delete" onclick="document.getElementById('delete-form-<?php echo $it['id']; ?>').submit();">Yes</button>
                                    <button type="button" class="btn btn-cancel" onclick="hideDeleteConfirm('<?php echo $it['id']; ?>')">No</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// --- NEW INLINE EDIT JAVASCRIPT ---

// Defines which fields in the row are editable
const editableFields = ['name', 'category', 'supplier', 'qty', 'reorder_point', 'uom'];

/**
 * Toggles a row into "edit mode"
 * @param {string} id - The product ID
 */
function toggleEdit(id) {
    // Hide default buttons, show edit buttons
    document.getElementById(`actions-default-${id}`).style.display = 'none';
    document.getElementById(`actions-edit-${id}`).style.display = 'flex';

    const form = document.getElementById(`edit-form-${id}`);

    for (const field of editableFields) {
        const displaySpan = document.getElementById(`display-${field}-${id}`);
        const currentValue = form.elements[field].value;
        const inputType = (field === 'qty' || field === 'reorder_point') ? 'number' : 'text';

        // Hide the text span
        displaySpan.style.display = 'none';
        
        // Create and insert an input field
        const input = document.createElement('input');
        input.type = inputType;
        input.id = `input-${field}-${id}`; // Give it an ID so we can find it
        input.className = 'edit-input';
        input.value = currentValue;
        
        // For number inputs, set min value
        if (inputType === 'number') {
            input.min = '0';
        }
        
        // Add the input field right after the hidden span
        displaySpan.parentElement.appendChild(input);
    }
}

/**
 * Cancels edit mode, discarding changes
 * @param {string} id - The product ID
 */
function cancelEdit(id) {
    // Show default buttons, hide edit buttons
    document.getElementById(`actions-default-${id}`).style.display = 'flex';
    document.getElementById(`actions-edit-${id}`).style.display = 'none';

    for (const field of editableFields) {
        const displaySpan = document.getElementById(`display-${field}-${id}`);
        const inputField = document.getElementById(`input-${field}-${id}`);
        
        // Remove the input field
        if (inputField) {
            inputField.remove();
        }
        
        // Show the original text span
        displaySpan.style.display = '';
    }
}

/**
 * Saves the edited data by updating and submitting the hidden form
 * @param {string} id - The product ID
 */
function saveEdit(id) {
    const form = document.getElementById(`edit-form-${id}`);
    
    for (const field of editableFields) {
        const inputField = document.getElementById(`input-${field}-${id}`);
        
        // Update the hidden form's value from the visible input
        if (inputField) {
            form.elements[field].value = inputField.value;
        }
    }
    
    // Submit the hidden form
    form.submit();
}

/**
 * Shows the inline delete confirmation
 * @param {string} id - The product ID
 */
function showDeleteConfirm(id) {
    document.getElementById(`actions-default-${id}`).style.display = 'none';
    document.getElementById(`actions-delete-${id}`).style.display = 'block';
}

/**
 * Hides the inline delete confirmation
 * @param {string} id - The product ID
 */
function hideDeleteConfirm(id) {
    document.getElementById(`actions-default-${id}`).style.display = 'flex';
    document.getElementById(`actions-delete-${id}`).style.display = 'none';
}

</script>

<?php
renderLayoutEnd();
?>