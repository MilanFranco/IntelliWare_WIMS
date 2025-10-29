<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }
$reorder = $_SESSION['reorder'] ?? 50;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $reorder = max(0, (int)($_POST['reorder'] ?? 0));
  $_SESSION['reorder'] = $reorder;
  header('Location: reorder.php');
  exit();
}

require_once 'src/layout.php';
renderLayoutStart('Set Reorder Point', $_SESSION['Role'], $_SESSION['Username']);
?>

<style>
.card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
.btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
.btn:hover { background:#3d9743; }
.input { background:#13275B; color:#fff; border:1px solid #304374; padding:8px; border-radius:6px; width:100%; }
.label { color:#E3E3E3; font-family:'Onest'; font-size:12px; margin-bottom:6px; display:block; }
</style>

<div class="card" style="max-width:520px;">
    <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">⚙️ Configure Reorder Point</div>
    <div style="color:#E3E3E3; font-family:'Onest'; font-size:14px; margin-bottom:16px;">
        Set the default reorder point threshold for inventory management. Items below this level will trigger low stock alerts.
    </div>
    
    <form method="POST">
        <label class="label">Reorder Point (units)</label>
        <input class="input" type="number" name="reorder" value="<?php echo (int)$reorder; ?>" min="0" placeholder="Enter minimum stock level" />
        <div style="margin-top:16px;">
            <button class="btn" type="submit">💾 Save Configuration</button>
        </div>
    </form>
    
    <div style="margin-top:20px; padding:12px; background:#13275B; border-radius:8px; border:1px solid #2A4484;">
        <div style="color:#51D55A; font-weight:600; font-size:12px; margin-bottom:8px;">ℹ️ How it works:</div>
        <div style="color:#E3E3E3; font-family:'Onest'; font-size:11px; line-height:1.4;">
            • Items with stock below this threshold will appear in low stock alerts<br/>
            • This setting applies to all products in your inventory<br/>
            • You can override this for individual products in the inventory management section
        </div>
    </div>
</div>

<?php
renderLayoutEnd();
?>



