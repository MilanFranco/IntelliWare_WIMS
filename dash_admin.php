<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
if (($_SESSION['Role'] ?? '') !== 'admin') { header('Location: dash_staff.php'); exit(); }

// Include layout and database
require_once 'src/layout.php';
require_once 'config/db.php';

// Get products from database
$items = [];
$productQuery = "SELECT 
                    product_id as id, 
                    CONCAT('P-', LPAD(product_id, 4, '0')) as sku, 
                    product_name as name, 
                    category, 
                    quantity as qty, 
                    unit as uom, 
                    reorder_point
                 FROM products";
$productResult = $conn->query($productQuery);

if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $items[] = $row;
    }
}

// Get user info
$userQuery = "SELECT username, role, full_name FROM users WHERE username = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $_SESSION['Username']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
$userStmt->close();

// Calculate metrics
$lowStockItems = array_filter($items, function($i){ 
    return (int)$i['qty'] <= (int)($i['reorder_point'] ?? 10); 
});
$lowStockCount = count($lowStockItems);
$totalSKUs = count($items);
$totalQty = array_sum(array_map(function($i){return (int)$i['qty'];}, $items));
$totalCategories = count(array_unique(array_map(function($i){return $i['category'];}, $items)));

// Get Recent Transactions (Last 5)
$recentTransactions = [];
$transQuery = "SELECT 
                    t.transaction_type, 
                    t.quantity, 
                    t.transaction_date, 
                    p.product_name, 
                    u.username
                FROM transactions t
                JOIN products p ON t.product_id = p.product_id
                JOIN users u ON t.user_id = u.user_id
                ORDER BY t.transaction_date DESC
                LIMIT 5";
$transResult = $conn->query($transQuery);
if ($transResult && $transResult->num_rows > 0) {
    while ($row = $transResult->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}

$conn->close();

// Start layout with notification count
renderLayoutStart('Admin Dashboard', $_SESSION['Role'], $userInfo['full_name'] ?? $_SESSION['Username'], $lowStockCount);
?>

<!-- Metric Cards -->
<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
  <div class="premium-metric-card premium-metric">
    <div>Total Stock-keeping Unit</div>
    <div class="num"><?php echo $totalSKUs; ?></div>
  </div>
  <div class="premium-metric-card premium-metric">
    <div>Total Quantiy</div>
    <div class="num"><?php echo number_format($totalQty); ?></div>
  </div>
  <div class="premium-metric-card premium-metric">
    <div>Low Stock Items</div>
    <div class="num"><?php echo $lowStockCount; ?></div>
  </div>
  <div class="premium-metric-card premium-metric">
    <div>Categories</div>
    <div class="num"><?php echo $totalCategories; ?></div>
  </div>
</div>

<!-- Content Layout -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-top:16px;">
  
  <!-- Column 1: Recent Activity & Chart -->
  <div style="display:flex; flex-direction:column; gap:16px;">
      
      <!-- Recent Transactions Card -->
      <div class="premium-metric-card">
          <div class="card-title">Recent Transactions</div>
          <table class="activity-table">
              <?php if (empty($recentTransactions)): ?>
                  <tr><td style="text-align:center; color:#9aa6d1; padding:20px;">No transactions recorded yet.</td></tr>
              <?php else: ?>
                  <?php foreach($recentTransactions as $tx): ?>
                      <tr>
                          <td>
                              <?php if($tx['transaction_type'] === 'stock_in'): ?>
                                  <span class="badge badge-in">IN</span>
                              <?php else: ?>
                                  <span class="badge badge-out">OUT</span>
                              <?php endif; ?>
                          </td>
                          <td>
                              <strong><?php echo htmlspecialchars($tx['product_name']); ?></strong>
                          </td>
                          <td>
                              Qty: <strong><?php echo (int)$tx['quantity']; ?></strong>
                          </td>
                          <td style="color:#9aa6d1;"><?php echo htmlspecialchars($tx['username']); ?></td>
                          <td style="color:#9aa6d1; text-align:right;"><?php echo date('M j, g:ia', strtotime($tx['transaction_date'])); ?></td>
                      </tr>
                  <?php endforeach; ?>
              <?php endif; ?>
          </table>
      </div>

      <!-- Chart Card -->
      <div class="premium-metric-card">
          <div class="card-title">Inventory Overview (by SKU Count)</div>
          <canvas id="overviewChart" height="120"></canvas>
      </div>
  </div>

  <!-- Column 2: Low Stock & Quick Links -->
  <div style="display:flex; flex-direction:column; gap:16px;">
      
      <!-- Actionable Low Stock Card -->
      <div class="premium-metric-card">
          <div class="card-title">Actionable Low Stock</div>
          <div class="low-stock-list">
              <?php if (empty($lowStockItems)): ?>
                  <div style="text-align:center; color:#9aa6d1; padding:20px 0;">All items are well-stocked!</div>
              <?php else: ?>
                  <?php foreach(array_slice($lowStockItems, 0, 5) as $item): ?>
                      <div class="low-stock-item">
                          <span class="low-stock-name"><?php echo htmlspecialchars($item['name']); ?></span>
                          <span class="low-stock-qty"><?php echo (int)$item['qty']; ?> left</span>
                      </div>
                  <?php endforeach; ?>
                  <?php if ($lowStockCount > 5): ?>
                      <div style="color:#9aa6d1; font-size: 13px; text-align: center; margin-top: 5px;">
                          ...and <?php echo $lowStockCount - 5; ?> more.
                      </div>
                  <?php endif; ?>
              <?php endif; ?>
          </div>
      </div>

      <!-- Quick Links Card -->
      <div class="premium-metric-card">
          <div class="card-title">Quick Links</div>
          <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <a class="premium-button" href="products.php">Manage Products</a>
              <a class="premium-button" href="transactions.php">Stock In/Out</a>
              <a class="premium-button" href="reports.php">View Reports</a>
          </div>
      </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Chart initialization
(function(){
  const items = <?php echo json_encode($items); ?>;
  
  const low = items.filter(i => {
      const qty = parseInt(i.qty || 0, 10);
      const reorder = parseInt(i.reorder_point || 10, 10);
      return qty <= reorder;
  }).length;
  
  const ok = items.filter(i => {
      const qty = parseInt(i.qty || 0, 10);
      const reorder = parseInt(i.reorder_point || 10, 10);
      return qty > reorder && qty < (reorder * 5); 
  }).length;
  
  const high = items.filter(i => {
      const qty = parseInt(i.qty || 0, 10);
      const reorder = parseInt(i.reorder_point || 10, 10);
      return qty >= (reorder * 5);
  }).length;

  const ctx = document.getElementById('overviewChart');
  if(ctx){
    const myChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Low','Normal','High'],
        datasets: [{ 
          label:'SKU Count', 
          data:[low, ok, high], 
          backgroundColor:['#f87171','#60a5fa','#34d399'],
          borderColor: ['#ef4444', '#3b82f6', '#10b981'],
          borderWidth: 2,
          borderRadius: 8,
          borderSkipped: false,
        }]
      },
      options: { 
        onClick: (e) => {
            const points = myChart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true);
            if (points.length) {
                const firstPoint = points[0];
                const label = myChart.data.labels[firstPoint.index];
                let statusFilter = '';
                
                if (label === 'Low') statusFilter = 'low';
                if (label === 'Normal') statusFilter = 'normal';
                if (label === 'High') statusFilter = 'high';
                
                if (statusFilter) {
                    window.location.href = `products.php?status=${statusFilter}`;
                }
            }
        },
        
        onHover: (event, chartElement) => {
            const canvas = event.native.target;
            canvas.style.cursor = chartElement[0] ? 'pointer' : 'default';
        },

        plugins:{ 
          legend:{ 
            labels:{ 
              color:'#E3E3E3',
              font: { family: 'Onest', size: 12 }
            } 
          } 
        }, 
        scales:{ 
          x:{ 
            ticks:{ 
              color:'#E3E3E3',
              font: { family: 'Onest', size: 11 }
            }, 
            grid:{ 
              color:'rgba(255,255,255,0.1)',
              drawBorder: false
            } 
          }, 
          y:{ 
            ticks:{ 
              color:'#E3E3E3',
              font: { family: 'Onest', size: 11 }
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

<?php renderLayoutEnd(); ?>