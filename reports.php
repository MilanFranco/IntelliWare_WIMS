<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

// 1. Include your new layout functions
// (Assuming you saved the file in 'components/layout.php')
require_once 'src/layout.php';
require_once 'config/db.php';

// 2. All database and report logic
// Get filter parameters
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

// --- Build conditions for MAIN product query (all filters) ---
$mainWhereConditions = [];
$mainParams = [];
$mainTypes = '';

if ($dateFrom) {
    $mainWhereConditions[] = "DATE(created_at) >= ?";
    $mainParams[] = $dateFrom;
    $mainTypes .= 's';
}
if ($dateTo) {
    $mainWhereConditions[] = "DATE(created_at) <= ?";
    $mainParams[] = $dateTo;
    $mainTypes .= 's';
}
if ($categoryFilter) {
    $mainWhereConditions[] = "category = ?";
    $mainParams[] = $categoryFilter;
    $mainTypes .= 's';
}

$mainWhereClause = '';
if (count($mainWhereConditions) > 0) {
    $mainWhereClause = 'WHERE ' . implode(' AND ', $mainWhereConditions);
}

// --- Build conditions for CATEGORY dropdown query (dates only) ---
$categoryWhereConditions = [];
$categoryParams = [];
$categoryTypes = '';

if ($dateFrom) {
    $categoryWhereConditions[] = "DATE(created_at) >= ?";
    $categoryParams[] = $dateFrom;
    $categoryTypes .= 's';
}
if ($dateTo) {
    $categoryWhereConditions[] = "DATE(created_at) <= ?";
    $categoryParams[] = $dateTo;
    $categoryTypes .= 's';
}

// Add the non-empty/null condition that was there before
$categoryWhereConditions[] = "category IS NOT NULL AND category != ''";

// Always start with WHERE for this query
$categoryWhereClause = 'WHERE ' . implode(' AND ', $categoryWhereConditions);


// --- Get products from database (using main filters) ---
$items = [];
$productQuery = "SELECT 
    product_id as id, 
    CONCAT('P-', LPAD(product_id, 4, '0')) as sku, 
    product_name as name, 
    category, 
    quantity as qty, 
    unit as uom, 
    'A1' as location,
    created_at
FROM products 
$mainWhereClause 
ORDER BY category, product_name";

if ($stmt = $conn->prepare($productQuery)) {
    if (count($mainParams) > 0) {
        // Use main params
        $stmt->bind_param($mainTypes, ...$mainParams); 
    }
    $stmt->execute();
    $productResult = $stmt->get_result();
    
    if ($productResult && $productResult->num_rows > 0) {
        while ($row = $productResult->fetch_assoc()) {
            $items[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Failed to prepare main product query: " . $conn->error);
}

// --- Get unique categories for filter dropdown (using date filters) ---
// This query now respects the date filters
$categoriesQuery = "SELECT DISTINCT category FROM products $categoryWhereClause ORDER BY category";
$allCategories = [];

// Use a prepared statement for security and consistency
if ($stmt_cat = $conn->prepare($categoriesQuery)) {
    if (count($categoryParams) > 0) {
        // Use category (date-only) params
        $stmt_cat->bind_param($categoryTypes, ...$categoryParams); 
    }
    $stmt_cat->execute();
    $categoriesResult = $stmt_cat->get_result();

    if ($categoriesResult) {
        while ($row = $categoriesResult->fetch_assoc()) {
            $allCategories[] = $row['category'];
        }
    }
    $stmt_cat->close();
} else {
    error_log("Failed to prepare category query: " . $conn->error);
}

// --- Build category groups (this logic is unchanged) ---
$categoryGroups = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'Uncategorized';
    if (!isset($categoryGroups[$cat])) {
        $categoryGroups[$cat] = [
            'items' => [],
            'total_qty' => 0
        ];
    }
    $categoryGroups[$cat]['items'][] = $item;
    $categoryGroups[$cat]['total_qty'] += (int)($item['qty'] ?? 0);
}

$conn->close();

// 3. Define variables for the layout
$pageTitle = 'Reports';
$username = $_SESSION['Username'];
// This page doesn't calculate low stock, so we pass 0
$lowStockCount = 0; 

// 4. Start the layout
renderLayoutStart($pageTitle, $role, $username, $lowStockCount);
?>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  /* Premium Card Styles (from original file) */
  .premium-card {
    background: linear-gradient(145deg, rgba(29, 56, 123, 0.95) 0%, rgba(18, 39, 91, 0.95) 100%);
    border: 1px solid rgba(42, 68, 132, 0.6);
    border-radius: 16px;
    padding: 24px;
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
  
  /* Input/Select Styles (from original file) */
  .premium-input, .premium-select {
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
    width: 100%;
  }
  .premium-input:focus, .premium-select:focus {
    outline: none;
    border-color: #51D55A;
    box-shadow: 
      0 0 0 3px rgba(81, 213, 90, 0.1),
      inset 0 2px 4px rgba(0, 0, 0, 0.1);
    background: rgba(19, 39, 91, 0.9);
  }
  .premium-select option {
    background: #13275B;
    color: #E3E3E3;
  }

  /* Page-specific styles */
  .label {
    color: #E3E3E3;
    font-family: 'Onest';
    font-size: 13px;
    margin-bottom: 8px;
    display: block;
    font-weight: 500;
  }
  .category-browser {
    display: grid;
    grid-template-columns: 260px 1fr 1fr;
    gap: 20px;
    margin-top: 12px;
    min-height: 400px;
  }
  .category-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 500px;
    overflow-y: auto;
    padding-right: 8px;
  }
  .category-button {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid rgba(81, 213, 90, 0.2);
    background: rgba(19, 39, 91, 0.6);
    color: #e2e8f0;
    cursor: pointer;
    transition: all 0.25s ease;
    text-align: left;
  }
  .category-button:hover {
    background: rgba(29, 56, 123, 0.65);
    border-color: rgba(81, 213, 90, 0.35);
    transform: translateX(4px);
  }
  .category-button.active {
    background: linear-gradient(135deg, rgba(81, 213, 90, 0.25) 0%, rgba(29, 56, 123, 0.8) 100%);
    border-color: rgba(81, 213, 90, 0.5);
    box-shadow: 0 6px 18px rgba(81, 213, 90, 0.15);
  }
  .category-button-title {
    font-family: 'onest', sans-serif;
    font-weight: 600;
    font-size: 15px;
  }
  .category-button-meta {
    font-family: 'Onest', sans-serif;
    font-size: 11px;
    color: rgba(226, 232, 240, 0.75);
    display: flex;
    justify-content: space-between;
  }
  .panel {
    border: 1px solid rgba(42, 68, 132, 0.35);
    border-radius: 16px;
    background: rgba(9, 18, 52, 0.85);
    backdrop-filter: blur(6px);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow: hidden;
  }
  .panel-title {
    font-family: 'onest', sans-serif;
    font-weight: 600;
    font-size: 16px;
    color: #f8fafc;
  }
  .panel-subtitle {
    font-family: 'Onest', sans-serif;
    font-size: 13px;
    color: rgba(226, 232, 240, 0.7);
    margin-top: 2px;
  }
  .panel-content {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 440px;
  }
  .item-pill {
    padding: 12px 14px;
    border-radius: 10px;
    background: rgba(29, 56, 123, 0.55);
    border: 1px solid rgba(42, 68, 132, 0.35);
    color: #e0e7ff;
    font-family: 'Onest', sans-serif;
    font-size: 13px;
  }
  .status-row {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 0.8fr) 120px;
    gap: 12px;
    align-items: center;
    padding: 12px 10px;
    border-bottom: 1px solid rgba(42, 68, 132, 0.28);
  }
  .status-row:last-child {
    border-bottom: none;
  }
  .status-product {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .status-product span:first-child {
    font-family: 'onest', sans-serif;
    font-weight: 600;
    color: #f1f5ff;
    font-size: 14px;
  }
  .status-product span:last-child {
    font-family: 'Onest', sans-serif;
    font-size: 11px;
    color: rgba(226, 232, 240, 0.68);
  }
  .status-qty {
    font-family: 'Onest', sans-serif;
    font-weight: 600;
    color: #e2e8f0;
    font-size: 14px;
    text-align: right;
  }
  .status-pill {
    justify-self: flex-end;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-family: 'Onest', sans-serif;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .status-ok {
    background: rgba(16, 185, 129, 0.18);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.35);
  }
  .status-low {
    background: rgba(239, 68, 68, 0.18);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.35);
  }
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    text-align: center;
    color: rgba(226, 232, 240, 0.6);
    font-family: 'Onest', sans-serif;
  }
  .empty-state svg {
    width: 64px;
    height: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
  }
  .filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(81, 213, 90, 0.15);
    border: 1px solid rgba(81, 213, 90, 0.3);
    border-radius: 8px;
    color: #6ee7b7;
    font-size: 12px;
    font-family: 'Onest', sans-serif;
    margin-top: 8px;
  }
</style>
<div class="premium-card">
  <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:17px; margin-bottom:18px;">Filters</div>
  <form method="GET" action="reports.php" style="display:grid; grid-template-columns: repeat(4,1fr); gap:14px; margin-top:8px;">
    <div>
      <label class="label">Date From</label>
      <input type="date" name="date_from" class="premium-input" value="<?php echo htmlspecialchars($dateFrom); ?>" />
    </div>
    <div>
      <label class="label">Date To</label>
      <input type="date" name="date_to" class="premium-input" value="<?php echo htmlspecialchars($dateTo); ?>" />
    </div>
    <div>
      <label class="label">Category</label>
      <select name="category" class="premium-select">
        <option value="">All Categories</option>
        <?php foreach ($allCategories as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($cat); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex; align-items:flex-end; gap:10px;">
      <button class="premium-button" type="submit">Generate</button>
      <?php if ($dateFrom || $dateTo || $categoryFilter): ?>
        <a href="reports.php" class="premium-button" style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);">Clear</a>
      <?php endif; ?>
    </div>
  </form>
  <?php if ($dateFrom || $dateTo || $categoryFilter): ?>
    <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
      <?php if ($dateFrom): ?>
        <span class="filter-badge">From: <?php echo htmlspecialchars($dateFrom); ?></span>
      <?php endif; ?>
      <?php if ($dateTo): ?>
        <span class="filter-badge">To: <?php echo htmlspecialchars($dateTo); ?></span>
      <?php endif; ?>
      <?php if ($categoryFilter): ?>
        <span class="filter-badge">Category: <?php echo htmlspecialchars($categoryFilter); ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($items)): ?>
<div class="premium-card" style="margin-top:16px;">
  <div class="empty-state">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <div style="font-size:16px; font-weight:600; margin-bottom:8px;">No Products Found</div>
    <div style="font-size:14px;">No products match your current filters. Try adjusting your search criteria.</div>
  </div>
</div>
<?php else: ?>
<div class="premium-card" style="margin-top:16px;">
  <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:17px; margin-bottom:4px;">Stock Snapshot</div>
  <div style="color:rgba(226, 232, 240, 0.7); font-family:'Onest'; font-size:13px; margin-bottom:18px;">
    Showing <?php echo count($items); ?> product<?php echo count($items) !== 1 ? 's' : ''; ?> across <?php echo count($categoryGroups); ?> categor<?php echo count($categoryGroups) !== 1 ? 'ies' : 'y'; ?>
  </div>
  <div class="category-browser">
    <div class="category-list premium-scrollbar">
      <?php foreach ($categoryGroups as $categoryName => $group):
        $productCount = count($group['items']);
        $totalQty = $group['total_qty'];
        $catKeyAttr = htmlspecialchars(json_encode($categoryName), ENT_QUOTES);
      ?>
      <button class="category-button" type="button" onclick="toggleCategory(<?php echo $catKeyAttr; ?>, this)">
        <span class="category-button-title"><?php echo htmlspecialchars($categoryName); ?></span>
        <span class="category-button-meta">
          <span><?php echo $productCount; ?> item<?php echo $productCount !== 1 ? 's' : ''; ?></span>
          <span>Qty: <?php echo number_format($totalQty); ?></span>
        </span>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="panel" id="itemPanel">
      <div>
        <div class="panel-title">Items</div>
        <div class="panel-subtitle" id="itemPanelMeta">Select a category to view items.</div>
      </div>
      <div class="panel-content premium-scrollbar" id="itemList">
        <div class="item-pill">No category selected.</div>
      </div>
    </div>
    <div class="panel" id="statusPanel">
      <div>
        <div class="panel-title">Stock &amp; Status</div>
        <div class="panel-subtitle" id="statusPanelMeta">Current stock levels and status.</div>
      </div>
      <div class="panel-content premium-scrollbar" id="statusList">
        <div class="item-pill">Choose a category to display stock details.</div>
      </div>
    </div>
  </div>
  <div style="margin-top:18px; display:flex; gap:12px;">
    <button class="premium-button" type="button" onclick="downloadPDF()">
      <svg style="width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      Download PDF
    </button>
    <button class="premium-button" type="button" onclick="downloadExcel()">
      <svg style="width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
      </svg>
      Download Excel
    </button>
  </div>
</div>
<div class="premium-card" style="margin-top:16px;">
  <div style="color:#E3E3E3; font-weight:600; font-family:'onest'; font-size:17px; margin-bottom:18px;">Category Breakdown</div>
  <canvas id="reportCatChart" height="100"></canvas>
</div>
<?php endif; ?>

<script>
  // Chart initialization
  (function(){
    const items = <?php echo json_encode($items); ?>;
    if (items.length === 0) return;
    
    const catTotals = {};
    items.forEach(i=>{ 
      const c=(i.category||'Uncategorized'); 
      catTotals[c]=(catTotals[c]||0)+parseInt(i.qty||0,10);
    });
    const labels = Object.keys(catTotals);
    const values = Object.values(catTotals);
    
    // Generate dynamic colors
    const colors = labels.map((_, i) => {
      const hue = (i * 137.5) % 360; // Golden angle
      return `hsl(${hue}, 70%, 60%)`;
    });
    
    const ctx = document.getElementById('reportCatChart');
    if(ctx){
      new Chart(ctx, {
        type: 'bar',
        data: { 
          labels, 
          datasets:[{ 
            label:'Quantity', 
            data: values, 
            backgroundColor: colors,
            borderColor: colors.map(c => c.replace('60%', '50%')),
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
          }] 
        },
        options: { 
          responsive: true,
          maintainAspectRatio: true,
          plugins:{ 
            legend:{ 
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(29, 56, 123, 0.95)',
              titleColor: '#E3E3E3',
              bodyColor: '#E3E3E3',
              borderColor: 'rgba(81, 213, 90, 0.3)',
              borderWidth: 1,
              padding: 12,
              cornerRadius: 8,
              titleFont: {
                family: 'onest',
                size: 14,
                weight: 600
              },
              bodyFont: {
                family: 'Onest',
                size: 13
              }
            }
          }, 
          scales:{ 
            x:{ 
              ticks:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 12
                }
              }, 
              grid:{ 
                color:'rgba(255,255,255,0.08)',
                drawBorder: false
              }
            }, 
            y:{ 
              beginAtZero: true,
              ticks:{ 
                color:'#E3E3E3',
                font: {
                  family: 'Onest',
                  size: 12
                }
              }, 
              grid:{ 
                color:'rgba(255,255,255,0.08)',
                drawBorder: false
              }
            } 
          },
          animation: {
            duration: 1500,
            easing: 'easeInOutQuart'
          }
        }
      });
    }
  })();

  const categoryData = <?php echo json_encode($categoryGroups); ?>;
  const defaultItemHTML = `<div class="item-pill">No category selected.</div>`;
  const defaultStatusHTML = `<div class="item-pill">Choose a category to display stock details.</div>`;
  let activeCategory = null;

  function toggleCategory(categoryKey, buttonEl) {
    const buttons = document.querySelectorAll('.category-button');
    const itemPanelMeta = document.getElementById('itemPanelMeta');
    const statusPanelMeta = document.getElementById('statusPanelMeta');
    const itemList = document.getElementById('itemList');
    const statusList = document.getElementById('statusList');

    if (!itemPanelMeta || !statusPanelMeta || !itemList || !statusList) return;

    if (activeCategory === categoryKey) {
      activeCategory = null;
      buttons.forEach(btn => btn.classList.remove('active'));
      itemPanelMeta.textContent = 'Select a category to view items.';
      statusPanelMeta.textContent = 'Current stock levels and status.';
      itemList.innerHTML = defaultItemHTML;
      statusList.innerHTML = defaultStatusHTML;
      return;
    }

    activeCategory = categoryKey;
    buttons.forEach(btn => btn.classList.remove('active'));
    if (buttonEl) {
      buttonEl.classList.add('active');
    }

    const category = categoryData[categoryKey];
    if (!category) {
      itemPanelMeta.textContent = 'No items found for this category.';
      statusPanelMeta.textContent = '';
      itemList.innerHTML = defaultItemHTML;
      statusList.innerHTML = defaultStatusHTML;
      return;
    }

    const products = category.items || [];
    const totalQty = category.total_qty || 0;

    itemPanelMeta.textContent = `${products.length} item${products.length !== 1 ? 's' : ''} in ${categoryKey}`;
    statusPanelMeta.textContent = `Total quantity: ${Number(totalQty).toLocaleString()}`;

    if (!products.length) {
      itemList.innerHTML = defaultItemHTML;
      statusList.innerHTML = `<div class="item-pill">No stock data available.</div>`;
      return;
    }

    const itemHTML = products.map(item => `
      <div class="item-pill">
        <div style="font-weight:600;">${escapeHtml(item.name || 'Unnamed product')}</div>
        <div style="font-size:11px; opacity:0.7; margin-top:4px;">SKU: ${escapeHtml(item.sku || 'N/A')}</div>
      </div>
    `).join('');
    itemList.innerHTML = itemHTML;

    const statusHTML = products.map(item => {
      const qty = parseInt(item.qty || 0, 10);
      const statusClass = qty < 20 ? 'status-low' : 'status-ok';
      const statusLabel = qty < 20 ? 'Low Stock' : 'In Stock';
      const unit = item.uom ? ` ${item.uom}` : '';
      const location = item.location ? ` • Loc: ${escapeHtml(item.location)}` : '';
      return `
        <div class="status-row">
          <div class="status-product">
            <span>${escapeHtml(item.name || 'Unnamed product')}</span>
            <span>SKU: ${escapeHtml(item.sku || 'N/A')}${location}</span>
          </div>
          <div class="status-qty">${qty.toLocaleString()}${unit}</div>
          <span class="status-pill ${statusClass}">${statusLabel}</span>
        </div>
      `;
    }).join('');
    statusList.innerHTML = statusHTML;
  }

  function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, function(match) {
      const escape = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      };
      return escape[match] || match;
    });
  }

  function downloadPDF() {
    alert('PDF download functionality would be implemented here. This requires a PDF library like TCPDF or FPDF.');
  }

  /**
   * NEW: True Excel (XLSX) Export using SheetJS
   * This replaces the old CSV-based function.
   */
  function downloadExcel() {
    // This 'items' variable is defined in the Chart.js script block
    const items = <?php echo json_encode($items); ?>; 
    
    if (items.length === 0) {
      alert('No data to export.');
      return;
    }

    // 1. Map the data to a more friendly format for the sheet
    const dataToExport = items.map(item => ({
      'SKU': item.sku,
      'Product Name': item.name,
      'Category': item.category,
      'Quantity': parseInt(item.qty || 0), // Ensure it's a number for Excel
      'Unit': item.uom,
      'Location': item.location
    }));

    // 2. Create a new worksheet from our JSON data
    const ws = XLSX.utils.json_to_sheet(dataToExport);

    // 3. (Optional but nice) Set column widths
    const cols = [
      { wch: 15 }, // SKU
      { wch: 40 }, // Product Name
      { wch: 20 }, // Category
      { wch: 10 }, // Quantity
      { wch: 10 }, // Unit
      { wch: 10 }  // Location
    ];
    ws['!cols'] = cols;
    
    // 4. Create a new workbook and append the worksheet
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Inventory Report');

    // 5. Generate the .xlsx file and trigger the download
    const fileName = `inventory_report_${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(wb, fileName);
  }
</script>
<?php
// 8. End the layout
renderLayoutEnd();
?>