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
    $items = $_SESSION['items'] ?? [
        [ 'id' => 1, 'sku' => 'W-0001', 'name' => 'Wood Pallet', 'category' => 'Packaging', 'qty' => 120, 'uom' => 'pcs', 'location' => 'A1' ],
        [ 'id' => 2, 'sku' => 'B-0042', 'name' => 'Bolt M8x40', 'category' => 'Hardware', 'qty' => 860, 'uom' => 'pcs', 'location' => 'B3' ],
        [ 'id' => 3, 'sku' => 'T-0100', 'name' => 'Thermal Label 4x6', 'category' => 'Labels', 'qty' => 540, 'uom' => 'roll', 'location' => 'C2' ],
    ];
}

// Get user info
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

    /* Sidebar */
    .sidebar { background:#1D387B; color:#fff; }

    .menu-item {
      border:1px solid #2A4484;
      color:#E3E3E3;
      border-radius:14px;
      padding:14px 18px;
      display:flex;
      align-items:center;
      gap:12px;
      text-decoration:none;
      transition:0.2s;
    }
    .menu-item:hover { background:#132B63; }

    .menu-active {
      background: linear-gradient(90deg, #1A4D3F, #224F78);
      border-color:#51D55A;
      color:white;
    }

    .logout-btn {
      border:1px solid #2A4484;
      color:#fff;
      border-radius:14px;
      padding:14px 18px;
      display:flex;
      align-items:center;
      gap:12px;
      justify-content:center;
      text-decoration:none;
      transition:0.2s;
    }
    .logout-btn:hover { background:#132B63; }

    /* Cards */
    .card {
      background:#1D387B; border:1px solid #2A4484;
      border-radius:10px; padding:14px;
      box-shadow: 0 0 20px 2px #304374;
      margin-bottom:16px; color:#E3E3E3;
      font-family:'Onest';
    }

    /* HEADER */
    .premium-manager-header {
      background:#1D387B;
      border-bottom:1px solid #2A4484;
      padding:16px 50px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      color:white;
      font-family:'Onest', sans-serif;
    }

    .user-profile {
      display:flex; align-items:center; gap:12px;
      padding:8px 16px;
      background: rgba(255,255,255,0.08);
      border-radius:12px;
      border:1px solid rgba(255,255,255,0.15);
    }
  </style>
</head>

<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">
  <div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-gray-200 shadow-lg">

    <!-- Sidebar -->
    <div class="sidebar w-[290px] p-3 pt-5 flex flex-col">
      <div class="ml-2 mb-5">
        <img src="img/logo.svg" alt="IntelliWare" class="w-[200px]" />
      </div>

      <div class="p-2 flex flex-col gap-[10px]">

        <!-- DASHBOARD -->
        <a class="menu-item menu-active" href="dash_manager.php">
          <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3 12l9-9 9 9M4 10v10h6v-6h4v6h6V10"/>
          </svg>
          Dashboard
        </a>

        <!-- INSIGHTS -->
        <a class="menu-item" href="insights.php">
          <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M11 3a9 9 0 110 18 9 9 0 010-18zm0 0v9h9"/>
          </svg>
          Data Mining Insights
        </a>

        <!-- REPORTS -->
        <a class="menu-item" href="reports.php">
          <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3 3v18h18M7 14l3-3 4 4 3-3"/>
          </svg>
          Reports
        </a>

      </div>

      <!-- LOGOUT BUTTON -->
      <div class="mt-auto p-2">
        <a class="logout-btn" href="logout.php">
          <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 3h4v18h-4M10 17l5-5-5-5M15 12H3"/>
          </svg>
          Logout
        </a>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col">

      <!-- HEADER -->
      <div class="premium-manager-header">
        <div class="dashboard-title text-xl font-semibold">Manager / Auditor Dashboard</div>

        <div class="manager-user-section">
          <div class="user-profile">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['Username']); ?>&background=4ade80&color=fff&size=40&bold=true"
                 alt="User Avatar" class="user-avatar w-10 h-10 rounded-full border-2 border-green-400" />
            <div>
              <div class="font-semibold text-white"><?php echo htmlspecialchars($_SESSION['Username']); ?></div>
              <div class="text-xs text-gray-300">Manager</div>
            </div>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="p-6" style="background:#0b1438; flex:1; overflow:auto;">
        <div class="card">
          <div class="font-semibold">Read-only Panels</div>
          <ul class="mt-1 ml-4 text-sm">
            <li>Current stock summary</li>
            <li>Low stock alerts</li>
            <li>Recent transactions (read-only)</li>
          </ul>
        </div>

        <div class="card">
          <div class="font-semibold">Stock Turnover (Sample)</div>
          <canvas id="turnoverChart" height="120"></canvas>
        </div>

        <div class="card">
          <div class="font-semibold">Category Distribution</div>
          <canvas id="distChart" height="120"></canvas>
        </div>

      </div>
    </div>
  </div>

<script>
(function(){
    const items = <?php echo json_encode($items); ?>;
    const catTotals = {};
    items.forEach(i => {
        const c = (i.category || 'Uncategorized');
        catTotals[c] = (catTotals[c] || 0) + parseInt(i.qty || 0, 10);
    });

    const labels = Object.keys(catTotals);
    const values = Object.values(catTotals);

    const ctx1 = document.getElementById('turnoverChart');
    if (ctx1) {
      new Chart(ctx1, {
        type: 'line',
        data: { labels: ['Jan','Feb','Mar','Apr','May','Jun'], datasets: [{
          label:'Turnover', data:[3.2,3.5,4.0,4.4,4.1,4.6],
          borderColor:'#51D55A', backgroundColor:'rgba(81,213,90,0.25)', fill:true, tension:0.3
        }] },
        options: { plugins:{ legend:{ labels:{ color:'#E3E3E3' } } } }
      });
    }

    const ctx2 = document.getElementById('distChart');
    if (ctx2) {
      new Chart(ctx2, {
        type: 'doughnut',
        data: { labels, datasets:[{
          data: values,
          backgroundColor:['#51D55A','#60a5fa','#fbbf24','#f87171','#34d399','#c084fc']
        }] },
        options: { plugins:{ legend:{ labels:{ color:'#E3E3E3' } } } }
      });
    }
})();
</script>

</body>
</html>
