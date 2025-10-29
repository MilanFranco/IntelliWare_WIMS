<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'manager') { header('Location: dash_admin.php'); exit(); }

// Include database connection
require_once 'config/db.php';

// Get products from database
$items = [];
$productQuery = "SELECT product_id as id, CONCAT('P-', LPAD(product_id, 4, '0')) as sku, product_name as name, category, quantity as qty, unit as uom, 'A1' as location FROM products";
$productResult = $conn->query($productQuery);

if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    // If no products in database, use session data as fallback
    $items = $_SESSION['items'] ?? [
        [ 'id' => 1, 'sku' => 'W-0001', 'name' => 'Wood Pallet', 'category' => 'Packaging', 'qty' => 120, 'uom' => 'pcs', 'location' => 'A1' ],
        [ 'id' => 2, 'sku' => 'B-0042', 'name' => 'Bolt M8x40', 'category' => 'Hardware', 'qty' => 860, 'uom' => 'pcs', 'location' => 'B3' ],
        [ 'id' => 3, 'sku' => 'T-0100', 'name' => 'Thermal Label 4x6', 'category' => 'Labels', 'qty' => 540, 'uom' => 'roll', 'location' => 'C2' ],
    ];
}

// Get user info from database
$userQuery = "SELECT username, role, full_name FROM users WHERE username = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $_SESSION['Username']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
$userStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="styles.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Onest:wght@200;300;400;500;600;700&family=Overpass:wght@400;500;600;700&display=swap" rel="stylesheet">
  <title>Manager Dashboard | IntelliWare</title>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
    .menu-item { border:1px solid #2A4484; color:#E3E3E3; border-radius:10px; padding:12px 16px; display:block; text-decoration:none; }
    .menu-item:hover { background:#13275B; }
    .btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; }
    
    /* Premium Manager Styles */
    .premium-manager-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.1),
        0 1px 0 rgba(255, 255, 255, 0.8);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      backdrop-filter: blur(10px);
    }
    
    .manager-user-section {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 16px;
      background: rgba(96, 165, 250, 0.1);
      border-radius: 12px;
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(96, 165, 250, 0.2);
    }
    
    .user-profile:hover {
      background: rgba(96, 165, 250, 0.15);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(96, 165, 250, 0.2);
    }
    
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #60a5fa;
      object-fit: cover;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 12px rgba(96, 165, 250, 0.3);
    }
    
    .user-avatar:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 20px rgba(96, 165, 250, 0.4);
    }
    
    .user-details {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    
    .user-name {
      font-family: 'Onest', sans-serif;
      font-weight: 600;
      font-size: 14px;
      color: #1f2937;
    }
    
    .user-role {
      font-family: 'Onest', sans-serif;
      font-weight: 400;
      font-size: 12px;
      color: #6b7280;
    }
  </style>
</head>
<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">
  <div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-gray-200 shadow-lg">
    <div class="sidebar w-[290px] bg-[#1D387B] text-white p-3 pt-5 flex flex-col">
      <div class="ml-2 mb-4">
        <img src="img/logo.svg" alt="IntelliWare" class="w-[220px]" />
      </div>
      <div class="p-2 flex flex-col gap-[8px]">
        <a class="menu-item" href="dash_manager.php">Dashboard</a>
        <a class="menu-item" href="insights.php">Data Mining Insights</a>
        <a class="menu-item" href="reports.php">Reports</a>
      </div>
      <div class="mt-auto p-2"><a class="btn" href="logout.php">Logout</a></div>
    </div>
    <div class="flex-1 flex flex-col">
      <div class="premium-manager-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">
        <div class="font-onest text-[20px] font-semibold text-gray-800">Manager/Auditor Dashboard</div>
        <div class="manager-user-section">
          <div class="user-profile">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['Username']); ?>&background=60a5fa&color=fff&size=40&bold=true" 
                 alt="User Avatar" class="user-avatar" />
            <div class="user-details">
              <div class="user-name"><?php echo htmlspecialchars($_SESSION['Username']); ?></div>
              <div class="user-role">Manager</div>
            </div>
          </div>
        </div>
      </div>
      <div class="p-6" style="background:#0b1438; flex:1; overflow:auto;">
        <div class="card">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass';">Read-only Panels</div>
          <ul style="margin:8px 0 0 16px; color:#E3E3E3; font-family:'Onest'; font-size:12px;">
            <li>Current stock summary</li>
            <li>Low stock alerts</li>
            <li>Recent transactions (read-only)</li>
          </ul>
        </div>
        <div class="card" style="margin-top:16px;">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass';">Stock Turnover (Sample)</div>
          <canvas id="turnoverChart" height="120"></canvas>
        </div>
        <div class="card" style="margin-top:16px;">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass';">Category Distribution</div>
          <canvas id="distChart" height="120"></canvas>
        </div>
      </div>
    </div>
  </div>
<script>
  (function(){
    const items = <?php echo json_encode($items); ?>;
    const catTotals = {};
    items.forEach(i=>{ const c = (i.category||'Uncategorized'); catTotals[c] = (catTotals[c]||0) + parseInt(i.qty||0,10); });
    const labels = Object.keys(catTotals);
    const values = Object.values(catTotals);

    const ctx1 = document.getElementById('turnoverChart');
    if (ctx1) {
      new Chart(ctx1, {
        type: 'line',
        data: { labels: ['Jan','Feb','Mar','Apr','May','Jun'], datasets: [{ label:'Turnover', data:[3.2,3.5,4.0,4.4,4.1,4.6], borderColor:'#51D55A', backgroundColor:'rgba(81,213,90,0.25)', fill:true, tension:0.3 }] },
        options: { plugins:{ legend:{ labels:{ color:'#E3E3E3' } } }, scales:{ x:{ ticks:{ color:'#E3E3E3' }, grid:{ color:'rgba(255,255,255,0.1)' } }, y:{ ticks:{ color:'#E3E3E3' }, grid:{ color:'rgba(255,255,255,0.1)' } } } }
      });
    }

    const ctx2 = document.getElementById('distChart');
    if (ctx2) {
      new Chart(ctx2, {
        type: 'doughnut',
        data: { labels, datasets:[{ data: values, backgroundColor:['#51D55A','#60a5fa','#fbbf24','#f87171','#34d399','#c084fc'] }] },
        options: { plugins:{ legend:{ labels:{ color:'#E3E3E3' } } } }
      });
    }
  })();
</script>
</body>
</html>


