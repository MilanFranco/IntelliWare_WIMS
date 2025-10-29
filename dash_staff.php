<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'staff') { header('Location: dash_admin.php'); exit(); }

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
  <title>Staff Dashboard | IntelliWare</title>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
    .menu-item { border:1px solid #2A4484; color:#E3E3E3; border-radius:10px; padding:12px 16px; display:block; text-decoration:none; }
    .menu-item:hover { background:#13275B; }
    .btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; }
    
    /* Premium Staff Styles */
    .premium-staff-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.1),
        0 1px 0 rgba(255, 255, 255, 0.8);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      backdrop-filter: blur(10px);
    }
    
    .staff-user-section {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 16px;
      background: rgba(139, 92, 246, 0.1);
      border-radius: 12px;
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(139, 92, 246, 0.2);
    }
    
    .user-profile:hover {
      background: rgba(139, 92, 246, 0.15);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
    }
    
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #8b5cf6;
      object-fit: cover;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }
    
    .user-avatar:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
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
        <a class="menu-item" href="dash_staff.php">Dashboard</a>
        <a class="menu-item" href="transactions.php">Stock In/Out</a>
        <a class="menu-item" href="products.php">View Products</a>
        <a class="menu-item" href="transaction_logs.php">My Transaction History</a>
      </div>
      <div class="mt-auto p-2"><a class="btn" href="logout.php">Logout</a></div>
    </div>
    <div class="flex-1 flex flex-col">
      <div class="premium-staff-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">
        <div class="font-onest text-[20px] font-semibold text-gray-800">Staff Dashboard</div>
        <div class="staff-user-section">
          <div class="user-profile">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['Username']); ?>&background=8b5cf6&color=fff&size=40&bold=true" 
                 alt="User Avatar" class="user-avatar" />
            <div class="user-details">
              <div class="user-name"><?php echo htmlspecialchars($_SESSION['Username']); ?></div>
              <div class="user-role">Staff Member</div>
            </div>
          </div>
        </div>
      </div>
      <div class="p-6" style="background:#0b1438; flex:1; overflow:auto;">
        <div class="card">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass';">Quick Actions</div>
          <div style="display:flex; gap:8px; margin-top:10px;">
            <a class="btn" href="transactions.php?action=in">Add Stock (In)</a>
            <a class="btn" href="transactions.php?action=out">Dispatch Stock (Out)</a>
            <a class="btn" href="products.php">View Stock Levels</a>
          </div>
        </div>
        <div class="card" style="margin-top:16px;">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass';">Low Stock</div>
          <ul style="margin:8px 0 0 16px; color:#E3E3E3; font-family:'Onest'; font-size:12px;">
            <?php $low = array_filter($items,function($i){return (int)$i['qty']<50;}); if(empty($low)) { echo '<li>None</li>'; } foreach($low as $it){ echo '<li>'.htmlspecialchars($it['sku'].' — '.$it['name'].' ('.(int)$it['qty'].')').'</li>'; } ?>
          </ul>
        </div>

        <div class="card" style="margin-top:16px;">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass';">Stock by Category</div>
          <canvas id="catChart" height="120"></canvas>
        </div>
      </div>
    </div>
  </div>
<script>
  (function(){
    const ctx = document.getElementById('catChart');
    if(!ctx) return;
    const data = <?php
      $categoryTotals = [];
      foreach ($items as $it) {
        $cat = (string)($it['category'] ?? 'Uncategorized');
        $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0) + (int)$it['qty'];
      }
      echo json_encode([
        'labels' => array_keys($categoryTotals),
        'values' => array_values($categoryTotals)
      ]);
    ?>;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Quantity',
          data: data.values,
          backgroundColor: 'rgba(81,213,90,0.6)',
          borderColor: 'rgba(81,213,90,1)',
          borderWidth: 1
        }]
      },
      options: {
        plugins: { legend: { labels: { color: '#E3E3E3' } } },
        scales: {
          x: { ticks: { color: '#E3E3E3' }, grid: { color: 'rgba(255,255,255,0.1)' } },
          y: { ticks: { color: '#E3E3E3' }, grid: { color: 'rgba(255,255,255,0.1)' } }
        }
      }
    });
  })();
</script>
</body>
</html>


