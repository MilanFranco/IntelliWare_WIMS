<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

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
  <title>Reports | IntelliWare</title>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .card { background:#1D387B; border:1px solid #2A4484; border-radius:10px; padding:14px; box-shadow: 0 0 20px 2px #304374; }
    .menu-item { border:1px solid #2A4484; color:#E3E3E3; border-radius:10px; padding:12px 16px; display:block; text-decoration:none; }
    .menu-item:hover { background:#13275B; }
    .btn { background:#51D55A; color:#fff; border-radius:6px; padding:8px 10px; font-size:12px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
    .input { background:#13275B; color:#fff; border:1px solid #304374; padding:8px; border-radius:6px; width:100%; }
    .label { color:#E3E3E3; font-family:'Onest'; font-size:12px; margin-bottom:6px; display:block; }
    .table { width:100%; border-collapse:collapse; }
    .table th, .table td { padding:10px; border-bottom:1px solid #2A4484; color:#E3E3E3; font-family:'Onest'; font-size:12px; }
    
    /* Premium Reports Styles */
    .premium-reports-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.1),
        0 1px 0 rgba(255, 255, 255, 0.8);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      backdrop-filter: blur(10px);
    }
    
    .reports-user-section {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 16px;
      background: rgba(81, 213, 90, 0.1);
      border-radius: 12px;
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(81, 213, 90, 0.2);
    }
    
    .user-profile:hover {
      background: rgba(81, 213, 90, 0.15);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2);
    }
    
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #51D55A;
      object-fit: cover;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3);
    }
    
    .user-avatar:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 20px rgba(81, 213, 90, 0.4);
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
    
    /* Premium Card Styles */
    .premium-card {
      background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
      border: 1px solid rgba(42, 68, 132, 0.6);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(81, 213, 90, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .premium-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(81, 213, 90, 0.5), transparent);
    }
    
    .premium-card:hover {
      transform: translateY(-2px);
      box-shadow: 
        0 12px 40px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(81, 213, 90, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
      border-color: rgba(81, 213, 90, 0.3);
    }
    
    .premium-button {
      background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 12px 24px;
      font-size: 14px;
      font-weight: 600;
      font-family: 'Onest', sans-serif;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 
        0 4px 12px rgba(81, 213, 90, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
      position: relative;
      overflow: hidden;
      text-decoration: none;
      display: inline-block;
    }
    
    .premium-button::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }
    
    .premium-button:hover {
      transform: translateY(-1px);
      box-shadow: 
        0 6px 20px rgba(81, 213, 90, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }
    
    .premium-button:hover::before {
      left: 100%;
    }
    
    .premium-button:active {
      transform: translateY(0);
      box-shadow: 
        0 2px 8px rgba(81, 213, 90, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }
    
    .premium-input {
      background: rgba(19, 39, 91, 0.8);
      color: #E3E3E3;
      border: 1px solid rgba(48, 67, 116, 0.6);
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 14px;
      font-family: 'Onest', sans-serif;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      backdrop-filter: blur(10px);
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .premium-input:focus {
      outline: none;
      border-color: #51D55A;
      box-shadow: 
        0 0 0 3px rgba(81, 213, 90, 0.1),
        inset 0 2px 4px rgba(0, 0, 0, 0.1);
      background: rgba(19, 39, 91, 0.9);
    }
    
    .premium-table {
      width: 100%;
      border-collapse: collapse;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .premium-table th {
      background: linear-gradient(135deg, #1D387B 0%, #13275B 100%);
      color: #E3E3E3;
      padding: 16px;
      font-family: 'Onest', sans-serif;
      font-weight: 600;
      font-size: 14px;
      text-align: left;
      border-bottom: 2px solid rgba(81, 213, 90, 0.3);
    }
    
    .premium-table td {
      padding: 16px;
      border-bottom: 1px solid rgba(42, 68, 132, 0.3);
      color: #E3E3E3;
      font-family: 'Onest', sans-serif;
      font-size: 14px;
      transition: background-color 0.2s;
    }
    
    .premium-table tbody tr:hover td {
      background: rgba(81, 213, 90, 0.05);
    }
    
    .premium-sidebar {
      background: linear-gradient(180deg, #1D387B 0%, #13275B 100%);
      border-right: 1px solid rgba(42, 68, 132, 0.6);
      box-shadow: 
        4px 0 20px rgba(0, 0, 0, 0.3),
        inset -1px 0 0 rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
    }
    
    .premium-menu-item {
      border: 1px solid rgba(42, 68, 132, 0.6);
      color: #E3E3E3;
      border-radius: 12px;
      padding: 14px 18px;
      display: block;
      text-decoration: none;
      font-family: 'Onest', sans-serif;
      font-weight: 500;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .premium-menu-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(81, 213, 90, 0.1), transparent);
      transition: left 0.5s;
    }
    
    .premium-menu-item:hover {
      background: linear-gradient(135deg, rgba(19, 39, 91, 0.8) 0%, rgba(29, 56, 123, 0.6) 100%);
      border-color: rgba(81, 213, 90, 0.3);
      transform: translateX(4px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .premium-menu-item:hover::before {
      left: 100%;
    }
    
    .premium-scrollbar::-webkit-scrollbar {
      width: 8px;
    }
    
    .premium-scrollbar::-webkit-scrollbar-track {
      background: rgba(19, 39, 91, 0.3);
      border-radius: 4px;
    }
    
    .premium-scrollbar::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
      border-radius: 4px;
      transition: all 0.3s;
    }
    
    .premium-scrollbar::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, #4BC052 0%, #3EA147 100%);
    }
    
    /* User Dropdown Menu */
    .user-dropdown {
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 8px;
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 12px;
      box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(81, 213, 90, 0.1);
      border: 1px solid rgba(81, 213, 90, 0.2);
      min-width: 200px;
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 99999;
    }
    
    .user-dropdown.show {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
      display: block !important;
    }
    
    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      color: #374151;
      text-decoration: none;
      font-family: 'Onest', sans-serif;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s ease;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .dropdown-item:last-child {
      border-bottom: none;
    }
    
    .dropdown-item:hover {
      background: rgba(81, 213, 90, 0.1);
      color: #1f2937;
    }
    
    .dropdown-item svg {
      width: 16px;
      height: 16px;
      color: #6b7280;
    }
    
    .dropdown-item:hover svg {
      color: #51D55A;
    }
    
    .user-profile {
      cursor: pointer;
      position: relative;
    }
  </style>
</head>
<body class="w-full h-screen bg-[#020A27] px-3 pt-3 flex items-start justify-center">
  <div class="w-full h-full flex flex-row rounded-t-[15px] overflow-hidden bg-gray-200 shadow-lg">
    <div class="premium-sidebar w-[290px] text-white p-3 pt-5 flex flex-col">
      <div class="ml-2 mb-4">
        <img src="img/logo.svg" alt="IntelliWare" class="w-[180px]" />
        <div class="text-[10px]">Warehouse Inventory System</div>
      </div>
      <div class="p-2 flex flex-col gap-[8px]">
        <a class="premium-menu-item" href="<?php echo $role==='admin'?'dash_admin.php':'dash_manager.php'; ?>">Dashboard</a>
        <a class="premium-menu-item" href="reports.php">Reports</a>
        <a class="premium-menu-item" href="insights.php">Insights</a>
      </div>
      <div class="mt-auto p-2"><a class="premium-button" href="logout.php">Logout</a></div>
    </div>
    <div class="flex-1 flex flex-col">
      <div class="premium-reports-header px-[50px] py-[20px] h-[67px] flex justify-between items-center">
        <div class="font-onest text-[20px] font-semibold text-gray-800">Reports</div>
        <div class="reports-user-section">
          <div class="user-profile" onclick="toggleUserDropdown()">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['Username']); ?>&background=51D55A&color=fff&size=40&bold=true" 
                 alt="User Avatar" class="user-avatar" />
            <div class="user-details">
              <div class="user-name"><?php echo htmlspecialchars($_SESSION['Username']); ?></div>
              <div class="user-role"><?php echo htmlspecialchars(strtoupper($role)); ?></div>
            </div>
            <div class="user-dropdown" id="userDropdown">
              <a href="settings.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Settings
              </a>
              <a href="reports.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Reports
              </a>
              <a href="insights.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Insights
              </a>
              <a href="logout.php" class="dropdown-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Logout
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="p-6 premium-scrollbar" style="background:#0b1438; flex:1; overflow:auto;">
        <div class="premium-card">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">Filters</div>
          <form style="display:grid; grid-template-columns: repeat(4,1fr); gap:12px; margin-top:8px;">
            <div><label class="label">Date From</label><input type="date" class="premium-input" /></div>
            <div><label class="label">Date To</label><input type="date" class="premium-input" /></div>
            <div><label class="label">Category</label><input class="premium-input" placeholder="All" /></div>
            <div style="display:flex; align-items:flex-end;"><button class="premium-button" type="button">Generate</button></div>
          </form>
        </div>
        <div class="premium-card" style="margin-top:16px;">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">Stock Snapshot</div>
          <table class="premium-table" style="margin-top:8px;">
            <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Qty</th></tr></thead>
            <tbody>
              <?php foreach($items as $it): ?>
                <tr>
                  <td><?php echo htmlspecialchars($it['sku']); ?></td>
                  <td><?php echo htmlspecialchars($it['name']); ?></td>
                  <td><?php echo htmlspecialchars($it['category']); ?></td>
                  <td><?php echo (int)$it['qty']; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div style="margin-top:16px; display:flex; gap:12px;">
            <button class="premium-button" type="button">Download PDF</button>
            <button class="premium-button" type="button">Download Excel</button>
          </div>
        </div>
        <div class="premium-card" style="margin-top:16px;">
          <div style="color:#E3E3E3; font-weight:600; font-family:'Overpass'; font-size:16px; margin-bottom:16px;">Category Breakdown</div>
          <canvas id="reportCatChart" height="120"></canvas>
        </div>
      </div>
    </div>
  </div>
<script>
  // User dropdown functionality
  function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
  }

  // Close dropdown when clicking outside
  document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const userProfile = document.querySelector('.user-profile');
    
    if (!userProfile.contains(event.target)) {
      dropdown.classList.remove('show');
    }
  });

  // Chart initialization
  (function(){
    const items = <?php echo json_encode($items); ?>;
    const catTotals = {};
    items.forEach(i=>{ const c=(i.category||'Uncategorized'); catTotals[c]=(catTotals[c]||0)+parseInt(i.qty||0,10);});
    const labels = Object.keys(catTotals);
    const values = Object.values(catTotals);
    const ctx = document.getElementById('reportCatChart');
    if(ctx){
      new Chart(ctx, {
        type: 'bar',
        data: { 
          labels, 
          datasets:[{ 
            label:'Qty', 
            data: values, 
            backgroundColor:'#60a5fa',
            borderColor: '#3b82f6',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
          }] 
        },
        options: { 
          plugins:{ 
            legend:{ 
              labels:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 12
                }
              } 
            } 
          }, 
          scales:{ 
            x:{ 
              ticks:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 11
                }
              }, 
              grid:{ 
                color:'rgba(255,255,255,0.1)',
                drawBorder: false
              }
            }, 
            y:{ 
              ticks:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 11
                }
              }, 
              grid:{ 
                color:'rgba(255,255,255,0.1)',
                drawBorder: false
              }
            } 
          },
          animation: {
            duration: 2000,
            easing: 'easeInOutQuart'
          }
        }
      });
    }
  })();
</script>
</body>
</html>


